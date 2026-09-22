#!/usr/bin/env node
// Teste do hook-bites. Deriva do CONTRATO (medir ENTREGA real de hook de runtime),
// não da implementação. Fixture boa + ruim + CONTROLE (a sonda tem que achar o que
// sabemos que existe e NÃO achar o que sabemos que não existe).
// Rodar: node scripts/governance/hook-bites.test.mjs

import { mkdtempSync, writeFileSync, mkdirSync, utimesSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, basename } from 'node:path';
import { hooksWired, tagDe, sondas, contarNoTexto, relatorio, ALIASES, checarAliases, listarJsonlLocal, arquivosTranscript,
  primeiroTimestampMs, cobertura, PISO_COBERTURA_HORAS } from './hook-bites.mjs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

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
// 4 formas desde 2026-08-16: as 3 de ADVISORY (a tag no INICIO do valor) + a do
// CANAL DE BLOQUEIO, que prefixa `[node .claude/hooks/<arquivo>.mjs]: ` antes da tag.
// Sem a 4a, o medidor era cego justamente aos hooks Tier-0 que barram.
check('sondas cobre as 4 formas (3 advisory + 1 canal de bloqueio)', sondas('x').length === 4
  && sondas('x').some(s => s.includes('systemMessage'))
  && sondas('x').some(s => s.includes('permissionDecisionReason'))
  && sondas('x').some(s => s.startsWith('"content":"['))
  && sondas('x').some(s => s.startsWith('.mjs]: [')));

const EMITIU = String.raw`{"content":"{\"systemMessage\":\"[block-destructive] Bash BLOQUEADO"}`;
const EMITIU2 = String.raw`{"x":"permissionDecisionReason\":\"[charter-first] tela TEM contrato"}`;
const EMITIU3 = '{"content":"[mwart-process] Edit em BLOQUEADO"}';
check('FIXTURE BOA: conta emissao systemMessage', contarNoTexto(EMITIU, 'block-destructive') === 1);
check('FIXTURE BOA: conta emissao permissionDecisionReason', contarNoTexto(EMITIU2, 'charter-first') === 1);
check('FIXTURE BOA: conta emissao content-plano', contarNoTexto(EMITIU3, 'mwart-process') === 1);

// FIXTURE BOA do CANAL DE BLOQUEIO — forma COPIADA de transcript real (2026-08-16),
// nao inventada. As 2 linhas sao o mesmo evento aparecendo 2x (content + Error:),
// que e' a razao pela qual a contagem infla ~2x e o numero vale como PISO.
const EMITIU_BLOQ = String.raw`"tool_result","content":"PreToolUse:Bash hook error: [node .claude/hooks/block-destructive.mjs]: [block-destructive] Bash BLOQUEADO (git-force-push). Motivo
,"toolUseResult":"Error: PreToolUse:Bash hook error: [node .claude/hooks/block-destructive.mjs]: [block-destructive] Bash BLOQUEADO (git-force-push). Motivo`;
check('FIXTURE BOA: conta emissao do canal de BLOQUEIO', contarNoTexto(EMITIU_BLOQ, 'block-destructive') === 2);
check('CONTROLE NEGATIVO: mencao ao .mjs sem a tag colada NAO conta',
  contarNoTexto('rodei node .claude/hooks/block-destructive.mjs e nada saiu', 'block-destructive') === 0);

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

// ── checarAliases: a promessa do cabecalho dos ALIASES, que ate 2026-08-05 nao existia ──
// Alias que para de casar NAO da erro em lugar nenhum: o hook so volta, calado, pra lista
// de nao-observaveis. Por isso o modo precisa MORDER — e precisa ser provado que morde.
{
  const dir = mkdtempSync(join(tmpdir(), 'hb-alias-'));
  // fixture BOA: o arquivo existe e contem a tag que o alias promete
  writeFileSync(join(dir, 'charter-validate.mjs'), 'process.stderr.write("[charter-first] oi")');
  const soUm = Object.fromEntries(Object.entries(ALIASES).filter(([k]) => k === 'charter-validate'));
  check('checarAliases: retorna estrutura {ok, quebrados}', (() => {
    const r = checarAliases(dir);
    return typeof r.ok === 'boolean' && Array.isArray(r.quebrados);
  })());
  check('FIXTURE BOA: alias que casa nao e acusado', (() => {
    const r = checarAliases(dir);
    return !r.quebrados.some((q) => q.arquivo === 'charter-validate');
  })() && Object.keys(soUm).length === 1);
  // fixture RUIM 1: arquivo existe mas nao emite mais a tag (alguem trocou a string)
  writeFileSync(join(dir, 'charter-validate.mjs'), 'process.stderr.write("mensagem nova sem tag")');
  check('FIXTURE RUIM: tag trocada no arquivo => MORDE', (() => {
    const r = checarAliases(dir);
    const q = r.quebrados.find((x) => x.arquivo === 'charter-validate');
    return r.ok === false && !!q && /nao emite mais/.test(q.motivo);
  })());
  // fixture RUIM 2: arquivo sumiu (foi o caso REAL do mcp-first-nudge, achado no 1o uso)
  check('FIXTURE RUIM: arquivo inexistente => MORDE com motivo proprio', (() => {
    const r = checarAliases(join(dir, 'vazio-de-proposito'));
    return r.ok === false && r.quebrados.every((q) => q.motivo === 'arquivo nao existe');
  })());
  check('checarAliases: o estado REAL do repo esta limpo', checarAliases().ok === true);
}

