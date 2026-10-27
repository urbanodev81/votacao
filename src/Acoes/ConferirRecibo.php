<?php

namespace UrbanoDev\Votacao\Acoes;

use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Recibo;

/**
 * Quem guardou o recibo confere que a participação dele está registrada
 * nesta votação. Responde só sim ou não: o recibo não revela a escolha.
 */
class ConferirRecibo
{
    public function __invoke(Votacao $votacao, string $codigo): bool
    {
        return $votacao->participacoes()->where('recibo_hash', Recibo::hash($codigo))->exists();
    }
}
