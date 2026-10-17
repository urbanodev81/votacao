<?php

namespace UrbanoDev\Votacao\Acoes;

use Illuminate\Support\Facades\DB;
use UrbanoDev\Votacao\Enums\StatusDaVotacao;
use UrbanoDev\Votacao\Models\Contagem;
use UrbanoDev\Votacao\Models\Votacao;

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

            return $votacao;
        });
    }
}
