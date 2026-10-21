<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sinônimos do exercício para a busca por texto da biblioteca (US-FISIO-004).
 *
 * [W] aceitou em 2026-10-18 a decisão nº 3 da ADR 0437 §14: a busca por texto entra na F1.
 * `etapas/01-biblioteca.md` §3.3 pede sinônimos ("ponte" = "elevação pélvica") e §3.10 propõe
 * uma tabela própria (`fisio_exercise_synonyms`, tarefa B-01). Aqui fica a forma mínima: uma
 * lista JSON no próprio exercício, que herda o escopo dele (global ou da clínica) sem tabela
 * nova para isolar. Se a B-01 normalizar em tabela, esta coluna migra para lá.
 *
 * Aditiva e idempotente: coluna nula, sem tocar em dado existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fisio_exercises') && ! Schema::hasColumn('fisio_exercises', 'synonyms')) {
            Schema::table('fisio_exercises', function (Blueprint $t) {
                $t->json('synonyms')->nullable()->after('steps');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fisio_exercises') && Schema::hasColumn('fisio_exercises', 'synonyms')) {
            Schema::table('fisio_exercises', function (Blueprint $t) {
                $t->dropColumn('synonyms');
            });
        }
    }
};
