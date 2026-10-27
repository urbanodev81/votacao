<?php

namespace UrbanoDev\Votacao\Suporte;

use InvalidArgumentException;
use UrbanoDev\Votacao\Enums\Identificacao;

/**
 * Quem vota, já reduzido ao identificador normalizado (especificação 02).
 * O motor não sabe se veio de cookie, e-mail ou CPF; só compara chaves.
 */
final readonly class Eleitor
{
    private function __construct(
        public Identificacao $forma,
        public string $identificador,
        /** Id do usuário do sistema, para a costura de elegibilidade (forma `conta`). */
        public mixed $usuario = null,
    ) {
        if ($identificador === '') {
            throw new InvalidArgumentException('Eleitor sem identificador.');
        }
    }

    public static function porAparelho(string $token): self
    {
        return new self(Identificacao::Aparelho, 'aparelho:'.$token);
    }

    public static function porEmail(string $email): self
    {
        return new self(Identificacao::Email, 'email:'.mb_strtolower(trim($email)));
    }

    public static function porConta(int|string $usuarioId): self
    {
        return new self(Identificacao::Conta, 'conta:'.$usuarioId, $usuarioId);
    }

    /**
     * O documento chega já validado pela costura `VerificadorDeIdentidade` do sistema.
     * `$usuario` é a referência do sistema (ex.: a ficha do associado) para a costura
     * de elegibilidade; a chave continua sendo só o documento.
     */
    public static function porDocumento(string $documentoNormalizado, mixed $usuario = null): self
    {
        return new self(Identificacao::Documento, 'documento:'.$documentoNormalizado, $usuario);
    }
}
