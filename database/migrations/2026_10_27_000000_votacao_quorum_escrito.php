<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.2.0 (especificação 01 e 04): quórum e voto escrito.
 *
 * No secreto o texto escrito não ganha linha por voto: vira contador por texto
 * em `votacao_escritos`, regravado no encerramento como `votacao_contagens`
 * (invariante 4). No aberto ele vai na própria linha da escolha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votacao_votacoes', function (Blueprint $table) {
            $table->string('quorum_tipo', 20)->default('nenhum');
            $table->unsignedInteger('quorum_valor')->nullable()->comment('% dos aptos ou nº de votantes');
        });

        Schema::table('votacao_opcoes', function (Blueprint $table) {
            $table->boolean('escrito')->default(false)->comment('"Outro — escrever"; no máximo uma por votação');
        });

        Schema::table('votacao_voto_opcoes', function (Blueprint $table) {
            $table->string('texto_escrito', 120)->nullable();
        });

        Schema::create('votacao_escritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacao_id')->constrained('votacao_votacoes')->cascadeOnDelete();
            $table->string('texto', 120);
            $table->unsignedInteger('total')->default(0);

            $table->unique(['votacao_id', 'texto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votacao_escritos');
        Schema::table('votacao_voto_opcoes', fn (Blueprint $t) => $t->dropColumn('texto_escrito'));
        Schema::table('votacao_opcoes', fn (Blueprint $t) => $t->dropColumn('escrito'));
        Schema::table('votacao_votacoes', fn (Blueprint $t) => $t->dropColumn(['quorum_tipo', 'quorum_valor']));
    }
};
