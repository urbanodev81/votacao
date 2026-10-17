<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A apuração. No modo secreto é o ÚNICO lugar onde a escolha existe (invariante 4). */
class Contagem extends Model
{
    protected $table = 'votacao_contagens';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function opcao(): BelongsTo
    {
        return $this->belongsTo(Opcao::class);
    }
}
