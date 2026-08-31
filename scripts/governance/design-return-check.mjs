#!/usr/bin/env node
// Verifica o retorno Code -> Design definido em prototipo-ui/PROTOCOL.md §10.2.
// O workflow pós-merge roda em modo advisory; --check existe para fixtures/catracas e
// para uma futura promoção explícita, sem criar enforcement novo por acidente.

import { appendFileSync, existsSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const RETURN_CHANNELS = Object.freeze([
  'prototipo-ui/DS_ADOCAO_INDICE.md',
  'prototipo-ui/SYNC_LOG.md',
  'prototipo-ui/HANDOFF.md',
]);

export const UI_PATTERNS = Object.freeze([
  /^resources\/js\/(?:Pages|Components|Layouts)\/.+\.(?:[jt]sx?|css)$/,
  /^resources\/css\/.+\.css$/,
  // Caixa: `[Rr]esources` segue a politica do dono de "onde moram as telas"
  // (`scripts/qa/page-path.mjs`), cuja razao esta escrita la: casing errado nao pode tirar a tela
  // do denominador em silencio. Esta perna nasceu so com `resources` minusculo e, em runner Linux
  // (case-sensitive), nunca casou arquivo nenhum — medido em 2026-08-31 contra origin/main:
  //   git ls-tree -r --name-only --full-tree origin/main | grep -cE '^Modules/[^/]+/resources/'
  // devolveu 0, contra 867 de `Resources/`. O gerador nWidart deste repo cria `Resources/`
  // (config/modules.php, paths.generator).
  /^Modules\/[^/]+\/[Rr]esources\/(?:js|css)\/.+\.(?:[jt]sx?|css)$/,
]);

export function normalizeChangedPaths(input) {
  const paths = Array.isArray(input) ? input : String(input || '').split(/\r?\n/);
  return [...new Set(paths
    .map((path) => String(path).trim().replaceAll('\\', '/').replace(/^\.\//, ''))
    .filter(Boolean))];
}

export function evaluateDesignReturn(input) {
  const changed = normalizeChangedPaths(input);
  const affected = changed.filter((path) =>
    UI_PATTERNS.some((pattern) => pattern.test(path))
      || path.startsWith('prototipo-ui/cowork/'));
  const missing = affected.length
    ? RETURN_CHANNELS.filter((channel) => !changed.includes(channel))
    : [];

  return {
    changed,
    affected,
    missing,
    applicable: affected.length > 0,
    complete: affected.length === 0 || missing.length === 0,
    issues: [],
  };
}

export function validateReturnDocuments({ index = '', syncDiff = '', handoff = '' }) {
  const issues = [];
  if (!index.includes('<!-- ds:worklist:start')
      || !index.includes('<!-- ds:worklist:end -->')
      || !/Gerado por `npm run ds:report/.test(index)
      || !/\*\*Próximo da fila:\*\*/.test(index)) {
    issues.push('DS_ADOCAO_INDICE não contém um worklist completo gerado por ds:report');
  }

  const added = String(syncDiff).split(/\r?\n/).filter((line) => /^\+(?!\+\+)/.test(line));
  const removed = String(syncDiff).split(/\r?\n/).filter((line) => /^-(?!--)/.test(line));
  if (removed.length || !added.some((line) => /^\+\d{4}-\d{2}-\d{2}.*\[CL\].*PR #\d+/i.test(line))) {
    issues.push('SYNC_LOG deve receber append datado [CL] com PR #N, sem apagar linhas');
  }

  for (const field of ['agora', 'próximo', 'restante']) {
    if (!new RegExp(`\\b${field}\\b`, 'iu').test(handoff)) issues.push(`HANDOFF sem campo semântico "${field}"`);
  }
  return issues;
}

export function renderDesignReturn(result) {
  if (!result.applicable) {
    return 'OK — mudança não tocou tela/DS; retorno §10.2 não se aplica.';
  }
  if (result.complete) {
    return `OK — retorno §10.2 completo: ${RETURN_CHANNELS.length}/${RETURN_CHANNELS.length} canais atualizados.`;
  }
  return [
    'design_return_skipped (§10.2) — mudança tocou tela/DS sem o retorno completo Code -> Design.',
    `Arquivos de tela/DS: ${result.affected.join(', ')}`,
    result.missing.length ? `Canais ausentes: ${result.missing.join(', ')}` : 'Os três arquivos foram tocados, mas seu conteúdo não fecha o contrato.',
    ...result.issues.map((issue) => `Problema semântico: ${issue}`),
    'Conserto: npm run ds:report:write + atualizar DS_ADOCAO_INDICE.md, SYNC_LOG.md e HANDOFF.md.',
  ].join('\n');
}

function summaryMarkdown(result) {
  return [
    '## design_return_skipped (§10.2)',
    '',
    'Esta mudança tocou **tela/DS**, mas não fechou os três canais obrigatórios do retorno `Code -> Design`.',
    '',
    'Tela/DS nesta mudança:',
    ...result.affected.map((path) => `- ${path}`),
    '',
    'Canais ausentes:',
    ...(result.missing.length ? result.missing.map((path) => `- ${path}`) : ['- nenhum path ausente; o conteúdo é inválido']),
    '',
    ...(result.issues.length ? ['Problemas semânticos:', ...result.issues.map((issue) => `- ${issue}`), ''] : []),
    '',
    'Conserto: `npm run ds:report:write` + atualizar `DS_ADOCAO_INDICE.md`, `SYNC_LOG.md` e `HANDOFF.md`.',
    '',
  ].join('\n');
}

function valueAfter(args, flag) {
  const index = args.indexOf(flag);
  return index === -1 ? null : args[index + 1] || null;
}

function changedFromCli(args) {
  const fromFile = valueAfter(args, '--changed-from');
  if (fromFile) {
    if (!existsSync(fromFile)) throw new Error(`--changed-from não existe: ${fromFile}`);
    return readFileSync(fromFile, 'utf8');
  }

  const base = valueAfter(args, '--base') || 'HEAD~1';
  const head = valueAfter(args, '--head') || 'HEAD';
  try {
    if (/^0+$/.test(base)) {
      return execFileSync('git', ['ls-tree', '-r', '--name-only', head], { encoding: 'utf8' });
    }
    return execFileSync('git', ['diff', '--name-only', base, head], { encoding: 'utf8' });
  } catch (error) {
    throw new Error(`git diff falhou para ${base}..${head}; sem universo, sem veredito (${String(error.message || error).split('\n')[0]})`);
  }
}

export function main(args = process.argv.slice(2), env = process.env) {
  let changed;
  try {
    changed = changedFromCli(args);
  } catch (error) {
    console.error(`✗ design-return-check: ${error.message}`);
    return 2;
  }

  const result = evaluateDesignReturn(changed);
  if (result.applicable && result.complete && args.includes('--validate-content')) {
    const base = valueAfter(args, '--base') || 'HEAD~1';
    const head = valueAfter(args, '--head') || 'HEAD';
    try {
      const index = readFileSync('prototipo-ui/DS_ADOCAO_INDICE.md', 'utf8');
      const handoff = readFileSync('prototipo-ui/HANDOFF.md', 'utf8');
      const syncDiff = execFileSync('git', ['diff', '--unified=0', base, head, '--', 'prototipo-ui/SYNC_LOG.md'], { encoding: 'utf8' });
      result.issues = validateReturnDocuments({ index, syncDiff, handoff });
      result.complete = result.issues.length === 0;
    } catch (error) {
      console.error(`✗ design-return-check: não foi possível validar o conteúdo dos canais (${error.message})`);
      return 2;
    }
  }
  console.log(renderDesignReturn(result));

  if (result.applicable && !result.complete) {
    for (const path of result.affected) {
      console.log(`::warning file=${path}::design_return_skipped — faltam ${result.missing.length} canal(is) do retorno §10.2`);
    }
    if (args.includes('--github-summary') && env.GITHUB_STEP_SUMMARY) {
      appendFileSync(env.GITHUB_STEP_SUMMARY, summaryMarkdown(result));
    }
    if (args.includes('--check')) return 1;
    console.log('ADVISORY: retorno incompleto ficou visível, mas este modo não bloqueia.');
  }
  return 0;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  process.exitCode = main();
}
