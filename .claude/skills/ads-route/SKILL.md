---
name: ads-route
description: Use ANTES de qualquer mudança custosa (chamada Brain B Sonnet/Opus, deploy prod, mudança Tier 0). Roteia decisão por decide(domain,intent,payload) que faz triagem custo/risco. Tier A DORMENTE — ativa quando S5 entregar ADS Universal (~jul/2026).
tier: A
resumo: roteia mudança custosa via `decide(domain,intent,payload)` — ativa quando S5 entregar ADS Universal
tier_enforce: decide-firewall
parent_adr: 0095
related_adrs: [0094]
enabled: false
ativacao: S5 (jul/2026 — após ADS Universal deployed em Modules/ADS/)
---

# ads-route — Tier A DORMENTE (ativa em S5)

> ⚠️ **DORMENTE até S5** — `enabled: false` no frontmatter. Quando S5 entregar `decide()` em `Modules/ADS/Services/`, esta skill vira `enabled: true`.

## Quando ativar (futuro pós-S5)

ANTES de qualquer **decisão custosa**:
- Chamar Brain B (Sonnet/Opus) quando Brain A (gpt-4o-mini) bastaria
- Mudança em Tier 0 (token MCP, schema mcp_audit_log, ADR canon)
- Deploy em produção fora de janela
- Mudança em billing/auth/dados de cliente

## Por que Tier A

**Princípio duro #2 da Constituição v2** (Tiered cost).

Sem firewall, agents tendem a usar Brain B sempre. Com 10 agents × 30 sessões/dia × Sonnet:
- ~$40-80/dia desnecessários
- Sem trilha de auditoria do "por quê desta decisão"
- Sem fallback se Sonnet down

`decide(domain, intent, payload)` resolve:
- **Domain** = `code` | `design` | `produto` | `memoria` | `runtime`
- **Intent** = ação curta (ex: "edit-listing-tsx", "rebuild-payment-form")
- **Payload** = contexto (tier do arquivo, charter invariants, autor)

Retorna 1 de 4 outcomes:
- `ALLOW_BRAIN_A` — barato OK
- `REQUIRE_BRAIN_B` — risco médio, Sonnet revisa
- `REQUIRE_HUMAN_REVIEW` — Wagner aprova manualmente
- `BLOCK_ALWAYS` — Tier 0 sem ADR mãe → recusar

## Estrutura proposta (S5)

```php
// Modules/ADS/Services/Decide.php
class Decide
{
    public function __invoke(string $domain, string $intent, array $payload): Decision
    {
        $score = $this->scorers[$domain]->score($payload);  // 0-30
        $tier = $payload['tier'] ?? 3;  // 0=tier 0, 3=cosmético
        $level = $score >= 26 ? 'CRIT' : ($score >= 16 ? 'HIGH' : ($score >= 6 ? 'MED' : 'LOW'));

        return $this->policyMatrix[$domain][$tier][$level];  // ALLOW_BRAIN_A | ...
    }
}
```

Policy matrix (S5 §6.3):
```
              | LOW(0-5) | MED(6-15) | HIGH(16-25) | CRIT(26-30)
DESIGN tier 0 | BLOCK    | BLOCK     | BLOCK       | BLOCK
DESIGN tier 1 | BRAIN_B  | BRAIN_B   | HUMAN       | HUMAN
DESIGN tier 2 | BRAIN_A  | BRAIN_B   | BRAIN_B     | HUMAN
DESIGN tier 3 | BRAIN_A  | BRAIN_A   | BRAIN_B     | HUMAN
PRODUTO       | BRAIN_A  | BRAIN_B   | HUMAN       | HUMAN
MEMORIA       | BRAIN_A  | BRAIN_B   | HUMAN       | HUMAN
RUNTIME       | BRAIN_A  | HUMAN     | HUMAN       | HUMAN
CODE          | (ADR específica) | ... | ... | ...
```

## Estado dormente — não dispara nada

Enquanto `enabled: false`:
- Skill carrega no system prompt (custo: ~80 tokens)
- Description "DORMENTE" indica pra Claude não acionar
- Code paths atuais (ChatCopilotoAgent, etc) chamam LLM diretamente sem Decide
- Sem regressão — estado anterior preservado

## Critério ativação (S5 entregue)

- [ ] `Modules/ADS/Services/Decide.php` deployed
- [ ] 5 RiskScorers (Code/Design/Produto/Memoria/Runtime)
- [ ] Policy matrix em `config/ads-policy.php`
- [ ] Brain A em Ollama local (CT 100) ou OpenAI gpt-4o-mini
- [ ] Brain B revisor de design (charter + diff + screenshot)
- [ ] Budget circuit breaker 3 níveis (baseline / warning 2.5× / halt 5×)
- [ ] Mudar `enabled: false` → `true` no frontmatter
- [ ] ADR específica documentando ativação

## Referências

- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — princípio duro #2 Tiered cost
- [ADR 0095](../../../memory/decisions/0095-skills-tiers-convencao-interna.md) — convenção tiers
- [memory/sprints/research/s5-ads-deep-dive.md](../../../memory/sprints/research/s5-ads-deep-dive.md) — TRiSM 5 pilares + circuit breaker
