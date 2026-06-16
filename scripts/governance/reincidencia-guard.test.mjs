#!/usr/bin/env node
// reincidencia-guard.test.mjs — CONTROLE-NEGATIVO (padrão GT-G6 · "quem vigia os vigias").
// Prova que reincidencia-guard.mjs MORDE o caso ruim (C3 fundido, C4 ref morta) E PASSA
// quando limpo / quando a violação já está no baseline. Monta repo-fixtures temporários e
// exercita o MESMO code path (cwd-based) do guard real.
// Rodar: node scripts/governance/reincidencia-guard.test.mjs — exit 0 = passa.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'reincidencia-guard.mjs');
let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

function sandbox() {
  const root = mkdtempSync(join(tmpdir(), 'reincidencia-'));
  mkdirSync(join(root, 'memory', 'handoffs'), { recursive: true });
  mkdirSync(join(root, 'governance'), { recursive: true });
  mkdirSync(join(root, 'prototipo-ui'), { recursive: true });
  return root;
}
const run = (root, extra = []) => spawnSync('node', [SCRIPT, ...extra], { cwd: root, encoding: 'utf8' });
const out = (r) => (r.stdout || '') + (r.stderr || '');
const baseline = (root, violations = []) =>
  writeFileSync(join(root, 'governance', 'reincidencia-baseline.json'),
    JSON.stringify({ _meta: { count: violations.length }, violations }, null, 2));
const handoff = (root, name, body) => writeFileSync(join(root, 'memory', 'handoffs', name), body);

// 1. fixture LIMPA (handoff válido + ref VIVA) → exit 0 (não morde o inocente)
let root = sandbox();
handoff(root, 'alvo.md', '# alvo\nok\n');
handoff(root, 'clean.md', '# limpo\n**Status:** ok\n\nVer [alvo](alvo.md) e `memory/handoffs/alvo.md`.\n');
baseline(root, []);
let r = run(root);
check('limpo (ref viva) → exit 0', r.status === 0);
rmSync(root, { recursive: true, force: true });

// 2. C3 cabeçalho fundido → exit 1 + acusa C3
root = sandbox();
baseline(root, []);
handoff(root, 'c3.md', '# fundido\n**A:** > **B:** x\n');
r = run(root);
check('C3 fundido → exit 1', r.status === 1);
check('C3 fundido → acusa C3', /C3/.test(out(r)));
rmSync(root, { recursive: true, force: true });

// 3. C4 ref morta (link md) → exit 1 + acusa C4
root = sandbox();
baseline(root, []);
handoff(root, 'c4.md', '# morta\nVer [sumiu](memory/nao/existe.md).\n');
r = run(root);
check('C4 ref morta → exit 1', r.status === 1);
check('C4 ref morta → acusa C4', /C4|ref morta/i.test(out(r)));
rmSync(root, { recursive: true, force: true });

// 4. C4 em `code` com extensão inexistente → morde; sem extensão / Modules<X> → NÃO morde
root = sandbox();
baseline(root, []);
handoff(root, 'mix.md', '# mix\n`scripts/governance/nao-existe.mjs` morto.\n' +
  '`business_id` e `Modules/Fantasma/Service.php` NÃO contam (sem path / domínio do knowledge-drift).\n');
r = run(root);
check('C4 code-path inexistente → exit 1', r.status === 1);
check('C4 ignora não-path e Modules/<X>', !/business_id|Fantasma/.test(out(r)));
rmSync(root, { recursive: true, force: true });

// 5. violação congelada no baseline → exit 0 (ratchet respeita legado)
root = sandbox();
handoff(root, 'c4.md', '# morta\nVer [sumiu](memory/nao/existe.md).\n');
baseline(root, ['memory/handoffs/c4.md::C4::memory/nao/existe.md']);
r = run(root);
check('violação no baseline → exit 0', r.status === 0);
rmSync(root, { recursive: true, force: true });

// 6. --write congela tudo e zera o NOVO
root = sandbox();
handoff(root, 'c4.md', '# morta\nVer [sumiu](memory/nao/existe.md).\n');
run(root, ['--write']);
r = run(root);
check('pós --write → exit 0 (virou baseline)', r.status === 0);
rmSync(root, { recursive: true, force: true });

console.log(fails ? `\n${fails} FALHA(S)` : '\nTODOS OS TESTES PASSARAM');
process.exit(fails ? 1 : 0);
