<?php

namespace UrbanoDev\Votacao\Excecoes;

use InvalidArgumentException;

class PerfilInvalido extends InvalidArgumentException
{
    /** @param array<string, string> $erros eixo => explicação */
    public function __construct(public readonly array $erros)
    {
        parent::__construct('Configuração de votação inválida: '.implode(' ', $erros));
    }
}
