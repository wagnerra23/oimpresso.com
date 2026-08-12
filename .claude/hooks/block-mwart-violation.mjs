#!/usr/bin/env node
// block-mwart-violation.mjs — PreToolUse:Write|Edit|MultiEdit (PORTE cross-plataforma do .ps1).
// BLOQUEIA Edit/Write em Pages/<Mod>/<Tela>.tsx sem o RUNBOOK da tela existir.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// ADR 0104 §Enforcement (processo MWART canônico — ÚNICO caminho): F1 PLAN (RUNBOOK +
// SPEC) acontece ANTES de F3 FRONTEND (codar a Page Inertia). Wagner 2026-05-08:
// "Falhas não são aceitáveis. Não pode ter 2 caminhos de desenvolvimento."
// proibicoes.md §MWART: Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` SEM
// `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` é PROIBIDO. Desde a ADR 0271 onda 2
// (mwart-gate.yml de CI deletado — era teatro continue-on-error) este hook runtime é o
// ÚNICO enforcement de RUNBOOK.
//
// ⚠️ NÃO TEM ESCAPE — e até 2026-08-12 esta linha e a mensagem OFERECIAM um ('/mwart-override
// <razão>' em PR). Não havia handler (zero process.env, exit 2 é o único veto) e não poderia
// haver: o '/mwart-override' é registro HUMANO no PR — exceção de PROCESSO que vira ADR
// per-tela (ex. 0112 Whatsapp/Settings, 0177 Cliente/Show) — nunca comando de máquina, porque
// nenhum workflow processa `issue_comment` (mwart-process/SKILL.md §131, caso PR #2544) e este
// hook é PreToolUse: dispara ANTES de existir PR pra comentar. Mesma correção que o
// visual-regression.yml fez em 2026-06-11 ("removida a oferta — nunca houve handler que a
// processasse"). O único caminho aqui é fazer a F1: criar o RUNBOOK.
//
// Exempções (derivadas da regra — _components/helpers não são telas migráveis):
//   - Pages/_Showcase|_components|_internal/** (módulo utilitário, não vertical)
//   - Pages/<Mod>/_components/** e qualquer subpasta _* (privado do módulo)
//   - Telas iniciando com _ ou chamadas App/Layout (infra do shell, não tela)
//
// ── TELAS ANINHADAS: RUNBOOK por ROTA/SUBDIR ou declarado no charter ────────────
// Pages/<Mod>/<Subdir>/Index.tsx nomeia o RUNBOOK pela ROTA (o subdir), não pelo
// filename genérico 'Index'. Ex: governance/ModuleGrades/Index.tsx → o RUNBOOK real é
// RUNBOOK-module-grades.md (kebab do subdir), declarado no campo `runbook:` do
// Index.charter.md ao lado. Antes derivávamos só de 'Index' → RUNBOOK-index.md ausente
// → falso-positivo (bloqueou Edit legítimo no PR #4648). O gate agora aceita, além de
// RUNBOOK-<kebab(tela)>.md:
//   (1) RUNBOOK-<kebab(subdir)>.md  quando o path é <Mod>/<Subdir>/<Tela>.tsx; E/OU
//   (2) o `runbook:` declarado no <Tela>.charter.md ao lado — VALIDANDO que o arquivo
//       referenciado EXISTE. Charter apontando runbook fantasma NÃO fura o gate
//       (caso-ataque coberto no teste). A confiança termina no charter (mesma fronteira
//       de proveniência da lápide 2026-06-30): validamos existência, não semântica.
//
// ── POR QUE .mjs (porte da leva Tier-0, SPEC US-GOV-052 / P24) ───────────────
// O .ps1 legado SÓ roda no Windows do Wagner; no Mac/Linux do time MCP o enforcement
// evaporava em silêncio — e este é o ÚNICO gate de RUNBOOK desde a ADR 0271. Node é
// cross-plataforma. Nota Linux: o lookup do RUNBOOK é case-insensitive DE PROPÓSITO
// (Windows é case-blind; um RUNBOOK-Foo.md criado no Windows precisa contar no Linux).
// Supersede block-mwart-violation.ps1 (pattern-setter: block-test-fora-ct100.mjs, #4025).
//
// Fail-open: qualquer erro/parse-fail/fs-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/block-mwart-violation.test.mjs
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira a razão pro Claude).

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';

const WRITE_TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);
// Pages/<Mod>/<Tela>.tsx com no máx. 1 subpasta intermediária NÃO-underscore, CAPTURADA
// (Pages/<Mod>/_components/X.tsx NÃO casa → exempt por construção).
const PAGE_REGEX = /resources\/js\/Pages\/([^/_][^/]*)\/(?:([^/_][^/]*)\/)?([A-Za-z][A-Za-z0-9]*)\.tsx$/;
const MOD_EXEMPT = new Set(['_showcase', '_components', '_internal']);
const TELA_EXEMPT = new Set(['App', 'Layout']);

