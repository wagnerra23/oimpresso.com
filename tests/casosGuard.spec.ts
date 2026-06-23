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

  it('REGEX: formato com hífen (UC-IMP-01 / UC-FORJA-01) é ENXERGADO', () => {
    // O regex antigo (UC-[A-Z]{0,3}\d, sem hífen) era CEGO a prefixo >3 letras + hífen:
    // UC-IMP-*/UC-FORJA-* nunca chegavam ao G-2 → 35 UCs declarados invisíveis (audit
    // 2026-06-22). Trava: UC com hífen sem teste tem que virar ÓRFÃO (= o gate o vê).
    write(page('H'), 'export default function H() { return null }');
    write('resources/js/Pages/H/Index.charter.md', '# c');
    write('resources/js/Pages/H/Index.casos.md', '## UC-IMP-01 · x');
    write('tests/HTest.php', '<?php // UC-IMP-01'); // cobre UC-IMP-01 (string-match)
    run('--write-baseline'); // limpo: UC-IMP-01 é enxergado E coberto
    write('resources/js/Pages/H/Index.casos.md', '## UC-IMP-01 · x\n## UC-FORJA-01 · y');
    const out = runExpectFail('');
    expect(out).toMatch(/uc-orphan:resources\/js\/Pages\/H\/Index\.casos\.md#UC-FORJA-01/);
    expect(out).not.toMatch(/UC-IMP-01/); // coberto → não é órfão (prova que é VISTO)
  });
});

