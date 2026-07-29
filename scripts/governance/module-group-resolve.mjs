#!/usr/bin/env node
// @ts-check
/**
 * module-group-resolve.mjs — resolve O GRUPO DE MEMÓRIA de um módulo a partir da ÁRVORE.
 *
 * Contrato de forma: scripts/memory-schemas/module-group.schema.json
 * Instância (os papéis):     governance/module-group.json
 *
 * ── POR QUE ISTO EXISTE ──────────────────────────────────────────────────────
 * Todo agente/RAG que precisa "saber tudo do módulo X" hoje ADIVINHA quais arquivos
 * ler. Adivinhar o corpus é LC-08 (medir/afirmar a partir da fonte errada) — a classe
 * que mais reincide neste projeto. Aqui o corpus passa a ser DERIVADO da árvore e
 * conferível, em vez de lembrado.
 *
 * ── O QUE ELE NÃO FAZ (e não pode passar a fazer sem decisão [W]) ────────────
 *   · NÃO é gate. Sai 0 sempre, inclusive com papel faltando. Report-only por desenho:
 *     promover exige mordida provada + FP medido (regra "LIGUE A MÁQUINA" item 4).
 *   · NÃO escreve arquivo nenhum. Um "mapa de módulos" commitado seria doc paralelo aos
 *     donos que já existem (PAINEL-SISTEMA / governance/ARCHITECTURE) — §5 2026-07-23.
 *   · NÃO julga qualidade. Resolver um papel prova que o arquivo EXISTE; não prova que o
 *     conteúdo está certo nem fresco. Tratar presença como qualidade é LC-11 — a classe
 *     que bateu 3× em 3 dias neste repo. Por isso o relatório diz "resolvido", nunca "ok".
 *
 * ── A ARMADILHA QUE ELE JÁ NASCE IMUNE ───────────────────────────────────────
 * As três árvores divergem no nome do mesmo módulo:
 *     resources/js/Pages/kb        ↔  memory/requisitos/KB
 *     resources/js/Pages/team-mcp  ↔  memory/requisitos/TeamMcp
 * Casamento string-exata perde esses módulos EM SILÊNCIO — foi assim que um nudge de CI
 * ficou morto sem ninguém ver (§5 2026-07-17). Daí `case_insensitive` +
 * `normaliza_separador` serem OBRIGATÓRIOS no schema, e o selftest ter caso pra isso.
 *
 * Uso:
 *   node scripts/governance/module-group-resolve.mjs <Modulo>     # 1 módulo, legível
 *   node scripts/governance/module-group-resolve.mjs --all        # todos, tabela
 *   node scripts/governance/module-group-resolve.mjs <Mod> --json # máquina (RAG/agente)
 *   node scripts/governance/module-group-resolve.mjs --selftest
 */

