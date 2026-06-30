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

// AUTO-CONTIDO (adversário ATAQUE 1): tudo INLINE, sem import de ancora.mjs — um guarda não
// pode falhar-aberto porque outro arquivo quebrou (import com syntax-error → exit 1 ≠ 2 → tool
// liberada). Mantido.
//
// ALLOWLIST DE PROVENIÊNCIA (adversário ATAQUE 2, 2026-06-30): a defesa NÃO é mais lista-negra
// de nome ("audit-*.png") — que o rename-bypass furava (`financeiro-final-v2.png` escapava).
// Agora é ALLOWLIST: imagem externa (bundle/Downloads) só vale como âncora se for FONTE-DE-DESIGN
// CONHECIDA (screenshots/ · _ds/ · ds-v6/ · ph-*/kpi-*) ou o related_prototype do charter. Qualquer
// outra imagem externa — renomeada, print solto, arbitrária — BLOQUEIA. É o "espaço decidível" dos
// papers de verificação formal (denylist sintática é incompleta por construção; allowlist fecha).
const IMG = /\.(png|jpe?g|webp|gif)$/i;
// fontes-de-design legítimas (allowlist) — as ÚNICAS imagens externas que passam como âncora
const FONTE_DS_LEGITIMA = /(\/|\\)(screenshots|_ds|ds-v6)(\/|\\)|(^|\/|\\)(ph-|kpi-)[\w-]*\.(png|jpe?g|webp)$/i;
// fora-do-repo = onde bundles caem (Downloads/Desktop/staging) — não casa caminho do repo
const FORA_DO_REPO = /(\/|\\)(Downloads|Desktop|Área de Trabalho|Ã.rea de Trabalho)(\/|\\)|_cowork-handoff-staging|_handoff-[^/\\]*staging|cowork-snapshot/i;

export function temProvenanca(fp) { return FONTE_DS_LEGITIMA.test(String(fp).replace(/\\/g, '/')); }
// lista-negra de nome: NÃO é mais o gate — só enriquece a mensagem ("parece print de auditoria")
const NAO_ANCORA = /audit|critique|cr[ií]tica|scrap|-old|reavalia|tribunal/i;
export function ehAncoraIlegitima(p) { return !!p && IMG.test(p) && NAO_ANCORA.test(basename(p)); }

export function razaoBloqueio(toolName, toolInput) {
  if (toolName !== 'Read') return null;
  const fp = (toolInput && toolInput.file_path) || '';
  if (!fp) return null;
  const norm = fp.replace(/\\/g, '/');
  if (!IMG.test(norm)) return null;            // não é imagem → segue
  if (!FORA_DO_REPO.test(norm)) return null;    // imagem committed no repo → segue
  if (temProvenanca(norm)) return null;         // fonte-DS conhecida (allowlist) → segue
  const dica = ehAncoraIlegitima(fp) ? ' (parece print de auditoria/crítica)' : '';
  return (
    `⛔ ÂNCORA SEM PROVENIÊNCIA: "${basename(fp)}" é imagem externa que NÃO é fonte-de-design conhecida${dica}.\n` +
    `   Incidente #7: print apresentado como "o design". Allowlist (flip 2026-06-30): só vale como âncora\n` +
    `   screenshots/ · _ds/ · ph-*/kpi-* · ou o related_prototype do charter. Renomear o print NÃO escapa mais.\n` +
    `   A âncora é COMPUTADA: node prototipo-ui/ancora.mjs <Mod/Tela>\n` +
    `   Escape (investigar, não usar como design): OIMPRESSO_ANCORA_OK=1`
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
