<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\ModoDeManifestacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Enums\VisibilidadeDoResultado;
use UrbanoDev\Votacao\Excecoes\PerfilInvalido;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 9 e as combinações impossíveis da especificação 01. */
class PerfisTest extends TestCase
{
    use RefreshDatabase;

    public function test_enquete_nasce_com_os_padroes(): void
    {
        $v = Votacao::query()->create(['perfil' => Perfil::Enquete, 'titulo' => 'T']);
        $this->assertSame(Identificacao::Aparelho, $v->identificacao);
        $this->assertSame(Sigilo::Aberto, $v->sigilo);
        $this->assertSame(ModoDeManifestacao::ComentarioEVoto, $v->manifestacao);
    }

    public function test_formal_valido_grava(): void
    {
        $v = Votacao::query()->create(['perfil' => Perfil::Formal, 'titulo' => 'Assembleia', 'elegibilidade' => ['tipo' => 'filiados']]);
        $this->assertSame(Sigilo::Secreto, $v->sigilo);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function formaisFurados(): array
    {
        $base = ['elegibilidade' => ['tipo' => 'filiados']];

        return [
            'aberto' => [$base + ['sigilo' => Sigilo::Aberto], 'sigilo'],
            'com comentário' => [$base + ['manifestacao' => ModoDeManifestacao::Comentario], 'manifestacao'],
            'parcial pública' => [$base + ['resultado' => VisibilidadeDoResultado::Sempre], 'resultado'],
            'por aparelho' => [$base + ['identificacao' => Identificacao::Aparelho], 'identificacao'],
            'sem eleitorado' => [[], 'elegibilidade'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formaisFurados')]
    public function test_formal_trava_os_eixos(array $atributos, string $eixo): void
    {
        try {
            Votacao::query()->create($atributos + ['perfil' => Perfil::Formal, 'titulo' => 'T']);
            $this->fail("Formal com {$eixo} afrouxado deveria ser recusado.");
        } catch (PerfilInvalido $e) {
            $this->assertArrayHasKey($eixo, $e->erros);
        }
        $this->assertSame(0, Votacao::query()->count());
    }

    public function test_afrouxar_depois_de_criado_tambem_falha(): void
    {
        $v = Votacao::query()->create(['perfil' => Perfil::Formal, 'titulo' => 'T', 'elegibilidade' => ['tipo' => 'filiados']]);
        $this->expectException(PerfilInvalido::class);
        $v->update(['sigilo' => Sigilo::Aberto]);
    }

    public function test_mostrar_voto_nao_existe_no_secreto(): void
    {
        $this->expectException(PerfilInvalido::class);
        Votacao::query()->create(['perfil' => Perfil::Interna, 'titulo' => 'T', 'manifestacao' => ModoDeManifestacao::ComentarioEVoto]);
    }
}
