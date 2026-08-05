#!/usr/bin/env node
// doc-fora-do-rag.mjs — PreToolUse:Write. ADVISORY (nunca bloqueia).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Em `memory/requisitos/<Mod>/`, o indexador do RAG coleta por **allowlist de nome
// EXATO** (9 nomes). Um `.md` fora dela existe no git, aparece no `Glob` do agente e
// **não existe para a busca da IA**. Isso hoje é guia escrita; ninguém é avisado no
// momento em que o arquivo nasce invisível.
//
// O dono do tema é `memory/reference/como-escrever-doc-para-o-rag.md` (Regra 1/2/3),
// que por sua vez é derivado de `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php`.
// Este hook NÃO cria regra nova nem segundo índice — ele cobra a regra do dono no
// único chokepoint que o fluxo real atravessa: a criação do arquivo.
//
// ── O QUE ELE AFIRMA (e por que isso não é julgamento) ───────────────────────
// A afirmação é FACTUAL e verificável: "este path não é coletado pelo indexador".
// Não diz que o autor errou — a própria Regra 2 admite anexo de módulo fora da busca.
// Por isso é advisory: o fato é do agente, a decisão é de quem escreve.
//
// ── FORWARD-ONLY ─────────────────────────────────────────────────────────────
// Só dispara em `Write` de arquivo que AINDA NÃO EXISTE. Os 584 legados ficam
// grandfatherados (lápide §5 2026-07-12 — tocar legado em massa acorda gate).
// `Edit`/overwrite de arquivo existente: silêncio.
//
// ── FP MEDIDO ANTES DE INSTALAR (§5 proibicoes) ──────────────────────────────
// FP aqui = avisar sobre path que o indexador COLETA. Medido em 2026-07-29 contra o
// ORÁCULO DE RUNTIME — a tabela `mcp_memory_documents` do container `oimpresso-mcp`
// (CT 100), não contra a minha leitura do PHP:
//
//   árvore main 1.077 .md sob memory/requisitos/ · classificados dentro 486 · fora 591
//   índice de produção (não-deletados) ................................. 486
//   FALSO-POSITIVO (disse FORA, o índice TEM) ............................. 0
//   FALSO-NEGATIVO (disse DENTRO, o índice não tem) ....................... 0
//
// O oráculo pegou 2 bugs que a replicação à mão tinha: `_DesignSystem/README.md` É
// indexado (o laço da allowlist casa QUALQUER diretório, inclusive `_DesignSystem`),
// e `memory/requisitos/_*.md` na raiz NÃO é (o laço da raiz pula `_`).
//
// Frequência de disparo (git log --diff-filter=A, 90d, repo completo): 1,29/dia
// fora o pico de migração em massa de 2026-06-08 — 35 dias ativos de 90.
//
// Reproduzir: node .claude/hooks/doc-fora-do-rag.mjs --measure [--indice <dump.txt>]
// Dump do oráculo: ver cabeçalho de `--measure` (tinker no container oimpresso-mcp).
//
// ── LIMITE HONESTO ───────────────────────────────────────────────────────────
// 1. A allowlist está HARDCODED aqui. Se o `$docsPorModulo` do indexador mudar, este
//    hook mente. Quem acusa isso é `doc-fora-do-rag.test.mjs`, que lê o PHP e compara
//    os conjuntos — o teste fica VERMELHO no dia da divergência. Não há leitura do PHP
//    em runtime (parsear código pra decidir comportamento é a classe LC-08).
// 2. Escopo = filho DIRETO de `memory/requisitos/<Mod>/`. É onde a allowlist existe.
//    Subpasta não-coberta (`_telas/`, `roadmap/`) também fica fora da busca, mas por
//    outro motivo (subárvore sem glob), e a saída "vá pra reference/" não se aplica
//    igual. Medido: 0,63/dia. Fora de escopo por decisão, não por esquecimento.
// 3. Basename com `_` fica em SILÊNCIO: o `_` é o opt-out declarado do próprio
//    indexador (Regra 3) — quem prefixa está dizendo "não me indexe". 13 casos em 90d.
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/doc-fora-do-rag.test.mjs
//
// Exit: sempre 0 (advisory). O aviso sai em stderr e vira contexto pro Claude.

import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const DONO_REL = 'memory/reference/como-escrever-doc-para-o-rag.md';

/**
 * Os 9 nomes EXATOS aceitos em `memory/requisitos/<Mod>/`.
 * Espelha `$docsPorModulo` + o glob dedicado de SPEC em IndexarMemoryGitParaDb.php.
 * Divergência é acusada pelo selftest, que lê o PHP.
 */
export const ALLOWLIST = Object.freeze([
  'SPEC', 'BRIEFING', 'RUNBOOK', 'ARCHITECTURE', 'GLOSSARY',
  'CHANGELOG', 'README', 'COMPARATIVO_CONCORRENCIA', 'SUPERFICIE',
]);
const ALLOW = new Set(ALLOWLIST);

