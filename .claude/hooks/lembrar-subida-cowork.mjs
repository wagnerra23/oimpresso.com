#!/usr/bin/env node
// lembrar-subida-cowork.mjs — PostToolUse:Bash. Depois de `gh pr create` ou `git push`, se o
// branch mexeu em `prototipo-ui/cowork/Wagner/`, diz ao agente o que ainda falta subir para
// cada um dos dois projetos do Claude Design. ADVISORY: não bloqueia nada e NÃO sobe nada.
//
// POR QUE ([W] 2026-09-29): "quero 1 git e dois desing syncronizado com ultimo git". O git é a
// fonte; o projeto de telas do [W] e o projeto-cópia (PROJETOS.telasFelipe, onde a Maiara vê a
// tela) recebem cópia dele. Subir exige o login interativo do claude.ai (ADR 0315) — hook e CI
// não têm —, então a subida segue sendo do agente logado. O que faltava era o LEMBRETE na hora
// certa: o PR #8110 só descobriu que precisava subir quando o check required "espelho — mexeu
// depois de verificar" ficou vermelho.
//
// QUEM SABE O QUE SUBIR não é este hook: é `scripts/design-sync/pendentes-cowork.mjs --resumo`
// (o dono do critério). Aqui só se decide QUANDO perguntar e se restringe aos arquivos do PR
// (`--so`), para não repetir a cada push os ~900 arquivos que o projeto-cópia ainda não recebeu.
//
// CANAL: `hookSpecificOutput.additionalContext` em JSON. stdout/stderr com exit 0 num PostToolUse
// ficam no transcript e não chegam ao agente (§5 2026-09-23, aviso armado que não chegava).
// Falha ao medir (git sem origin/main, script ausente) vira aviso "não consegui medir" — nunca
// silêncio com cara de "nada a subir" (LC-33).

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ESPELHO = 'prototipo-ui/cowork/Wagner/';

/** O comando interceptado é de publicação de branch? */
export function deveOlhar(cmd) {
  return /\bgh\s+pr\s+create\b/.test(cmd || '') || /\bgit\s+push\b/.test(cmd || '');
}

/** Monta o aviso a partir da saída do `--resumo` (1 linha por projeto: "<chave>: N a subir — ..."). */
// Devolve null quando não há nada a subir, e `undefined` quando a saída NÃO É um resumo — o
// branch pode carregar um pendentes-cowork anterior ao `--resumo` (medido 2026-09-29 no branch do
// #8110: ele ignora a flag e imprime o relatório longo, que lido como resumo vira aviso falso).
export const LINHA_RESUMO = /^[a-z]+: \d+ a subir\b/;
export function mensagem(resumo, qtdArquivos) {
  const linhas = String(resumo).split('\n').map((l) => l.trim()).filter(Boolean);
  if (!linhas.length || !linhas.every((l) => LINHA_RESUMO.test(l))) return undefined;
  const comPendencia = linhas.filter((l) => !/^[a-z]+: 0 a subir\b/.test(l));
  if (!comPendencia.length) return null;
  return [
    `[lembrar-subida-cowork] Este branch mexeu em ${qtdArquivos} arquivo(s) de ${ESPELHO} e eles ainda não subiram para:`,
    ...comPendencia.map((l) => '  · ' + l),
    'Suba antes do merge (o projeto w é cobrado pelo check required "espelho — mexeu depois de verificar"):',
    '  node scripts/design-sync/pendentes-cowork.mjs --plano [--projeto copia]  -> DesignSync finalize_plan/write_files',
    '  -> get_file de volta, salvo em <dir>/<rel>  -> pendentes-cowork.mjs --conferir <dir> [--projeto copia]',
    'O projeto w sobe da sessão do [W]; o copia, da sessão da Maiara. Escrita no Claude Design exige opt-in (ADR 0315).',
  ].join('\n');
}

function emitir(texto) {
  process.stdout.write(JSON.stringify({ hookSpecificOutput: { hookEventName: 'PostToolUse', additionalContext: texto } }) + '\n');
}

function principal() {
  let entrada = {};
  try { entrada = JSON.parse(readFileSync(0, 'utf8') || '{}'); } catch { return 0; }
  if (!deveOlhar(entrada?.tool_input?.command)) return 0;
  const cwd = entrada.cwd || process.cwd();
  let root, arquivos;
  try {
    root = execFileSync('git', ['rev-parse', '--show-toplevel'], { cwd, encoding: 'utf8' }).trim();
    arquivos = execFileSync('git', ['diff', '--name-only', 'origin/main...HEAD', '--', ESPELHO], { cwd: root, encoding: 'utf8' })
      .split('\n').filter(Boolean).map((p) => p.slice(ESPELHO.length));
  } catch (e) {
    emitir(`[lembrar-subida-cowork] NÃO MEDI o que o branch mudou em ${ESPELHO} (git falhou: ${String(e.message).split('\n')[0]}). Confira à mão: node scripts/design-sync/pendentes-cowork.mjs --resumo`);
    return 0;
  }
  if (!arquivos.length) return 0;
  const script = join(root, 'scripts', 'design-sync', 'pendentes-cowork.mjs');
  if (!existsSync(script)) return 0;
  let resumo;
  try {
    resumo = execFileSync(process.execPath, [script, '--resumo', '--so', ...arquivos], { cwd: root, encoding: 'utf8' });
  } catch (e) {
    emitir(`[lembrar-subida-cowork] NÃO MEDI as pendências (pendentes-cowork --resumo saiu ${e.status}). Rode à mão: node scripts/design-sync/pendentes-cowork.mjs --resumo`);
    return 0;
  }
  const m = mensagem(resumo, arquivos.length);
  if (m === undefined) emitir(`[lembrar-subida-cowork] NÃO MEDI: o pendentes-cowork deste branch não entende --resumo (branch anterior à versão dos 2 projetos). Atualize o branch com o main ou rode: node scripts/design-sync/pendentes-cowork.mjs --plano`);
  else if (m) emitir(m);
  return 0;
}

const direto = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (direto) process.exit(principal());
