// Camada META — teste FÍSICO (caixa-preta) do guard casos:check (ADR 0264 G-1/G-2).
//
// Regra do MÉTODO ("todo ✅ tem que ter sido visto falhar"): monta um repo-fixture num
// dir temporário, RODA o script real (node, subprocess) e exige o comportamento —
// sensibilidade (pega violação nova), especificidade (não acusa trio completo) e o
// ratchet (baseline absorve o legado; só o novo falha). Não é mock: é o .mjs de verdade.
//
// Cobre: scripts/casos-coverage-guard.mjs
// Refs: ADR 0264 · padrão governanceAdrScripts.spec.ts / foundationGuard.spec.ts.

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';

const REPO = process.cwd();
const GUARD = resolve(REPO, 'scripts/casos-coverage-guard.mjs');

let tmp: string;

const write = (rel: string, content: string) => {
  const full = join(tmp, rel);
  mkdirSync(dirname(full), { recursive: true });
  writeFileSync(full, content);
};

const page = (dir: string) => `resources/js/Pages/${dir}/Index.tsx`;

beforeEach(() => {
  tmp = mkdtempSync(join(tmpdir(), 'casos-'));
  mkdirSync(join(tmp, 'scripts'), { recursive: true }); // destino do baseline
  mkdirSync(join(tmp, 'resources/js/Pages'), { recursive: true });
});
afterEach(() => rmSync(tmp, { recursive: true, force: true }));

const run = (args: string) =>
  execSync(`node "${GUARD}" ${args}`, { cwd: tmp, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });

const runExpectFail = (args: string): string => {
  try {
    run(args);
    throw new Error('esperava exit 1, mas passou');
  } catch (e: any) {
    if (e?.message === 'esperava exit 1, mas passou') throw e;
    return (e.stdout || '') + (e.stderr || '');
  }
};

describe('casos:check — G-1 trio-de-tela (físico)', () => {
  it('SENSIBILIDADE: tela NOVA sem charter/casos falha vs baseline (ratchet)', () => {
    // Legado: Foo sem trio é absorvido no baseline.
    write(page('Foo'), 'export default function Foo() { return null }');
    run('--write-baseline');
    // Novo: Bar sem trio → tem que falhar.
    write(page('Bar'), 'export default function Bar() { return null }');
    const out = runExpectFail('');
    expect(out).toMatch(/trio:missing-charter:resources\/js\/Pages\/Bar\/Index\.tsx/);
    expect(out).toMatch(/trio:missing-casos:resources\/js\/Pages\/Bar\/Index\.tsx/);
  });

  it('ESPECIFICIDADE: tela com trio completo + UC com teste NÃO viola', () => {
    write(page('Ok'), 'export default function Ok() { return null }');
    write('resources/js/Pages/Ok/Index.charter.md', '# charter');
    write('resources/js/Pages/Ok/Index.casos.md', '## UC-01 · faz algo');
    write('tests/OkTest.php', '<?php // cobre UC-01');
    run('--write-baseline');
    const out = run(''); // passa
    expect(out).toMatch(/Sem violações novas/);
  });

  it('ESPECIFICIDADE: .tsx sob /_components/ NÃO conta como página', () => {
    write('resources/js/Pages/Mod/_components/Card.tsx', 'export const Card = () => null');
    run('--write-baseline');
    // Sem páginas roteadas → 0 violações; check passa.
    const out = run('');
    expect(out).toMatch(/Sem violações novas/);
  });
});

describe('casos:check — G-2 rastreabilidade caso↔teste (físico)', () => {
  it('SENSIBILIDADE: UC NOVO sem teste vira órfão e falha', () => {
    write(page('A'), 'export default function A() { return null }');
    write('resources/js/Pages/A/Index.charter.md', '# c');
    write('resources/js/Pages/A/Index.casos.md', '## UC-01 · x');
    write('tests/ATest.php', '<?php // UC-01');
    run('--write-baseline'); // estado limpo (UC-01 tem teste)
    // Adiciona UC-02 ao casos.md SEM teste → órfão novo.
    write('resources/js/Pages/A/Index.casos.md', '## UC-01 · x\n## UC-02 · y');
    const out = runExpectFail('');
    expect(out).toMatch(/uc-orphan:resources\/js\/Pages\/A\/Index\.casos\.md#UC-02/);
    expect(out).not.toMatch(/UC-01/); // UC-01 tem teste, não é órfão
  });

  it('ESPECIFICIDADE: UC citado por teste Playwright (e2e/) não é órfão', () => {
    write(page('B'), 'export default function B() { return null }');
    write('resources/js/Pages/B/Index.charter.md', '# c');
    write('resources/js/Pages/B/Index.casos.md', '## UC-V05 · split fiscal');
    write('e2e/vendas-uc-v05.spec.ts', "test('UC-V05 split', async () => {})");
    run('--write-baseline');
    const out = run('');
    expect(out).toMatch(/Sem violações novas/);
  });
});
