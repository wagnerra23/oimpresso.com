#!/usr/bin/env node
// observabilidade-tags.test.mjs — a tag SAI na saída real? (bite-test da leva 2026-08-05)
//
// ── O BURACO QUE ISTO FECHA ─────────────────────────────────────────────────
// O `hook-bites` decide "observável" lendo o FONTE (`tagDe()` faz `src.includes('[tag]')`).
// Logo, uma tag esquecida num COMENTÁRIO contaria como observável e o hook seguiria mudo
// no mundo — presence-gate clássico (§5 LC-11: mede PRESENÇA, não COMPORTAMENTO), e da
// pior espécie, porque o painel diria "coberto".
//
// Este teste prova o COMPORTAMENTO: dispara o hook (ou a função que monta a mensagem) e
// exige a tag no INÍCIO da saída — que é onde a sonda casa (`"content":"[<tag>]`). Tag no
// meio da mensagem NÃO é observável, e um teste que só checasse `includes` deixaria passar.
//
// COBERTURA HONESTA: cobre os 15 hooks tagados na leva dos condicionais (2026-08-05).
// Os 11 do #5314 não estão aqui — entram quando forem tocados por trabalho real
// (forward-only). Este arquivo não é gate novo: é o bite-test do que esta leva mudou.
//
// Rodar: node .claude/hooks/observabilidade-tags.test.mjs
// (o CI já o pega via `node --test .claude/hooks/*.test.mjs` no gate-selftest)

import { spawnSync } from 'node:child_process';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

import { formatMessage } from './memory-pending.mjs';
import { NUDGE as NUDGE_DIAG } from './nudge-diagnosis-without-evidence.mjs';
import { NUDGE as NUDGE_MENU } from './nudge-recommend-not-menu.mjs';
import { CABECALHO } from './nudge-auditoria-resposta.mjs';
import { warnLines } from './warn-red-first.mjs';
import { NUDGE_LINES } from './nudge-test-contract-anchor.mjs';
import { mensagem as msgDocForaDoRag } from './doc-fora-do-rag.mjs';
import { mensagem as msgCharterDaTela } from './charter-da-tela-que-o-controller-serve.mjs';
import { buildOutput as temaOwnerOutput } from './tema-owner-advisory.mjs';

const DIR = dirname(fileURLToPath(import.meta.url));
const tmp = mkdtempSync(join(tmpdir(), 'obs-tags-'));

let fails = 0;
function check(nome, ok) {
  console.log(`${ok ? '[OK]  ' : '[FAIL]'} ${nome}`);
  if (!ok) fails++;
}

/** roda o hook com um payload e devolve stdout+stderr (os 2 canais chegam ao transcript). */
function rodar(hook, payload) {
  const r = spawnSync(process.execPath, [join(DIR, `${hook}.mjs`)], {
    input: JSON.stringify(payload),
    encoding: 'utf8',
  });
  return (r.stdout || '') + (r.stderr || '');
}

/** transcript JSONL de 1 linha com uma fala do assistant (payload dos hooks de Stop). */
function transcript(texto) {
  const p = join(tmp, `${Math.random().toString(36).slice(2)}.jsonl`);
  writeFileSync(p, JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: texto }] } }));
  return p;
}

/** o contrato: a saída COMEÇA com `[tag]`. `includes` não serve — a sonda casa no início. */
function comecaComTag(saida, tag) {
  return String(saida).trimStart().startsWith(`[${tag}]`);
}

