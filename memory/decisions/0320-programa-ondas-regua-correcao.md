---
slug: 0320-programa-ondas-regua-correcao
number: 320
title: "Programa de Ondas — ciclo-padrão adversário→régua por módulo + régua estendida (casos_coverage + D1 cálculo, PLUGAR não fundir) + piso Tier-0 (cálculo+caso+paridade)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-02"
accepted_at: "2026-07-02"
accepted_via: "Wagner 2026-07-02 no chat: 'aprovado merge' (mesmo rito de aceite da ADR 0304). Origem: sessão 2026-07-02 (PLANO-MESTRE programa-ondas + onda-0a). Diagnóstico: módulos de nota alta escondem cálculo de valor indefeso (a camada do incidente num_uf, valor inflado ~×100k). Redação [CC]; placement canônico/renumeração final sob soberania [CL] (ADR 0238) — proposta aceita mantida em proposals/ como os demais aceitos."
module: governance
quarter: 2026-Q3
tags: [governance, ondas, adversario, regua, casos, calculo, tier-0, catraca, ratchet, anti-paralelo, paridade, migracao]
supersedes: []
supersedes_partially:
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
  - "0264-governanca-executavel-trio-dominio-e2e"
superseded_by: []
related:
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0298-teto-de-governanca-anti-proliferacao-gates
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0250-screen-qa-specialist-sustentavel
  - 0319-product-truth-stream-adversario-modulo-analise
pii: false
---

# ADR 0320 — Programa de Ondas: régua de correção por módulo (emenda a 0256 + 0264)

> **STATUS: ACEITO — Wagner 2026-07-02 ("aprovado merge").** Este foi o PORTÃO (Onda 0a): o mecanismo do programa está travado. As etapas seguintes (0b extensão da régua · 0c sentinela de cadência · 0d paridade de migração · Onda 1 Sells) ficam **liberadas mediante OK [W] por etapa** — cada onda de módulo ainda exige aprovação pra abrir (item 3 da Decisão / [ADR 0105]). Placement canônico/renumeração final sob soberania [CL] ([ADR 0238]); proposta aceita mantida em `proposals/` como os demais aceitos.
>
> Contexto vivo do programa: [PLANO-MESTRE.md](../requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md) §Status vivo (1 plano = 1 registro, [ADR 0294]) — execução via tasks MCP `parent_plan=programa-ondas` ([ADR 0070](./0070-jira-style-task-management-current-md-removed.md)), nunca status em markdown de etapa.

## Contexto

Telas migradas (`/perfil`) e módulos de **nota alta** (`Financeiro` 82) escondem **cálculo de valor indefeso** — a mesma classe do incidente `Util::num_uf` (2026-06-05, valor inflado ~×100k em 16 vendas de ROTA LIVRE biz=4; ver `memory/proibicoes.md §REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE`). Verificado em `origin/main` (2026-07-02, PLANO-MESTRE):

- **6/6 métodos de cálculo core sem teste** (`calculateInvoiceTotal`, `getTotalPaid`≠`getTotalAmountPaid`, `calculatePaymentStatus`, `updateGroupTaxAmount`, `recalculateSellLineTotals`).
- **211 telas Tier-0 sem teste de comportamento** (E2E = 4 de 242 telas).
- **31 migrações Blade→React sem nenhuma verificação de paridade**, 0 gate.

**Causa-raiz:** as **3 réguas do projeto não se sobrepõem** e deixam um buraco no meio — `screen-grade` (UX), `module-grade` (estrutura, [ADR 0155]), `.casos.md` (comportamento, ortogonal, **fora das notas**). Ninguém liga "a tela funciona" à foto por tela. A máquina de durabilidade ([ADR 0256]) é **classe mundial, mas guarda a porta errada**: protege segurança (multi-tenant/PII/secrets) e é **cega no cálculo de dinheiro** (âncora 2026: D8 durabilidade ≈ 70 vs D1 cálculo ≈ 15). O gap é exatamente o que a alucinação da "locação de caçamba" atravessou ([ADR 0265]) e o que o trio de [ADR 0264] semeou mas não cobre em valor.

