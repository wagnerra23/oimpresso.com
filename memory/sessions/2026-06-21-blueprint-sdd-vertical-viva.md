---
date: "2026-06-21"
topic: "Blueprint — refazer o oimpresso com SDD otimizado (strangler spec-anchored, vertical viva)"
authors: [C]
type: blueprint-estrategico
tema: refazer-sistema-com-sdd-otimizado
escopo: estratégia de reconstrução + metodologia SDD + arquitetura/stack alvo + modo de execução com IA, focado na vertical viva Venda→Estoque→Financeiro→Fiscal
metodo: análise adversarial (2 steelmen greenfield×strangler + 1 juiz; depois 2 pesquisadores profundos cruzando 4 dimensões, criticando a recomendação anterior)
autor: claude-code (Opus 4.8) — sessão Wagner
websearch_count: ~40 (agregado dos agentes)
status: blueprint aprovado por Wagner; decisão formal pendente via proposta de ADR
adrs_citados: [0093, 0094, 0101, 0104, 0106, 0175, 0273, 0275, 0271, 0270, 0282]
proposta: memory/decisions/proposals/strangler-spec-anchored-reconstrucao-sdd.md
---

# Blueprint: refazer o oimpresso com SDD (otimizado) — vertical viva primeiro

## TL;DR

Wagner perguntou *"se eu quiser refazer o sistema inteiro com SDD e otimizado, como seria? o que faria diferente? quais specs gerar?"*, pediu análise adversarial (não chute), e ao ver a v1 disse *"parece que existe coisa melhor"* (apontou 4 dimensões: metodologia SDD, estratégia, arquitetura/stack, execução com IA).

**Veredito após pesquisa de indústria 2025-2026:** a "coisa melhor" **NÃO é um paradigma diferente**. Greenfield, event sourcing, microserviços e trocar o SDD caseiro por Tessl/Kiro/Spec Kit **perdem feio com evidência**. A resposta ótima é o **mesmo strangler fig "spec-anchored", porém explicitamente armado** com 2 mecanismos antes implícitos (**Branch by Abstraction** + **shadow/parallel-run com reconciliação**), 2 peças baratas de SDD do mercado (**delta-spec** do OpenSpec + **EARS** do Kiro), e — maior alavanca de todas — **operar o enforcement que já existe** (o SDD "mede e não governa" por razões ~90% operacionais).

Ponto de partida escolhido: vertical do dinheiro Venda → Estoque → Financeiro → Fiscal (ROTA LIVRE, biz=4, 99% do volume).

## Os 3 fatos que esmagam quase toda alternativa

1. **1 cliente = 99% do volume** (biz=4, 17.251 vendas) → blast radius total; não há cohorte para canário gradual. O canário tem que ser *interno ao fluxo* (write a write, shadow), não por tenant.
2. **Time minúsculo (1 dev + IA)** → padrões cujo custo dominante é operacional contínuo (microserviços, event sourcing) são desproporcionais.
3. **Fiscal mutante em produção AGORA** (IBS/CBS fase-teste desde 01/01/2026; dupla escrituração; rejeição automática a partir de 2027; transição até 2033) → a capacidade de engenharia de 2026 já está hipotecada para conformidade fiscal. Qualquer reescrita compete com prazo legal.

## Veredito por dimensão

### 1. Estratégia → Strangler spec-anchored COMPOSTO (não o simples)

| Alternativa | Veredito | Por quê |
|---|---|---|
| Greenfield total | ❌ | 60–80% de falha; com 1 cliente pagando o caixa = roleta-russa (Spolsky; amazingcto; dev.to 2026) |
| Event sourcing no fiscal | ❌ | Produção: custo 4×, latência 300→80ms *depois de sair*, 300+ linhas de upcasting por 1 campo — fatal com schema fiscal mudando até 2033 |
| Microserviços | ❌ | Erro de categoria p/ 1 dev; **fragmenta o `business_id` scope** = multiplica superfície de vazamento Tier 0 |
| **Branch by Abstraction** | ✅ novo | Mecanismo de troca *interno* p/ os pontos profundos (SellPos 3378 LOC, Observer no-op) que não se estrangula por fachada HTTP |
| **Shadow/parallel-run + reconciliação** | ✅ novo | Validação certa p/ 1 cliente: novo roda em paralelo, job reconcilia diário vs legado, cutover só após N dias de match 100%. **Teria pego o num_uf ×100k antes do SEFAZ** |
| Hexagonal | ⚠️ seletivo | Só no core Fiscal/Financeiro que será reescrito pelo IBS/CBS — **tenant scope fica no ORM** (tirá-lo é a operação mais perigosa do projeto) |
| Anti-corruption layer | ⚠️ pontual | Só na fronteira Venda→Financeiro (o Observer no-op) |

