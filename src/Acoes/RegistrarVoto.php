<?php

namespace UrbanoDev\Votacao\Acoes;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use UrbanoDev\Votacao\Contratos\ResolvedorDeElegibilidade;
use UrbanoDev\Votacao\Enums\EstadoDaParticipacao;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Excecoes\VotoRecusado;
use UrbanoDev\Votacao\Models\Contagem;
use UrbanoDev\Votacao\Models\Escrito;
use UrbanoDev\Votacao\Models\Participacao;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Models\Voto;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Suporte\Recibo;

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

    /**
     * @param  list<int|string>  $opcaoIds
     * @param  ?string  $escrito  texto da opção "Outro — escrever", se ela estiver entre as escolhidas
     */
    public function __invoke(Votacao $votacao, Eleitor $eleitor, array $opcaoIds, ?string $escrito = null): Participacao
    {
        if ($eleitor->forma === Identificacao::Email) {
            throw new LogicException('Voto por e-mail entra pendente; use o fluxo de confirmação.');
        }
        if ($eleitor->forma !== $votacao->identificacao) {
            throw new LogicException("A votação identifica por {$votacao->identificacao->value}, não por {$eleitor->forma->value}.");
        }

        return DB::transaction(function () use ($votacao, $eleitor, $opcaoIds, $escrito) {
            // A trava serializa os votos desta votação contra o encerramento:
            // ou o voto entra antes, ou vê a votação fechada (invariante 2).
            $votacao = Votacao::query()->lockForUpdate()->findOrFail($votacao->getKey());

            if (! $votacao->aceitaVotosEm(now())) {
                throw VotoRecusado::foraDaJanela();
            }

            $ids = $this->escolhaValida($votacao, $opcaoIds);
            [$opcaoEscrita, $texto] = $this->escritoValido($votacao, $ids, $escrito);

            if ($votacao->exigeElegibilidade() && ! $this->elegibilidade->podeVotar($votacao, $eleitor)) {
                throw VotoRecusado::inelegivel();
            }

            $recibo = $votacao->perfil === Perfil::Formal ? Recibo::gerar() : null;

            try {
                $participacao = $votacao->participacoes()->create([
                    'chave_eleitor' => $votacao->chaveDoEleitor($eleitor->identificador),
                    'estado' => EstadoDaParticipacao::Confirmada,
                    'confirmada_em' => now(),
                    'recibo_hash' => $recibo ? Recibo::hash($recibo) : null,
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
                $voto->opcoes()->attach(collect($ids)->mapWithKeys(
                    fn ($id) => [$id => ['texto_escrito' => $id === $opcaoEscrita ? $texto : null]],
                )->all());
            } elseif ($opcaoEscrita !== null) {
                // Secreto: o texto vira contador, como a opção (especificação 01).
                // Nada de firstOrCreate: ele abre um savepoint, que ganha um xid
                // vizinho ao da participação — rastro tão bom quanto o mesmo xid.
                // A votação está travada, então ler e depois gravar não tem corrida.
                $linha = Escrito::query()->where('votacao_id', $votacao->id)->where('texto', $texto);
                $linha->exists()
                    ? $linha->increment('total')
                    : Escrito::query()->insert(['votacao_id' => $votacao->id, 'texto' => $texto, 'total' => 1]);
            }

            Contagem::query()->where('votacao_id', $votacao->id)->whereIn('opcao_id', $ids)->increment('total');
            $votacao->newQuery()->whereKey($votacao->id)->increment('total_votantes');

            $participacao->recibo = $recibo;

            return $participacao;
        });
    }

    /**
     * @param  list<int>  $ids
     * @return array{0: ?int, 1: ?string} a opção escrita escolhida e o texto normalizado
     */
    private function escritoValido(Votacao $votacao, array $ids, ?string $escrito): array
    {
        $opcaoEscrita = $votacao->opcoes()->whereIn('id', $ids)->where('escrito', true)->value('id');
        $texto = Escrito::normalizar($escrito);

        if ($opcaoEscrita === null) {
            if ($texto !== '') {
                throw VotoRecusado::escolhaInvalida('Texto escrito sem escolher a opção de escrever.');
            }

            return [null, null];
        }
        if ($texto === '') {
            throw VotoRecusado::escolhaInvalida('Escreva o seu voto na opção "Outro".');
        }

        return [(int) $opcaoEscrita, $texto];
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
