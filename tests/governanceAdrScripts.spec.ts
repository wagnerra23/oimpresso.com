// Camada META — teste FÍSICO (integração caixa-preta) dos scripts de governança de ADR.
//
// Regra do MÉTODO ("todo ✅ tem que ter sido visto falhar"): cria ADRs-fixture num dir
// temporário, RODA o script real (node, subprocess) e exige o comportamento — incluindo
// os bugs que já mordemos (parser pegando ano/hue "2026"/"295"; superseded_by em número
// cru em vez de slug). Não é mock: é o .mjs de verdade contra disco real.
//
// Cobre: scripts/governance/adr-index-generate.mjs + adr-supersede.mjs
// Refs: ADR 0256/0257/0258 · padrão foundationGuard.spec.ts (controle-negativo).

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const REPO = process.cwd();
const GEN = resolve(REPO, 'scripts/governance/adr-index-generate.mjs');
const SUP = resolve(REPO, 'scripts/governance/adr-supersede.mjs');
const MH = resolve(REPO, 'scripts/governance/memory-health.mjs');

let tmp: string;
const adr = (dir: string, file: string, fm: string) =>
  writeFileSync(join(dir, 'memory/decisions', file), `---\n${fm}\n---\n\n# ${file}\n\nCorpo.\n`);

beforeEach(() => {
  tmp = mkdtempSync(join(tmpdir(), 'adrgov-'));
  mkdirSync(join(tmp, 'memory/decisions'), { recursive: true });
});
afterEach(() => rmSync(tmp, { recursive: true, force: true }));

const run = (cmd: string) => execSync(cmd, { cwd: tmp, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });

