#!/usr/bin/env node
// @ts-check
/**
 * module-surface.mjs — GERADOR determinístico da "Superfície de código" de um módulo.
 *
 * A DOR (Wagner 2026-07-21): "não sei os arquivos de cada contexto, dificulta a busca
 * da IA". A resposta canônica NÃO é uma lista à mão no BRIEFING (apodrece — o distiller
 * `jana:distill-module-truth` recusa links por design + sobrescreve) nem um gate de
 * presença (§5 proibicoes). É DERIVAR da árvore, agrupado por PAPEL, num arquivo gerado
 * co-locado (`memory/requisitos/<Mod>/SUPERFICIE.md`) que a próxima geração recalcula.
 * "Derivado sobrevive; escrito+lembrado apodrece" (ADR 0256).
 *
 * ONDE ele mede: `Modules/<Mod>/**` (o código que MORA no módulo) + `resources/js/Pages/<Mod>/**`
 * (telas/charters/casos). Âncoras cross-cutting (bridge em app/, FSM) NÃO são deriváveis
 * por path — ficam narradas no BRIEFING (curado/destilado), não aqui. Honesto por construção.
 *
 * O que ele NÃO faz (delega): contagem de cobertura, nota, status por tela — donos são
 * `screen-coverage-map.mjs` + `casos-gate`. Aqui é só ONDE o código mora (ponteiro, não cópia).
 *
 * SEM data volátil no corpo: o frescor é provado por `--check` (conteúdo == árvore), não por
 * um timestamp que apodrece (§5 2026-07-17 — recibo é query re-rodável, não afirmação atemporal).
 *
 * Uso:
 *   node scripts/governance/module-surface.mjs <Mod>            (dry-run: imprime resumo)
 *   node scripts/governance/module-surface.mjs <Mod> --write    (grava memory/requisitos/<Mod>/SUPERFICIE.md)
 *   node scripts/governance/module-surface.mjs <Mod> --check    (CI: exit 1 se o gerado ≠ commitado = drift)
 *   node scripts/governance/module-surface.mjs --all [--write|--check]   (todos os Modules/*)
 *
 * Refs: ADR 0256 (survival, fonte única gerada) · dor estado-da-arte 2026-07-21
 *       (memory/sessions/2026-07-21-arte-contexto-vivo-descoberta.md, Gap 2).
 */
