<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cria as permissões de Variações, Garantias e Etiquetas (D1 [W] 2026-10-01) e as CONCEDE a
 * quem hoje já chega nessas telas — playbook Produto, thread 01 (achado A-P1).
 *
 * POR QUE o backfill é obrigatório: no mesmo PR, `VariationTemplateController`,
 * `WarrantyController` e `LabelsController` passam a exigir estas permissões. Antes eles não
 * checavam NADA além do business da sessão. Sem esta migration, no dia do deploy todo papel
 * não-admin perde as três telas — a permissão nova nasce sem nenhum papel apontando pra ela.
 *
 * ALCANCE: o dono do negócio não depende disto — `Gate::before`
 * (app/Providers/AuthServiceProvider.php) devolve true pra `Admin#{business_id}`. Quem está em
 * risco são os papéis criados pelo cliente.
 *
 * MAPA DE ORIGEM — medido nas PORTAS DE ENTRADA da UI em 2026-10-01 (não é a D2, é o status quo):
 *
 *   variation.*  <- `product.create`
 *        AdminSidebarMenu.php mostra "Variações" só pra `product.create`; quem chegava tinha o
 *        CRUD inteiro, inclusive excluir.
 *   warranty.*   <- qualquer uma das 8 que abrem o menu Produtos
 *        o item "Garantias" do AdminSidebarMenu NÃO tem condição própria: aparece pra todo
 *        mundo que vê o dropdown Produtos (product.view|create, brand.view|create,
 *        unit.view|create, category.view|create).
 *   print_labels.access <- TODO papel existente
 *        além do menu (`product.view`) e da lista de produtos, a ação "Etiquetas/Rótulos"
 *        aparece em toda linha da lista de compras (PurchaseController:141, Purchase/Index.tsx,
 *        Compras/AcoesDropdown.tsx) SEM permissão nenhuma — e Compras/Index só checa o
 *        business. Ou seja: hoje qualquer usuário imprime etiqueta. Conceder a todos é o único
 *        recorte que não tira acesso de ninguém.
 *
 * D2 ([W] 2026-10-01 — Balcão = `*.view` + `print_labels.access`; Gerente = tudo menos
 * `*.delete`) é o PERFIL ALVO dos papéis; aplicá-lo aqui retiraria `*.delete` de quem tem hoje.
 * O ajuste fino por papel fica com o admin de cada negócio. Esta migration só garante que
 * ninguém perde o que tem no deploy.
 *
 * MULTI-TENANT (ADR 0093): `permissions` é global, `roles` é por negócio. O backfill percorre
 * o pivô `role_has_permissions` (ou `roles` inteira, no caso das etiquetas), então cada papel
 * recebe a permissão dentro do próprio business_id. Nenhum papel ganha dado de outro negócio.
 *
 * IDEMPOTENTE: firstOrCreate + insertOrIgnore. Re-run não duplica nem derruba concessão manual.
 *
 * ANTES -> DEPOIS, leitura pura (rodar antes do deploy pra ver quem ganha o quê):
 *
 *   SELECT r.business_id, r.id, r.name,
 *          MAX(p.name = 'product.create') AS ganha_variation,
 *          MAX(p.name IN ('product.view','product.create','brand.view','brand.create',
 *                         'unit.view','unit.create','category.view','category.create')) AS ganha_warranty,
 *          1 AS ganha_print_labels
 *   FROM roles r
 *   LEFT JOIN role_has_permissions rhp ON rhp.role_id = r.id
 *   LEFT JOIN permissions p ON p.id = rhp.permission_id
 *   GROUP BY r.business_id, r.id, r.name ORDER BY r.business_id, r.name;
 */
return new class extends Migration
{
    /** Permissão nova => permissões antigas que a concedem. `null` = todo papel existente. */
    public const BACKFILL = [
        'variation.view' => ['product.create'],
        'variation.create' => ['product.create'],
        'variation.update' => ['product.create'],
        'variation.delete' => ['product.create'],
        'warranty.view' => self::MENU_PRODUTOS,
        'warranty.create' => self::MENU_PRODUTOS,
        'warranty.update' => self::MENU_PRODUTOS,
        'warranty.delete' => self::MENU_PRODUTOS,
        'print_labels.access' => null,
    ];

    /** As 8 permissões que abrem o dropdown Produtos no AdminSidebarMenu. */
    private const MENU_PRODUTOS = [
        'product.view', 'product.create', 'brand.view', 'brand.create',
        'unit.view', 'unit.create', 'category.view', 'category.create',
    ];

    public function up(): void
    {
        foreach (self::BACKFILL as $nova => $origens) {
            $permissao = Permission::firstOrCreate(['name' => $nova, 'guard_name' => 'web']);

            $this->conceder((int) $permissao->id, $origens);
        }

        $this->limparCacheDePermissoes();
    }

    public function down(): void
    {
        // FK ON DELETE CASCADE em role_has_permissions limpa o pivô.
        Permission::whereIn('name', array_keys(self::BACKFILL))
            ->where('guard_name', 'web')
            ->delete();

        $this->limparCacheDePermissoes();
    }

    /** @param  array<int,string>|null  $origens */
    private function conceder(int $permissaoNovaId, ?array $origens): void
    {
        if ($origens === null) {
            $papeis = DB::table('roles')->pluck('id');
        } else {
            $origemIds = DB::table('permissions')
                ->whereIn('name', $origens)
                ->where('guard_name', 'web')
                ->pluck('id');

            if ($origemIds->isEmpty()) {
                return; // instalação nova: não há o que preservar
            }

            $papeis = DB::table('role_has_permissions')
                ->whereIn('permission_id', $origemIds)
                ->distinct()
                ->pluck('role_id');
        }

        foreach ($papeis->chunk(500) as $lote) {
            $linhas = $lote->map(fn ($roleId) => [
                'permission_id' => $permissaoNovaId,
                'role_id' => (int) $roleId,
            ])->values()->all();

            if ($linhas !== []) {
                DB::table('role_has_permissions')->insertOrIgnore($linhas);
            }
        }
    }

    private function limparCacheDePermissoes(): void
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // tolerante a ambiente sem cache configurado
        }
    }
};
