<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Excecoes\OpcaoTravada;
use UrbanoDev\Votacao\Models\Opcao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 3. */
class OpcoesTravadasTest extends TestCase
{
    use RefreshDatabase;

    public function test_antes_do_primeiro_voto_corrige_o_texto(): void
    {
        $votacao = $this->votacao();
        $opcao = $votacao->opcoes()->first();
        $opcao->update(['rotulo' => 'Música A (corrigida)']);
        $this->assertSame('Música A (corrigida)', $opcao->fresh()->rotulo);
    }

    private function comUmVoto(): array
    {
        $votacao = $this->votacao();
        $this->app->make(RegistrarVoto::class)($votacao, Eleitor::porAparelho('x'), $this->ids($votacao, 0));

        return [$votacao, $votacao->opcoes()->first()];
    }

    public function test_depois_do_voto_nao_troca_o_texto(): void
    {
        [, $opcao] = $this->comUmVoto();
        $this->expectException(OpcaoTravada::class);
        $opcao->update(['rotulo' => 'Outra']);
    }

    public function test_depois_do_voto_nao_inclui_opcao(): void
    {
        [$votacao] = $this->comUmVoto();
        $this->expectException(OpcaoTravada::class);
        $votacao->opcoes()->create(['rotulo' => 'Nova']);
    }

    public function test_depois_do_voto_nao_remove_opcao(): void
    {
        [, $opcao] = $this->comUmVoto();
        $this->expectException(OpcaoTravada::class);
        $opcao->delete();
    }

    public function test_depois_do_voto_reordenar_pode(): void
    {
        [, $opcao] = $this->comUmVoto();
        $opcao->update(['ordem' => 9]);
        $this->assertSame(9, Opcao::query()->find($opcao->id)->ordem);
    }
}
