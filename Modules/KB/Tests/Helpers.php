<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Pest.php — Module KB
|--------------------------------------------------------------------------
|
| Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md
| Tier 0 IRREVOGÁVEL: tests biz=1 OR biz=99 — NUNCA biz=4 (ROTA LIVRE prod)
| ADR 0093 multi-tenant + ADR 0101 biz=1 em tests.
|
| Estratégia de schema:
|   - SQLite in-memory (phpunit.xml DB_CONNECTION=sqlite, DB_DATABASE=:memory:)
|   - Dependências externas (business, users, mcp_memory_documents) montadas
|     inline como tabelas mínimas — só o necessário pra FKs e queries.
|   - Migrations KB de Modules/KB/Database/Migrations/* são carregadas via
|     require/up() — TODO[CL]: Agent A pode criar Schema::createKb() helper
|     se preferir bootstrap mais limpo.
|
| Helpers expostos:
|   - kbBootstrapSchema()  → cria deps externas + roda migrations KB
|   - kbTeardownSchema()   → dropIfExists em ordem reversa
|   - kbActAsUser($bizId, $userId=42, array $permissions=[])  → autentica + session()
|   - kbCreateBusinessRow($bizId)
|   - kbCreateMcpDoc($bizId, $type='adr', array $overrides=[])
|
| Carregado via composer autoload-dev.files (Pest discovery não sobe acima
| do testsuite root no phpunit.xml — `Modules/KB/Tests/Feature` + `/Unit`).
| `uses(...)->in(__DIR__)` foi movido pra Pest.php dentro de cada subdir.
*/

/**
 * Cria as tabelas mínimas necessárias pros tests do KB.
 *
 * Inclui:
 *   - business           (FK target de todas kb_*.business_id)
 *   - users              (FK target de author_user_id e kb_favorites)
 *   - mcp_memory_documents (FK target de kb_nodes.source_doc_id)
 *   - permissions/roles/model_has_* (Spatie — pra middleware can:kb.*)
 *   - kb_* (carregadas via Modules/KB/Database/Migrations/2026_05_15_1000*.php)
 *
 * Idempotente — chama Schema::dropIfExists antes pra suportar reuso entre tests.
 *
 * MySQL-safe (era-sqlite floor fix 2026-06-13): em sqlite :memory: a DB nasce
 * vazia por processo, então dropar/criar tabelas CORE COMPARTILHADAS (business,
 * users, mcp_memory_documents, permissions/roles/model_has_*) era benigno. Na
 * full-suite contra MySQL PERSISTENTE (nightly CT 100) esses drops DESTROEM o
 * schema real compartilhado e estouram "Base table not found" em centenas de
 * testes alheios. Regra: tabela CORE (sem prefixo de módulo) NUNCA é dropada;
 * o create vira condicional via Schema::hasTable() → no-op no MySQL já-migrado,
 * cria no sqlite fresco. Tabelas kb_* (módulo-prefixadas) seguem drop+create
 * idempotente — não são compartilhadas. Ver ADR 0093 + ADR 0101.
 */
function kbBootstrapSchema(): void
{
    // kb_* (módulo-prefixadas, NÃO compartilhadas): drop+create idempotente OK.
    //
    // FK-cycle fix (2026-07-20): os drops rodam com FK checks DESLIGADOS porque
    // kb_decision_tree_steps participa de dois laços que NENHUMA ordem de drop
    // resolve — (1) FK AUTO-referencial (yes_next_step_id/no_next_step_id →
    // kb_decision_tree_steps.id) e (2) ciclo com kb_decision_trees
    // (steps.tree_id → trees.id  ×  trees.root_step_id → steps.id via
    // fk_kb_dt_root_step, ALTER delayed na migration 100008). No MySQL real
    // (CT 100 oimpresso-staging) dropar o "pai" estoura 1451/3730 "Cannot delete
    // parent row"; em sqlite o FK enforcement é frouxo, então passava
    // despercebido no CI. withoutForeignKeyConstraints restaura o check no fim
    // (try/finally) mesmo em erro. Só afeta os drops kb_* — CORE nunca é dropada.
    Schema::withoutForeignKeyConstraints(function () {
        Schema::dropIfExists('kb_bridge_state');
        Schema::dropIfExists('kb_comments');
        Schema::dropIfExists('kb_favorites');
        Schema::dropIfExists('kb_node_versions');
        Schema::dropIfExists('kb_decision_tree_steps');
        Schema::dropIfExists('kb_decision_trees');
        Schema::dropIfExists('kb_path_steps');
        Schema::dropIfExists('kb_paths');
        Schema::dropIfExists('kb_edges');
        Schema::dropIfExists('kb_nodes');
        Schema::dropIfExists('kb_subcategories');
        Schema::dropIfExists('kb_categories');
    });

    // Externals CORE COMPARTILHADAS — NUNCA dropar. Create só condicional:
    // sqlite fresco cria; MySQL persistente (já migrado) vira no-op.
    if (! Schema::hasTable('business')) {
        Schema::create('business', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 200)->nullable();
            $t->timestamps();
        });
    }

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('username')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->integer('business_id')->unsigned()->nullable();
            $t->rememberToken();
            $t->softDeletes();
            $t->timestamps();
        });
    }

    if (! Schema::hasTable('mcp_memory_documents')) {
        Schema::create('mcp_memory_documents', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('business_id')->unsigned()->nullable();
            $t->string('type', 40)->nullable();
            $t->string('slug', 180)->nullable();
            $t->string('title', 255)->nullable();
            $t->longText('content_md')->nullable();
            $t->json('metadata')->nullable();
            $t->string('git_sha', 64)->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
    }

    // Spatie tables CORE COMPARTILHADAS (pra middleware can:* nos Controllers).
    foreach (['permissions', 'roles'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            Schema::create($tbl, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name');
                $t->string('guard_name');
                $t->unsignedInteger('business_id')->nullable();
                $t->timestamps();
                $t->unique(['name', 'guard_name']);
            });
        }
    }
    if (! Schema::hasTable('model_has_roles')) {
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
        });
    }
    if (! Schema::hasTable('model_has_permissions')) {
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
        });
    }
    if (! Schema::hasTable('role_has_permissions')) {
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });
    }

    // Roda as 12 migrations KB em ordem.
    $kbMigrationDir = realpath(__DIR__ . '/../Database/Migrations');
    if ($kbMigrationDir === false) {
        throw new \RuntimeException('Modules/KB/Database/Migrations não encontrado — Agent A já criou? cwd='.getcwd());
    }
    $files = glob($kbMigrationDir . '/2026_05_15_1000*.php') ?: [];
    sort($files);
    foreach ($files as $f) {
        (require $f)->up();
    }
}

