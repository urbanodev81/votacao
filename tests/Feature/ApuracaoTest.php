<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Acoes\Apurar;
use UrbanoDev\Votacao\Acoes\EncerrarVotacao;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\VisibilidadeDoResultado;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 8 e especificação 04. */
class ApuracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentual_sobre_votantes(): void
    {
        $v = $this->votacao(['resultado' => VisibilidadeDoResultado::Sempre]);
        $votar = $this->app->make(RegistrarVoto::class);
        $votar($v, Eleitor::porAparelho('a'), $this->ids($v, 0));
        $votar($v, Eleitor::porAparelho('b'), $this->ids($v, 0));
        $votar($v, Eleitor::porAparelho('c'), $this->ids($v, 1));

        $r = $this->app->make(Apurar::class)->paraPublico($v, jaVotou: false);

        $this->assertTrue($r->visivel);
        $this->assertSame(3, $r->votantes);
        $this->assertSame([2, 1, 0], array_column($r->opcoes, 'total'));
        $this->assertSame([66.7, 33.3, 0.0], array_column($r->opcoes, 'percentual'));
    }

    public function test_apos_votar_esconde_de_quem_nao_votou(): void
    {
        $v = $this->votacao();
        $apurar = $this->app->make(Apurar::class);

        $this->assertFalse($apurar->paraPublico($v, jaVotou: false)->visivel);
        $this->assertSame([null, null, null], array_column($apurar->paraPublico($v, jaVotou: false)->opcoes, 'total'));
        $this->assertTrue($apurar->paraPublico($v, jaVotou: true)->visivel);
    }

    public function test_no_fechamento_so_depois_de_encerrar(): void
    {
        $v = $this->votacao(['resultado' => VisibilidadeDoResultado::NoFechamento]);
        $apurar = $this->app->make(Apurar::class);

        $this->assertFalse($apurar->paraPublico($v, jaVotou: true)->visivel);
        $this->app->make(EncerrarVotacao::class)($v);
        $this->assertTrue($apurar->paraPublico($v, jaVotou: false)->visivel);
    }

    public function test_so_painel_nunca_vai_ao_publico(): void
    {
        $v = $this->votacao(['resultado' => VisibilidadeDoResultado::SoPainel]);
        $this->app->make(EncerrarVotacao::class)($v);

        $this->assertFalse($this->app->make(Apurar::class)->paraPublico($v, jaVotou: true)->visivel);
        $this->assertTrue($this->app->make(Apurar::class)->paraPainel($v)->visivel);
    }

    public function test_formal_nem_o_painel_ve_a_parcial(): void
    {
        $v = $this->votacao(['perfil' => Perfil::Formal, 'identificacao' => Identificacao::Conta, 'elegibilidade' => ['tipo' => 'filiados']]);
        $apurar = $this->app->make(Apurar::class);

        $parcial = $apurar->paraPainel($v);
        $this->assertFalse($parcial->visivel);
        $this->assertSame(0, $parcial->votantes); // a participação aparece; a contagem por opção não

        $this->app->make(EncerrarVotacao::class)($v);
        $this->assertTrue($apurar->paraPainel($v)->visivel);
    }
}
