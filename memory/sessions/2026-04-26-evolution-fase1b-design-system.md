# Sessão 15 — EvolutionAgent Fase 1a+1b + Design System v2 (2026-04-26)

> Continuação da sessão 14 (Copiloto mergeado). Foco: EvolutionAgent fase
> 1a+1b autônomo (4h cap) + setup multi-provider + design system enterprise
> como base do Copiloto UI estilo Claude.

## O que foi feito

### 1. EvolutionAgent Fase 1a+1b → PR #21 (draft)

Branch: `claude/evolution-agent-vizra` (commit `f60289da`)

- **Vizra ADK incompatível com L13** (suporta só ^11|^12) → criei layer
  Vizra-shaped local em `app/Services/Evolution/Agents/{BaseAgent,EvolutionAgent,FinanceiroAgent}.php`. Swap mecânico quando upstream publicar L13.
- **Prism PHP `^0.100`** instalado (multi-LLM, suporta L13).
- **6 migrations `vizra_*`** (agents, messages, traces, memory_chunks, evaluations, eval_runs) — zero FK com tabelas core.
- **6 Eloquent models** em `app/Models/Evolution/`.
- **9 tools** Vizra-compat: `MemoryQuery`, `ListAdrs`, `RankByRoi`, `PestRun`, `RouteList`, `ModelSchema`, `GitDiffStat`, `EvalGoldenSet`, `Extractor` (DeepSeek-V3).
- **2 agents**: `EvolutionAgent` (router) + `FinanceiroAgent` (sub-agent scope='Financeiro'). Os outros 3 (Ponto/Cms/Copiloto) ficaram pra Fase 2.
- **4 artisan commands**: `evolution:query --agent --model=`, `:index`, `:rank`, `:eval`.
- **Pipeline ingest** com chunking H2/H3, idempotente por hash, `scope_module` derivado do path.
- **2 drivers de embedding**: `VoyageEmbeddingDriver` (prod, Voyage-3-lite via Prism) + `HashEmbeddingDriver` (offline determinístico p/ testes e dev sem chave).
- **Golden set** 5 perguntas (`tests/Evolution/golden_set.json`) + `GoldenSetRunner` com judge configurável (Opus 4.5 default, fallback heurística offline).
- **`SchemaGuard`** defende contra deploy parcial (CLAUDE.md histórico de crashes 18/04, 19/04, 21/04).
- **41 Pest tests passing** (128 assertions).

### 2. Multi-provider routing (ADR tech/0004) → mesmo PR #21

- `ProviderRouter` resolve `<slug>:<modelo>` → `[Provider, model]` com aliases
  curtos: `opus`, `sonnet`, `haiku`, `deepseek`, `grok`, `gpt-4o-mini`.
- 4 providers ativos: Anthropic (default), DeepSeek (extração 3× barato),
  OpenAI (vision), xAI (Grok realtime).
- `BaseAgent::withModel()` + `evolution:query --model=`.
- Wagner setou `DEEPSEEK_API_KEY` localmente — recomendei rotacionar
  (foi compartilhada em chat).

### 3. Deploy plan → Issue #22

Plano fatiado pro deploy Hostinger: pré-reqs (PHP 8.4, disco), sequência
(`deploy.yml` workflow_dispatch), API keys manual via SSH, smoke test,
reversão de emergência. Não executei deploy — Wagner faz manual.

### 4. Design System v2 → PR #23 (draft)

Branch: `claude/design-system-enterprise` (commit `cdeec9d7`)

PR 1/4 do plano "Copiloto UI estilo Claude" (cofre:
`memory/claude/ideia_copiloto_ui_estilo_claude.md`).

- **Tipografia Inter** (variable, via `rsms.me`) + 6 níveis hierárquicos
  (display/h1/h2/h3/h4/body/small/caption).
- **Cores semânticas extras** além do shadcn slate: `success`/`warning`/`info`
  + `surface-1`/`surface-2` (camadas pra dashboards).
- **Sombras enterprise** em camadas (xs/sm/md/lg/xl).
- **Utilitários novos**: `tabular-nums`, `shimmer`, `focus-ring`,
  `bg-surface-*`, `bg-success/warning/info`.