/**
 * NÚCLEO PURO: o indexador coleta este path?
 * Replica os globs de IndexarMemoryGitParaDb::coletarArquivos() que tocam
 * `memory/requisitos/`. Verificado contra o índice de produção: 486/486, 0 FP, 0 FN.
 *
 * @param {string} gitPath caminho POSIX relativo ao repo
 * @returns {boolean|null} true = indexado · false = fora do RAG · null = fora de escopo
 */
export function coletadoPeloIndexador(gitPath) {
  const partes = String(gitPath || '').split('/');
  if (partes[0] !== 'memory' || partes[1] !== 'requisitos') return null;
  if (!/\.md$/i.test(partes[partes.length - 1])) return null;

  const resto = partes.slice(2);
  const base = resto[resto.length - 1].replace(/\.md$/i, '');
  // Regra 3: a coleta recursiva pula `_*` e README.
  const pulado = base.startsWith('_') || base === 'README';

  // glob("memory/requisitos/*.md") — pula `_*` (não pula README).
  if (resto.length === 1) return !base.startsWith('_');
  // glob("memory/requisitos/*/*.md") + glob dedicado de SPEC. O `*` casa QUALQUER
  // diretório — inclusive `_DesignSystem`, e é por isso que o README dele entra.
  if (resto.length === 2 && ALLOW.has(base)) return true;
  // coletarRecursivo("memory/requisitos/_DesignSystem") — subárvore inteira.
  if (resto[0] === '_DesignSystem') return !pulado;
  // glob("memory/requisitos/*/adr/*/*.md") — exige a categoria; <Mod>/adr/x.md fica fora.
  if (resto.length === 4 && resto[1] === 'adr') return !pulado;
  // glob("memory/requisitos/*/audits/*.md")
  if (resto.length === 3 && resto[1] === 'audits') return !base.startsWith('_');
  return false;
}

/**
 * NÚCLEO PURO: este Write merece aviso?
 * @param {{gitPath:string, jaExiste:boolean}} ctx
 * @returns {{avisar:boolean, base?:string, modulo?:string}}
 */
export function avaliar({ gitPath, jaExiste }) {
  if (jaExiste) return { avisar: false };                        // forward-only
  const partes = String(gitPath || '').split('/');
  // Escopo: filho DIRETO de memory/requisitos/<Mod>/ (onde a allowlist existe).
  if (partes.length !== 4) return { avisar: false };
  if (coletadoPeloIndexador(gitPath) !== false) return { avisar: false };
  const base = partes[3].replace(/\.md$/i, '');
  if (base.startsWith('_')) return { avisar: false };            // opt-out da Regra 3
  return { avisar: true, base, modulo: partes[2] };
}

/**
 * NÚCLEO PURO: converte o `file_path` da tool em caminho POSIX relativo ao repo.
 * Devolve '' quando o caminho está fora do repo.
 * @param {string} fp
 * @param {string} root
 */
