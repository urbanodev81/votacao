<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 4. */
class VotoSecretoTest extends TestCase
{
    use RefreshDatabase;

    public function test_secreto_nao_grava_linha_por_voto(): void
    {
        $votacao = $this->votacao(['perfil' => Perfil::Interna, 'identificacao' => Identificacao::Conta]);
        $this->assertSame(Sigilo::Secreto, $votacao->sigilo);

        $votar = $this->app->make(RegistrarVoto::class);
        $votar($votacao, Eleitor::porConta(1), $this->ids($votacao, 0));
        $votar($votacao, Eleitor::porConta(2), $this->ids($votacao, 1));

        $this->assertSame(0, DB::table('votacao_votos')->count());
        $this->assertSame(0, DB::table('votacao_voto_opcoes')->count());
        $this->assertSame(2, $votacao->participacoes()->count());
        $this->assertSame([1, 1, 0], $votacao->contagens()->orderBy('opcao_id')->pluck('total')->all());
    }

    public function test_a_participacao_nao_tem_coluna_que_aponte_para_a_escolha(): void
    {
        $colunas = Schema::getColumnListing('votacao_participacoes');
        foreach ($colunas as $coluna) {
            $this->assertDoesNotMatchRegularExpression('/opcao|voto|escolha/', $coluna, "Coluna {$coluna} liga participação à escolha.");
        }
    }

    public function test_contagem_nao_tem_horario(): void
    {
        $this->assertSame(['id', 'votacao_id', 'opcao_id', 'total'], Schema::getColumnListing('votacao_contagens'));
    }

    public function test_aberto_guarda_o_voto_para_mostrar_votou_em_x(): void
    {
        $votacao = $this->votacao();
        $participacao = $this->app->make(RegistrarVoto::class)($votacao, Eleitor::porAparelho('x'), $this->ids($votacao, 2));

        $this->assertSame(['Música C'], $participacao->voto->opcoes->pluck('rotulo')->all());
    }
}
