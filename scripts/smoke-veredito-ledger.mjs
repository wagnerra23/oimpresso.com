#!/usr/bin/env node
// smoke-veredito-ledger.mjs — Smoke / acceptance harness do programa veredito-ledger.
//
// Roda num comando só os checks LOCAIS-VERIFICÁVEIS de ponta a ponta (a catraca morde, o schema
// conhece o `recusado`, o escopo não vaza entre tenants) E imprime o checklist do que SÓ dá pra
// testar VIVO (MCP/prod). NUNCA finge-passar a parte viva — isso seria o "skip-as-pass" que o
// RUNBOOK-contrato-de-tela §3 condena. Os itens ⚠️ MANUAL não contam pro exit code.
//
// Uso:  node scripts/smoke-veredito-ledger.mjs   (ou: npm run smoke:veredito)
// Exit: 0 = todos os checks LOCAIS passaram · 1 = algum falhou.
//
// Origem: 2026-06-18 (Wagner: "se eu fosse testar todo o sistema, o que eu deveria?").

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..');
const GATE = join(HERE, 'contrato-de-tela.mjs');

let fail = 0, pass = 0;
const ok = (m) => { console.log(`  \x1b[32m✅\x1b[0m ${m}`); pass++; };
const bad = (m, extra = '') => { console.log(`  \x1b[31m❌\x1b[0m ${m}${extra ? '\n     ' + extra.replace(/\n/g, ' ').slice(0, 200) : ''}`); fail++; };
const manual = (m) => console.log(`  \x1b[33m⚠️  MANUAL\x1b[0m ${m}`);
const sec = (t) => console.log(`\n\x1b[1m${t}\x1b[0m`);
const node = (args) => spawnSync('node', args, { encoding: 'utf8' });
const out = (r) => (r.stdout || '') + (r.stderr || '');
const tmp = (p) => mkdtempSync(join(tmpdir(), p));
const drop = (d) => rmSync(d, { recursive: true, force: true });

// monta um contrato-fixture hermético (backend PHP + frontend TS + 1 seção ancorada)
function fixture({ feTs, bePhp }) {
  const root = tmp('smoke-');
  mkdirSync(join(root, 'be'), { recursive: true });
  mkdirSync(join(root, 'fe'), { recursive: true });
  writeFileSync(join(root, 'fe', 'Index.tsx'), `export default () => (<div data-contract="ok">Canal reconectado!</div>);`);
  writeFileSync(join(root, 'fe', 'reconnectState.ts'), feTs);
  writeFileSync(join(root, 'be', 'C.php'), bePhp);
  writeFileSync(join(root, 'c.json'), JSON.stringify({
    tela: 'Smoke', alvo: ['fe'], secoes: [{ id: 'ok', copy: ['Canal reconectado!'] }],
    acordos_estado: [{ id: 'sessao-ativa', valores: ['paired', 'connected'], backend: 'be/C.php', frontend: ['fe/reconnectState.ts'] }],
  }));
  return root;
}
const FE_OK = `export const isSessionActive = d => d.state === 'paired' || d.state === 'connected';`;
const BE_OK = `<?php\nreturn ['state' => 'paired'];\nreturn ['state' => 'connected'];\n`;

console.log('\x1b[1m🔎 SMOKE — programa veredito-ledger (Ondas 1-3)\x1b[0m');

// ── 1. Catraca — self-test inteiro morde (20 controles) ───────────────────────
sec('1. Catraca — self-test (20 controles)');
{
  const r = node([join(HERE, 'contrato-de-tela.test.mjs')]);
  r.status === 0 ? ok('self-test verde (gate morde e libera certo)') : bad('self-test FALHOU', out(r));
}

// ── 2. Catraca MORDE no backend + sem falso-positivo (o #2999) ────────────────
sec('2. Catraca — drift de vocabulário no backend vira VERMELHO');
{
  const root = fixture({ feTs: FE_OK, bePhp: `<?php\nreturn ['state' => 'linked'];\nreturn ['state' => 'connected'];\n` }); // 'paired' renomeado SÓ no backend
  const r = node([GATE, '--root', root, '--contract', 'c.json']);
  r.status === 1 && /paired/.test(out(r)) ? ok("rename de 'paired' no backend → gate vermelho (drift pego)") : bad('NÃO pegou o rename de backend', out(r));
  drop(root);
}
{
  const root = fixture({ feTs: FE_OK, bePhp: BE_OK });
  const r = node([GATE, '--root', root, '--contract', 'c.json']);
  r.status === 0 ? ok('contrato coerente → verde (sem falso-positivo)') : bad('falso-positivo num contrato OK', out(r));
  drop(root);
}