/** PascalCase → kebab-case (NfceStatus → nfce-status; ModuleGrades → module-grades). */
export function toKebab(tela) {
  return String(tela).replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/** extrai {modulo, subdir, tela} se o path é uma tela migrável; null se fora de escopo/exempt.
 *  subdir = a subpasta intermediária (ex: ModuleGrades em <Mod>/ModuleGrades/Index.tsx) ou null. */
export function parsePagePath(filePath) {
  const fwd = String(filePath || '').replace(/\\/g, '/');
  const m = PAGE_REGEX.exec(fwd);
  if (!m) return null;
  const [, modulo, subdir, tela] = m;
  if (MOD_EXEMPT.has(modulo.toLowerCase())) return null;
  if (TELA_EXEMPT.has(tela)) return null;
  return { modulo, subdir: subdir || null, tela };
}

/** lookup case-insensitive: pasta do módulo em memory/requisitos + QUALQUER RUNBOOK-<kebab>.md
 *  dentre os candidatos (kebab da tela E/OU do subdir). Aceita string ou array de kebabs.
 *  Retorna 'ok' | 'sem-pasta' | 'sem-runbook'. fs-fail → 'ok' (fail-open).
 *
 *  `primarioKebab` (opcional) habilita a convenção com PREFIXO DO MÓDULO —
 *  `RUNBOOK-<modulo-kebab>-<primario>.md` — usada de fato no repo (`RUNBOOK-compras-index.md`,
 *  `RUNBOOK-repair-index.md`, `RUNBOOK-repair-show.md`, os 7 `RUNBOOK-produto-*.md`).
 *
 *  POR QUE SÓ O PRIMÁRIO, e não todos os candidatos (medido 2026-08-11, 210 telas do repo):
 *  aplicar o prefixo a TODOS destravava 8 telas, mas 5 eram FALSO-RESGATE — `Repair/Status/Index`,
 *  `Repair/JobSheet/Index`, `Repair/Dashboard/Index` e `Repair/ProducaoOficina/Index` casavam
 *  TODAS o mesmo `RUNBOOK-repair-index.md`, porque `<Mod>/<Sub>/Index.tsx` tem `index` entre os
 *  candidatos. Isso reintroduziria a ambiguidade que o #4648 tratou e viraria carimbo. Restrito
 *  ao primário (subdir p/ aninhada, tela p/ flat) destrava 3, todas flat e inequívocas; 120 das
 *  123 telas seguem bloqueadas. Tela aninhada com nome divergente resolve pelo `runbook:` do
 *  charter — declaração é autoritativa, adivinhação não. */
export function runbookStatus(modulo, kebabCandidatos, root = process.cwd(), primarioKebab = null) {
  try {
    const base = join(root, 'memory', 'requisitos');
    const dirs = readdirSync(base, { withFileTypes: true });
    const modDir = dirs.find((d) => d.isDirectory() && d.name.toLowerCase() === modulo.toLowerCase());
    if (!modDir) return 'sem-pasta';
    const alvos = new Set(
      (Array.isArray(kebabCandidatos) ? kebabCandidatos : [kebabCandidatos])
        .filter(Boolean)
        .map((k) => `runbook-${k}.md`.toLowerCase()),
    );
    if (primarioKebab) alvos.add(`runbook-${toKebab(modulo)}-${primarioKebab}.md`.toLowerCase());
    const files = readdirSync(join(base, modDir.name));
    return files.some((f) => alvos.has(f.toLowerCase())) ? 'ok' : 'sem-runbook';
  } catch {
    return 'ok'; // fail-open — fs indisponível nunca trava a sessão
  }
}

/** extrai o valor do campo `runbook:` (ou `related_runbook:`) da frontmatter YAML do charter.
 *  Aceita as DUAS chaves porque o schema canônico (scripts/memory-schemas/charter.schema.json)
 *  define `related_runbook` — medido 2026-07-27: 7 charters usam a chave do schema e 5 a curta.
 *  Ler só a curta cegava o resgate por proveniência justamente pros charters que seguem o canon
 *  (caso real: Produto/BulkEdit, RUNBOOK existente em _telas/, bloqueado por chave não-lida).
 *  A validação de EXISTÊNCIA do arquivo segue idêntica — isto amplia a leitura, não afrouxa o gate. */
function parseRunbookField(text) {
  const norm = String(text || '').replace(/\r\n/g, '\n');
  const fm = /^---\n([\s\S]*?)\n---/.exec(norm);      // limita à frontmatter, se houver
  const scope = fm ? fm[1] : norm;
  const m = /^[ \t]*(?:related_)?runbook:[ \t]*(.+?)[ \t]*$/m.exec(scope);
  if (!m) return null;
  let v = m[1].replace(/\s+#.*$/, '').trim();          // strip comentário YAML inline (' #...')
  if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) {
    v = v.slice(1, -1);
  }
  return v.trim() || null;
}

/** o charter irmão (<Tela>.charter.md) declara um `runbook:` cujo arquivo EXISTE?
 *  Só RESGATA (true) quando o campo aponta pra um arquivo real. Charter ausente, sem o
 *  campo, ou apontando arquivo fantasma → false (caso-ataque: charter não fura o gate).
 *  Validamos EXISTÊNCIA, não semântica — a confiança termina no charter (lápide 2026-06-30). */
export function charterRunbookExists(filePath, root = process.cwd()) {
  try {
    const fwd = String(filePath || '').replace(/\\/g, '/');
    if (!fwd.endsWith('.tsx')) return false;
    const charterAbs = join(root, fwd.replace(/\.tsx$/, '.charter.md'));
    if (!existsSync(charterAbs)) return false;
    const declared = parseRunbookField(readFileSync(charterAbs, 'utf8'));
    if (!declared) return false;
    return existsSync(join(root, declared.replace(/\\/g, '/')));
  } catch {
    return false; // charter ilegível não RESGATA; o veredito final segue fail-open no wrapper
  }
}

/** veredito único: null (continua) ou a mensagem de bloqueio. */
export function decide(toolName, filePath, root = process.cwd()) {
  if (!WRITE_TOOLS.has(toolName)) return null;
  const page = parsePagePath(filePath);
  if (!page) return null;
  const telaKebab = toKebab(page.tela);
  const subdirKebab = page.subdir ? toKebab(page.subdir) : null;
  const candidatos = subdirKebab ? [telaKebab, subdirKebab] : [telaKebab];
  // canônico da tela aninhada = por rota (subdir); flat = por nome (tela).
  const primaryKebab = subdirKebab || telaKebab;
  const status = runbookStatus(page.modulo, candidatos, root, primaryKebab);
  if (status === 'ok') return null;
  // resgate por proveniência: charter irmão declara um runbook: cujo arquivo EXISTE (validado).
  if (charterRunbookExists(filePath, root)) return null;
  const modKebab = toKebab(page.modulo);
  const runbook = `memory/requisitos/${page.modulo}/RUNBOOK-${primaryKebab}.md`;
  const comPrefixo = `RUNBOOK-${modKebab}-${primaryKebab}.md (com prefixo do módulo)`;
  const alternativas = subdirKebab
    ? `RUNBOOK-${subdirKebab}.md (por rota), ${comPrefixo} ou RUNBOOK-${telaKebab}.md (por nome), nem 'runbook:' válido em ${page.tela}.charter.md`
    : `RUNBOOK-${telaKebab}.md, ${comPrefixo}, nem 'runbook:' válido em ${page.tela}.charter.md`;
  const causa = status === 'sem-pasta'
    ? `A pasta 'memory/requisitos/${page.modulo}/' nem existe — F1 (PLAN) nunca rolou.`
    : `Nenhum candidato encontrado (${alternativas}).`;
  return `[mwart-process] ${toolName} em '${filePath}' BLOQUEADO.
ADR 0104 §F1 PLAN exige RUNBOOK '${runbook}' antes de F3 FRONTEND (codar a Page). ${causa}
Rode '/cockpit-runbook /<rota>' pra gerar RUNBOOK + SPEC (~12min com IA-pair) — é o caminho.
Este bloqueio NÃO tem escape: '/mwart-override' é registro HUMANO no PR (exceção de processo,
vira ADR per-tela) e não destrava este Edit — o hook roda ANTES de existir PR pra comentar.
Desde a ADR 0271 onda 2 este hook runtime é o ÚNICO enforcement de RUNBOOK (mwart-gate CI foi deletado).`;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try { raw = await readStdin(); } catch { process.exit(0); }
  if (!raw) process.exit(0);
  let tool = '';
  let path = '';
  try {
    const payload = JSON.parse(raw);
    tool = String((payload && payload.tool_name) || '');
    path = String((payload && payload.tool_input && payload.tool_input.file_path) || '');
  } catch { process.exit(0); }        // parse-fail → fail-open
  const veto = decide(tool, path);
  if (veto) { process.stderr.write(veto + '\n'); process.exit(2); }
  process.exit(0);
}

// entry-point (pathToFileURL — cross-platform, backslash do Windows não quebra)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    // fileURLToPath (não .pathname) — no Windows .pathname vira '/D:/...' e o node
    // mangleia pra 'D:\D:\...' (MODULE_NOT_FOUND). Cross-plataforma é o propósito do porte.
    const test = fileURLToPath(new URL('./block-mwart-violation.test.mjs', import.meta.url));
    const r = spawnSync(process.execPath, [test], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
