# Arquiteto Adversarial — estrutura-estado-da-arte

**Data:** 2026-05-13 06:53 BRT
**Pergunta:** Como estruturar a ferramenta `estado-da-arte` no oimpresso (skill+subagent vs alternativas)?
**Status técnico:** ⚠️ **inconclusivo** — proposta v1 derrubada por 3 ataques P0; proposta v2 viável mas exige baseline empírico ANTES de promover pra Tier B.
**Dogfood:** este doc foi gerado rodando o próprio fluxo `estado-da-arte` sobre a decisão de criar o `estado-da-arte`. Validação meta.

---

## Fase 0 — Charter

```yaml
mission: "Definir estrutura canônica da ferramenta estado-da-arte no oimpresso pra que decisões arquiteturais saiam calibradas no cenário do projeto em ≤2 trocas humanas."
goals:
  - "Trigger por linguagem natural ('faça o estado da arte de X') sem comando explícito"
  - "Contexto isolado (sub-conversação) — não polui main thread"
  - "Diferenciação clara vs skills existentes (ultrareview/comparativo/charter-write)"
  - "ROI mensurável: taxa de revisão de decisão <90d cai pra <10%"
non_goals:
  - "Não automatiza implementação (Wagner aprova v2 politicamente antes)"
  - "Não substitui ADRs aceitas"
  - "Não vira gate obrigatório em todo PR"
anti_hooks:
  - "Não criar dependência runtime nova"
  - "Não duplicar skills existentes"
  - "Não violar ADR 0061 (doc final em local canônico)"
metrica_aceite:
  formula_v1: "(a) skill ativa por trigger natural ≥90% testes + (b) doc gerado tem 6 seções fase-a-fase + (c) Expert e Anti-Expert entregam outputs distintos"
  threshold_v1: "a∧b∧c obrigatório agora; d valida em 14d"
```

**Veredito do charter pelo Anti-Expert:** `metrica_aceite` mede atividade (telemetria de uso, forma documental, distintividade trivial) e não outcome (revisão <90d). **Charter foi reescrito na v2** (Fase 5).

---

## Fase 1 — Research

| # | Padrão | Fonte | Aplicabilidade oimpresso |
|---|---|---|---|
| 1 | Skills + Subagents combo | docs Claude Code oficiais | Canônico no projeto (mwart-comparative + Claude Design plugin). Skill auto-ativa por description; subagent isola via Task. |
| 2 | Reflexion | NeurIPS 2023 | Adversarial reflection +15-30% sem trocar modelo. Skill `ultrareview` já implementa tático. |
| 3 | Constitutional AI auto-critique | Anthropic 2022 | Mapeia pras 7 lentes Anti-Expert (multi-tenant, runtime, lock-in, sinal, LGPD, precedente, hipóteses). |

**Decisões análogas no projeto:**
- ADR 0089 + skill `comparativo-do-modulo` (research + gap analysis pra módulo)
- ADR 0094 §3 (Charter > Spec) + §4 (Loop fechado por métrica) + §5 (SoC brutal)
- ADR 0105 (cliente como sinal qualificado) — gate de admissibilidade
- Skill `meta-skill-roi-erp-autonomo` — critério de admissão de skill nova
- Skill `ads-route` (dormente, S5) — `decide(domain, intent, payload)` planejado pra decisões custosas

**Convergência:** ecossistema oimpresso já existe. Pergunta era "como compor", não "construir do zero".

---

## Fase 2 — Proposta v1 (Expert)

**Estrutura recomendada:** Skill + Subagent (combo).

- **Skill** `.claude/skills/estado-da-arte/SKILL.md` — trigger semântico ("faça o estado da arte de X"), orquestrador das 6 fases. Inclui Fase 0.5 (brief-fetch + decisions-search + memoria-search).
- **Subagent** `.claude/agents/anti-expert-arquitetural.md` — isolador de contexto pro Anti-Expert (evita viés de ancoragem do Expert).

