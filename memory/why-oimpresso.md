# Por que o oimpresso existe

ERP gráfico brasileiro pra setor de **comunicação visual** (gráficas rápidas, plotters, fachadas, brindes). Construído sobre UltimatePOS v6 com módulos próprios em `Modules/` (Jana IA, Financeiro, MemCofre, NfeBrasil, RecurringBilling, etc).

## Origem
Originalmente nasceu como módulo Ponto WR2 (controle eletrônico Portaria MTP 671/2021) e evoluiu pra plataforma vertical completa.

## Cliente piloto
**ROTA LIVRE** (`business_id=4`, Larissa) — 99% do volume de vendas. Histórico de quirks documentado em auto-memória do agente. Monitor 1280px. Customizações ativas: `format_date` shift +3h ([ADR 0066](decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md) — preservado intencionalmente).

## Posicionamento
Capterra-inventoried em todos módulos críticos. Diferencial vs concorrentes (Iugu/Asaas/Vindi):
- **NFe automática a partir de boleto pago** (US-RB-044 entregue)
- **Copiloto IA Jana** com memória persistente + recall hybrid Meilisearch
- **Governança formal** (Constituição v2 — ADR 0094) — 36% das enterprises não têm

## Meta financeira
**R$ 5 milhões/ano** ([ADR 0022](decisions/0022-meta-5mi-ano-financeira.md)). Usa stack canônica IA econômica (gpt-4o-mini Brain A) pra controlar CAC.

## Cliente externo separado
`Eliana(WR2)` (cliente externa, eliana@wr2.com.br) ≠ `Eliana[E]` (esposa Wagner, time interno). Sempre desambiguar em commits/notas.

## Cliente principal Copiloto
**ROTA LIVRE** — Larissa pergunta sobre faturamento/metas e recebe resposta correta usando dados reais (CYCLE-01 goal validado em prod).
