<?php

namespace UrbanoDev\Votacao\Suporte;

use JsonSerializable;

final readonly class Apuracao implements JsonSerializable
{
    /** @param list<array{id:int, rotulo:string, total:?int, percentual:?float}> $opcoes */
    public function __construct(
        public string $votacao,
        public bool $encerrada,
        public bool $visivel,
        public int $votantes,
        public array $opcoes,
    ) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
