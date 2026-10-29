<?php

namespace UrbanoDev\Votacao\Contratos;

use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;

/**
 * Em que grupo do recorte da votação (`$votacao->recorte`) o eleitor cai.
 * Chamado dentro da transação do voto: o grupo fica congelado ali.
 */
interface ResolvedorDeRecorte
{
    /** `null` ou vazio = grupo desconhecido, que cai em "Outros". */
    public function recorteDe(Votacao $votacao, Eleitor $eleitor): ?string;
}
