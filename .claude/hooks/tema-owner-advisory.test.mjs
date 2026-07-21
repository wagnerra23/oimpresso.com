#!/usr/bin/env node
// Teste do hook tema-owner-advisory.mjs — deriva do CONTRATO (advisory que aponta dono-de-tema ao
// criar doc de estrutura novo sob memory/requisitos), com CONTROLE-NEGATIVO de acoplamento:
// dispara no path certo, NÃO dispara fora dele nem em tema novo (trava §5 "chokepoint fantasma").

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { toFwd, isEstruturaDoc, buildOutput } from './tema-owner-advisory.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'tema-owner-advisory.mjs');
const REPO_ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const BS = String.fromCharCode(92);
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── isEstruturaDoc (puro) — o ROTEAMENTO (mede path, é o único lugar onde path importa) ──
check('roteia: tópico sob memory/requisitos → true', isEstruturaDoc('memory/requisitos/Produto/topicos/x.md'));
check('roteia: BRIEFING sob memory/requisitos → true', isEstruturaDoc('D:/repo/memory/requisitos/Jana/BRIEFING.md'));
check('roteia: backslash Windows → true', isEstruturaDoc('memory' + BS + 'requisitos' + BS + 'X' + BS + 'SPEC.md'));
check('roteia: memory/sessions (não requisitos) → false', !isEstruturaDoc('memory/sessions/2026-07-21-x.md'));
check('roteia: código app/*.php → false', !isEstruturaDoc('app/Utils/ProductUtil.php'));
check('roteia: charter de tela (Pages) → false', !isEstruturaDoc('resources/js/Pages/Sells/Index.charter.md'));
check('roteia: não-md sob requisitos → false', !isEstruturaDoc('memory/requisitos/X/foo.json'));

// ── buildOutput (puro) ──
check('buildOutput: advisory presente → allow + cita arquivo', (() => { const o = buildOutput('memory/requisitos/X/topicos/y.md', 'ALGO'); return o.hookSpecificOutput.permissionDecision === 'allow' && /y\.md/.test(o.hookSpecificOutput.permissionDecisionReason) && /ALGO/.test(o.hookSpecificOutput.permissionDecisionReason); })());
check('buildOutput: advisory vazio → null (silêncio)', buildOutput('memory/requisitos/X/topicos/y.md', '') === null);

// ── E2E via subprocess (o hook chama o núcleo real com o corpus real) ──
function run(input) { return spawnSync(process.execPath, [HOOK], { input: JSON.stringify(input), encoding: 'utf8' }); }
const wNovo = (rel, content) => ({ tool_name: 'Write', tool_input: { file_path: join(REPO_ROOT, rel), content } });

// Conteúdo que declara entidades JÁ cobertas pelo corpus real (tax_rates + calculateInvoiceTotal
// → tópico Produto/calculo-total-fatura). Path NOVO (não existe) sob memory/requisitos.
const CONTENT_DUP = `---
id: teste-imposto-paralelo
module: Fiscal
title: "Regra de imposto (tema paralelo de teste)"
kind: regra-negocio
status: rascunho
updated_at: "2026-07-21"
anchors:
  tables:
    - tax_rates
  functions:
    - app/Utils/ProductUtil.php::calculateInvoiceTotal
---
corpo de teste
`;
const CONTENT_NOVO = `---
id: teste-tema-novo
module: ExportacaoNova
title: "Assunto genuinamente novo de teste"
kind: capacidade
status: rascunho
updated_at: "2026-07-21"
anchors:
  tables:
    - zzz_tabela_fantasma_teste_9999
---
`;

// BITE positiva: doc de estrutura novo que SOBREPÕE tema existente → emite advisory
const dup = run(wNovo('memory/requisitos/FakeMod/topicos/teste-imposto-paralelo.md', CONTENT_DUP));
check('E2E BITE: doc novo com tema JÁ coberto → emite advisory SOBREPÕE', dup.status === 0 && (() => { try { return /SOBREP/i.test(JSON.parse(dup.stdout).hookSpecificOutput.permissionDecisionReason); } catch { return false; } })());

// CONTROLE-NEGATIVO semântico: tema genuinamente novo → silêncio (não vira ruído)
const novo = run(wNovo('memory/requisitos/FakeMod/topicos/teste-tema-novo.md', CONTENT_NOVO));
check('E2E: tema NOVO (entidade fantasma) → exit 0 silencioso', novo.status === 0 && !novo.stdout.trim());

// CONTROLE-NEGATIVO de path: mesmo content-dup, mas arquivo de CÓDIGO → NÃO dispara
const codigo = run({ tool_name: 'Write', tool_input: { file_path: join(REPO_ROOT, 'app/Foo.php'), content: CONTENT_DUP } });
check('E2E: Write de código (fora de memory/requisitos) → exit 0 silencioso', codigo.status === 0 && !codigo.stdout.trim());

// CONTROLE-NEGATIVO de path: memory/sessions não é estrutura de módulo → NÃO dispara
const sessao = run({ tool_name: 'Write', tool_input: { file_path: join(REPO_ROOT, 'memory/sessions/2026-07-21-x.md'), content: CONTENT_DUP } });
check('E2E: Write em memory/sessions → exit 0 silencioso', sessao.status === 0 && !sessao.stdout.trim());

// Edit (não Write) → silêncio
check('E2E: Edit (não Write) → exit 0 silencioso', (() => { const r = spawnSync(process.execPath, [HOOK], { input: JSON.stringify({ tool_name: 'Edit', tool_input: { file_path: join(REPO_ROOT, 'memory/requisitos/X/topicos/y.md'), content: CONTENT_DUP } }), encoding: 'utf8' }); return r.status === 0 && !r.stdout.trim(); })());

// fail-open
check('E2E: stdin vazio → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '', encoding: 'utf8' }).status === 0);
check('E2E: JSON inválido → exit 0 (fail-open)', spawnSync(process.execPath, [HOOK], { input: '{lixo', encoding: 'utf8' }).status === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — hook dispara em doc-de-estrutura novo que duplica tema, silencia fora do path e em tema novo; advisory; fail-open provado.');
process.exit(fails ? 1 : 0);
