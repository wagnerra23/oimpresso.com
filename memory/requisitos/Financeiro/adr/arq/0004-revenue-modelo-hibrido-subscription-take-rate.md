# ADR ARQ-0004 (Financeiro) · Revenue híbrido: subscription + take rate sobre boleto/PIX

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq (decisão de produto/precificação)
- **Relacionado**: ARQ-0003, `memory/requisitos/_Roadmap_Faturamento.md`

## Contexto

oimpresso hoje cobra **subscription puro** (R$ 49/mês plano POS). Para Financeiro virar receita maior, 3 modelos foram considerados:

1. **Subscription puro mais caro** (ex: Pro R$ 199/mês)
   - Pró: previsível, sem complexidade fiscal
   - Contra: tenant pequeno (5 vendas/mês) paga igual ao grande (5k vendas/mês). Adoção ruim no topo.
2. **Take rate puro** (ex: 1% sobre cada boleto pago, sem subscription)
   - Pró: cliente paga só quando usa
   - Contra: receita imprevisível, ruim pra cobrir custo fixo de manutenção (adapters bancários quebram constantemente)
3. **Híbrido** — subscription cobre o "ter o módulo" + take rate cobre o "uso intenso"

Concorrentes BR (Conta Azul, Tiny, Bling) usam **híbrido**: assinatura mensal + fee por boleto/NFe além de quota. É o padrão do mercado.

## Decisão

Modelo **híbrido**:

| Plano | Mensalidade | Quota incluída | Fee adicional |
|---|---|---|---|
| **Free** | R$ 0 | 50 títulos/mês, sem boleto/PIX | n/a |
| **Pro** | R$ 199/mês | 500 títulos/mês + 50 boletos | 0,5% capped R$ 9,90 por boleto pago além |
| **Enterprise** | R$ 599/mês | ilimitado + CNAB direto | 0% take rate (já paga premium) |

**Take rate escopo:** só boletos/PIX emitidos via `GatewayStrategy` (oimpresso intermedia o gateway). Em `CnabDirectStrategy`, oimpresso não toca dinheiro → sem take rate (cliente já paga ao banco).

## Consequências

**Positivas:**
- Tenant pequeno: free dá acesso, vira upgrade quando crescer
- Tenant médio: Pro R$ 199 + ~30 boletos extras (R$ 0 a R$ 297) = R$ 199-496/mês
- Tenant grande: Enterprise R$ 599 fixo (previsível) + sem fee → atrai quem fatura R$ 100k+/mês
- Receita escala com sucesso do cliente (fica melhor quando ele cresce)
- Cobre custo de manutenção de adapter bancário (que quebra toda semana)
- **Win-win:** se Asaas baixa fee de R$ 1,99 → R$ 1,49, oimpresso mantém 0,5% e fica com mais margem
- Take rate cap (R$ 9,90) impede cobrança abusiva em boleto de R$ 5k+ (cliente VIP)

**Negativas:**
- Complexidade de billing: precisa medidor de "boletos pagos no mês" + fechamento mensal
- Compliance fiscal: oimpresso recebendo take rate vira "intermediador de pagamento"? Decisão pendente (ver "Decisões em aberto")
- Tenant pode reclamar de surprise bill se ultrapassar quota (mitigar: alerta +80% via e-mail/in-app)
- Risco de sub-precificação se Asaas reajustar fee

## Mecânica operacional

1. **Medidor**: `fin_revenue_events` registra cada boleto pago via Gateway. `(business_id, mes_competencia, titulo_id, boleto_remessa_id, fee_calculado)` UNIQUE.
2. **Fechamento mensal**: job dia 1, próximo mês, soma todos os events do mês anterior, gera `superadmin_invoices.financeiro_take_rate_amount`.
3. **Cobrança**: junto da mensalidade Superadmin (mesmo gateway de subscription do oimpresso).
4. **Transparência**: tenant vê `/admin/financeiro/billing` com gráfico mês-a-mês + fee corrente.

## Decisões em aberto

- [ ] **Aspecto fiscal:** oimpresso emite NFSe pelo take rate? Provavelmente sim (serviço de software/intermediação) — confirmar com contador. Se sim, integra com NfeBrasil pra emissão automática.
- [ ] **Split com tenant?** Tenant pode receber 0,1% de cada boleto seu (incentivo a usar nosso gateway vs CNAB direto)? Decisão de marketing.
- [ ] **Tier intermediário** R$ 349 com 1.000 títulos + 200 boletos? Análise de funnel após 3 meses live.
- [ ] **Free plan limit 50** mata adoção? Talvez Free = 100 com boleto/PIX bloqueado (mais permissivo).
- [ ] **Lifetime deal** pra primeiros 100 enterprises (R$ 9.990 1x = 16 meses)? Decisão de growth.

## Alternativas consideradas

- **Subscription puro caro** — rejeitado: perde clientes pequenos e enterprise reclama (paga igual)
- **Take rate puro** — rejeitado: receita não cobre custo de manutenção
- **Per-seat pricing** — rejeitado: Larissa-financeiro é 1 user; modelo per-seat só faz sentido em RH grande
- **Anual com desconto** — *adicionado em paralelo* — anual paga 10x (2 meses grátis), pago à vista, melhora cash flow

## Referências

- `_Roadmap_Faturamento.md` (a criar) — projeção 24 meses
- `ARQ-0003 (Financeiro)` — strategy define quando aplica take rate
- Conta Azul pricing 2026 — benchmark
- Asaas/Iugu fee structure (R$ 1,99/boleto pago) — input pro cap take rate
