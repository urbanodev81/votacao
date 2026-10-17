<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariantes 10 e 11. */
class ChaveDoEleitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_mesmo_aparelho_tem_chaves_diferentes_em_cada_votacao(): void
    {
        $uma = $this->votacao();
        $outra = $this->votacao();
        $votar = $this->app->make(RegistrarVoto::class);

        $a = $votar($uma, Eleitor::porAparelho('mesmo'), $this->ids($uma, 0));
        $b = $votar($outra, Eleitor::porAparelho('mesmo'), $this->ids($outra, 0));

        $chaves = DB::table('votacao_participacoes')->whereIn('id', [$a->id, $b->id])->pluck('chave_eleitor');
        $this->assertCount(2, $chaves->unique());
        $this->assertStringNotContainsString('mesmo', $chaves->implode(''));
    }

    public function test_o_segredo_fica_cifrado_no_banco(): void
    {
        $votacao = $this->votacao();
        $cru = DB::table('votacao_votacoes')->where('id', $votacao->id)->value('segredo');
        $this->assertNotSame($votacao->segredo, $cru);
        $this->assertArrayNotHasKey('segredo', $votacao->toArray());
    }

    public function test_nenhuma_tabela_guarda_ip(): void
    {
        foreach (['votacao_votacoes', 'votacao_opcoes', 'votacao_contagens', 'votacao_participacoes', 'votacao_votos', 'votacao_manifestacoes'] as $tabela) {
            foreach (Schema::getColumnListing($tabela) as $coluna) {
                $this->assertDoesNotMatchRegularExpression('/(^|_)ip($|_)|user_agent/', $coluna, "{$tabela}.{$coluna}");
            }
        }
    }
}
