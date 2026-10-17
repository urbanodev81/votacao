<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UrbanoDev\Votacao\Acoes\Manifestar;
use UrbanoDev\Votacao\Acoes\Moderar;
use UrbanoDev\Votacao\Acoes\RegistrarVoto;
use UrbanoDev\Votacao\Acoes\RemoverManifestacao;
use UrbanoDev\Votacao\Enums\EstadoDaManifestacao;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\ModoDeManifestacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Excecoes\ManifestacaoRecusada;
use UrbanoDev\Votacao\Models\Manifestacao;
use UrbanoDev\Votacao\Models\Participacao;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\Eleitor;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 6 e especificação 03. */
class ManifestacaoModeradaTest extends TestCase
{
    use RefreshDatabase;

    private function votou(Votacao $votacao, string $aparelho = 'a', int $opcao = 0): Participacao
    {
        return $this->app->make(RegistrarVoto::class)($votacao, Eleitor::porAparelho($aparelho), $this->ids($votacao, $opcao));
    }

    private function manifestar(Votacao $votacao, Participacao $participacao, array $dados = []): Manifestacao
    {
        return $this->app->make(Manifestar::class)($votacao, $participacao, $dados + ['nome_exibido' => 'Dani', 'aceite' => true])->manifestacao;
    }

    private function recusa(callable $acao): ManifestacaoRecusada
    {
        try {
            $acao();
        } catch (ManifestacaoRecusada $e) {
            return $e;
        }
        $this->fail('Deveria ter recusado.');
    }

    public function test_nasce_pendente_e_so_aparece_depois_de_aprovada(): void
    {
        $votacao = $this->votacao();
        $m = $this->manifestar($votacao, $this->votou($votacao), ['texto' => 'Toca de novo!', 'estado' => 'aprovada']);

        $this->assertSame(EstadoDaManifestacao::Pendente, $m->refresh()->estado);
        $this->assertSame(0, $votacao->manifestacoes()->publicadas()->count());

        $this->app->make(Moderar::class)->aprovar($votacao, $m->id, 7);

        $publicada = $votacao->manifestacoes()->publicadas()->sole();
        $this->assertSame('Toca de novo!', $publicada->texto);
        $this->assertSame(7, (int) $publicada->moderado_por);
    }

    public function test_votacao_sem_manifestacao_recusa(): void
    {
        $votacao = $this->votacao(['manifestacao' => ModoDeManifestacao::Desligada]);
        $participacao = $this->votou($votacao);

        $this->assertSame('desligada', $this->recusa(fn () => $this->manifestar($votacao, $participacao))->motivo);
    }

    public function test_so_quem_votou_nesta_votacao(): void
    {
        $votacao = $this->votacao();
        $outra = $this->votacao();

        $this->assertSame('sem_voto', $this->recusa(fn () => $this->manifestar($votacao, $this->votou($outra)))->motivo);
    }

    public function test_aceite_e_nome_sao_obrigatorios(): void
    {
        $votacao = $this->votacao();
        $participacao = $this->votou($votacao);

        $this->assertSame('aceite', $this->recusa(fn () => $this->manifestar($votacao, $participacao, ['aceite' => false]))->campo);
        $this->assertSame('nome_exibido', $this->recusa(fn () => $this->manifestar($votacao, $participacao, ['nome_exibido' => '  ']))->campo);
        $this->assertSame('texto', $this->recusa(fn () => $this->manifestar($votacao, $participacao, ['texto' => str_repeat('é', 281)]))->campo);
        $this->assertSame(0, Manifestacao::query()->count());
    }

    public function test_mostrar_voto_copia_a_opcao_escolhida(): void
    {
        $votacao = $this->votacao();
        $m = $this->manifestar($votacao, $this->votou($votacao, opcao: 2), ['mostrar_voto' => true]);

        $this->assertSame('Música C', $m->opcao->rotulo);
    }

    public function test_mostrar_voto_so_no_modo_comentario_e_voto(): void
    {
        $votacao = $this->votacao(['manifestacao' => ModoDeManifestacao::Comentario]);
        $participacao = $this->votou($votacao);

        $this->assertSame('mostrar_voto', $this->recusa(fn () => $this->manifestar($votacao, $participacao, ['mostrar_voto' => true]))->campo);
    }

    public function test_mostrar_voto_com_varias_escolhas_recusa(): void
    {
        $votacao = $this->votacao(['escolha_maximo' => 2]);
        $participacao = $this->app->make(RegistrarVoto::class)($votacao, Eleitor::porAparelho('a'), $this->ids($votacao, 0, 1));

        $this->assertSame('mostrar_voto', $this->recusa(fn () => $this->manifestar($votacao, $participacao, ['mostrar_voto' => true]))->campo);
    }

