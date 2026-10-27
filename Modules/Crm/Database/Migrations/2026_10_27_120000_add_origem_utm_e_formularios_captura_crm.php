<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-096 · captura de lead com origem e UTM.
 *
 * 1. `crm_deals` ganha a origem do negócio (`origem`) e os 5 parâmetros UTM. Negócio criado à
 *    mão no funil fica com `origem = 'manual'` (default); o que entra pelo formulário público
 *    grava `formulario` e os UTM da página onde o visitante estava.
 * 2. `crm_formularios_captura` — um formulário por empresa (ou vários): a `chave` pública é o
 *    único dado que o site do cliente conhece, e é dela que o endpoint resolve a empresa. O
 *    visitante nunca informa `business_id` (Tier 0, ADR 0093).
 *
 * O negócio aponta para cliente do cadastro (`contacts.type='customer'`), não para
 * `contacts.type='lead'`, que segue em depreciação (ADR 0441 §2).
 *
 * Idempotente (hasColumn/hasTable) + reversível.
 */
return new class extends Migration
{
    private const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function up(): void
    {
        if (Schema::hasTable('crm_deals') && ! Schema::hasColumn('crm_deals', 'origem')) {
            Schema::table('crm_deals', function (Blueprint $t) {
                $t->string('origem', 40)->default('manual')->after('stage')
                    ->comment('manual|formulario — de onde o negócio veio');
                foreach (self::UTM as $c) {
                    $t->string($c, 120)->nullable();
                }
                $t->index(['business_id', 'origem'], 'idx_crm_deals_biz_origem');
            });
        }

        if (! Schema::hasTable('crm_formularios_captura')) {
            Schema::create('crm_formularios_captura', function (Blueprint $t) {
                $t->bigIncrements('id');
                // business.id é INT UNSIGNED: a FK exige o mesmo tipo (MySQL 3780 com bigint).
                $t->unsignedInteger('business_id')->comment('Tier 0 ADR 0093 — resolvida pela chave, nunca pelo visitante');
                $t->foreign('business_id', 'crm_form_captura_biz_fk')->references('id')->on('business')->onDelete('cascade');
                $t->char('chave', 40)->unique('crm_form_captura_chave_uq')->comment('Pública: vai no action do formulário do site');
                $t->string('nome', 120);
                $t->unsignedInteger('owner_user_id')->comment('Responsável pelos negócios que entram por este formulário');
                $t->string('obrigado_url', 500)->nullable()->comment('Para onde o navegador volta depois do envio (definido pela empresa, não pelo visitante)');
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->index('business_id', 'crm_form_captura_biz_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_formularios_captura');
        if (Schema::hasTable('crm_deals') && Schema::hasColumn('crm_deals', 'origem')) {
            Schema::table('crm_deals', function (Blueprint $t) {
                $t->dropIndex('idx_crm_deals_biz_origem');
                $t->dropColumn(array_merge(['origem'], self::UTM));
            });
        }
    }
};
