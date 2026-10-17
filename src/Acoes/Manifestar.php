<?php

namespace UrbanoDev\Votacao\Acoes;

use Illuminate\Support\Str;
use UrbanoDev\Votacao\Enums\EstadoDaManifestacao;
use UrbanoDev\Votacao\Enums\EstadoDaParticipacao;
use UrbanoDev\Votacao\Enums\ModoDeManifestacao;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Excecoes\ManifestacaoRecusada;
use UrbanoDev\Votacao\Models\Manifestacao;
use UrbanoDev\Votacao\Models\Participacao;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\LinkDeRede;
use UrbanoDev\Votacao\Suporte\ManifestacaoCriada;

/**
 * Comentário, nome, link e "mostrar meu voto" (especificação 03).
 *
 * Entra SEMPRE pendente (invariante 6): não existe parâmetro que publique
 * direto. A participação prova que a pessoa votou; no modo secreto ela não é
 * gravada na manifestação, que fica anônima de verdade.
 */
class Manifestar
{
    /**
     * @param  array{nome_exibido?: ?string, texto?: ?string, link_rede?: ?string, mostrar_voto?: bool, aceite?: bool}  $dados
     */
    public function __invoke(Votacao $votacao, Participacao $participacao, array $dados): ManifestacaoCriada
    {
        if ($votacao->manifestacao === ModoDeManifestacao::Desligada) {
            throw ManifestacaoRecusada::desligada();
        }
        if ((int) $participacao->votacao_id !== (int) $votacao->id || $participacao->estado !== EstadoDaParticipacao::Confirmada) {
            throw ManifestacaoRecusada::semVoto();
        }
        if (empty($dados['aceite'])) {
            throw ManifestacaoRecusada::semAceite();
        }

        $nome = trim((string) ($dados['nome_exibido'] ?? ''));
        if ($nome === '' || mb_strlen($nome) > 60) {
            throw ManifestacaoRecusada::campoInvalido('nome_exibido', 'Nome de 1 a 60 caracteres.');
        }

        $texto = trim((string) ($dados['texto'] ?? '')) ?: null;
        if ($texto !== null && mb_strlen($texto) > 280) {
            throw ManifestacaoRecusada::campoInvalido('texto', 'Comentário de até 280 caracteres.');
        }

        $token = Str::random(40);

        $manifestacao = new Manifestacao([
            'nome_exibido' => $nome,
            'texto' => $texto,
            'link_rede' => LinkDeRede::validar($dados['link_rede'] ?? null),
            'opcao_id' => ! empty($dados['mostrar_voto']) ? $this->opcaoVotada($votacao, $participacao) : null,
            'token_remocao_hash' => hash('sha256', $token),
        ]);
        $manifestacao->forceFill([
            'uuid' => (string) Str::uuid(),
            'votacao_id' => $votacao->id,
            'estado' => EstadoDaManifestacao::Pendente,
            'atencao' => $this->pedeAtencao($nome.' '.$texto),
        ])->save();

        return new ManifestacaoCriada($manifestacao, $token);
    }

    private function opcaoVotada(Votacao $votacao, Participacao $participacao): int
    {
        if ($votacao->manifestacao !== ModoDeManifestacao::ComentarioEVoto || $votacao->sigilo !== Sigilo::Aberto) {
            throw ManifestacaoRecusada::campoInvalido('mostrar_voto', 'Esta votação não mostra o voto.');
        }

        $opcoes = $participacao->voto?->opcoes()->pluck('votacao_opcoes.id')->all() ?? [];
        if (count($opcoes) !== 1) {
            // "Votou em X" é uma opção só; com várias escolhas, mostrar uma mentiria.
            throw ManifestacaoRecusada::campoInvalido('mostrar_voto', 'Mostrar o voto só existe com uma escolha.');
        }

        return $opcoes[0];
    }

    /** Filtro de palavras: só marca para o topo da fila, nunca decide. */
    private function pedeAtencao(string $texto): bool
    {
        $normalizado = ' '.Str::lower(Str::ascii($texto)).' ';

        foreach (config('votacao.palavras_de_atencao', []) as $palavra) {
            $palavra = Str::lower(Str::ascii($palavra));
            if ($palavra !== '' && preg_match('/\b'.preg_quote($palavra, '/').'\b/u', $normalizado)) {
                return true;
            }
        }

        return false;
    }
}
