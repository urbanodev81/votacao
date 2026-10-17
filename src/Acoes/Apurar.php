<?php

namespace UrbanoDev\Votacao\Acoes;

use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\VisibilidadeDoResultado;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Apuracao;

/**
 * Especificação 04. A regra de quem vê o quê mora AQUI, no servidor
 * (invariante 8) — a tela só desenha o que recebeu.
 */
class Apurar
{
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

        return $this->montar($votacao, $visivel);
    }

    public function paraPainel(Votacao $votacao): Apuracao
    {
        $votacao->refresh();
        // No formal, nem o painel vê a parcial: só a participação.
        $visivel = $votacao->perfil !== Perfil::Formal || $votacao->encerrada();

        return $this->montar($votacao, $visivel);
    }

    private function montar(Votacao $votacao, bool $comContagem): Apuracao
    {
        $votantes = $votacao->total_votantes;

        $opcoes = $votacao->opcoes()
            ->leftJoin('votacao_contagens as c', 'c.opcao_id', '=', 'votacao_opcoes.id')
            ->get(['votacao_opcoes.id', 'votacao_opcoes.rotulo', 'c.total'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'rotulo' => $o->rotulo,
                'total' => $comContagem ? (int) $o->total : null,
                // Sobre votantes: em escolha múltipla a soma passa de 100%.
                'percentual' => $comContagem && $votantes > 0 ? round($o->total * 100 / $votantes, 1) : null,
            ])
            ->all();

        return new Apuracao(
            votacao: $votacao->uuid,
            encerrada: $votacao->encerrada(),
            visivel: $comContagem,
            votantes: $votantes,
            opcoes: $opcoes,
        );
    }
}
