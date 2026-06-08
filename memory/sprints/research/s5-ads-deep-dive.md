---
name: S5 Deep Dive — ADS Universal (L2)
description: Pesquisa estado-da-arte 2026 pra Sprint 5. TRiSM framework, GraphPlanner MDP routing, circuit breaker pattern, Playwright visual regression built-in, pricing real Sonnet/Opus/mini.
type: project
created: 2026-05-06
related_sprint: S5
sources_count: 3
---

# S5 — ADS Universal L2 (deep-dive)

> **Objetivo da pesquisa:** validar nosso ADS (5 domínios + risk scorers + policy matrix +
> budget caps + design Brain B revisor) contra estado-da-arte 2026.

---

## Achado #1 — Framework TRiSM é o consenso enterprise para ADS

[TRiSM for Agentic AI — ScienceDirect 2026](https://www.sciencedirect.com/science/article/pii/S2666651026000069), [arXiv 2506.04133](https://arxiv.org/html/2506.04133v4):

> "Trust, Risk, and Security Management (TRiSM) in LLM-based Agentic Multi-Agent Systems is structured around key pillars: **Explainability, ModelOps, Security, Privacy and Lifecycle Governance**. A risk taxonomy captures unique threats: coordination failures to prompt-based adversarial manipulation."

### Mapeamento TRiSM → nosso ADS

| TRiSM pillar | Nosso ADS já cobre? | Onde |
|---|---|---|
| **Explainability** | 🟡 parcial | `mcp_audit_log` registra mas sem UI legível |
| **ModelOps** | 🟢 sim | router Brain A/B, fallback Ollama |
| **Security** | 🟢 sim (parcial) | publication-policy + Tier 0 ADRs |
| **Privacy** | 🟡 parcial | LGPD redactor pendente (COPI-43) + business_id Tier 0 |
| **Lifecycle Governance** | 🟢 sim | ADRs canon + cycles + retro |

Métricas novas TRiSM:
- **Component Synergy Score (CSS)** — qualidade de colaboração entre agents
- **Tool Utilization Efficacy (TUE)** — eficiência de uso de tools

### Implicação no plano S5

🟢 **Nosso ADS já cobre 4/5 pilares.** Adições recomendadas:
- [ ] **Explainability UI** no Cockpit (S7) — pra cada decisão Brain B, mostrar "por que"
- [ ] **CSS metric** no Cockpit — % decisões em que Brain B + humano concordam
- [ ] **TUE metric** — tools chamadas vs tools úteis (skill_telemetry já coleta parcial)

Adicionar ao plano S5 §6.6 Métricas:
- ✅ atual: 5 domínios, ≥40% PRs auto-aprovados, custo Brain B/dia ≤ $25, 0 escapes
- 🆕 add: Explainability UI, CSS, TUE

---

## Achado #2 — Pricing real 2026 — nossos targets de custo são realistas

[RelayPlane Blog — Agent Runaway Costs 2026](https://relayplane.com/blog/agent-runaway-costs-2026), [Cycles — AI Agent Budget Control](https://runcycles.io/blog/ai-agent-budget-control-enforce-hard-spend-limits):

| Modelo | Input $/1M | Output $/1M | Caso de uso típico |
|---|---|---|---|
| Claude Opus 4.7 | $15 | $75 | Brain B raciocínio profundo |
| Claude Sonnet 4.6 | $3 | $15 | Brain B padrão |
| GPT-4o | $2.50 | $10 | Brain B alternativo |
| GPT-4o-mini | $0.15 | $0.60 | **Brain A barato (atual)** |
| Ollama local | $0 (eletricidade) | $0 | Brain A gratuito (CPU lento) |

### Custo estimado nosso plano (com 10 agents, 30 sessões/dia médio)

**Cenário pessimista (sem ADS):**
- 30 sessões × 50k tokens prompt + 10k output × Sonnet 4.6
- 30 × ($0.15 + $0.15) = **$9/dia** = $270/mês
- Pico (Opus): $2700/mês

**Cenário com ADS funcionando (alvo S5):**
- 60% tarefas auto-aprovadas com Brain A (gpt-4o-mini): 18 × ($0.0075 + $0.006) = $0.24/dia
- 30% precisa Brain B (Sonnet): 9 × $0.30 = $2.70/dia
- 10% humano: $0
- **Total: ~$3/dia** = $90/mês 🎯

🟢 **Alvo do plano S5 ($25/dia) é conservador.** Provavelmente vamos bater $5/dia médio em estado normal.

### Implicação no plano S5

✅ **Manter cap $25/dia como alvo defensivo.** Mas medir realmente — se rodar a $3/dia em soak, abaixar cap pra $10 e usar economia pra investir em Opus em decisões críticas.

---

## Achado #3 — Circuit breaker pattern é canônico pra cost cap

[Fountain City — The Cost Circuit Breaker](https://fountaincity.tech/resources/blog/ai-agent-cost-circuit-breaker/), [Ravoid — AI Agent Budget Enforcement](https://ravoid.com/blog/ai-agent-budget-enforcement):

> "Daily and hourly budget caps allow you to set a dollar ceiling per day. When the budget is hit, systems **return an error to the agent instead of forwarding the request**, and the bill stops growing."
>
> "**Recommended threshold:** multiply average daily spend by 2.5 for warning threshold and by 5 for halt threshold."

### Aplicação ao plano S5

Plano original previa:
- Default: $5/dia/agent Brain B
- Wagner: ilimitado (alerta em $50)

**Refinamento com circuit breaker pattern:**

```php
// Modules/ADS/Services/BudgetCircuitBreaker.php
class BudgetCircuitBreaker {
  public function check(string $agentId): BudgetStatus {
    $spent = $this->getDailySpend($agentId);
    $config = config("ads.budget.{$agentId}", config('ads.budget.default'));

    return match (true) {
      $spent >= $config['halt']    => BudgetStatus::HALT,        // 5x baseline
      $spent >= $config['warning'] => BudgetStatus::WARNING,     // 2.5x baseline
      default                       => BudgetStatus::OK,
    };
  }
}

// config/ads-budget.php
return [
  'default' => [
    'baseline' => 1.00,    // $1/dia esperado
    'warning'  => 2.50,    // alerta em $2.50
    'halt'     => 5.00,    // bloqueia em $5
  ],
  'wagner' => [
    'baseline' => 5.00,
    'warning'  => 25.00,
    'halt'     => 50.00,
  ],
  'opus-autonomous' => [
    'baseline' => 3.00,
    'warning'  => 15.00,
    'halt'     => 50.00,  // ADR 0096? — Wagner aprova
  ],
];
```

Quando HALT atinge:
1. Decision retorna `BLOCK_BUDGET_EXCEEDED`
2. Posta no `mcp_inbox` channel `hitl`
3. Cockpit (S7) mostra flag vermelha
4. Reseta 00:00 BRT

### Implicação no plano S5

🟢 **Refinar §6.5 com circuit breaker pattern explícito.** Pequena mudança — adicionar 3 níveis (baseline/warning/halt) em vez de só "alerta em $50".

---

## Achado #4 — Playwright visual regression é built-in e free

[Playwright Visual Regression Testing — Bug0](https://bug0.com/knowledge-base/playwright-visual-regression-testing), [BrowserStack — Snapshot Testing](https://www.browserstack.com/guide/playwright-snapshot-testing):

> "Playwright generates three images automatically: **expected, actual, and diff**. The diff image highlights exactly which pixels changed. Built-in `maxDiffPixels` and `maxDiffPixelRatio` options allow granular control."

### Implicação no plano S5

Plano original §6.4 previa Brain B revisor lendo "screenshot before/after" — isso pode ser feito via Playwright **sem custo extra**:

```javascript
// tests/visual/repair-listagem.spec.js
test('repair listagem visual paridade', async ({ page }) => {
  await page.goto('/repair/repair?MWART_REPAIR_INDEX=false'); // Blade
  await expect(page).toHaveScreenshot('repair-listagem-blade.png');

  await page.goto('/repair/repair?MWART_REPAIR_INDEX=true');  // Inertia
  await expect(page).toHaveScreenshot('repair-listagem-inertia.png', {
    maxDiffPixelRatio: 0.05  // 5% pixels podem diferir (whitespace, fontes)
  });
});
```

CI gera diff automaticamente. Brain B revisor lê esse diff + charter + lint output.

### Aproach pro plano S5 §6.4

```
Brain B Design Reviewer pipeline:
  1. PR aberto com mudança em .tsx
  2. CI roda: lint + Playwright visual + Pest
  3. Brain B recebe: charter + diff text + 3 screenshots (expected/actual/diff)
  4. Brain B compara contra invariants do charter
  5. Devolve: { verdict, reasoning, score }
  6. Bot comenta no PR
```

🟢 **Não precisa Applitools ($).** Playwright basta no início. Migrar pra Applitools ou Chromatic só se Wagner ver valor.

---

## Achado #5 — Risk scoring deve ser MDP, não score linear

[GraphPlanner ICLR 2026](https://iclr.cc/virtual/2026/poster/10008792):

> "GraphPlanner formulates **workflow generation as a Markov Decision Process (MDP)**, where at each step it selects both the LLM backbone and the agent role (Planner, Executor, Summarizer)."

### Implicação no plano S5

Plano original tinha risk score linear (`tier weight + visual delta + invariants + ...`). Estado-da-arte sugere abordagem MDP — mas isso é overkill para nosso volume.

🟢 **Manter score linear no S5.** É pragmático e auditável. MDP fica como evolução S5.5 ou ADR futura se ADS amadurecer.

Mas adotar UMA ideia do MDP: **multi-step decision**. Hoje plano prevê uma decisão (`decide()` → ALLOW/REQUIRE/BLOCK). Pode evoluir pra:

```
Step 1: decide(domain, intent, payload) → ALLOW_BRAIN_A
Step 2 (após Brain A executar): re-decide com output → ESCALATE/COMMIT
```

Isso permite Brain A propor solução e Brain B revisar antes de commit, em vez de bloquear up-front. Aumenta auto-aprovação.

### Recomendação

🟡 **Adicionar `re-decide` step opcional no S5**, mas só ativar se métricas baseline mostrarem que escalation rate é alto (>30%). Default: decisão única no `decide()`.

---

## Recomendações pro plano S5 (revisões)

### O que manter

- Entry point `decide(domain, intent, payload)`
- 5 RiskScorers (Code/Design/Produto/Memória/Runtime)
- Policy matrix 5×4
- Brain B revisor de design com charter + diff + screenshot
- Skill `ads-route` (sai do dormente)

### O que mudar

| Item | Plano original | Revisão pós deep-dive |
|---|---|---|
| Cost cap | "$5/dia + alerta $50" | **Circuit breaker 3 níveis (baseline/warning 2.5x/halt 5x)** |
| Métricas | 4 (5 domínios, 40% auto, $25/dia, 0 escapes) | **+ CSS, + TUE, + Explainability UI** |
| Visual review | "Brain B lê screenshot" | **Playwright built-in gera diff; Brain B lê texto + diff** |
| Brain A | Ollama prefer + OpenAI fallback | **OpenAI gpt-4o-mini canônico (Ollama 120b inviável); Ollama 20b opcional pra ADS de baixa frequência** |
| Risk scoring | linear | **manter linear; MDP fica como S5.5 evolução** |
| Re-decide | não previsto | **adicionar como opção ativa se escalation rate > 30%** |

### O que adicionar

- [ ] Tabela TRiSM 5 pilares no ADR mãe do ADS
- [ ] Circuit breaker pattern em `BudgetCircuitBreaker` service
- [ ] Métricas CSS + TUE no schema `mcp_ads_decisions`
- [ ] Pipeline Playwright visual regression configurada antes do S5 começar (não esperar)

### O que remover

- ❌ Ollama gpt-oss:120b como Brain A (resultado §11 ROTEIRO)

### Estimativa revisada

Plano original: 7–10 dias.
Pós deep-dive: **8–12 dias** (circuit breaker + Playwright integration + CSS/TUE schema).

---

## Sources

- [TRiSM for Agentic AI — ScienceDirect](https://www.sciencedirect.com/science/article/pii/S2666651026000069)
- [TRiSM Review — arXiv 2506.04133](https://arxiv.org/html/2506.04133v4)
- [GraphPlanner ICLR 2026](https://iclr.cc/virtual/2026/poster/10008792)
- [Why Your Agent Costs Explode — TechAhead](https://www.techaheadcorp.com/blog/how-to-cap-ai-agent-costs/)
- [Agent Runaway Costs — RelayPlane](https://relayplane.com/blog/agent-runaway-costs-2026)
- [AI Agent Budget Enforcement — Ravoid](https://ravoid.com/blog/ai-agent-budget-enforcement)
- [The Cost Circuit Breaker — Fountain City](https://fountaincity.tech/resources/blog/ai-agent-cost-circuit-breaker/)
- [Playwright Visual Regression — Bug0](https://bug0.com/knowledge-base/playwright-visual-regression-testing)
- [Playwright Snapshot Testing — BrowserStack](https://www.browserstack.com/guide/playwright-snapshot-testing)
- [The 2026 Playbook for Reliable Agentic Workflows — Prompt Engineering](https://promptengineering.org/agents-at-work-the-2026-playbook-for-building-reliable-agentic-workflows/)