import { readdirSync, readFileSync, existsSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { pathToFileURL } from 'node:url';

const ROOT = process.cwd();

// ── normalização de nome de módulo (o coração da imunidade ao case) ──────────
/** @param {string} s */
export const normalizaModulo = (s) =>
  String(s || '').toLowerCase().replace(/[-_\s]/g, '');

// ── glob mínimo (sem dep): suporta *, **, e o token {mod} ────────────────────
/**
 * Expande um glob com {mod} já substituído. Suporta `*` (dentro de um segmento)
 * e `**` (atravessa segmentos). Retorna paths relativos ao ROOT.
 * @param {string} padrao
 * @param {string} root
 * @returns {string[]}
 */
export function expandeGlob(padrao, root = ROOT) {
  const segmentos = padrao.split('/');
  /** @type {string[]} */
  const achados = [];

  /** @param {string} dirAbs @param {number} i */
  const anda = (dirAbs, i) => {
    if (i >= segmentos.length) return;
    const seg = segmentos[i];
    const ultimo = i === segmentos.length - 1;

    let entradas;
    try {
      entradas = readdirSync(dirAbs, { withFileTypes: true });
    } catch {
      return; // diretório não existe — ausência é resultado, não erro
    }

    if (seg === '**') {
      // '**' casa zero ou mais níveis: tenta o resto aqui mesmo…
      anda(dirAbs, i + 1);
      // …e desce em cada subdiretório
      for (const e of entradas) {
        if (e.isDirectory()) anda(join(dirAbs, e.name), i);
      }
      return;
    }

    const re = new RegExp(
      '^' + seg.split('*').map((p) => p.replace(/[.+?^${}()|[\]\\]/g, '\\$&')).join('[^/]*') + '$',
      'i', // case-insensitive: exigência do schema (Pages/kb ↔ requisitos/KB)
    );

    for (const e of entradas) {
      if (!re.test(e.name)) continue;
      const abs = join(dirAbs, e.name);
      if (ultimo) {
        if (e.isFile()) achados.push(relative(root, abs).split('\\').join('/'));
      } else if (e.isDirectory()) {
        anda(abs, i + 1);
      }
    }
  };

  anda(root, 0);
  return [...new Set(achados)].sort();
}

/**
 * Resolve o grupo de UM módulo.
 * @param {string} modulo
 * @param {any} contrato
 * @param {string} root
 */
export function resolveGrupo(modulo, contrato, root = ROOT) {
  const alvo = normalizaModulo(modulo);
  /** @type {Record<string, {pergunta: string, cardinalidade: string, arquivos: string[], nota?: string}>} */
  const papeis = {};

  for (const p of contrato.papeis) {
    /** @type {string[]} */
    let arquivos = [];
    for (const onde of p.onde) {
      // {mod} vira '*' e o filtro real acontece por normalização — assim
      // 'team-mcp', 'TeamMcp' e 'teammcp' caem no mesmo balde.
      const padrao = onde.replace('{mod}', '*');
      const segs = onde.split('/');
      const iToken = segs.findIndex((s) => s.includes('{mod}'));
      // O token pode ser o SEGMENTO INTEIRO (memory/requisitos/{mod}/SPEC.md) ou só
      // PARTE do nome do arquivo (memory/dominio/{mod}.md). No 2º caso, comparar o
      // segmento cru falha — 'financeiro.md' ≠ 'financeiro' — e o papel some calado.
      // Por isso extraímos o pedaço que o token realmente ocupa, via captura.
      const reToken = new RegExp(
        '^' + segs[iToken]
          .split('{mod}')
          .map((p2) => p2.replace(/[.+?^${}()|[\]\\*]/g, '\\$&'))
          .join('(.+)') + '$',
        'i',
      );
      for (const achado of expandeGlob(padrao, root)) {
        const candidato = (achado.split('/')[iToken] || '').match(reToken)?.[1];
        if (candidato && normalizaModulo(candidato) === alvo) arquivos.push(achado);
      }
    }
    arquivos = [...new Set(arquivos)].sort();
    papeis[p.papel] = {
      pergunta: p.pergunta,
      cardinalidade: p.cardinalidade,
      arquivos,
      ...(p.nota ? { nota: p.nota } : {}),
    };
  }

  const preenchidos = Object.values(papeis).filter((v) => v.arquivos.length > 0).length;
  return {
    modulo,
    papeis_no_contrato: contrato.papeis.length,
    papeis_resolvidos: preenchidos,
    papeis,
  };
}

/** Módulos conhecidos = união das árvores (nenhuma sozinha é a verdade). */
export function listaModulos(root = ROOT) {
  const dirs = (p) => {
    try {
      return readdirSync(join(root, p), { withFileTypes: true })
        .filter((e) => e.isDirectory() && !e.name.startsWith('_') && !e.name.startsWith('.'))
        .map((e) => e.name);
    } catch { return []; }
  };
  const porNorm = new Map();
  // Modules/ primeiro: é onde o nome canônico do módulo vive.
  for (const n of dirs('Modules')) porNorm.set(normalizaModulo(n), n);
  for (const n of dirs('memory/requisitos')) {
    if (!porNorm.has(normalizaModulo(n))) porNorm.set(normalizaModulo(n), n);
  }
  return [...porNorm.values()].sort();
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const ehRunDireto = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;

if (ehRunDireto) {
  const args = process.argv.slice(2);

  if (args.includes('--selftest')) {
    const { rodaSelftest } = await import('./module-group-resolve.test.mjs');
    process.exit(rodaSelftest() ? 0 : 1);
  }

  const contrato = JSON.parse(readFileSync(join(ROOT, 'governance', 'module-group.json'), 'utf8'));
  const json = args.includes('--json');
  const todos = args.includes('--all');
  const alvo = args.find((a) => !a.startsWith('--'));

  if (!todos && !alvo) {
    console.error('uso: module-group-resolve.mjs <Modulo> | --all [--json] | --selftest');
    process.exit(2);
  }

  if (todos) {
    const linhas = listaModulos().map((m) => resolveGrupo(m, contrato));
    if (json) { console.log(JSON.stringify(linhas, null, 2)); process.exit(0); }

    const nomes = contrato.papeis.map((/** @type {any} */ p) => p.papel);
    console.log(`\n  GRUPO DE MEMÓRIA por módulo — ${linhas.length} módulos × ${nomes.length} papéis`);
    console.log('  (resolvido = o arquivo EXISTE. NÃO diz que está correto nem fresco.)\n');
    const w = Math.max(...linhas.map((l) => l.modulo.length), 6);
    console.log('  ' + 'módulo'.padEnd(w) + '  ' + nomes.map((/** @type {string} */ n) => n.slice(0, 6).padEnd(6)).join(' ') + '   n');
    for (const l of linhas.sort((a, b) => a.papeis_resolvidos - b.papeis_resolvidos)) {
      const cels = nomes.map((/** @type {string} */ n) => (l.papeis[n].arquivos.length ? '  ✓   ' : '  ·   '));
      console.log('  ' + l.modulo.padEnd(w) + '  ' + cels.join(' ') + `   ${l.papeis_resolvidos}/${nomes.length}`);
    }
    console.log('\n  ✓ resolvido · · ausente — ausência NÃO é reprovação (report-only por desenho).\n');
    process.exit(0);
  }

  const r = resolveGrupo(alvo, contrato);
  if (json) { console.log(JSON.stringify(r, null, 2)); process.exit(0); }

  console.log(`\n  GRUPO DE MEMÓRIA — ${r.modulo}  (${r.papeis_resolvidos}/${r.papeis_no_contrato} papéis resolvidos)\n`);
  for (const [papel, v] of Object.entries(r.papeis)) {
    const marca = v.arquivos.length ? '✓' : '·';
    console.log(`  ${marca} ${papel}  — ${v.pergunta}`);
    for (const a of v.arquivos.slice(0, 8)) console.log(`      ${a}`);
    if (v.arquivos.length > 8) console.log(`      … +${v.arquivos.length - 8}`);
    if (!v.arquivos.length && v.nota) console.log(`      (${v.nota})`);
  }
  console.log('\n  Resolvido = o arquivo existe e está no lugar do papel. NÃO é veredito de qualidade.\n');
  process.exit(0);
}