// ── E2E: hook disparado de verdade, com payload que satisfaz a condição ──────
check(
  'E2E nudge-recommend-not-menu emite a tag no inicio',
  comecaComTag(rodar('nudge-recommend-not-menu', { transcript_path: transcript('Opções:\n1. A\n2. B\nQual você prefere?') }), 'nudge-recommend-not-menu'),
);
check(
  'E2E nudge-diagnosis-without-evidence emite a tag no inicio',
  comecaComTag(rodar('nudge-diagnosis-without-evidence', { transcript_path: transcript('A causa é a config errada.') }), 'nudge-diagnosis-without-evidence'),
);
check(
  'E2E design-agente-ativa emite a tag no inicio',
  comecaComTag(rodar('design-agente-ativa', { prompt: 'preciso desenhar a tela de vendas, faz o design' }), 'design-agente-ativa'),
);
check(
  'E2E design-compare-protocol emite a tag no inicio',
  comecaComTag(rodar('design-compare-protocol', { prompt: 'compare o design com a prod da tela de vendas' }), 'design-compare-protocol'),
);
check(
  'E2E design-handoff-reprocess emite a tag no inicio',
  comecaComTag(rodar('design-handoff-reprocess', { prompt: 'segue o handoff\n\n## new_design_memories\n- token novo' }), 'design-handoff-reprocess'),
);
check(
  'E2E force-r12-closing-signal emite a tag no inicio',
  comecaComTag(rodar('force-r12-closing-signal', { prompt: 'vamos encerrar a sessão' }), 'force-r12-closing-signal'),
);
check(
  'E2E audit-creates-tasks emite a tag no inicio',
  comecaComTag(
    rodar('audit-creates-tasks', {
      tool_name: 'Write',
      tool_input: {
        file_path: 'memory/sessions/2026-08-05-audit-teste.md',
        content: '# Audit\n\n- [ ] TASK[W](P0): consertar isso\n',
      },
    }),
    'audit-creates-tasks',
  ),
);
{
  const vista = join(tmp, 'vista.html');
  writeFileSync(vista, '<h1>Vista</h1><p>sem bloco de procedencia</p>');
  check(
    'E2E vista-publicada-padrao emite a tag no inicio',
    comecaComTag(rodar('vista-publicada-padrao', { tool_name: 'Artifact', tool_input: { file_path: vista } }), 'vista-publicada-padrao'),
  );
}

// ── unit: a mensagem é exportada, então testa-se a fonte da saída direto ─────
check('memory-pending: mensagem comeca com a tag', comecaComTag(formatMessage([' M memory/x.md']), 'memory-pending'));
check('nudge-auditoria-resposta: cabecalho comeca com a tag', comecaComTag(CABECALHO, 'nudge-auditoria-resposta'));
check('warn-red-first: warnLines comeca com a tag', comecaComTag(warnLines('Modules/X/Services/Y.php').join('\n'), 'warn-red-first'));
check('nudge-test-contract-anchor: NUDGE_LINES comeca com a tag', comecaComTag(NUDGE_LINES.join('\n'), 'nudge-test-contract-anchor'));
check('doc-fora-do-rag: mensagem comeca com a tag', comecaComTag(msgDocForaDoRag('memory/requisitos/X/ZZZ.md', 'ZZZ', 'X'), 'doc-fora-do-rag'));

// ── ALIAS: a tag emitida DIVERGE do nome do arquivo (registrada em hook-bites ALIASES) ──
check(
  'ALIAS charter-da-tela-que-o-controller-serve emite [charter-da-tela]',
  comecaComTag(msgCharterDaTela(['a.charter.md']), 'charter-da-tela'),
);
check(
  'ALIAS tema-owner-advisory emite [tema-owner]',
  comecaComTag(temaOwnerOutput('memory/x.md', '  · dono').hookSpecificOutput.permissionDecisionReason, 'tema-owner'),
);

// ── CONTROLE NEGATIVO: o teste sabe reprovar? ───────────────────────────────
// Sem isto, um `comecaComTag` quebrado deixaria os 15 verdes por vacuidade — que é
// exatamente o verde-por-nao-execucao que o §5 (LC-13) bane.
check('controle-negativo: tag no MEIO da mensagem reprova', comecaComTag('texto antes [nudge-recommend-not-menu]', 'nudge-recommend-not-menu') === false);
check('controle-negativo: mensagem sem tag reprova', comecaComTag('mensagem qualquer', 'memory-pending') === false);
check('controle-negativo: saida vazia reprova', comecaComTag('', 'memory-pending') === false);
check('controle-negativo: NUDGE das 2 constantes nao se confundem', NUDGE_MENU !== NUDGE_DIAG);

console.log(
  fails
    ? `\nSELFTEST FALHOU (${fails})`
    : '\nSELFTEST OK — os 15 hooks da leva emitem a tag no INICIO da saida real (8 E2E + 5 unit + 2 alias), e o teste sabe reprovar.',
);
process.exit(fails ? 1 : 0);
