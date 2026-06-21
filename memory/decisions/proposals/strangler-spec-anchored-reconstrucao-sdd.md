---
proposal_id: strangler-spec-anchored-reconstrucao-sdd
status: proposed
created: 2026-06-21
proposed_by: claude-code
decided_by: wagner
decided_at:
parent_adr: 0094 (Constituição v2)
related_adrs: [0093, 0101, 0104, 0106, 0175, 0273, 0275, 0271, 0270]
type: estrategia-arquitetural-reconstrucao
blueprint: memory/sessions/2026-06-21-blueprint-sdd-vertical-viva.md
---

# Proposta · Reconstrução do oimpresso = Strangler "spec-anchored" composto (NÃO rewrite)

> **Status:** 🟡 **PROPOSED 2026-06-21** — aguarda decisão do Wagner.
> Origem: Wagner perguntou como "refazer o sistema inteiro com SDD e otimizado" e pediu análise adversarial. Blueprint completo + fontes no [session log do dia](../../sessions/2026-06-21-blueprint-sdd-vertical-viva.md).

## Contexto

Existe a tentação recorrente de "refazer o sistema do zero" para sair da dívida do fork UltimatePOS (SellPosController 3378 LOC, bridge Financeiro com Observer no-op, 58% Blade legado, incidentes como num_uf ×100k). A pergunta foi tratada com rigor: 2 steelmen (greenfield × strangler) + 1 juiz independente, depois 2 pesquisadores profundos cruzando 4 dimensões (metodologia SDD, estratégia, arquitetura/stack, execução com IA), com pesquisa de indústria 2025-2026 citada.

Três fatos do oimpresso dominam a decisão: **(1)** 1 cliente real = 99% do volume (ROTA LIVRE biz=4) → blast radius total; **(2)** time minúsculo (1 dev + IA); **(3)** fiscal mutante em produção (IBS/CBS, transição 2026→2033) hipoteca a capacidade de engenharia para conformidade legal.

## Decisão proposta

Adotar como estratégia-mãe de reconstrução o **Strangler Fig "spec-anchored" composto**, e **rejeitar formalmente** rewrite/greenfield, event sourcing no núcleo, e microserviços.

O composto = strangler (casca) + **Branch by Abstraction** (mecanismo de troca interno para SellPosController e Observer no-op) + **shadow/parallel-run com reconciliação diária** (validação do slice Financeiro/Fiscal, já que biz=4 = 99%) + **hexagonal seletivo** apenas no core Fiscal/Financeiro (mantendo o `business_id` scope no ORM como invariante de borda — Tier 0, ADR 0093) + **append-only ledger/partidas dobradas** para auditabilidade fiscal (não event sourcing).

Complementos de processo: **manter** o SDD caseiro (regra de armamento + avaliador adversarial de 7 skeptics são estado-da-arte que o mercado não replica); **importar** delta-spec (OpenSpec) e EARS (Kiro); **manter** o modo de execução com IA (workflow determinístico + subagents). A **maior alavanca** é operacional: armar o enforcement que já existe (transportar o floor CT100→main, ligar pcov, aplicar a migration do scorecard em prod, promover 1 gate SDD a `required` com counterfactual).

Ponto de partida: vertical viva Venda → Estoque → Financeiro → Fiscal. Catálogo de 8 specs (F0–F3 fundação, V1–V4 domínio) no blueprint.

## Por que não as alternativas

- **Greenfield/rewrite:** 60–80% de falha; descarta conhecimento codificado (ADRs, lições L-01..L-26, FSM, mapa de minas) e dá "2-3 anos ao concorrente" (Spolsky). Com 1 cliente pagando o caixa = roleta-russa.
- **Event sourcing no fiscal:** evidência de produção (custo 4×, latência pior, upcasting de schema custoso) — fatal com schema fiscal mudando todo ano até 2033.
- **Microserviços:** fragmentam o `business_id` scope → multiplicam a superfície de vazamento Tier 0; overhead desproporcional p/ 1 dev.
- **Trocar o SDD por Tessl/Kiro/Spec Kit:** downgrade com lock-in — perde os 2 mecanismos mais avançados do campo (armamento + verificador adversarial de realidade).

## Kill-criteria (quando reabrir a discussão)

1. Reescrever o core Fiscal forçar tirar o tenant scope do ORM → pare (Tier 0 vence a arquitetura).
2. Reconciliação shadow diverge e não se explica em <1 dia → aborta o slice (dual-write sem reconciliação confiável corrompe os títulos).
3. Nenhum slice cabe em PR ≤300 LOC → bounded-context mapping antes de codar.
4. Cronograma IBS/CBS escorrega por causa da modernização → congela a modernização (prazo legal tem prioridade).
5. `anchor_coverage` travado em ~7,5% por 3 meses → sprint só de ancoragem (delta-spec).
6. Upstream UltimatePOS lança algo necessário e não-puxável → de-forking de verdade (padrão Meta).

## Reversibilidade

Alta. É incremental por construção (cada US = uma fita do strangler, atrás de flag, com shadow antes de cutover). Nada exige big-bang; qualquer onda pode parar/reverter a flag. A decisão não cria schema novo irreversível nem toca Tier 0.

## Decisão a tomar pelo Wagner

- [ ] Aprovar a estratégia composta e promover a ADR canon (Nygard, próximo número livre), OU
- [ ] Ajustar escopo/ordem (ex: começar por fundação em vez da vertical), OU
- [ ] Rejeitar / pedir mais investigação.

**Input do Wagner (2026-06-21):** não puxamos updates do upstream UltimatePOS há tempo → **de-forking custa ~zero** (formalizar a base como código próprio); padrão Meta dispensado. A dimensão de stack está resolvida: tratar a base como código próprio, modular monolith nwidart mantido.
