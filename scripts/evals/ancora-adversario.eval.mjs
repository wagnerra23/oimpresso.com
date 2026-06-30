#!/usr/bin/env node
// ancora-adversario.eval.mjs — o ADVERSÁRIO PERMANENTE da máquina de âncora/import.
//
// Não só acha buraco: pra cada vetor carrega a SOLUÇÃO (Wagner 2026-06-30 "coloque um
// adversário que busque soluções"). Os vetores que JÁ têm defesa viram GATE (não podem
// regredir); os gaps aceitos ficam DOCUMENTADOS com o fix proposto (visíveis, não escondidos).
//
// Mesmo papel que design-source-of-truth.eval.mjs (baseline armado · vetores de ataque) faz
// pro enforcement do Figma. Roda no CI (design-memory-gates.yml).
//
// Rodar: node scripts/evals/ancora-adversario.eval.mjs   (exit 0 = defesas de pé + gaps como esperado)

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { razaoBloqueio, ehAncoraIlegitima } from '../../.claude/hooks/block-ancora-no-olho.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..', '..');
const HOOK_SRC = readFileSync(join(ROOT, '.claude', 'hooks', 'block-ancora-no-olho.mjs'), 'utf8');
const DOWN = 'C:/Users/x/Downloads/_cowork-handoff-staging/oimpresso/project'; // fora do repo

let fails = 0;
const reg = (nome, defendido, solucao) => {
  console.log(`${defendido ? '[DEFENDIDO]' : '[FALHOU]   '} ${nome}`);
  if (!defendido) { fails++; console.log(`            ↳ SOLUÇÃO: ${solucao}`); }
};
const gap = (nome, comoEstaHoje, esperadoHoje, solucaoFutura) => {
  const ok = comoEstaHoje === esperadoHoje;
  console.log(`${ok ? '[GAP-OK]   ' : '[GAP-MUDOU]'} ${nome}  (hoje: ${comoEstaHoje})`);
  console.log(`            ↳ fix proposto: ${solucaoFutura}`);
  if (!ok) fails++; // se o comportamento do gap mudar, força revisitar (bom: fix ou regressão)
};

console.log('— ADVERSÁRIO DA MÁQUINA DE ÂNCORA —\n');

// ATAQUE 1 (era CRÍTICO, corrigido): hook não pode falhar-aberto se ancora.mjs quebrar.
// Estrutural: o hook NÃO importa ancora.mjs → erro em ancora.mjs não derruba o guarda.
reg('ATAQUE 1 — hook é auto-contido (não importa ancora.mjs → não falha-aberto)',
    !/from\s+['"][^'"]*prototipo-ui\/ancora\.mjs['"]/.test(HOOK_SRC),
    'inline da lista-negra no hook; zero dependência de import (guarda não pode depender de outro arquivo).');
reg('ATAQUE 1b — hook ainda BLOQUEIA o audit png (exit-2 funcional)',
    razaoBloqueio('Read', { file_path: `${DOWN}/audit-financeiro.png` }) !== null,
    'razaoBloqueio deve retornar razão pra audit-*.png fora do repo.');

// Defesas positivas que não podem regredir:
reg('LIBERA código .tsx', razaoBloqueio('Read', { file_path: 'resources/js/Pages/Financeiro/Unificado/Index.tsx' }) === null,
    'só barra png de auditoria/crítica fora do repo, nunca código.');
reg('LIBERA png de design legítimo (ph-financeiro2.png)', razaoBloqueio('Read', { file_path: `${DOWN}/screenshots/ph-financeiro2.png` }) === null,
    'ph-/kpi-/screenshots não casam a lista-negra.');
reg('lista-negra do hook == a do ancora (sem drift)', ehAncoraIlegitima('audit-x.png') === true && ehAncoraIlegitima('financeiro-page.jsx') === false,
    'manter as duas cópias idênticas; este eval é o guarda de drift.');

// ATAQUE 2 — ERA GAP (rename-bypass), AGORA DEFENDIDO pela allowlist de proveniência (2026-06-30):
reg('ATAQUE 2 — print renomeado sem "audit" (financeiro-final-v2.png) agora é BLOQUEADO',
    razaoBloqueio('Read', { file_path: `${DOWN}/financeiro-final-v2.png` }) !== null,
    'allowlist de proveniência: imagem externa só passa se for fonte-DS conhecida — nome arbitrário não escapa mais.');
reg('allowlist deixa passar a fonte-DS legítima (não vira fail-closed cego)',
    razaoBloqueio('Read', { file_path: `${DOWN}/_ds/tokens.png` }) === null
    && razaoBloqueio('Read', { file_path: `${DOWN}/kpi-dark.png` }) === null,
    '_ds/ e ph-/kpi- são allowlist; só o que NÃO é fonte conhecida bloqueia.');

// GAPS conhecidos — documentados com fix, comportamento congelado (muda = revisitar):
gap('ATAQUE 3 — leitura por outra tool (Bash cat/Chrome) não passa pelo hook (matcher Read)',
    /Read/.test('Read') ? 'só-Read' : 'amplo',
    'só-Read',
    'cobrir Bash/screenshot tem retorno decrescente; o vetor primário (Read, o erro #7) está coberto.');

console.log('');
if (fails === 0) { console.log('[PASS] adversário: defesas de pé, gaps como esperado (com fix proposto).'); process.exit(0); }
console.log(`[FAIL] ${fails} — defesa regrediu OU gap mudou de comportamento (revisitar).`);
process.exit(1);
