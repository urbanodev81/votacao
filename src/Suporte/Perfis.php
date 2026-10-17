<?php

namespace UrbanoDev\Votacao\Suporte;

use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\ModoDeManifestacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Enums\VisibilidadeDoResultado;

/** Os padrões de cada perfil (especificação 01, "Os perfis"). */
final class Perfis
{
    /** @return array<string, mixed> */
    public static function padroes(Perfil $perfil): array
    {
        return match ($perfil) {
            Perfil::Enquete => [
                'identificacao' => Identificacao::Aparelho,
                'sigilo' => Sigilo::Aberto,
                'manifestacao' => ModoDeManifestacao::ComentarioEVoto,
                'resultado' => VisibilidadeDoResultado::AposVotar,
            ],
            Perfil::Interna => [
                'identificacao' => Identificacao::Conta,
                'sigilo' => Sigilo::Secreto,
                'manifestacao' => ModoDeManifestacao::Desligada,
                'resultado' => VisibilidadeDoResultado::NoFechamento,
            ],
            Perfil::Formal => [
                'identificacao' => Identificacao::Documento,
                'sigilo' => Sigilo::Secreto,
                'manifestacao' => ModoDeManifestacao::Desligada,
                'resultado' => VisibilidadeDoResultado::NoFechamento,
            ],
        };
    }
}
