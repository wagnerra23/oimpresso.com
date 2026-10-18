<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FISIO-004 — campos da biblioteca que o seed e a tela precisam (etapa 01 §3.8 e §3.10,
 * recorte mínimo; mídia, ligações, sinônimos e o resto do B-01 ficam para as US seguintes).
 *
 *  - `code`: identificador estável do conteúdo do oimpresso (ex.: `FIS-LOM-004`). O seed é
 *    idempotente por ele. Nulo no exercício próprio da clínica.
 *  - `equipment` (json): equipamento caseiro — faceta do filtro da tela (lista vazia = nenhum).
 *  - `review_status` + `reviewed_*`: estado da REVISÃO CLÍNICA do conteúdo global
 *    (`rascunho → em_revisao → publicado → retirado`, etapa 01 §3.8). Só `publicado` aparece
 *    para as clínicas. Nulo no exercício próprio (a clínica responde pelo dela). O revisor é
 *    guardado por nome + CREFITO, não por FK de `users`: a responsável técnica (Dra. Dejeane,
 *    ADR 0437) não é usuária do ERP.
 *
 * Só acrescenta colunas nulas a uma tabela nova do próprio módulo (US-FISIO-003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fisio_exercises', function (Blueprint $t) {
            if (! Schema::hasColumn('fisio_exercises', 'code')) {
                $t->string('code', 40)->nullable()->after('business_id');
                $t->unique('code', 'fisio_exercises_code_uq');
            }
            if (! Schema::hasColumn('fisio_exercises', 'equipment')) {
                $t->json('equipment')->nullable()->after('pose');
            }
            if (! Schema::hasColumn('fisio_exercises', 'review_status')) {
                $t->enum('review_status', ['rascunho', 'em_revisao', 'publicado', 'retirado'])->nullable()->after('is_custom');
                $t->string('reviewed_by_name', 120)->nullable()->after('review_status');
                $t->string('reviewed_crefito', 40)->nullable()->after('reviewed_by_name');
                $t->dateTime('reviewed_at')->nullable()->after('reviewed_crefito');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fisio_exercises', function (Blueprint $t) {
            if (Schema::hasColumn('fisio_exercises', 'code')) {
                $t->dropUnique('fisio_exercises_code_uq');
                $t->dropColumn(['code', 'equipment', 'review_status', 'reviewed_by_name', 'reviewed_crefito', 'reviewed_at']);
            }
        });
    }
};
