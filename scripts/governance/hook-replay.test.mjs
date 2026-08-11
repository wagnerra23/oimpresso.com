#!/usr/bin/env node
// Teste do hook-replay. O selftest PROVA O CONTRAFACTUAL: o harness precisa REPROVAR a
// implementação histórica quebrada e APROVAR a corrigida, usando a MESMA sessão.
// Se ele concordar com as duas, não mede nada — é o teste tautológico do §5 2026-06-05.
// Rodar: node scripts/governance/hook-replay.test.mjs

import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { parseSessao, CONTRATOS, replay, formatar, portaVivaRunbook } from './hook-replay.mjs';
import { hasReadEvidence as IMPL_ATUAL } from '../../.claude/hooks/modulo-preflight-warning.mjs';
import { decide as MWART_ATUAL, parsePagePath, toKebab, runbookStatus } from '../../.claude/hooks/block-mwart-violation.mjs';

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

// ═════════════════════════════════════════════════════════════════════════════
// CONTRATO 2 — block-mwart-violation
// Oráculo = a PORTA VIVA (screen-coverage-map). Aqui ela é INJETADA: o teste tem que
// ser determinístico, e depender do estado do repo faria o resultado mudar de máquina
// pra máquina — que é o oposto de um controle-negativo.
// ═════════════════════════════════════════════════════════════════════════════
const M = CONTRATOS['block-mwart-violation'];

// sandbox: a raiz viaja NO impl (replay não passa raiz), então cada impl é um closure.
const SB = mkdtempSync(join(tmpdir(), 'mwart-replay-'));
mkdirSync(join(SB, 'memory', 'requisitos', 'Governance'), { recursive: true });
mkdirSync(join(SB, 'memory', 'requisitos', 'Sells'), { recursive: true });
// tela ANINHADA: o RUNBOOK real se chama pela ROTA (subdir), não pelo filename 'Index'
writeFileSync(join(SB, 'memory', 'requisitos', 'Governance', 'RUNBOOK-module-grades.md'), '# rb\n');
// tela FLAT: RUNBOOK pelo nome da tela — os dois impls acham
writeFileSync(join(SB, 'memory', 'requisitos', 'Sells', 'RUNBOOK-drafts.md'), '# rb\n');

/** impl HISTÓRICA (pré-#4648): só kebab da TELA — sem subdir, sem resgate por charter.
 *  Reconstruída dos helpers exportados do hook; o que mudou no #4648 foi a COMPOSIÇÃO. */
const MWART_ANTIGO = (_tool, filePath, root) => {
  const p = parsePagePath(filePath);
  if (!p) return null;
  return runbookStatus(p.modulo, [toKebab(p.tela)], root) === 'ok' ? null : 'BLOQUEADO';
};

const edit = (fp) => ev('Edit', { file_path: fp });
const S_ANINHADA = [{ nome: 'aninhada', texto: edit('D:/p/resources/js/Pages/governance/ModuleGrades/Index.tsx') }];
const S_FLAT = [{ nome: 'flat', texto: edit('D:/p/resources/js/Pages/Sells/Drafts.tsx') }];

// oráculo: a porta viva enxerga o RUNBOOK das duas (walk recursivo + substring)
const CTX = { runbook: {
  'governance/ModuleGrades/Index.tsx': { source: 'name', status: 'unique', candidates: ['x'] },
  'Sells/Drafts.tsx': { source: 'name', status: 'unique', candidates: ['y'] },
} };

check('casos extrai a tela aninhada como <Mod>/<Sub>/<Tela>.tsx',
  M.casos(parseSessao(S_ANINHADA[0].texto))[0].tela === 'governance/ModuleGrades/Index.tsx');
check('casos IGNORA path que nao e' + ' tela (Modules/, _components/)',
  M.casos(parseSessao([edit('D:/p/Modules/Jana/X.php'),
    edit('D:/p/resources/js/Pages/Jana/_components/Y.tsx')].join('\n'))).length === 0);
