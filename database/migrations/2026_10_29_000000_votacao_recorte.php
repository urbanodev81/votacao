<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.3.0 (especificação 01, "Placar por recorte"): um recorte por votação.
 *
 * Contador por grupo e opção, como numa seção eleitoral. No secreto, grupo
 * abaixo do mínimo é fundido em "Outros" (`valor = ''`) no encerramento e o
 * resto regravado junto com as contagens (invariante 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votacao_votacoes', function (Blueprint $table) {
            $table->string('recorte', 40)->nullable()->comment('nome do recorte do sistema; trava no 1º voto');
            $table->unsignedSmallInteger('recorte_minimo')->default(10)->comment('votantes para o grupo aparecer; nunca abaixo de 5');
        });

        Schema::create('votacao_recortes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacao_id')->constrained('votacao_votacoes')->cascadeOnDelete();
            $table->string('valor', 120)->comment("'' = Outros");
            $table->unsignedInteger('votantes')->default(0);

            $table->unique(['votacao_id', 'valor']);
        });

        Schema::create('votacao_recorte_contagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorte_id')->constrained('votacao_recortes')->cascadeOnDelete();
            $table->foreignId('opcao_id')->constrained('votacao_opcoes')->cascadeOnDelete();
            $table->unsignedInteger('total')->default(0);

            $table->unique(['recorte_id', 'opcao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votacao_recorte_contagens');
        Schema::dropIfExists('votacao_recortes');
        Schema::table('votacao_votacoes', fn (Blueprint $t) => $t->dropColumn(['recorte', 'recorte_minimo']));
    }
};
