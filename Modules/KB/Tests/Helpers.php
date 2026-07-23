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
    // NO-OP no MySQL (lane + CT100): o schema kb_* + CORE JÁ vem do `migrate --force` do setup
    // (mysql-schema.sql + migrations novas). Qualquer create/migration-runner abaixo seria DDL
    // REDUNDANTE — e DDL dá COMMIT IMPLÍCITO, que quebraria a transação do DatabaseTransactions
    // (o teste não rolaria back → volta o acúmulo/flakiness). Só o sqlite (fallback; os testes
    // MySQL-only SKIPam) monta o schema abaixo, onde a DB nasce vazia por processo.
    if (\DB::connection()->getDriverName() !== 'sqlite' && Schema::hasTable('kb_nodes')) {
        return;
    }

    // ISOLAMENTO via DatabaseTransactions (ligado em tests/Pest.php pro dir Feature do KB):
    // cada teste roda numa transação com ROLLBACK automático no fim → NÃO precisamos mais
    // dropar+recriar kb_* por teste. O drop foi REMOVIDO (2026-07-23) porque:
    //   (a) no MySQL (lane + CT100) o schema kb_* vem do `migrate --force` do setup
    //       (mysql-schema.sql + migrations novas) — não precisa recriar;
    //   (b) dropar é DDL → dá COMMIT IMPLÍCITO no MySQL, quebrando a transação (os dados do
    //       teste não rolariam back → volta o acúmulo de mcp_docs/perms que causava a
    //       flakiness + o bug do "8 nós" no bridge job).
    // O create-if-not-exists (CORE, abaixo) + o runner de migrations KB seguem idempotentes:
    // no-op no MySQL já migrado (sem DDL → transação intacta), criam no sqlite fresco (onde os
    // testes MySQL-only SKIPam de qualquer jeito).

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

    // Roda TODAS as migrations KB em ordem (as 12 de criação 2026_05_15_1000*
    // + ALTERs posteriores, ex: code_drift_state 2026_07_23). Todas são
    // idempotentes (guard hasTable/hasColumn), então re-run é seguro. O glob
    // largo evita editar este helper a cada migration nova.
    $kbMigrationDir = realpath(__DIR__ . '/../Database/Migrations');
    if ($kbMigrationDir === false) {
        throw new \RuntimeException('Modules/KB/Database/Migrations não encontrado — Agent A já criou? cwd='.getcwd());
    }
    $files = glob($kbMigrationDir . '/2026_*.php') ?: [];
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
    // NO-OP desde 2026-07-23: o cleanup agora é o ROLLBACK automático do DatabaseTransactions
    // (ligado em tests/Pest.php). Dropar aqui seria DDL no afterEach — commit implícito ANTES
    // do rollback → quebraria a transação E derrubaria o schema kb_* (que vem do migrate) pro
    // próximo teste. Mantido como função pra não editar os afterEach() de dezenas de arquivos;
    // o corpo é vazio de propósito.
}

/**
 * Cria row business com id especificado (ou padrão biz=1). Idempotente.
 *
 * MySQL-real fix (2026-07-20): a tabela `business` REAL do UltimatePOS (staging
 * oimpresso-staging, clone anonimizado de prod) tem colunas NOT NULL + FK sem
 * default — `currency_id`→currencies, `owner_id`→users, `stop_selling_before`,
 * `weighing_scale_setting` (text), `certificado` (blob), `officeimpresso_numerodemaquinas`.
 * O SQL mode do staging é NÃO-estrito (NO_ENGINE_SUBSTITUTION), então as NOT NULL
 * viram default implícito (0/''), mas os FK `currency_id=0`/`owner_id=0` NÃO existem
 * nas tabelas-pai → o INSERT estoura FK. Com `insertOrIgnore` esse erro era ENGOLIDO
 * silenciosamente → o business fictício (ex: biz=99) NUNCA era criado → FK 1452
 * (`fk_kb_nodes_business`) nos `kb_*` downstream de TODO teste cross-tenant.
 *
 * Solução robusta a drift de schema: CLONAR uma row-modelo já válida (biz=1 do
 * clone de prod) trocando só `id`/`name`/`uuid` — satisfaz TODA NOT NULL e TODO FK
 * sem enumerar colunas (imune a colunas NOT NULL futuras). Única unique além do PK
 * é `uuid` (nullable) → nulamos pra não colidir com a row-modelo. Trocamos o
 * `insertOrIgnore` por checagem `exists()` + `insert()` puro: idempotente igual,
 * mas SEM engolir erro — qualquer problema futuro FALHA alto, não silencioso.
 *
 * SQLite (CI/local, tabela `business` mínima de kbBootstrapSchema): quando não há
 * row-modelo (banco fresco), cai no insert mínimo de 4 colunas — comportamento
 * idêntico ao anterior. Ver ADR 0093 (multi-tenant) + ADR 0101 (biz=1) + ADR 0062.
 */
function kbCreateBusinessRow(int $bizId = 1): void
{
    if ($bizId === 4) {
        throw new \LogicException('ADR 0101: biz=4 (ROTA LIVRE prod) NUNCA em tests. Use biz=1 OR biz=99.');
    }

    // Idempotente: biz=1 já vem do clone de prod no staging MySQL; recall é no-op.
    if (\DB::table('business')->where('id', $bizId)->exists()) {
        return;
    }

    // Row-modelo válida (biz=1 no staging; qualquer row no sqlite). null = banco fresco.
    $template = \DB::table('business')->orderBy('id')->first();

    if ($template !== null) {
        $row = (array) $template;
        $row['id']         = $bizId;
        $row['name']       = "Test Business {$bizId}";
        $row['created_at'] = now();
        $row['updated_at'] = now();
        if (array_key_exists('uuid', $row)) {
            $row['uuid'] = null; // unique index — evita colisão com a row-modelo
        }
        \DB::table('business')->insert($row);

        return;
    }

    // Fallback banco fresco (sqlite :memory: — tabela mínima id/name/timestamps).
    \DB::table('business')->insert([
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

    // BLOQUEADOR 2 (lane, phpunit.xml executionOrder="random"): as tabelas Spatie
    // (permissions/model_has_*) são CORE COMPARTILHADAS e NÃO são resetadas por
    // kbTeardownSchema, então o PermissionRegistrar acumula estado entre testes no
    // MySQL persistente-no-run. Em ordem aleatória isso deixava o `can:` middleware
    // ver um mapa de permissões STALE → 403 intermitente (ex: V2b do KbIndexV2ContractTest,
    // mesma perm coarse que V3/V4/V5/V6 resolviam OK). Forçar o flush do cache aqui —
    // depois de conceder — garante que o próximo `->can()` releia fresco do DB. Barato e
    // idempotente (Spatie já faz isso internamente em cada mutação; aqui blinda a ordem).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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
