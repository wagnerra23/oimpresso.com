#!/usr/bin/env node
// ledger-check-external.test.mjs — contrato do gerador NÃO-Anthropic (emenda 2026-07-30).
//
// POR QUE ESTE TESTE EXISTE: o `ledger-check --enforce` roda dentro do job umbrella
// REQUIRED. Afrouxar a regra de tier é mexer em gate que morde, e os modos de falha são
// simétricos:
//   (a) FROUXO DEMAIS — qualquer string vira "gerador externo" e o campo de modelo perde
//       sentido; ou um refutador fraco (haiku/sonnet) passa a validar lote externo, que é
//       exatamente a correlação de erros que a regra de 2026-07-01 existe pra impedir.
//   (b) APERTADO DEMAIS — a emenda não pega, e o lote do Codex segue sem caminho honesto
//       de abertura (o estado que ela veio consertar, PR #5069).
// Cada `deve` abaixo é um desses vetores, sempre com o par bite/release — release sozinho
// não prova nada, provaria só que o teste é frouxo.
//
// Node puro (fs + spawnSync + tmp). Sem deps, sem DB, sem rede. Segundos.
//   node scripts/governance/ledger-check-external.test.mjs

import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const SCRIPT = join(process.cwd(), 'scripts', 'governance', 'ledger-check.mjs');
let pass = 0;
const fails = [];

function deve(nome, fn) {
  try { fn(); pass++; console.log(`  ✓ ${nome}`); }
  catch (e) { fails.push(`${nome}: ${e.message}`); console.log(`  ✗ ${nome}\n      ${e.message}`); }
}
const inclui = (hay, needle, m) => {
  if (!String(hay).includes(needle)) throw new Error(`${m ?? ''} esperava conter "${needle}" em:\n${hay}`);
};
const naoInclui = (hay, needle, m) => {
  if (String(hay).includes(needle)) throw new Error(`${m ?? ''} NÃO esperava "${needle}" em:\n${hay}`);
};

const tmp = mkdtempSync(join(tmpdir(), 'ledger-external-'));

// 11 arquivos > threshold 10 → é PR-de-lote (mesma forma da fixture do gate-selftest).
const FILES = join(tmp, 'files.txt');
writeFileSync(FILES, Array.from({ length: 11 }, (_, i) => `memory/requisitos/DemoMod/doc-${i}.md`).join('\n'));

const base = {
  pr: 9998, lote_id: 'lote-externo', data: '2026-07-30', tipo: 'anchors',
  sessao_fresca: true, amostra_pct: 100, pii_scan: true, pii_hits: 0,
  itens_verificados: 50, erros_confirmados: 0, error_rate_pct: 0,
  veredito: 'aprovado', evidencia: 'memory/sessions/exemplo.md',
};

let seq = 0;
function roda(entry) {
  const p = join(tmp, `ledger-${++seq}.json`);
  // O ledger é um OBJETO com `.entries` — array cru faz o check cair em "nenhuma entry",
  // e aí toda asserção de ausência-de-string passa por não-execução (LC-13). A guarda
  // contra isso é o `exigeQueTenhaLido` abaixo, obrigatório em todo caso.
  writeFileSync(p, JSON.stringify({ entries: [{ ...base, ...entry }] }, null, 2));
  const r = spawnSync(process.execPath, [SCRIPT, '--pr', '9998', '--files-from', FILES, '--ledger', p, '--enforce'], { encoding: 'utf8' });
  const out = `${r.stdout}${r.stderr}`;
  exigeQueTenhaLido(out);
  return out;
}

// PRÉ-CONDIÇÃO ANTI-VÁCUO: se o check não achou a entry, ele não avaliou regra nenhuma —
// e um teste que só verifica ausência de string passaria medindo nada.
function exigeQueTenhaLido(out) {
  if (String(out).includes('nenhuma entry no ledger')) {
    throw new Error('VÁCUO: o check não leu a entry — nada foi avaliado, asserção sem valor');
  }
}

console.log('\nledger-check — gerador NÃO-Anthropic (emenda GT-G5 r2 do PR #5069)\n');

// RELEASE — o caso que a emenda veio destravar.
deve('RELEASE: gerador codex + refutador opus passa (vendor cruzado decorrelaciona)', () => {
  const out = roda({ gerador: 'codex (GPT-5.x)', refutador: 'opus-5 (Claude Code)' });
  naoInclui(out, 'sem modelo reconhecivel', 'a emenda não pegou —');
  naoInclui(out, 'exige refutador Anthropic', 'reprovou refutador válido —');
});

// BITE — refutador fraco não vale, mesmo com gerador externo.
deve('BITE: gerador codex + refutador haiku REPROVA (correlação/força insuficiente)', () => {
  inclui(roda({ gerador: 'codex (GPT-5.x)', refutador: 'haiku-4.5' }), 'exige refutador Anthropic >= opus');
});
deve('BITE: gerador codex + refutador sonnet REPROVA', () => {
  inclui(roda({ gerador: 'codex (GPT-5.x)', refutador: 'sonnet-5' }), 'exige refutador Anthropic >= opus');
});