**O caminho é reapontar, não reconstruir** — coerente com a fase de subtração da [ADR 0271](./0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) e o teto anti-proliferação da [ADR 0298](./0298-teto-de-governanca-anti-proliferacao-gates.md). O projeto **já tem todas as peças**: `capterra-senior` (adversário), `/comparativo` (gaps+backlog+changelog, [ADR 0089]), `screen-grade`, a catraca/sentinela/gate de [ADR 0256].

## Decisão

Esta ADR é **emenda** (não substituição) a duas ADRs vivas — elas permanecem `lifecycle: ativo`, esta só reaponta/estende (por isso `supersedes_partially`, nunca `supersedes`):

- **[ADR 0256]** (catraca/sentinela/gate/cadência) — **reaponta** o framework de durabilidade para as **camadas de valor** (cálculo/comportamento por tela), além da segurança que já cobre.
- **[ADR 0264]** (trio `.tsx`+`.charter`+`.casos` + domínio + E2E) — **adiciona a dimensão de cálculo (D1) ao trio**, sem tocar em G-1..G-4 (que seguem valendo tais como aceitos).

O que a ADR **trava**:

### 1. O ciclo-padrão de 4 passos é o único caminho de onda de módulo

Cada onda de módulo roda estes 4 passos, reusando ferramentas que já existem — nenhum harness novo:

1. **Adversário concorrente** — agente `capterra-senior` → `CAPTERRA-FICHA.md` (nota 0-100 vs 10-15 concorrentes, P0-P3).
2. **Gaps + backlog + changelog** — skill `/comparativo <Mod>` ([ADR 0089]) → `CAPTERRA-INVENTARIO.md` (3 buckets ✅🟡❌) + batch `tasks-create` (MCP, `parent_plan=programa-ondas`) + US no SPEC + changelog.
3. **Régua por tela (com a dimensão que falta plugada)** — `screen-grade` (UX) **+ `casos_coverage`** (UCs que defendem a tela + status) **+ dente de cálculo (D1)** se a tela toca valor.
4. **Catraca** — trava a nota + `casos-gate` + a sentinela de cadência reporta o débito das 3 camadas (espelha [ADR 0256] princípios 2/3/4/6).

Onda de módulo fora deste ciclo é improviso e fica proibida.

### 2. A régua estende-se por PLUGAR, não fundir

Adiciona-se ao **scorecard de tela**: (a) `casos_coverage` (quantos UCs do `.casos.md` daquela tela têm teste que os defende — deriva de G-2 de [ADR 0264]) e (b) a **dimensão D1 — cálculo de valor** (existe teste que pega bug injetado no cálculo daquela tela/serviço).

**Justificativa registrada (invariante):** fundir `screen-grade` (UX) com a assurance de comportamento/cálculo **destruiria a clareza "tela bonita ≠ tela testada"** — uma nota única de UX-alta esconderia cálculo indefeso, que é exatamente o furo de hoje (Financeiro 82 com 6/6 cálculos sem teste). Por isso `casos_coverage` e D1 entram como **campos/eixos distintos e visíveis lado a lado** no mesmo scorecard, não como média diluída. Recomendação do inventário de réguas 2026-07-02.

### 3. A fila de ondas encaixa no roadmap existente (T6 — proibido paralelo)

- **Roadmaps ativos seguem intactos:** OficinaAuto Fase 3 (canary Martinho), PaymentGateway (smoke/canary).
- **Faturamento é o canon macro:** as ondas de **Financeiro / NfeBrasil / RecurringBilling encaixam** em [`_Roadmap_Faturamento.md`](../requisitos/_Roadmap_Faturamento.md) como novas seções/etapas — **nunca** em doc paralelo. Se colidir com um item de lá, o item de lá vence e a etapa daqui vira referência a ele.
- **Novas ondas operacionais, por exposição×débito:** **Sells (piloto) → Compras (nota 59) → Produto → Cliente.** Cada onda exige **OK [W] antes de abrir** — onda sem sinal/aprovação é hipótese, não trabalho ativo (coerente com [ADR 0105](./0105-cliente-como-sinal-guiar-sem-mandar.md)).

