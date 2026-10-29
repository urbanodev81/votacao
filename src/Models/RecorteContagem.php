<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Model;

/** Contador por grupo e opção: no secreto, sem linha por voto (invariante 4). */
class RecorteContagem extends Model
{
    protected $table = 'votacao_recorte_contagens';

    public $timestamps = false;

    protected $guarded = ['id'];
}
