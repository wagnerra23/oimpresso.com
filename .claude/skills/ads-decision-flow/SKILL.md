---
name: ads-decision-flow
description: Use ao trabalhar em Modules/ADS/ ou tocar fluxo de decisão automatizada (Risk → Confidence → Policy → Router → Brain A/B → HITL). Carrega o contrato Dual-Brain canônico, 4 outcomes do PolicyEngine (ALLOW_BRAIN_A / REQUIRE_BRAIN_B / REQUIRE_HUMAN_REVIEW / BLOCK_ALWAYS), 4 níveis HITL, 4 Agents (BrainB/Planner/ProjectDecomposer/Reviewer), 7 Tools internos, e onde cada peça vive (Hostinger app vs CT 100 daemon). Substitui leitura repetida de ARQ-0001..0011.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
tier: B
parent_adr: 0095
---

# Adaptive Decision System — fluxo canônico

## Quando ativa

- Edit/Write em qualquer arquivo dentro de `Modules/ADS/`
- Pedido com termos: "ADS", "decision router", "dual brain", "Brain A", "Brain B", "policy engine", "risk engine", "HITL", "learning loop"
- Adicionar tabela `mcp_dual_brain_*` ou `mcp_file_locks` ou `mcp_confidence_scores`
- Cron `ads:process-brain-b` ou rota `POST /api/ads/route`

## Fonte canônica completa

[`memory/requisitos/ADS/SPEC.md`](memory/requisitos/ADS/SPEC.md) — 11 ADRs em [`memory/requisitos/ADS/adr/arq/`](memory/requisitos/ADS/adr/arq/).

## §Extensão administrativa (ADR 0145 — 2026-05-15)

ADS deixou de ser "router só de atendimento WhatsApp" e passou a ser **router universal de ações administrativas auditáveis**. Pivot formalizado em [ADR 0145](memory/decisions/0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md).

- **Bridge único pro executor:** toda ação administrativa aprovada (Brain A / Brain B / HITL) chama `Modules/ADS/Services/FsmActionBridge` (US-ADS-070, em construção) que invoca `app/Domain/Fsm/Services/ExecuteStageActionService` ([ADR 0143](memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)). NÃO criar atalhos. NÃO bypassar trait `GuardsFsmTransitions`.
- **Audit Card visível ao cliente final = Tier 0** quando há decisão automatizada que afete cliente final (LGPD Art. 20 / ANPD NT 12/2025). Toda `RoutingDecision` com `client_visible=true` exige URL `/copiloto/decisoes/{id}/revisao` funcional + rodapé "decisão automatizada — revisar humanamente" na mensagem disparada.
- **HITL extendido pra blast radius administrativo:** valor <R$ 50 = L0; R$ 50-R$ 500 = L1; >R$ 500 OR VIP OR primeira cobrança mês = L2; bloqueio/cancelamento = L3. Per-agent declarado em `config/ads.php`.

### §Modelo comercial (decisão Wagner 2026-05-15)

