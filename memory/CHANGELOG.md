# Changelog do Projeto oimpresso.com

Registro cronológico de mudanças estruturais, decisões arquiteturais e operações
de manutenção. Diferente dos session logs (`sessions/YYYY-MM-DD-*.md`) que contam
"o que foi feito em cada sessão", este arquivo lista **eventos notáveis** de forma
enxuta e pesquisável.

Formato inspirado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Datas no formato `YYYY-MM-DD`. Categorias:

- **Added** — nova funcionalidade / módulo / tela
- **Changed** — mudança no comportamento existente
- **Deprecated** — marcado para remoção futura
- **Removed** — removido do codebase
- **Fixed** — bug corrigido
- **Security** — correção de segurança
- **Migration** — mudança de branch, versão, infra ou fluxo de dev
- **Decision** — decisão arquitetural (geralmente casado com ADR)

---

## [Unreleased] — branch `6.7-react`

### Added — 2026-04-22
- **Shell React** (`resources/js/Layouts/AppShell.tsx`) — sidebar 2 níveis (col 1 módulos + col 2 sub-páginas do módulo ativo), topbar com search, footer com user + theme toggle
- **Dark mode por usuário** — coluna `users.ui_theme` + anti-flash blade + hook React `useTheme`
- **LegacyMenuAdapter** — shell lê menu dinâmico do nwidart/laravel-menus; todos os módulos aparecem automaticamente
- **Gerenciador de Módulos** (`/modulos`) — tela React shadcn substituindo `/manage-modules` (AdminLTE quebrado). Switch por módulo + install/uninstall com preservação de tabelas
- **ModuleSpecGenerator** — scanner PHP que inspeciona 29 módulos e gera spec markdown por módulo em `memory/modulos/`. Inclui rotas, controllers, entities, migrations, permissões, jobs, events, config, cross-deps, foreign keys, hooks UltimatePOS, assets JS/CSS e diffs vs branches antigas
- **Artisan command** `module:specs` — gera ou regenera as specs
- **Pages/Ponto/Welcome.tsx** + **Pages/Ponto/Relatorios/Index.tsx** — primeiras telas React reais do módulo PontoWR2, dentro do AppShell

### Changed — 2026-04-22
- **`modules_statuses.json`** reorganizado (ordem alfabética, ativos/inativos explícitos)
- **Rota `/manage-modules`** → redireciona automaticamente para `/modulos` via LegacyMenuAdapter

### Removed — 2026-04-22
- 🗑️ **`Modules/Officeimpresso1/`** apagado — bug: mesmo namespace `Modules\Officeimpresso\Providers\OfficeimpressoServiceProvider` do módulo Officeimpresso, causava colisão
- 🗑️ Código Vue (Example.vue + passport/*.vue) — scaffold não montado em blade nenhum
- 🗑️ `daisyui 3` + `tailwindcss-motion` + `tailwindcss@3.4.17` — migramos para Tailwind 4 no pipeline Inertia
- 🗑️ `tailwind.config.js` + `postcss.config.cjs` — artefatos TW3 não usados mais

### Deprecated — 2026-04-22 (desativados em `modules_statuses.json` — aguardam decisão de apagar ou reativar)
- **`AiAssistance`** — Wagner: "não útil". Overlap com plano IA-first via OpenAI direto
- **`Writebot`** — outro módulo de IA legado. Overlap com AiAssistance e plano novo
- **`IProduction`** — overlap com Grow (prioridade produção). Investigar antes de apagar
- **`Officeimpresso`** — licenciamento desktop legado, já coberto por Superadmin + Connector
- **`Grow`** — Wagner: prioridade produção mas precisa avaliar viabilidade (957 views CodeCanyon)

### Migration — 2026-04-22
- Branch atual: **`6.7-react`** criada a partir de `6.7-bootstrap`
- Backup `main-wip-2026-04-22` preservado (6484 arquivos do snapshot WIP local do Wagner)

### Fixed — 2026-04-22
- **Officeimpresso DataController** — `action()` para método `generateQr` falhava porque a rota estava comentada; isolado via `if(false)` temporário. Menu do shell voltou a renderizar (antes derrubava sidebar inteira)
- **Deprecations PHP 8.4** — suprimidas via `public/.user.ini` + `public/index.php` (deprecations do Laravel 9 contra PHP 8.4, ruído sem impacto funcional)

### Decision — 2026-04-22
- **Stack UI Inertia + React 19 + shadcn/ui + Tailwind 4** (= Laravel 12/13 Starter Kit) — escolhido como alvo de migração ao longo de 10 milestones (ver `memory/07-roadmap.md` Fase 13 e user memory `project_roadmap_milestones.md`)
- **Módulos perdidos 3.7 → 6.7** (BI, Boleto, Chat, Dashboard, Fiscal, Help, Jana, Knowledgebase, codecanyon-ticketing) — mapeados em `memory/modulos/RECOMENDACOES.md`. 4 para restaurar via pacotes padrão (F15), 5 para descartar, 3 a investigar
- **Tema por usuário** (coluna `users.ui_theme`) — mais escalável que por empresa; cada usuário escolhe independentemente. Fallback: preferência do SO

---

## Anterior a 2026-04-22

Histórico compactado — detalhe em `memory/sessions/*.md` e `memory/08-handoff.md`.

### 2026-04-21 (sessão 09 — upgrade stack + PontoWR2)
- UltimatePOS atualizado para v6.7 (Laravel 9.51 + PHP 8.3)
- Restrição PHP 7.1 revogada; toda sintaxe PHP 8.x liberada
- `PontoWr2` adaptado para L9 (factories class-based, HasFactory trait, removido `Factory::class`)
- Branch `6.7-bootstrap` criada no GitHub

### 2026-04-18 a 2026-04-20 (sessões 01-08)
- Scaffolding completo do módulo PontoWR2
- Refactor para padrão `Modules/Jana` (ADR 0011)
- Regras CLT implementadas (Art. 58, 66, 71, 59 via `ApuracaoService`)
- Hash chain SHA-256 imutabilidade (Portaria 671/2021)
- AFD parser funcional
- Banco de horas FIFO
- 14 telas migradas de Tailwind → AdminLTE
- 9 testes unitários

---

## Como atualizar este changelog

1. A cada mudança estrutural: adicione entrada sob `[Unreleased]` na categoria certa
2. Ao marcar um marco (milestone, release, deploy prod), crie seção `[vX.Y.Z] — YYYY-MM-DD` acima de `[Unreleased]` e mova os itens para lá
3. Use linguagem **ativa** ("Apagado X", "Adicionado Y") e sempre cite arquivo/path
4. Para decisões grandes, crie também um ADR em `memory/decisions/NNNN-slug.md` e referencie aqui
