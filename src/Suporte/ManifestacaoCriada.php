<?php

namespace UrbanoDev\Votacao\Suporte;

use UrbanoDev\Votacao\Models\Manifestacao;

/**
 * O token de remoção só existe em claro aqui, na hora: o banco guarda o hash.
 * A tela entrega o link "apagar meu comentário" (e o e-mail, quando houver).
 */
final readonly class ManifestacaoCriada
{
    public function __construct(
        public Manifestacao $manifestacao,
        public string $tokenRemocao,
    ) {}
}
