<?php

namespace UrbanoDev\Votacao\Suporte;

use JsonSerializable;

final readonly class Apuracao implements JsonSerializable
{
    /**
     * @param  list<array{id:int, rotulo:string, escrito:bool, total:?int, percentual:?float}>  $opcoes
     * @param  ?int  $aptos  null quando a votação não tem eleitorado definido
     * @param  ?array{tipo:string, valor:int, exigido:int, atingido:bool}  $quorum
     * @param  ?list<array{texto:string, total:int}>  $escritos  só no painel, com a contagem visível
     * @param  ?array{nome:string, minimo:int, grupos:list<array{valor:string, outros:bool, votantes:int, opcoes:array<int,int>}>}  $recorte
     *         placar por grupo, com a contagem visível; `opcoes` = id da opção => total
     */
    public function __construct(
        public string $votacao,
        public bool $encerrada,
        public bool $visivel,
        public int $votantes,
        public array $opcoes,
        public ?int $aptos = null,
        public ?array $quorum = null,
        public ?array $escritos = null,
        public ?array $recorte = null,
    ) {}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
