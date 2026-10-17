<?php

namespace UrbanoDev\Votacao\Acoes;

use UrbanoDev\Votacao\Enums\EstadoDaManifestacao;
use UrbanoDev\Votacao\Models\Manifestacao;
use UrbanoDev\Votacao\Models\Votacao;

/**
 * "Apagar meu comentário" (especificação 03): nome, texto, link e voto
 * mostrado somem na hora; fica só a linha, para a contagem da moderação.
 * Token de outra votação é 404 (invariante 5).
 */
class RemoverManifestacao
{
    public function __invoke(Votacao $votacao, string $token): Manifestacao
    {
        $manifestacao = $votacao->manifestacoes()
            ->where('token_remocao_hash', hash('sha256', $token))
            ->firstOrFail();

        $manifestacao->forceFill([
            'estado' => EstadoDaManifestacao::Removida,
            'nome_exibido' => null,
            'texto' => null,
            'link_rede' => null,
            'opcao_id' => null,
        ])->save();

        return $manifestacao;
    }
}
