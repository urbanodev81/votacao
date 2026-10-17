<?php

namespace UrbanoDev\Votacao\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\VotacaoServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [VotacaoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');

        // Os invariantes 1 e 2 dependem do banco (índice único, FOR UPDATE):
        // `phpunit -c tests/phpunit.pgsql.xml` roda a mesma suíte num Postgres.
        if (getenv('VOTACAO_TESTE_PGSQL')) {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'votacao',
                'username' => getenv('DB_USERNAME') ?: 'postgres',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);
        }
    }

    /** Votação aberta com as opções dadas. */
    protected function votacao(array $atributos = [], array $opcoes = ['Música A', 'Música B', 'Música C']): Votacao
    {
        $votacao = Votacao::query()->create($atributos + ['perfil' => Perfil::Enquete, 'titulo' => 'Qual a saideira?']);
        foreach ($opcoes as $i => $rotulo) {
            $votacao->opcoes()->create(['rotulo' => $rotulo, 'ordem' => $i]);
        }
        ($this->app->make(\UrbanoDev\Votacao\Acoes\AbrirVotacao::class))($votacao);

        return $votacao->refresh();
    }

    /** @return list<int> */
    protected function ids(Votacao $votacao, int ...$posicoes): array
    {
        $todas = $votacao->opcoes()->pluck('id')->all();

        return array_map(fn ($p) => $todas[$p], $posicoes);
    }
}
