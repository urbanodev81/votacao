<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Contratos\ResolvedorDeElegibilidade;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Excecoes\VotoRecusado;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

class EscolhaEElegibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private function motivo(callable $acao): string
    {
        try {
            $acao();
        } catch (VotoRecusado $e) {
            return $e->motivo;
        }
        $this->fail('Deveria ter recusado.');
    }

    public function test_escolha_unica_recusa_duas(): void
    {
        $v = $this->votacao();
        $this->assertSame('escolha_invalida', $this->motivo(fn () => $this->app->make(RegistrarVoto::class)($v, Eleitor::porAparelho('x'), $this->ids($v, 0, 1))));
    }

    public function test_as_tres_que_mais_gostaram(): void
    {
        $v = $this->votacao(['escolha_maximo' => 3], ['A', 'B', 'C', 'D']);
        $votar = $this->app->make(RegistrarVoto::class);

        $this->assertSame('escolha_invalida', $this->motivo(fn () => $votar($v, Eleitor::porAparelho('x'), $this->ids($v, 0, 1, 2, 3))));
        $votar($v, Eleitor::porAparelho('y'), $this->ids($v, 0, 1, 2));
        $this->assertSame([1, 1, 1, 0], $v->contagens()->orderBy('opcao_id')->pluck('total')->all());
    }

    public function test_opcao_de_outra_votacao_e_recusada(): void
    {
        $v = $this->votacao();
        $outra = $this->votacao();
        $this->assertSame('escolha_invalida', $this->motivo(fn () => $this->app->make(RegistrarVoto::class)($v, Eleitor::porAparelho('x'), $this->ids($outra, 0))));
    }

    private function interna(): Votacao
    {
        return $this->votacao(['perfil' => Perfil::Interna, 'identificacao' => Identificacao::Conta, 'elegibilidade' => ['tipo' => 'equipe']]);
    }

    public function test_costura_nao_ligada_recusa_em_vez_de_liberar(): void
    {
        $v = $this->interna();
        $this->assertSame('inelegivel', $this->motivo(fn () => $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(7), $this->ids($v, 0))));
    }

    public function test_costura_ligada_decide(): void
    {
        $this->app->bind(ResolvedorDeElegibilidade::class, fn () => new class implements ResolvedorDeElegibilidade
        {
            public function podeVotar(Votacao $votacao, Eleitor $eleitor): bool
            {
                return $eleitor->usuario === 7;
            }

            public function quantosPodem(Votacao $votacao): int
            {
                return 1;
            }

            public function quemAvisar(Votacao $votacao): iterable
            {
                return [7];
            }
        });

        $v = $this->interna();
        $votar = $this->app->make(RegistrarVoto::class);
        $votar($v, Eleitor::porConta(7), $this->ids($v, 0));
        $this->assertSame('inelegivel', $this->motivo(fn () => $votar($v, Eleitor::porConta(8), $this->ids($v, 0))));
    }

    public function test_forma_de_identificacao_errada_e_bug_do_sistema(): void
    {
        $v = $this->votacao();
        $this->expectException(\LogicException::class);
        $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(1), $this->ids($v, 0));
    }
}
