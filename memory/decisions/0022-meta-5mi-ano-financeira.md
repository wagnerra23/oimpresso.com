# ADR 0022 — Meta financeira oimpresso: R$ 5 milhões/ano

**Data:** 2026-04-24
**Status:** Aceita
**Autor:** Wagner (dono/operador)
**Registrado por:** Claude (sessão 2026-04-24)

---

## Contexto

A oimpresso.com tem hoje 56 businesses cadastrados, dos quais **7 com vendas registradas**, com **ROTA LIVRE (biz=4) concentrando 99% do volume** (ver `memory/claude/reference_clientes_ativos.md`). A base instalada ainda é essencialmente 1 cliente forte + alguns satélites, apesar do produto ter breadth (UltimatePOS base + módulos Ponto, Essentials/HRM, Grow, MemCofre, Officeimpresso).

O roadmap atual (`memory/07-roadmap.md`) é **centrado em produto** (Fases 1–14, foco em PontoWr2 + stack React/Inertia + observabilidade) mas **não tem meta de negócio quantificada**. Isso deixa decisões de priorização sem âncora numérica: qual módulo liberar primeiro? Quais clientes perseguir? Quanto investir em aquisição × em retenção?

Wagner estabeleceu a meta em 2026-04-24: **R$ 5 milhões de faturamento anual** na oimpresso (equivalente a ~R$ 417k/mês de receita recorrente, se SaaS puro).

## Decisão

A meta oficial da oimpresso passa a ser **R$ 5 milhões/ano de faturamento**, com horizonte-alvo a definir (**dado pendente — Wagner precisa confirmar prazo: 12 / 24 / 36 meses?**).

Essa meta serve como **filtro de priorização** para:

1. **Roadmap de produto** — features que geram receita direta ou reduzem churn têm precedência sobre refactors e polimentos.
2. **Alocação de tempo** — trabalho em módulos com ROI pouco claro (ex.: `AiAssistance`, já marcado pra descartar) é cortado.
3. **Venda/CS** — frente comercial precisa ser dimensionada (hoje Wagner opera solo).

## Racional (premissas assumidas — validar com Wagner)

- **"Financeira"** foi interpretada como "meta financeira do negócio" = faturamento bruto anual da oimpresso. Se for outra coisa (ex.: linha de módulo "financeiro" do produto, como fluxo de caixa / contas a pagar), este ADR precisa de correção.
- **Receita é prioritariamente SaaS recorrente** (mensalidade UltimatePOS + módulos), não serviços pontuais. Se consultoria/setup entra na conta, o mix muda o plano.
- **ROTA LIVRE permanece cliente âncora** — não há plano de substituí-lo, mas há plano de **reduzir a dependência** (hoje 99%) crescendo os outros.

## Consequências

**Positivas:**
- Qualquer proposta de feature passa a precisar responder "como isso aproxima da meta?".
- Cria base para decompor em metas trimestrais e acompanhar em `memory/11-metas-negocio.md` (documento vivo).
- Orienta escolhas de investimento em aquisição vs. retenção.

**Negativas/Custos:**
- Requer disciplina de **medir faturamento mensal** — hoje não há dashboard interno de MRR/faturamento consolidado. Precisa ser criado.
- Pode pressionar a descontinuar/parar de investir em módulos que são "legais" mas não vendem.
- Se a meta for agressiva demais para o prazo, cria ilusão de progresso falso.

## Dados que precisam ser levantados para virar plano executável

1. **Faturamento atual** (12 últimos meses) — base para calcular gap real.
2. **Ticket médio por cliente ativo** e por módulo.
3. **Churn histórico** dos 7 clientes ativos.
4. **Prazo-alvo** da meta (12/24/36 meses).
5. **Custo de aquisição** (marketing, comercial, setup) — hoje provavelmente R$ 0 porque Wagner traz cliente por rede pessoal.
6. **Capacidade de atendimento** — quantos clientes novos/mês Wagner consegue operar sozinho sem degradar serviço.

Esses dados alimentam `memory/11-metas-negocio.md`.

## Relação com outras ADRs

- **ADR 0016** (Plano de Otimização e Roadmap PontoWR2) — foco técnico; este ADR 0022 dá a ele um "porquê comercial".
- **Preferência `preference_modulos_prioridade.md`** (auto-memória) — Grow é prioridade / AiAssistance descartável. Consistente com filtro "o que vende vs. o que não vende".

## Módulo operacional: Copiloto

A gestão desta meta (e de todas as metas de businesses) é instrumentada pelo **Módulo Copiloto** — ver [`memory/requisitos/Copiloto/README.md`](../requisitos/Copiloto/README.md). Este ADR é a **origem conceitual** do Copiloto: a meta R$ 5mi serve como primeiro registro seed do módulo (`business_id = null`, origem `seed`).

---

**Última atualização:** 2026-04-24