**Correção da v1:** "biz=4 por último" é insuficiente. O certo no slice Financeiro/Fiscal é **shadow-mode** com reconciliação diária; o backfill de 17.412 títulos é o baseline.

### 2. Arquitetura/stack → não de-forkar via rewrite; de-divergir

- Case Meta WebRTC (2026): saíram do fork **sem reescrever** (shim + dual-stack + flag = Branch by Abstraction). Lição: "pare de divergir", não "reconstrua".
- **Respondido por Wagner (2026-06-21): NÃO puxamos updates do upstream UltimatePOS há tempo** → já saímos do fork de facto. "De-forkar" = formalizar a base como código próprio (custo ~zero); o padrão Meta (shim + patches sobre upstream) **não se aplica**. Parar de tratar a base como "fork" é só renomear a realidade.
- Modular monolith (nwidart) é o destino correto p/ time <15 — consenso forte. Microserviços fragmentariam o Tier 0.
- Auditabilidade fiscal: **append-only ledger + partidas dobradas imutáveis + `audit_log`** (já existe AuditLog write), NÃO event sourcing.

### 3. Metodologia SDD → AUMENTAR (importar 2 peças), não substituir

Nenhuma ferramenta inteira supera o SDD caseiro — ele tem os **2 mecanismos mais avançados do campo**: **regra de armamento** (métrica só pune após 3 medições reais) e **avaliador adversarial de 7 skeptics que checa realidade-vs-alegação em git**. O survey acadêmico (arXiv 2602.00180) e Fowler reconhecem esses riscos como *abertos* no mercado.

Importar (barato, sem lock-in): **delta-spec do OpenSpec** (ANCHORED/MODIFIED/REMOVED por PR) p/ destravar o backfill de anchors (emperrado em 3/57); **EARS do Kiro** nos critérios de aceite. Opcional: empacotar o fluxo como comandos nomeados (`/speckit.*`-style).

Rejeitar: Tessl (beta, lock-in, compilador-LLM não-determinístico, JS-cêntrico), Kiro-como-IDE, Spec Kit inteiro, BMAD.

### 4. Execução com IA → MANTER (já é estado da arte)

Workflow determinístico + subagents + verificador adversarial = padrão 2026. Calibrar: **"10x" é mito** (METR RCT — devs 19% *mais lentos* sentindo-se 20% mais rápidos); realista ~2–3x em trabalho mecânico, ~1× no julgamento. Ajuste: disciplina de fila p/ IA paralela na governança (≤5 Opus concorrentes; alocação coordenada de nº de ADR/índice).

## A MAIOR alavanca: operar o que já existe (~90% operacional)

