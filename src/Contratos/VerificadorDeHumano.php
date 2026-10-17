<?php

namespace UrbanoDev\Votacao\Contratos;

/** Nos nossos sistemas, o Altcha. Obrigatório nas formas `aparelho` e `email`. */
interface VerificadorDeHumano
{
    public function verificar(?string $resposta): bool;
}
