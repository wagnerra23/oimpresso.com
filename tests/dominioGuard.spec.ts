// Camada META — teste FÍSICO (caixa-preta) do guard dominio:check (ADR 0264 G-4).
//
// Roda o .mjs real contra migrations-fixture + dicionário-fixture e exige o comportamento,
// incluindo os bugs já mordidos: (a) ALTER...MODIFY...ENUM partido por concatenação PHP
// ("order_type " . "ENUM(...)") e (b) down() reintroduzindo locacao não pode contar como
// estado atual (só up()). Sensibilidade + especificidade + ratchet.
//
// Cobre: scripts/domain-dict-guard.mjs
// Refs: ADR 0264 (G-4) · ADR 0265 (erradica locação) · padrão governanceAdrScripts.spec.ts.

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';

const REPO = process.cwd();
const GUARD = resolve(REPO, 'scripts/domain-dict-guard.mjs');

let tmp: string;

const write = (rel: string, content: string) => {
  const full = join(tmp, rel);
  mkdirSync(dirname(full), { recursive: true });
  writeFileSync(full, content);
};

const dict = (module: string, enums: Record<string, string[]>, extra: Record<string, unknown> = {}) =>
  write(`memory/dominio/${module.toLowerCase()}.md`, '# dict\n\n```json\n' + JSON.stringify({ module, enums, ...extra }) + '\n```\n');

const migration = (module: string, file: string, php: string) =>
  write(`Modules/${module}/Database/Migrations/${file}`, php);

// Arquivo de código de aplicação do módulo (Salto #3 — cobertura de código).
const code = (module: string, rel: string, php: string) =>
  write(`Modules/${module}/${rel}`, php);

beforeEach(() => {
  tmp = mkdtempSync(join(tmpdir(), 'dominio-'));
  mkdirSync(join(tmp, 'scripts'), { recursive: true });
  mkdirSync(join(tmp, 'memory/dominio'), { recursive: true });
  mkdirSync(join(tmp, 'Modules'), { recursive: true });
});
afterEach(() => rmSync(tmp, { recursive: true, force: true }));

const run = (args: string) =>
  execSync(`node "${GUARD}" ${args}`, { cwd: tmp, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });

const runJsonExpectFail = (): string => {
  try {
    run('--json');
    throw new Error('esperava exit 1, mas passou');
  } catch (e: any) {
    if (e?.message === 'esperava exit 1, mas passou') throw e;
    return (e.stdout || '') + (e.stderr || '');
  }
};

