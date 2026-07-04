---
slug: guia-do-sistema
title: "Guia do Sistema — mapa do oimpresso + como usar (Claude Code)"
type: guide
authority: canonical
lifecycle: ativo
version: "1.0.0"
maintained_by: wagner
last_updated: "2026-07-04"
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0062-separacao-runtime-hostinger-ct100
pii: false
---

# Guia do Sistema — oimpresso

> **Pra quem:** Wagner (e time). Ponto de entrada humano: entenda o sistema numa página e saiba como operá-lo com o Claude Code.
>
> **Regra de ouro deste doc:** ele é um **mapa que aponta pras fontes vivas** — não copia o detalhe (detalhe copiado apodrece). Se um número/estado aqui divergir da fonte linkada, **a fonte manda**.
>
> **Estado VIVO (cycle, tasks, brief) nunca vem daqui** — vem das tools MCP: `brief-fetch`, `my-work`, `cycles-active`.

---

## PARTE A — O PRODUTO (o que é o oimpresso)

### A1. Em uma frase

ERP brasileiro **multi-tenant**, **modular especializado por vertical**: um **núcleo comum** (multi-tenant + Jana IA + Financeiro + NFe) que serve qualquer PME BR, e **módulos verticais** (`Modules/<Vertical>`) que aprofundam onde há cliente real. Construído sobre UltimatePOS v6. — [why-oimpresso.md](why-oimpresso.md) · [ADR 0121](decisions/0121-oimpresso-modular-especializado-por-vertical.md)

### A2. As camadas (mental model)

```
┌─────────────────────────────────────────────────────────────┐
│  GOVERNANÇA   Constituição v2 · ADRs · Skills · Trust Tiers  │  ← "as leis"
├─────────────────────────────────────────────────────────────┤
│  VERTICAIS    Vestuario ✅ · ComunicacaoVisual 🟡 · OficinaAuto ⏸│  ← produto vendável por setor
├─────────────────────────────────────────────────────────────┤
│  NÚCLEO       Jana IA · Financeiro · NFe/NFSe · Repair(OS) ·  │  ← comum a todos
│               RecurringBilling · PaymentGateway · FSM Pipeline│
├─────────────────────────────────────────────────────────────┤
│  KERNEL       UltimatePOS (Connector, Superadmin) + business_id│  ← base multi-tenant
└─────────────────────────────────────────────────────────────┘
```

Camada de cima **herda** da de baixo e **nunca contradiz**. Detalhe canônico (arc42, 30+ módulos, trust level, runtime C4): **[governance/ARCHITECTURE.md](governance/ARCHITECTURE.md) — comece por aí pra o mapa técnico.**

### A3. Stack canônica (resumo — fonte: [what-oimpresso.md](what-oimpresso.md))

- **Laravel 13.6 + PHP 8.4** · **MySQL** · **Inertia v3 + React 19 + Tailwind 4** · **Pest v4**
- **nWidart Modules** (`Modules/<Nome>/`) — hoje **44 detectados / 36 ativos** ([modulos/INDEX.md](modulos/INDEX.md), auto-gerado)
- **IA:** `laravel/ai` (camada A) + Agents próprios Jana (camada B) + memória Meilisearch (camada C) — [ADR 0035](decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)

### A4. Onde roda (Tier 0 IRREVOGÁVEL — [ADR 0062](decisions/0062-separacao-runtime-hostinger-ct100.md))

| Ambiente | O que roda | ⛔ Nunca |
|---|---|---|
| **Hostinger** (shared) | ERP web + `git pull` de deploy | daemons, `laravel/octane`, `laravel/mcp`, testes pesados |
| **CT 100 Proxmox** (tailscale) | FrankenPHP + Centrifugo + Meilisearch + **MCP server** + Ollama embedder + Vaultwarden + **testes/PHPStan** | — |
| **GitHub `origin/main`** | fonte de verdade do código + `memory/` | — |

Acesso/deploy detalhado: [reference/INFRA-ACESSO-CANON.md](reference/INFRA-ACESSO-CANON.md).

### A5. Verticais — estado (fonte: [why-oimpresso.md](why-oimpresso.md))

| Vertical | CNAE | Status | Cliente piloto |
|---|---|---|---|
| **Vestuario** | 4781-4/00 | ✅ em produção | **ROTA LIVRE** (Larissa, `business_id=4`, 99% do volume) |
| **ComunicacaoVisual** | 1813-0/01 | 🟡 em construção | 6 candidatos OfficeImpresso |
| **OficinaAuto** | 4520-0/01 | ⏸️ aguarda sinal | Martinho (a confirmar) — é **reparo/mecânica**, nunca locação ([ADR 0265](decisions/0265-oficina-reparo-erradica-locacao.md)) |

