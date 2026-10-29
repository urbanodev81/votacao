<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Um grupo do placar por recorte. `valor = ''` é "Outros". */
class Recorte extends Model
{
    public const OUTROS = '';

    protected $table = 'votacao_recortes';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function contagens(): HasMany
    {
        return $this->hasMany(RecorteContagem::class);
    }

    public static function normalizar(?string $valor): string
    {
        return mb_substr(trim((string) $valor), 0, 120);
    }
}
