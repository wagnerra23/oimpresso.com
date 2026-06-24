---
slug: 0306-strangler-spec-anchored-reconstrucao-sdd
number: 306
title: "Reconstrução do oimpresso = Strangler spec-anchored composto (rejeita rewrite/greenfield, event sourcing no núcleo e microserviços)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-24"
module: governance
tags: [arquitetura, estrategia, strangler-fig, branch-by-abstraction, shadow-run, sdd, reconstrucao, tier-0]
supersedes: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0175-fix-observer-conta-bancaria-opcional
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0307-onda-0-rede-seguranca-enforcement
pii: false
---

> **Ratificada por [W] em 2026-06-24.** Promove a canon a proposta [`strangler-spec-anchored-reconstrucao-sdd`](proposals/strangler-spec-anchored-reconstrucao-sdd.md) (PROPOSED 2026-06-21). Blueprint completo + fontes de indústria 2025-2026 em [`2026-06-21-blueprint-sdd-vertical-viva`](../sessions/2026-06-21-blueprint-sdd-vertical-viva.md). Esta ADR é a **estratégia-mãe**; a [ADR 0307](0307-onda-0-rede-seguranca-enforcement.md) é a 1ª onda executada.

# ADR 0306 — Reconstrução = Strangler spec-anchored composto (NÃO rewrite)

## Contexto

Existe a tentação recorrente de "refazer o sistema do zero" para sair da dívida do fork UltimatePOS (`SellPosController` 3378 LOC, bridge Financeiro com Observer no-op, ~58% Blade legado, incidentes como `num_uf` ×100k). A pergunta do Wagner ("como refazer o sistema inteiro com SDD e otimizado?") foi tratada com rigor adversarial: 2 steelmen (greenfield × strangler) + 1 juiz independente, depois 2 pesquisadores profundos cruzando 4 dimensões (metodologia SDD, estratégia, arquitetura/stack, execução com IA), com pesquisa de indústria 2025-2026 citada.

Três fatos do oimpresso dominam a decisão:
1. **1 cliente = 99% do volume** (ROTA LIVRE, biz=4, ~17,2k vendas) → blast radius total; não há cohorte para canário por tenant.
2. **Time minúsculo** (1 dev + IA) → padrões cujo custo dominante é operacional contínuo (microserviços, event sourcing) são desproporcionais.
3. **Fiscal mutante em produção** (IBS/CBS em fase-teste desde 01/01/2026; transição até 2033) → a capacidade de engenharia de 2026 já está hipotecada para conformidade legal; qualquer reescrita compete com prazo de lei.

## Decisão

Adotar como **estratégia-mãe de reconstrução** o **Strangler Fig "spec-anchored" composto**, e **rejeitar formalmente** rewrite/greenfield, event sourcing no núcleo e microserviços.

O composto =
- **Strangler** (casca incremental) +
- **Branch by Abstraction** — mecanismo de troca *interno* para os pontos profundos que não se estrangulam por fachada HTTP (`SellPosController`, Observer no-op), Legacy+Sdd atrás de flag;
- **Shadow / parallel-run com reconciliação diária** — validação certa para 1 cliente que é 99% do volume: o novo roda em paralelo, job reconcilia vs legado, cutover só após N dias de match 100% (teria pego o `num_uf` ×100k antes do SEFAZ);
- **Hexagonal seletivo** apenas no core Fiscal/Financeiro reescrito pelo IBS/CBS — **mantendo o `business_id` scope no ORM como invariante de borda** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md), Tier 0; tirá-lo é a operação mais perigosa do projeto);
- **Append-only ledger + partidas dobradas imutáveis** para auditabilidade fiscal — NÃO event sourcing.

