<?php

namespace UrbanoDev\Votacao\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UrbanoDev\Votacao\Excecoes\OpcaoTravada;
use UrbanoDev\Votacao\Excecoes\PerfilInvalido;

/**
 * O `rotulo` é copiado na criação: se a música do repertório mudar de nome
 * depois, o voto continua no texto que a pessoa leu.
 */
class Opcao extends Model
{
    protected $table = 'votacao_opcoes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['escrito' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Invariante 3.
        $trava = function (Opcao $opcao) {
            if ($opcao->votacao()->first()?->jaRecebeuVoto()) {
                throw new OpcaoTravada;
            }
        };

        // Reordenar não muda o que a pessoa leu; o resto trava.
        static::saving(function (Opcao $opcao) use ($trava) {
            if (! $opcao->exists || array_diff(array_keys($opcao->getDirty()), ['ordem', 'updated_at'])) {
                $trava($opcao);
            }
            if ($opcao->escrito && static::query()->where('votacao_id', $opcao->votacao_id)
                ->where('escrito', true)->whereKeyNot($opcao->getKey())->exists()) {
                throw new PerfilInvalido(['escrito' => 'Só uma opção de voto escrito por votação.']);
            }
        });
        static::deleting($trava);

        static::created(fn (Opcao $opcao) => Contagem::query()->create([
            'votacao_id' => $opcao->votacao_id,
            'opcao_id' => $opcao->id,
        ]));
    }

    public function votacao(): BelongsTo
    {
        return $this->belongsTo(Votacao::class);
    }

    public function opcionavel(): MorphTo
    {
        return $this->morphTo();
    }
}
