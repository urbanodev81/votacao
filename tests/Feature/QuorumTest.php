<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Acoes\Apurar;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Contratos\ResolvedorDeElegibilidade;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\TipoDeQuorum;
use UrbanoDev\Votacao\Excecoes\PerfilInvalido;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Especificação 04: quórum conta participação e é visível mesmo no formal aberto. */
class QuorumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Dez aptos; todo mundo pode votar.
        $this->app->instance(ResolvedorDeElegibilidade::class, new class implements ResolvedorDeElegibilidade
        {
            public function podeVotar(Votacao $votacao, Eleitor $eleitor): bool
            {
                return true;
            }

            public function quantosPodem(Votacao $votacao): int
            {
                return 10;
            }

            public function quemAvisar(Votacao $votacao): iterable
            {
                return [];
            }
        });
    }

    private function formal(array $quorum): Votacao
    {
        return $this->votacao($quorum + [
            'perfil' => Perfil::Formal,
            'identificacao' => Identificacao::Documento,
            'elegibilidade' => ['tipo' => 'associados'],
        ]);
    }

    public function test_percentual_dos_aptos_arredonda_para_cima(): void
    {
        $v = $this->formal(['quorum_tipo' => TipoDeQuorum::PercentualAptos, 'quorum_valor' => 25]);
        $votar = $this->app->make(RegistrarVoto::class);
        $votar($v, Eleitor::porDocumento('1'), $this->ids($v, 0));
        $votar($v, Eleitor::porDocumento('2'), $this->ids($v, 1));

        $q = $this->app->make(Apurar::class)->paraPainel($v);
        $this->assertSame(10, $q->aptos);
        $this->assertSame(3, $q->quorum['exigido']);
        $this->assertFalse($q->quorum['atingido']);
        // Formal aberto: quórum visível, contagem não.
        $this->assertFalse($q->visivel);

        $votar($v, Eleitor::porDocumento('3'), $this->ids($v, 0));
        $this->assertTrue($this->app->make(Apurar::class)->paraPainel($v)->quorum['atingido']);
    }

    public function test_absoluto(): void
    {
        $v = $this->formal(['quorum_tipo' => TipoDeQuorum::Absoluto, 'quorum_valor' => 1]);
        $this->app->make(RegistrarVoto::class)($v, Eleitor::porDocumento('1'), $this->ids($v, 0));

        $q = $this->app->make(Apurar::class)->paraPublico($v, jaVotou: true);
        $this->assertSame(['tipo' => 'absoluto', 'valor' => 1, 'exigido' => 1, 'atingido' => true], $q->quorum);
    }

    public function test_sem_quorum_nao_devolve_quorum(): void
    {
        $this->assertNull($this->app->make(Apurar::class)->paraPainel($this->votacao())->quorum);
    }

    public function test_percentual_sem_eleitorado_e_recusado(): void
    {
        $this->expectException(PerfilInvalido::class);
        $this->votacao(['quorum_tipo' => TipoDeQuorum::PercentualAptos, 'quorum_valor' => 50]);
    }

    public function test_percentual_acima_de_cem_e_recusado(): void
    {
        $this->expectException(PerfilInvalido::class);
        $this->formal(['quorum_tipo' => TipoDeQuorum::PercentualAptos, 'quorum_valor' => 120]);
    }

    public function test_absoluto_sem_valor_e_recusado(): void
    {
        $this->expectException(PerfilInvalido::class);
        $this->votacao(['quorum_tipo' => TipoDeQuorum::Absoluto]);
    }
}
