<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Acoes\Manifestar;
use UrbanoDev\Votacao\Acoes\Moderar;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Acoes\RemoverManifestacao;
use UrbanoDev\Votacao\Enums\EstadoDaManifestacao;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Suporte\ManifestacaoCriada;
use UrbanoDev\Votacao\Tests\TestCase;

/**
 * Invariante 5: o dono vem do resolver; endereço de outro dono é 404.
 * O pacote não tem endpoint, então o teste é nas portas que os endpoints
 * dos sistemas usam: `Votacao::localizar`, `Moderar` e `RemoverManifestacao`.
 */
class EscopoTest extends TestCase
{
    use RefreshDatabase;

    private function dono(int $id): Model
    {
        return (new class extends Model
        {
            protected $table = 'donos';
        })->forceFill(['id' => $id]);
    }

    private function votacaoDe(?Model $dono): Votacao
    {
        $votacao = $this->votacao();
        if ($dono) {
            $votacao->forceFill(['escopo_type' => $dono->getMorphClass(), 'escopo_id' => $dono->getKey()])->save();
        }

        return $votacao;
    }

    private function manifestacao(Votacao $votacao, string $aparelho): ManifestacaoCriada
    {
        $participacao = $this->app->make(RegistrarVoto::class)($votacao, Eleitor::porAparelho($aparelho), $this->ids($votacao, 0));

        return $this->app->make(Manifestar::class)($votacao, $participacao, ['nome_exibido' => 'Fã', 'aceite' => true]);
    }

    public function test_localizar_acha_so_no_proprio_escopo(): void
    {
        $a = $this->votacaoDe($this->dono(1));
        $b = $this->votacaoDe($this->dono(2));

        $this->assertTrue($a->is(Votacao::localizar($this->dono(1), $a->uuid)));

        $this->expectException(ModelNotFoundException::class);
        Votacao::localizar($this->dono(1), $b->uuid);
    }

    public function test_dono_unico_nao_enxerga_votacao_com_escopo(): void
    {
        $comDono = $this->votacaoDe($this->dono(1));

        $this->expectException(ModelNotFoundException::class);
        Votacao::localizar(null, $comDono->uuid);
    }

    public function test_moderar_com_id_de_outra_votacao_e_404_e_nao_mexe_em_nada(): void
    {
        $minha = $this->votacaoDe($this->dono(1));
        $alheia = $this->votacaoDe($this->dono(2));
        $daMinha = $this->manifestacao($minha, 'a')->manifestacao;
        $daAlheia = $this->manifestacao($alheia, 'b')->manifestacao;

        try {
            $this->app->make(Moderar::class)->aprovar($minha, [$daMinha->id, $daAlheia->id], 7);
            $this->fail('Id de outra votação deveria dar 404.');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame(EstadoDaManifestacao::Pendente, $daMinha->refresh()->estado);
        $this->assertSame(EstadoDaManifestacao::Pendente, $daAlheia->refresh()->estado);
    }

    public function test_token_de_remocao_de_outra_votacao_e_404(): void
    {
        $minha = $this->votacaoDe($this->dono(1));
        $alheia = $this->votacaoDe($this->dono(2));
        $daAlheia = $this->manifestacao($alheia, 'b');

        try {
            $this->app->make(RemoverManifestacao::class)($minha, $daAlheia->tokenRemocao);
            $this->fail('Token de outra votação deveria dar 404.');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame('Fã', $daAlheia->manifestacao->refresh()->nome_exibido);
    }
}