check('oraculo mwart NAO chama o hook (independencia)', !String(M.esperado).includes('impl'));

const mA = replay({ contrato: M, sessoes: S_ANINHADA, ctx: CTX, impl: (t, p) => MWART_ATUAL(t, p, SB) });
const mH = replay({ contrato: M, sessoes: S_ANINHADA, ctx: CTX, impl: (t, p) => MWART_ANTIGO(t, p, SB) });

check('BITE: impl HISTORICA diverge na tela aninhada (o FP do #4648)',
  mH.divergencias.length === 1 && mH.divergencias[0].esperado === 'passa' && mH.divergencias[0].observado === 'bloqueia');
check('GOOD: impl ATUAL concorda com a porta viva na MESMA tela',
  mA.divergencias.length === 0 && mA.acordo === 1);
check('DISCRIMINA: as duas impls dao resultados DIFERENTES', mA.taxa !== mH.taxa);

// CONTROLE-NEGATIVO: numa tela FLAT as duas impls concordam — a fixture sozinha nao
// discrimina, entao o BITE acima vem da tela ANINHADA, nao do arranjo do sandbox.
check('CONTROLE: em tela flat as 2 impls CONCORDAM (o bite vem do aninhamento)',
  replay({ contrato: M, sessoes: S_FLAT, ctx: CTX, impl: (t, p) => MWART_ATUAL(t, p, SB) }).taxa
  === replay({ contrato: M, sessoes: S_FLAT, ctx: CTX, impl: (t, p) => MWART_ANTIGO(t, p, SB) }).taxa);

// ── indeterminado NAO vira acordo (senao a isencao esvazia o conjunto, §5 2026-08-04) ──
const semOraculo = replay({ contrato: M, sessoes: S_ANINHADA, ctx: { runbook: {} }, impl: () => null });
check('oraculo mudo -> INDETERMINADO, fora da conta (nao conta como acordo)',
  semOraculo.total === 0 && semOraculo.indeterminados === 1 && semOraculo.acordo === 0);
const declMissing = replay({
  contrato: M, sessoes: S_ANINHADA, impl: () => null,
  ctx: { runbook: { 'governance/ModuleGrades/Index.tsx': { status: 'declared-missing', candidates: ['fantasma.md'] } } },
});
check('declared-missing (charter aponta fantasma) -> INDETERMINADO, nao fabrica divergencia',
  declMissing.total === 0 && declMissing.indeterminados === 1);
check('formatar reporta os indeterminados em vez de escondê-los',
  /indeterminados \(oraculo nao sabe, FORA da conta\): 1/.test(formatar('mwart', M, semOraculo)));

// ── preparar(): coleta as telas do corpus e delega pra porta viva (injetada) ──
let pedidas = null;
const ctxPrep = M.preparar([...S_ANINHADA, ...S_FLAT], {
  portaViva: (telas) => { pedidas = telas; return { ok: 1 }; },
});
check('preparar coleta as telas DISTINTAS e chama a porta viva 1x',
  pedidas.length === 2 && pedidas.includes('governance/ModuleGrades/Index.tsx') && ctxPrep.runbook.ok === 1);

// ── porta viva indisponivel -> {} (todos indeterminados), NUNCA verde por nao medir ──
check('portaVivaRunbook fail-open devolve {} quando o subprocesso falha',
  Object.keys(portaVivaRunbook(['A/B.tsx'], () => { throw new Error('sem node'); })).length === 0);
check('portaVivaRunbook sem telas nao gasta subprocesso',
  Object.keys(portaVivaRunbook([], () => { throw new Error('nao devia rodar'); })).length === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — os 2 contratos REPROVAM a impl historica e APROVAM a atual na MESMA fixture; com fixture que nao discrimina, as impls empatam (a licao). Indeterminado fica FORA da conta.');
process.exit(fails ? 1 : 0);
