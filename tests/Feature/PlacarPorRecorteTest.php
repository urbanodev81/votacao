<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use UrbanoDev\Votacao\Acoes\Apurar;
use UrbanoDev\Votacao\Acoes\EncerrarVotacao;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Contratos\ResolvedorDeRecorte;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Excecoes\PerfilInvalido;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Suporte\SupressaoDeRecortes;
use UrbanoDev\Votacao\Tests\TestCase;

/** Especificação 01, "Placar por recorte"; invariante 4 no secreto. */
class PlacarPorRecorteTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, ?string> conta => grupo */
    private array $grupos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ResolvedorDeRecorte::class, new class($this->grupos) implements ResolvedorDeRecorte
        {
            public function __construct(private array &$grupos) {}

            public function recorteDe(Votacao $votacao, Eleitor $eleitor): ?string
            {
                return $this->grupos[$eleitor->usuario] ?? null;
            }
        });
    }

    private function interna(array $atributos = []): Votacao
    {
        return $this->votacao($atributos + ['perfil' => Perfil::Interna, 'identificacao' => Identificacao::Conta, 'recorte' => 'empresa', 'recorte_minimo' => 5], ['Ana', 'Bruno']);
    }

    /** Vota `$quantos` eleitores do grupo, todos na opção da posição dada. */
    private function votos(Votacao $v, string $grupo, int $quantos, int $posicao): void
    {
        static $conta = 0;
        for ($i = 0; $i < $quantos; $i++) {
            $this->grupos[++$conta] = $grupo;
            $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta($conta), $this->ids($v, $posicao));
        }
    }

    /** @return array<string, array{int, list<int>}> grupo => [votantes, totais por opção] */
    private function placar(Votacao $v): array
    {
        return collect($this->app->make(Apurar::class)->paraPainel($v)->recorte['grupos'])
            ->mapWithKeys(fn ($g) => [($g['outros'] ? 'Outros' : $g['valor']) => [$g['votantes'], array_values($g['opcoes'])]])
            ->all();
    }

    public function test_conta_por_grupo_sem_linha_por_voto(): void
    {
        $v = $this->interna();
        $this->votos($v, 'Metalúrgica', 3, 0);
        $this->votos($v, 'Metalúrgica', 2, 1);

        $this->assertSame(0, DB::table('votacao_votos')->count());
        $this->assertSame([5], DB::table('votacao_recortes')->pluck('votantes')->all());
        $this->assertSame([3, 2], DB::table('votacao_recorte_contagens')->orderBy('opcao_id')->pluck('total')->all());
        $this->assertSame(['Metalúrgica' => [5, [3, 2]]], $this->placar($v));
    }

    public function test_tabelas_de_recorte_nao_apontam_para_quem_votou(): void
    {
        $this->assertSame(['id', 'votacao_id', 'valor', 'votantes'], Schema::getColumnListing('votacao_recortes'));
        $this->assertSame(['id', 'recorte_id', 'opcao_id', 'total'], Schema::getColumnListing('votacao_recorte_contagens'));
    }

    public function test_grupo_novo_nasce_com_todas_as_opcoes_zeradas(): void
    {
        $v = $this->interna();
        $this->votos($v, 'Padaria', 1, 1);

        // A ordem das linhas não diz em que o primeiro eleitor votou.
        $this->assertSame([0, 1], DB::table('votacao_recorte_contagens')->orderBy('id')->pluck('total')->all());
    }

    public function test_grupo_pequeno_vai_para_outros_na_leitura(): void
    {
        $v = $this->interna();
        $this->votos($v, 'Metalúrgica', 6, 0);
        $this->votos($v, 'Química', 5, 1);
        $this->votos($v, 'Padaria', 3, 0);
        $this->votos($v, 'Gráfica', 2, 1);

        $this->assertSame([
            'Metalúrgica' => [6, [6, 0]],
            'Química' => [5, [0, 5]],
            'Outros' => [5, [3, 2]],
        ], $this->placar($v));
    }

    public function test_outros_pequeno_puxa_o_menor_grupo_visivel(): void
    {
        $v = $this->interna();
        $this->votos($v, 'Metalúrgica', 7, 0);
        $this->votos($v, 'Química', 5, 1);
        $this->votos($v, 'Padaria', 1, 0);

        // Sem a fusão extra, 13 − 7 − 5 daria o voto do único da Padaria.
        $this->assertSame(['Metalúrgica' => [7, [7, 0]], 'Outros' => [6, [1, 5]]], $this->placar($v));
    }

    public function test_eleitor_sem_grupo_cai_em_outros(): void
    {
        $v = $this->interna();
        $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(999), $this->ids($v, 0));

        $this->assertSame([''], DB::table('votacao_recortes')->pluck('valor')->all());
    }

    public function test_encerramento_funde_os_grupos_pequenos_no_banco(): void
    {
        $v = $this->interna();
        $this->votos($v, 'Metalúrgica', 6, 0);
        $this->votos($v, 'Padaria', 3, 0);
        $this->votos($v, 'Gráfica', 2, 1);
        $antes = $this->placar($v);

        $this->app->make(EncerrarVotacao::class)($v);

        $this->assertEqualsCanonicalizing(['Metalúrgica', ''], DB::table('votacao_recortes')->pluck('valor')->all());
        $this->assertSame(4, DB::table('votacao_recorte_contagens')->count());
        $this->assertSame($antes, $this->placar($v));
        $this->assertSame(['Metalúrgica' => [6, [6, 0]], 'Outros' => [5, [3, 2]]], $this->placar($v));
    }

    public function test_aberto_nao_esconde_grupo(): void
    {
        $v = $this->votacao(['perfil' => Perfil::Enquete, 'identificacao' => Identificacao::Conta, 'recorte' => 'cidade'], ['Ana', 'Bruno']);
        $this->votos($v, 'Petrópolis', 1, 0);
        $this->app->make(EncerrarVotacao::class)($v);

        $this->assertSame(['Petrópolis' => [1, [1, 0]]], $this->placar($v));
        $this->assertSame(0, $this->app->make(Apurar::class)->paraPainel($v)->recorte['minimo']);
    }

    public function test_formal_nao_mostra_placar_antes_de_encerrar(): void
    {
        $v = Votacao::query()->create(['perfil' => Perfil::Formal, 'identificacao' => Identificacao::Documento, 'elegibilidade' => ['todos'], 'titulo' => 'Diretoria', 'recorte' => 'empresa']);

        $this->assertNull($this->app->make(Apurar::class)->paraPainel($v)->recorte);
    }

    public function test_sem_a_costura_o_voto_nao_entra(): void
    {
        $this->app->forgetInstance(ResolvedorDeRecorte::class);
        $this->app->bind(ResolvedorDeRecorte::class, \UrbanoDev\Votacao\Contratos\Recusa::class);
        $v = $this->interna();

        try {
            $this->app->make(RegistrarVoto::class)($v, Eleitor::porConta(1), $this->ids($v, 0));
            $this->fail('Deveria ter recusado.');
        } catch (LogicException) {
        }
        $this->assertSame(0, $v->participacoes()->count());
    }

    public function test_minimo_nunca_abaixo_de_cinco(): void
    {
        $this->expectException(PerfilInvalido::class);
        Votacao::query()->create(['titulo' => 'x', 'recorte' => 'empresa', 'recorte_minimo' => 4]);
    }

    public function test_minimo_padrao_e_dez(): void
    {
        $this->assertSame(10, Votacao::query()->create(['titulo' => 'x', 'recorte' => 'empresa'])->recorte_minimo);
    }

    public function test_recorte_trava_no_primeiro_voto(): void
    {
        $v = $this->interna();
        $v->update(['recorte_minimo' => 8]);
        $this->votos($v, 'Padaria', 1, 0);

        $this->expectException(PerfilInvalido::class);
        $v->refresh()->update(['recorte' => 'cidade']);
    }

    public function test_supressao_com_chave_numerica_e_empate(): void
    {
        $this->assertSame(['7', '9'], SupressaoDeRecortes::fundir([7 => 2, 8 => 9, 9 => 6, '' => 0], 5));
        // Outros = 4: puxa o menor visível; empate de tamanho decide pelo nome.
        $this->assertSame(['x', 'a'], SupressaoDeRecortes::fundir(['b' => 6, 'a' => 6, 'x' => 4], 5));
        $this->assertSame([], SupressaoDeRecortes::fundir(['a' => 5, 'b' => 9], 5));
        $this->assertSame(5, SupressaoDeRecortes::minimo(3));
    }
}
