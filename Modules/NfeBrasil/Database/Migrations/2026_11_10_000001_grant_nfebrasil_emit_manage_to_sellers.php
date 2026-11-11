<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lote 3.1 da auditoria NfeBrasil (N5-03) — decisão B-02 de [W], 2026-11-10.
 *
 * O `StoreEmissaoRequest` passa a exigir `nfebrasil.emit.manage` para emitir NF-e/NFC-e
 * (POST /nfe-brasil/transactions/{tx}/emitir). Até aqui bastava estar logado.
 *
 * QUEM RECEBE: quem já cria venda — `sell.create` (PDV) ou `direct_sell.access` (venda
 * direta), as duas permissões que o SellController aceita para criar. Ninguém perde acesso
 * no dia do deploy. Quem não deve emitir passa a ser decisão do admin de cada empresa,
 * desmarcando a permissão em /roles/{id}/edit.
 *
 * ALCANCE: o dono (`Admin#{business_id}`) não depende disto — o `Gate::before` libera. Os
 * papéis criados à mão pela empresa são os que precisam do backfill.
 *
 * DOIS PIVÔS: papel (`role_has_permissions`) e permissão direta ao usuário
 * (`model_has_permissions`), como em 2026_10_02_120000_add_discount_view_manage_permissions.
 *
 * MULTI-TENANT (ADR 0093): `permissions` é global no Spatie; `roles` e `users` são por
 * empresa. O backfill só COPIA vínculos existentes — cada papel/usuário recebe a permissão
 * dentro da própria empresa. Nenhum ganha acesso a dado de outra.
 *
 * IDEMPOTENTE: firstOrCreate + insertOrIgnore.
 *
 * DOWN: não remove nada. A permissão já era declarada no DataController antes deste lote e
 * pode ter sido marcada à mão em algum papel; apagar vínculos aqui tiraria acesso que não
 * foi este arquivo que deu. Reverter o lote é reverter o `authorize()` do FormRequest.
 *
 * ANTES -> DEPOIS, leitura pura (rodar antes do deploy):
 *
 *   SELECT r.business_id, r.id AS role_id, r.name AS papel
 *   FROM roles r
 *   JOIN role_has_permissions rhp ON rhp.role_id = r.id
 *   JOIN permissions p ON p.id = rhp.permission_id
 *   WHERE p.name IN ('sell.create', 'direct_sell.access')
 *   GROUP BY r.business_id, r.id, r.name
 *   ORDER BY r.business_id, r.name;
 *   -- cada linha ganha nfebrasil.emit.manage.
 */
return new class extends Migration
{
    public const ORIGENS = ['sell.create', 'direct_sell.access'];

    public const NOVA = 'nfebrasil.emit.manage';

    public function up(): void
    {
        $nova = Permission::firstOrCreate(['name' => self::NOVA, 'guard_name' => 'web']);

        $origens = DB::table('permissions')
            ->whereIn('name', self::ORIGENS)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($origens !== []) {
            $this->copiarVinculosDePapel($origens, (int) $nova->id);
            $this->copiarVinculosDiretos($origens, (int) $nova->id);
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Tolerante a ambiente sem cache configurado.
        }
    }

    public function down(): void
    {
        // Intencionalmente vazio — ver docblock.
    }

    /** @param list<int> $origens */
    private function copiarVinculosDePapel(array $origens, int $novaId): void
    {
        $papeis = DB::table('role_has_permissions')
            ->whereIn('permission_id', $origens)
            ->distinct()
            ->pluck('role_id');

        foreach ($papeis->chunk(500) as $lote) {
            $linhas = $lote->map(fn ($roleId) => ['permission_id' => $novaId, 'role_id' => (int) $roleId])->values()->all();
            if ($linhas !== []) {
                DB::table('role_has_permissions')->insertOrIgnore($linhas);
            }
        }
    }

    /** @param list<int> $origens */
    private function copiarVinculosDiretos(array $origens, int $novaId): void
    {
        $vinculos = DB::table('model_has_permissions')
            ->whereIn('permission_id', $origens)
            ->distinct()
            ->get(['model_type', 'model_id']);

        foreach ($vinculos->chunk(500) as $lote) {
            $linhas = $lote->map(fn ($v) => [
                'permission_id' => $novaId,
                'model_type' => $v->model_type,
                'model_id' => $v->model_id,
            ])->values()->all();
            if ($linhas !== []) {
                DB::table('model_has_permissions')->insertOrIgnore($linhas);
            }
        }
    }
};
