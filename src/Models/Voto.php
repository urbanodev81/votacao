<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Só modo aberto: é o que permite "votou em X" na manifestação. */
class Voto extends Model
{
    use HasUuids;

    protected $table = 'votacao_votos';

    public $timestamps = false;

    protected $guarded = [];

    public function participacao(): BelongsTo
    {
        return $this->belongsTo(Participacao::class);
    }

    public function opcoes(): BelongsToMany
    {
        return $this->belongsToMany(Opcao::class, 'votacao_voto_opcoes', 'voto_id', 'opcao_id')->withPivot('texto_escrito');
    }
}