**Variante implementada hoje:** subagent `estado-da-arte` que internamente spawna 2 sub-agents (Expert + Anti-Expert). Expert sugere variante mais enxuta (subagent só pro Anti-Expert).

**Justificativas:**
- Skill pura (alt 1) falha porque não isola Expert↔Anti-Expert
- Subagent puro (alt 2) falha porque não auto-ativa por linguagem natural
- Slash command (alt 4) redundante com trigger de skill
- Runbook manual (alt 5) viola princípio 4 (loop por métrica)
- Compor skills existentes (alt 6) é "ilusão de overlap" — ultrareview opera sobre diff, comparativo é módulo-escopado

**Trade-offs reconhecidos:**
- Custo 1.8-2.5x tokens vs skill pura
- Latência +30-90s
- Sem persistência cross-sessão (loop não retomável)
- Anti-Expert pode atacar com viés inverso (sempre achar falha)
- Não generaliza pra decisões não-arquiteturais

**Esforço estimado:** 90-120 min IA-pair (15-20h humano sem IA — fator 10x ADR 0106).

**Recomendação Expert final:** "construir combo. **Não fazer hoje se fila de decisões arquiteturais reais nas próximas 4 semanas é <3** — sem demanda real, vira gold-plating."

---

## Fase 3 — Ataques (Anti-Expert)

10 ataques cobrindo todas 7 lentes. Resumo executivo:

| # | Severidade | Lente | Ataque |
|---|---|---|---|
| 1 | **P0** | #7, #4 | Métrica mede atividade, não outcome |
| 2 | **P0** | #4, #7 | Sem baseline — viola ADR 0105 (cliente como sinal) |
| 3 | **P0** | #3, #6 | 4 níveis de indireção quebra debuggability |
| 4 | P1 | #3, #6 | Sobreposição com ultrareview + comparativo + ads-route |
| 5 | P1 | #2 | Custo Opus sem gate `meta-skill-roi-erp-autonomo` |
| 6 | P1 | #3 | Fase 0.5 duplica `brief-first` Tier A always-on |
| 7 | P1 | #6 | Output em `sessions/` é órfão (não ADR, não session log) |
| 8 | P1 | #1 (princípio), #3 | 6 responsabilidades = anti-SoC |
| 9 | P2 | #5 | WebSearch + cenário = vazamento PII potencial (LGPD) |
| 10 | P2 | #4, #7 | N=1 vira canon sem A/B |

**Cobertura:** 7/7 lentes obrigatórias tocadas. ✅

**Citações duras:**
> "A skill que se propõe a melhorar decisões arquiteturais não tem ADR mãe."
> "A skill que pretende calibrar decisões foi adotada com a falácia que ela mesma deveria detectar."
> "P0 #1 e #2 são suficientes pra rejeitar a proposta como está."

---

## Fase 4 — Refinação