describe('dominio:check — divergência enum⇔dicionário (físico)', () => {
  it('SENSIBILIDADE: valor de enum fora do dicionário vira undeclared-value', () => {
    dict('ModA', { 'service_orders.order_type': ['manutencao'] });
    migration('ModA', '2026_01_01_000001_create.php',
      `<?php return new class { public function up(): void {
        Schema::create('service_orders', function ($t) {
          $t->enum('order_type', ['manutencao', 'locacao']);
        });
      } };`);
    const out = runJsonExpectFail(); // sem baseline → tudo novo → exit 1
    expect(out).toMatch(/dominio:undeclared-value:ModA:service_orders\.order_type:locacao/);
  });

  it('REGRESSÃO (glue): ALTER...MODIFY...ENUM partido por concatenação PHP é parseado', () => {
    // O bug real: "...MODIFY order_type " . "ENUM('manutencao','mecanica')" não casava o regex.
    dict('ModB', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    migration('ModB', '2026_02_01_000001_alter.php',
      `<?php return new class { public function up(): void {
        \\DB::statement(
          "ALTER TABLE service_orders MODIFY order_type "
          . "ENUM('manutencao','mecanica') NOT NULL DEFAULT 'manutencao'"
        );
      } };`);
    const out = run('--json'); // deve ser VERDE (exit 0)
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/undeclared-value:ModB:service_orders\.order_type/);
    expect(out).not.toMatch(/missing-column-in-schema:ModB:service_orders\.order_type/); // prova que foi parseado
  });

  it('REGRESSÃO (up-only): down() reintroduzindo locacao NÃO conta como estado atual', () => {
    dict('ModC', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    migration('ModC', '2026_03_01_000001_alter.php',
      `<?php return new class {
        public function up(): void {
          \\DB::statement("ALTER TABLE service_orders MODIFY order_type ENUM('manutencao','mecanica') NOT NULL");
        }
        public function down(): void {
          \\DB::statement("ALTER TABLE service_orders MODIFY order_type ENUM('locacao','manutencao') NOT NULL");
        }
      };`);
    const out = run('--json'); // up() = {manutencao,mecanica} = dicionário → VERDE
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/locacao/);
  });

  it('REGRESSÃO (last-write-wins): migration mais recente define o enum atual', () => {
    dict('ModD', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    migration('ModD', '2026_01_01_000001_create.php',
      `<?php return new class { public function up(): void {
        Schema::create('service_orders', function ($t) { $t->enum('order_type', ['locacao', 'manutencao']); });
      } };`);
    migration('ModD', '2026_06_01_000001_alter.php',
      `<?php return new class { public function up(): void {
        \\DB::statement("ALTER TABLE service_orders MODIFY order_type " . "ENUM('manutencao','mecanica') NOT NULL");
      } };`);
    const out = run('--json'); // o ALTER recente vence o create antigo → VERDE
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/locacao/);
  });

  it('ESPECIFICIDADE: módulo com enum mas SEM dicionário vira module-no-dict (não trava o resto)', () => {
    migration('SemDict', '2026_01_01_000001_create.php',
      `<?php return new class { public function up(): void {
        Schema::create('x', function ($t) { $t->enum('status', ['a', 'b']); });
      } };`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:module-no-dict:SemDict/);
  });

  it('RATCHET: baseline absorve a divergência atual e a próxima rodada passa', () => {
    dict('ModE', { 'service_orders.order_type': ['manutencao'] });
    migration('ModE', '2026_01_01_000001_create.php',
      `<?php return new class { public function up(): void {
        Schema::create('service_orders', function ($t) { $t->enum('order_type', ['manutencao', 'locacao']); });
      } };`);
    run('--write-baseline'); // absorve o débito locacao
    const out = run(''); // passa (nada NOVO)
    expect(out).toMatch(/Sem divergências novas/);
  });
});

