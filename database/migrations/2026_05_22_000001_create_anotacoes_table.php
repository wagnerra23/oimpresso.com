<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave B — ADR 0179 Cliente drawer 760px.
 *
 * Tabela polimorfica `anotacoes` -- texto livre vinculado a qualquer
 * Subject (Contact, Sale, Repair, etc). Cobre o widget "Anotacoes" no
 * cabecalho do drawer (Wave C) + futuro re-uso em outras telas (Sale,
 * Repair JobSheet).
 *
 * Multi-tenant: `business_id` FK indexado obrigatorio (ADR 0093 Tier 0).
 * Cada anotacao pertence ao business; cross-tenant abort 404 server-side.
 *
 * LGPD: `body` pode conter PII (Larissa anota "cliente reclamou do
 * orcamento R$ [redacted Tier 0]"). Spatie ActivityLog v4.8 NAO loga properties desta
 * tabela (Wave F decide). softDeletes() preserva historico em estorno.
 *
 * Polimorfismo: `morphs('subject')` cria `subject_type` + `subject_id`
 * (Laravel canon). Indices compostos pra query rapida tipo "todas as
 * anotacoes do Contact 1234 do business 4".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('anotacoes')) {
            // Idempotente -- se ja existe (ambiente dev refeito), nao falha.
            return;
        }

        Schema::create('anotacoes', function (Blueprint $table) {
            $table->id();

            // Tenant scope obrigatorio (ADR 0093 IRREVOGAVEL).
            $table->unsignedBigInteger('business_id');

            // Polimorfismo Subject -- Contact, Sale, Repair, etc.
            // morphs cria subject_type + subject_id + indice composto.
            $table->morphs('subject');

            // Autor da anotacao.
            $table->unsignedBigInteger('user_id');

            // Corpo livre. TEXT cabe ~64KB (suficiente pra historico
            // cumulativo de comentarios curtos por cliente).
            $table->text('body');

            $table->timestamps();
            $table->softDeletes();

            // Indice tenant -- toda query filtra business_id primeiro.
            $table->index('business_id');

            // Indice composto -- "anotacoes do subject X no business Y".
            $table->index(['business_id', 'subject_type', 'subject_id'], 'anotacoes_biz_subject_index');

            // FKs com cascade -- se o business for excluido (TB-2050 super-admin),
            // anotacoes seguem. Mesmo pra user. SoftDeletes preserva historico.
            $table->foreign('business_id')
                ->references('id')
                ->on('business')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anotacoes');
    }
};
