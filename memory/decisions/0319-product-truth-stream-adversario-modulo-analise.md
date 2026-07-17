---
slug: 0319-product-truth-stream-adversario-modulo-analise
number: 319
title: "Product Truth (stream PT no roadmap SDD) — adversário refutador por módulo + refutador de análise de pedido"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-02"
accepted_at: "2026-07-09"
accepted_via: "Aceito o STREAM PT (Q1 da §Decisões pendentes) — greenlight Wagner 2026-07-02 ('sim pode fazer') + ratificação 2026-07-09 ('b ratifique ou apague', US-GOV-050 follow-up). As sub-decisões Q2-Q5 (armar métrica no GT-G3, R15 vs prática, cadência, sequenciamento pós-Onda 1) ficam ABERTAS/advisory-default por design — não bloqueiam; a seção '## Decisões Wagner pendentes' segue como tracker. Redação [CC]."
module: governance
quarter: 2026-Q3
tags: [governance, sdd, adversarial, skeptic, product-truth, briefing, capterra, memoria, refutador, anti-teatro]
supersedes: []
superseded_by: []
related:
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0070-jira-style-task-management-current-md-removed
  - 0061-conhecimento-canonico-git-mcp-zero-automem
pii: false
---

# ADR 0319 — Product Truth: adversário refutador por módulo (stream PT) + refutador de análise de pedido

> Movido de proposals/ + flip proposto→aceito em 2026-07-09 (Wagner 'b ratifique ou apague', US-GOV-050 follow-up). **Escopo ratificado:** o CONCEITO do stream PT (Q1) — respaldado no greenlight 'sim pode fazer' (2026-07-02). Execução ainda NÃO iniciada (é "após aceite"); as 5 decisões da seção "## Decisões Wagner pendentes" seguem como tracker aberto — Q2-Q5 são advisory-default e não bloqueiam. Conteúdo original 100% intacto; ajustes SÓ de frontmatter (status/accepted_at/accepted_via). Aceite é reversível via supersede/deprecate se o stream não avançar.

## Contexto

O programa SDD fechou o loop adversarial pra **governança/processo** (avaliação 2026-07-02: composto 76/100, skeptics por stream, counterfactuals provados live no required). Mas a camada de **produto/módulo** ficou fora do loop:

