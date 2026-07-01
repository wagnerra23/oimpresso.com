---
title: "RUNBOOK — JANA Pro modo Concierge MVP"
module: Jana
owner: W
status: ativo
last_validated: '2026-05-11'
related_adrs:
  - 0140-jana-pro-produto-comercial-saas
  - 0141-agents-tool-use-pattern-claude-code
---

# RUNBOOK — JANA Pro modo Concierge MVP

> **Movido de `requisitos/Copiloto/` → `requisitos/Jana/`** em 2026-07-01 (fecha o loop do rename Copiloto→Jana, [ADR 0088]; follow-up do PR #3559). Dir canônico do módulo agora é `Jana/`.
> **Estado:** ATIVO até Wagner juntar caixa pra ligar provider LLM (Groq grátis ou Anthropic API pago).
> **Data:** 2026-05-11
> **Decisão:** Caminho B do trade-off em [ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md) — Wagner é o agente, Claude Code é a ferramenta dev pessoal.

---

## Quando usar este RUNBOOK

Toda manhã (~8h BRT) ou sob demanda quando cliente JANA Pro pedir brief. Foundation técnica (BriefDiarioService + BriefDiarioAgent + 5 Tools) já tá em prod **dormente** — só falta provider LLM ligado.

Até lá, **tu (Wagner) é o LLM** com ajuda do Claude Code na tua Max.

---

## Pré-requisitos (uma vez só)

- [x] PR #597 merged — `BriefDiarioService` 5 sources em prod
- [x] PR #600 merged — `BriefDiarioAgent` foundation (dormente)
- [x] Endpoint `/copiloto/admin/jana-pro/preview?business_id=N` em prod
- [x] Skill `.claude/skills/jana-brief-concierge/` ativada (esta PR)
- [ ] Lista de clientes JANA Pro pagantes (até hoje: nenhum — pré-MVP)

---

## Playbook diário (passo a passo)

### Passo 1 — Buscar snapshot do cliente

No browser/curl, autenticado como superadmin:

```
https://oimpresso.com/copiloto/admin/jana-pro/preview?business_id=4
```

Substitui `business_id=N` pelo cliente do dia. ROTA LIVRE = 4. Outros clientes verão seus próprios sem trocar URL (Tier 0 garante).

Saída: JSON com 5 sources (`vendas`, `inadimplencia`, `tickets`, `nfe`, `oportunidades`).

### Passo 2 — Colar no Claude Code

Abre tua sessão Claude Code local (Max subscription — uso pessoal, dentro do ToS).

Cola o JSON completo no chat:

```
{cola aqui o JSON do passo 1}
```

A skill **jana-brief-concierge** ativa automático (Tier B auto-trigger por padrão JSON). Eu detecto, gero o brief markdown seguindo as mesmas instructions do `BriefDiarioAgent::instructions()`.

### Passo 3 — Revisar narrativa

Output meu vai ter ~250-400 palavras estruturado:

```markdown
## ☀️ Bom dia, [nome]!
### 📊 Vendas
### 🚨 Alertas
### 💡 Oportunidades
### ✅ Ação sugerida hoje
```

**Revisa**: ainda é tu o gestor final. Verifica se:
- Nomes batem (caso JSON tenha contato com nome estranho)
- Tom serve pro cliente (Larissa = direto; outro cliente pode preferir formal)
- Ações sugeridas fazem sentido contextual (skill não sabe se cliente tá de férias hoje)

### Passo 4 — Enviar manual

**WhatsApp (preferido):**
- Abre conversa do cliente
- Cola o markdown direto — WhatsApp renderiza `## ☀️` como negrito visual decente

**Email (alternativo):**
- Markdown → HTML via qualquer ferramenta (ex: pandoc, ou colar em editor markdown WYSIWYG)
- Subject sugerido: "Bom dia [Nome] — Brief diário [data]"

### Passo 5 — Marcar como entregue (tracking manual)

Por enquanto, anota numa planilha simples ou MCP task `tasks-comment` numa US "JANA Pro briefs entregues":

```
2026-05-12 08:42 — biz=4 ROTA LIVRE — brief enviado WhatsApp — Larissa leu 09:01
```

Quando ligar provider automatizado, isso vira tabela `mcp_briefs` (Sprint A US-COPI-204).

---

## SLA Concierge MVP

| Plano | Cliente paga | SLA brief | Quem gera |
|---|---|---|---|
| Free | R$ [redacted Tier 0] | "quando der" | Não recebe — só preview self-service |
| Pro | R$ [redacted Tier 0] | 1 brief/dia 8h-10h BRT | Wagner+Claude Code manual |
| Enterprise | R$ [redacted Tier 0] | 1 brief/dia 8h-9h BRT + chat ad-hoc | Wagner manual |

**Capacidade real:** Wagner consegue ~5-10 briefs/dia em ~30min total (5-6min por cliente). Limite = bottleneck Wagner.

**Migrar pra automatizado quando:**
- ≥5 clientes pagantes Pro OU ≥1 Enterprise (R$ [redacted Tier 0]+ MRR)
- Tempo Wagner virou > 60min/dia (custo oportunidade > custo API)
- Cliente reportar atraso/falha (sinal forte)

---

## Roteiro de migração pra modo automatizado (caminho A)

Quando bater trigger acima, sequência:

1. **Gerar `GROQ_API_KEY` grátis** (console.groq.com → 30s)
2. **Adicionar no `.env` Hostinger + local** — `GROQ_API_KEY=gsk_...`
3. **Patch `BriefDiarioAgent.php`** — adicionar `#[Provider('groq')] #[Model('llama-3.3-70b-versatile')]` na classe
4. **Smoke test local** — `php artisan tinker --execute="echo (new Modules\Jana\Ai\Agents\BriefDiarioAgent(4))->prompt('Gere brief diário de hoje.')"` — confirma narrativa não-vazia
5. **Sprint A US-COPI-203** — `BriefDiarioJob` schedule Horizon CT 100 8h BRT
6. **Sprint A US-COPI-204** — Persistir em `mcp_briefs` + namespace memória `analises.brief_diario`

Tempo total estimado: ~4-8h IA-pair quando Wagner sinalizar.

---

## Anti-patterns (NÃO fazer no modo Concierge)

- ❌ **Inventar dado** que não tá no JSON pra "deixar brief mais rico" — fere princípio anti-fabricação
- ❌ **Enviar brief padrão genérico** quando snapshot vier vazio — melhor mandar "sem movimento hoje" do que invenção
- ❌ **Misturar clientes** — sempre busca um `business_id` por vez, nunca compara biz=4 com biz=7 no mesmo brief
- ❌ **Esquecer de revisar** — eu (Claude) sou skill, não responsável final pela mensagem que o cliente recebe
- ❌ **Cobrar do cliente sem entregar** — modo Concierge tem limite humano, melhor pausar inscrições novas se chegar no limite que entregar com atraso

---

## Métricas de saúde do modo Concierge

Acompanhar manualmente (planilha ou MCP comments):

| Métrica | Target |
|---|---|
| Briefs entregues no SLA (8h-10h BRT) | ≥95% |
| Tempo médio Wagner gerar 1 brief | ≤6min |
| Tempo total Wagner/dia em briefs | ≤30min |
| Clientes que reclamaram atraso | 0 |
| Briefs com dado inventado detectados | 0 |

Se qualquer métrica falhar, é trigger pra migrar pra modo automatizado.

---

## Custo do modo Concierge

| Item | Custo mensal |
|---|---|
| Claude Code Max | R$ [redacted Tier 0] (já pago Wagner pessoal) |
| Tempo Wagner (30min/dia × 22 dias) | 11h/mês — custo oportunidade |
| Infra extra | R$ [redacted Tier 0] (endpoint preview já em prod) |
| **Total adicional** | **R$ [redacted Tier 0]** |

Receita por 1 cliente Pro pagante: R$ [redacted Tier 0] — **margem ~100%** até bater limite humano.

---

## Referências

- [ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md) — JANA Pro produto SaaS
- [ADR 0141](../../decisions/0141-agents-tool-use-pattern-claude-code.md) — Pattern Claude Code Camada B v2
- [`.claude/skills/jana-brief-concierge/SKILL.md`](../../../.claude/skills/jana-brief-concierge/SKILL.md) — skill Tier B auto-trigger
- [`JANA-PRO-PRODUCT-PLAN.md`](JANA-PRO-PRODUCT-PLAN.md) — roadmap 4 sprints
- `Modules/Jana/Services/BriefDiarioService.php` — gera snapshot
- `Modules/Jana/Ai/Agents/BriefDiarioAgent.php` — versão automatizada dormente