**Agentes administrativos são feature PAGA — add-on monetizado, não vêm no plano básico.** Cliente paga pra ter Cobradora/Auditora/etc rodando. ROTA LIVRE durante piloto = free (validação). Pra outros biz, sinal qualificado = cliente paga (alinha com [ADR 0105](memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

Implicações práticas:
- Flag de feature por biz (`businesses.cobradora_ativa boolean` ou equivalente billing) controla ligamento.
- Sem cliente pagante, agent fica desligado mesmo que código esteja deployado.
- Brain B custo (R$ ~150/mês orçado) é OPEX que entra no preço do add-on.

### §Dry-run total enquanto Wagner não confia (decisão 2026-05-15)

Default da config canary US-COPI-086: `COBRADORA_DRY_RUN_BIZ_4=true`. Agent decide MAS não dispatcha — Wagner revisa decisões em dashboard `/copiloto/admin/cobradora`. Larissa NÃO recebe mensagem real até Wagner virar flag `false` explicitamente. Wagner ainda não comunicou Larissa do piloto — comunicação acontece DEPOIS que ele confiar nas decisões em dry-run.

## Pipeline de decisão (não pular nenhum estágio)

```
Evento → RiskEngine → ConfidenceEngine → PolicyEngine → DecisionRouter → Brain A | Brain B | Wagner
                                              │
                                              └── PolicyEngine VENCE sempre (firewall hardcoded)
```

| Estágio | Service | Tabela / artefato |
|---|---|---|
| 1. Risk | [`RiskEngine.php`](Modules/ADS/Services/RiskEngine.php) → [`RiskResult`](Modules/ADS/Services/RiskResult.php) | PHP puro, sem DB (ARQ-0004) |
| 2. Confidence | [`ConfidenceEngine.php`](Modules/ADS/Services/ConfidenceEngine.php) | `mcp_confidence_scores` (ARQ-0005) |
| 3. Policy (firewall) | [`PolicyEngine.php`](Modules/ADS/Services/PolicyEngine.php) → [`PolicyResult`](Modules/ADS/Services/PolicyResult.php) | PHP puro, sem DB (ARQ-0006) |
| 4. Router | [`DecisionRouter.php`](Modules/ADS/Services/DecisionRouter.php) → [`RoutingDecision`](Modules/ADS/Services/RoutingDecision.php) ([`RoutingInput`](Modules/ADS/Services/RoutingInput.php)) | `mcp_file_locks` (ARQ-0003) |
| 5. Memória | (todos os passos persistem) | `mcp_dual_brain_decisions` (ARQ-0009) |

## §Crítico — 4 outcomes do PolicyEngine (ARQ-0006)

PolicyEngine é **firewall**, não recomendação. Ordem de precedência:

1. **`BLOCK_ALWAYS`** — nunca executa automaticamente, nem com confiança 1.0. Sem override.
2. **`REQUIRE_HUMAN_REVIEW`** — sempre cria task pendente Wagner, mesmo se Brain B aprovou.
3. **`REQUIRE_BRAIN_B`** — Brain A não toca; obrigatório Brain B + instrução detalhada.
4. **`ALLOW_BRAIN_A`** — Brain A executa sem revisão se confiança > 0.7.

**Regra de ouro:** Policy vence Confidence vence Risk. Se conflitar, [ARQ-0010](memory/requisitos/ADS/adr/arq/ARQ-0010-governance-conflito-hierarquia.md) define hierarquia.

## §Crítico — Dual Brain (ARQ-0002)

| | Brain A (System 1) | Brain B (System 2) |
|---|---|---|
| Onde roda | **CT 100 Proxmox** — Node.js daemon | **Hostinger** — `BrainBService.php` via `laravel/ai` |
| Modelo | Ollama `qwen2.5-coder:14b` (localhost:11434) | `claude-sonnet-4-6` (Anthropic API) |
| Custo | $0 (24/7) | $$ (acionado on-demand) |
| Erro típico | Falso negativo (confia em padrão conhecido) | Falso positivo (escala demais) |
| Pode escalar pra cima? | ✅ Sim — chama Brain B se threshold | ❌ Nunca devolve pra Brain A — entrega ou cria task Wagner |

**Meta:** 80% eventos resolvidos por Brain A a $0; Brain B só nos ~20% que importam.

## 4 Agents internos (`Modules/ADS/Ai/Agents/`)

| Agent | Service correspondente | Quando entra |
|---|---|---|
| [`BrainBAgent`](Modules/ADS/Ai/Agents/BrainBAgent.php) | [`BrainBService.php`](Modules/ADS/Services/BrainBService.php) | Policy = REQUIRE_BRAIN_B; ou Brain A escalou |
| [`PlannerAgent`](Modules/ADS/Ai/Agents/PlannerAgent.php) | [`PlannerService.php`](Modules/ADS/Services/PlannerService.php) | Decompor task pendente em plano executável |
| [`ProjectDecomposerAgent`](Modules/ADS/Ai/Agents/ProjectDecomposerAgent.php) | [`ProjectDecomposerService.php`](Modules/ADS/Services/ProjectDecomposerService.php) | SPEC novo → quebrar em US-XXX-NNN |
| [`ReviewerAgent`](Modules/ADS/Ai/Agents/ReviewerAgent.php) | [`ReviewerService.php`](Modules/ADS/Services/ReviewerService.php) | Code review adversarial pré-merge |

## 7 Tools (`Modules/ADS/Tools/`) — capacidades dos Agents

| Tool | Uso |
|---|---|
| [`GitInspectTool`](Modules/ADS/Tools/GitInspectTool.php) | Read-only: log, blame, diff |
| [`GitCommitWipTool`](Modules/ADS/Tools/GitCommitWipTool.php) | Commit incremental WIP (não push) |
| [`LogReaderTool`](Modules/ADS/Tools/LogReaderTool.php) | Tail de `storage/logs/*` |
| [`MetricsQueryTool`](Modules/ADS/Tools/MetricsQueryTool.php) | Query em `copiloto_memoria_metricas` + observabilidade |
| [`RunTestTool`](Modules/ADS/Tools/RunTestTool.php) | `vendor/bin/pest --filter=...` (sandbox) |
| [`WriteFileTool`](Modules/ADS/Tools/WriteFileTool.php) | Sujeito a Policy (BLOCK_ALWAYS bloqueia paths sensíveis) |
| [`BoostToolAdapter`](Modules/ADS/Tools/BoostToolAdapter.php) | Bridge pra tools do laravel/boost |

Toolset registrado em [`ToolRegistry.php`](Modules/ADS/Services/ToolRegistry.php).

## §Crítico — 4 níveis HITL (ARQ-0008)

Intervenção humana graduada por intensidade — não é binário "humano sim/não":

| Nível | Quando | Wagner age |
|---|---|---|
| **L0** Auto | Policy = ALLOW_BRAIN_A + confidence > 0.7 | Não age (audita posteriormente) |
| **L1** Notify | Brain B corrigiu algo de baixo impacto | Vê notificação; não bloqueia |
| **L2** Approve | Brain B prepara resumo + riscos + recomendação | Clica aprovar/rejeitar antes de executar |
| **L3** Co-create | Meta Learning L3 sugere mudança estrutural | Wagner discute, edita, decide |

## Topologia deployment (ARQ-0011)

| Ambiente | O que vive lá |
|---|---|
| **Hostinger** (app web) | Modules/ADS/ inteiro · 5 tabelas `mcp_dual_brain_*` · UI `/ads/admin/decisoes` · `POST /api/ads/route` · `GET /api/ads/recent-{commits,errors}` · cron `ads:process-brain-b` |
| **CT 100 Proxmox** | Brain A daemon (Node.js + systemd) · Ollama `qwen2.5-coder:14b` localhost:11434 · watchers HTTP poll Hostinger |
| **Anthropic API** | Brain B (`claude-sonnet-4-6`) — chamado pelo cron Hostinger |

**Regra de runtime (ver skill `runtime-rules-hostinger-ct100`):** nunca instalar `laravel/octane`/`laravel/mcp` no Hostinger; nunca rodar Brain A daemon fora do CT 100.

## Learning Loop 3 níveis (ARQ-0007)

| Nível | Frequência | Mecanismo |
|---|---|---|
| L1 (priors) | Real-time | Laravel Observer atualiza counters em `mcp_confidence_scores` |
| L2 (patterns) | Semanal | Command analisa decisões agregadas | [`PatternLearningService.php`](Modules/ADS/Services/PatternLearningService.php) |
| L3 (meta) | Mensal | Command usando Brain B propõe mudança estrutural (gera ADR draft) |

## Pegadinhas críticas

- ❌ **NÃO chamar Brain B direto sem passar pelo PolicyEngine** — fura o firewall. Sempre `RoutingInput` → `DecisionRouter::route()`.
- ❌ **NÃO escrever em arquivo via Brain A se path está em `BLOCK_ALWAYS`** (`.env`, `composer.lock`, migrations já rodadas, `app/Http/Middleware/Authenticate.php`, etc.). [`PolicyEngine::BLOCK_ALWAYS`](Modules/ADS/Services/PolicyEngine.php) é a lista canônica.
- ❌ **NÃO tentar instalar Ollama no Hostinger.** Brain A só vive no CT 100. Hostinger faz HTTP poll via watchers.
- ❌ **NÃO criar branch nova em produção pra "testar" decisão automatizada.** Use worktree e limpe depois.
- ❌ **NÃO suprimir `mcp_dual_brain_decisions` mesmo em retry** — append-only. Decisão errada vira evidência pra L1/L2 ajustar prior.
- ❌ **Brain B nunca devolve pra Brain A.** Se Brain B foi acionado, ele entrega instrução completa OU cria task pendente Wagner. Devolver pra Brain A = bug.

## Multi-tenant (skill `multi-tenant-patterns`)

ADS é **operacional/governança**, não tem `business_id` no nível de decisão (decide sobre código, não sobre dados de cliente). Mas:
- Quando Brain B propõe mudança em código que toca tabelas com `business_id`, Reviewer **deve** verificar que ScopeByBusiness não foi removido.
- Tasks geradas pelo ADS pra `mcp_tasks` herdam `business_id` da feature originária se aplicável.

## Validação local antes de comitar mudança em ADS

```bash
# 1. Lint dos services
php -l Modules/ADS/Services/PolicyEngine.php
php -l Modules/ADS/Services/DecisionRouter.php

# 2. Testes do módulo
vendor/bin/pest Modules/ADS/Tests/

# 3. Ver decisões recentes (se ambiente local com seed)
php artisan tinker --execute="dump(\DB::table('mcp_dual_brain_decisions')->latest()->limit(5)->get())"
```

## Refs canônicas

- [SPEC ADS](memory/requisitos/ADS/SPEC.md)
- [ARQ-0001 — Escopo e papel único](memory/requisitos/ADS/adr/arq/ARQ-0001-ads-escopo-e-papel-unico.md)
- [ARQ-0002 — Dual Brain papéis](memory/requisitos/ADS/adr/arq/ARQ-0002-dual-brain-papeis.md)
- [ARQ-0003 — Decision Router algoritmo](memory/requisitos/ADS/adr/arq/ARQ-0003-decision-router-algoritmo.md)
- [ARQ-0004 — Risk Engine fórmula](memory/requisitos/ADS/adr/arq/ARQ-0004-risk-engine-formula-e-priors.md)
- [ARQ-0005 — Confidence Engine](memory/requisitos/ADS/adr/arq/ARQ-0005-confidence-engine.md)
- [ARQ-0006 — Policy Engine firewall](memory/requisitos/ADS/adr/arq/ARQ-0006-policy-engine-firewall.md)
- [ARQ-0007 — Learning Loop 3 níveis](memory/requisitos/ADS/adr/arq/ARQ-0007-learning-loop-tres-niveis.md)
- [ARQ-0008 — HITL 4 níveis](memory/requisitos/ADS/adr/arq/ARQ-0008-hitl-quatro-niveis.md)
- [ARQ-0009 — Decision Memory schema](memory/requisitos/ADS/adr/arq/ARQ-0009-decision-memory-schema.md)
- [ARQ-0010 — Governance hierarquia](memory/requisitos/ADS/adr/arq/ARQ-0010-governance-conflito-hierarquia.md)
- [ARQ-0011 — Topologia deployment](memory/requisitos/ADS/adr/arq/ARQ-0011-topologia-deployment.md)
- [Runbook deploy](memory/requisitos/ADS/RUNBOOK-deploy-producao.md)