### 4. O piso Tier-0: dinheiro/estoque/fiscal exige três provas

Toda tela/serviço do conjunto quente (**valor · estoque · fiscal**) só fecha com:

- **(a) teste de cálculo (D1)** — pega bug injetado no total/desconto/imposto/estoque (o dente que teria pego o `num_uf`);
- **(b) UC de comportamento defendido** — pelo menos um `UC-*` no `.casos.md` referenciado por teste (G-2 de [ADR 0264]);
- **(c) artefato de paridade** — se for migração Blade→React, prova de que a migração preservou a função (a pior dimensão hoje: 8/100).

Isto é coerente com [ADR 0271] (**required = só Tier-0**): o piso não infla required de higiene; ele aponta o rigor máximo exatamente onde o dinheiro/estoque/fiscal vive.

## Anti-padrões que esta ADR proíbe explicitamente

- **Gate de PRESENÇA** — "charter/casos/doc apareceu no diff" como se fosse garantia de correção. **Já rejeitado** em `memory/proibicoes.md §"Ideias avaliadas e DESCARTADAS"` (entrada **2026-07-01 — charter-sync-gate**): presença ≠ correção (L-24); passa com um typo no changelog e não pega a regressão real. O enforcement é sempre de **comportamento** (teste que quebra quando a função some / valor drifta), cego a qual arquivo mudou. Nada nesta ADR pode virar gate de presença.
- **Régua nova paralela** às 3 existentes (`screen-grade` / `module-grade` / `.casos`) — o programa **estende** (pluga `casos_coverage` + D1), **não substitui** nem cria 4ª nota concorrente (violaria fonte-única de [ADR 0256] e o teto de [ADR 0298]).
- **Abrir onda de módulo do Faturamento fora do `_Roadmap_Faturamento.md`** (viola T6 / item 3).
- **Big-bang / required no legado inteiro** — extensão de régua e piso Tier-0 entram por baseline→ratchet (idioma de [ADR 0264]/[ADR 0261]), never quebrar o repo de uma vez.

## Não-objetivos

- Não inventar harness novo: o ciclo reusa `capterra-senior` + `/comparativo` + `screen-grade` + a catraca/sentinela de [ADR 0256]; os guards seguem o padrão `scripts/*.mjs`.
- Não criar 4ª nota nem fundir as 3 réguas.
- Não promover nada a `required` fora do piso Tier-0 (dinheiro/estoque/fiscal) — respeita [ADR 0271].
- Não substituir revisão humana nem teste de emissão fiscal real (SEFAZ).
- Não escrever código nesta etapa: a Onda 0a é **docs-only**; 0b (extensão da régua), 0c (sentinela de cadência) e 0d (paridade de migração) só abrem **depois** deste aceite.

## Consequências

- **+** O buraco entre as 3 réguas fecha: "a tela funciona" passa a ter foto por tela (`casos_coverage`) e o cálculo de valor ganha um dente (D1). O furo da `/perfil`/`num_uf` deixa de depender de sorte.
- **+** A máquina de [ADR 0256] passa a guardar **também** a porta do valor, sem reconstruir nada (reaponta).
- **+** O trio de [ADR 0264] ganha a dimensão de cálculo — as 4 camadas de anti-drift cobrem dinheiro, não só domínio/comportamento.
- **+** Fila de ondas rastreável e sem paralelo (T6): um dono de plano (PLANO-MESTRE), execução no MCP.
- **−** Custo: manter os campos novos do scorecard + a sentinela + os testes de cálculo. Mitigado por serem determinísticos, baratos (Node/Pest, sem LLM no caminho crítico) e faseados (baseline→ratchet).
- **−** Piso Tier-0 adiciona fricção a telas de valor/estoque/fiscal — aceitável e proposital (é onde o incidente custou ~×100k).
- **Risco:** virar teatro se algum dente for de presença/tautológico. **Mitigação:** o §Anti-padrões proíbe presença; D1 tem que pegar **bug injetado** (mutation-style), não "o teste existe".