describe('casos:check — G-5 metadata viva (quem · quando · status) (físico)', () => {
  it('SENSIBILIDADE: casos.md sem owner/last_run e UC sem Status falham', () => {
    // baseline limpo com uma tela compliant.
    write(page('M'), 'x');
    write('resources/js/Pages/M/Index.charter.md', '# c');
    write('resources/js/Pages/M/Index.casos.md', '---\nowner: wagner\nlast_run: "2026-06-09"\n---\n## UC-01 · x\n- **Status: ✅**');
    write('tests/MTest.php', '<?php // UC-01');
    run('--write-baseline');
    // tela nova com casos.md SEM frontmatter + UC sem Status.
    write(page('N'), 'x');
    write('resources/js/Pages/N/Index.charter.md', '# c');
    write('resources/js/Pages/N/Index.casos.md', '## UC-02 · y');
    write('tests/NTest.php', '<?php // UC-02');
    const out = runExpectFail('');
    expect(out).toMatch(/meta:missing-owner:resources\/js\/Pages\/N\/Index\.casos\.md/);
    expect(out).toMatch(/meta:missing-last_run:resources\/js\/Pages\/N\/Index\.casos\.md/);
    expect(out).toMatch(/meta:uc-no-status:resources\/js\/Pages\/N\/Index\.casos\.md#UC-02/);
  });

  it('ESPECIFICIDADE: casos.md com owner+last_run+Status por UC NÃO gera meta-violação', () => {
    write(page('Z'), 'x');
    write('resources/js/Pages/Z/Index.charter.md', '# c');
    write('resources/js/Pages/Z/Index.casos.md', '---\nowner: wagner\nlast_run: "2026-06-09"\n---\n## UC-09 · ok\n- **Status: 🧪**');
    write('tests/ZTest.php', '<?php // UC-09');
    run('--write-baseline');
    const out = run('--json');
    expect(out).toMatch(/"metadata_issues": 0/);
  });

  it('SENSIBILIDADE: last_run com formato inválido (não-data) falha', () => {
    write(page('D'), 'x');
    write('resources/js/Pages/D/Index.charter.md', '# c');
    write('resources/js/Pages/D/Index.casos.md', '---\nowner: wagner\nlast_run: ontem\n---\n## UC-01 · x\n- **Status: ✅**');
    write('tests/DTest.php', '<?php // UC-01');
    const out = runExpectFail('--json'); // sem baseline → tudo novo
    expect(out).toMatch(/meta:missing-last_run:resources\/js\/Pages\/D\/Index\.casos\.md/);
  });
});

describe('casos:check — G-6 frescor via git (físico)', () => {
  const git = (cmd: string) => execSync(`git ${cmd}`, { cwd: tmp, stdio: 'ignore' });
  const initRepo = () => {
    git('init -q');
    git('config user.email t@t.co');
    git('config user.name t');
    git('config commit.gpgsign false');
  };
  const seedScreen = (dir: string, lastRun: string) => {
    write(page(dir), 'x');
    write(`resources/js/Pages/${dir}/Index.charter.md`, '# c');
    write(`resources/js/Pages/${dir}/Index.casos.md`, `---\nowner: w\nlast_run: "${lastRun}"\n---\n## UC-01 · x\n- **Status: ✅**`);
    write(`tests/${dir}Test.php`, '<?php // UC-01');
  };

  it('SENSIBILIDADE: .tsx com commit MAIS NOVO que last_run vira stale', () => {
    initRepo();
    seedScreen('S', '2099-01-01'); // last_run no futuro → o commit (hoje) NÃO é mais novo
    git('add -A');
    git('commit -qm init'); // .tsx commit = agora
    run('--write-baseline'); // 0 stale (last_run 2099 > hoje)
    // bumba o last_run pra trás → agora a tela está "mais nova" que os casos.
    write('resources/js/Pages/S/Index.casos.md', '---\nowner: w\nlast_run: "2020-01-01"\n---\n## UC-01 · x\n- **Status: ✅**');
    const out = runExpectFail('');
    expect(out).toMatch(/stale:resources\/js\/Pages\/S\/Index\.casos\.md/);
  });

  it('ESPECIFICIDADE: last_run >= commit da tela NÃO é stale', () => {
    initRepo();
    seedScreen('F', '2099-01-01');
    git('add -A');
    git('commit -qm init');
    const out = run('--json');
    expect(out).toMatch(/"stale_cases": 0/);
  });

  it('GRACIOSO: repo shallow / sem histórico → pula frescor (zero falso-positivo)', () => {
    // tmp NÃO é repo git (sem initRepo) → isShallowRepo()=true → G-6 pula.
    seedScreen('G', '2020-01-01'); // last_run antiga, mas sem git não dá pra saber → não acusa.
    const out = run('--json');
    expect(out).toMatch(/"stale_cases": 0/);
  });
});

// =====================================================================================
// G-7 — STATUS DERIVADO DO VERDE (Salto #2): ✅ declarado vs veredito real (manifesto)
// =====================================================================================
describe('casos:check — G-7 status derivado do verde (físico)', () => {
  // Tela compliant em todas as outras camadas, com Status declarado parametrizável.
  const compliant = (dir: string, uc: string, statusGlyph: string) => {
    write(page(dir), 'x');
    write(`resources/js/Pages/${dir}/Index.charter.md`, '# c');
    write(
      `resources/js/Pages/${dir}/Index.casos.md`,
      `---\nowner: w\nlast_run: "2026-06-09"\n---\n## ${uc} · caso\n- **Status: ${statusGlyph}**`,
    );
    write(`tests/${dir}Test.php`, `<?php // ${uc}`);
  };
  const manifest = (ucs: Record<string, { verdict: string; ran_at?: string }>) =>
    write('scripts/casos-test-results.json', JSON.stringify({ ucs }));

  it('SENSIBILIDADE (lies): ✅ declarado mas teste FALHOU → status:lies', () => {
    compliant('L', 'UC-01', '✅');
    manifest({ 'UC-01': { verdict: 'fail' } });
    const out = runExpectFail('--json'); // sem baseline → tudo novo
    expect(out).toMatch(/status:lies:resources\/js\/Pages\/L\/Index\.casos\.md#UC-01/);
  });

  it('SENSIBILIDADE (unverified): ✅ declarado sem teste verde (manifesto vazio) → status:unverified', () => {
    compliant('U', 'UC-01', '✅');
    manifest({});
    const out = runExpectFail('--json');
    expect(out).toMatch(/status:unverified:resources\/js\/Pages\/U\/Index\.casos\.md#UC-01/);
  });

  it('SENSIBILIDADE (unverified): ✅ com teste SKIP no manifesto → unverified (skip não é prova)', () => {
    compliant('K', 'UC-01', '✅');
    manifest({ 'UC-01': { verdict: 'skip' } });
    const out = runExpectFail('--json');
    expect(out).toMatch(/status:unverified:resources\/js\/Pages\/K\/Index\.casos\.md#UC-01/);
  });

  it('ESPECIFICIDADE: ✅ com teste PASS no manifesto → sem violação de status', () => {
    compliant('P', 'UC-01', '✅');
    manifest({ 'UC-01': { verdict: 'pass' } });
    const out = run('--json'); // tela 100% compliant + verde provado → ok
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/status:/);
  });

  it('ESPECIFICIDADE (honesto): ❌ declarado + teste FALHOU → NÃO é mentira', () => {
    compliant('B', 'UC-01', '❌');
    manifest({ 'UC-01': { verdict: 'fail' } });
    const out = run('--json'); // ❌ é afirmação honesta de quebra → sem status:lies
    expect(out).not.toMatch(/status:lies/);
    expect(out).not.toMatch(/status:unverified/);
  });

  it('ESPECIFICIDADE: 🧪 / ⬜ não são afirmação ✅ → não exigem prova', () => {
    compliant('T', 'UC-01', '🧪');
    manifest({}); // sem prova nenhuma
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/status:/);
  });

  it('ESPECIFICIDADE (regressão): 🧪 com ✅ na PROSA da mesma linha resolve testing, não green', () => {
    // Repro PR #3234 (commit 51a4a2b4ab): `- **Status: 🧪** … devolverem o ✅` fazia o
    // ✅-primeiro ler 'green' e emitir status:stale-results/unverified contra um status que
    // o autor honestamente rebaixou. O glyph declarado é o PRIMEIRO da linha; o resto é prosa.
    write(page('W'), 'x');
    write('resources/js/Pages/W/Index.charter.md', '# c');
    write(
      'resources/js/Pages/W/Index.casos.md',
      '---\nowner: w\nlast_run: "2026-06-09"\n---\n## UC-01 · caso\n- **Status: 🧪** _(volta a ✅ quando o teste passar)_',
    );
    write('tests/WTest.php', '<?php // UC-01');
    manifest({}); // sem prova nenhuma — se lesse 'green', emitiria status:unverified
    const out = run('--json');
    expect(out).toMatch(/"ok": true/);
    expect(out).not.toMatch(/status:/);
  });

  it('GRACIOSO: sem manifesto (bootstrap) → G-7 dorme mesmo com ✅ sem prova', () => {
    compliant('S', 'UC-01', '✅');
    // sem manifest() → arquivo ausente → G-7 não roda.
    const out = run('--json');
    expect(out).toMatch(/"status_unverified": 0/);
  });

  it('RATCHET: lie atual absorvida no baseline; lie NOVA (outro UC) bloqueia', () => {
    write(page('R'), 'x');
    write('resources/js/Pages/R/Index.charter.md', '# c');
    write(
      'resources/js/Pages/R/Index.casos.md',
      '---\nowner: w\nlast_run: "2026-06-09"\n---\n## UC-01 · a\n- **Status: ✅**',
    );
    write('tests/RTest.php', '<?php // UC-01 UC-02');
    manifest({ 'UC-01': { verdict: 'fail' } });
    run('--write-baseline'); // absorve a lie de UC-01
    expect(run('')).toMatch(/Sem violações novas/);
    // adiciona UC-02 ✅ + manifesto fail → lie NOVA bloqueia.
    write(
      'resources/js/Pages/R/Index.casos.md',
      '---\nowner: w\nlast_run: "2026-06-09"\n---\n## UC-01 · a\n- **Status: ✅**\n## UC-02 · b\n- **Status: ✅**',
    );
    manifest({ 'UC-01': { verdict: 'fail' }, 'UC-02': { verdict: 'fail' } });
    const out = runExpectFail('');
    expect(out).toMatch(/status:lies:resources\/js\/Pages\/R\/Index\.casos\.md#UC-02/);
  });
});

// ── Onda Q2 — ratchet SÓ-DESCE do arquivo de baseline (--check-baseline-shrink) ────────
// O ratchet padrão pega violação nova no repo; este modo impede o BASELINE COMMITADO de
// crescer vs a referência (main). Método ADR 0258: visto FALHAR (cresceu) e PASSAR (encolheu).
describe('casos:check — só-desce do baseline (Onda Q2, físico)', () => {
  const baselineWith = (rel: string, violations: string[]) =>
    write(rel, JSON.stringify({ _meta: { gate: 'fixture' }, violations }, null, 2));

  it('SENSIBILIDADE: baseline com entrada NOVA vs referência → exit 1 citando a entrada', () => {
    baselineWith('ref/baseline-main.json', ['trio:missing-casos:resources/js/Pages/A/Index.tsx']);
    baselineWith('scripts/casos-coverage-baseline.json', [
      'trio:missing-casos:resources/js/Pages/A/Index.tsx',
      'trio:missing-casos:resources/js/Pages/B/Index.tsx', // dívida NOVA fotografada
    ]);
    const out = runExpectFail('--check-baseline-shrink ref/baseline-main.json');
    expect(out).toMatch(/Baseline CRESCEU/);
    expect(out).toMatch(/trio:missing-casos:resources\/js\/Pages\/B\/Index\.tsx/);
    expect(out).toMatch(/casos-baseline-grow-approved/); // caminho consciente documentado
  });

  it('ESPECIFICIDADE: baseline ENCOLHEU vs referência → passa reportando a queda', () => {
    baselineWith('ref/baseline-main.json', [
      'trio:missing-casos:resources/js/Pages/A/Index.tsx',
      'trio:missing-casos:resources/js/Pages/B/Index.tsx',
    ]);
    baselineWith('scripts/casos-coverage-baseline.json', [
      'trio:missing-casos:resources/js/Pages/A/Index.tsx',
    ]);
    const out = run('--check-baseline-shrink ref/baseline-main.json');
    expect(out).toMatch(/só-desce OK/);
    expect(out).toMatch(/caiu −1/);
  });

  it('ESPECIFICIDADE: baseline idêntico → estável, passa', () => {
    baselineWith('ref/baseline-main.json', ['x:1']);
    baselineWith('scripts/casos-coverage-baseline.json', ['x:1']);
    expect(run('--check-baseline-shrink ref/baseline-main.json')).toMatch(/estável/);
  });

  it('GRACIOSO: referência ausente (bootstrap) → exit 0 sem efeito', () => {
    baselineWith('scripts/casos-coverage-baseline.json', ['x:1']);
    expect(run('--check-baseline-shrink ref/nao-existe.json')).toMatch(/bootstrap/);
  });
});
