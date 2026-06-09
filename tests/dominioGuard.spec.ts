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

const dict = (module: string, enums: Record<string, string[]>) =>
  write(`memory/dominio/${module.toLowerCase()}.md`, '# dict\n\n```json\n' + JSON.stringify({ module, enums }) + '\n```\n');

const migration = (module: string, file: string, php: string) =>
  write(`Modules/${module}/Database/Migrations/${file}`, php);

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
