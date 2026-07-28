#!/usr/bin/env node
// Teste do hook-replay. O selftest PROVA O CONTRAFACTUAL: o harness precisa REPROVAR a
// implementação histórica quebrada e APROVAR a corrigida, usando a MESMA sessão.
// Se ele concordar com as duas, não mede nada — é o teste tautológico do §5 2026-06-05.
// Rodar: node scripts/governance/hook-replay.test.mjs

import { parseSessao, CONTRATOS, replay, formatar } from './hook-replay.mjs';
import { hasReadEvidence as IMPL_ATUAL } from '../../.claude/hooks/modulo-preflight-warning.mjs';

let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── implementação HISTÓRICA (a quebrada, 2026-07-26) — regex sem âncora ──────
function IMPL_ANTIGA(content, mod) {
  const l = mod.toLowerCase();
  return !!content && [`memory/requisitos/${mod}/`, `Modules/${mod}/README`,
    `${l}.*charter`, `${l}.*spec`, `decisions-search.*${l}`, `como-integrar.*${l}`]
    .some((p) => new RegExp(p, 'i').test(content));
}

const ev = (name, input) => JSON.stringify({ type: 'assistant', message: { content: [{ type: 'tool_use', name, input }] } });

// ── FIXTURE REALISTA: o que fazia o hook quebrar no mundo ────────────────────
// Uma linha carregando o registry de agents do system prompt: cita "Compras" e, MILHARES
// de chars adiante, "charter". A fixture limpa das suites nunca tem isso — e foi por isso
// que 26 asserts ficaram verdes sobre um hook morto.
const BLOB = JSON.stringify({ type: 'user', message: { content: [{ type: 'text', text:
  '- financeiro-bridge-auditor: auditor da bridge Sells/Compras (UltimatePOS core) '
  + 'x'.repeat(4000) + ' skills: charter-first, charter-write, memory-schema-preflight' }] } });

const SEM_LEITURA = [ev('Edit', { file_path: 'D:/p/Modules/Compras/Http/X.php' }), BLOB].join('\n');
const COM_LEITURA = [ev('Edit', { file_path: 'D:/p/Modules/Compras/Http/X.php' }),
                     ev('Read', { file_path: 'D:/p/memory/requisitos/Compras/SPEC.md' }), BLOB].join('\n');

const C = CONTRATOS['modulo-preflight-warning'];

// ── parse estrutural ─────────────────────────────────────────────────────────
const p = parseSessao(SEM_LEITURA);
check('parseSessao captura o edit do modulo', p.edits.length === 1 && /Modules\/Compras\//.test(p.edits[0]));
// Edit conta como leitura IMPLICITA do arquivo editado (o tool exige Read previo), mas
// editar o CODIGO do modulo nao e' ler o BRIEFING dele — e isso que decide o caso.
check('editar codigo do modulo NAO conta como ler o briefing',
  !p.leituras.some((l) => /memory\/requisitos\/Compras\//i.test(l)));
check('Edit no BRIEFING conta como leitura (tool exige Read previo)',
  parseSessao(ev('Edit', { file_path: 'D:/p/memory/requisitos/Compras/SPEC.md' }))
    .leituras.some((l) => /memory\/requisitos\/Compras\//i.test(l)));
check('Write NAO conta como leitura (criar != ler)',
  !parseSessao(ev('Write', { file_path: 'D:/p/memory/requisitos/Compras/NOVO.md' }))
    .leituras.some((l) => /memory\/requisitos\/Compras\//i.test(l)));
check('parseSessao ignora TEXTO (so tool_use conta)', !parseSessao(BLOB).edits.length);

// ── oráculo independente ─────────────────────────────────────────────────────
check('caso: sem leitura -> esperado AVISA', C.esperado(C.casos(parseSessao(SEM_LEITURA))[0]) === 'avisa');
check('caso: com leitura -> esperado CALA', C.esperado(C.casos(parseSessao(COM_LEITURA))[0]) === 'cala');
check('oraculo NAO chama o hook (independencia)', !String(C.esperado).includes('impl'));

// ── O CONTRAFACTUAL: o harness morde a implementacao historica? ──────────────
const sessoes = [{ nome: 'fix-1', texto: SEM_LEITURA }, { nome: 'fix-2', texto: COM_LEITURA }];
const rAntiga = replay({ contrato: C, sessoes, impl: IMPL_ANTIGA });
const rAtual = replay({ contrato: C, sessoes, impl: IMPL_ATUAL });

check('BITE: impl HISTORICA diverge na sessao sem leitura',
  rAntiga.divergencias.length === 1 && rAntiga.divergencias[0].esperado === 'avisa' && rAntiga.divergencias[0].observado === 'cala');
check('BITE: e a divergencia e o caso REAL (Compras silenciado pelo blob)',
  rAntiga.divergencias[0].modulo === 'Compras');
check('GOOD: impl ATUAL concorda com o contrato nos 2 casos',
  rAtual.divergencias.length === 0 && rAtual.acordo === 2);
check('DISCRIMINA: as duas impls dao resultados DIFERENTES (senao nao mede nada)',
  rAntiga.taxa !== rAtual.taxa);

// ── fixture LIMPA nao discrimina — a prova de que realismo e' o ponto ────────
const LIMPA = [{ nome: 'limpa', texto: ev('Edit', { file_path: 'D:/p/Modules/Compras/X.php' }) }];
check('LICAO: com fixture limpa as 2 impls CONCORDAM (por isso o selftest verde mentia)',
  replay({ contrato: C, sessoes: LIMPA, impl: IMPL_ANTIGA }).taxa
  === replay({ contrato: C, sessoes: LIMPA, impl: IMPL_ATUAL }).taxa);

// ── corpus vazio / sem gatilho ───────────────────────────────────────────────
const vazio = replay({ contrato: C, sessoes: [], impl: IMPL_ATUAL });
check('corpus vazio -> sem veredito, nao passa fingindo', vazio.total === 0 && vazio.taxa === null);
check('formatar diz "sem gatilho, sem veredito"', /sem gatilho, sem veredito/.test(formatar('x', C, vazio)));

// ── relatorio nao vende divergencia como bug ─────────────────────────────────
const rel = formatar('modulo-preflight-warning', C, rAntiga);
check('relatorio mostra a divergencia', /Compras/.test(rel));
check('relatorio avisa que divergencia != bug do hook', /NAO E AUTOMATICAMENTE BUG/.test(rel));
check('relatorio declara o oraculo usado', /oraculo:/.test(rel));

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — o harness REPROVA a impl historica e APROVA a atual na MESMA sessao real; com fixture limpa nao discrimina (a licao).');
process.exit(fails ? 1 : 0);