// ── 3. recusado write-path — o schema conhece o NÃO + exige os 3 campos ───────
sec('3. recusado — o schema canônico conhece o NÃO');
{
  const schemaP = join(ROOT, 'scripts/memory-schemas/adr.schema.json');
  if (!existsSync(schemaP)) bad('adr.schema.json não encontrado');
  else {
    const s = JSON.parse(readFileSync(schemaP, 'utf8'));
    const blob = JSON.stringify(s);
    (s.properties?.status?.enum || []).includes('recusado') ? ok("status enum inclui 'recusado'") : bad("status enum NÃO inclui 'recusado'");
    (/"const"\s*:\s*"recusado"/.test(blob) && /rejected_reason/.test(blob) && /rejected_at/.test(blob) && /rejected_via/.test(blob))
      ? ok('recusado exige rejected_at/via/reason (bloco condicional presente)')
      : bad('recusado sem o condicional dos 3 campos');
  }
}

// ── 4. Escopo — NÃO-VAZAMENTO Tier 0 (cliente:biz=4 não aplica a biz=7) ────────
sec('4. Escopo — veredito cliente:biz=4 NÃO vaza pra biz=7');
{
  const root = tmp('smoke-esc-');
  mkdirSync(join(root, 'fe'), { recursive: true });
  writeFileSync(join(root, 'fe', 'Index.tsx'), `export default () => (<div data-contract="ok">x</div>);`);
  writeFileSync(join(root, 'c.json'), JSON.stringify({
    tela: 'E', alvo: ['fe'], secoes: [{ id: 'ok', copy: [] }],
    acordos_estado: [
      { id: 'sessao-ativa', escopo: 'global', valores: ['x'], backend: 'fe/Index.tsx' },
      { id: 'sessao-ativa', escopo: 'cliente:biz=4', valores: ['x'], backend: 'fe/Index.tsx' },
    ],
  }));
  const r4 = node([GATE, '--root', root, '--resolve', 'c.json', '--ctx', 'cliente:biz=4']);
  /vence \[escopo:cliente:biz=4\]/.test(out(r4)) ? ok('ctx biz=4 → vence cliente:biz=4') : bad('biz=4 não venceu', out(r4));
  const r7 = node([GATE, '--root', root, '--resolve', 'c.json', '--ctx', 'cliente:biz=7']);
  (/vence \[escopo:global\]/.test(out(r7)) && !/vence \[escopo:cliente:biz=4\]/.test(out(r7)))
    ? ok('ctx biz=7 → vence global (biz=4 NÃO vazou) · Tier 0')
    : bad('VAZAMENTO: biz=4 aplicou a biz=7', out(r7));
  drop(root);
}

console.log(`\n\x1b[1m── Local: ${pass} ✅ · ${fail} ❌ ──\x1b[0m`);

// ── O que SÓ dá pra testar VIVO (MCP/prod) — não conta pro exit ───────────────
sec('⚠️  AGORA O VIVO (não auto-verificável — precisa de você):');
manual('P0.1 — o NÃO consultável: merge 1 ADR `status: recusado` REAL (ex: o v0 "Fidelity Lock"');
console.log('              que morreu — RUNBOOK-contrato-de-tela §4, conhecimento genuíno, não falso),');
console.log('              aguarde o webhook→MCP reindexar, e rode no MCP `decisions-search` pelo tema');
console.log('              → TEM que voltar. (testa #2998 FULLTEXT + #3000 híbrido Meili.)');
manual('P0.3 — vereditos no MCP: `memoria-search` por "REQ-001" / "vocabulário de estado" → deve voltar (#2995).');
manual('P1.6 — smoke prod: na Caixa/Atendimento, reconectar um canal já ativo → a msg de sucesso');
console.log('              NÃO pode pintar de vermelho (o bug 2026-06-18 original · #2984).');

console.log('');
process.exit(fail ? 1 : 0);
