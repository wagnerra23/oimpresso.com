<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * channel_user_access — enforça "no máx 1 grant ATIVO por (channel_id, user_id)".
 *
 * BUG corrigido (US-GOV-018 / US-WA-068, ADR 0135): a migration de criação
 * (2026_05_12_160000) definiu UNIQUE(channel_id, user_id, revoked_at) achando
 * que isso garantiria "só 1 row revoked_at=NULL por par". ERRADO — em MySQL,
 * MariaDB, SQLite e SQL padrão, valores NULL são tratados como DISTINTOS num
 * índice UNIQUE. Logo DUAS rows com revoked_at=NULL (= dois grants ativos do
 * mesmo user no mesmo canal) NÃO colidiam. O contrato de ACL não era enforced
 * pelo schema (guard test R-WA-068-005 falhava no nightly MySQL).
 *
 * Solução (enforcement de banco — Tier 0): coluna gerada VIRTUAL `revoked_marker`
 * que vale `1` quando ativo (revoked_at NULL) e `NULL` quando revogado, e
 * UNIQUE(channel_id, user_id, revoked_marker):
 *   - ativos    → marker=1    → no máx 1 por (channel_id,user_id) [colidem]
 *   - revogados → marker=NULL → coexistem N (NULLs distintos) → preserva history
 *                               e permite re-grant após revoke (R-WA-068-004/006)
 *
 * Por que VIRTUAL e não STORED: o SQLite só aceita coluna gerada VIRTUAL via
 * `ALTER TABLE ADD COLUMN` (STORED é proibido em ALTER) — e as suites com
 * RefreshDatabase rodam todas as migrations contra SQLite :memory:. VIRTUAL +
 * UNIQUE é suportado em MySQL 5.7.8+, MariaDB 10.2+ (InnoDB) e SQLite 3.31+.
 *
 * Append-only: não edita a migration de criação. Idempotente (hasTable/
 * hasColumn/hasIndex) e reversível (down()).
 *
 * Multi-tenant Tier 0 (ADR 0093): a higienização de duplicados roda como
 * SUPERADMIN (migration sem sessão de business) — opera cross-tenant
 * explicitamente. O UNIQUE escopa por channel_id, que já pertence a 1 business.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-068
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_user_access')) {
            return; // tabela ainda não criada — a migration de criação cuida disso
        }

        // 1) Coluna gerada VIRTUAL: 1=ativo (revoked_at NULL), NULL=revogado.
        if (! Schema::hasColumn('channel_user_access', 'revoked_marker')) {
            Schema::table('channel_user_access', function (Blueprint $table) {
                $table->integer('revoked_marker')
                    ->virtualAs('case when revoked_at is null then 1 else null end')
                    ->nullable()
                    ->comment('GERADA: 1=ativo (revoked_at NULL), NULL=revogado. Enforce 1 grant ativo/(canal,user) via UNIQUE cua_active_grant_unq.');
            });
        }

        // 2) Higieniza duplicados ATIVOS pré-existentes (deixados passar pelo
        //    UNIQUE antigo) ANTES de criar o índice novo — senão a criação falha.
        $this->revokeDuplicateActiveGrants();

        // 3) UNIQUE real do invariante. Criado ANTES de dropar o antigo porque
        //    tem channel_id como primeira coluna → cobre o índice exigido pela
        //    FK cua_channel_fk (MySQL/MariaDB recusam dropar o último índice da FK).
        if (! Schema::hasIndex('channel_user_access', 'cua_active_grant_unq')) {
            Schema::table('channel_user_access', function (Blueprint $table) {
                $table->unique(['channel_id', 'user_id', 'revoked_marker'], 'cua_active_grant_unq');
            });
        }

        // 4) Remove o UNIQUE antigo (não enforça nada — NULLs distintos).
        if (Schema::hasIndex('channel_user_access', 'cua_channel_user_unq')) {
            Schema::table('channel_user_access', function (Blueprint $table) {
                $table->dropUnique('cua_channel_user_unq');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_user_access')) {
            return;
        }

        // Restaura o UNIQUE antigo ANTES de remover o novo (cobre o índice da FK).
        if (! Schema::hasIndex('channel_user_access', 'cua_channel_user_unq')) {
            Schema::table('channel_user_access', function (Blueprint $table) {
                $table->unique(['channel_id', 'user_id', 'revoked_at'], 'cua_channel_user_unq');
            });
        }

        // Dropa o índice novo antes da coluna gerada que ele referencia.
        if (Schema::hasIndex('channel_user_access', 'cua_active_grant_unq')) {
            Schema::table('channel_user_access', function (Blueprint $table) {
                $table->dropUnique('cua_active_grant_unq');
            });
        }

        if (Schema::hasColumn('channel_user_access', 'revoked_marker')) {
            Schema::table('channel_user_access', function (Blueprint $table) {
                $table->dropColumn('revoked_marker');
            });
        }
    }

    /**
     * Soft-revoga grants ATIVOS duplicados, mantendo o mais recente (maior id)
     * por (channel_id, user_id). Portável (query builder, sem UPDATE..JOIN) e
     * idempotente — após rodar, nenhum par tem >1 ativo, então re-rodar é no-op.
     *
     * ⚠️ Timestamps ESCALONADOS (now()+i s) por uma razão sutil: neste ponto da
     * migration o índice ANTIGO UNIQUE(channel_id, user_id, revoked_at) ainda
     * existe. Revogar N rows do mesmo par com o MESMO revoked_at colidiria nele
     * (revoked_at iguais não são distintos quando não-NULL). Escalonar garante
     * revoked_at distintos. Pós-migration o índice real ignora revoked_at
     * (usa revoked_marker), então o escalonamento é cosmético e inofensivo.
     *
     * SUPERADMIN: migration roda sem sessão de business — opera cross-tenant.
     */
    private function revokeDuplicateActiveGrants(): void
    {
        $duplicateGroups = DB::table('channel_user_access')
            ->select('channel_id', 'user_id')
            ->whereNull('revoked_at')
            ->groupBy('channel_id', 'user_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $activeIds = DB::table('channel_user_access')
                ->where('channel_id', $group->channel_id)
                ->where('user_id', $group->user_id)
                ->whereNull('revoked_at')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            array_pop($activeIds); // mantém o maior id (mais recente) ativo

            foreach (array_values($activeIds) as $i => $id) {
                DB::table('channel_user_access')
                    ->where('id', $id)
                    ->update([
                        'revoked_at' => now()->addSeconds($i),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
};
