#!/usr/bin/env node
// @ts-check
/**
 * coleta.mjs — roteamento determinístico diff→contratos do pr-critic (zero LLM).
 *
 * Contrato-âncora: memory/sessions/2026-06-22-arte-design-to-code-sdd.md §3 gap #5
 * (critic automático read-only: GAP-SPEC × diff, achievement check) — variante CI
 * da grade-das-réguas 2026-07-09 (ataque ① — sem critic adversarial em PR de agente).
 *
 * O que faz: recebe a lista de arquivos do diff do PR, agrupa por tela/módulo e
 * resolve os ARTEFATOS DE CONTRATO de cada grupo — charter/casos ao lado do .tsx
 * (padrão vital-signs.mjs) + gap-spec/map.json em memory/requisitos/<Mod>/ que
 * REFERENCIAM algum arquivo tocado (roteamento por conteúdo, não por nome — os
 * gap.md têm nome solto tipo vendas-index-gap.md, mas frontmatter `tela_viva:`
 * aponta pro .tsx). Emite um manifesto JSON pro agente crítico (critica.mjs).
 *
 * NÃO decide nada de mérito; a crítica em si é o agente. Sem cap silencioso:
 * grupos cortados pelo teto vão em `descartados` (lei "no silent caps").
 *
 * Uso:
 *   node scripts/pr-critic/coleta.mjs --files-from /tmp/files.txt --out /tmp/manifesto.json
 *   node scripts/pr-critic/coleta.mjs --files "a.tsx,b.php"            # inline
 *   node scripts/pr-critic/coleta.mjs                                  # git diff origin/main...HEAD
 *   node scripts/pr-critic/coleta.mjs --root <dir>                     # árvore alternativa (fixtures/teste)
 */

import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, dirname, join, posix } from 'node:path';

export const MAX_GRUPOS = 8; // teto de custo por PR — excedente vai em `descartados`, nunca some calado

/** O arquivo do diff é alvo do critic? (Pages Inertia ou Modules) */
export function ehAlvo(p) {
  if (/^resources\/js\/Pages\/.+\.(tsx|jsx)$/.test(p)) return true;
  if (/^Modules\/[^/]+\/.+\.(php|tsx|ts|jsx|js|vue)$/.test(p)) return true;
  return false;
}