| # | Veredito | Mudança em v2 |
|---|---|---|
| 1 (P0 métrica atividade) | **Aceito** | Métrica de aceite reescrita: "≥3 decisões processadas geram ADR aceita + zero superseded em <90d" (mede outcome real, não forma) |
| 2 (P0 sem baseline) | **Aceito** | Pré-requisito obrigatório ANTES de promover Tier B: rodar `decisions-search lifecycle:superseded since:2025-11-13` e calcular taxa de revisão atual. Se já <10%, **skill é solução sem problema → não promover** |
| 3 (P0 4 níveis indireção) | **Aceito** | Simplificar: skill spawna 2 sub-agents general-purpose direto (Expert + Anti-Expert) via Task — sem subagent intermediário. Logging detalhado de cada fase no doc final pra debuggability single-scroll |
| 4 (P1 sobreposição) | **Aceito parcial** | Decision tree explícito no SKILL.md: "diff/code → ultrareview; módulo specific → comparativo; decisão arquitetural ampla → estado-da-arte; chamada custosa pré-decidida → ads-route (S5)". Quando `ads-route` ativar, virar gate dela |
| 5 (P1 custo Opus) | **Aceito** | Skill começa com gate `decide()` rudimentar: triagem complexidade (≥3 alternativas + reversibilidade <X + sinal qualificado presente). Falhou triagem → sugere skill mais leve. Não roda Opus sem gate |
| 6 (P1 Fase 0.5 dup) | **Aceito** | Remover Fase 0.5 — `brief-first` já roda no SessionStart hook. Sub-agents recebem só problema + restrições; brief já tá no contexto do parent |
| 7 (P1 output órfão) | **Aceito** | Mudar output de `memory/sessions/` pra `memory/decisions-drafts/YYYY-MM-DD-arq-<slug>.md` com frontmatter Nygard `status: draft`. Wagner aprova → arquivo MOVE pra `memory/decisions/NNNN-<slug>.md` (mesma origem, vira ADR canon). Sem categoria órfã |
| 8 (P1 anti-SoC) | **Rejeitado** | SoC brutal = 1 responsabilidade = 1 conceito, não 1 arquivo. Skill faz UMA coisa: rodar loop adversarial pra decisão arquitetural. As 6 fases são internas como Pest tem Arrange-Act-Assert (sem violar SoC). |
| 9 (P2 PII WebSearch) | **Aceito** | Anti-hook explícito no prompt do sub-agent Expert: "NÃO inclua razão social/CPF/CNPJ em queries WebSearch — substituir por `<cliente-anônimo>`". Adicionar `PiiRedactor::redact()` no prompt template |
| 10 (P2 N=1 vira canon) | **Aceito** | Skill nasce `status: experimental` (não `active`). Promoção pra `active` exige: (a) ≥3 decisões processadas + (b) baseline taxa-revisão medido + (c) A/B com pelo menos 1 decisão tratada via skill mais leve pra comparar |

**Score de aceitação:** 9 aceitos (3 P0 + 5 P1 + 1 P2 + 1 parcial) / 1 rejeitado com razão = **proposta v1 substancialmente revisada**.

---

## Fase 5 — Proposta v2 + Métrica

### Proposta v2

**Estrutura simplificada (2 níveis):**

```
.claude/skills/estado-da-arte/
└── SKILL.md
    - status: experimental (não active até A/B passar)
    - gate decide() rudimentar (triagem complexidade)
    - decision tree vs ultrareview/comparativo/ads-route
    - spawn Expert + Anti-Expert via Task direto (sem subagent intermediário)
    - Sem Fase 0.5 (brief-first já cobre)
    - Output em memory/decisions-drafts/ (move pra decisions/ quando Wagner aprovar)
    - PiiRedactor obrigatório em prompt template Expert
```

**Remoção:** `.claude/agents/estado-da-arte.md` (subagent intermediário) — não necessário; skill spawna 2 sub-agents direto.

### Métrica de aceite reescrita

```yaml
metrica_aceite_v2:
  pre_requisito_obrigatorio:
    - "Baseline taxa-revisão <90d medido ANTES de promover Tier B"
    - "Se baseline atual já <10%, skill é solução sem problema — NÃO PROMOVER"
  metrica_outcome:
    - "≥3 decisões processadas pela skill nos próximos 60d"
    - "Zero das ADRs geradas foram superseded em <90d"
    - "≥1 caso Wagner explicitamente reportou 'usei e foi bom'"
  metrica_calibragem:
    - "Custo médio por invocação <80k tokens"
    - "Taxa de aprovação Wagner do doc gerado ≥60% (resto vira `inconclusivo` legítimo)"
  threshold_promocao_active: "Todas as 5 acima precisam estar ✅ pra mudar status de experimental → active"
```

### Score v2 contra métrica

**Score:** **inconclusivo no momento da escrita** — pré-requisito (baseline) não foi medido. Métrica de outcome requer 60d de uso. **Não posso declarar aprovada-tecnica honestamente.**

---

## Recomendação ao Wagner

### O que fazer AGORA

1. **Manter os arquivos criados hoje** (`.claude/skills/estado-da-arte/SKILL.md` + `.claude/agents/estado-da-arte.md`) **como experimental** — não promover pra Tier B, não anunciar como canon.

