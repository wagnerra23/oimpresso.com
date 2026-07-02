---
slug: 0319-product-truth-stream-adversario-modulo-analise
number: 319
title: "Product Truth (stream PT no roadmap SDD) — adversário refutador por módulo + refutador de análise de pedido"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-02"
accepted_at: ""
accepted_via: "PROPOSTA — origem: conversa Wagner 2026-07-02 ('quero saber se eu ja tenho os gaps por modulo? e se tenho um adversario refutador por modulo?' + 'notei que a ia tem um rendimento extraordinario se no processo de analise da solicitação tiver um adversario' + 'sim pode fazer'). Redação [CC]. Aguarda decisão Wagner."
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
  - 0089-capterra-ficha-canonica
pii: false
---

# ADR 0319 — Product Truth: adversário refutador por módulo (stream PT) + refutador de análise de pedido

## Contexto

O programa SDD fechou o loop adversarial pra **governança/processo** (avaliação 2026-07-02: composto 76/100, skeptics por stream, counterfactuals provados live no required). Mas a camada de **produto/módulo** ficou fora do loop:

1. **Gaps profundos só em ~10 de ~78 módulos** — CAPTERRA-FICHA existe em 10 módulos; CAPTERRA-INVENTARIO (bucket acionável ✅/🟡/❌) em ~5. O resto tem só a camada rasa (`module:grade` + seção Gaps do BRIEFING).
2. **Nenhum adversário refuta as afirmações de módulo.** O skeptic hoje opera por stream de governança (`sdd-avaliador-processo`, 7 streams), por diff (`/ultrareview`) e por âncora (refutador G5). Ninguém é pago pra provar que um "✅ aprovado" do INVENTARIO ou uma capacidade declarada no BRIEFING **mente**.
3. **Memória falsa se propaga pra IA.** A cadeia `BRIEFING → mcp_memory_documents → Meilisearch → brief/KbAnswerAgent` serve claims não-verificados pra Jana e pra toda sessão Claude. Caso real: `COMPARATIVO_CONCORRENCIA.md` da Jana ficou ~2 meses dizendo "nota 0 — não implementado" com a Jana live em prod (corrigido só em 2026-07-02, PR #3625, por pergunta manual do Wagner).
4. **O ganho adversarial na análise de pedido é observado mas não institucionalizado.** Wagner 2026-07-02: *"notei que a ia tem um rendimento extraordinário se no processo de análise da solicitação tiver um adversário"*. O `wagner-understand` decodifica o pedido, mas ninguém refuta a decodificação — interpretação errada executa com confiança (a falha mais cara: 3 PRs revertidos por leitura errada).

Fundamento (por que adversário funciona — literatura + evidência interna): assimetria gerador×verificador (CriticGPT 2024), painel diverso > juiz único (Cohere "Juries" 2024), Default-FAIL (arte-evidência 2026-05-17), e os casos internos: dogfood `estado-da-arte` pegou 3 P0 fatais (2026-05-13); review adversarial ancora-guard achou 33 modos de falha, Wagner-tinha-razão 33/33 (2026-06-30); trajetória do scorecard SDD 60→76 movida pelos skeptics. A auditoria `arte-governanca-sdd` (2026-06-21) confirmou: **nenhum player open combina armamento de métrica + adversário recorrente** — estender o padrão que já é superior ao mercado, não adotar framework externo.

## Decisão (proposta)

### Peça A — Stream **PT (Product Truth)** no roadmap SDD

Novo stream no `_ROADMAP.md` **existente** (extensão, nunca plano paralelo — proibições §"Ideias descartadas" 2026-06-05/T6): 1 skeptic por módulo refuta as afirmações escritas do módulo contra o código real.

**Workflow `/pt-avaliar <Modulo>` (4 fases):**

| Fase | O quê | Custo |
|---|---|---|
| **F0 Extrair** | Parser determinístico lê `BRIEFING.md` + `CAPTERRA-INVENTARIO.md` + `SPEC.md` (US done + âncoras `Implementado em:`) + charters → lista de claims verificáveis `{claim, fonte, arquivo:linha}` | barato, mecânico |
| **F1 Refutar** | 1 skeptic por módulo, instrução invertida ("prove que é FALSO, Default-FAIL"), verifica contra código real (`Modules/<X>/`, rotas, `phpunit.xml`, âncoras vivas). Prova de checkout obrigatória (`git rev-parse HEAD == origin/main` — lição da errata falso-positivo 2026-07-01). Veredicto por claim: `CONFIRMADO` (file:line) / `STALE` / `ILUSÓRIO` / `FALTA` | 1 agent/módulo; refutador de tier ≥ gerador |
| **F2 Score+ledger** | `pt_claim_accuracy = confirmados/(confirmados+stale+ilusórios)`; veredictos apendados em `governance/pt-ledger/<modulo>.json` (**append-only**, padrão ledger G5); BRIEFING frontmatter ganha `verified_at` + `claim_accuracy` | determinístico |
| **F3 Síntese** | Session log + `ILUSÓRIO`/`STALE` viram **tasks MCP** (`tasks-create`, ADR 0070 — nunca markdown) na fila do Wagner. **Nada corrigido no calado.** | fila humana |

**Arquivos:** `.claude/workflows/pt-avaliador-modulo.js` (espelha `sdd-avaliador-processo.js`) · `.claude/skills/pt-avaliar/SKILL.md` · `scripts/governance/pt-scorecard.mjs` (read-side) · `governance/pt-ledger/*.json` · § PT no `_ROADMAP.md`.

**Métricas — ZERO gate novo (lei de fusões ADR 0314):** `pt_claim_accuracy` e `pt_freshness` entram como métricas no scorecard do **GT-G3 já required**, armadas só após 3 medições reais (ADR 0275), catraca só-desce depois.

**Anti-decadência (3 mecanismos obrigatórios, senão o PT vira o teatro que caça):**
1. **Watchdog staleness**: `verified_at > 30d` → módulo cai pra `not_verified` (mesmo fix do defeito nº1 da avaliação 76 — implementar junto).
2. **Counterfactual no gate-selftest**: fixture com claim falsa injetada → skeptic TEM que pegar → senão selftest RED (vigia-dos-vigias, 46→47).
3. **Armamento antes de morder**: nunca pune no escuro.

**Integração com a memória (pré-requisito descoberto em revisão):** o BRIEFING é gerado pelo distiller (`jana:distill-module-truth`) — o PT escreve **só frontmatter**, e o distiller ganha 1 ajuste pra (a) **preservar** `verified_at`/`claim_accuracy` no re-destile e (b) idealmente **consumir o ledger** descontando claims `ILUSÓRIO` na próxima destilação. Efeito colateral: `verified_at` alimenta o Freshness Pipeline da Jana (`StalenessDetectorService`) — retrieval passa a poder preferir memória auditada.

**Rollout:** piloto **Financeiro** (tem INVENTARIO denso) → calibrar taxa de erro do skeptic (meta <2%, padrão G5) → expandir aos 10 módulos com CAPTERRA-FICHA → cadência quinzenal junto do `/sdd-avaliar` → demais módulos sob demanda/sinal (ADR 0105).

### Peça B — Refutador de análise de pedido

Pedido não-trivial (≥3 passos, toca Tier 0, ou ambíguo): `wagner-understand` produz a interpretação → **novo agent `analise-refutador`** ataca com 5 perguntas fixas:

1. Essa é a ÚNICA leitura do pedido? Qual a 2ª mais provável?
2. O que já EXISTE que isso duplica? (Glob/Grep + proibições §"Ideias descartadas")
3. Qual regra do PROTOCOLO/proibições isso tangencia?
4. Qual premissa não-validada a interpretação assumiu como fato?
5. Se executar essa leitura e Wagner quisesse a outra, qual o custo?

Síntese: ambiguidade REAL sobrevivente → 1 pergunta curta ao Wagner; interpretação sobrevive → executa direto (R11, sem pausa). Custo ~2 agents/pedido não-trivial; mata o plausível-mas-errado antes de virar PR revertido.

**Arquivos:** `.claude/agents/analise-refutador.md` + emenda ao `PROTOCOLO-WAGNER-SEMPRE.md` (R12: "pedido não-trivial = understand + refutador antes de executar").

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

1. Aceitar o stream PT como extensão do roadmap SDD (este ADR)?
2. Peça B vira regra R12 do PROTOCOLO (emenda) ou fica como prática sem regra?
3. Cadência: quinzenal junto do `/sdd-avaliar` ou mensal?