## Refutação adversarial (dogfood 2026-07-02 — antes do aceite)

Aplicando o próprio espírito adversário do programa a esta ADR:

1. *"É régua nova disfarçada de extensão?"* — Não: `casos_coverage` deriva de G-2 ([ADR 0264]) e D1 é campo no scorecard existente; zero nota concorrente. Se na Onda 0b virar 4ª nota, **reabrir esta ADR** (não deixar drift).
2. *"O piso Tier-0 infla `required` contra a 0271?"* — Não: só aponta rigor onde já é Tier-0 (dinheiro/estoque/fiscal); higiene continua advisory. O flip pra bloqueante segue o rito faseado + palavra do Wagner ([ADR 0271] D-4).
3. *"Cadê a prova de que não é presença?"* — D1 exige bug injetado; `casos_coverage` conta UC **defendido por teste** (não UC escrito). O anti-padrão está travado em texto e citado da `proibicoes §descartados`.

## Decisões Wagner (aceite 2026-07-02 — "aprovado merge")

1. **Mecanismo** (ciclo de 4 passos como único caminho de onda de módulo) — [x] aceito
2. **Extensão PLUGAR-não-fundir** (`casos_coverage` + D1 como eixos distintos no scorecard) — [x] aceito
3. **Fila** Sells → Compras → Produto → Cliente, cada uma com OK [W] pra abrir, Faturamento encaixando em `_Roadmap_Faturamento.md` — [x] aceito (o OK [W] por onda permanece exigido pra abrir cada uma)
4. **Piso Tier-0** (3 provas: cálculo + caso + paridade) — [x] aceito
5. **Numeração 0320** (0319 já usado por proposta irmã do mesmo dia — allocator `next-id.mjs` só conta `decisions/*.md` aceitos, não `proposals/`; 0320 é o próximo livre no disco) — [x] confirmada

Após o aceite: criar as tasks MCP das etapas 0b/0c/0d e abrir a Onda 1 (Sells) — **cada abertura mediante OK [W]** (não no calado).

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-07-02 | [CC] propõe | Onda 0a do programa-ondas. Trava o ciclo de 4 passos, a régua estendida (casos_coverage + D1, plugar não fundir), a fila encaixada (T6) e o piso Tier-0 (cálculo+caso+paridade). Emenda (supersedes_partially, base ativa) a [ADR 0256] + [ADR 0264]. Proíbe gate de presença (proibicoes §descartados 2026-07-01). Docs-only; aguarda Wagner. |
| 2026-07-02 | [W] aceita | "aprovado merge" no chat (mesmo rito da ADR 0304). PORTÃO liberado; 5 itens aceitos incl. numeração 0320. Etapas 0b/0c/0d + Onda 1 Sells liberadas mediante OK [W] por etapa. Placement/renumeração final sob [CL] (ADR 0238). |

## Refs

- Plano mestre: [`memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md`](../requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md)
- Etapa: [`onda-0-fundacao/0a-adr-proposta.md`](../requisitos/_Governanca/programa-ondas/onda-0-fundacao/0a-adr-proposta.md)
- Emenda: [ADR 0256](./0256-knowledge-survival-meia-vida-catraca-sentinela.md) (durabilidade) · [ADR 0264](./0264-governanca-executavel-trio-dominio-e2e.md) (trio+E2E+domínio)
- Coerência: [ADR 0271](./0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) (required=Tier-0) · [ADR 0298](./0298-teto-de-governanca-anti-proliferacao-gates.md) (teto de gates) · [ADR 0105](./0105-cliente-como-sinal-guiar-sem-mandar.md) (sinal) · [ADR 0089](./0089-capterra-ficha-canonica.md) (comparativo)
- Anti-padrão citado: `memory/proibicoes.md §"Ideias avaliadas e DESCARTADAS"` — entrada 2026-07-01 (charter-sync-gate)
- Irmã do mesmo dia: [ADR 0319](proposals/0319-product-truth-stream-adversario-modulo-analise.md) (Product Truth — adversário refutador por módulo, stream PT/SDD)
