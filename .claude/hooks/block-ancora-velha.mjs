#!/usr/bin/env node
// block-ancora-velha.mjs — PreToolUse(Edit|Write): não se edita tela com âncora de design VELHA.
//
// REGISTRADO em .claude/settings.json (PreToolUse, matcher "Edit|Write|MultiEdit"). O REGISTRO
// é o que ativa — sem ele o arquivo é decoração.
//
// ── POR QUE EXISTE (incidente 2026-08-27, medido) ────────────────────────────────
// O agente portou o componente `Chart` do Design System lendo
// `scripts/design-sync/mirror-snapshot/_ds_bundle.js`. Medido DEPOIS, contra o vivo:
//
//     _ds_bundle.js   vivo  1a4ce290…  259.766 chars
//                     local 9d2f6ce4…  287.354 chars   → DIVERGE
//
// O snapshot local estava VELHO. O `Chart` especificamente não tinha mudado (sha256 idêntico
// dos dois lados), então o porte saiu certo — por SORTE, não por método. Se ele tivesse
// mudado, o código nasceria morto e nenhum gate pegaria: build passa, TypeScript passa,
// pt-conformance passa. Fidelidade a design velho é invisível pra toda catraca existente.
//
// [W] 2026-08-27, textual: "âncora velha não execute" · "precisa ter a âncora atualizada" ·
// "arrume a máquina para exigir a âncora atualizada, antes de iniciar e todas suas
// dependências baixada por máquina" · "proíba âncoras velhas".
//
// ── O QUE JÁ EXISTIA, E POR QUE NÃO BASTAVA ──────────────────────────────────────
// O motor está pronto há tempo em `prototipo-ui/ancora.mjs` (`resolveAncora`,
// `frescorDoEspelho`, `ultimaRodada`) e o painel do protocolo DECLARA, em texto, que
// `--preview-ds` é "PORTÃO fail-closed: exit != 0 PROÍBE editar produto".
//
// Só que NADA executava isso antes de um Edit. Os dois hooks que citam o freshness
// (`design-compare-protocol`, `design-agente-ativa`) são UserPromptSubmit — imprimem
// lembrete, não travam. Era promessa sem chokepoint: a classe LC-15 do ledger
// ("mecanismo anuncia saída que não implementa"). Este arquivo é o chokepoint.
//
// ── O CRITÉRIO, E POR QUE ELE NÃO BLOQUEIA O MUNDO ───────────────────────────────
// `frescorDoEspelho` separa três estados, e a lápide §5 2026-07-29 exige tratá-los
// diferente — "não consegui medir" NÃO é um estado do objeto medido:
//
//   stale       divergência PROVADA (staleList, ou hash local ≠ hash registrado) → BLOQUEIA
//   nunca       a âncora existe mas ninguém mediu ainda                          → AVISA
//   sem-ledger  não há rodada registrada                                          → AVISA
//   verificado  medida e igual ao vivo                                            → passa
//
// FP MEDIDO ANTES DE ARMAR (regra "LIGUE A MÁQUINA" #4, 2026-08-27):
//   · 46 telas declaram âncora · 76 declaram `n/a` (não entram) · 17 sem o campo (não entram)
//   · `--sla` hoje sai != 0 (247 unchecked) — bloquear NELE reprovaria as 46 de uma vez,
//     sem que ninguém pudesse destravar: a medição exige DesignSync com auth interativa.
//     Por isso o gatilho é `stale`, não "não medido". Zero FP hoje: staleList está vazia.
//
// Nasce assim de propósito (ADR 0275: advisory e forward-only primeiro). Promover o `nunca`
// a bloqueio é flip do [W], DEPOIS que a rotina de medição tiver dono que a execute.
//
// Escape: OIMPRESSO_ANCORA_VELHA_OK=1 — para o caso em que [W] decide seguir com âncora
// sabidamente velha. Some da mensagem quando usado, porque o registro é o PR, não o env.
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão).

import { stdin, env, argv } from 'node:process';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { readFileSync, existsSync } from 'node:fs';
import { createHash } from 'node:crypto';

const AQUI = dirname(fileURLToPath(import.meta.url));
const RAIZ = resolve(AQUI, '..', '..');

/** telas Inertia — núcleo e módulos (as duas raízes que o app.tsx resolve) */
const ALVO = /(^|[\\/])(resources[\\/]js[\\/]Pages|Modules[\\/][^\\/]+[\\/]Resources[\\/]js[\\/]Pages)[\\/].+\.tsx$/i;

/** lê o charter irmão de uma Page e devolve o valor cru de related_prototype */
export function ancoraDeclarada(caminhoTsx, raiz = RAIZ) {
  const charter = caminhoTsx.replace(/\.tsx$/i, '.charter.md');
  const abs = resolve(raiz, charter);
  if (!existsSync(abs)) return null;
  let txt;
  try { txt = readFileSync(abs, 'utf8'); } catch { return null; }
  const fm = /^---\n([\s\S]*?)\n---/.exec(txt.replace(/\r\n/g, '\n'));
  const escopo = fm ? fm[1] : txt;
  const m = /^[ \t]*(?:related_prototype|canon_source):[ \t]*(.+?)[ \t]*$/m.exec(escopo);
  return m ? m[1].trim() : null;
}

