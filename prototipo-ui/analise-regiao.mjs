#!/usr/bin/env node
// analise-regiao.mjs — W3 do processo região-a-região: ROTEIA cada região ao crítico certo,
// pra a análise rodar ESCOPADA à parte (não à tela inteira). A dor literal do Wagner:
// "aplicar diferenças em ÁREAS/PARTES com análises ESPECIALIZADAS; assertividade muito melhor
// focando em partes" — e o review de hoje provou empiricamente (escopo menor = mais assertivo).
//
// O que é DETERMINÍSTICO (este script, testável): o ROTEAMENTO — dado o tipo/risco da região,
// QUAIS críticos rodar (design-critique / ux-copy / accessibility-review / screen-grade) + o
// CONTEXTO escopado (o recorte do W2 + a copy do contrato). O que é ASSISTIVO (o agente): rodar
// o crítico escolhido sobre o recorte. Por isso só o roteador é máquina; a crítica é o plugin.
//
// Uso:
//   node prototipo-ui/analise-regiao.mjs --contract <c.json>   # imprime o plano de análise por região
//   node prototipo-ui/analise-regiao.mjs --selftest
//
// Exit: 0 = ok | 1 = contrato sem seções | 2 = uso

import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const norm = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

// ── ROTEAMENTO (lógica PURA, testável) ───────────────────────────────────────
// Dado uma seção {id,_parte,_risco,_acao,copy}, devolve os críticos especializados a rodar.
export function criticosParaRegiao(sec) {
  const ctx = norm([sec._parte, sec._risco, sec._acao].join(' '));
  const temCopy = Array.isArray(sec.copy) && sec.copy.some((c) => c && !/^todo/i.test(c));
  const out = new Set(['screen-grade']); // baseline 16-dim escopado SEMPRE
  // visual/layout → design-critique
  if (/visual|layout|header|cabec|densidade|cor|espac|grid|drawer|card|kpi|chip/.test(ctx) || sec._risco === undefined) out.add('design-critique');
  // copy/texto → ux-copy (se a região tem copy declarada ou o tema é texto/label/título)
  if (temCopy || /copy|texto|label|titulo|microcopy|mensagem|placeholder/.test(ctx)) out.add('ux-copy');
  // acessibilidade → accessibility-review
  if (/a11y|acess|contraste|aria|teclado|foco|leitor/.test(ctx)) out.add('accessibility-review');
  return [...out];
}

export function planoDeAnalise(contrato) {
  const secoes = (contrato && contrato.secoes) || [];
  return secoes.map((s) => ({
    regiao: s.id,
    criticos: criticosParaRegiao(s),
    contexto: { recorte: `prototipo-ui/.regioes/<tela>/${s.id}.png (W2)`, copy: s.copy || [], parte: s._parte || s.id },
    escopo: 'REGIÃO (não a tela inteira)',
  }));
}

function selftest() {
  let f = 0; const t = (l, c) => { if (!c) f++; console.log(`  [${c ? 'PASS' : 'FAIL'}] ${l}`); };
  t('região visual (Header, risco só-visual) → design-critique + screen-grade',
    JSON.stringify(criticosParaRegiao({ _parte: 'Header da página', _risco: 'só-visual' }).sort()) === JSON.stringify(['design-critique', 'screen-grade']));
  t('região com copy declarada → inclui ux-copy',
    criticosParaRegiao({ _parte: 'Banner', copy: ['Canal reconectado!'] }).includes('ux-copy'));
  t('região a11y/contraste → inclui accessibility-review',
    criticosParaRegiao({ _parte: 'Botão', _risco: 'contraste a11y' }).includes('accessibility-review'));
  t('screen-grade SEMPRE presente (baseline)',
    criticosParaRegiao({ _parte: 'x', _risco: 'backend' }).includes('screen-grade'));
  t('copy=TODO (não preenchida) NÃO dispara ux-copy sozinha',
    !criticosParaRegiao({ _parte: 'Drawer', _risco: 'backend', copy: ['TODO: copy literal'] }).includes('ux-copy'));
  const plano = planoDeAnalise({ secoes: [{ id: 'header', _parte: 'Header', _risco: 'só-visual', copy: ['Caixa'] }] });
  t('plano: escopo=REGIÃO + aponta o recorte do W2', plano[0].escopo.startsWith('REGIÃO') && /\.regioes/.test(plano[0].contexto.recorte));
  console.log(f ? `\nSELFTEST FALHOU (${f})` : '\nSELFTEST OK — roteia região→crítico especializado, escopado ao recorte (não à tela).');
  process.exit(f ? 1 : 0);
}

const argv = process.argv.slice(2);
const val = (k) => { const i = argv.indexOf(k); return i >= 0 && argv[i + 1] ? argv[i + 1] : null; };
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  if (argv.includes('--selftest')) selftest();
  else {
    const cf = val('--contract');
    if (!cf || !existsSync(cf)) { console.error('uso: analise-regiao.mjs --contract <c.json> | --selftest'); process.exit(2); }
    const contrato = JSON.parse(readFileSync(cf, 'utf8'));
    const plano = planoDeAnalise(contrato);
    if (!plano.length) { console.error('✗ contrato sem seções'); process.exit(1); }
    console.error(`# análise ESCOPADA por região (${plano.length}) — rode cada crítico sobre o recorte do W2, não a tela:`);
    console.log(JSON.stringify(plano, null, 2));
    process.exit(0);
  }
}
