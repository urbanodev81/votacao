<?php

namespace UrbanoDev\Votacao\Suporte;

/**
 * Recibo do perfil formal (especificação 01): 80 bits em base32, mostrado em
 * grupos de 4. Prova a participação, não a escolha. Só o hash vai ao banco.
 */
final class Recibo
{
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function gerar(): string
    {
        $bits = '';
        foreach (str_split(random_bytes(10)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $codigo = '';
        foreach (str_split($bits, 5) as $grupo) {
            $codigo .= self::ALFABETO[bindec($grupo)];
        }

        return implode('-', str_split($codigo, 4));
    }

    public static function hash(string $codigo): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo)));
    }
}
