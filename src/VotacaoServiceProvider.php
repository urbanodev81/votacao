<?php

namespace UrbanoDev\Votacao;

use Illuminate\Support\ServiceProvider;
use UrbanoDev\Votacao\Contratos\Recusa;
use UrbanoDev\Votacao\Contratos\ResolvedorDeElegibilidade;
use UrbanoDev\Votacao\Contratos\VerificadorDeHumano;

class VotacaoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/votacao.php', 'votacao');

        // Costura não ligada pelo sistema RECUSA (ver Contratos\Recusa).
        $this->app->bindIf(ResolvedorDeElegibilidade::class, Recusa::class);
        $this->app->bindIf(VerificadorDeHumano::class, Recusa::class);
    }

    public function boot(): void
    {
        // Banco por tenant (stancl): desligue e publique em database/migrations/tenant.
        if (config('votacao.carregar_migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'votacao-migrations');
        $this->publishes([__DIR__.'/../config/votacao.php' => config_path('votacao.php')], 'votacao-config');
    }
}
