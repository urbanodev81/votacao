<?php

namespace UrbanoDev\Votacao\Excecoes;

use RuntimeException;

/**
 * O voto não foi registrado. O `motivo` é estável (a tela traduz); a mensagem
 * é para log. "Já votou" e "fora da janela" não são erro do sistema: são a
 * regra funcionando.
 */
class VotoRecusado extends RuntimeException
{
    public function __construct(public readonly string $motivo, string $mensagem)
    {
        parent::__construct($mensagem);
    }

    public static function jaVotou(): self
    {
        return new self('ja_votou', 'Este eleitor já votou nesta votação.');
    }

    public static function foraDaJanela(): self
    {
        return new self('fora_da_janela', 'A votação não está aberta agora.');
    }

    public static function escolhaInvalida(string $detalhe): self
    {
        return new self('escolha_invalida', $detalhe);
    }

    public static function inelegivel(): self
    {
        return new self('inelegivel', 'Este eleitor não pode votar nesta votação.');
    }
}