> ROTA LIVRE não é exceção — é o **caso piloto validado em prod há 2+ anos**. Testes/smoke usam **biz=1** (dogfooding), nunca biz=4 do cliente ([ADR 0101](decisions/0101-tests-business-id-1-nunca-cliente.md)).

### A6. Peças transversais que vale conhecer

- **Jana IA** — copiloto conversacional com memória persistente ([Modules/Jana](../Modules/Jana/), skill `jana-arch`)
- **FSM Pipeline** — toda mudança de estado de Venda/OS passa por `ExecuteStageActionService` ([ADR 0143](decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)); UPDATE direto em `current_stage_id` é bloqueado
- **MCP server** (`mcp.oimpresso.com`) — expõe conhecimento canônico do `memory/` como tools ([ADR 0053](decisions/0053-mcp-server-governanca-como-produto.md))
- **Multi-tenant Tier 0** — `business_id` global scope obrigatório; vazar dado entre tenants é o pior bug possível ([ADR 0093](decisions/0093-multi-tenant-isolation-tier-0.md))

---

## PARTE B — COMO USAR (operar com o Claude Code)

### B1. Protocolo de sessão (o passo-a-passo)

1. **`brief-fetch`** — 1ª coisa, sempre. Estado consolidado (~3k tokens): cycle, tasks, decisões 24h, flags. (skill `brief-first`)
2. **`my-work`** / **`my-inbox`** — suas tasks e notificações
3. **Antes de mexer num módulo** → pré-flight: ler `SPEC.md` + `RUNBOOK*.md` + charter da tela (skill `preflight-modulo`)
4. **Trabalhar** (ler → editar → testar no CT 100)
5. **Commit** conventional + `[W]`/`[F]`/... + `Refs:` (skill `commit-discipline`)
6. **Fechar sessão** → handoff append-only + session log (skill `encerrar-sessao`)

Detalhe: [how-trabalhar.md](how-trabalhar.md).

### B2. Tools MCP — cola de bolso (estado vivo, nunca markdown)

| Pergunta | Tool |
|---|---|
| Estado do projeto (CHAME 1º) | `brief-fetch` |
| O que estou fazendo? | `my-work` |
| Caixa de entrada | `my-inbox` |
| Cycle ativo + goals | `cycles-active` |
| Backlog do módulo X | `tasks-list module:X` |
| Detalhe de uma task | `tasks-detail task_id:...` |
| Qual ADR fala sobre X? | `decisions-search query:"X"` |
| Ler ADR inteira | `decisions-fetch slug:"..."` |
| Fato do negócio sobre Y | `memoria-search query:"Y"` |

Tasks são entidades vivas no MCP (Jira-style), **não** arquivos markdown ([ADR 0070](decisions/0070-jira-style-task-management-current-md-removed.md)).

### B3. Como me pedir as coisas (o que funciona melhor)

- **Pedido vago = eu pergunto antes** de implementar (é regra, não preguiça). Quanto mais concreto o critério de "pronto", menos idas e voltas.
- **Tela nova / mudar tela** → veja [COMO_PEDIR_NOVA_TELA_OU_MODULO.md](COMO_PEDIR_NOVA_TELA_OU_MODULO.md). Design vem do **protótipo Cowork** (`prototipo-ui/`), não de Figma ([ADR 0299](decisions/0299-figma-nao-e-fonte-de-design.md)).
- **Auditar/comparar módulo com o mercado** → `/comparativo <Modulo>` ou agente `capterra-senior`.
- **"Como os melhores fazem X?"** → agente `estado-da-arte`.
- **Fazer em paralelo (N frentes isoladas)** → agente `coordenador-paralelo`.
- **Entender um pedido cru antes de executar** → agente `wagner-understand`.

### B4. As linhas vermelhas (Tier 0 — eu respeito automaticamente)

Fonte completa: [proibicoes.md](proibicoes.md). As que mais te afetam:

- **R1 — Smoke real, não narração.** "Funcionando/deployed" só com evidência (`curl` com status, ou screenshot pós-deploy de UI). Sem prova = não está pronto.
- **R10 — Aprovação humana** antes de `git push` / `pr merge` / deploy. **R11** — dentro de um escopo que você já aprovou, eu vou até o fim sem ficar te cutucando.
- **Regra Mestre de VALOR/ESTOQUE** — toda mudança que toque preço/total/desconto/imposto/estoque exige **dupla conferência com números** + eu te mostro a tabela **antes→depois** antes de aplicar.
- **"Mexeu, registra"** — mudança em módulo/schema/infra vai pro git + testes + docs na hora (nada de "depois eu commito").
- **Multi-tenant** — `business_id` sempre scopado. **Testes** só no CT 100. **PT-BR** em tudo. **Sem valores BRL no git** (só você e a Eliana veem valores; time vê escopo/contagem).

