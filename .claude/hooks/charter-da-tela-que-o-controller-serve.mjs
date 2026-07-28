#!/usr/bin/env node
// charter-da-tela-que-o-controller-serve.mjs — PreToolUse:Read. ADVISORY (nunca bloqueia).
//
// Quando você abre um Controller que RENDERIZA uma tela, aponta o charter dessa tela —
// que é o contrato dela, e frequentemente já responde a pergunta que te trouxe aqui.
//
// ── ORIGEM (incidente 2026-07-28) ───────────────────────────────────────────
// Reportei ao [W] que `toggleAutoEmission` "liga emissão automática de documento fiscal
// sem gate nenhum" — frase que sugere nota saindo sozinha pra todo cliente. FALSO: é
// opt-in POR EMPRESA (`nfe_business_configs.auto_emission_enabled`), o toggle só grava a
// escolha, e quem emite é o listener de venda finalizada.
// O charter da tela JÁ DIZIA ISSO, literal, em §Automation Anti-hooks:
//   "Não dispara Job de emissão quando toggleAutoEmission=true (Job é disparado por
//    listener de venda finalizada)"
// Eu li o Controller e nunca o charter. [W]: "não deveria ser sempre esse processo antes
// de tentar resolver algo?" — deveria, e é canon (how-trabalhar §ordem-de-fonte: doc canon
// ANTES do código). Faltava o lembrete no momento.
//
// ── POR QUE `Read` E NÃO `Edit` (medido, não achismo) ───────────────────────
// Corpus: 448 sessões com conteúdo em ~/.claude/projects. Pares (sessão, controller-com-
// charter) em que o charter NÃO tinha sido lido, por tool que tocou o controller:
//   Read puro 27 (59%) · Read+Edit 16 · Glob+Read 2 · Edit puro 1 (2%)
// Acoplar em Write|Edit (como o `modulo-preflight-warning`) pegaria 17 de 46 e perderia
// justamente a maioria — o caso do incidente, que foi LEITURA pra diagnosticar.
//
// ── PRECISÃO MEDIDA ─────────────────────────────────────────────────────────
//   leu o charter ANTES do controller:  0   ← em 448 sessões. adesão espontânea ZERO
//   leu DEPOIS (ia ler sozinho):       18   ← redundante
//   NUNCA leu (só o nudge avisaria):   46
//   precisão: 71,9%  ·  frequência: ~1 a cada 7 sessões
// Os 28% "redundantes" NÃO são a família dos guards mortos do §5: o `toHaveKey` (100% FP)
// acusava assert CORRETO de defeito. Aqui o pior caso é dizer algo que o agente ia
// descobrir sozinho — custo de 1 linha, sem veredito falso.
//
// ── POR QUE HOOK NOVO E NÃO EXTENSÃO ────────────────────────────────────────
// `modulo-preflight-warning` cobre BRIEFING/SPEC do MÓDULO em Write|Edit.
// `block-instrumento-sem-porta-viva` é instrumento-errado→porta-viva, e BLOQUEIA (exit 2).
// Aqui o predicado é outro (fonte complementar da TELA) e a severidade tem de ser
// advisory — bloquear um Read seria hostil e nunca é o desenho certo pra "leia também".
//
// ÂNCORA DETERMINÍSTICA, não heurística: a tela sai do `Inertia::render('Mod/Tela')` que o
// PRÓPRIO controller declara — 138 dos 140 controllers que renderizam resolvem charter.
// Não adivinho por nome de arquivo nem por pasta (§5: allowlist-de-pasta, 2026-06-30).
//
// Modos: OIMPRESSO_CHARTER_TELA_HOOK=off desliga. Fail-open sempre.
// Selftest: node .claude/hooks/charter-da-tela-que-o-controller-serve.mjs --selftest
// Exit: SEMPRE 0 (advisory puro — informa no stderr e deixa passar).

import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const RE_CONTROLLER = /Modules[\\/][A-Za-z]+[\\/]Http[\\/]Controllers[\\/].+Controller\.php$/;

/** Só Read de Controller de módulo. */
export function shouldFire(tool, toolInput) {
  if (String(tool || '') !== 'Read') return false;
  const p = String((toolInput && toolInput.file_path) || '');
  return RE_CONTROLLER.test(p);
}

/**
 * Charters das telas que ESTE controller declara renderizar.
 * A âncora é o argumento do Inertia::render — o código dizendo qual tela serve.
 */
export function chartersDo(arquivo, raiz) {
  let src = '';
  try { src = readFileSync(arquivo, 'utf8'); } catch { return []; }
  const telas = [...src.matchAll(/Inertia::render\(\s*['"]([^'"]+)['"]/g)].map((m) => m[1]);
  const out = [];
  for (const t of [...new Set(telas)]) {
    const rel = `resources/js/Pages/${t}.charter.md`;
    if (existsSync(join(raiz, rel))) out.push(rel);
  }
  return out;
}

export function mensagem(charters) {
  const lista = charters.map((c) => `  · ${c}`).join('\n');
  return `[charter-da-tela] Este Controller renderiza tela que TEM contrato escrito:\n${lista}\n` +
    `  O charter traz §Non-Goals e §Automation Anti-hooks — o que a tela deliberadamente NÃO faz.\n` +
    `  Ordem de fonte canônica (how-trabalhar.md): doc canon ANTES do código. Medido: em 448\n` +
    `  sessões, ZERO leram o charter antes do controller — e foi assim que um "achado" virou\n` +
    `  alarme falso em 2026-07-28 sobre algo que o charter já respondia.`;
}

function main() {
  if ((process.env.OIMPRESSO_CHARTER_TELA_HOOK || '').toLowerCase() === 'off') process.exit(0);
  let payload;
  try { payload = JSON.parse(readFileSync(0, 'utf8')); } catch { process.exit(0); }
  let fire = false;
  try { fire = shouldFire(payload?.tool_name, payload?.tool_input); } catch { process.exit(0); }
  if (!fire) process.exit(0);

  const arquivo = String(payload.tool_input.file_path);
  if (!existsSync(arquivo)) process.exit(0);
  // raiz = onde o Modules/ começa no path do arquivo
  const norm = arquivo.split('\\').join('/');
  const i = norm.indexOf('/Modules/');
  const raiz = i > 0 ? norm.slice(0, i) : resolve('.');

  let chs = [];
  try { chs = chartersDo(arquivo, raiz); } catch { process.exit(0); }
  if (!chs.length) process.exit(0);            // controller sem tela → silêncio

  process.stderr.write(mensagem(chs) + '\n');
  process.exit(0);                              // ADVISORY: informa e deixa passar
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const t = new URL('./charter-da-tela-que-o-controller-serve.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [fileURLToPath(t)], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