import { readdirSync, readFileSync, writeFileSync, existsSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const args = process.argv.slice(2);
const MODE = args.includes('--write') ? 'write' : args.includes('--check') ? 'check' : 'dry';
const ALL = args.includes('--all');
const POS = args.filter((a) => !a.startsWith('--'));

/**
 * Papéis, na ordem de exibição. `listar:false` = role volumoso → mostra contagem + link do dir
 * (Tests não precisa listar 77 arquivos; o dono da cobertura é screen-coverage/casos-gate).
 * Cada regra casa por prefixo relativo à raiz do repo. 1ª regra que casa vence (ordem importa).
 */
const PAPEIS = [
  { rot: 'Controllers', re: /^Modules\/[^/]+\/Http\/Controllers\/.*\.php$/, listar: true },
  { rot: 'Requests (validação)', re: /^Modules\/[^/]+\/Http\/Requests\/.*\.php$/, listar: true },
  { rot: 'Middleware', re: /^Modules\/[^/]+\/Http\/Middleware\/.*\.php$/, listar: true },
  { rot: 'Services', re: /^Modules\/[^/]+\/Services\/.*\.php$/, listar: true },
  { rot: 'Strategies', re: /^Modules\/[^/]+\/Strategies\/.*\.php$/, listar: true },
  { rot: 'Models / Entities', re: /^Modules\/[^/]+\/(Models|Entities)\/.*\.php$/, listar: true },
  { rot: 'Observers', re: /^Modules\/[^/]+\/Observers\/.*\.php$/, listar: true },
  { rot: 'Jobs', re: /^Modules\/[^/]+\/Jobs\/.*\.php$/, listar: true },
  { rot: 'Events / Listeners', re: /^Modules\/[^/]+\/(Events|Listeners)\/.*\.php$/, listar: true },
  { rot: 'Console / Commands', re: /^Modules\/[^/]+\/Console\/.*\.php$/, listar: true },
  { rot: 'Providers', re: /^Modules\/[^/]+\/Providers\/.*\.php$/, listar: true },
  { rot: 'Rotas', re: /^Modules\/[^/]+\/Routes\/.*\.php$/, listar: true },
  { rot: 'Migrations (schema)', re: /^Modules\/[^/]+\/Database\/Migrations\/.*\.php$/, listar: true },
  { rot: 'Seeders', re: /^Modules\/[^/]+\/Database\/Seeders\/.*\.php$/, listar: true },
  { rot: 'Config', re: /^Modules\/[^/]+\/Config\/.*\.php$/, listar: true },
  { rot: 'Views (Blade)', re: /^Modules\/[^/]+\/Resources\/views\/.*\.blade\.php$/, listar: false },
  { rot: 'Telas (Inertia/React)', re: /^resources\/js\/Pages\/[^/]+\/.*\.tsx$/, listar: true },
  { rot: 'Charters (lei da tela)', re: /^resources\/js\/Pages\/[^/]+\/.*\.charter\.md$/, listar: true },
  { rot: 'Casos (contrato UC)', re: /^resources\/js\/Pages\/[^/]+\/.*\.casos\.md$/, listar: true },
  { rot: 'Testes (Pest)', re: /^Modules\/[^/]+\/Tests\/.*\.php$/, listar: false },
];

/** Walk recursivo determinístico (sort). Retorna paths relativos à raiz, com forward-slash. */
function walk(rel) {
  const abs = join(ROOT, rel);
  if (!existsSync(abs)) return [];
  const out = [];
  for (const name of readdirSync(abs).sort()) {
    const childRel = `${rel}/${name}`;
    const st = statSync(join(ROOT, childRel));
    if (st.isDirectory()) out.push(...walk(childRel));
    else out.push(childRel);
  }
  return out;
}

/** Lista os módulos (dirs em Modules/) que têm module.json — a definição de "é um módulo". */
function listarModulos() {
  const dir = join(ROOT, 'Modules');
  if (!existsSync(dir)) return [];
  return readdirSync(dir).sort().filter((m) => existsSync(join(dir, m, 'module.json')));
}

/** Coleta os arquivos do módulo (código + telas) e agrupa por papel. */
function coletar(mod) {
  const files = [
    ...walk(`Modules/${mod}`),
    ...walk(`resources/js/Pages/${mod}`),
  ];
  const grupos = PAPEIS.map((p) => ({ ...p, files: /** @type {string[]} */ ([]) }));
  const outros = [];
  for (const f of files) {
    const g = grupos.find((p) => p.re.test(f));
    if (g) g.files.push(f);
    // "Outros" = só código .php de dir não-reconhecido (drop manifests json/js, assets, lang, views não-blade).
    else if (f.startsWith(`Modules/${mod}/`) && f.endsWith('.php') && !f.includes(`Modules/${mod}/Resources/`)) outros.push(f);
  }
  return { grupos, outros };
}

/** Path relativo de memory/requisitos/<Mod>/SUPERFICIE.md até um arquivo na raiz do repo. */
function linkDe(f) {
  return `../../../${f}`;
}

/** Monta o markdown determinístico da SUPERFICIE.md. */
function montar(mod, grupos, outros) {
  const total = grupos.reduce((n, g) => n + g.files.length, 0);
  const L = [];
  L.push('---');
  L.push(`name: "SUPERFÍCIE — ${mod}"`);
  L.push(`description: "Índice GERADO dos arquivos que moram no módulo ${mod}, agrupado por papel. Responde 'quais arquivos são deste contexto'. NÃO editar à mão."`);
  L.push('type: reference');
  L.push('authority: generated');
  L.push('lifecycle: ativo');
  L.push(`module: ${mod}`);
  L.push('---');
  L.push('');
  L.push(`# 🗺️ Superfície de código — ${mod}`);
  L.push('');
  L.push(`> ⚙️ **Gerado por máquina** (\`scripts/governance/module-surface.mjs\`). NÃO edite à mão — a próxima geração sobrescreve.`);
  L.push(`> Regenerar: \`node scripts/governance/module-surface.mjs ${mod} --write\`. Validar frescor: \`--check\` (exit 1 se a árvore mudou e isto não foi regenerado).`);
  L.push('>');
  L.push('> **O que isto é:** o código que MORA em `Modules/' + mod + '/**` + `resources/js/Pages/' + mod + '/**` — a porta pra "quais arquivos". **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.');
  L.push('');
  L.push(`**Total mapeado:** ${total} arquivos em ${grupos.filter((g) => g.files.length).length} papéis.`);
  L.push('');
  for (const g of grupos) {
    if (!g.files.length) continue;
    L.push(`## ${g.rot} — ${g.files.length}`);
    L.push('');
    if (g.listar) {
      for (const f of g.files) {
        const nome = f.split('/').pop();
        L.push(`- [${nome}](${linkDe(f)})`);
      }
    } else {
      // Role volumoso: contagem + link do diretório (não polui com dezenas de arquivos).
      const dir = g.files[0].split('/').slice(0, -1).join('/');
      L.push(`- ${g.files.length} arquivos em [${dir}/](${linkDe(dir)}) — cobertura é do \`casos-gate\`/\`screen-coverage\`, não deste índice.`);
    }
    L.push('');
  }
  if (outros.length) {
    L.push(`## Outros (raiz/misc) — ${outros.length}`);
    L.push('');
    for (const f of outros) L.push(`- [${f.split('/').pop()}](${linkDe(f)})`);
    L.push('');
  }
  return L.join('\n') + '\n';
}

/** Processa 1 módulo. Retorna {mod, total, drift}. */
function processar(mod) {
  const { grupos, outros } = coletar(mod);
  const total = grupos.reduce((n, g) => n + g.files.length, 0);
  if (total === 0 && outros.length === 0) {
    console.error(`[module-surface] módulo "${mod}" não encontrado ou vazio (Modules/${mod} inexistente?).`);
    return { mod, total, drift: false, missing: true };
  }
  const content = montar(mod, grupos, outros);
  const out = `memory/requisitos/${mod}/SUPERFICIE.md`;
  const outAbs = join(ROOT, out);

  if (MODE === 'write') {
    writeFileSync(outAbs, content, 'utf8');
    console.log(`[module-surface] ${mod}: ${total} arquivos → ${out} (gravado)`);
    return { mod, total, drift: false };
  }
  if (MODE === 'check') {
    const atual = existsSync(outAbs) ? readFileSync(outAbs, 'utf8') : null;
    // --all --check: só guarda módulos que JÁ optaram (têm SUPERFICIE.md commitado). Módulo
    // sem o arquivo = não-opt-in → pula (não é drift). Check de módulo explícito sem arquivo = drift.
    if (atual === null && ALL) {
      return { mod, total, drift: false, skipped: true };
    }
    const drift = atual !== content;
    if (drift) {
      console.error(`[module-surface] DRIFT em ${mod}: ${out} está desatualizado vs a árvore. Rode: node scripts/governance/module-surface.mjs ${mod} --write`);
    } else {
      console.log(`[module-surface] ${mod}: OK (${total} arquivos, sem drift)`);
    }
    return { mod, total, drift };
  }
  // dry
  console.log(`[module-surface] ${mod}: ${total} arquivos em ${grupos.filter((g) => g.files.length).length} papéis (dry-run — use --write pra gravar)`);
  for (const g of grupos) if (g.files.length) console.log(`  - ${g.rot}: ${g.files.length}`);
  return { mod, total, drift: false };
}

// ── main (só quando executado direto, não em import de teste) ───────────────────
import { pathToFileURL } from 'node:url';
function main() {
  const alvos = ALL ? listarModulos() : POS;
  if (!alvos.length) {
    console.error('Uso: node scripts/governance/module-surface.mjs <Mod> [--write|--check]  |  --all [--write|--check]');
    process.exit(2);
  }
  let driftCount = 0;
  for (const mod of alvos) {
    const r = processar(mod);
    if (r.drift) driftCount++;
  }
  if (MODE === 'check' && driftCount > 0) process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) main();

export { PAPEIS, coletar, montar };
