<?php

namespace UrbanoDev\Votacao\Suporte;

use UrbanoDev\Votacao\Excecoes\ManifestacaoRecusada;

/**
 * Invariante 7: link de rede só de host permitido. Fora da regra é recusado,
 * nunca "limpo" — consertar o link da pessoa é adivinhar o que ela quis.
 */
final class LinkDeRede
{
    public static function validar(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }

        $partes = parse_url($link);
        $hosts = config('votacao.hosts_de_rede', []);

        $valido = is_array($partes)
            && strlen($link) <= 500
            && strtolower($partes['scheme'] ?? '') === 'https'
            && ! isset($partes['user'])
            && ! isset($partes['pass'])
            && ! isset($partes['port'])
            && in_array(strtolower($partes['host'] ?? ''), $hosts, true);

        if (! $valido) {
            throw ManifestacaoRecusada::campoInvalido('link_rede', 'Link só do Instagram ou do Facebook, começando com https://.');
        }

        return $link;
    }

    /** O host que a página mostra ao lado do link, para ninguém clicar às cegas. */
    public static function host(string $link): string
    {
        return preg_replace('/^(www|m)\./', '', strtolower((string) parse_url($link, PHP_URL_HOST)));
    }
}
