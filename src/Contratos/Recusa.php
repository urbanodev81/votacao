<?php

namespace UrbanoDev\Votacao\Contratos;

use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;

/**
 * Binding padrão das costuras: RECUSA. Costura não ligada pelo sistema nunca
 * vira "sempre passa" — esquecer o binding trava a votação, não a abre.
 */
final class Recusa implements ResolvedorDeElegibilidade, VerificadorDeHumano
{
    public function podeVotar(Votacao $votacao, Eleitor $eleitor): bool
    {
        return false;
    }

    public function quantosPodem(Votacao $votacao): int
    {
        return 0;
    }

    public function quemAvisar(Votacao $votacao): iterable
    {
        return [];
    }

    public function verificar(?string $resposta): bool
    {
        return false;
    }
}