describe('adr-index-generate — GERADOR (físico)', () => {
  it('SENSIBILIDADE: detecta colisão de número + conta ATIVOS normalizando grafia', () => {
    adr(tmp, '0901-alpha.md', 'slug: 0901-alpha\nnumber: 901\ntitle: "Alpha"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0902-collide.md', 'slug: 0902-collide\nnumber: 902\ntitle: "C1"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0902-beta.md', 'slug: 0902-beta\nnumber: 902\ntitle: "C2"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0903-drift.md', 'slug: 0903-drift\nnumber: 903\ntitle: "Drift"\ntype: adr\nstatus: accepted\nauthority: canonical\nlifecycle: active\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    run(`node "${GEN}" --write`);
    const idx = readFileSync(join(tmp, 'memory/decisions/_INDEX-GENERATED.md'), 'utf8');
    expect(idx).toContain('**0902** ×2'); // colisão detectada
    expect(idx).toMatch(/ATIVOS \(lifecycle ativo\): 4/); // 0903 active→ativo normalizado entra na conta
    expect(idx).toMatch(/aceito 4/); // 0903 accepted→aceito normalizado
  });

  it('ESPECIFICIDADE: não inventa "supersedes 2026/295" de comentário/ano (regressão do parser)', () => {
    adr(tmp, '0905-superseder.md',
      'slug: 0905-superseder\nnumber: 905\ntitle: "Sup"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsupersedes:\n  - \'0901-alpha\'  # adotado 2026-01, hue 295');
    adr(tmp, '0901-alpha.md', 'slug: 0901-alpha\nnumber: 901\ntitle: "Alpha"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    run(`node "${GEN}" --write`);
    const idx = readFileSync(join(tmp, 'memory/decisions/_INDEX-GENERATED.md'), 'utf8');
    expect(idx).not.toMatch(/supersede[^\n]*2026/i); // NÃO captura o ano do comentário
    expect(idx).not.toMatch(/supersede[^\n]*\b295\b/);  // NÃO captura o hue
    expect(idx).toMatch(/0905 supersedes 0901/);        // captura o alvo real (0901 não marcada)
  });
});

describe('adr-supersede — SUPERSEDE ATÔMICO (físico)', () => {
  it('rebaixa a antiga com superseded_by em SLUG (regressão: era número cru)', () => {
    adr(tmp, '0911-old.md', 'slug: 0911-old\nnumber: 911\ntitle: "Old"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0912-new.md', 'slug: 0912-new\nnumber: 912\ntitle: "New"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsupersedes: [\'0911-old\']');
    run(`node "${SUP}" --new 0912 --old 0911 --write`);
    const old = readFileSync(join(tmp, 'memory/decisions/0911-old.md'), 'utf8');
    expect(old).toMatch(/^status: superseded$/m);
    expect(old).toMatch(/^lifecycle: substituido$/m);
    expect(old).toMatch(/^superseded_by: \['0912-new'\]$/m); // SLUG, não [0912]
    expect(old).not.toMatch(/superseded_by: \[0912\]/);       // o bug que consertamos
  });

  it('SENSIBILIDADE: número colidido exige slug (não adivinha)', () => {
    adr(tmp, '0913-x.md', 'slug: 0913-x\nnumber: 913\ntitle: "X"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0913-y.md', 'slug: 0913-y\nnumber: 913\ntitle: "Y"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0914-z.md', 'slug: 0914-z\nnumber: 914\ntitle: "Z"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    let threw = false; let msg = '';
    try { run(`node "${SUP}" --new 0914 --old 0913 --write`); }
    catch (e: any) { threw = true; msg = (e.stdout || '') + (e.stderr || ''); }
    expect(threw).toBe(true);            // colisão → erro (não escolhe sozinho)
    expect(msg).toMatch(/colide|slug/i);
  });
});

describe('GAP 1 — supersede-integrity como GATE DURO (--check, ADR 0258)', () => {
  it('--check FALHA quando A supersedes B mas B não está marcada superseded', () => {
    adr(tmp, '0920-old.md', 'slug: 0920-old\nnumber: 920\ntitle: "Old"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0921-new.md', 'slug: 0921-new\nnumber: 921\ntitle: "New"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsupersedes: [\'0920-old\']');
    run(`node "${GEN}" --write`); // gera o índice (com o alerta)
    let threw = false; let msg = '';
    try { run(`node "${GEN}" --check`); }
    catch (e: any) { threw = true; msg = (e.stdout || '') + (e.stderr || ''); }
    expect(threw).toBe(true);                 // supWarn>0 → gate bloqueia
    expect(msg).toMatch(/supersess|0920/i);
  });
});

describe('GAP 3 — invariante anti-ressurreição (memory-health Check F, ADR 0258/0061)', () => {
  it('FALHA se memory/claude/ reaparece', () => {
    mkdirSync(join(tmp, 'memory/claude'), { recursive: true });
    writeFileSync(join(tmp, 'memory/claude/legado.md'), '# legado ressuscitado');
    let threw = false; let msg = '';
    try { run(`node "${MH}"`); } // sem --warn-only → fail-class bloqueia
    catch (e: any) { threw = true; msg = (e.stdout || '') + (e.stderr || ''); }
    expect(threw).toBe(true);
    expect(msg).toMatch(/ressuscit|claude/i);
  });
  it('NÃO acusa quando memory/claude não existe (especificidade)', () => {
    const out = run(`node "${MH}" --warn-only`);
    expect(out).not.toMatch(/\[F\]/); // Check F sem fail
  });
});

describe('GAP 2 — memory-health ENFORCE + baseline ratchet (Check C, ADR 0258)', () => {
  it('FALHA (enforce) em segredo NOVO fora do baseline', () => {
    writeFileSync(join(tmp, 'memory/vazou.md'), '# doc\n\nPASSWORD: hunter2secretLeak123\n');
    let threw = false; let msg = '';
    try { run(`node "${MH}"`); } // sem --warn-only = enforce; sem baseline no tmp = tudo novo
    catch (e: any) { threw = true; msg = (e.stdout || '') + (e.stderr || ''); }
    expect(threw).toBe(true);
    expect(msg).toMatch(/segredo|memory/i);
  });
  it('--update-baseline aceita o atual e a próxima rodada passa (ratchet)', () => {
    mkdirSync(join(tmp, 'scripts/governance'), { recursive: true }); // destino do baseline
    writeFileSync(join(tmp, 'memory/legado.md'), '# doc\n\nPASSWORD: defaultDocValue123\n');
    run(`node "${MH}" --update-baseline`); // aceita o estado atual
    const out = run(`node "${MH}"`); // agora passa (nada NOVO acima do baseline)
    expect(out).toMatch(/0 .*fail|saudável|🩺/i);
  });
});

describe('colisão-de-número — catraca anti-bifurcação (--check, baseline grandfather)', () => {
  it('SENSIBILIDADE: colisão NOVA (fora do baseline) → --check FALHA', () => {
    // tmp não tem governance/adr-collisions-baseline.json → toda colisão é nova (fail-closed)
    adr(tmp, '0950-a.md', 'slug: 0950-a\nnumber: 950\ntitle: "A"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0950-b.md', 'slug: 0950-b\nnumber: 950\ntitle: "B"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    run(`node "${GEN}" --write`); // gera o índice (lista a colisão, sem drift)
    let threw = false; let msg = '';
    try { run(`node "${GEN}" --check`); }
    catch (e: any) { threw = true; msg = (e.stdout || '') + (e.stderr || ''); }
    expect(threw).toBe(true);                 // colisão nova → gate bloqueia
    expect(msg).toMatch(/colis/i);
    expect(msg).toMatch(/0950/);
  });

  it('ESPECIFICIDADE: colisão grandfathered no baseline → --check PASSA', () => {
    mkdirSync(join(tmp, 'governance'), { recursive: true });
    writeFileSync(join(tmp, 'governance/adr-collisions-baseline.json'),
      JSON.stringify({ collisions_grandfathered: ['0950'] }));
    adr(tmp, '0950-a.md', 'slug: 0950-a\nnumber: 950\ntitle: "A"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    adr(tmp, '0950-b.md', 'slug: 0950-b\nnumber: 950\ntitle: "B"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"');
    run(`node "${GEN}" --write`);
    const out = run(`node "${GEN}" --check`); // exit 0 (grandfathered)
    expect(out).toMatch(/grandfathered/i);
    expect(out).toMatch(/em dia/i);
  });
});

describe('double-supersede — >1 ADR herdando a mesma (adversário 2026-06-20, físico)', () => {
  it('SENSIBILIDADE: 2 ADRs supersedem o MESMO número → --check FALHA citando conflito de herança', () => {
    adr(tmp, '0930-target.md', 'slug: 0930-target\nnumber: 930\ntitle: "T"\ntype: adr\nstatus: superseded\nauthority: canonical\nlifecycle: substituido\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsuperseded_by: [\'0931-a\']');
    adr(tmp, '0931-a.md', 'slug: 0931-a\nnumber: 931\ntitle: "A"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsupersedes: [\'0930-target\']');
    adr(tmp, '0932-b.md', 'slug: 0932-b\nnumber: 932\ntitle: "B"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsupersedes: [\'0930-target\']');
    run(`node "${GEN}" --write`);
    let threw = false; let msg = '';
    try { run(`node "${GEN}" --check`); }
    catch (e: any) { threw = true; msg = (e.stdout || '') + (e.stderr || ''); }
    expect(threw).toBe(true);                          // double-supersede entra em supWarn → gate bloqueia
    expect(msg).toMatch(/double-supersede|conflito de herança/i);
    expect(msg).toMatch(/0930/);
  });

  it('ESPECIFICIDADE: supersede 1→1 (sucessora única) → sem conflito, --check passa', () => {
    adr(tmp, '0940-old.md', 'slug: 0940-old\nnumber: 940\ntitle: "Old"\ntype: adr\nstatus: superseded\nauthority: canonical\nlifecycle: substituido\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsuperseded_by: [\'0941-new\']');
    adr(tmp, '0941-new.md', 'slug: 0941-new\nnumber: 941\ntitle: "New"\ntype: adr\nstatus: aceito\nauthority: canonical\nlifecycle: ativo\ndecided_by: [W]\ndecided_at: "2026-06-07"\nsupersedes: [\'0940-old\']');
    run(`node "${GEN}" --write`);
    const out = run(`node "${GEN}" --check`); // exit 0 (não lança)
    expect(out).not.toMatch(/double-supersede|conflito de herança/i);
    expect(out).toMatch(/em dia|íntegra/i);
  });
});