2. **Rodar baseline (5 min)** — antes de qualquer adoção:
   ```
   # Via MCP:
   decisions-search lifecycle:superseded
   # Calcular: das ADRs aceitas nos últimos 6 meses, quantas foram superseded em <90d?
   ```
   Se taxa atual já <10% → skill é solução sem problema. **Deletar e parar aqui.** Anti-Expert ataque #2 vence.

3. **Se baseline ≥10%, aplicar correções v2 nos arquivos:**
   - Reescrever `metrica_aceite` no SKILL.md (outcome, não atividade)
   - Adicionar gate `decide()` rudimentar
   - Remover Fase 0.5 (duplica brief-first)
   - Mudar output pra `memory/decisions-drafts/`
   - Adicionar PiiRedactor no prompt template
   - Marcar `status: experimental`
   - Deletar subagent intermediário (`.claude/agents/estado-da-arte.md`) — desnecessário

4. **Usar em 3 casos reais nos próximos 60d** — sugestões da memória atual:
   - Decisão "oimpresso é vertical ComVis ou horizontal modular B2B PME?" (tensão estratégica não-resolvida segundo MEMORY.md)
   - Decisão "Agrosys deal: aceitar comissão Artur 50% perpétua ou re-negociar?" (red flag financeiro documentado)
   - Decisão "WhatsApp Cloud API vs daemon Baileys CT 100 pra escala 4000 clientes Agrosys"

### Sinais de drift a monitorar

- ⚠️ Skill ativa em conversa casual ("estado da arte" como tique verbal de Wagner em handoffs) → R$2k-4k/mês desperdiçado (ataque #5)
- ⚠️ Output em `decisions-drafts/` acumula sem virar ADR → loop falhando em produzir decisão acionável
- ⚠️ Anti-Expert vira espelho do Expert (>70% overlap textual) → adversarial loop morreu (calibrar)

### Quando reabrir essa decisão

- Após 60d com 3+ usos reais → A/B real possível
- Se nova evidência paper/Anthropic mostrar pattern superior (ex: "single agent multi-turn self-critique")
- Se outro dev do time (Felipe/Maiara) reportar atrito ou paradoxo no decision tree de skills

---

## Pré-requisitos antes de implementar

1. **Baseline taxa-revisão medido** (5 min — query MCP)
2. **Decisão Wagner:** vale a pena promover skill experimental → active? Critério: baseline ≥10% E ≥3 decisões reais na fila em 4 semanas
3. **Aplicação das 9 correções v2** (15-20 min IA-pair) se aprovado
4. **ADR mãe da skill** (`memory/decisions/NNNN-skill-estado-da-arte-experimental.md`) com status `experimental` referenciando este doc — corrige ataque #7 (output órfão)

---

## Lição meta (dogfood)

O **próprio fluxo `estado-da-arte` funcionou** — pegou 3 P0 fatais que eu (Claude) não tinha visto sozinho ao construir. Especialmente:
- P0-2 (sem baseline) — eu inventei meta "<10%" sem medir, violando ADR 0105 que eu mesmo citei na skill
- P0-3 (4 níveis indireção) — design sobrecarregado por excesso de zelo

**Custo do loop:** ~100k tokens (2 sub-agents Opus + análise). Custo de ter promovido skill como Tier B sem rodar adversarial: provavelmente alto (lock-in + custo recorrente Opus + output órfão por meses).

ROI do dogfood = positivo só nesta execução. Mas N=1. Repetir em 2-3 decisões reais antes de afirmar valor.

---

**Wagner aprova essa v2 (com pré-requisitos)?**

Se sim: rodo baseline → aplico correções → marco `status: experimental` → uso em 1 caso real (sugiro tensão estratégica vertical-vs-horizontal, que está aberta há tempo). Se não: deleto os 2 arquivos criados, escrevo handoff descrevendo o que aprendi, e voltamos pra abordagem ad-hoc (Expert sugeriu: compor `ultrareview` + Task manual quando precisar).