/**
 * Drop em ordem reversa pra respeitar FKs.
 *
 * MySQL-safe (era-sqlite floor fix 2026-06-13): só dropa tabelas kb_*
 * (módulo-prefixadas, não compartilhadas). As CORE COMPARTILHADAS
 * (mcp_memory_documents, users, business, permissions/roles/model_has_*)
 * NÃO são dropadas — em MySQL persistente isso destruía o schema real
 * compartilhado e quebrava centenas de testes alheios. Ver ADR 0093 + 0101.
 */
function kbTeardownSchema(): void
{
    // FK-cycle fix (2026-07-20): drops com FK checks desligados — mesmo motivo
    // de kbBootstrapSchema (ciclo kb_decision_trees ↔ kb_decision_tree_steps +
    // self-FK). Ver comentário lá. Restaura o check no fim (try/finally).
    Schema::withoutForeignKeyConstraints(function () {
        foreach (['kb_bridge_state', 'kb_comments', 'kb_favorites', 'kb_node_versions',
                  'kb_decision_tree_steps', 'kb_decision_trees',
                  'kb_path_steps', 'kb_paths',
                  'kb_edges', 'kb_nodes',
                  'kb_subcategories', 'kb_categories'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    });
}

/**
 * Cria row business com id especificado (ou padrão biz=1).
 */
function kbCreateBusinessRow(int $bizId = 1): void
{
    \DB::table('business')->insertOrIgnore([
        'id'         => $bizId,
        'name'       => "Test Business {$bizId}",
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Cria user de teste + autentica + popula session canônica UltimatePOS.
 *
 * @param  int          $bizId        business_id da sessão (1 ou 99 — NUNCA 4 ROTA LIVRE)
 * @param  int          $userId       id (default 42)
 * @param  string[]     $permissions  ex: ['kb.write', 'kb.publish.path']
 */
function kbActAsUser(int $bizId = 1, int $userId = 42, array $permissions = []): \Illuminate\Contracts\Auth\Authenticatable
{
    if ($bizId === 4) {
        throw new \LogicException('ADR 0101: biz=4 (ROTA LIVRE prod) NUNCA em tests. Use biz=1 OR biz=99.');
    }

    kbCreateBusinessRow($bizId);

    // Tenta App\User canônico UltimatePOS; fallback pra App\Models\User Laravel padrão.
    $userClass = class_exists(\App\User::class) ? \App\User::class : \App\Models\User::class;

    $user = $userClass::query()->find($userId);
    if (!$user) {
        $user = new $userClass();
        $user->id = $userId;
        $user->username = "test_user_{$userId}";
        $user->email = "test_{$userId}@test.local";
        $user->password = bcrypt('test');
        $user->business_id = $bizId;
        $user->save();
    }

    foreach ($permissions as $perm) {
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => $perm,
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($perm);
    }

    test()->actingAs($user);
    session([
        'user.business_id'         => $bizId,
        'user.id'                  => $user->id,
        'business.id'              => $bizId,
        'business'                 => ['id' => $bizId],
    ]);

    return $user;
}

/**
 * Cria row em mcp_memory_documents pra simular fotografia git canônica.
 */
function kbCreateMcpDoc(int $bizId = 1, string $type = 'adr', array $overrides = []): int
{
    static $autoslug = 0;
    $autoslug++;
    $slug = $overrides['slug'] ?? sprintf('%04d-test-doc-%s-%d', $autoslug, $type, $bizId);

    $id = \DB::table('mcp_memory_documents')->insertGetId(array_merge([
        'business_id' => $bizId,
        'type'        => $type,
        'slug'        => $slug,
        'title'       => $overrides['title'] ?? "Test {$type} {$autoslug}",
        'content_md'  => $overrides['content_md'] ?? "# Test {$type}\n\nConteúdo de teste.",
        'metadata'    => isset($overrides['metadata']) ? json_encode($overrides['metadata']) : null,
        'git_sha'     => $overrides['git_sha'] ?? str_repeat('a', 40),
        'deleted_at'  => $overrides['deleted_at'] ?? null,
        'created_at'  => now(),
        'updated_at'  => now(),
    ], array_diff_key($overrides, ['metadata' => null])));

    return $id;
}
