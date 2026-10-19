<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favoritos e equipamento da biblioteca de exercícios (US-FISIO-004, comparativo Physitrack).
 *
 *  - `fisio_exercises.equipment`: lista JSON com chaves de `config('fisio.biblioteca.equipamentos')`
 *    (`etapas/01-biblioteca.md` §3.3). Nula no exercício que ainda não declarou equipamento.
 *  - `fisio_exercise_favorites`: favorito é do PROFISSIONAL dentro da EMPRESA. Um por
 *    (business, usuário, exercício). Favoritar um exercício global grava aqui, na empresa da
 *    sessão; o exercício global não muda (ADR 0437 §4). `business_id` NOT NULL com índice e FK
 *    (ADR 0093). Não é dado clínico: desfavoritar apaga a linha.
 *
 * Aditiva e idempotente: coluna nula e tabela nova, sem tocar em dado existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fisio_exercises') && ! Schema::hasColumn('fisio_exercises', 'equipment')) {
            Schema::table('fisio_exercises', function (Blueprint $t) {
                $t->json('equipment')->nullable()->after('pose');
            });
        }

        if (! Schema::hasTable('fisio_exercise_favorites')) {
            Schema::create('fisio_exercise_favorites', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('user_id');
                $t->foreignId('exercise_id')->constrained('fisio_exercises');
                $t->timestamps();
                $t->unique(['business_id', 'user_id', 'exercise_id'], 'fisio_ex_fav_biz_user_ex_uq');
                $t->index(['business_id', 'user_id'], 'fisio_ex_fav_biz_user_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('user_id')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_exercise_favorites');

        if (Schema::hasTable('fisio_exercises') && Schema::hasColumn('fisio_exercises', 'equipment')) {
            Schema::table('fisio_exercises', function (Blueprint $t) {
                $t->dropColumn('equipment');
            });
        }
    }
};
