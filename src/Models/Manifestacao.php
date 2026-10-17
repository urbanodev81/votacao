<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UrbanoDev\Votacao\Enums\EstadoDaManifestacao;

/** Especificação 03. Nasce pendente; só a aprovação publica (invariante 6). */
class Manifestacao extends Model
{
    protected $table = 'votacao_manifestacoes';

    protected $guarded = ['id', 'uuid', 'estado', 'moderado_por', 'moderado_em'];

    protected $hidden = ['token_remocao_hash', 'motivo_rejeicao'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoDaManifestacao::class,
            'atencao' => 'boolean',
            'moderado_em' => 'datetime',
        ];
    }

    public function votacao(): BelongsTo
    {
        return $this->belongsTo(Votacao::class);
    }

    public function opcao(): BelongsTo
    {
        return $this->belongsTo(Opcao::class);
    }

    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('estado', EstadoDaManifestacao::Aprovada)->latest();
    }

    public function scopeNaFila(Builder $query): Builder
    {
        return $query->where('estado', EstadoDaManifestacao::Pendente)->orderByDesc('atencao')->oldest();
    }
}
