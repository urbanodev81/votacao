<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use UrbanoDev\Votacao\Enums\EstadoDaParticipacao;

/** "Quem votou". Nunca "em quê" — isso, só no modo aberto, fica em `Voto`. */
class Participacao extends Model
{
    protected $table = 'votacao_participacoes';

    protected $guarded = ['id'];

    protected $hidden = ['chave_eleitor', 'recibo_hash'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDaParticipacao::class,
            'confirmada_em' => 'datetime',
        ];
    }

    public function votacao(): BelongsTo
    {
        return $this->belongsTo(Votacao::class);
    }

    public function voto(): HasOne
    {
        return $this->hasOne(Voto::class);
    }
}
