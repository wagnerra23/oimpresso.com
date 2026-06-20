// Camada META — teste FÍSICO (caixa-preta) dos checks G/H/I do memory-health (Onda Q5).
//
// Método ADR 0258: todo check é visto FALHAR (sensibilidade) e PASSAR (especificidade)
// num fixture físico antes de valer. Roda o .mjs real (subprocess) num dir temporário.
//
// Cobre: scripts/governance/memory-health.mjs (Check G registry de gates · Check H
// frescor "✓lido @main" · Check I lição sem asserção).
// Refs: ADR 0256 · ADR 0258 · padrão casosGuard.spec.ts / dominioGuard.spec.ts.

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';

const REPO = process.cwd();
const HEALTH = resolve(REPO, 'scripts/governance/memory-health.mjs');

let tmp: string;

const write = (rel: string, content: string) => {
  const full = join(tmp, rel);
  mkdirSync(dirname(full), { recursive: true });
  writeFileSync(full, content);
};

const registry = (workflows: Record<string, unknown>) =>
  write('scripts/governance/gates-registry.json', JSON.stringify({ workflows }, null, 2));

beforeEach(() => {
  tmp = mkdtempSync(join(tmpdir(), 'memhealth-'));
  mkdirSync(join(tmp, 'memory'), { recursive: true });
});
afterEach(() => rmSync(tmp, { recursive: true, force: true }));

const run = (args = '--json') =>
  execSync(`node "${HEALTH}" ${args}`, { cwd: tmp, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });

const runExpectFail = (args = '--json'): string => {
  try {
    run(args);
    throw new Error('esperava exit 1, mas passou');
  } catch (e: any) {
    if (e?.message === 'esperava exit 1, mas passou') throw e;
    return (e.stdout || '') + (e.stderr || '');
  }
};

describe('memory-health — Check G: registry canônico de gates (Onda Q5, físico)', () => {
  it('SENSIBILIDADE: workflow NOVO fora do registry → 🔴 fail citando o arquivo', () => {
    write('.github/workflows/gate-novo.yml', 'name: x\non: pull_request\n');
    registry({}); // censo vazio — gate-novo.yml não registrado
    const out = runExpectFail();
    expect(out).toMatch(/workflow-fora-do-registry/);
    expect(out).toMatch(/gate-novo\.yml/);
  });

  it('ESPECIFICIDADE: workflow registrado no censo → sem fail G', () => {
    write('.github/workflows/gate-novo.yml', 'name: x\non: pull_request\n');
    registry({ 'gate-novo.yml': { nome: 'x', classe: 'gate' } });
    const out = run();
    expect(out).not.toMatch(/workflow-fora-do-registry/);
  });

  it('SENSIBILIDADE: registry ausente com workflows presentes → 🔴 registry-ausente', () => {
    write('.github/workflows/gate-novo.yml', 'name: x\n');
    const out = runExpectFail();
    expect(out).toMatch(/registry-ausente/);
  });

  it('🟡 entrada órfã (workflow apagado, censo não atualizado) → warn, não fail', () => {
    mkdirSync(join(tmp, '.github/workflows'), { recursive: true });
    registry({ 'apagado.yml': { nome: 'morto', classe: 'gate' } });
    const out = run();
    expect(out).toMatch(/registry-entrada-orfa/);
    expect(out).toMatch(/"ok": true/); // warn não bloqueia
  });
});

describe('memory-health — Check H: frescor "✓lido @main" (Onda Q5, físico)', () => {
  it('SENSIBILIDADE: carimbo com >14 dias → 🟡 doc-cache-stale', () => {
    write('memory/censo-gates.md', '# censo\n\n✓lido @main 2026-01-01\n');
    const out = run();
    expect(out).toMatch(/doc-cache-stale/);
    expect(out).toMatch(/censo-gates\.md/);
  });

  it('ESPECIFICIDADE: carimbo fresco (hoje) → sem warn H', () => {
    const hoje = new Date().toISOString().slice(0, 10);
    write('memory/censo-gates.md', `# censo\n\n✓lido @main ${hoje}\n`);
    const out = run();
    expect(out).not.toMatch(/doc-cache-stale/);
  });
});

describe('memory-health — Check I: lição sem asserção (Onda Q5, físico)', () => {
  it('SENSIBILIDADE: lição sem gate/G#/IT# nem `não-mecanizável:` → 🟡 citando o id', () => {
    write('memory/LICOES_CC.md', '# lições\n\n## L-99 — esqueci de tudo\n- **Erro:** x\n- **Regra:** prestar atenção.\n');
    const out = run();
    expect(out).toMatch(/licao-sem-assercao/);
    expect(out).toMatch(/L-99/);
  });

  it('ESPECIFICIDADE: lição apontando gate (G-7) passa limpa', () => {
    write('memory/LICOES_CC.md', '# lições\n\n## L-98 — status mentia\n- **Regra:** veredito vem do manifesto G-7.\n');
    const out = run();
    expect(out).not.toMatch(/licao-sem-assercao/);
  });

  it('ESPECIFICIDADE: lição declarada `não-mecanizável:` passa limpa', () => {
    write('memory/LICOES_CC.md', '# lições\n\n## L-97 — tom de conversa\n- não-mecanizável: julgamento humano.\n');
    const out = run();
    expect(out).not.toMatch(/licao-sem-assercao/);
  });
});

describe('memory-health — Check K: decisão em session log sem âncora (Onda armar-gates, físico)', () => {
  it('SENSIBILIDADE: session log >30d com `## Decisão` sem ADR aceito/BRIEFING → 🟡 K, não bloqueia', () => {
    write('memory/sessions/2026-01-01-perdido.md', '---\ndate: "2026-01-01"\n---\n\n# Sessão\n\n## Decisão\n\nFazer X. US-FOO-001 em rollout por ondas.\n');
    const out = run();
    expect(out).toMatch(/session-decisao-sem-ancora/);
    expect(out).toMatch(/2026-01-01-perdido\.md/);
    expect(out).toMatch(/"ok": true/); // warn não bloqueia
  });

  it('ESPECIFICIDADE: referencia ADR ACEITO existente → sem warn K', () => {
    write('memory/decisions/0294-metodo.md', '---\nstatus: aceito\nlifecycle: ativo\n---\n\n# ADR 0294\n');
    write('memory/sessions/2026-01-02-ancorado.md', '---\ndate: "2026-01-02"\n---\n\n# Sessão\n\n## Decisão\n\nDecisão landou na ADR 0294. rollout ok.\n');
    const out = run();
    expect(out).not.toMatch(/session-decisao-sem-ancora/);
  });

  it('ESPECIFICIDADE: session log RECENTE (<30d) com decisão → sem warn K (filtro de idade)', () => {
    write('memory/sessions/2026-06-19-fresco.md', '---\ndate: "2026-06-19"\n---\n\n# Sessão\n\n## Decisão\n\nDecidiu Y. rollout amanhã.\n');
    const out = run();
    expect(out).not.toMatch(/session-decisao-sem-ancora/);
  });
});