export function paraGitPath(fp, root = ROOT) {
  const norm = String(fp || '').replace(/\\/g, '/');
  const base = String(root).replace(/\\/g, '/').replace(/\/+$/, '');
  if (norm.toLowerCase().startsWith(base.toLowerCase() + '/')) return norm.slice(base.length + 1);
  if (/^[a-z]:\//i.test(norm) || norm.startsWith('/')) return '';  // absoluto de outro lugar
  return norm.replace(/^\.\//, '');
}

/** NÚCLEO PURO: a mensagem (separada do IO pra ser testável). */
export function mensagem(gitPath, base, modulo) {
  return (
    `[doc-fora-do-rag] ℹ️  DOC NASCE FORA DO ÍNDICE DA IA — ${gitPath}\n` +
    `   Em memory/requisitos/<Mod>/ o indexador coleta por nome EXATO, e só estes 9:\n` +
    `   ${ALLOWLIST.join(' · ')}\n` +
    `   "${base}" não casa nenhum — e não casa por prefixo (RUNBOOK-algo ≠ RUNBOOK).\n` +
    `   O arquivo vai existir no git e no Glob do agente, e NÃO existir pra busca da IA.\n\n` +
    `   → precisa ser recuperável pela IA?  memory/reference/ tem cobertura recursiva\n` +
    `     (ou renomeie pra um dos 9 nomes, se for o doc canônico de ${modulo}).\n` +
    `   → é anexo do módulo que só se lê por path?  siga — a Regra 2 admite, sabendo do custo.\n\n` +
    `   Regra e exceções: ${DONO_REL} (Regra 2).\n` +
    `   Escrita NÃO foi bloqueada (advisory).`
  );
}

// ── modo --measure (reprodutibilidade do FP citado no cabeçalho) ─────────────
async function medir() {
  const { spawnSync } = await import('node:child_process');
  const { readFileSync } = await import('node:fs');
  // argv sem shell: cmd.exe come aspas simples e o MSYS mangleia ':(glob)'.
  const git = (...a) => {
    const r = spawnSync('git', a, { cwd: ROOT, encoding: 'utf8', maxBuffer: 1 << 28 });
    if (r.status !== 0) throw new Error(`git ${a.join(' ')} → ${r.status}\n${r.stderr}`);
    return r.stdout;
  };

  const arvore = git('ls-files', 'memory/requisitos').trim().split('\n').filter((f) => /\.md$/i.test(f));
  const dentro = arvore.filter((f) => coletadoPeloIndexador(f) === true);
  const fora = arvore.filter((f) => coletadoPeloIndexador(f) === false);
  console.log(`corpus memory/requisitos/**.md : ${arvore.length}`);
  console.log(`  classificados DENTRO do RAG  : ${dentro.length}`);
  console.log(`  classificados FORA do RAG    : ${fora.length}`);

  // Cross-check contra o oráculo de runtime (dump do índice de produção). Sem ele,
  // isto é auto-declaração — o número que vale é o do dump.
  const i = process.argv.indexOf('--indice');
  if (i >= 0 && process.argv[i + 1]) {
    const idx = new Set(readFileSync(process.argv[i + 1], 'utf8').trim().split(/\r?\n/).filter(Boolean));
    const fp = fora.filter((f) => idx.has(f));
    const fn = dentro.filter((f) => !idx.has(f));
    console.log(`\n[oráculo] índice de produção: ${idx.size} paths`);
    console.log(`  FALSO-POSITIVO (disse FORA, índice TEM) : ${fp.length}`);
    fp.slice(0, 20).forEach((f) => console.log(`     ${f}`));
    console.log(`  FALSO-NEGATIVO (disse DENTRO, não tem)  : ${fn.length}`);
    fn.slice(0, 20).forEach((f) => console.log(`     ${f}`));
  } else {
    console.log(
      '\n[oráculo] passe --indice <dump.txt> pra medir FP/FN de verdade. Gerar o dump:\n' +
      "  echo \"foreach (\\DB::table('mcp_memory_documents')->whereNull('deleted_at')\\n" +
      "    ->where('git_path','like','memory/requisitos/%')->pluck('git_path') as \\$p) echo \\$p,PHP_EOL;\" \\\n" +
      "    | tailscale ssh root@ct100-mcp 'docker exec -i oimpresso-mcp php artisan tinker' \\\n" +
      "    | grep -o 'memory/requisitos/[^ ]*\\.md' | sort -u > dump.txt"
    );
  }

  // Frequência de disparo real (forward-only ⇒ só arquivo que NASCE).
  const log = git('log', '--since=90 days ago', '--diff-filter=A', '--name-only',
    '--pretty=format:@%ad', '--date=short', '--', 'memory/requisitos');
  let dia = null; const novos = new Map();
  for (const l of log.split('\n')) {
    if (l.startsWith('@')) { dia = l.slice(1); continue; }
    if (/\.md$/i.test(l) && !novos.has(l)) novos.set(l, dia);
  }
  const dispara = [...novos].filter(([f]) => avaliar({ gitPath: f, jaExiste: false }).avisar);
  const dias = new Set(dispara.map(([, d]) => d));
  console.log(`\n[frequência 90d] .md novos sob memory/requisitos/: ${novos.size}`);
  console.log(`  disparariam o aviso: ${dispara.length} em ${dias.size} dias distintos`);
  const porDia = {};
  for (const [, d] of dispara) porDia[d] = (porDia[d] || 0) + 1;
  const pico = Object.entries(porDia).sort((a, b) => b[1] - a[1]).slice(0, 3);
  console.log(`  picos: ${pico.map(([d, n]) => `${d}=${n}`).join(' ')}`);
  const semPico = dispara.filter(([, d]) => d !== (pico[0] || [])[0]);
  console.log(`  sem o maior pico: ${semPico.length} (${(semPico.length / 90).toFixed(2)}/dia)`);
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────
async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

if (process.argv.includes('--measure')) {
  medir().catch((e) => { console.error(String(e && e.message)); process.exit(1); });
} else {
  (async () => {
    try {
      let raw = '';
      try { raw = await readStdin(); } catch { process.exit(0); }
      if (!raw) process.exit(0);

      let tool = '', input = {};
      try {
        const p = JSON.parse(raw);
        tool = String((p && p.tool_name) || '');
        input = (p && p.tool_input) || {};
      } catch { process.exit(0); }

      // Só Write. Edit/MultiEdit tocam arquivo que já existe → legado grandfatherado.
      if (tool !== 'Write') process.exit(0);

      const gitPath = paraGitPath(String(input.file_path || ''));
      if (!gitPath) process.exit(0);

      const alvo = resolve(ROOT, gitPath);
      const r = avaliar({ gitPath, jaExiste: existsSync(alvo) });
      if (r.avisar) console.error(mensagem(gitPath, r.base, r.modulo));
      process.exit(0);
    } catch { process.exit(0); }
  })();
}
