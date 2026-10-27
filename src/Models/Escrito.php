<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Voto escrito no modo secreto: contador por texto, sem linha por voto
 * (invariante 4). No aberto o texto fica em `votacao_voto_opcoes`.
 */
class Escrito extends Model
{
    protected $table = 'votacao_escritos';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** Mesma grafia, mesmo contador: "  João  da Silva" e "joão da silva" somam juntos. */
    public static function normalizar(?string $texto): string
    {
        $texto = preg_replace('/\s+/u', ' ', trim((string) $texto));

        return mb_substr(mb_strtolower($texto), 0, 120);
    }
}
