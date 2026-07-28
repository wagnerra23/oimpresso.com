#!/usr/bin/env node
/**
 * rag-status-vocab-check — detecta documento que ENTRA no índice do RAG mas
 * é DESCARTADO na consulta por descasamento de vocabulário de `status`.
 *
 * O DEFEITO QUE ISTO VIGIA (medido 2026-07-28 no DB de prod `u906587222_oimpresso`):
 * o filtro de `status` aceita só o vocabulário de ADR — {aceito, accepted,
 * accepted-historical, recusado} ou ausente — mas vale pra TODO tipo, e os schemas
 * canônicos dos outros tipos definem enums que não intersectam.
 *
 * ⚠️ MAGNITUDE — a 1ª redação deste cabeçalho dizia "285 de 2.012 (14%) invisíveis à
 * busca". ERRADO por generalização: esse número é do caminho FULLTEXT, que é FALLBACK.
 * Os dois caminhos leem FONTES DIFERENTES:
 *   híbrido (primário, docs_pipeline=true): lê a COLUNA TIPADA via toSearchableArray
 *     (`$this->status ?? 'aceito'`) — desconhecido vira NULL vira 'aceito' → PASSA.
 *     Medido: 1.958 de 2.015 visíveis (97,2%); dos 57 fora, 47 são
 *     deprecated/rascunho/superseded (corretos) + 10 'proposto'.
 *   FULLTEXT (fallback): lê `metadata->status` CRU em scopePorStatusAtivo → descarta.
 *     Medido: 285 de 2.012.
 * Ou seja: o descasamento é REAL mas custa ~10 docs no caminho que atende, não 285.
 * O conserto é trocar a FONTE do filtro (coluna tipada, que já existe ao lado) —
 * jamais normalizar documento em massa (big-bang de legado, §5 proibicoes 2026-07-12).
 *
 * POR QUE O PREDICADO É LEGÍTIMO (e não um presence-gate dos proibidos em §5):
 * não mede presença de texto nem campo auto-declarado. É comparação de CONJUNTOS entre
 * três fontes derivadas de código — enum do schema × tipos que o indexador produz ×
 * whitelist do retrieval. Determinístico, sem banco, sem heurística de nome.
 *
 * FALSO-POSITIVO MEDIDO ANTES DE INSTALAR (regra §5 "ligar a máquina"):
 *   - 9 schemas varridos; 5 com interseção vazia
 *   - 2 deles (charter, topico) NÃO são tipos produzidos pelo indexador → excluídos
 *   - restam 3 (spec, briefing, runbook) — os TRÊS confirmados no banco de produção
 *   → FP = 0/3. O ADR passa limpo (é o dono do vocabulário).
 *
 * Advisory por desenho: o conserto é decisão do dono do módulo Jana (matriz §3 do
 * TEAM.md — [F]), e mexe em retrieval de produção, área com histórico de regressão
 * silenciosa (ADR 0312/0322). Este script REPORTA; não decide enforcement.
 *
 * Uso:
 *   node scripts/governance/rag-status-vocab-check.mjs            # relatório
 *   node scripts/governance/rag-status-vocab-check.mjs --check    # exit 1 se piorar vs baseline
 *   node scripts/governance/rag-status-vocab-check.mjs --selftest # bite-test
 */

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SCHEMAS_DIR = join(ROOT, 'scripts', 'memory-schemas');
const INDEXER = join(ROOT, 'Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php');
const MODEL = join(ROOT, 'Modules/Jana/Entities/Mcp/McpMemoryDocument.php');

