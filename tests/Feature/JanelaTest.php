<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Acoes\EncerrarVotacao;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Excecoes\VotoRecusado;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 2. */
class JanelaTest extends TestCase
{
    use RefreshDatabase;

    private function recusa(Votacao $votacao): void
    {
        try {
            $this->app->make(RegistrarVoto::class)($votacao, Eleitor::porAparelho('x'), [$votacao->opcoes()->value('id')]);
            $this->fail('Voto fora da janela deveria ser recusado.');
        } catch (VotoRecusado $e) {
            $this->assertSame('fora_da_janela', $e->motivo);
        }
        $this->assertSame(0, $votacao->refresh()->total_votantes);
    }

    public function test_rascunho_nao_recebe_voto(): void
    {
        $votacao = Votacao::query()->create(['perfil' => Perfil::Enquete, 'titulo' => 'T']);
        $votacao->opcoes()->create(['rotulo' => 'A']);
        $this->recusa($votacao);
    }

    public function test_antes_de_abrir_nao_recebe_voto(): void
    {
        $this->recusa($this->votacao(['abre_em' => now()->addHour()]));
    }

    public function test_depois_de_fechar_nao_recebe_voto(): void
    {
        $votacao = $this->votacao(['abre_em' => now()->subHours(2), 'fecha_em' => now()->addHour()]);
        $this->travel(2)->hours();
        $this->recusa($votacao);
    }

    public function test_encerrada_na_mao_nao_recebe_voto(): void
    {
        $votacao = $this->votacao();
        $this->app->make(EncerrarVotacao::class)($votacao);
        $this->recusa($votacao);
    }

    public function test_pagina_aberta_antes_do_fechamento_nao_ganha_prorrogacao(): void
    {
        $votacao = $this->votacao();
        $naTelaDaPessoa = $votacao->replicate()->forceFill(['id' => $votacao->id]); // cópia "velha", ainda aberta
        $this->app->make(EncerrarVotacao::class)($votacao);
        $naTelaDaPessoa->exists = true;
        $this->recusa($naTelaDaPessoa);
    }
}