### B5. Governança em 30 segundos

- **Constituição v2** = lei máxima ([ADR 0094](decisions/0094-constituicao-v2-7-camadas-8-principios.md)): 7 camadas + 8 princípios duros. Append-only — muda só via ADR nova com `supersedes`.
- **ADRs** = decisões arquiteturais (`memory/decisions/`, formato Nygard). Índice vivo: [decisions/_INDEX-GENERATED.md](decisions/_INDEX-GENERATED.md).
- **Skills** = automações por contexto (`.claude/skills/`). **Tier A** sempre-on (multi-tenant, commit-discipline, smoke). **Tier B** disparam por path/intenção.
- **Rules path-scoped** (`.claude/rules/`) = instruções que só carregam ao tocar certos arquivos.
- **Saúde:** `php artisan jana:health-check` (5 checks SQL diários).

---

## Backbone operacional — como tudo se conecta

> Como tarefas, backlog, changelog, ciclos e histórico ficam **em máquina e integrados** (auditoria 2026-07-04).

**A fonte é o git; o MCP é cache governado** (nunca o inverso — [ADR 0061](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)):

```
git memory/ (FONTE DE VERDADE)
   │ push → webhook → MCP server ingere → mcp_* tables (cache vivo, ADR 0053, reconcile por ID ADR 0144)
   ├─ tools MCP leem o cache vivo:  tasks-* · cycles-* · decisions-* · sessions-recent
   ├─ ÍNDICES gerados da fonte:  decisions/_INDEX-GENERATED (gated) · requisitos/_BACKLOG-GENERATED (Check W)
   ├─ HISTÓRIA append-only:  handoffs (ADR 0130) + session logs + git log
   └─ AUDITORIA do drift:  memory-health (Checks S–W) → gov-sync propõe → Story (DoD = sentinela zera)
```

| Sistema | Onde vive / máquina |
|---|---|
| **Tarefas / backlog** | US-* nos `SPEC.md` (git canon) → `mcp_tasks` (cache). Índice `_BACKLOG-GENERATED` (gerado). Tools `tasks-*`. |
| **ADRs** | `decisions/*.md` (Nygard) → índice `_INDEX-GENERATED` (gerado + gated). Tool `decisions-search`. |
| **Changelog** | git history + índices gerados + shipped-logs. O `CHANGELOG.md` manual está **congelado** (legado abr/2026). |
| **Ciclos** | `mcp_cycles` (Linear-style, `cycles-*`). **Modo atual: off-cycle** (fluxo contínuo desde CYCLE-08) — reativar é `cycles-create` quando quiser planejar em janelas de 2 semanas. |
| **Histórico** | git (canon) + handoffs append-only + session logs → sincroniza pro MCP (time vê via `sessions-recent`). |

> **Modo off-cycle é intencional** (não um bug): o projeto roda em fluxo contínuo; velocity/burndown por cycle ficam dormentes até um `cycles-create`. O cron mantém o shipped-log do último cycle.

## Navegação — pra ir fundo

| Quero... | Vá pra |
|---|---|
| Mapa técnico do produto (arc42) | [governance/ARCHITECTURE.md](governance/ARCHITECTURE.md) |
| Navegar toda a memória | [INDEX.md](INDEX.md) · [INDEX_TEMATICO.md](INDEX_TEMATICO.md) |
| Regras de sessão / como trabalhar | [how-trabalhar.md](how-trabalhar.md) · [CLAUDE.md](../CLAUDE.md) |
| Linhas vermelhas | [proibicoes.md](proibicoes.md) |
| Time e papéis | [regras-time.md](regras-time.md) · [TEAM.md](../TEAM.md) |
| Responsabilidade de um módulo | `Modules/<X>/SCOPE.md` + `BRIEFING.md` |
| Conectar um dev novo ao MCP | [MEMORY_TEAM_ONBOARDING.md](../MEMORY_TEAM_ONBOARDING.md) |

---

_Guia navegacional — delega o detalhe às fontes vivas. Criado 2026-07-04. Atualizar quando surgir uma nova porta de entrada (não quando um número mudar — isso é responsabilidade da fonte)._