// ── listarJsonlLocal: a varredura do corpus (RECURSIVA desde 2026-08-17) ─────
// O defeito que isto trava: o loop era de 1 nivel e perdia 845 de 1173 .jsonl (72,1%)
// no corpus real — TODOS de subagente. Subagente dispara hook igual a sessao pai, entao
// o dead man's switch subestimava a entrega por construcao. A fixture abaixo reproduz a
// hierarquia REAL medida no corpus (`<sessao>/subagents/**` e
// `<sessao>/subagents/workflows/wf_<id>/**`), nao uma arvore inventada.
{
  const base = mkdtempSync(join(tmpdir(), 'hb-corpus-'));
  const proj = join(base, 'D--oimpresso-com');
  const sub = join(proj, 'sessao-uuid', 'subagents');
  const wf = join(sub, 'workflows', 'wf_abc123');
  mkdirSync(wf, { recursive: true });
  writeFileSync(join(proj, 'raso.jsonl'), '{}');            // o unico que a versao antiga via
  writeFileSync(join(sub, 'sub.jsonl'), '{}');              // nivel 2 — perdido antes
  writeFileSync(join(wf, 'wfagente.jsonl'), '{}');          // nivel 4 — perdido antes
  writeFileSync(join(proj, 'ruido.txt'), 'nao e jsonl');    // controle: extensao
  // dir de OUTRO projeto: o filtro tem que continuar excluindo (senao mede o mundo)
  const outro = join(base, 'D--outro-projeto');
  mkdirSync(join(outro, 'subagents'), { recursive: true });
  writeFileSync(join(outro, 'subagents', 'alheio.jsonl'), '{}');

  const achados = listarJsonlLocal({ base }).map((p) => basename(p)).sort();
  check('FIXTURE BOA: acha o .jsonl RASO (o unico que a versao de 1 nivel via)',
    achados.includes('raso.jsonl'));
  check('FIXTURE BOA: acha .jsonl de SUBAGENTE (nivel 2) — o caso que estava invisivel',
    achados.includes('sub.jsonl'));
  check('FIXTURE BOA: acha .jsonl sob subagents/workflows/wf_<id> (nivel 4)',
    achados.includes('wfagente.jsonl'));
  check('CONTROLE NEGATIVO: arquivo que nao e .jsonl fica de fora',
    !achados.some((n) => n.endsWith('.txt')));
  check('CONTROLE NEGATIVO: dir de outro projeto e excluido pelo filtro, INCLUSIVE em subdir',
    !achados.includes('alheio.jsonl') && achados.length === 3);
  check('filtro casa so no dir de 1o nivel — `subagents` NAO precisa casar o filtro',
    listarJsonlLocal({ base, filtro: 'oimpresso-com' }).length === 3);
  check('CONTROLE: filtro que nao casa nada devolve vazio',
    listarJsonlLocal({ base, filtro: 'inexistente-xyz' }).length === 0);
  check('base inexistente nao explode', listarJsonlLocal({ base: join(base, 'nao-existe') }).length === 0);

  // corte por mtime: envelhece o de subagente e confirma que a janela o exclui
  const velho = new Date(Date.now() - 40 * 86400000);
  utimesSync(join(sub, 'sub.jsonl'), velho, velho);
  const janela = listarJsonlLocal({ base, desdeMs: Date.now() - 7 * 86400000 }).map((p) => basename(p));
  check('corte por mtime vale TAMBEM no subdiretorio (nao so na raiz)',
    !janela.includes('sub.jsonl') && janela.includes('raso.jsonl') && janela.includes('wfagente.jsonl'));
  check('arquivosTranscript(0) = janela toda (wrapper nao perde arquivo)',
    arquivosTranscript(0, base).length === 3);
}