- **4 componentes novos** (vs 19 shadcn já existentes): `Kbd`, `Spinner` (acessível),
  `Empty` (estado vazio enterprise), `CodeBlock` (header + copy button —
  syntax highlight via shiki entra no PR 2).
- **Showcase em `/showcase/design-system`** (superadmin only) demonstrando
  tudo em uma página.
- **Prova visual** em `Site/Pricing.tsx` refinado com novos tokens.
- 8 Pest tests passing (42 assertions).
- Screenshots gerados via Playwright (light + dark) salvos em `/tmp/preview-*.png`.

**Erro encontrado e corrigido na sessão**: `EmptyProps` extendia
`HTMLAttributes<HTMLDivElement>` mas redeclarava `title?: ReactNode` quando
nativo é `string` → TS2430. Fix: `Omit<HTMLAttributes, "title">` antes de
redeclarar (commit `cdeec9d7`).

## Decisões registradas

- **`memory/requisitos/EvolutionAgent/adr/tech/0004-roteamento-multi-provider.md`**: 4 providers + escolha por tarefa.
- **`memory/claude/ideia_copiloto_ui_estilo_claude.md`** atualizada com 5 respostas do Wagner: reabrir #13 enterprise, sidebar via `copiloto_conversas`, Pusher/Echo, FAB+página, design system enterprise.

## Estado dos PRs

| PR | Branch | Status | Conteúdo | Próximo passo |
|---|---|---|---|---|
| **#21** | `claude/evolution-agent-vizra` | draft, CI ✅✅ | EvolutionAgent Fase 1a+1b + multi-provider + SchemaGuard | Wagner: marca ready-for-review + merge |
| **#22** | issue (não PR) | open | Tracking deploy Hostinger | Wagner: executa após merge #21 |
| **#23** | `claude/design-system-enterprise` | draft, CI ✅ Vite (Pest pendente do último commit) | Design system v2 (Inter + tokens + 4 componentes + showcase + Pricing) | Wagner: revisar showcase + merge |

## Roadmap restante (cofre)

PRs do plano Copiloto UI (sequenciais, dependem do #23 mergeado):

| # | Branch | Conteúdo | Estimativa |
|---|---|---|---|
| 2 | `claude/copiloto-chat-streaming` | Reabrir scaffold #13; react-markdown + shiki + Pusher streaming; Cmd+Enter; copy code | ~400 linhas |
| 3 | `claude/copiloto-chat-history` | Sidebar `copiloto_conversas`; nova/arquivar | ~300 |
| 4 | `claude/copiloto-fab-global` | FAB layout admin + `/copiloto/chat` full-screen | ~200 |

Roadmap restante EvolutionAgent (Fase 2):
- 3 sub-agents restantes (Ponto/Cms/Copiloto) replicando `FinanceiroAgent`
- GH Action `evolution-eval.yml`
- Sub-agent CC `.claude/agents/evolucao.md`

## Bloqueios desta sessão

- **Sandbox bloqueia `oimpresso.com`** ("Host not in allowlist") + `~/.ssh/`
  vazio → não pude SSH/deploy direto. Plano de deploy ficou registrado na
  issue #22 pra Wagner executar.
- **`npm install` produziu 6 vulnerabilidades** (2 mod, 4 high) — não corrigi
  (`audit fix` pode quebrar deps). Anotado pra próxima sessão.

## Como retomar (próxima sessão)

1. `git fetch origin` + checa estado dos PRs #21 e #23 (mergeados? CI?).
2. Se mergeados → executar deploy via issue #22.
3. Se #23 mergeado mas #21 não → começar PR #2 do Copiloto UI.
4. Se houver erro reportado pelo Wagner → primeiro `npx tsc --noEmit` (achei
   um TS2430 oculto na showcase v2).

## Comandos prontos pra próxima sessão

```bash
# Checa estado
gh pr view 21 --json state,mergeable
gh pr view 23 --json state,mergeable

# Recriar local após pull
composer install
npm install
npm run build:inertia
php artisan migrate
php artisan evolution:index
./vendor/bin/pest --filter "Evolution|DesignSystem"
```

---

**Última atualização**: 2026-04-26, fim da sessão 15