// BITE — o campo não virou terra de ninguém: string qualquer segue reprovando.
deve('BITE: gerador "estagiario do Wagner" segue REPROVANDO (não é externo reconhecido)', () => {
  inclui(roda({ gerador: 'estagiario do Wagner', refutador: 'opus-5' }), 'sem modelo reconhecivel');
});

// BITE — afrouxar os dois lados de uma vez esvaziaria o campo.
deve('BITE: refutador externo REPROVA mesmo com gerador externo (não sabemos ranquear)', () => {
  inclui(roda({ gerador: 'codex (GPT-5.x)', refutador: 'gemini-3-pro' }), 'exige refutador Anthropic >= opus');
});

// CONTROLE NEGATIVO — a regra Anthropic×Anthropic não pode ter sido tocada.
deve('REGRESSÃO: opus gera + sonnet refuta segue REPROVANDO (tier inferior)', () => {
  inclui(roda({ gerador: 'opus-5', refutador: 'sonnet-5' }), 'exigido tier SUPERIOR');
});
deve('REGRESSÃO: sonnet gera + sonnet refuta segue REPROVANDO (mesmo tier, não é o máximo)', () => {
  inclui(roda({ gerador: 'sonnet-5', refutador: 'sonnet-5' }), 'do MESMO tier');
});
deve('REGRESSÃO: fable gera + fable refuta segue PASSANDO (igualdade no tier máximo)', () => {
  const out = roda({ gerador: 'fable-5', refutador: 'fable-5' });
  naoInclui(out, 'tier SUPERIOR');
  naoInclui(out, 'do MESMO tier');
});

// ── TETO DE POLÍTICA (emenda 2026-08-12): "máximo" = o mais alto que o projeto USA ──
// Fable está vetado por custo, então opus é o topo na prática. Sem isto, lote gerado
// por opus era irrefutável — gate insatisfazível, o defeito que a §4.1 já consertou
// pro gerador externo.
deve('BITE: opus gera + opus refuta PASSA (opus é o teto de política)', () => {
  const out = roda({ gerador: 'claude-opus-5', refutador: 'claude-opus-5' });
  naoInclui(out, 'tier SUPERIOR');
  naoInclui(out, 'do MESMO tier');
});
// CONTROLES NEGATIVOS — o teto não pode ter virado "vale tudo".
deve('BITE: sonnet gera + sonnet refuta REPROVA (abaixo do teto, igualdade não vale)', () => {
  inclui(roda({ gerador: 'sonnet-5', refutador: 'sonnet-5' }), 'do MESMO tier');
});
deve('BITE: opus gera + sonnet refuta REPROVA (refutador abaixo do gerador)', () => {
  inclui(roda({ gerador: 'claude-opus-5', refutador: 'sonnet-5' }), 'exigido tier SUPERIOR');
});
deve('BITE: haiku gera + haiku refuta REPROVA (igualdade longe do teto)', () => {
  inclui(roda({ gerador: 'haiku-4.5', refutador: 'haiku-4.5' }), 'do MESMO tier');
});
deve('REGRESSÃO: sessao_fresca=false segue barrando — o teto NÃO afrouxa a outra metade da decorrelação', () => {
  inclui(roda({ gerador: 'claude-opus-5', refutador: 'claude-opus-5', sessao_fresca: false }), 'sessao_fresca');
});
deve('REGRESSÃO: veredito reprovado segue barrando com opus×opus', () => {
  inclui(roda({ gerador: 'claude-opus-5', refutador: 'claude-opus-5', veredito: 'reprovado' }), 'exigido: aprovado');
});
deve('REGRESSÃO: error_rate >= 2 segue barrando com opus×opus', () => {
  inclui(roda({ gerador: 'claude-opus-5', refutador: 'claude-opus-5', error_rate_pct: 5.08 }), 'aceite: < 2');
});
deve('REGRESSÃO: veredito reprovado segue barrando, mesmo com gerador externo', () => {
  inclui(roda({ gerador: 'codex (GPT-5.x)', refutador: 'opus-5', veredito: 'reprovado' }), 'exigido: aprovado');
});
deve('REGRESSÃO: error_rate >= 2 segue barrando, mesmo com gerador externo', () => {
  inclui(roda({ gerador: 'codex (GPT-5.x)', refutador: 'opus-5', error_rate_pct: 5.08 }), 'aceite: < 2');
});

console.log(`\n${fails.length ? '✗' : '✓'} ${pass} passou · ${fails.length} falhou`);
if (fails.length) { fails.forEach((f) => console.log(`  - ${f}`)); process.exit(1); }
console.log('  OK — a emenda destrava vendor cruzado e NÃO afrouxa o resto.\n');
