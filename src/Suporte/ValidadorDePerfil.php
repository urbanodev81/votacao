<?php

namespace UrbanoDev\Votacao\Suporte;

use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\ModoDeManifestacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Enums\VisibilidadeDoResultado;
use UrbanoDev\Votacao\Excecoes\PerfilInvalido;
use UrbanoDev\Votacao\Models\Votacao;

/**
 * Combinações de eixos que não podem existir (especificação 01).
 *
 * Roda no `saving` da votação, então nenhum caminho grava uma votação furada.
 * A tela de configuração pode chamar `erros()` antes, para mostrar no formulário.
 */
class ValidadorDePerfil
{
    /** @return array<string, string> */
    public static function erros(Votacao $votacao): array
    {
        $erros = [];

        if ($votacao->manifestacao === ModoDeManifestacao::ComentarioEVoto && $votacao->sigilo === Sigilo::Secreto) {
            $erros['manifestacao'] = 'Mostrar o voto não existe em votação secreta.';
        }

        if ($votacao->escolha_minimo < 1 || $votacao->escolha_maximo < $votacao->escolha_minimo) {
            $erros['escolha'] = 'O máximo de escolhas precisa ser pelo menos o mínimo, e o mínimo pelo menos 1.';
        }

        if ($votacao->fecha_em && $votacao->abre_em && $votacao->fecha_em->lte($votacao->abre_em)) {
            $erros['janela'] = 'O fechamento precisa ser depois da abertura.';
        }

        // Invariante 9: o formal trava os eixos.
        if ($votacao->perfil === Perfil::Formal) {
            if ($votacao->sigilo !== Sigilo::Secreto) {
                $erros['sigilo'] = 'Votação formal é sempre secreta.';
            }
            if ($votacao->manifestacao !== ModoDeManifestacao::Desligada) {
                $erros['manifestacao'] = 'Votação formal não tem comentário.';
            }
            if (! in_array($votacao->resultado, [VisibilidadeDoResultado::NoFechamento, VisibilidadeDoResultado::SoPainel], true)) {
                $erros['resultado'] = 'Votação formal só mostra resultado no fechamento.';
            }
            if (! in_array($votacao->identificacao, [Identificacao::Documento, Identificacao::Conta], true)) {
                $erros['identificacao'] = 'Votação formal exige documento ou conta.';
            }
            if (empty($votacao->elegibilidade)) {
                $erros['elegibilidade'] = 'Votação formal exige o eleitorado definido.';
            }
        }

        return $erros;
    }

    public static function validar(Votacao $votacao): void
    {
        if ($erros = self::erros($votacao)) {
            throw new PerfilInvalido($erros);
        }
    }
}
