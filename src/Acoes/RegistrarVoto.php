<?php

namespace UrbanoDev\Votacao\Acoes;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use UrbanoDev\Votacao\Contratos\ResolvedorDeElegibilidade;
use UrbanoDev\Votacao\Enums\EstadoDaParticipacao;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Excecoes\VotoRecusado;
use UrbanoDev\Votacao\Models\Contagem;
use UrbanoDev\Votacao\Models\Participacao;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Models\Voto;
use UrbanoDev\Votacao\Suporte\Eleitor;

/**
 * Registra um voto já confirmado (formas `aparelho`, `conta`, `documento`).
 *
 * O verificador de humano e o rate limit são da borda (controller do sistema):
 * aqui chega um eleitor já passado por eles. Tudo o que é invariante acontece
 * dentro de UMA transação, com a votação travada.
 */
class RegistrarVoto
{
    public function __construct(private ResolvedorDeElegibilidade $elegibilidade) {}

    /** @param list<int|string> $opcaoIds */
    public function __invoke(Votacao $votacao, Eleitor $eleitor, array $opcaoIds): Participacao
    {
        if ($eleitor->forma === Identificacao::Email) {
            throw new LogicException('Voto por e-mail entra pendente; use o fluxo de confirmação.');
        }
        if ($eleitor->forma !== $votacao->identificacao) {
            throw new LogicException("A votação identifica por {$votacao->identificacao->value}, não por {$eleitor->forma->value}.");
        }

        return DB::transaction(function () use ($votacao, $eleitor, $opcaoIds) {
            // A trava serializa os votos desta votação contra o encerramento:
            // ou o voto entra antes, ou vê a votação fechada (invariante 2).
            $votacao = Votacao::query()->lockForUpdate()->findOrFail($votacao->getKey());

            if (! $votacao->aceitaVotosEm(now())) {
                throw VotoRecusado::foraDaJanela();
            }

            $ids = $this->escolhaValida($votacao, $opcaoIds);

            if ($votacao->exigeElegibilidade() && ! $this->elegibilidade->podeVotar($votacao, $eleitor)) {
                throw VotoRecusado::inelegivel();
            }

            try {
                $participacao = $votacao->participacoes()->create([
                    'chave_eleitor' => $votacao->chaveDoEleitor($eleitor->identificador),
                    'estado' => EstadoDaParticipacao::Confirmada,
                    'confirmada_em' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Invariante 1: quem decide é o índice único, não um `exists()` antes.
                throw VotoRecusado::jaVotou();
            }

            // Invariante 4: no secreto, a escolha não ganha linha própria.
            if ($votacao->sigilo === Sigilo::Aberto) {
                $voto = Voto::query()->create([
                    'votacao_id' => $votacao->id,
                    'participacao_id' => $participacao->id,
                ]);
                $voto->opcoes()->attach($ids);
            }

            Contagem::query()->where('votacao_id', $votacao->id)->whereIn('opcao_id', $ids)->increment('total');
            $votacao->newQuery()->whereKey($votacao->id)->increment('total_votantes');

            return $participacao;
        });
    }

    /**
     * @param  list<int|string>  $opcaoIds
     * @return list<int>
     */
    private function escolhaValida(Votacao $votacao, array $opcaoIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $opcaoIds)));
        $quantas = count($ids);

        if ($quantas < $votacao->escolha_minimo || $quantas > $votacao->escolha_maximo) {
            throw VotoRecusado::escolhaInvalida(
                $votacao->escolha_minimo === $votacao->escolha_maximo
                    ? "Escolha {$votacao->escolha_maximo}."
                    : "Escolha de {$votacao->escolha_minimo} a {$votacao->escolha_maximo}."
            );
        }

        $existentes = $votacao->opcoes()->whereIn('id', $ids)->count();
        if ($existentes !== $quantas) {
            throw VotoRecusado::escolhaInvalida('Opção que não é desta votação.');
        }

        return $ids;
    }
}
