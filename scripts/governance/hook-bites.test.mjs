#!/usr/bin/env node
// Teste do hook-bites. Deriva do CONTRATO (medir ENTREGA real de hook de runtime),
// não da implementação. Fixture boa + ruim + CONTROLE (a sonda tem que achar o que
// sabemos que existe e NÃO achar o que sabemos que não existe).
// Rodar: node scripts/governance/hook-bites.test.mjs

import { mkdtempSync, writeFileSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { hooksWired, tagDe, sondas, contarNoTexto, relatorio, ALIASES } from './hook-bites.mjs';

let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// ── wiring ───────────────────────────────────────────────────────────────────
const S = { hooks: { PreToolUse: [{ matcher: 'Write|Edit', hooks: [
  { command: 'node .claude/hooks/foo.mjs' }, { command: 'node .claude/hooks/bar.mjs' }] }],
  Stop: [{ hooks: [{ command: 'node .claude/hooks/baz.mjs' }] }] } };
const w = hooksWired(S);
check('hooksWired acha os 3 + evento + matcher', w.length === 3
  && w[0].arquivo === 'foo' && w[0].evento === 'PreToolUse' && w[0].matcher === 'Write|Edit'
  && w[2].evento === 'Stop' && w[2].matcher === '*');
check('hooksWired: settings vazio nao explode', hooksWired({}).length === 0 && hooksWired(null).length === 0);

// ── tag: convencao, alias e NAO-OBSERVAVEL ───────────────────────────────────
const dir = mkdtempSync(join(tmpdir(), 'hb-'));
writeFileSync(join(dir, 'usa-convencao.mjs'), 'const M = "[usa-convencao] algo";');
writeFileSync(join(dir, 'charter-validate.mjs'), 'const M = "[charter-first] contrato vivo";');
writeFileSync(join(dir, 'sem-tag.mjs'), 'const M = "PRE-FLIGHT MISSING sem colchete";');
writeFileSync(join(dir, 'alias-mentiroso.mjs'), 'const M = "[outra-coisa] nao e o alias";');
check('tagDe: convencao [<arquivo>]', tagDe('usa-convencao', dir).tag === 'usa-convencao');
check('tagDe: alias declarado E presente no arquivo', tagDe('charter-validate', dir).tag === 'charter-first');
check('tagDe: sem colchete -> NAO-OBSERVAVEL', tagDe('sem-tag', dir).tag === null);
check('CONTROLE anti-drift: alias que o arquivo NAO contem nao vale',
  (() => { ALIASES['alias-mentiroso'] = 'inexistente'; const r = tagDe('alias-mentiroso', dir); delete ALIASES['alias-mentiroso']; return r.tag === null; })());

// ── sondas + contagem (o nucleo: escape literal, sem regex) ──────────────────
check('sondas cobre as 3 formas', sondas('x').length === 3
  && sondas('x').some(s => s.includes('systemMessage'))
  && sondas('x').some(s => s.includes('permissionDecisionReason'))
  && sondas('x').some(s => s.startsWith('"content":"[')));

const EMITIU = String.raw`{"content":"{\"systemMessage\":\"[block-destructive] Bash BLOQUEADO"}`;
const EMITIU2 = String.raw`{"x":"permissionDecisionReason\":\"[charter-first] tela TEM contrato"}`;
const EMITIU3 = '{"content":"[mwart-process] Edit em BLOQUEADO"}';
check('FIXTURE BOA: conta emissao systemMessage', contarNoTexto(EMITIU, 'block-destructive') === 1);
check('FIXTURE BOA: conta emissao permissionDecisionReason', contarNoTexto(EMITIU2, 'charter-first') === 1);
check('FIXTURE BOA: conta emissao content-plano', contarNoTexto(EMITIU3, 'mwart-process') === 1);

// FIXTURE RUIM — o erro que matou 2 das minhas 3 sondas em 2026-07-26:
// a tag aparece no CODIGO-FONTE do hook lido/editado, nao numa emissao.
const CODIGO_FONTE = String.raw`{"type":"tool_use","name":"Read","input":{"file_path":"D:/x/.claude/hooks/charter-validate.mjs"}}
{"content":"export function buildOutput(){ return '[charter-first] esta tela TEM contrato vivo'; }"}`;
check('FIXTURE RUIM: tag no codigo-fonte lido NAO conta como entrega',
  contarNoTexto(CODIGO_FONTE, 'charter-first') === 0);
check('FIXTURE RUIM: prosa mencionando a tag NAO conta',
  contarNoTexto('o hook [block-destructive] deveria falar aqui', 'block-destructive') === 0);
check('CONTROLE NEGATIVO: tag inexistente da 0', contarNoTexto(EMITIU, 'nao-existe') === 0);
check('contagem acumula multiplas emissoes', contarNoTexto(EMITIU + '\n' + EMITIU, 'block-destructive') === 2);

// ── relatorio: zero != falha, e nao-observavel aparece ───────────────────────
const rel = relatorio({
  wired: [{ arquivo: 'vivo', tag: 'vivo', evento: 'PreToolUse', matcher: 'Edit' },
          { arquivo: 'mudo', tag: 'mudo', evento: 'PreToolUse', matcher: 'Edit' }],
  contagem: new Map([['vivo', 42]]), naoObservaveis: ['sem-tag'], sessoes: 10, segundos: '1.0',
});
check('relatorio lista o que entregou', /42\s+vivo/.test(rel));
check('relatorio trata zero como OLHAR, nao falha', /ZERO entrega/.test(rel) && /nao e' falha/.test(rel) && !/FALHOU/.test(rel));
check('relatorio explica o FP do zero (condicao nunca satisfeita)', /Figma/.test(rel));
check('relatorio expoe os NAO-OBSERVAVEIS', /NAO-OBSERVAVEIS/.test(rel) && /sem-tag/.test(rel));
check('relatorio diz que a convencao e forward-only', /Forward-only|forward-only/i.test(rel));

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — mede ENTREGA real, ignora tag em codigo-fonte/prosa, zero e OLHAR nao falha.');
process.exit(fails ? 1 : 0);
