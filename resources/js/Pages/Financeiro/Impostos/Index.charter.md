---
page: /financeiro/impostos
component: resources/js/Pages/Financeiro/Impostos/Index.tsx
owner: wagner
status: live
last_validated: "2026-06-10"
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-INVENTARIO.md
related_adrs: [93, 94]
related_us: [US-FIN-062]
smoke: "2026-07-02 — render prod OK biz=1 (Chrome MCP, sessão WR2 Sistemas; Inertia page servida com sessão real). Antes: 2026-07-01 render completo (KPIs/guias/calendário) sob sessão matriz biz=0."
related_prototype: prototipo-ui/cowork/financeiro-telas-extras.jsx (TelaImpostos), aprovado [W] 2026-06-10 (PACOTE-FINANCEIRO-F2 PR-2)
tier: B
charter_version: 1
---

# Page Charter — /financeiro/impostos

> **Status:** F1 entregue (PACOTE-FINANCEIRO-F2 PR-2, [W] "aprovado" 2026-06-10).
> Persona: **Eliana [E]** — financeiro escritório; secundária Larissa [L] (dona, quer saber "quanto de imposto vou pagar?").
> Origem: protótipo Cowork `TelaImpostos` (financeiro-telas-extras.jsx). Censo Fiscal 2026-06-09
> validado @main 2026-06-10: "impostos a recolher + calendário" não existia em nenhum módulo.

---

## Mission (1 frase)

Responder **"quanto de imposto vou recolher e quando vence?"** numa tela só — estimativa
Simples Nacional (regime caixa) costurada ao caixa unificado — sem fingir ser apuração oficial.

---

## Goals — Features (faz)

- **3 KPIs**: A recolher (soma das guias abertas) · Próxima obrigação (menor vencimento aberto) · % receita com NF no mês.
- **Tabela de guias**: DAS Simples estimado **≈6% sobre o RECEBIDO do mês (regime caixa**, espelha o card Recebido do kpisCore: baixas sem estorno, exclui cancelados, valor real juros+multa−desconto) + histórico de guias já lançadas como título payable (FGTS · DCTFWeb/INSS · DAS, match por descritivo, últimos 6 meses). Status: a vencer / paga / atrasada.
- **"Lançar a pagar"**: cria título payable no Unificado (costura do protótipo). Valor **recalculado server-side** (anti tampering) · **idempotente** por `metadata.guia = "das-YYYY-MM"` · numero `P-NNNNN` business-isolado com `lockForUpdate` (R-FIN-002) · dispara `TituloCriado`.
- **Calendário de obrigações**: lista datada das guias abertas + lembrete do fechamento mensal.
- **Painel NF↔título**: recebíveis do mês sem NF vinculada (`metadata.nfe_numero/nfe_chave`) — sem NF a base do DAS sai distorcida; aviso pré-fechamento.
- **Disclaimer fixo**: "estimativa — apuração oficial no módulo Fiscal" (sempre visível no rodapé).
- Sub-tela do Financeiro: ghost `impostos` no hub (DataController) + `FinanceiroSubNav active="impostos"`.
- Multi-tenant Tier 0 (ADR 0093): `business_id` da session em todas as queries.

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD quando virar dor.

- ❌ **Apuração oficial do Simples** (anexos I-V, fator R, sublimites, partilha de tributos) — módulo Fiscal.
- ❌ **Emissão de guia** (PGDAS-D, DARF, GPS) — módulo Fiscal / contador.
- ❌ **Folha de pagamento** (FGTS/INSS calculados) — sistema não tem folha; guias de folha só aparecem se lançadas manualmente como título payable.
- ❌ Outros regimes (Lucro Presumido/Real) — F1 é Simples Nacional ≈6% fixo.
- ❌ Editar/cancelar guia nesta tela — título criado é gerido no Unificado (cancelar lá).
- ❌ Persistência própria de guias (zero tabela nova) — tudo derivado de `fin_titulos`/`fin_titulo_baixas`.

---

## UX Targets

- Cabe em 1280px (Larissa) sem scroll horizontal; coluna lateral colapsa pra 1 col <1100px.
- AppShellV2 + wrapper `.fin-cowork` (vocabulário visual do módulo, mesmo padrão do DRE).
- 0 erros JS console; zero cor crua (status pills via tokens semânticos do @theme).

---

## Automation Hooks

- GET `/financeiro/impostos` → `ImpostosController::index` (read-only).
- POST `/financeiro/impostos/lancar` → `ImpostosController::lancar` (cria Titulo payable; idempotente; `TituloCriado` event).
- Permission: `financeiro.dashboard.view` (mesma do Unificado).

---

## UX Anti-patterns

- ❌ **Apresentar estimativa como apuração** — disclaimer fixo é obrigatório ([W]: "não declarar feito o que é stub").
- ❌ Confiar em valor vindo do client no lançamento — servidor recalcula.
- ❌ Inventar guia de folha sem dado real — só DAS é derivado; folha entra via título manual.

---

## Automation Anti-hooks

- ❌ Agente NÃO cria guia/título automaticamente sem clique humano em "Lançar a pagar" (decisão fiscal é humana).
- ❌ Agente NÃO altera a alíquota ≈6% sem ADR (mudança de regime = decisão [W] + contador).
- ❌ Agente NÃO remove o disclaimer de estimativa.

---

## Métricas vivas

- Guias DAS lançadas via tela (count títulos com `metadata.guia` like `das-%`) — costura sendo usada?
- % receita com NF no fechamento do mês — proxy da consistência da base do DAS.
- Visitas à tela no mês (se telemetria de rota existir) — valida a hipótese "Larissa quer saber quanto paga".
