#!/usr/bin/env node
// ancora.mjs — a ÂNCORA de uma tela é COMPUTADA do charter, nunca escolhida no olho.
//
// Por que existe (incidente #7, 2026-06-30): o agente, pra comparar "a tela viva vs o
// design", pegou `audit-financeiro.png` (um PRINT DE AUDITORIA — estado velho sendo
// criticado) e apresentou como "o design". DUAS vezes. O mecanismo de detecção existia,
// mas nada FORÇAVA usá-lo nem IMPEDIA pegar um png solto. Wagner: "já deveria ter uma
// máquina pra isso." Esta é a máquina: dado uma tela, ela resolve, do charter canônico,
// QUAL é a fonte-de-design legítima — e diz explicitamente o que NÃO é âncora.
//
// Regra dura: âncora ∈ { related_prototype do charter, -page.jsx do bundle via charter }.
// audit-*.png / critique / screenshot solto NUNCA é âncora.
//
// Uso:
//   node prototipo-ui/ancora.mjs <tela>            # tela = rota (/financeiro/unificado)
//                                                  #   ou Mod/Tela (Financeiro/Unificado)
//                                                  #   ou caminho .tsx
//   node prototipo-ui/ancora.mjs <tela> --staging <dir>   # + resolve o -page.jsx do bundle
//   node prototipo-ui/ancora.mjs --list            # todas as telas + suas âncoras
//   node prototipo-ui/ancora.mjs --selftest        # fixture hermético
//
// Exit: 0 = âncora resolvida | 1 = sem charter (NÃO invente — registre/pergunte) | 2 = uso

import { existsSync } from 'node:fs';
import { join, resolve, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ehPrintSemantico } from '../.claude/hooks/block-ancora-no-olho.mjs';
import { read, frontmatter, walk } from './_lib-charter.mjs';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const REPO_DEFAULT = resolve(HERE, '..');

// ── helpers de leitura: read/frontmatter/walk vêm da lib compartilhada ────────
// (eram cópias locais idênticas às de detectar-telas — agora _lib-charter.mjs é a fonte única)
export { frontmatter }; // re-exporta pra preservar a API pública de ancora.mjs

// extrai 1º path de repo (.tsx) de um texto livre
export function repoTsx(text) {
  if (!text) return null;
  const m = text.match(/resources\/js\/Pages\/[\w./-]+\.tsx/);
  return m ? m[0] : null;
}
// extrai 1º mockup -page.jsx citado (NUNCA um audit/critique png)
export function mockupJsx(text) {
  if (!text) return null;
  const m = text.match(/[\w.-]*-page\.jsx/);
  return m ? m[0] : null;
}

// ── "print de auditoria não é âncora": FONTE ÚNICA = o hook ────────────────────
// Auditoria 2026-06-30 pegou DUAS denylists divergindo (esta tinha `screenshot`, o hook tinha
// `antig|adversari`). Agora reusa ehPrintSemantico do hook — uma definição só, não evolui à parte.
// É helper de MENSAGEM (o GATE real de âncora é a proveniência por charter, no hook::decidir).
// Auditoria: frontmatter/walk extraídos pra _lib-charter.mjs (fonte única); a denylist segue no hook.
export const ehAncoraIlegitima = ehPrintSemantico;

// normaliza a query da tela → tokens comparáveis
function norm(s) { return (s || '').toLowerCase().replace(/\\/g, '/').replace(/\.(tsx|charter\.md)$/i, '').replace(/\/index$/i, ''); }

// ── núcleo: resolve a âncora de UMA tela a partir dos charters do repo ────────
export async function resolveAncora(query, { repoRoot = REPO_DEFAULT, stagingDir = null } = {}) {
  // Git Bash (MSYS) mangleia arg iniciado em "/" pra "<raiz-msys>/<rota>" (ex.:
  // "/financeiro/unificado" vira "C:/Program Files/Git/financeiro/unificado") e a máquina
  // responderia "sem charter" FALSO. Detecção: path absoluto Windows que NÃO existe no disco
  // → tenta os sufixos como rota original (pegadinha catalogada 2026-07-01).
  if (/^[a-z]:[\\/]/i.test(query) && !existsSync(query)) {
    const partes = query.replace(/\\/g, '/').split('/').filter(Boolean);
    for (let i = 1; i < partes.length; i++) {
      const cand = '/' + partes.slice(i).join('/');
      const r = await resolveAncora(cand, { repoRoot, stagingDir });
      if (r.ok) return { ...r, query, avisoMangle: `query recebida mangleada pelo MSYS ("${query}") — recuperada como "${cand}". Use MSYS_NO_PATHCONV=1 no Git Bash.` };
    }
  }
  const pagesRoot = join(repoRoot, 'resources', 'js', 'Pages');
  const charters = (await walk(pagesRoot)).filter((f) => f.endsWith('.charter.md'));
  const q = norm(query);

  let hit = null;
  for (const cf of charters) {
    const fm = frontmatter(await read(cf));
    const page = norm(fm.page);                 // rota: /financeiro/unificado
    const comp = norm(fm.component);            // resources/js/Pages/Financeiro/Unificado/Index.tsx
    const relc = norm(relative(repoRoot, cf));  // .../Unificado/Index.charter.md
    if ((page && page === q) || (comp && comp.endsWith(q)) || (comp && q.endsWith(comp)) ||
        relc.includes(q) || (q && comp && comp.includes(q))) {
      hit = { charter: relative(repoRoot, cf).replace(/\\/g, '/'), fm };
      if (page === q || comp.endsWith(q)) break; // match forte ganha
    }
  }
  if (!hit) return { ok: false, query, motivo: 'sem charter pra essa tela — NÃO invente âncora; registre ou pergunte' };

  const fm = hit.fm;
  const ancoras = [];
  // 1) protótipo aprovado declarado no charter (related_prototype)
  if (fm.related_prototype) ancoras.push({ tipo: 'related_prototype (charter)', valor: fm.related_prototype });
  // 2) -page.jsx do bundle (se staging dado).
  // PREFERE o campo estruturado `bundle_source:` do charter (determinístico) — musing-elion 2026-06-30:
  // a heurística startsWith(dir) falhava quando o bundle nomeia o mockup pela RAIZ do módulo
  // (financeiro-page) e a tela vive em sub-pasta (Unificado). Só cai na heurística se não houver campo.
  if (stagingDir) {
    const stFiles = await walk(stagingDir);
    const declarado = mockupJsx(fm.bundle_source) || mockupJsx(fm.visual_source);
    let cand = declarado ? stFiles.find((f) => basename(f).toLowerCase() === declarado.toLowerCase()) : null;
    let via = cand ? 'bundle_source' : null;
    if (!cand) {
      const wanted = (basename(dirname(repoTsx(fm.component) || hit.charter)) || '').toLowerCase();
      cand = stFiles.find((f) => /-page\.jsx$/i.test(f) && basename(f).toLowerCase().startsWith(wanted));
      if (cand) via = 'heurística startsWith(dir)';
    }
    if (cand) ancoras.push({ tipo: `-page.jsx (bundle · ${via})`, valor: relative(stagingDir, cand).replace(/\\/g, '/') });
  }
  const liveTsx = repoTsx(fm.component);
  return {
    ok: true, query, charter: hit.charter,
    telaViva: liveTsx, ancoras,
    aviso: 'ÂNCORA = um dos itens acima. audit-*.png / critique / screenshot NUNCA é âncora.',
  };
}