// =====================================================================================
// SALTO #3 — cobertura de código (valor de domínio hardcoded fora do enum)
// =====================================================================================
describe('dominio:check — valor de domínio no CÓDIGO (Salto #3, físico)', () => {
  // enum que BATE com o dicionário, pra isolar a divergência só ao code-scan.
  const cleanEnum = (module: string, col = 'order_type', vals = ['manutencao', 'mecanica']) =>
    migration(module, '2026_01_01_000001_create.php',
      `<?php return new class { public function up(): void {
        Schema::create('service_orders', function ($t) { $t->enum('${col}', ${JSON.stringify(vals)}); });
      } };`);

  it('SENSIBILIDADE (where): where("order_type","locacao") no código vira undeclared-code-value', () => {
    dict('ModW', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModW');
    code('ModW', 'Http/Controllers/X.php',
      `<?php class X { function q($qb) { return $qb->where('order_type', 'locacao'); } }`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-code-value:ModW:order_type:locacao/);
  });

  it('SENSIBILIDADE (comparação de campo): $x->order_type === "locacao" vira undeclared-code-value', () => {
    dict('ModP', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModP');
    code('ModP', 'Entities/ServiceOrder.php',
      `<?php class ServiceOrder { function f() { if ($this->order_type !== 'locacao') { return; } } }`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-code-value:ModP:order_type:locacao/);
  });

  it('SENSIBILIDADE (whereIn): lista literal com valor não-declarado é pega', () => {
    dict('ModI', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModI');
    code('ModI', 'Services/S.php',
      `<?php class S { function q($qb) { return $qb->whereIn('order_type', ['manutencao', 'locacao']); } }`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-code-value:ModI:order_type:locacao/);
    expect(out).not.toMatch(/order_type:manutencao/); // valor declarado não vira violação
  });

  it('SENSIBILIDADE (validação in:): regra Laravel in: com valor fora do dicionário é pega', () => {
    dict('ModV', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModV');
    code('ModV', 'Http/Requests/R.php',
      `<?php class R { function rules() { return ['order_type' => ['nullable', 'in:manutencao,locacao']]; } }`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-code-value:ModV:order_type:locacao/);
  });

  it('ESPECIFICIDADE: valor DECLARADO usado no código não vira violação', () => {
    dict('ModOK', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModOK');
    code('ModOK', 'Http/Controllers/X.php',
      `<?php class X { function q($qb) { return $qb->where('order_type', 'manutencao')->orWhere('order_type', 'mecanica'); } }`);
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/undeclared-code-value/);
  });

  it('ESPECIFICIDADE: coluna FORA do dicionário não é vigiada no código', () => {
    dict('ModC', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModC');
    code('ModC', 'Http/Controllers/X.php',
      `<?php class X { function q($qb) { return $qb->where('status', 'qualquer_coisa'); } }`); // 'status' não está no dict
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/undeclared-code-value/);
  });

  it('ESPECIFICIDADE: self::CONST / variável (sem aspas) não é literal → não conta', () => {
    dict('ModK', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModK');
    code('ModK', 'Services/S.php',
      `<?php class S { function q($qb, $tipo) { return $qb->where('order_type', self::ALGO)->orWhere('order_type', $tipo); } }`);
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/undeclared-code-value/);
  });

  it('REGRESSÃO (operador LIKE): where("col","like","%x%") não lê o operador como valor', () => {
    // O bug real mordido: orWhere('vehicle_type','like',"%search%") → 'like' virou "valor".
    dict('ModL', { 'vehicles.vehicle_type': ['caminhao'] });
    migration('ModL', '2026_01_01_000001_create.php',
      `<?php return new class { public function up(): void {
        Schema::create('vehicles', function ($t) { $t->enum('vehicle_type', ['caminhao']); });
      } };`);
    code('ModL', 'Services/VehicleQueryService.php',
      `<?php class V { function q($qb, $search) { return $qb->orWhere('vehicle_type', 'like', "%{$search}%"); } }`);
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/vehicle_type:like/);
  });

  it('ESPECIFICIDADE: Database/ e Tests/ são excluídos (data-fix / fixture cita valor velho)', () => {
    dict('ModX', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModX');
    // data-fix legítimo na migration/seeder + fixture que afirma rejeição — citam 'locacao'.
    code('ModX', 'Database/Seeders/Seed.php',
      `<?php class Seed { function r($qb) { return $qb->where('order_type', 'locacao'); } }`);
    code('ModX', 'Tests/Feature/T.php',
      `<?php class T { function t($qb) { return $qb->where('order_type', 'locacao'); } }`);
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/undeclared-code-value/);
  });

  it('RATCHET: débito de código no baseline não bloqueia; valor de código NOVO bloqueia', () => {
    dict('ModR', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModR');
    code('ModR', 'Http/Controllers/X.php',
      `<?php class X { function q($qb) { return $qb->where('order_type', 'locacao'); } }`);
    run('--write-baseline'); // absorve locacao no código
    expect(run('')).toMatch(/Sem divergências novas/);
    // valor de domínio NOVO aparece no código → bloqueia
    code('ModR', 'Http/Controllers/Y.php',
      `<?php class Y { function q($qb) { return $qb->where('order_type', 'aluguel'); } }`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-code-value:ModR:order_type:aluguel/);
  });
});

// =====================================================================================
// SALTO #4 — termos PROIBIDOS user-facing (trava de regressão ADR 0265)
// =====================================================================================
describe('dominio:check — termos proibidos user-facing (Salto #4, físico)', () => {
  const uiDict = (module: string) =>
    dict(module, { 'service_orders.order_type': ['manutencao', 'mecanica'] }, {
      forbidden_ui_terms: ['locacao', 'cacamba'],
      forbidden_ui_paths: [`resources/js/Pages/${module}`, `Modules/${module}/Database/Seeders`],
    });

  const cleanEnum = (module: string) =>
    migration(module, '2026_01_01_000001_create.php',
      `<?php return new class { public function up(): void {
        Schema::create('service_orders', function ($t) { $t->enum('order_type', ['manutencao', 'mecanica']); });
      } };`);

  const page = (module: string, rel: string, tsx: string) =>
    write(`resources/js/Pages/${module}/${rel}`, tsx);

  it('SENSIBILIDADE: string de UI com "Locação" (acento+caixa) vira forbidden-ui-term', () => {
    uiDict('ModU');
    cleanEnum('ModU');
    page('ModU', 'Index.tsx', `export const label = 'Iniciar Locação (entregar Caçamba)';`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:forbidden-ui-term:ModU:resources\/js\/Pages\/ModU\/Index\.tsx:locacao:1/);
    expect(out).toMatch(/dominio:forbidden-ui-term:ModU:resources\/js\/Pages\/ModU\/Index\.tsx:cacamba:1/);
  });

  it('SENSIBILIDADE: label de seeder com termo proibido é pego', () => {
    uiDict('ModS');
    cleanEnum('ModS');
    code('ModS', 'Database/Seeders/FsmSeeder.php',
      `<?php class FsmSeeder { function defs() { return [['key1', 'Liberar pra locação']]; } }`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:forbidden-ui-term:ModS:Modules\/ModS\/Database\/Seeders\/FsmSeeder\.php:locacao:1/);
  });

  it('ESPECIFICIDADE: comentário explicando a erradicação NÃO conta', () => {
    uiDict('ModN');
    cleanEnum('ModN');
    page('ModN', 'Index.tsx',
      `// locação erradicada (ADR 0265) — não renderizamos mais o card\n` +
      `/* a palavra caçamba aqui também é só explicação */\n` +
      `export const label = 'Reparo';`);
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/forbidden-ui-term/);
  });

  it('RATCHET por contagem: ocorrência NOVA num arquivo já-baselined bloqueia', () => {
    uiDict('ModT');
    cleanEnum('ModT');
    page('ModT', 'Index.tsx', `export const a = 'dias_locacao';`);
    run('--write-baseline'); // absorve a 1ª ocorrência
    expect(run('')).toMatch(/Sem divergências novas/);
    // 2ª ocorrência no MESMO arquivo → chave :2 nova → bloqueia
    page('ModT', 'Index.tsx', `export const a = 'dias_locacao';\nexport const b = 'Locação ativa';`);
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:forbidden-ui-term:ModT:resources\/js\/Pages\/ModT\/Index\.tsx:locacao:2/);
  });

  it('ESPECIFICIDADE: dicionário SEM forbidden_ui_terms não roda o scan (opt-in)', () => {
    dict('ModZ', { 'service_orders.order_type': ['manutencao', 'mecanica'] });
    cleanEnum('ModZ');
    page('ModZ', 'Index.tsx', `export const label = 'Locação';`);
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/forbidden-ui-term/);
  });
});

// ── Onda Q3 — domínios CORE (migrations_paths + tables_scope + code_paths) ─────────────
// Vendas/estoque vivem em database/migrations (não Modules/<X>). O dict declara os paths
// e REIVINDICA tabelas; undeclared-column não cobra tabela alheia do diretório compartilhado.
describe('dominio:check — domínios core (Onda Q3, físico)', () => {
  const coreMig2 = (file: string, php: string) => write(`database/migrations/${file}`, php);
  const coreMigration = (file: string, php: string) => write(`database/migrations/${file}`, php);

  it('SENSIBILIDADE: enum core divergente do dicionário (via migrations_paths) → undeclared-value', () => {
    coreMigration(
      '2026_01_01_000000_create_transactions.php',
      `<?php return new class { public function up(): void {
        Schema::create('transactions', function ($t) { $t->enum('payment_status', ['paid', 'due', 'partial', 'fantasma']); });
      } };`,
    );
    dict('VendasCore', { 'transactions.payment_status': ['paid', 'due', 'partial'] }, {
      migrations_paths: ['database/migrations'],
      tables_scope: ['transactions'],
    });
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-value:VendasCore:transactions\.payment_status:fantasma/);
  });

  it('ESPECIFICIDADE: tables_scope NÃO cobra tabela alheia do diretório compartilhado', () => {
    coreMigration(
      '2026_01_01_000000_create_core.php',
      `<?php return new class { public function up(): void {
        Schema::create('transactions', function ($t) { $t->enum('payment_status', ['paid', 'due']); });
        Schema::create('users', function ($t) { $t->enum('marital_status', ['married', 'unmarried']); });
      } };`,
    );
    dict('VendasCore', { 'transactions.payment_status': ['paid', 'due'] }, {
      migrations_paths: ['database/migrations'],
      tables_scope: ['transactions'],
    });
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/users\.marital_status/); // fora do scope reivindicado
  });

  it('SENSIBILIDADE: sem tables_scope, coluna enum não-declarada no diretório É cobrada (semântica original)', () => {
    coreMigration(
      '2026_01_01_000000_create_core.php',
      `<?php return new class { public function up(): void {
        Schema::create('transactions', function ($t) { $t->enum('payment_status', ['paid']); });
        Schema::create('users', function ($t) { $t->enum('marital_status', ['married']); });
      } };`,
    );
    dict('VendasCore', { 'transactions.payment_status': ['paid'] }, {
      migrations_paths: ['database/migrations'],
    });
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-column:VendasCore:users\.marital_status/);
  });

  it('SALTO #3 core: code_paths aceita ARQUIVO único e pega valor não-canônico', () => {
    coreMigration(
      '2026_01_01_000000_create_transactions.php',
      `<?php return new class { public function up(): void {
        Schema::create('transactions', function ($t) { $t->enum('payment_status', ['paid', 'due']); });
      } };`,
    );
    write('app/Http/Controllers/SellController.php', `<?php class SellController { function f($q) { return $q->where('payment_status', 'estornado'); } }`);
    dict('VendasCore', { 'transactions.payment_status': ['paid', 'due'] }, {
      migrations_paths: ['database/migrations'],
      tables_scope: ['transactions'],
      code_paths: ['app/Http/Controllers/SellController.php'],
    });
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-code-value:VendasCore:payment_status:estornado/);
  });

  it('ESPECIFICIDADE core: valor canônico no code_path único passa limpo', () => {
    coreMigration(
      '2026_01_01_000000_create_transactions.php',
      `<?php return new class { public function up(): void {
        Schema::create('transactions', function ($t) { $t->enum('payment_status', ['paid', 'due']); });
      } };`,
    );
    write('app/Http/Controllers/SellController.php', `<?php class SellController { function f($q) { return $q->where('payment_status', 'due'); } }`);
    dict('VendasCore', { 'transactions.payment_status': ['paid', 'due'] }, {
      migrations_paths: ['database/migrations'],
      tables_scope: ['transactions'],
      code_paths: ['app/Http/Controllers/SellController.php'],
    });
    expect(run('--json')).toMatch(/"ok": true/);
  });

  it('CRONOLOGIA cross-dir: last-write-wins pelo TIMESTAMP da migration, não pelo path', () => {
    // Caso real nfse_emissoes: módulo A cria (05_01), módulo B RE-cria depois (05_11).
    // O estado atual é o de B, mesmo que o path de A ordene depois alfabeticamente.
    write(
      'pacotes/zfirst/migrations/2026_05_01_000000_create_t.php',
      `<?php return new class { public function up(): void {
        Schema::create('t', function ($t) { $t->enum('status', ['velho']); });
      } };`,
    );
    write(
      'pacotes/afterz/migrations/2026_05_11_000000_create_t.php',
      `<?php return new class { public function up(): void {
        Schema::create('t', function ($t) { $t->enum('status', ['novo']); });
      } };`,
    );
    dict('FiscalX', { 't.status': ['novo'] }, {
      migrations_paths: ['pacotes/zfirst/migrations', 'pacotes/afterz/migrations'],
      tables_scope: ['t'],
    });
    // 'novo' (timestamp 05_11) vence 'velho' (05_01) → dict bate com o estado atual.
    expect(run('--json')).toMatch(/"ok": true/);
  });

  it("VOCAB (varchar sem constraint): coluna no vocab nao e cobrada como undeclared-column nem comparada valor-a-valor", () => {
    // migrations legadas registram enum antigo; fisica virou varchar (caso transactions.type)
    coreMig2('2026_01_01_000000_create_t.php', `<?php return new class { public function up(): void {
      Schema::create('t', function ($t) { $t->enum('tipo', ['a', 'b']); });
    } };`);
    dict('CoreV', { }, {
      migrations_paths: ['database/migrations'],
      tables_scope: ['t'],
      vocab: { 't.tipo': ['a', 'b', 'c_do_modulo'] },
    });
    expect(run('--json')).toMatch(/"ok": true/); // sem undeclared-column nem stale-dict-value
  });

  it('VOCAB alimenta o Salto #3: valor fora do vocabulario no codigo = violacao; dentro passa', () => {
    coreMig2('2026_01_01_000000_create_t.php', `<?php return new class { public function up(): void {
      Schema::create('t', function ($t) { $t->string('tipo'); });
    } };`);
    write('app/X.php', `<?php class X { function f($q) { return $q->where('tipo', 'fantasma'); } }`);
    dict('CoreV', { }, {
      migrations_paths: ['database/migrations'],
      tables_scope: ['t'],
      code_paths: ['app/X.php'],
      vocab: { 't.tipo': ['a', 'b'] },
    });
    const out = runJsonExpectFail();
    expect(out).toMatch(/dominio:undeclared-code-value:CoreV:tipo:fantasma/);
  });
});

