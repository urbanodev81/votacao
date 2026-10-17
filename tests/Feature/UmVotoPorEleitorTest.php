<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Enums\EstadoDaParticipacao;
use UrbanoDev\Votacao\Excecoes\VotoRecusado;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 1. */
class UmVotoPorEleitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_mesmo_aparelho_nao_vota_duas_vezes(): void
    {
        $votacao = $this->votacao();
        $votar = $this->app->make(RegistrarVoto::class);

        $votar($votacao, Eleitor::porAparelho('abc'), $this->ids($votacao, 0));

        try {
            $votar($votacao, Eleitor::porAparelho('abc'), $this->ids($votacao, 1));
            $this->fail('Segundo voto deveria ser recusado.');
        } catch (VotoRecusado $e) {
            $this->assertSame('ja_votou', $e->motivo);
        }

        $this->assertSame(1, $votacao->refresh()->total_votantes);
        $this->assertSame([1, 0, 0], $votacao->contagens()->orderBy('opcao_id')->pluck('total')->all());
    }

    public function test_aparelhos_diferentes_votam(): void
    {
        $votacao = $this->votacao();
        $votar = $this->app->make(RegistrarVoto::class);

        $votar($votacao, Eleitor::porAparelho('a'), $this->ids($votacao, 0));
        $votar($votacao, Eleitor::porAparelho('b'), $this->ids($votacao, 0));

        $this->assertSame(2, $votacao->refresh()->total_votantes);
    }

    public function test_a_unicidade_e_do_banco_nao_de_um_if(): void
    {
        $votacao = $this->votacao();
        $linha = ['chave_eleitor' => str_repeat('a', 64), 'estado' => EstadoDaParticipacao::Confirmada];
        $votacao->participacoes()->create($linha);

        $this->expectException(UniqueConstraintViolationException::class);
        $votacao->participacoes()->create($linha);
    }
}
