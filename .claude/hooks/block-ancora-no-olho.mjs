#!/usr/bin/env node
// block-ancora-no-olho.mjs — PreToolUse(Read): print de auditoria/crítica NÃO é âncora.
//
// REGISTRADO em .claude/settings.json (PreToolUse, matcher "Read"). Criar o arquivo NÃO
// ativa nada — o REGISTRO ativa. settings-ancora-registration.test.mjs guarda o registro
// (mesmo padrão de block-figma-without-optin).
//
// Por que existe (incidente #7, 2026-06-30): pra comparar "tela viva vs design", o agente
// leu `audit-financeiro.png` (PRINT DE AUDITORIA — estado velho sendo criticado) e o
// apresentou como "o design". DUAS vezes. O `detectar-telas`/`ancora.mjs` existiam, mas
// NADA impedia pegar um png solto no olho. Texto-canon não vence o reflexo; só interceptar
// a AÇÃO (a Read) vence — é o que este hook faz (mesma classe de block-figma/block-automem:
// bloqueio determinístico por PATH, não por regex semântica de prompt → conforme ADR 0224).
//
// REGRA: barra Read de um arquivo que é (a) print de auditoria/crítica (lista-negra única em
// ancora.mjs::ehAncoraIlegitima) E (b) está FORA do repo (bundle em Downloads/Desktop/staging).
// Pngs legítimos de design (ph-*, kpi-*, screenshots/ aprovados) NÃO casam a lista-negra.
//
// Escape (raro, justifique): OIMPRESSO_ANCORA_OK=1  (env) — pra quando você REALMENTE
// precisa abrir o print (ex: investigar a própria auditoria, não usá-lo como âncora).
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude)

import { stdin, env } from 'node:process';
import { basename, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ehAncoraIlegitima } from '../../prototipo-ui/ancora.mjs';

// fora-do-repo = onde bundles caem (Downloads/Desktop/staging) — não casa caminho do repo
const FORA_DO_REPO = /(\/|\\)(Downloads|Desktop|Área de Trabalho|Ã.rea de Trabalho)(\/|\\)|_cowork-handoff-staging|_handoff-[^/\\]*staging|cowork-snapshot/i;

export function razaoBloqueio(toolName, toolInput) {
  if (toolName !== 'Read') return null;
  const fp = (toolInput && toolInput.file_path) || '';
  if (!fp) return null;
  const norm = fp.replace(/\\/g, '/');
  if (!ehAncoraIlegitima(fp)) return null;     // não é print de auditoria/crítica → segue
  if (!FORA_DO_REPO.test(norm)) return null;    // png dentro do repo (raro) → não barra
  return (
    `⛔ ÂNCORA NO OLHO: "${basename(fp)}" é print de AUDITORIA/CRÍTICA (estado velho), NÃO o design.\n` +
    `   Foi exatamente o erro #7 (2026-06-30): print de auditoria apresentado como "o design".\n` +
    `   A âncora é COMPUTADA do charter:\n` +
    `     node prototipo-ui/ancora.mjs <Mod/Tela>   (ex: Financeiro/Unificado)\n` +
    `   Ela te dá o related_prototype aprovado + a tela viva. Print solto nunca é âncora.\n` +
    `   Escape real (investigar o próprio print, não usar como design): OIMPRESSO_ANCORA_OK=1`
  );
}

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  if (env.OIMPRESSO_ANCORA_OK === '1') process.exit(0);
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let p;
  try { p = JSON.parse(raw); } catch { process.exit(0); }
  const razao = razaoBloqueio(p.tool_name || '', p.tool_input || {});
  if (razao) { console.error(razao); process.exit(2); }
  process.exit(0);
}

const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) main();
