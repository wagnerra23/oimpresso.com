<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug fix 2026-05-22 -- coluna `numero` canon BR em `contacts`.
 *
 * Contexto: o fork BR original (commit 7ab688162, 2025-06-09) adicionou
 * `numero` direto na `create_contacts_table`. Upgrade UPOS 6.4 -> 6.7
 * sobrescreveu a migration upstream e perdemos a coluna em deploys novos
 * (dev limpo + CI). Em prod existente (biz=1 oimpresso + biz=4 Larissa
 * ROTA LIVRE) a coluna sobreviveu (ALTER TABLE em update PHP-UPOS nao
 * dropa columns). ADR 0178 §Contexto descreve a mesma situacao pros
 * 4 campos fiscais BR (cpf_cnpj/consumidor_final/contribuinte/regime),
 * restaurados pela migration 2026_05_21_140000.
 *
 * `numero` ficou de fora porque ADR 0178 priorizou identidade fiscal +
 * UI Inertia. Wave C (drawer 760px ADR 0179) precisa de `numero` separado
 * de `address_line_1` por UX BR (Larissa digita Rua + Numero em campos
 * distintos no drawer -- match com Cowork blueprint).
 *
 * IDEMPOTENTE -- Schema::hasColumn check protege prod existente (no-op),
 * e dev/CI cria a coluna. down() so dropa quando criada por esta migration
 * (NAO dropa em prod onde coluna preexiste do fork BR).
 *
 * @see memory/decisions/0178-restauracao-campos-fiscais-br-canon.md
 * @see memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md
 * @see Modules/Crm/Http/Controllers/ClienteAutosaveController::endereco
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Numero do endereco (BR canon -- separado de address_line_1).
            // Larissa biz=4 ROTA LIVRE: "Av Paulista" + "1578" em fields distintos.
            // Tipo string (nao integer) porque enderecos BR aceitam "1578-A", "s/n",
            // "Lt 12", "km 8" etc. Tamanho 20 = mesma escolha de tax_number/rg.
            if (! Schema::hasColumn('contacts', 'numero')) {
                $table->string('numero', 20)->nullable()->after('address_line_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'numero')) {
                $table->dropColumn('numero');
            }
        });
    }
};
