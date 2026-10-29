<?php

namespace UrbanoDev\Votacao\Acoes;

use Illuminate\Support\Facades\DB;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Enums\StatusDaVotacao;
use UrbanoDev\Votacao\Models\Contagem;
use UrbanoDev\Votacao\Models\Escrito;
use UrbanoDev\Votacao\Models\Recorte;
use UrbanoDev\Votacao\Models\RecorteContagem;
use UrbanoDev\Votacao\Models\Votacao;
use UrbanoDev\Votacao\Suporte\SupressaoDeRecortes;

class EncerrarVotacao
{
    public function __invoke(Votacao $votacao): Votacao
    {
        return DB::transaction(function () use ($votacao) {
            $votacao = Votacao::query()->lockForUpdate()->findOrFail($votacao->getKey());

            if ($votacao->encerrada()) {
                return $votacao;
            }

            $votacao->forceFill([
                'status' => StatusDaVotacao::Encerrada,
                'encerrada_em' => now(),
            ])->save();

            // Invariante 4: cada contador guarda o xmin do ÚLTIMO voto naquela
            // opção. Regravar todos numa transação só apaga esse rastro.
            Contagem::query()->where('votacao_id', $votacao->id)->update(['total' => DB::raw('total')]);
            Escrito::query()->where('votacao_id', $votacao->id)->update(['total' => DB::raw('total')]);
            if ($votacao->sigilo === Sigilo::Secreto) {
                $this->fundirRecortes($votacao);
            }

            return $votacao;
        });
    }

    /**
     * Grupo abaixo do mínimo some do banco, somado em "Outros": um contador de
     * grupo com um votante só seria o voto dele. O resto é regravado (xmin).
     */
    private function fundirRecortes(Votacao $votacao): void
    {
        $grupos = Recorte::query()->where('votacao_id', $votacao->id)->get();
        $fundir = SupressaoDeRecortes::fundir(
            $grupos->mapWithKeys(fn (Recorte $g) => [$g->valor => (int) $g->votantes])->all(),
            SupressaoDeRecortes::minimo($votacao->recorte_minimo),
        );

        if ($fundir !== []) {
            $saem = $grupos->filter(fn (Recorte $g) => in_array($g->valor, $fundir, true));
            $outros = $grupos->firstWhere('valor', Recorte::OUTROS)?->id;
            if ($outros === null) {
                $outros = Recorte::query()->insertGetId(['votacao_id' => $votacao->id, 'valor' => Recorte::OUTROS, 'votantes' => 0]);
                RecorteContagem::query()->insert($votacao->opcoes()->pluck('votacao_opcoes.id')
                    ->map(fn ($opcao) => ['recorte_id' => $outros, 'opcao_id' => $opcao, 'total' => 0])->all());
            }

            $somas = RecorteContagem::query()->whereIn('recorte_id', $saem->modelKeys())
                ->groupBy('opcao_id')->selectRaw('opcao_id, sum(total) as soma')->pluck('soma', 'opcao_id');
            foreach ($somas as $opcao => $soma) {
                RecorteContagem::query()->where('recorte_id', $outros)->where('opcao_id', $opcao)->increment('total', (int) $soma);
            }
            Recorte::query()->whereKey($outros)->increment('votantes', $saem->sum('votantes'));
            // Sem depender do ON DELETE CASCADE (o SQLite só cumpre com a pragma ligada).
            RecorteContagem::query()->whereIn('recorte_id', $saem->modelKeys())->delete();
            Recorte::query()->whereKey($saem->modelKeys())->delete();
        }

        $restantes = Recorte::query()->where('votacao_id', $votacao->id)->select('id');
        Recorte::query()->where('votacao_id', $votacao->id)->update(['votantes' => DB::raw('votantes')]);
        RecorteContagem::query()->whereIn('recorte_id', $restantes)->update(['total' => DB::raw('total')]);
    }
}