/** Módulo dono do arquivo (segmento após Pages/ ou Modules/). */
export function moduloDe(p) {
  let m = p.match(/^resources\/js\/Pages\/([^/]+)\//);
  if (m) return m[1];
  m = p.match(/^Modules\/([^/]+)\//);
  if (m) return m[1];
  return null;
}

/**
 * Chave de grupo: telas Inertia agrupam pelo diretório da tela (o dir que contém
 * o .tsx; arquivos sob _components/ sobem pro dir pai da tela). Modules agrupam
 * por Modules/<Mod>.
 */
export function grupoDe(p) {
  if (p.startsWith('resources/js/Pages/')) {
    const dir = posix.dirname(p);
    const idx = dir.indexOf('/_components');
    return idx === -1 ? dir : dir.slice(0, idx);
  }
  const m = p.match(/^(Modules\/[^/]+)\//);
  return m ? m[1] : null;
}

/** Charters/casos de um arquivo de tela: irmãos <Tela>.charter.md / <Tela>.casos.md. */
function contratosIrmaos(root, arquivo) {
  const base = arquivo.replace(/\.(tsx|jsx)$/, '');
  const out = { charter: [], casos: [] };
  for (const [tipo, suf] of [['charter', '.charter.md'], ['casos', '.casos.md']]) {
    const p = `${base}${suf}`;
    if (existsSync(join(root, p))) out[tipo].push(p);
  }
  return out;
}

/** Todos os charters/casos existentes no dir da tela (fallback pra mudança só em _components/). */
function contratosDoDiretorio(root, grupoDir) {
  const abs = join(root, grupoDir);
  const out = { charter: [], casos: [] };
  if (!existsSync(abs)) return out;
  for (const f of readdirSync(abs)) {
    if (f.endsWith('.charter.md')) out.charter.push(posix.join(grupoDir, f));
    else if (f.endsWith('.casos.md')) out.casos.push(posix.join(grupoDir, f));
  }
  return out;
}

/**
 * Gap-specs e maps do módulo que REFERENCIAM algum arquivo tocado.
 * Roteamento por conteúdo (path do arquivo aparece no texto do gap/map) — o nome
 * do gap é solto demais pra casar por convenção. Charter de módulo entra por nome.
 */
export function contratosDoModulo(root, mod, arquivosTocados) {
  const dir = join(root, 'memory', 'requisitos', mod);
  const out = { gap: [], map: [], charter_modulo: [] };
  if (!mod || !existsSync(dir)) return out;
  const charterMod = join(dir, `${mod}.charter.md`);
  if (existsSync(charterMod)) out.charter_modulo.push(posix.join('memory/requisitos', mod, `${mod}.charter.md`));
  for (const f of readdirSync(dir)) {
    const ehGap = f.endsWith('-gap.md');
    const ehMap = f.endsWith('.map.json');
    if (!ehGap && !ehMap) continue;
    let texto;
    try { texto = readFileSync(join(dir, f), 'utf8'); } catch { continue; }
    if (arquivosTocados.some((a) => texto.includes(a))) {
      out[ehGap ? 'gap' : 'map'].push(posix.join('memory/requisitos', mod, f));
    }
  }
  return out;
}

/** Aplica o teto de grupos — devolve { mantidos, descartados } SEM cap silencioso. */
export function limitarGrupos(grupos, max = MAX_GRUPOS) {
  const ordenados = [...grupos].sort((a, b) => b.arquivos.length - a.arquivos.length || a.id.localeCompare(b.id));
  return { mantidos: ordenados.slice(0, max), descartados: ordenados.slice(max) };
}

/** Pipeline completo: lista de arquivos → manifesto. */
export function coletar(arquivos, root = process.cwd()) {
  const alvos = arquivos.filter(ehAlvo);
  const porGrupo = new Map();
  for (const a of alvos) {
    const g = grupoDe(a);
    if (!g) continue;
    if (!porGrupo.has(g)) porGrupo.set(g, []);
    porGrupo.get(g).push(a);
  }

  const comContrato = [];
  const semContrato = [];
  for (const [id, arqs] of porGrupo) {
    const contratos = { charter: [], casos: [], gap: [], map: [], charter_modulo: [] };
    if (id.startsWith('resources/js/Pages/')) {
      let algumIrmao = false;
      for (const a of arqs) {
        if (a.includes('/_components/')) continue;
        const s = contratosIrmaos(root, a);
        if (s.charter.length || s.casos.length) algumIrmao = true;
        contratos.charter.push(...s.charter);
        contratos.casos.push(...s.casos);
      }
      // mudou só componente (ou tela sem contrato próprio): consome os charters do dir da tela
      if (!algumIrmao) {
        const d = contratosDoDiretorio(root, id);
        contratos.charter.push(...d.charter);
        contratos.casos.push(...d.casos);
      }
    }
    const mod = moduloDe(arqs[0]);
    const m = contratosDoModulo(root, mod, arqs);
    contratos.gap.push(...m.gap);
    contratos.map.push(...m.map);
    contratos.charter_modulo.push(...m.charter_modulo);

    for (const k of Object.keys(contratos)) contratos[k] = [...new Set(contratos[k])].sort();
    const total = Object.values(contratos).reduce((n, l) => n + l.length, 0);
    const grupo = { id, modulo: mod, arquivos: arqs.sort(), contratos };
    (total > 0 ? comContrato : semContrato).push(grupo);
  }

  const { mantidos, descartados } = limitarGrupos(comContrato);
  return {
    gerado_por: 'scripts/pr-critic/coleta.mjs',
    grupos: mantidos,
    sem_contrato: semContrato.map((g) => ({ id: g.id, arquivos: g.arquivos })),
    descartados: descartados.map((g) => ({ id: g.id, motivo: `teto de ${MAX_GRUPOS} grupos por PR`, arquivos: g.arquivos })),
    ignorados: arquivos.filter((a) => !ehAlvo(a)),
  };
}

// ── CLI ──────────────────────────────────────────────────────────────────────
function argVal(flag) {
  const i = process.argv.indexOf(flag);
  return i !== -1 ? process.argv[i + 1] : null;
}

function main() {
  const root = argVal('--root') || process.cwd();
  const out = argVal('--out') || 'storage/pr-critic/manifesto.json';
  let arquivos;
  const inline = argVal('--files');
  const deArquivo = argVal('--files-from');
  if (inline) arquivos = inline.split(',').map((s) => s.trim()).filter(Boolean);
  else if (deArquivo) arquivos = readFileSync(deArquivo, 'utf8').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
  else arquivos = execFileSync('git', ['diff', '--name-only', 'origin/main...HEAD'], { cwd: root, encoding: 'utf8' })
    .split(/\r?\n/).filter(Boolean);

  const manifesto = coletar(arquivos, root);
  mkdirSync(dirname(out), { recursive: true });
  writeFileSync(out, JSON.stringify(manifesto, null, 2) + '\n');
  console.log(`[coleta] ${manifesto.grupos.length} grupo(s) com contrato · ${manifesto.sem_contrato.length} sem contrato · ${manifesto.descartados.length} descartado(s) pelo teto`);
  for (const g of manifesto.grupos) {
    const n = Object.values(g.contratos).reduce((s, l) => s + l.length, 0);
    console.log(`  - ${g.id}: ${g.arquivos.length} arquivo(s), ${n} contrato(s)`);
  }
}

if (process.argv[1] && import.meta.url.endsWith(basename(process.argv[1]))) main();
