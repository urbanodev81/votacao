<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use UrbanoDev\Votacao\Acoes\AbrirVotacao;
use UrbanoDev\Votacao\Acoes\EncerrarVotacao;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/**
 * Invariante 4, a parte que só o Postgres mostra: cada linha guarda o id da
 * transação que a gravou (`xmin`). Sem a regravação no encerramento, o contador
 * de cada opção teria o mesmo `xmin` da participação do último eleitor dela.
 *
 * Fora do `RefreshDatabase` de propósito: dentro de uma transação só, todo
 * mundo teria o mesmo `xmin` e o teste passaria sem provar nada.
 */
class RastroDeTransacaoTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (! getenv('VOTACAO_TESTE_PGSQL')) {
            $this->markTestSkipped('Só no Postgres (phpunit -c tests/phpunit.pgsql.xml).');
        }
        parent::setUp();
    }

    public function test_depois_de_encerrar_nenhum_contador_tem_o_xmin_de_uma_participacao(): void
    {
        $v = $this->votacao(['perfil' => Perfil::Interna, 'identificacao' => Identificacao::Conta]);
        $votar = $this->app->make(RegistrarVoto::class);
        $votar($v, Eleitor::porConta(1), $this->ids($v, 0));
        $votar($v, Eleitor::porConta(2), $this->ids($v, 1));

        $xminParticipacoes = DB::table('votacao_participacoes')->selectRaw('xmin::text as x')->pluck('x')->all();
        $antes = DB::table('votacao_contagens')->where('total', '>', 0)->selectRaw('xmin::text as x')->pluck('x')->all();
        $this->assertNotEmpty(array_intersect($antes, $xminParticipacoes), 'Premissa: antes de encerrar, o rastro existe.');

        $this->app->make(EncerrarVotacao::class)($v);

        $depois = DB::table('votacao_contagens')->where('votacao_id', $v->id)->selectRaw('xmin::text as x')->pluck('x')->all();
        $this->assertSame([], array_intersect($depois, $xminParticipacoes));
        $this->assertCount(1, array_unique($depois));
    }

    public function test_voto_escrito_tambem_perde_o_xmin_no_encerramento(): void
    {
        $v = Votacao::query()->create(['perfil' => Perfil::Interna, 'identificacao' => Identificacao::Conta, 'titulo' => 'Presidente']);
        $v->opcoes()->create(['rotulo' => 'Ana']);
        $v->opcoes()->create(['rotulo' => 'Outro', 'escrito' => true]);
        $this->app->make(AbrirVotacao::class)($v);
        // Um texto que só uma pessoa escreveu: o caso que identifica.
        $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(1), $this->ids($v, 1), 'Carla');

        $xminParticipacoes = DB::table('votacao_participacoes')->selectRaw('xmin::text as x')->pluck('x')->all();
        $antes = DB::table('votacao_escritos')->selectRaw('xmin::text as x')->pluck('x')->all();
        $this->assertNotEmpty(array_intersect($antes, $xminParticipacoes), 'Premissa: antes de encerrar, o rastro existe.');

        $this->app->make(EncerrarVotacao::class)($v);

        $depois = DB::table('votacao_escritos')->selectRaw('xmin::text as x')->pluck('x')->all();
        $this->assertSame([], array_intersect($depois, $xminParticipacoes));
        // Nem vizinho: savepoint dentro do voto daria um xid colado ao da participação.
        foreach ($xminParticipacoes as $x) {
            $this->assertNotContains((string) ((int) $x + 1), $antes, 'Escrito gravado em subtransação.');
        }
    }
}