1. **Gaps profundos só em ~10 de ~78 módulos** — CAPTERRA-FICHA existe em 10 módulos; CAPTERRA-INVENTARIO (bucket acionável ✅/🟡/❌) em ~5. O resto tem só a camada rasa (`module:grade` + seção Gaps do BRIEFING).
2. **Nenhum adversário refuta as afirmações de módulo.** O skeptic hoje opera por stream de governança (`sdd-avaliador-processo`, 7 streams), por diff (`/ultrareview`) e por âncora (refutador G5). Ninguém é pago pra provar que um "✅ aprovado" do INVENTARIO ou uma capacidade declarada no BRIEFING **mente**.
3. **Memória falsa se propaga pra IA.** A cadeia `BRIEFING → mcp_memory_documents → Meilisearch → brief/KbAnswerAgent` serve claims não-verificados pra Jana e pra toda sessão Claude. Caso real: `COMPARATIVO_CONCORRENCIA.md` da Jana ficou ~2 meses dizendo "nota 0 — não implementado" com a Jana live em prod (corrigido só em 2026-07-02, PR #3625, por pergunta manual do Wagner).
4. **O ganho adversarial na análise de pedido é observado mas não institucionalizado.** Wagner 2026-07-02: *"notei que a ia tem um rendimento extraordinário se no processo de análise da solicitação tiver um adversário"*. O `wagner-understand` decodifica o pedido, mas ninguém refuta a decodificação — interpretação errada executa com confiança (a falha mais cara: 3 PRs revertidos por leitura errada).

Fundamento (por que adversário funciona — literatura + evidência interna): assimetria gerador×verificador (CriticGPT 2024), painel diverso > juiz único (Cohere "Juries" 2024), Default-FAIL (arte-evidência 2026-05-17), e os casos internos: dogfood `estado-da-arte` pegou 3 P0 fatais (2026-05-13); review adversarial ancora-guard achou 33 modos de falha, Wagner-tinha-razão 33/33 (2026-06-30); trajetória do scorecard SDD 60→76 movida pelos skeptics. A auditoria `arte-governanca-sdd` (2026-06-21) confirmou: **nenhum player open combina armamento de métrica + adversário recorrente** — estender o padrão que já é superior ao mercado, não adotar framework externo.

## Refutação adversarial — dogfood 2026-07-02 (3 skeptics, lentes diversas)

> A própria 0319 passou pelo mecanismo que ela propõe (Peça B aplicada a si mesma). 3 skeptics paralelos (duplicação/canon · mecanismo · operação), Default-FAIL, verificados no repo real. **A ADR sobrevive no núcleo, mas NÃO na forma original — as emendas abaixo são vinculantes** e reduzem a ambição. Achado-mãe (a favor da tese): o refutador pegou a própria ADR com **1 erro factual duro** que nenhuma passada única viu.

| # | Achado (evidência) | Veredicto | Emenda vinculante |
|---|---|---|---|
| **E1** | **"R12" já existe** (`PROTOCOLO-WAGNER-SEMPRE.md:296`; protocolo vai até R14). 2 skeptics pegaram. | REFUTADA (factual) | Peça B vira **R15**. A ADR que institucionaliza refutar claims **continha uma claim stale** — prova empírica do valor (registrada, precedente 2026-05-13). |
| **E2** | **BRIEFING é prosa vaga de LLM** ("operacional em 85%" — `Jana/BRIEFING.md:12`), não claim refutável. F0 refutaria paráfrase do distiller = falso-positivo em massa. | REFUTADA (fatia BRIEFING) | F0 extrai claims só de **INVENTARIO + âncoras SPEC/US com ID estável**, NÃO do corpo do BRIEFING. 5º veredicto `NÃO-REFUTÁVEL` (claim vago não vira ILUSÓRIO). |
| **E3** | **Adversário de módulo já existe parcialmente** — o refutador G5 já refuta BRIEFINGs destilados no WRITE de lote IA (`Governance/PROTOCOLO-REFUTADOR-BACKFILL.md:15`). | ENFRAQUECIDA (claim overstated) | Reescrever o gap: G5 morde no **write de lote**; o PT cobre o **estoque** (staleness) + **claim humano/PR**. Não "ninguém refuta". |
| **E4** | **F0/F1 re-medem o que `anchor-lint` + `doneness-lint` já mordem** (path/status — `doneness-lint.mjs:18`). Risco "dois números pro mesmo conceito" que o scorecard acabou de matar (`sdd-scorecard.mjs:52`). | ENFRAQUECIDA | F0 **delega** existência-de-path/status a anchor-lint/doneness (transporta, não re-mede); o skeptic PT só refuta a camada **semântica** (o código FAZ o que o claim diz), com 2 evidências (rota registrada + handler não-stub). |
| **E5** | **`pt_freshness` duplica `distiller_freshness`** (já ARMADA no GT-G3, mesmo `BRIEFING.md`) e **`pt-ledger` duplica `sdd-verification-ledger.json`** (keystone proíbe "dois sistemas paralelos" — `sdd-scorecard.mjs:95`). | REFUTADA (métricas/ledger separados) | UM watchdog de frescura por arquivo (deriva do `measureDistillerFreshness`). Ledger PT = **extensão do schema** do `sdd-verification-ledger` (`tipo: pt-stock`+`modulo`), não pasta nova. `pt-scorecard.mjs` é **fonte que o sdd-scorecard transporta**, nunca scorecard 2º. |
| **E6** | **Armar `pt_claim_accuracy` no required** = doc-quality bloqueando merge, 2 dias após a 0314 D-1 **demover** `charter_refs` (mesma classe). Precedente corta dos 2 lados (`anchor_coverage` foi armada). | ENFRAQUECIDA (tensão de política não-nomeada) | Métricas PT nascem **advisory-perene** (padrão `drift_alarms`). Armar no required = **checkbox Wagner separado** do aceite do stream + parágrafo reconciliando com a 0314 (por que acurácia de produto seria Tier-0). |
| **E7** | **"1 ajuste no distiller" é falso** — `montarBriefing` recria frontmatter do zero (`DistillerModuloVerdade.php:121`, "sobrescreve, NÃO append"); cron kill-switched (`Kernel.php:246`); precisa venue git-backed. **Ironia a favor:** distiller morto ⇒ `verified_at` sobrevive por default. | REFUTADA ("1 ajuste") | Rebaixar pra "dependência do desbloqueio do distiller (P11)". Piloto roda **sem** essa peça; único fix ~5 linhas (merge de frontmatter no `montarBriefing`) pode ir já. |
| **E8** | **Watchdog "1 fix serve os 2" infla** — defeito nº1 SDD é no `measureFullSuiteFloor` (fonte `nightly-floor.json`, 48h) vs watchdog PT (`verified_at` em BRIEFING, 30d). Fontes/janelas/fixtures distintas. **E `verified_at`→Freshness Pipeline é falso hoje** (`StalenessDetectorService` só olha `mcp_memory_documents`; feature nova). | ENFRAQUECIDA / REFUTADA (o "efeito colateral") | Separar em 2 watchdogs que reusam 1 helper `staleThan()`. O ganho pra Jana é **trabalho futuro escopado**, não efeito colateral. |
| **E9** | **Peça B Q2/Q3 duplicam `wagner-understand`** (`wagner-understand.md:70,109`); refutador re-rodando buscas do gerador = erro correlacionado (lição G5 2026-07-01). E **understand está dormente 25d** + ADR-mãe 0168 `proposto` há 46d. | ENFRAQUECIDA | Refutador ataca só a **interpretação** (Q1/Q4/Q5); Q2/Q3 ele **audita se o understand respondeu**, não re-executa. Peça B condicionada a **investigar por que o understand caiu em desuso**. |
| **E10** | **Fila Wagner já tem ~35 decisões pendentes em ≥5 filas** (A6 ~28, skim distiller, HITL brief, roadmap ×4). "Nada no calado" ⇒ mentira detectada segue servida à Jana até triagem. | ENFRAQUECIDA (mitigação de fila é papel) | `claim_disputed` no frontmatter **no ato do veredicto** (metadado, não correção de conteúdo — retrieval rebaixa já); F3 = **1 batch quinzenal formato A6** (~30min skim); watchdog na latência da própria fila. |
| **E11** | **Custo/sequenciamento** — 78 módulos × quinzenal ≈ campanha P10 (8-14M tokens) 2×/mês; Onda 1 com 8 sessões **já alocadas** (watchdog = S1, distiller = S7). | SOBREVIVE no texto (limita a 10), REFUTADA na ambição do título | Teto explícito: **≤10 módulos/quinzena**; expansão = nova decisão Wagner com custo em tokens. Início da **Peça A condicionado ao fechamento de S1+S7 da Onda 1**. Peça B não compete por sessão (pode ir). |

**Veredicto consolidado dos 3:** *vai pro Wagner COM as emendas E1-E11 — não como estava.* O núcleo (adversário de **estoque** por módulo sobre INVENTARIO+âncoras semânticas + refutador de interpretação) ataca gap real que nenhuma peça cobre inteira. Morre a forma: BRIEFING-como-fonte, "1 ajuste", métricas/ledger paralelos, armamento silencioso no required, e a numeração R12.

## Decisão (proposta — já incorporando emendas E1-E11)

### Peça A — Stream **PT (Product Truth)** no roadmap SDD

Novo stream no `_ROADMAP.md` **existente** (extensão, nunca plano paralelo — proibições §"Ideias descartadas" 2026-06-05/T6): 1 skeptic por módulo refuta as afirmações escritas do módulo contra o código real.

**Workflow `/pt-avaliar <Modulo>` (4 fases):**

| Fase | O quê | Custo |
|---|---|---|
| **F0 Extrair** | Parser determinístico extrai claims de **ID estável**: linhas ✅/🟡/❌ do `CAPTERRA-INVENTARIO.md` + US `done` com âncora `Implementado em:` do `SPEC.md`. **NÃO** do corpo do BRIEFING (prosa vaga de LLM — E2). Existência-de-path/status = **delegado a anchor-lint/doneness-lint** (transporta, não re-mede — E4) | barato, mecânico |
| **F1 Refutar** | 1 skeptic por módulo, Default-FAIL, verifica a camada **semântica** (o código FAZ o que o claim diz — 2 evidências: rota registrada + handler não-stub). Prova de checkout obrigatória (`git rev-parse HEAD == origin/main`). Veredicto: `CONFIRMADO` (file:line) / `STALE` / `ILUSÓRIO` / `FALTA` / `NÃO-REFUTÁVEL` (claim vago — E2) | 1 agent/módulo; refutador de tier ≥ gerador |
| **F2 Score+ledger** | `pt_claim_accuracy = confirmados/(confirmados+stale+ilusórios)` sobre **denominador de IDs congelado por baseline** (mudança de universo = PR no baseline — E5/E-denominador); veredictos apendados como `tipo: pt-stock` no **`sdd-verification-ledger.json` existente** (não pasta nova — E5); no ato do veredicto, `claim_disputed` no frontmatter do módulo (metadado, retrieval rebaixa já — E10) | determinístico |
| **F3 Síntese** | Session log + `ILUSÓRIO`/`STALE` = **1 batch quinzenal formato A6** (~30min skim — E10), tasks MCP (ADR 0070). Fix de tela **encaminhado ao `/alinhar-tela`** (que já tem mandato de fix — E-alinhar). **Nada corrigido no calado.** | fila humana |

**Arquivos:** `.claude/workflows/pt-avaliador-modulo.js` (espelha `sdd-avaliador-processo.js`) · `.claude/skills/pt-avaliar/SKILL.md` · `scripts/governance/pt-scorecard.mjs` (**fonte que o sdd-scorecard transporta**, nunca scorecard 2º — E5) · schema estendido do `governance/sdd-verification-ledger.json` · § PT no `_ROADMAP.md`.

**Métricas — advisory-perene por default (E6):** `pt_claim_accuracy` nasce advisory (padrão `drift_alarms`, ADR 0275 métrica 9). Fusão/armamento no GT-G3 required = **checkbox Wagner SEPARADO** do aceite do stream, com parágrafo reconciliando a tensão com a 0314 (por que acurácia de produto seria Tier-0, já que `charter_refs` foi demovido 2026-06-30). Frescura: UM watchdog derivado do `measureDistillerFreshness` (não `pt_freshness` separado — E5).

**Anti-decadência (3 mecanismos obrigatórios, senão o PT vira o teatro que caça):**
1. **Watchdog staleness**: `verified_at > 30d` → módulo cai pra `not_verified` (mesmo fix do defeito nº1 da avaliação 76 — implementar junto).
2. **Counterfactual no gate-selftest**: fixture com claim falsa injetada → skeptic TEM que pegar → senão selftest RED (vigia-dos-vigias, 46→47).
3. **Armamento antes de morder**: nunca pune no escuro.

**Integração com a memória (E7 — NÃO é "1 ajuste"):** o BRIEFING é gerado pelo distiller, cujo `montarBriefing` recria o frontmatter do zero (`DistillerModuloVerdade.php:121`, "sobrescreve, NÃO append") e cujo cron está kill-switched (`Kernel.php:246`, precisa venue git-backed — bloqueio já catalogado no P11). **Consequência favorável:** com o distiller parado, o `verified_at` que o PT escrever **sobrevive por default** — o piloto roda SEM depender do distiller. Único fix imediato (~5 linhas): merge de frontmatter no `montarBriefing` pra não apagar o carimbo quando o distiller voltar. "Distiller consome o ledger" e "verified_at alimenta o Freshness Pipeline (`StalenessDetectorService`)" são **trabalho futuro escopado** (E8), não efeito colateral — o indexador só olha `mcp_memory_documents` hoje.

**Rollout (E11 — teto + sequenciamento):** **Peça A condicionada ao fechamento de S1 (watchdog) + S7 (distiller E3, 1º run real skim-aprovado) da Onda 1** (avaliação 2026-07-02) — não competir por capacidade já alocada. Então: piloto **Financeiro** (INVENTARIO denso, ~30/41 claims verificáveis) → **medir** a taxa de erro do skeptic na população de claims de **capacidade** (não herdar o 0% de âncoras-de-path do G5; meta <2% a validar) → expandir aos 10 módulos com CAPTERRA-FICHA. **Teto duro: ≤10 módulos/quinzena**; expansão além = nova decisão Wagner com custo em tokens no corpo (78×quinzenal ≈ campanha P10 2×/mês = insustentável). **Peça B pode ir já** (não compete por sessão), condicionada a E9 (investigar desuso do understand).

### Peça B — Refutador de análise de pedido

Pedido não-trivial (≥3 passos, toca Tier 0, ou ambíguo): `wagner-understand` produz a interpretação → **novo agent `analise-refutador`** ataca com 5 perguntas fixas:

1. Essa é a ÚNICA leitura do pedido? Qual a 2ª mais provável?
2. O que já EXISTE que isso duplica? (Glob/Grep + proibições §"Ideias descartadas")
3. Qual regra do PROTOCOLO/proibições isso tangencia?
4. Qual premissa não-validada a interpretação assumiu como fato?
5. Se executar essa leitura e Wagner quisesse a outra, qual o custo?

**Emendas E9/E-R11:** o refutador ataca só a **interpretação** (Q1/Q4/Q5); pra Q2/Q3 ele **audita se o `wagner-understand` respondeu**, não re-executa as buscas (senão erro correlacionado gerador↔refutador — lição G5). Gatilho **restrito**: *toca Tier 0 OU ambíguo OU ≥3 arquivos novos* (NÃO "≥3 passos" — amplo demais). Pedido dentro de escopo já pré-aprovado (R11 vigente) **pula o refutador**. FP budget medido no piloto (≤1 pergunta a cada 10 pedidos não-triviais, senão recalibra).

Síntese: ambiguidade REAL sobrevivente → 1 pergunta curta ao Wagner; interpretação sobrevive → executa direto (R11, sem pausa). Custo ~2 agents/pedido não-trivial; mata o plausível-mas-errado antes de virar PR revertido.

**Arquivos:** `.claude/agents/analise-refutador.md` + emenda ao `PROTOCOLO-WAGNER-SEMPRE.md` (**R15** — o protocolo já vai até R14; ver §Refutação: "pedido não-trivial = understand + refutador antes de executar").

**Peça B só lê memória, não escreve** — e ler é o trabalho dela: é o mecanismo que faz as memórias "não faça isso de novo" serem consultadas no momento do risco.

## Fronteiras Tier 0 (o que o PT NUNCA faz)

- ⛔ Editar ADR aceita (append-only — skeptic propõe errata, Wagner decide)
- ⛔ Corrigir claim no calado (veredicto → fila; humano vê antes)
- ⛔ Auto-mem privada (ADR 0061 — ledger e logs no git canônico)
- ⛔ Valores BRL / PII no ledger (estrutura sim, conteúdo sensível nunca)
- ⛔ Gate required novo (lei 0314 — fusão no GT-G3)

## Ordem de execução (após aceite)

1. Peça B (2 arquivos, ~1h, ganho imediato em toda sessão)
2. Ajuste do distiller (pré-req da Peça A)
3. Peça A piloto Financeiro (~1d: workflow + skill + parser + 1º run + fila)
4. Watchdog staleness (fecha defeito nº1 do SDD e já nasce servindo o PT)
5. Métricas PT armadas no GT-G3 após 3 medições

## Consequências

**Positivas:** memória com garantia de validade (quem verificou, quando, contra qual evidência); a mesma máquina que mantém o SDD honesto mantém o produto honesto; RAG da Jana pode preferir memória auditada; interpretação de pedido deixa de ser ponto cego.

**Negativas/custos:** ~1 run de workflow por módulo por quinzena (tokens); fila de triagem nova pro Wagner (mitigado: só `ILUSÓRIO`/`STALE` escalam); risco de skeptic falso-positivo irritar (mitigado: calibração piloto <2% + prova de checkout obrigatória).

**Rejeitadas:** (a) plano/roadmap paralelo — proibido §5/T6; (b) gate CI novo required — lei 0314; (c) corrigir BRIEFINGs automaticamente sem fila humana — troca erro silencioso por erro silencioso; (d) adotar framework externo (OPA/debate framework) — auditoria 2026-06-21 já classificou como regressivo.

## Decisões Wagner pendentes (nunca no calado)

1. Aceitar o stream PT como extensão do roadmap SDD (este ADR, já emendado E1-E11)?
2. **Armamento das métricas PT no GT-G3 required = checkbox SEPARADO** (E6): aceitar o stream ≠ aceitar que ele morda. Armar exige reconciliar a tensão com a 0314 (`charter_refs` demovido). Advisory-perene por default — OK?
3. Peça B vira regra **R15** do PROTOCOLO (não R12 — ocupado; E1) ou fica prática sem regra?
4. Cadência: quinzenal junto do `/sdd-avaliar` ou mensal? (teto ≤10 módulos/quinzena — E11)
5. Peça A **espera S1+S7 da Onda 1** fecharem (E11) — confirma o sequenciamento?
