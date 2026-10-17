<?php

namespace UrbanoDev\Votacao\Excecoes;

use RuntimeException;

/**
 * A manifestação não foi registrada. Como em `VotoRecusado`, o `motivo` é
 * estável (a tela traduz) e `campo` aponta o que corrigir no formulário.
 */
class ManifestacaoRecusada extends RuntimeException
{
    public function __construct(public readonly string $motivo, string $mensagem, public readonly ?string $campo = null)
    {
        parent::__construct($mensagem);
    }

    public static function desligada(): self
    {
        return new self('desligada', 'Esta votação não aceita manifestação.');
    }

    public static function semVoto(): self
    {
        return new self('sem_voto', 'A manifestação vem depois do voto.');
    }

    public static function semAceite(): self
    {
        return new self('sem_aceite', 'Falta o aceite de que nome, comentário e link podem aparecer na página.', 'aceite');
    }

    public static function campoInvalido(string $campo, string $mensagem): self
    {
        return new self('campo_invalido', $mensagem, $campo);
    }
}
