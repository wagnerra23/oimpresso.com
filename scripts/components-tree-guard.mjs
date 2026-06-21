#!/usr/bin/env node
// scripts/components-tree-guard.mjs — árvore canônica de Components/ (allowlist + convenção _components)
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Auditoria 2026-06-11 ("quais componentes eu tenho? e como deveria ser o otimizado?"):
// a pasta global `resources/js/Components/` tinha 7 pastas de DOMÍNIO de módulo
// (clientes, ConsultaOs, jana, …) misturadas com as camadas canônicas (ui/, shared/,
// layout/). PR #2539 moveu domínio single-módulo pra `Pages/<Mod>/_components/`
// (convenção já vigente em Sells, CaixaUnificada, ServiceOrders).
//
// ESTE guard impede a doença voltar — lei do projeto (ADR 0240): "derivado + enforcado
// sobrevive / escrito + lembrado apodrece". Sem ele, a próxima feature cria
// `Components/<MeuModulo>/` de novo e a árvore apodrece em 3 meses.
//
// =====================================================================================
// O QUE VALIDA
// =====================================================================================
// CHECK 1 · allowlist do top-level de resources/js/Components/
//   Cada entrada (pasta/arquivo) precisa estar na ALLOWLIST abaixo. Entrada nova =
//   decisão consciente: editar este script no MESMO PR (aparece no diff pro reviewer).
//   Onde criar componente novo:
//     - primitivo visual (input-like, overlay, badge…)        → ui/  (kebab-case + REGISTRY)
//     - composto cross-módulo (≥2 módulos consomem)           → shared/  (PascalCase)
//     - primitivo de layout                                   → layout/  (ADR 0253 — não criar novo aqui sem ADR)
//     - domínio de 1 módulo só                                → resources/js/Pages/<Mod>/_components/
//
// CHECK 2 · convenção `_components` (com underscore) sob Pages/
//   Pasta nova chamada `components` (sem underscore) sob Pages/ falha — o underscore
//   diferencia "componentes locais da tela" de sub-rotas/páginas no import.meta.glob.
//   As 4 pré-existentes estão grandfathered (migram quando a tela for tocada — catraca).
//
// Sem baseline file: a allowlist É o estado canônico (vive no diff, não em JSON).
// Comando local: npm run components:check
//
// Refs: ADR UI-0013 (4 camadas) · ADR 0240 (derivado+enforcado) · ADR 0253 (layout/) ·
//       MANUAL-CSS-JS.md §5 · prototipo-ui/REGISTRY_DS_COMPONENTES.md · PR #2539

import { readdirSync, existsSync } from 'node:fs';
import { resolve, relative, join } from 'node:path';

const ROOT = process.cwd();
const COMPONENTS_DIR = resolve(ROOT, 'resources/js/Components');
const PAGES_DIR = resolve(ROOT, 'resources/js/Pages');

// ── CHECK 1 · allowlist (entrada nova = editar AQUI, no mesmo PR, com justificativa) ──
const ALLOWED_DIRS = new Map([
  // camada 1/2 (UI-0013) — superfícies canônicas
  ['ui', 'primitivos shadcn/Radix/CVA — superfície única de import (REGISTRY_DS_COMPONENTES.md)'],
  ['shared', 'compostos cross-módulo (DataTable, EmptyState, PageFilters, …)'],
  ['layout', 'primitivos de layout Box/Stack/Inline/Grid (ADR 0253)'],
  ['PageHeader', 'PageHeader canon v3.8 (ADR 0189/0190) — alvo da migração F4'],
  ['cockpit', 'Shell — sidebar/cockpit (AppShellV2 consome; SIDEBAR_GROUP_HUE source of truth)'],
  // cross-módulo / surfaces justificados (auditoria 2026-06-11)
  ['board', 'Kanban cross-módulo (OficinaAuto + ProjectMgmt)'],
  ['Site', 'surface pública do site (SiteLayout consome)'],
  ['NfeBrasil', 'domínio fiscal consumido por módulo ≠ do dono (Sells)'],
]);