Autoavaliação de 2026-06-20: **0 dos 18 gates required são SDD** (todo gate SDD é advisory), `anchor_coverage` 5–7,5%, métricas ilusórias (migration `mcp_sdd_scorecard_history` nunca aplicada em prod = 0 rows; floor 274 existe mas o scorecard lê `not_yet_measured`), regressões reais entraram no main sem bloqueio (#2848). Nenhuma ferramenta de mercado conserta isto. Caminho crítico:
1. Transportar floor CT100→main lido pelo scorecard + ligar pcov → flipa `full_suite_pass_rate`/`coverage_pct` para número real.
2. Aplicar a migration em prod (≥1 row em `mcp_sdd_scorecard_history`) → destrava G7/G8.
3. Promover 1 gate SDD a `required` com counterfactual (PR-regressão dá exit 1).

## Catálogo de specs (vertical viva)

Fundação (F0–F3) antes da vertical (V1–V4).

- **F0 · `_Governanca/SPEC-tier0-multitenant.md`** (P0, bloqueia tudo) — contrato do `business_id` global scope **mantido no ORM**. Âncora: trait + `NoMissingTenantScopeRule`. Gate: required + counterfactual.
- **F1 · `_DesignSystem/SPEC-ui-0013.md`** (P1) — tokens OKLCH, PageHeader v3, AppShellV2, defer-default, charter obrigatório.
- **F2 · `Financeiro/SPEC-bridge-expand-contract.md`** (P0, coração da dor) — bridge `Transaction/TransactionPayment`→`fin_titulos` via **Branch by Abstraction** + flag; **shadow-write + reconciliação** vs backfill de 17.412 títulos; promover ADR 0175 (Observer guard). Gate: `NoNopMutationControllerRule` required + characterization-floor.
- **F3 · `_Governanca/SPEC-observabilidade-scorecard.md`** (P1, juiz) — scorecard armado (floor, pcov, migration prod, tamper-guard, EARS, delta-spec). Verde antes de qualquer V.
- **V1 · `Sells/SPEC-venda-pos.md`** (P0) — estrangular `SellPosController` (3378 LOC) por BbA: `PriceQuoting`/`StockMutator`/`FinTituloEmitter`, Legacy+Sdd atrás de `useV2SellsCreate`. Oráculo num_uf. Charters Create/Caixa/Index. Dep: F0, F2, V2.
- **V2 · `Estoque/SPEC-estoque-grade.md`** (P1) — grade tamanho×cor + baixa atômica. Dep: F0; consumido por V1.
- **V3 · `Financeiro/SPEC-titulos-ar-ap.md`** (P0) — AR/AP nome real, baixa idempotente, DRE BR, FluxoCaixa, aging, **dupla confirmação de saldo** + shadow. Gate: `NoSilentFallbackRule` required + financial-double-check. Append-only ledger + partidas dobradas. Dep: F2, V1.
- **V4 · `NfeBrasil/SPEC-fiscal-nfce-nfe.md`** (P1 anti-incidente / P2 completo) — NFC-e/NF-e/SPED + **IBS/CBS via hexagonal seletivo + ACL**, numeração sem salto (validada em shadow), contingência explícita, série por tenant. Gate: numeração-invariant + smoke só biz=1. Dep: V1, V3 (último).

Ordem: **operacional → F0 → F3 → F2 → V2 → V1 → V3 → F1 (paralelo) → V4.**

## Ondas (sem big-bang)

0 Rede+enforcement (characterization incl. +3h/ADR 0066 + num_uf; floor; pcov; 1 gate required; baseline 7-skeptics) → 1 Seam Pricing (BbA+flag, dormentes→quase-inativos) → 2 Seam Stock (BbA+flag, biz=4 por último) → 3 Expand fin_titulos (shadow-write, invisível) → 4 Migrate+reconciliar (N dias match 100% vs 17.412 títulos) → 5 Contract+cutover (biz=4 por último, fora do pico, rollback ensaiado). Capacidade ~15–20%; 80% mantém cliente + IBS/CBS em dia.

## Verificação

Counterfactual dos gates (exit 1 em diff quebrado); migration em prod (≥1 row); reconciliação shadow diária (cutover só com match 100% N dias biz=4); oráculo backfill 17.412; anchor-lint via delta-spec (ratchet incremental, só paths `verificado@sha7`); `sdd-avaliador-processo.js` na Onda 0 e fim de cada onda; testes no CT100 nunca local (ADR 0062); smoke fiscal só biz=1 (ADR 0101).

## Kill-criteria

(1) Reescrever core Fiscal forçar tirar tenant scope do ORM → pare (Tier 0 vence). (2) Reconciliação shadow diverge sem explicação em <1 dia → aborta slice. (3) Nenhum slice cabe em PR ≤300 LOC → bounded-context mapping antes. (4) Cronograma IBS/CBS escorrega → congela modernização. (5) `anchor_coverage` travado em ~7,5% por 3 meses → sprint só de ancoragem (delta-spec). (6) Upstream UltimatePOS lança algo que precisamos e não puxa → de-forking de verdade (padrão Meta).

## Próximos passos

1. (este doc) Persistir o blueprint canônico. ✅
2. Proposta de ADR registrando a decisão (status: proposed) → Wagner decide.
3. Follow-on (sessão de escrita): passe Glob/Grep no checkout principal confirmando paths (`SellPosController`, Observer `fin_titulos`, global scope `business_id`, `Modules/NfeBrasil`) + responder a pergunta de de-fork (ainda consome upstream?); então gerar os 8 esqueletos `SPEC.md` (F0–F3, V1–V4) com anchors `_pendente_` ou converter em backlog de tasks MCP.

## Fontes-chave (research)

Spolsky *Things You Should Never Do* · Herb Caudill *6 rewrite stories* · Meta *Escaping the Fork: WebRTC* (2026) · Fowler *StranglerFigApplication* + *ParallelChange* + *SDD with 3 tools* · Feathers *Working Effectively with Legacy Code* · AWS Prescriptive Guidance (Strangler / Branch-by-Abstraction / Hexagonal) · Azure Architecture Center (Strangler / Event Sourcing) · *Event Sourcing was a nightmare* (Production Nightmares 2026) · METR RCT arXiv 2507.09089 · SDD survey arXiv 2602.00180 · GitHub Spec Kit · AWS Kiro · Tessl · OpenSpec · Receita Federal *Orientações Reforma 2026*.
