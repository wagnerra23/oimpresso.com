---
module: ADS
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
na_justified:
  D6.b: "ADS é meta-sistema dormente (ARQ-0011 aguardando S5 ~jul/2026 — ver ADR 0105 cliente como sinal qualificado). p99 <500ms via OTel N/A enquanto módulo não está em prod ativa — sem tráfego pra medir."
  D6.c: "ADS Brain A roda no CT 100 (Node.js daemon), Brain B é Anthropic API. Hostinger expõe APENAS POST /api/ads/route (síncrono ~50ms). Não há queries paginate/eager-load com risco N+1 — ADS é firewall de decisão, não CRUD."
  D9.b: "ADS pre-S5 não tem jobs assíncronos Horizon — Brain B chamadas via cron `ads:process-brain-b` artisan direto. failed_jobs N/A enquanto pipeline não ativa (ADR 0105 dormant)."
related_adrs: [0105-cliente-como-sinal-guiar-sem-mandar, 0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado]
---
<!-- schema-allowlist: ADS é meta-sistema dormente sem backlog de US (ARQ-0001..0011 são ADRs de arquitetura, não user stories) — aguarda S5 ~jul/2026 (ADR 0105 cliente como sinal). Sem backlog ativo até ativação. -->

# ADS — Adaptive Decision System

> Módulo Laravel: `Modules/ADS/` (a criar)
> ADRs: `memory/requisitos/ADS/adr/arq/`

## O que é

Meta-sistema que orquestra todos os agentes do oimpresso. Não executa código diretamente.
Recebe eventos, decide qual agente age, com qual autoridade, e retroalimenta o sistema.

## ADRs canônicos

| # | Slug | Status |
|---|---|---|
| ARQ-0001 | [ads-escopo-e-papel-unico](adr/arq/ARQ-0001-ads-escopo-e-papel-unico.md) | accepted |
| ARQ-0002 | [dual-brain-papeis](adr/arq/ARQ-0002-dual-brain-papeis.md) | accepted |
| ARQ-0003 | [decision-router-algoritmo](adr/arq/ARQ-0003-decision-router-algoritmo.md) | accepted |
| ARQ-0004 | [risk-engine-formula-e-priors](adr/arq/ARQ-0004-risk-engine-formula-e-priors.md) | accepted |
| ARQ-0005 | [confidence-engine](adr/arq/ARQ-0005-confidence-engine.md) | accepted |
| ARQ-0006 | [policy-engine-firewall](adr/arq/ARQ-0006-policy-engine-firewall.md) | accepted |
| ARQ-0007 | [learning-loop-tres-niveis](adr/arq/ARQ-0007-learning-loop-tres-niveis.md) | accepted |
| ARQ-0008 | [hitl-quatro-niveis](adr/arq/ARQ-0008-hitl-quatro-niveis.md) | accepted |
| ARQ-0009 | [decision-memory-schema](adr/arq/ARQ-0009-decision-memory-schema.md) | accepted |
| ARQ-0010 | [governance-conflito-hierarquia](adr/arq/ARQ-0010-governance-conflito-hierarquia.md) | accepted |
| ARQ-0011 | [topologia-deployment](adr/arq/ARQ-0011-topologia-deployment.md) | accepted |

## Módulos clientes do ADS

O ADS é agnóstico de domínio. Estes módulos submetem eventos a ele:

| Módulo | Papel no ADS |
|---|---|
| `Modules/Jana/` | MCP bus compartilhado; Jana Chat NÃO submete eventos ao ADS |
| `EvolutionAgent/` | Submete eventos de oportunidade de evolução de codebase |
| `Brain A daemon` | Submete eventos de monitoramento (git, logs, métricas) |
| `TaskRegistry/` | Recebe tasks criadas pelo ADS; não submete eventos |

## Topologia de deployment (ARQ-0011)

| Ambiente | Componentes |
|---|---|
| **Hostinger** (app web) | Modules/ADS/ completo · 5 tabelas mcp_dual_brain_* · UI /ads/admin/decisoes · POST /api/ads/route · GET /api/ads/recent-{commits,errors} · cron `ads:process-brain-b` |
| **CT 100 Proxmox** | Brain A daemon (Node.js, systemd) · Ollama qwen2.5-coder:14b · OllamaClient → localhost:11434 · watchers HTTP poll Hostinger |
| **Anthropic API** | Brain B (Sonnet/Opus) — chamado pelo cron Hostinger |

## Stack técnico

| Componente | Tecnologia |
|---|---|
| Brain A | Node.js daemon + Ollama HTTP API |
| Brain B | `BrainBService.php` + `laravel/ai` + claude-sonnet-4-6 |
| Policy Engine | `PolicyEngine.php` — código PHP puro, sem DB |
| Risk Engine | `RiskEngine.php` — código PHP puro |
| Confidence Engine | `ConfidenceEngine.php` + tabela `mcp_confidence_scores` |
| Decision Router | `DecisionRouter.php` + tabela `mcp_file_locks` |
| Decision Memory | Tabela `mcp_dual_brain_decisions` |
| Learning Loop L1/L2 | Laravel Observer + Command semanal |
| Learning Loop L3 | Command mensal usando Brain B |