Complementos de processo: **manter** o SDD caseiro (regra de armamento + avaliador adversarial de 7 skeptics = estado-da-arte que o mercado não replica); **importar** delta-spec (OpenSpec) e EARS (Kiro), baratos e sem lock-in; **manter** o modo de execução com IA (workflow determinístico + subagents). Ponto de partida: vertical viva **Venda → Estoque → Financeiro → Fiscal** (catálogo de 8 specs F0–F3 / V1–V4 no blueprint). O **design (F1) é paralelo** — não fica atrás das ondas da vertical.

**De-forking:** input do Wagner (2026-06-21) — não se puxa update do upstream UltimatePOS há tempo, logo o de-fork custa ~zero (formalizar a base como código próprio). O padrão Meta (shim sobre upstream) **dispensado**. Modular monolith (nwidart) mantido.

## Por que não as alternativas

- **Greenfield/rewrite:** 60–80% de falha; descarta o conhecimento codificado (ADRs, lições L-01..L-26, FSM, mapa de minas) e dá "2-3 anos ao concorrente" (Spolsky). Com 1 cliente pagando o caixa = roleta-russa.
- **Event sourcing no fiscal:** evidência de produção (custo 4×, latência pior depois de sair, upcasting de schema custoso) — fatal com schema fiscal mudando todo ano até 2033.
- **Microserviços:** fragmentam o `business_id` scope → multiplicam a superfície de vazamento Tier 0; overhead desproporcional para 1 dev.
- **Trocar o SDD por Tessl/Kiro/Spec Kit inteiro:** downgrade com lock-in — perde os 2 mecanismos mais avançados do campo (armamento + verificador adversarial de realidade).

## Kill-criteria (quando reabrir a discussão)

1. Reescrever o core Fiscal forçar tirar o tenant scope do ORM → **pare** (Tier 0 vence a arquitetura).
2. Reconciliação shadow diverge e não se explica em <1 dia → **aborta o slice** (dual-write sem reconciliação confiável corrompe títulos).
3. Nenhum slice cabe em PR ≤300 LOC → bounded-context mapping **antes** de codar.
4. Cronograma IBS/CBS escorrega por causa da modernização → **congela** a modernização (prazo legal tem prioridade).
5. `anchor_coverage` travado em ~7,5% por 3 meses → sprint só de ancoragem (delta-spec).
6. Upstream UltimatePOS lança algo necessário e não-puxável → de-forking de verdade (padrão Meta).

## Reversibilidade

Alta — incremental por construção (cada US = uma fita do strangler, atrás de flag, com shadow antes de cutover). Nada exige big-bang; qualquer onda pode parar/reverter a flag. A decisão **não cria schema novo irreversível** nem toca Tier 0.

## Consequências

✅ Norte arquitetural único e blindado contra o reflexo "refaz do zero"; preserva o caixa e a conformidade fiscal; armadura explícita (BbA + shadow) onde o strangler simples não chega.
⚠️ Exige disciplina de fila para IA paralela na governança (≤5 Opus concorrentes; alocação coordenada de nº de ADR) — a própria ratificação desta ADR teve de reconciliar contra ~70 worktrees ativos. O "10x" é mito (METR RCT — ~2–3x mecânico, ~1× no julgamento); estimativas calibram por isso.

## Validação / estado

- Proposta + blueprint aprovados por Wagner (2026-06-21); ratificados a canon em 2026-06-24.
- 1ª onda (rede de segurança) já **executada e verde** — ver [ADR 0307](0307-onda-0-rede-seguranca-enforcement.md).
- Fontes-chave: Spolsky *Things You Should Never Do* · Fowler *StranglerFig/ParallelChange/SDD-3-tools* · Meta *Escaping the Fork: WebRTC* (2026) · *Event Sourcing was a nightmare* (2026) · METR RCT arXiv 2507.09089 · SDD survey arXiv 2602.00180 · Receita Federal *Orientações Reforma 2026*.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-21 | [CL] redige | proposta (análise adversarial + pesquisa de indústria) |
| 2026-06-24 | [W] decide + [CL] redige | ratificação a canon (decisão "fechar a Onda 0") |