/** Vocabulário aceito pelo retrieval, lido do model (não hardcoded aqui). */
export function extrairWhitelistStatus(src) {
  // Caminho híbrido/Meilisearch: status IN ['a','b',...]
  const m = src.match(/status\s+IN\s+\[([^\]]+)\]/);
  if (m) {
    return new Set(m[1].split(',').map((s) => s.trim().replace(/^['"]|['"]$/g, '')).filter(Boolean));
  }
  return new Set();
}

/**
 * Tipos que o indexador realmente produz.
 *
 * São TRÊS formas no arquivo, e usar só a primeira dá falso-negativo — foi o que
 * aconteceu na 1ª versão deste script: ele marcou `runbook` como "não indexado"
 * enquanto o banco de produção tinha 11 runbooks. Um gate com extrator parcial
 * silencia justamente o caso real.
 *   1. literal            'type'   => 'runbook'
 *   2. mapa $docsPorModulo 'RUNBOOK' => 'runbook'
 *   3. 3º argumento de coletarRecursivo($dir, $base, 'reference', 'prefixo')
 */
export function extrairTiposIndexados(src) {
  const out = new Set();
  for (const m of src.matchAll(/'type'\s*=>\s*'([a-z_-]+)'/g)) out.add(m[1]);
  for (const m of src.matchAll(/'[A-Z][A-Z_]*'\s*=>\s*'([a-z_-]+)'/g)) out.add(m[1]);
  for (const m of src.matchAll(/coletarRecursivo\([^)]*?,\s*'([a-z_-]+)'\s*,\s*'[a-z_-]+'\s*\)/g)) out.add(m[1]);
  return out;
}

/** Enum de `status` de cada schema, chaveado pelo tipo (nome do arquivo). */
export function extrairEnumsDeSchemas(dir) {
  const out = {};
  for (const f of readdirSync(dir).filter((x) => x.endsWith('.schema.json'))) {
    const tipo = f.replace('.schema.json', '');
    let json;
    try { json = JSON.parse(readFileSync(join(dir, f), 'utf8')); } catch { continue; }
    const en = json?.properties?.status?.enum;
    if (Array.isArray(en)) out[tipo] = en;
  }
  return out;
}

/** O núcleo: quem entra no índice mas é descartado na consulta. */
export function analisar({ enums, tiposIndexados, whitelist }) {
  const achados = [];
  const ignorados = [];
  for (const [tipo, en] of Object.entries(enums)) {
    if (!tiposIndexados.has(tipo)) { ignorados.push({ tipo, motivo: 'tipo não é produzido pelo indexador' }); continue; }
    const inter = en.filter((v) => whitelist.has(v));
    if (inter.length === 0) achados.push({ tipo, enum: en });
  }
  return { achados, ignorados };
}

function carregar() {
  const whitelist = extrairWhitelistStatus(readFileSync(MODEL, 'utf8'));
  const tiposIndexados = extrairTiposIndexados(readFileSync(INDEXER, 'utf8'));
  const enums = extrairEnumsDeSchemas(SCHEMAS_DIR);
  return { whitelist, tiposIndexados, enums };
}

function selftest() {
  const wl = new Set(['aceito', 'accepted', 'accepted-historical', 'recusado']);
  const casos = [
    { nome: 'MORDE: enum sem interseção em tipo indexado',
      enums: { runbook: ['rascunho', 'ativo'] }, tipos: new Set(['runbook']), esperado: 1 },
    { nome: 'LIBERA: enum intersecta (ADR, dono do vocabulário)',
      enums: { adr: ['aceito', 'recusado'] }, tipos: new Set(['adr']), esperado: 0 },
    { nome: 'LIBERA: tipo NÃO indexado (controle-negativo do FP medido)',
      enums: { charter: ['draft', 'live'] }, tipos: new Set(['adr']), esperado: 0 },
    { nome: 'LIBERA: schema sem enum de status',
      enums: {}, tipos: new Set(['reference']), esperado: 0 },
    { nome: 'MORDE: interseção parcial NÃO conta como vazia',
      enums: { spec: ['ativo', 'aceito'] }, tipos: new Set(['spec']), esperado: 0 },
  ];
  let ok = 0;
  for (const c of casos) {
    const { achados } = analisar({ enums: c.enums, tiposIndexados: c.tipos, whitelist: wl });
    const passou = achados.length === c.esperado;
    console.log(`  ${passou ? 'ok  ' : 'FALHA'} ${c.nome} (esperado ${c.esperado}, veio ${achados.length})`);
    if (passou) ok++;
  }
  // A whitelist tem que ser LIDA do model, não presumida.
  const wlReal = extrairWhitelistStatus(readFileSync(MODEL, 'utf8'));
  const leu = wlReal.size > 0;
  console.log(`  ${leu ? 'ok  ' : 'FALHA'} whitelist extraída do model (veio ${wlReal.size} valores)`);
  if (leu) ok++;

  // ÂNCORA CONTRA FALSO-NEGATIVO: estes tipos EXISTEM no índice de produção
  // (medido 2026-07-28: runbook=11, briefing=79, spec=62, surface=41, changelog=23).
  // A 1ª versão do extrator perdia `runbook` — só lia `'type' => 'x'` e ignorava o
  // mapa $docsPorModulo. Extrator parcial = gate que cala no caso real.
  const tiposReais = extrairTiposIndexados(readFileSync(INDEXER, 'utf8'));
  const devemExistir = ['runbook', 'briefing', 'spec', 'surface', 'changelog', 'adr', 'reference'];
  const faltando = devemExistir.filter((t) => !tiposReais.has(t));
  const achouTodos = faltando.length === 0;
  console.log(`  ${achouTodos ? 'ok  ' : 'FALHA'} extrator acha os tipos vistos no índice de prod${achouTodos ? '' : ' — FALTOU: ' + faltando.join(', ')}`);
  if (achouTodos) ok++;

  const total = casos.length + 2;
  console.log(`\n${ok}/${total} ${ok === total ? 'VERDE' : 'VERMELHO'}`);
  return ok === total ? 0 : 1;
}

function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) process.exit(selftest());

  if (!existsSync(MODEL) || !existsSync(INDEXER)) {
    console.error('rag-status-vocab-check: fonte ausente — o módulo Jana mudou de lugar?');
    process.exit(2); // falha visível, nunca exit 0 silencioso
  }

  const { whitelist, tiposIndexados, enums } = carregar();
  const { achados, ignorados } = analisar({ enums, tiposIndexados, whitelist });

  console.log('rag-status-vocab-check — vocabulário de `status`: schema × retrieval\n');
  console.log(`  whitelist do retrieval : ${[...whitelist].join(', ') || '(não lida)'} (+ status ausente)`);
  console.log(`  tipos indexados        : ${tiposIndexados.size}`);
  console.log(`  schemas com enum status: ${Object.keys(enums).length}\n`);

  if (achados.length === 0) {
    console.log('  Nenhum descasamento. Todo tipo indexado com enum de `status` intersecta a whitelist.');
    return 0;
  }

  console.log(`  ${achados.length} tipo(s) cujo doc CONFORME ao schema é descartado na consulta:\n`);
  for (const a of achados) {
    console.log(`    ${a.tipo.padEnd(10)} enum: [${a.enum.join(', ')}]  →  interseção VAZIA`);
  }
  for (const i of ignorados) console.log(`\n    (ignorado: ${i.tipo} — ${i.motivo})`);
  console.log('\n  Efeito: o doc que OBEDECE ao schema fica invisível; o que omite `status` aparece.');
  console.log('  Dono da decisão: módulo Jana (TEAM.md §3). Ver memory/reference/como-escrever-doc-para-o-rag.md §Regra 6-bis.');

  // Advisory: reporta sempre; só --check pode reprovar, e apenas contra baseline.
  if (args.includes('--check')) {
    const BASELINE = 3; // spec, briefing, runbook — estado conhecido em 2026-07-28
    if (achados.length > BASELINE) {
      console.error(`\n  PIOROU: ${achados.length} > baseline ${BASELINE}. Um tipo novo entrou no descasamento.`);
      return 1;
    }
    console.log(`\n  OK: ${achados.length} <= baseline ${BASELINE} (não piorou).`);
  }
  return 0;
}

process.exit(main());