function printResolve(r) {
  if (!r.ok) { console.error(`✗ ${r.query}: ${r.motivo}`); return 1; }
  if (r.avisoMangle) console.log(`⚠️ ${r.avisoMangle}`);
  console.log(`ÂNCORA da tela: ${r.query}`);
  console.log(`  charter:    ${r.charter}`);
  console.log(`  tela viva:  ${r.telaViva || '—'}`);
  if (!r.ancoras.length) console.log('  âncora:     ⚠️ charter sem related_prototype nem -page.jsx — registre o protótipo');
  for (const a of r.ancoras) console.log(`  âncora ✓:   [${a.tipo}] ${a.valor}`);
  console.log(`  ⛔ ${r.aviso}`);
  return 0;
}

async function listAll(repoRoot) {
  const charters = (await walk(join(repoRoot, 'resources', 'js', 'Pages'))).filter((f) => f.endsWith('.charter.md'));
  for (const cf of charters.sort()) {
    const fm = frontmatter(await read(cf));
    const anc = fm.related_prototype || mockupJsx(fm.component) || '⚠️ sem protótipo declarado';
    console.log(`${(fm.page || relative(repoRoot, cf)).padEnd(40)} → ${anc}`);
  }
}

async function selftest() {
  let fails = 0;
  const t = (label, cond) => { const ok = !!cond; if (!ok) fails++; console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label}`); };
  // contrato puro: audit/critique png nunca é âncora; -page.jsx é
  t('audit-financeiro.png é ÂNCORA ILEGÍTIMA', ehAncoraIlegitima('audit-financeiro.png') === true);
  t('Tribunal-x.png é ilegítima', ehAncoraIlegitima('Tribunal-x.png') === true);
  t('financeiro-page.jsx NÃO é ilegítima', ehAncoraIlegitima('financeiro-page.jsx') === false);
  t('ph-financeiro2.png (visual aprovado) NÃO casa lista-negra', ehAncoraIlegitima('ph-financeiro2.png') === false);
  t('mockupJsx pega -page.jsx', mockupJsx('component: financeiro-page.jsx (window.X)') === 'financeiro-page.jsx');
  t('repoTsx pega o .tsx', repoTsx('resources/js/Pages/Financeiro/Unificado/Index.tsx ok') === 'resources/js/Pages/Financeiro/Unificado/Index.tsx');
  // resolve real contra os charters do repo (tela conhecida)
  const r = await resolveAncora('/financeiro/unificado');
  t('resolve /financeiro/unificado acha charter', r.ok === true && /Unificado/.test(r.charter || ''));
  // query mangleada pelo MSYS (Git Bash converte "/" inicial) DEVE recuperar a rota
  const rm = await resolveAncora('C:/Program Files/Git/financeiro/unificado');
  t('resolve query mangleada MSYS recupera /financeiro/unificado', rm.ok === true && /Unificado/.test(rm.charter || '') && !!rm.avisoMangle);
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — âncora = charter, png de auditoria barrado.');
  process.exit(fails ? 1 : 0);
}

// ── main ─────────────────────────────────────────────────────────────────────
const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const val = (f) => { const i = argv.indexOf(f); return i >= 0 && argv[i + 1] ? argv[i + 1] : null; };
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (invokedDirectly) {
  if (has('--selftest')) await selftest();
  else if (has('--list')) { await listAll(REPO_DEFAULT); process.exit(0); }
  else {
    const tela = argv.find((a) => !a.startsWith('--') && argv[argv.indexOf(a) - 1] !== '--staging');
    if (!tela) { console.error('uso: node prototipo-ui/ancora.mjs <tela> [--staging <dir>] | --list | --selftest'); process.exit(2); }
    const r = await resolveAncora(tela, { stagingDir: val('--staging') });
    process.exit(printResolve(r));
  }
}
