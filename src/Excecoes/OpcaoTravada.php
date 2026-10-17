<?php

namespace UrbanoDev\Votacao\Excecoes;

use LogicException;

/** Invariante 3: o voto foi dado no texto que a pessoa leu. */
class OpcaoTravada extends LogicException
{
    public function __construct()
    {
        parent::__construct('As opções não podem mudar depois do primeiro voto.');
    }
}
