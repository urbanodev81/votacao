<?php

namespace UrbanoDev\Votacao\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use UrbanoDev\Votacao\Enums\Identificacao;
use UrbanoDev\Votacao\Enums\ModoDeManifestacao;
use UrbanoDev\Votacao\Enums\Perfil;
use UrbanoDev\Votacao\Enums\Sigilo;
use UrbanoDev\Votacao\Enums\StatusDaVotacao;
use UrbanoDev\Votacao\Enums\TipoDeQuorum;
use UrbanoDev\Votacao\Enums\VisibilidadeDoResultado;
use UrbanoDev\Votacao\Suporte\Perfis;
use UrbanoDev\Votacao\Suporte\ValidadorDePerfil;

class Votacao extends Model
{
    protected $table = 'votacao_votacoes';

    protected $guarded = ['id', 'uuid', 'segredo', 'total_votantes', 'encerrada_em'];

    protected $hidden = ['segredo'];

    protected function casts(): array
    {
        return [
            'perfil' => Perfil::class,
            'identificacao' => Identificacao::class,
            'sigilo' => Sigilo::class,
            'manifestacao' => ModoDeManifestacao::class,
            'resultado' => VisibilidadeDoResultado::class,
            'status' => StatusDaVotacao::class,
            'quorum_tipo' => TipoDeQuorum::class,
            'recorte_minimo' => 'integer',
            'elegibilidade' => 'array',
            'abre_em' => 'datetime',
            'fecha_em' => 'datetime',
            'encerrada_em' => 'datetime',
            'segredo' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // `saving` roda antes de `creating`: os padrões do perfil entram aqui,
        // senão a validação veria os eixos vazios.
        static::saving(function (Votacao $votacao) {
            if (! $votacao->exists) {
                $votacao->status ??= StatusDaVotacao::Rascunho;
                $votacao->quorum_tipo ??= TipoDeQuorum::Nenhum;
                $votacao->escolha_minimo ??= 1;
                $votacao->recorte_minimo ??= 10;
                $votacao->escolha_maximo ??= $votacao->escolha_minimo;
                foreach (Perfis::padroes($votacao->perfil ??= Perfil::Enquete) as $eixo => $valor) {
                    $votacao->{$eixo} ??= $valor;
                }
            }

            ValidadorDePerfil::validar($votacao);
        });

        static::creating(function (Votacao $votacao) {
            $votacao->uuid ??= (string) Str::uuid();
            // Invariante 11: segredo próprio de cada votação.
            $votacao->segredo ??= base64_encode(random_bytes(32));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function escopo(): MorphTo
    {
        return $this->morphTo();
    }

    public function contexto(): MorphTo
    {
        return $this->morphTo();
    }

    public function opcoes(): HasMany
    {
        return $this->hasMany(Opcao::class)->orderBy('votacao_opcoes.ordem')->orderBy('votacao_opcoes.id');
    }

    public function participacoes(): HasMany
    {
        return $this->hasMany(Participacao::class);
    }

    public function contagens(): HasMany
    {
        return $this->hasMany(Contagem::class);
    }

    public function escritos(): HasMany
    {
        return $this->hasMany(Escrito::class);
    }

    public function recortes(): HasMany
    {
        return $this->hasMany(Recorte::class);
    }

    public function manifestacoes(): HasMany
    {
        return $this->hasMany(Manifestacao::class);
    }

    /**
     * Invariante 5: o escopo vem do resolver, e é filtrado explicitamente.
     * `null` = sistema de dono único (ou banco por tenant).
     */
    public function scopeDoEscopo(Builder $query, ?Model $escopo): Builder
    {
        if ($escopo === null) {
            return $query->whereNull('escopo_type')->whereNull('escopo_id');
        }

        return $query->where('escopo_type', $escopo->getMorphClass())->where('escopo_id', $escopo->getKey());
    }

    /**
     * Invariante 5: a porta de entrada por endereço. Votação de outro dono não
     * é "proibida", é inexistente — 404.
     */
    public static function localizar(?Model $escopo, string $uuid): self
    {
        return static::query()->doEscopo($escopo)->where('uuid', $uuid)->firstOrFail();
    }

    /** Invariante 11: HMAC com o segredo desta votação. */
    public function chaveDoEleitor(string $identificador): string
    {
        return hash_hmac('sha256', $identificador, (string) $this->segredo);
    }

    /** Invariante 2. Conferido de novo dentro da transação do voto. */
    public function aceitaVotosEm(CarbonInterface $agora): bool
    {
        return $this->status === StatusDaVotacao::Aberta
            && ($this->abre_em === null || $this->abre_em->lte($agora))
            && ($this->fecha_em === null || $this->fecha_em->gt($agora));
    }

    public function encerrada(): bool
    {
        return $this->status === StatusDaVotacao::Encerrada;
    }

    public function exigeElegibilidade(): bool
    {
        return ! empty($this->elegibilidade);
    }

    public function jaRecebeuVoto(): bool
    {
        return $this->participacoes()->exists();
    }
}
