<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use UrbanoDev\Votacao\Acoes\ConferirRecibo;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Contratos\ResolvedorDeElegibilidade;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Especificação 01, "Recibo": formal entrega; só o hash fica no banco. */
class ReciboTest extends TestCase
{
    use RefreshDatabase;

    public function test_formal_entrega_recibo_que_confere_so_na_propria_votacao(): void
    {
        $this->app->instance(ResolvedorDeElegibilidade::class, new class implements ResolvedorDeElegibilidade
        {
            public function podeVotar(Votacao $votacao, Eleitor $eleitor): bool
            {
                return $eleitor->usuario === 'ficha-1';
            }

            public function quantosPodem(Votacao $votacao): int
            {
                return 1;
            }

            public function quemAvisar(Votacao $votacao): iterable
            {
                return [];
            }
        });
        $formal = ['perfil' => Perfil::Formal, 'identificacao' => Identificacao::Documento, 'elegibilidade' => ['tipo' => 'associados']];
        $v = $this->votacao($formal);
        $outra = $this->votacao($formal);

        $p = $this->app->make(RegistrarVoto::class)($v, Eleitor::porDocumento('12345678909', 'ficha-1'), $this->ids($v, 0));

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/', $p->recibo);
        $this->assertStringNotContainsString($p->recibo, json_encode(DB::table('votacao_participacoes')->get()));
        $conferir = $this->app->make(ConferirRecibo::class);
        $this->assertTrue($conferir($v, strtolower(str_replace('-', ' ', $p->recibo))));
        $this->assertFalse($conferir($outra, $p->recibo));
        $this->assertFalse($conferir($v, 'AAAA-AAAA-AAAA-AAAA'));
    }

    public function test_enquete_nao_tem_recibo(): void
    {
        $v = $this->votacao();
        $p = $this->app->make(RegistrarVoto::class)($v, Eleitor::porAparelho('x'), $this->ids($v, 0));
        $this->assertNull($p->recibo);
        $this->assertNull($p->refresh()->recibo_hash);
    }
}