// ── cobertura do corpus: "zero entrega" so vale se houve OPORTUNIDADE ─────────
// O defeito que isto trava (medido 2026-09-22, container de nuvem): o corpus tinha 1
// .jsonl — o da sessao que estava abrindo, com segundos de idade —, a protecao de corpus
// VAZIO nao disparava, e o heartbeat publicava "52 wired com ZERO entrega". Acusacao por
// nao-medicao pela porta do corpus de 1 (§5 2026-07-29 · LC-33).
{
  const agora = Date.parse('2026-09-22T12:00:00Z');
  check('primeiroTimestampMs le o 1o timestamp do jsonl',
    primeiroTimestampMs('{"a":1,"timestamp":"2026-09-22T10:00:00.000Z"}\n{"timestamp":"2026-09-22T11:00:00Z"}')
      === Date.parse('2026-09-22T10:00:00.000Z'));
  check('CONTROLE NEGATIVO: sem timestamp => null (nao inventa inicio)', primeiroTimestampMs('{"a":1}') === null);
  check('cobertura: sessao de minutos => INSUFICIENTE',
    cobertura({ inicioMs: agora - 5 * 60000, agoraMs: agora }).suficiente === false);
  check('cobertura: 3 dias de historia => suficiente',
    cobertura({ inicioMs: agora - 3 * 86400000, agoraMs: agora }).suficiente === true);
  check('cobertura: fronteira no piso declarado', cobertura({ inicioMs: agora - PISO_COBERTURA_HORAS * 3600000, agoraMs: agora }).suficiente === true
    && cobertura({ inicioMs: agora - PISO_COBERTURA_HORAS * 3600000 + 1, agoraMs: agora }).suficiente === false);
  check('cobertura: inicio desconhecido => insuficiente (nao sei o que cobre, nao afirmo ausencia)',
    cobertura({ inicioMs: null, agoraMs: agora }).suficiente === false);

  const base = { wired: [{ arquivo: 'vivo', tag: 'vivo', evento: 'PreToolUse', matcher: 'Edit' },
    { arquivo: 'mudo', tag: 'mudo', evento: 'PreToolUse', matcher: 'Edit' }],
    contagem: new Map([['vivo', 3]]), naoObservaveis: [], sessoes: 1, segundos: '0.1' };
  const curto = relatorio({ ...base, cob: { horas: 0.1, suficiente: false } });
  check('relatorio com corpus CURTO: zero vira NAO MEDIDO, nao "ZERO entrega"',
    /NAO MEDIDO/.test(curto) && !/wired com ZERO entrega/.test(curto));
  check('relatorio com corpus CURTO: entrega (evidencia positiva) segue listada', /3\s+vivo/.test(curto));
  const longo = relatorio({ ...base, cob: { horas: 72, suficiente: true } });
  check('CONTROLE: corpus com historia continua acusando zero entrega', /wired com ZERO entrega/.test(longo));

  // CLI de FORA — o heartbeat e' o que chega na sessao; assert em funcao pura nao prova
  // o pipeline (§5 2026-07-30). HOME aponta pra um corpus-fixture; nada do corpus real.
  const script = join(fileURLToPath(new URL('.', import.meta.url)), 'hook-bites.mjs');
  const heartbeat = (tsIso) => {
    const home = mkdtempSync(join(tmpdir(), 'hb-home-'));
    const proj = join(home, '.claude', 'projects', '-home-user-oimpresso-com');
    mkdirSync(proj, { recursive: true });
    writeFileSync(join(proj, 's.jsonl'), `{"type":"x","timestamp":"${tsIso}"}\n`);
    const r = spawnSync(process.execPath, [script, '--heartbeat', '--dias', '14', '--throttle-horas', '0'],
      { encoding: 'utf8', env: { ...process.env, HOME: home, USERPROFILE: home } });
    return { rc: r.status, out: (r.stdout || '') + (r.stderr || '') };
  };
  const novo = heartbeat(new Date(Date.now() - 2 * 60000).toISOString());
  check('CLI heartbeat: sessao recem-aberta => NAO MEDIDO, sem acusar "ZERO entrega"',
    novo.rc === 0 && /NAO MEDIDO/.test(novo.out) && !/wired com ZERO entrega/.test(novo.out));
  const velho = heartbeat(new Date(Date.now() - 3 * 86400000).toISOString());
  check('CLI heartbeat (CONTROLE): corpus de 3 dias => acusa zero entrega normalmente',
    velho.rc === 0 && /wired com ZERO entrega/.test(velho.out) && !/NAO MEDIDO/.test(velho.out));
}

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — mede ENTREGA real, ignora tag em codigo-fonte/prosa, zero e OLHAR nao falha, --check-aliases morde e a varredura do corpus desce em subagents/.');
process.exit(fails ? 1 : 0);
