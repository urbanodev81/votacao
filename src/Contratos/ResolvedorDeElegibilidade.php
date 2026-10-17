<?php

namespace UrbanoDev\Votacao\Contratos;

use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;

/**
 * Uma regra, três respostas (especificação 02): pode votar, quantos podem, para
 * quem vai o aviso. Responder em lugares diferentes faz o percentual mentir.
 */
interface ResolvedorDeElegibilidade
{
    public function podeVotar(Votacao $votacao, Eleitor $eleitor): bool;

    public function quantosPodem(Votacao $votacao): int;

    /** @return iterable<mixed> o que o Notificador do sistema souber avisar */
    public function quemAvisar(Votacao $votacao): iterable;
}
