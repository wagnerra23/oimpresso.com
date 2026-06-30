#!/usr/bin/env node
// ancora-adversario.eval.mjs — o ADVERSÁRIO PERMANENTE do guarda de âncora.
//
// Wagner: "coloque um adversário que busque soluções" + "vai dar errado, tem certeza?".
// O review adversarial (workflow 2026-06-30, 33 modos de falha) PROVOU que a allowlist-de-PASTA
// backfirava E que o eval ANTERIOR MENTIA — testava fixtures sintéticos (ph-financeiro2.png,
// _ds/tokens.png) que NÃO existem no corpus real, ficando verde enquanto barraria todo design.
//
// Este eval testa o critério ATUAL (print-semântico não-declarado bloqueia em qualquer lugar;
// design legítimo passa; charter é lido) contra os 4 modos de falha confirmados — e deixa o
// residual honesto DOCUMENTADO (não finge defender o que não defende).
//
// Roda no CI (design-memory-gate.yml · fundido ADR 0314 F2). exit 0 = defesas de pé + gaps como esperado.

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { razaoBloqueio, ehPrintSemantico, anchorsDeclaradas } from '../../.claude/hooks/block-ancora-no-olho.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..', '..');
const HOOK_SRC = readFileSync(join(ROOT, '.claude', 'hooks', 'block-ancora-no-olho.mjs'), 'utf8');
const DOWN = 'C:/Users/x/Downloads/_cowork-handoff-staging/oimpresso/project'; // fora do repo
const bloqueia = (fp) => razaoBloqueio('Read', { file_path: fp }) !== null;

let fails = 0;
const reg = (nome, defendido, solucao) => {
  console.log(`${defendido ? '[DEFENDIDO]' : '[FALHOU]   '} ${nome}`);
  if (!defendido) { fails++; console.log(`            ↳ ${solucao}`); }
};
const gap = (nome, atual, esperado, nota) => {
  const ok = atual === esperado;
  console.log(`${ok ? '[GAP-OK]   ' : '[GAP-MUDOU]'} ${nome}  (hoje: ${atual})`);
  console.log(`            ↳ ${nota}`);
  if (!ok) fails++;
};

console.log('— ADVERSÁRIO DO GUARDA DE ÂNCORA (critério v3: charter-aware) —\n');

// ATAQUE 1 — hook não pode falhar-aberto se ancora.mjs quebrar → não importa ancora.mjs.
reg('ATAQUE 1 — hook auto-contido (não importa ancora.mjs)',
    !/from\s+['"][^'"]*prototipo-ui\/ancora\.mjs['"]/.test(HOOK_SRC),
    'inline a leitura de charter; zero import → erro em ancora.mjs não derruba o guarda.');

// #7 REAL (o artefato que de fato apareceu): audit-financeiro.png — bloqueia FORA e DENTRO do repo.
reg('#7 — audit-financeiro.png externo → BLOQUEIA', bloqueia(`${DOWN}/audit-financeiro.png`),
    'print-semântico não-declarado deve bloquear.');
reg('MODO C — audit-financeiro.png DENTRO do repo → BLOQUEIA (antes passava livre)',
    bloqueia('resources/js/Pages/Financeiro/audit-financeiro.png'),
    'in-repo não pode ser pass-all; print-semântico bloqueia em qualquer lugar.');
reg('MODO D — screenshots/audit-financeiro-old.png → BLOQUEIA (antes escapava por pasta)',
    bloqueia(`${DOWN}/screenshots/audit-financeiro-old.png`),
    'allowlist por substring-de-pasta deixava passar; agora é por nome-semântico, pasta não salva.');

// MODO A (o backfire que o Wagner farejou): design legítimo NÃO pode ser bloqueado.
reg('MODO A — Financeiro.png (export de handoff legítimo) externo → PASSA (zero backfire)',
    !bloqueia(`${DOWN}/Financeiro.png`),
    'imagem de design legítima não-print deve passar — era o falso-positivo em massa.');
reg('MODO A — venda-create.png (mockup legítimo) → PASSA', !bloqueia(`${DOWN}/mockups/venda-create.png`),
    'não-print passa.');

// MODO B (a defesa MENTIA): o hook agora LÊ charter de verdade.
reg('MODO B — o hook LÊ os charters (proveniência real, não regex de pasta)',
    anchorsDeclaradas().size > 0 && /readFileSync|readdirSync/.test(HOOK_SRC),
    'anchorsDeclaradas() varre resources/js/Pages/**/*.charter.md — a promessa virou código.');

// design real é .html/.jsx (não imagem) → passa naturalmente (não-imagem).
reg('design real (.jsx/.html) e código (.tsx) → PASSAM (não-imagem)',
    !bloqueia(`${DOWN}/compras-page.jsx`) && !bloqueia(`${DOWN}/Financeiro - Prova Viva.html`)
    && !bloqueia('resources/js/Pages/Financeiro/Unificado/Index.tsx'),
    'o hook só olha imagem; âncora real .jsx/.html nunca foi o alvo.');

// classificador semântico coerente
reg('ehPrintSemantico: audit/tribunal/-old = print; financeiro-page.jsx/Financeiro.png = não',
    ehPrintSemantico('audit-x.png') && ehPrintSemantico('Tribunal-Compras-old.png')
    && !ehPrintSemantico('financeiro-page.jsx') && !ehPrintSemantico('Financeiro.png'),
    'manter o classificador alinhado.');

// GAP honesto — residual do workflow §4 (NÃO finge defender):
gap('RESIDUAL — print renomeado pra nome inocente (financeiro-final-v2.png) PASSA',
    bloqueia(`${DOWN}/financeiro-final-v2.png`) ? 'bloqueia' : 'passa', 'passa',
    'teto teórico: a confiança termina no charter. Sem related_prototype declarando, nome inocente não é distinguível de design. Fix real = declarar a âncora no charter; o hook honra o que o contrato diz, não advinha intenção.');
gap('RESIDUAL — leitura por Chrome/Bash/paste não passa pelo hook (matcher Read)',
    'só-Read', 'só-Read',
    'o #7 foi via Read; outros canais precisam de defesa própria (retorno decrescente cobrir tudo).');

console.log('');
if (fails === 0) { console.log('[PASS] guarda v3: modos A/B/C/D fechados, residual honesto documentado.'); process.exit(0); }
console.log(`[FAIL] ${fails} — defesa regrediu OU gap mudou (revisitar).`);
process.exit(1);
