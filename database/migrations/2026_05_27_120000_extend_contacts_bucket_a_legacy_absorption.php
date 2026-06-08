<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0197 — Bucket A · extend `contacts` pra absorver schema legacy PESSOAS.
 *
 * Wave D (pos-Bucket A+B+C+D+E acordo Wagner 2026-05-27). Adiciona 14 colunas
 * nullable que multiplos modulos (Sells, NfeBrasil, Compras, Financeiro,
 * ProducaoOficina) consultam direto via Eloquent durante migracao WR Comercial
 * (Delphi/Firebird) -> oimpresso (Laravel/MySQL).
 *
 * Pareada com Bucket B (proximo PR) que cria `contact_profile_legacy` 1:1
 * pra retro-rastreabilidade Delphi (campos auditoria + JSON catch-all).
 *
 * IDEMPOTENTE -- Schema::hasColumn check antes de cada add. Reversivel via
 * down() so pra colunas desta wave (campos pre-existentes ficam intactos).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGAVEL): `business_id` ja existe em
 * `contacts` (UPOS core) + indexado. FK self-referencing (parent_contact_id
 * + sales_rep_contact_id) NAO usam ON DELETE CASCADE pra preservar history
 * (importer Vargas 2-pass resolve via legacy_id; ciclo/orfao vira NULL).
 *
 * @see memory/decisions/0197-extend-contacts-absorcao-pessoas-legacy.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/sessions/2026-05-26-gap-pessoas-vs-contacts.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // ------------------------------------------------------------------
            // ENDERECO -- split logradouro/numero/complemento pra clareza fiscal.
            // UPOS legacy empacota em address_line_2 (text); separar permite
            // mapping direto Delphi PESSOAS.COMPLEMENTO.
            // ------------------------------------------------------------------
            if (! Schema::hasColumn('contacts', 'complemento')) {
                $table->string('complemento', 120)->nullable();
            }

            // ------------------------------------------------------------------
            // BLOQUEIO COMERCIAL -- Sells/Financeiro consultam pre-checkout.
            // Cliente bloqueado no Delphi NAO pode vender/cobrar no oimpresso.
            // contact_status enum nao basta (cliente pode estar 'active' +
            // 'bloqueado'=1 simultaneamente).
            // ------------------------------------------------------------------
            if (! Schema::hasColumn('contacts', 'bloqueado')) {
                $table->boolean('bloqueado')->default(false);
            }

            // ------------------------------------------------------------------
            // FINANCEIRO -- limites/preferencias comerciais.
            // ------------------------------------------------------------------

            // Limite de desconto por cliente -- PDV impede desconto > limite.
            // Aplica so a CLI (cliente). Fornecedor/funcionario nao usa.
            if (! Schema::hasColumn('contacts', 'limite_desconto_percentual')) {
                $table->decimal('limite_desconto_percentual', 5, 2)->nullable();
            }

            // Desconto pontualidade boleto -- Asaas/Modules/Financeiro incentivo
            // "paga ate dia X ganha Y% desconto". CLI only.
            if (! Schema::hasColumn('contacts', 'boleto_desconto_pontualidade_pct')) {
                $table->decimal('boleto_desconto_pontualidade_pct', 5, 2)->nullable();
            }

            // Cobrar custo boleto -- repassa tarifa banco/Asaas pro cliente.
            // Config per-cliente (alguns CLI tem custom rate). Default false.
            if (! Schema::hasColumn('contacts', 'cobrar_custo_boleto')) {
                $table->boolean('cobrar_custo_boleto')->default(false);
            }

            // Previsao proxima fatura -- Modules/Crm/Financeiro forecast UI.
            if (! Schema::hasColumn('contacts', 'fatura_previsao')) {
                $table->date('fatura_previsao')->nullable();
            }

            // ------------------------------------------------------------------
            // PRODUCAO/OFICINA -- prioridade na fila + NFSe.
            // ------------------------------------------------------------------

            // Prioridade producao 0-5 estrelas -- Modules/ProducaoOficina + Oficinas
            // ordenam fila por essa col. CLI only (fornecedor nao entra na fila).
            if (! Schema::hasColumn('contacts', 'prioridade_producao')) {
                $table->unsignedTinyInteger('prioridade_producao')->nullable();
            }

            // ISS retido NFSe -- 1=retido / 2=nao retido (SEFAZ). Modules/NfeBrasil.
            if (! Schema::hasColumn('contacts', 'iss_retido')) {
                $table->unsignedTinyInteger('iss_retido')->nullable();
            }

            // ------------------------------------------------------------------
            // PESSOAL -- aniversario PF (comemoracao, distinto de DOB).
            // Delphi distingue ANIVERSARIO (MM-DD pra parabens) de DATANASCIMENTO
            // (data exata cadastral). UPOS `dob` cobre o segundo; precisamos do
            // primeiro pra mailing/WhatsApp parabens sem expor ano.
            // ------------------------------------------------------------------
            if (! Schema::hasColumn('contacts', 'aniversario_mmdd')) {
                $table->string('aniversario_mmdd', 5)->nullable();
            }

            // ------------------------------------------------------------------
            // SELF-FK -- rede de filiais + representante.
            // ------------------------------------------------------------------

            // Pai da rede -- matriz/filial (CLI ou FOR). 2-pass importer resolve
            // via lookup legacy_id (Pass 1 INSERT sem FK · Pass 2 UPDATE).
            // ON DELETE NO ACTION (default) preserva orfao se matriz deletada.
            if (! Schema::hasColumn('contacts', 'parent_contact_id')) {
                $table->unsignedBigInteger('parent_contact_id')->nullable();
                $table->foreign('parent_contact_id', 'fk_contacts_parent')
                    ->references('id')->on('contacts')
                    ->nullOnDelete();
            }

            // Representante responsavel pelo cliente -- comissao Sells.
            // FK aponta pra OUTRO contacts row com is_representative=1.
            // Self-FK; nao adiciona contraint type-check (validado em app layer).
            if (! Schema::hasColumn('contacts', 'sales_rep_contact_id')) {
                $table->unsignedBigInteger('sales_rep_contact_id')->nullable();
                $table->foreign('sales_rep_contact_id', 'fk_contacts_sales_rep')
                    ->references('id')->on('contacts')
                    ->nullOnDelete();
            }

            // ------------------------------------------------------------------
            // UI / DISPLAY -- papel principal + situacao livre.
            // ------------------------------------------------------------------

            // Papel principal pra UI (qual flag is_X exibir como destaque).
            // Default = primeira flag=1 (resolvido em accessor App\Contact).
            if (! Schema::hasColumn('contacts', 'primary_role')) {
                $table->enum('primary_role', [
                    'customer', 'supplier', 'employee', 'representative',
                ])->nullable();
            }

            // Situacao livre Delphi (raw) -- back-compat string ad-hoc.
            // Casos canonicos (`VIP`, `BLACKLIST`) viram `tags` JSON + `vip` bool
            // no importer; casos exoticos preservam aqui pra forensics.
            // Review trigger ADR 0197: drop col se 0 uso pos-90d.
            if (! Schema::hasColumn('contacts', 'situacao')) {
                $table->string('situacao', 30)->nullable();
            }
        });

        // Indices compostos Tier 0 -- business_id PRIMEIRO sempre (ADR 0093).
        // try/catch porque alguns motores rejeitam ADD INDEX em paralelo a
        // ADD COLUMN no mesmo ALTER. Hostinger/CT100 MySQL 8.x OK; legacy
        // pode falhar -- nao fatal, query degrade gracioso.
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                    ->pluck('Key_name')
                    ->toArray();

                if (! in_array('idx_contacts_biz_bloqueado', $existing, true)) {
                    $table->index(['business_id', 'bloqueado'], 'idx_contacts_biz_bloqueado');
                }
                if (! in_array('idx_contacts_biz_parent', $existing, true)) {
                    $table->index(['business_id', 'parent_contact_id'], 'idx_contacts_biz_parent');
                }
                if (! in_array('idx_contacts_biz_sales_rep', $existing, true)) {
                    $table->index(['business_id', 'sales_rep_contact_id'], 'idx_contacts_biz_sales_rep');
                }
            });
        } catch (\Throwable $e) {
            \Log::warning('extend_contacts_bucket_a: indices compostos skip', [
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // Drop indices compostos primeiro.
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                    ->pluck('Key_name')
                    ->toArray();

                foreach ([
                    'idx_contacts_biz_bloqueado',
                    'idx_contacts_biz_parent',
                    'idx_contacts_biz_sales_rep',
                ] as $idx) {
                    if (in_array($idx, $existing, true)) {
                        $table->dropIndex($idx);
                    }
                }
            });
        } catch (\Throwable $e) {
            // Indices ja nao existem -- ok.
        }

        // Drop FKs explicitas antes de dropar colunas (MySQL requer ordem).
        Schema::table('contacts', function (Blueprint $table) {
            try {
                $table->dropForeign('fk_contacts_parent');
            } catch (\Throwable $e) { /* FK nao existe */ }

            try {
                $table->dropForeign('fk_contacts_sales_rep');
            } catch (\Throwable $e) { /* FK nao existe */ }
        });

        Schema::table('contacts', function (Blueprint $table) {
            $cols = [
                'complemento',
                'bloqueado',
                'limite_desconto_percentual',
                'boleto_desconto_pontualidade_pct',
                'cobrar_custo_boleto',
                'fatura_previsao',
                'prioridade_producao',
                'iss_retido',
                'aniversario_mmdd',
                'parent_contact_id',
                'sales_rep_contact_id',
                'primary_role',
                'situacao',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
