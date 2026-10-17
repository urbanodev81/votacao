<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do módulo de votação (kit-desenvolvimento/modulos/votacao, especificação 01).
 *
 * Nenhuma coluna guarda IP (invariante 10), e nenhuma tabela liga a participação
 * à escolha no modo secreto (invariante 4): lá a escolha só incrementa
 * `votacao_contagens`. `votacao_votos` existe só para o modo aberto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votacao_votacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('slug')->nullable();
            $table->nullableMorphs('escopo');
            $table->nullableMorphs('contexto');
            $table->string('perfil', 20);
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('identificacao', 20);
            $table->string('sigilo', 20);
            $table->json('elegibilidade')->nullable()->comment('null = todos');
            $table->unsignedSmallInteger('escolha_minimo')->default(1);
            $table->unsignedSmallInteger('escolha_maximo')->default(1);
            $table->string('manifestacao', 20);
            $table->string('resultado', 20);
            $table->string('status', 20)->default('rascunho');
            $table->timestampTz('abre_em')->nullable();
            $table->timestampTz('fecha_em')->nullable();
            $table->timestampTz('encerrada_em')->nullable();
            $table->unsignedInteger('total_votantes')->default(0);
            $table->text('segredo')->nullable()->comment('HMAC desta votação, cifrado; apagado na retenção');
            $table->unsignedBigInteger('criado_por')->nullable();
            $table->timestampsTz();

            $table->unique(['escopo_type', 'escopo_id', 'slug']);
        });

        Schema::create('votacao_opcoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacao_id')->constrained('votacao_votacoes')->cascadeOnDelete();
            $table->string('rotulo');
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->nullableMorphs('opcionavel');
            $table->timestampsTz();
        });

        Schema::create('votacao_contagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacao_id')->constrained('votacao_votacoes')->cascadeOnDelete();
            $table->foreignId('opcao_id')->constrained('votacao_opcoes')->cascadeOnDelete();
            $table->unsignedInteger('total')->default(0);

            $table->unique(['votacao_id', 'opcao_id']);
        });

        Schema::create('votacao_participacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacao_id')->constrained('votacao_votacoes')->cascadeOnDelete();
            $table->string('chave_eleitor', 64)->nullable()->comment('HMAC; nulo depois da retenção');
            $table->string('estado', 20);
            $table->timestampTz('confirmada_em')->nullable();
            $table->string('recibo_hash', 64)->nullable();
            $table->timestampsTz();

            // Invariante 1: um voto por eleitor é garantido aqui, não num `if`.
            $table->unique(['votacao_id', 'chave_eleitor']);
        });

        // Só modo aberto.
        Schema::create('votacao_votos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('votacao_id')->constrained('votacao_votacoes')->cascadeOnDelete();
            $table->foreignId('participacao_id')->unique()->constrained('votacao_participacoes')->cascadeOnDelete();
        });

        Schema::create('votacao_voto_opcoes', function (Blueprint $table) {
            $table->foreignUuid('voto_id')->constrained('votacao_votos')->cascadeOnDelete();
            $table->foreignId('opcao_id')->constrained('votacao_opcoes')->cascadeOnDelete();

            $table->primary(['voto_id', 'opcao_id']);
        });

        Schema::create('votacao_manifestacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('votacao_id')->constrained('votacao_votacoes')->cascadeOnDelete();
            $table->foreignId('opcao_id')->nullable()->comment('só com "mostrar meu voto"')
                ->constrained('votacao_opcoes')->nullOnDelete();
            $table->string('nome_exibido', 60)->nullable();
            $table->string('texto', 280)->nullable();
            $table->string('link_rede', 500)->nullable();
            $table->string('estado', 20)->default('pendente');
            $table->boolean('atencao')->default(false)->comment('filtro de palavras: ajuda, não decide');
            $table->unsignedBigInteger('moderado_por')->nullable();
            $table->timestampTz('moderado_em')->nullable();
            $table->string('motivo_rejeicao')->nullable();
            $table->string('token_remocao_hash', 64);
            $table->timestampsTz();

            $table->index(['votacao_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votacao_manifestacoes');
        Schema::dropIfExists('votacao_voto_opcoes');
        Schema::dropIfExists('votacao_votos');
        Schema::dropIfExists('votacao_participacoes');
        Schema::dropIfExists('votacao_contagens');
        Schema::dropIfExists('votacao_opcoes');
        Schema::dropIfExists('votacao_votacoes');
    }
};
