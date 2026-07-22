#!/usr/bin/env node
// Teste do block-edit-authority-generated.mjs. Cada caso deriva do CONTRATO
// (ADR 0256 — derivado+enforçado sobrevive; grade guardrails 2026-07-22 "editar
// gerado à mão"), NÃO da implementação. Prova: bloqueia edição de `authority:
// generated`, libera o resto, é content-agnostic, tem escape, e é fail-open.
//
// Rodar: node .claude/hooks/block-edit-authority-generated.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import {
  shouldBlock, declaresGenerated, extractFrontmatter, regeneratorHint, blockMessage,
} from './block-edit-authority-generated.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-edit-authority-generated.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

const GEN = `---
name: "SUPERFÍCIE — Jana"
description: "Índice GERADO. NÃO editar à mão."
authority: generated
module: Jana
---
# corpo`;
const GEN_QUOTED = `---\nauthority: "generated"\n---\nx`;
const GEN_CRLF = `---\r\nauthority: generated\r\n---\r\nx`;
const REF = `---\nname: nota\nauthority: reference\n---\ncorpo`;
const NO_FM = `# doc sem frontmatter\ntexto`;
// falso-positivo: menção no CORPO, não no frontmatter → NÃO deve bloquear
const BODY_MENTION = `---\nname: doc\nauthority: reference\n---\nEste doc explica \`authority: generated\` mas não é gerado.`;

// ── declaresGenerated: só o FRONTMATTER conta (guard anti-falso-positivo) ────────
check('declaresGenerated: frontmatter generated → true', declaresGenerated(GEN) === true);
check('declaresGenerated: quoted → true', declaresGenerated(GEN_QUOTED) === true);
check('declaresGenerated: CRLF → true', declaresGenerated(GEN_CRLF) === true);
check('declaresGenerated: reference → false', declaresGenerated(REF) === false);
check('declaresGenerated: sem frontmatter → false', declaresGenerated(NO_FM) === false);
check('declaresGenerated: menção no CORPO → false (não é allowlist sintática)', declaresGenerated(BODY_MENTION) === false);
check('extractFrontmatter: pega o bloco / vazio sem fm', /authority: generated/.test(extractFrontmatter(GEN)) && extractFrontmatter(NO_FM) === '');

// ── shouldBlock: veredito puro (tool × path × conteúdo × env) ────────────────────
check('BLOCK: Write em gerado', shouldBlock('Write', '/r/memory/requisitos/Jana/SUPERFICIE.md', GEN) === true);
check('BLOCK: Edit em gerado', shouldBlock('Edit', '/r/x/SUPERFICIE.md', GEN) === true);
check('BLOCK: MultiEdit em gerado', shouldBlock('MultiEdit', '/r/x/SUPERFICIE.md', GEN) === true);
check('ALLOW: Read (não é write tool)', shouldBlock('Read', '/r/x/SUPERFICIE.md', GEN) === false);
check('ALLOW: arquivo novo (currentContent null)', shouldBlock('Write', '/r/x/NOVO.md', null) === false);
check('ALLOW: arquivo não-gerado (reference)', shouldBlock('Edit', '/r/x/SPEC.md', REF) === false);
check('ALLOW: menção só no corpo', shouldBlock('Edit', '/r/x/doc.md', BODY_MENTION) === false);
check('ALLOW: path vazio', shouldBlock('Write', '', GEN) === false);
check('ALLOW: escape env OIMPRESSO_ALLOW_GENERATED_EDIT=1', shouldBlock('Write', '/r/x/SUPERFICIE.md', GEN, { OIMPRESSO_ALLOW_GENERATED_EDIT: '1' }) === false);

// ── hint + mensagem ─────────────────────────────────────────────────────────────
check('regeneratorHint: SUPERFICIE → module-surface <Mod> --write', /module-surface\.mjs Jana --write/.test(regeneratorHint('x/memory/requisitos/Jana/SUPERFICIE.md')));
check('regeneratorHint: outro gerado → genérico', /GERADOR dono/.test(regeneratorHint('x/memory/reference/PAINEL-SISTEMA.md')));
check('blockMessage: cita ADR 0256 + regenerador + escape', (() => {
  const m = blockMessage('Edit', 'x/memory/requisitos/Sells/SUPERFICIE.md');
  return /0256/.test(m) && /module-surface\.mjs Sells --write/.test(m) && /OIMPRESSO_ALLOW_GENERATED_EDIT=1/.test(m);
})());

// ── E2E: stdin JSON + arquivo REAL → exit code (prova a leitura + fail-open) ─────
const dir = mkdtempSync(join(tmpdir(), 'blk-gen-'));
const write = (name, body) => { const p = join(dir, name); writeFileSync(p, body, 'utf8'); return p; };
const pGen = write('SUPERFICIE.md', GEN);
const pRef = write('SPEC.md', REF);
const j = (tool, path) => JSON.stringify({ tool_name: tool, tool_input: { file_path: path } });
const runHook = (stdin, env) => spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', env: { ...process.env, ...(env || {}) } }).status;

try {
  check('E2E: Write em gerado real → exit 2 (BLOQUEIA)', runHook(j('Write', pGen)) === 2);
  check('E2E: Edit em gerado real → exit 2', runHook(j('Edit', pGen)) === 2);
  check('E2E: arquivo reference real → exit 0', runHook(j('Edit', pRef)) === 0);
  check('E2E: path inexistente (arquivo novo) → exit 0 (fail-open)', runHook(j('Write', join(dir, 'INEXISTE.md'))) === 0);
  check('E2E: Read em gerado → exit 0 (não é write tool)', runHook(j('Read', pGen)) === 0);
  check('E2E: escape env → exit 0', runHook(j('Write', pGen), { OIMPRESSO_ALLOW_GENERATED_EDIT: '1' }) === 0);
  check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
  check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava)', runHook('{lixo') === 0);
} finally {
  rmSync(dir, { recursive: true, force: true });
}

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — bloqueia edição de authority:generated (39 SUPERFICIE + irmãos), guard anti-falso-positivo, escape consciente, fail-open provado (E2E).');
process.exit(fails ? 1 : 0);
