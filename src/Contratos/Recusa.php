<?php

namespace UrbanoDev\Votacao\Contratos;

use LogicException;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;

/**
 * Binding padrão das costuras: RECUSA. Costura não ligada pelo sistema nunca
 * vira "sempre passa" — esquecer o binding trava a votação, não a abre.
 */
final class Recusa implements ResolvedorDeElegibilidade, ResolvedorDeRecorte, VerificadorDeHumano
{
    /** Sem a costura, o voto numa votação com recorte não entra: jogar todo mundo em "Outros" esconderia o erro. */
    public function recorteDe(Votacao $votacao, Eleitor $eleitor): ?string
    {
        throw new LogicException("A votação tem recorte ({$votacao->recorte}), mas o sistema não ligou o ResolvedorDeRecorte.");
    }

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