    public function test_no_secreto_a_manifestacao_e_anonima(): void
    {
        $votacao = $this->votacao(['perfil' => Perfil::Interna, 'identificacao' => Identificacao::Conta, 'manifestacao' => ModoDeManifestacao::Comentario]);
        $participacao = $this->app->make(RegistrarVoto::class)($votacao, Eleitor::porConta(1), $this->ids($votacao, 0));
        $m = $this->manifestar($votacao, $participacao, ['texto' => 'Concordo']);

        $this->assertNull($m->opcao_id);
        $this->assertArrayNotHasKey('participacao_id', $m->refresh()->getAttributes());
    }

    public function test_palavra_de_atencao_sobe_na_fila_mas_nao_decide(): void
    {
        config(['votacao.palavras_de_atencao' => ['porcaria']]);
        $votacao = $this->votacao();
        $primeira = $this->manifestar($votacao, $this->votou($votacao, 'a'), ['texto' => 'Que show!']);
        $segunda = $this->manifestar($votacao, $this->votou($votacao, 'b'), ['texto' => 'Que PORCARÍA de som']);

        $this->assertSame(EstadoDaManifestacao::Pendente, $segunda->refresh()->estado);
        $this->assertSame([$segunda->id, $primeira->id], $votacao->manifestacoes()->naFila()->pluck('id')->all());
    }

    public function test_rejeitada_pode_ser_aprovada_e_o_motivo_fica_interno(): void
    {
        $votacao = $this->votacao();
        $m = $this->manifestar($votacao, $this->votou($votacao));
        $moderar = $this->app->make(Moderar::class);

        $moderar->rejeitar($votacao, [$m->id], 7, 'spam');
        $this->assertArrayNotHasKey('motivo_rejeicao', $m->refresh()->toArray());
        $this->assertSame('spam', $m->motivo_rejeicao);

        $moderar->aprovar($votacao, [$m->id], 7);
        $this->assertSame(EstadoDaManifestacao::Aprovada, $m->refresh()->estado);
        $this->assertNull($m->motivo_rejeicao);
    }

    public function test_lote_aprova_varias_e_pula_a_removida(): void
    {
        $votacao = $this->votacao();
        $criar = $this->app->make(Manifestar::class);
        $a = $this->manifestar($votacao, $this->votou($votacao, 'a'));
        $b = $this->manifestar($votacao, $this->votou($votacao, 'b'));
        $c = $criar($votacao, $this->votou($votacao, 'c'), ['nome_exibido' => 'Gui', 'aceite' => true]);
        $this->app->make(RemoverManifestacao::class)($votacao, $c->tokenRemocao);

        $this->assertSame(2, $this->app->make(Moderar::class)->aprovar($votacao, [$a->id, $b->id, $c->manifestacao->id], 7));
        $this->assertSame(EstadoDaManifestacao::Removida, $c->manifestacao->refresh()->estado);
    }

    public function test_remover_apaga_nome_texto_link_e_voto_na_hora(): void
    {
        $votacao = $this->votacao();
        $criada = $this->app->make(Manifestar::class)($votacao, $this->votou($votacao), [
            'nome_exibido' => 'Dani', 'texto' => 'Oi', 'link_rede' => 'https://instagram.com/dani', 'mostrar_voto' => true, 'aceite' => true,
        ]);
        $this->app->make(Moderar::class)->aprovar($votacao, $criada->manifestacao->id, 7);

        $this->app->make(RemoverManifestacao::class)($votacao, $criada->tokenRemocao);

        $m = $criada->manifestacao->refresh();
        $this->assertSame(EstadoDaManifestacao::Removida, $m->estado);
        $this->assertSame([null, null, null, null], [$m->nome_exibido, $m->texto, $m->link_rede, $m->opcao_id]);
        $this->assertSame(0, $votacao->manifestacoes()->publicadas()->count());
    }

    public function test_o_token_de_remocao_nao_fica_em_claro(): void
    {
        $votacao = $this->votacao();
        $criada = $this->app->make(Manifestar::class)($votacao, $this->votou($votacao), ['nome_exibido' => 'Dani', 'aceite' => true]);

        $this->assertSame(0, Manifestacao::query()->where('token_remocao_hash', $criada->tokenRemocao)->count());
        $this->assertArrayNotHasKey('token_remocao_hash', $criada->manifestacao->toArray());
    }
}
