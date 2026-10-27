<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use UrbanoDev\Votacao\Acoes\Apurar;
use UrbanoDev\Votacao\Acoes\EncerrarVotacao;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Excecoes\PerfilInvalido;
use UrbanoDev\Votacao\Excecoes\VotoRecusado;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Especificação 01, "Voto escrito"; invariante 4 no secreto. */
class VotoEscritoTest extends TestCase
{
    use RefreshDatabase;

    private function comEscrito(array $atributos = []): Votacao
    {
        $v = Votacao::query()->create($atributos + ['perfil' => Perfil::Interna, 'identificacao' => Identificacao::Conta, 'titulo' => 'Presidente']);
        $v->opcoes()->create(['rotulo' => 'Ana', 'ordem' => 0]);
        $v->opcoes()->create(['rotulo' => 'Bruno', 'ordem' => 1]);
        $v->opcoes()->create(['rotulo' => 'Outro', 'ordem' => 2, 'escrito' => true]);
        ($this->app->make(\UrbanoDev\Votacao\Acoes\AbrirVotacao::class))($v);

        return $v->refresh();
    }

    private function motivo(callable $acao): string
    {
        try {
            $acao();
        } catch (VotoRecusado $e) {
            return $e->motivo;
        }
        $this->fail('Deveria ter recusado.');
    }

    public function test_secreto_vira_contador_por_texto_normalizado(): void
    {
        $v = $this->comEscrito();
        $votar = $this->app->make(RegistrarVoto::class);
        $votar($v, Eleitor::porConta(1), $this->ids($v, 2), '  Carla   Dias ');
        $votar($v, Eleitor::porConta(2), $this->ids($v, 2), 'carla dias');
        $votar($v, Eleitor::porConta(3), $this->ids($v, 0));

        $this->assertSame(0, DB::table('votacao_votos')->count());
        $this->assertSame([['carla dias', 2]], DB::table('votacao_escritos')->get(['texto', 'total'])->map(fn ($e) => [$e->texto, $e->total])->all());
        $this->assertSame([1, 0, 2], $v->contagens()->orderBy('opcao_id')->pluck('total')->all());
    }

    public function test_tabela_de_escritos_nao_aponta_para_quem_votou(): void
    {
        $this->assertSame(['id', 'votacao_id', 'texto', 'total'], Schema::getColumnListing('votacao_escritos'));
    }

    public function test_opcao_escrita_sem_texto_e_recusada(): void
    {
        $v = $this->comEscrito();
        $this->assertSame('escolha_invalida', $this->motivo(fn () => $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(1), $this->ids($v, 2), '   ')));
        $this->assertSame(0, $v->participacoes()->count());
    }

    public function test_texto_sem_a_opcao_escrita_e_recusado(): void
    {
        $v = $this->comEscrito();
        $this->assertSame('escolha_invalida', $this->motivo(fn () => $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(1), $this->ids($v, 0), 'Carla')));
    }

    public function test_so_uma_opcao_escrita_por_votacao(): void
    {
        $v = Votacao::query()->create(['titulo' => 'x']);
        $v->opcoes()->create(['rotulo' => 'Outro', 'escrito' => true]);
        $this->expectException(PerfilInvalido::class);
        $v->opcoes()->create(['rotulo' => 'Outro 2', 'escrito' => true]);
    }

    public function test_textos_so_no_painel_e_so_com_contagem_visivel(): void
    {
        $v = $this->comEscrito();
        $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(1), $this->ids($v, 2), 'Carla');
        $apurar = $this->app->make(Apurar::class);

        // Interna: painel vê a parcial e os textos; público (no fechamento) não vê nada.
        $this->assertSame([['texto' => 'carla', 'total' => 1]], $apurar->paraPainel($v)->escritos);
        $this->assertNull($apurar->paraPublico($v, jaVotou: true)->escritos);

        $this->app->make(EncerrarVotacao::class)($v);
        $publico = $apurar->paraPublico($v, jaVotou: true);
        $this->assertTrue($publico->visivel);
        $this->assertNull($publico->escritos, 'O público vê o total de "Outro", nunca os textos.');
        $this->assertSame(1, collect($publico->opcoes)->firstWhere('escrito', true)['total']);
    }

    public function test_aberto_guarda_o_texto_na_linha_da_escolha(): void
    {
        $v = $this->comEscrito(['perfil' => Perfil::Enquete, 'identificacao' => Identificacao::Aparelho]);
        $this->app->make(RegistrarVoto::class)($v, Eleitor::porAparelho('x'), $this->ids($v, 2), 'Carla');

        $this->assertSame(0, DB::table('votacao_escritos')->count());
        $this->assertSame('carla', DB::table('votacao_voto_opcoes')->value('texto_escrito'));
        $this->assertSame([['texto' => 'carla', 'total' => 1]], $this->app->make(Apurar::class)->paraPainel($v)->escritos);
    }
}
