# Protótipo F1 — financeiro-fluxo

**Status:** 🟡 WAITING_FOR_BACKEND — recomendado pra atacar 1º.
**Aprovado por:** [W] 2026-05-09 (Cowork)
**Stories:** US-FIN-014 (fluxo-caixa-projetado)

> ⚠️ **Antes de começar F3:** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight checklist obrigatório (T-AP-1 a T-AP-15) + 6 meta-anti-padrões + convenções pt-BR. Sem ele, mesmo erro do batch original repete.

## Por que está bloqueado

`FluxoController.php` do Cowork era stub 100% mock (`rand(0, 1500)`) **sem tenant scope nem middleware**. Mergear violaria Tier 0 multi-tenant ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

## Por que essa é a 1ª recomendada

Não precisa de tabela nova. Backend usa modelos que já existem:

- `Titulo` (a receber/a pagar) com `vencimento` em [hoje, hoje+35]
- `TituloBaixa` (passado) com `data_baixa` em [hoje-2, hoje-1]
- `ContaBancaria.saldo_cached` (somado pra tenant)

Service novo: `Modules/Financeiro/Services/FluxoCaixaService::projetar(businessId, dias=35)`. Sem migration, sem tabela.

## Próximo passo (loop F1.5 → F3)

1. Decisões abertas (Wagner responde):
   - Saldo hoje = soma de `ContaBancaria.saldo_cached` ativas? (provável sim)
   - Período 35d fixo ou parametrizável?
   - Margem mínima R$ 5k → config.tenant a posteriori, hardcode 5000 por enquanto?
   - Histórico = -2 dias arbitrário ou virar "últimas baixas relevantes"?

2. F1.5 critique-score (pode ser `/design-override` se Wagner achou OK no Cowork direto)

3. F3 Code: visual-comparison.md → Service → Controller real → Pest → .tsx → charter → routes → sidebar