const ALLOWED_FILES = new Set([
  'CommandPalette.tsx', // ⌘K global (Shell)
  'Icon.tsx', // wrapper lucide canon (UI-0003)
  'MentionInput.tsx', // cross-módulo (chat/comentários)
  'ThemeToggle.tsx', // dark mode por usuário (UI-0004)
]);

// ── CHECK 2 · pastas `components` sem underscore pré-existentes (grandfathered) ──
// Migram pra `_components` quando a tela for tocada — lista NÃO cresce.
const GRANDFATHERED_NO_UNDERSCORE = new Set([
  'resources/js/Pages/Compras/components',
  'resources/js/Pages/Financeiro/Categorias/components',
  'resources/js/Pages/Financeiro/ContasBancarias/components',
  'resources/js/Pages/Jana/components',
]);

const erros = [];

// CHECK 1
if (existsSync(COMPONENTS_DIR)) {
  for (const entry of readdirSync(COMPONENTS_DIR, { withFileTypes: true })) {
    if (entry.isDirectory() && !ALLOWED_DIRS.has(entry.name)) {
      erros.push(
        `🆕 pasta fora da árvore canônica: resources/js/Components/${entry.name}/\n` +
          `     domínio de 1 módulo? → mover pra resources/js/Pages/<Mod>/_components/\n` +
          `     cross-módulo (≥2 módulos)? → shared/ OU adicionar à ALLOWLIST deste script no MESMO PR (decisão consciente, revisável no diff)`,
      );
    } else if (entry.isFile() && !ALLOWED_FILES.has(entry.name)) {
      erros.push(
        `🆕 arquivo solto na raiz de Components/: ${entry.name}\n` +
          `     componente novo nasce em ui/ (primitivo) ou shared/ (composto) — raiz não ganha arquivo novo`,
      );
    }
  }
}

// CHECK 2
function findComponentsDirs(dir) {
  const found = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    if (!entry.isDirectory()) continue;
    const full = join(dir, entry.name);
    if (entry.name === 'components') found.push(relative(ROOT, full).replace(/\\/g, '/'));
    else found.push(...findComponentsDirs(full));
  }
  return found;
}

if (existsSync(PAGES_DIR)) {
  for (const dir of findComponentsDirs(PAGES_DIR)) {
    if (!GRANDFATHERED_NO_UNDERSCORE.has(dir)) {
      erros.push(
        `🆕 pasta 'components' SEM underscore: ${dir}\n` +
          `     convenção canônica é '_components' (diferencia componente local de página no import.meta.glob)`,
      );
    }
  }
}

// ── CHECK 3 · shared/ é FLAT — subpasta dentro de shared/ esconde domínio da
// allowlist do CHECK 1 (caso real: shared/ponto/, achado na auditoria 2026-06-11
// HORAS depois do guard nascer — domínio de 1 módulo um nível abaixo do radar).
// Composto cross-módulo é ARQUIVO direto em shared/; domínio vai pra
// Pages/<Mod>/_components/. Sem grandfather: a única subpasta existente (ponto/)
// foi movida no mesmo PR que criou este check.
const SHARED_DIR = join(COMPONENTS_DIR, 'shared');
if (existsSync(SHARED_DIR)) {
  for (const entry of readdirSync(SHARED_DIR, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      erros.push(
        `🆕 subpasta dentro de shared/: resources/js/Components/shared/${entry.name}/\n` +
          `     shared/ é flat — domínio de 1 módulo vai pra Pages/<Mod>/_components/; composto cross-módulo é arquivo direto em shared/`,
      );
    }
  }
}

if (erros.length) {
  console.error(`❌ components-tree-guard · ${erros.length} violação(ões):\n`);
  for (const e of erros) console.error('  ' + e + '\n');
  console.error('Árvore canônica (UI-0013 camadas → pastas): ver header deste script + .claude/rules/components.md');
  process.exit(1);
}

console.log(
  `✅ components-tree-guard · top-level de Components/ dentro da allowlist (${ALLOWED_DIRS.size} pastas + ${ALLOWED_FILES.size} arquivos) · convenção _components OK · shared/ flat OK.`,
);
process.exit(0);