/** `n/a (…)` é declaração legítima de "esta tela nasce do DS, não tem protótipo" */
export function declaraNa(valor) {
  return /^["']?n\/a\b/i.test(String(valor || '').trim());
}

/** extrai o path do espelho citado pela âncora (prototipo-ui/cowork/xxx.jsx) */
export function pathDaAncora(valor) {
  const m = String(valor || '').match(/prototipo-ui\/cowork\/[^\s`"'()]+\.(jsx|html|css|tsx)/i);
  if (m) return m[0];
  const solto = String(valor || '').match(/\b([\w.-]+\.(?:jsx|html|css))\b/i);
  return solto ? `prototipo-ui/cowork/${solto[1]}` : null;
}

/** última rodada de --compare do ledger (ignora as de --live-only) */
export function ultimaRodadaCompare(raiz = RAIZ) {
  try {
    const bruto = JSON.parse(readFileSync(join(raiz, 'scripts/governance/.cowork-freshness-ledger.json'), 'utf8'));
    const entradas = Array.isArray(bruto) ? bruto : (bruto.entries || []);
    const compare = entradas.filter((e) => e && e.kind !== 'live-only');
    return compare.length ? compare[compare.length - 1] : null;
  } catch {
    return null;
  }
}

/**
 * Estado de frescor de UMA âncora. Espelha `frescorDoEspelho` do ancora.mjs — reimplementado
 * aqui de propósito: import quebrado num hook vira exit 1, que é fail-OPEN silencioso
 * (mesma razão que o block-ancora-no-olho documenta).
 */
export function frescor(relPath, rodada, hashLocal) {
  if (!rodada) return 'sem-ledger';
  const rel = relPath.replace(/^prototipo-ui\/cowork\//, '');
  const na = (lista) => (lista || []).some((x) => x === relPath || x === rel);
  if (na(rodada.staleList)) return 'stale';
  if (!na(rodada.verified)) return 'nunca';
  const registrado = (rodada.verifiedHash || {})[relPath] || (rodada.verifiedHash || {})[rel];
  if (registrado && hashLocal && registrado !== hashLocal) return 'stale';
  return 'verificado';
}

function hashDe(abs) {
  try { return createHash('sha256').update(readFileSync(abs)).digest('hex'); } catch { return null; }
}

export function decidir(toolName, toolInput, raiz = RAIZ) {
  if (!/^(Edit|Write|MultiEdit)$/i.test(toolName || '')) return null;
  const alvo = toolInput?.file_path || toolInput?.path || '';
  if (!alvo || !ALVO.test(alvo)) return null;

  const rel = String(alvo).replace(/\\/g, '/').replace(new RegExp('^' + raiz.replace(/\\/g, '/').replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/?', 'i'), '');
  const declarada = ancoraDeclarada(rel, raiz);
  if (!declarada || declaraNa(declarada)) return null;   // sem âncora = nada a exigir

  const caminho = pathDaAncora(declarada);
  if (!caminho) return null;                              // não nomeia arquivo — outro guard cuida

  const estado = frescor(caminho, ultimaRodadaCompare(raiz), hashDe(resolve(raiz, caminho)));
  if (estado !== 'stale') return null;                    // `nunca`/`sem-ledger` avisam, não travam

  return {
    msg:
      `Edit em '${rel}' BLOQUEADO — a âncora de design está VELHA.\n` +
      `  âncora: ${caminho}\n` +
      `  o ledger de frescor registra este arquivo como STALE: o espelho divergiu do vivo.\n` +
      `Codar a partir dele produz tela fiel a um design que não existe mais — e nenhum gate pega\n` +
      `isso depois: build, TypeScript e pt-conformance passam todos.\n` +
      `Atualize pela MÁQUINA (nunca transcrevendo), na hierarquia do painel:\n` +
      `  node prototipo-ui/protocolo.config.mjs            # a fase -1 diz a rota vigente\n` +
      `  node scripts/governance/cowork-mirror-freshness.mjs --preview-ds   # deps do DS\n` +
      `Escape (decisão [W], registre no PR): OIMPRESSO_ANCORA_VELHA_OK=1`,
  };
}

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  if (env.OIMPRESSO_ANCORA_VELHA_OK === '1') process.exit(0);
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let p;
  try { p = JSON.parse(raw); } catch { process.exit(0); }
  const d = decidir(p.tool_name || '', p.tool_input || {});
  if (d) { console.error('[block-ancora-velha] ' + d.msg); process.exit(2); }
  process.exit(0);
}

const invocadoDireto = argv[1] && resolve(argv[1]) === fileURLToPath(import.meta.url);
if (invocadoDireto) main();
