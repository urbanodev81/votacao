<?php

namespace UrbanoDev\Votacao\Acoes;

use Illuminate\Support\Facades\DB;
use UrbanoDev\Votacao\Contratos\ResolvedorDeElegibilidade;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Enums\TipoDeQuorum;
use UrbanoDev\Votacao\Enums\VisibilidadeDoResultado;
use UrbanoDev\Votacao\Models\Recorte;
use UrbanoDev\Votacao\Models\RecorteContagem;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Apuracao;
use UrbanoDev\Votacao\Suporte\SupressaoDeRecortes;

/**
 * Especificação 04. A regra de quem vê o quê mora AQUI, no servidor
 * (invariante 8) — a tela só desenha o que recebeu.
 */
class Apurar
{
    public function __construct(private ResolvedorDeElegibilidade $elegibilidade) {}

    public function paraPublico(Votacao $votacao, bool $jaVotou): Apuracao
    {
        // A decisão sai do estado do banco, não da instância que chegou.
        $votacao->refresh();
        $visivel = match ($votacao->resultado) {
            VisibilidadeDoResultado::Sempre => true,
            VisibilidadeDoResultado::AposVotar => $jaVotou || $votacao->encerrada(),
            VisibilidadeDoResultado::NoFechamento => $votacao->encerrada(),
            VisibilidadeDoResultado::SoPainel => false,
        };

        // Os textos escritos nunca saem para o público: só o total da opção "Outro".
        return $this->montar($votacao, $visivel, comEscritos: false);
    }

    public function paraPainel(Votacao $votacao): Apuracao
    {
        $votacao->refresh();
        // No formal, nem o painel vê a parcial: só a participação.
        $visivel = $votacao->perfil !== Perfil::Formal || $votacao->encerrada();

        return $this->montar($votacao, $visivel, comEscritos: $visivel);
    }

    private function montar(Votacao $votacao, bool $comContagem, bool $comEscritos): Apuracao
    {
        $votantes = $votacao->total_votantes;

        $opcoes = $votacao->opcoes()
            ->leftJoin('votacao_contagens as c', 'c.opcao_id', '=', 'votacao_opcoes.id')
            ->get(['votacao_opcoes.id', 'votacao_opcoes.rotulo', 'votacao_opcoes.escrito', 'c.total'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'rotulo' => $o->rotulo,
                'escrito' => $o->escrito,
                'total' => $comContagem ? (int) $o->total : null,
                // Sobre votantes: em escolha múltipla a soma passa de 100%.
                'percentual' => $comContagem && $votantes > 0 ? round($o->total * 100 / $votantes, 1) : null,
            ])
            ->all();

        // Participação e quórum são sempre visíveis: contam quem votou, não em quê.
        $aptos = $votacao->exigeElegibilidade() ? $this->elegibilidade->quantosPodem($votacao) : null;

        return new Apuracao(
            votacao: $votacao->uuid,
            encerrada: $votacao->encerrada(),
            visivel: $comContagem,
            votantes: $votantes,
            opcoes: $opcoes,
            aptos: $aptos,
            quorum: $this->quorum($votacao, $votantes, $aptos),
            escritos: $comEscritos ? $this->escritos($votacao) : null,
            recorte: $comContagem && $votacao->recorte !== null ? $this->recorte($votacao) : null,
        );
    }

    /**
     * Especificação 01, "Placar por recorte". Depois do encerramento a fusão já
     * está no banco; antes, a mesma regra vale aqui, na leitura.
     *
     * @return array{nome:string, minimo:int, grupos:list<array{valor:string, outros:bool, votantes:int, opcoes:array<int,int>}>}
     */
    private function recorte(Votacao $votacao): array
    {
        $minimo = SupressaoDeRecortes::minimo($votacao->recorte_minimo);
        $opcoes = $votacao->opcoes()->pluck('votacao_opcoes.id')->all();
        $grupos = Recorte::query()->where('votacao_id', $votacao->id)->get(['id', 'valor', 'votantes']);
        $totais = RecorteContagem::query()->whereIn('recorte_id', $grupos->modelKeys())->get(['recorte_id', 'opcao_id', 'total'])
            ->groupBy('recorte_id');

        $fundir = $votacao->sigilo === Sigilo::Secreto
            ? SupressaoDeRecortes::fundir($grupos->mapWithKeys(fn ($g) => [$g->valor => (int) $g->votantes])->all(), $minimo)
            : [];

        $saida = [];
        foreach ($grupos as $g) {
            $valor = in_array($g->valor, $fundir, true) ? Recorte::OUTROS : $g->valor;
            $saida[$valor] ??= ['valor' => $valor, 'outros' => $valor === Recorte::OUTROS, 'votantes' => 0, 'opcoes' => array_fill_keys($opcoes, 0)];
            $saida[$valor]['votantes'] += (int) $g->votantes;
            foreach ($totais->get($g->id, []) as $c) {
                $saida[$valor]['opcoes'][$c->opcao_id] += (int) $c->total;
            }
        }

        // Maior grupo primeiro; "Outros" sempre por último.
        $saida = array_values($saida);
        usort($saida, fn ($a, $b) => [$a['outros'], -$a['votantes'], $a['valor']] <=> [$b['outros'], -$b['votantes'], $b['valor']]);

        return ['nome' => $votacao->recorte, 'minimo' => $votacao->sigilo === Sigilo::Secreto ? $minimo : 0, 'grupos' => $saida];
    }

    /** @return ?array{tipo:string, valor:int, exigido:int, atingido:bool} */
    private function quorum(Votacao $votacao, int $votantes, ?int $aptos): ?array
    {
        $exigido = match ($votacao->quorum_tipo) {
            TipoDeQuorum::Absoluto => (int) $votacao->quorum_valor,
            TipoDeQuorum::PercentualAptos => (int) ceil(($aptos ?? 0) * $votacao->quorum_valor / 100),
            default => null,
        };

        return $exigido === null ? null : [
            'tipo' => $votacao->quorum_tipo->value,
            'valor' => (int) $votacao->quorum_valor,
            'exigido' => $exigido,
            'atingido' => $votantes >= $exigido,
        ];
    }

    /** @return list<array{texto:string, total:int}> */
    private function escritos(Votacao $votacao): array
    {
        $secreto = $votacao->escritos()->orderByDesc('total')->orderBy('texto')->get(['texto', 'total']);
        if ($secreto->isNotEmpty()) {
            return $secreto->map(fn ($e) => ['texto' => $e->texto, 'total' => (int) $e->total])->all();
        }

        // Aberto: o texto está na linha da escolha.
        return DB::table('votacao_voto_opcoes as vo')
            ->join('votacao_votos as v', 'v.id', '=', 'vo.voto_id')
            ->where('v.votacao_id', $votacao->id)
            ->whereNotNull('vo.texto_escrito')
            ->groupBy('vo.texto_escrito')
            ->orderByRaw('count(*) desc')->orderBy('vo.texto_escrito')
            ->selectRaw('vo.texto_escrito as texto, count(*) as total')
            ->get()
            ->map(fn ($e) => ['texto' => $e->texto, 'total' => (int) $e->total])
            ->all();
    }
}
