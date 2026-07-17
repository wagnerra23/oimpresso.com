---
slug: 0101-sistema-charter-capterra-governanca-escopo
number: 101
title: "Sistema Charter-Capterra — governança de escopo em 2 níveis × 3 eixos"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
quarter: 2026-Q2
decided_at: "2026-05-07"
decided_by: [W]
accepted_at: 2026-05-07
accepted_by: wagner
module: governance
tier: CANON
related_adrs: [0089, 0094, 0095]
parent_charter: mission.charter-system
authors: [wagner, opus]
---

# ADR 0101 — Sistema Charter-Capterra (governança de escopo em 2 níveis × 3 eixos)

> **Status:** ✅ ACEITA em 2026-05-07 por Wagner ("adr 0101 aprovado onda b liberada ficou otimo").
>
> Operacionaliza o **princípio #3 da Constituição V2** ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md): "Charter > Spec") combinando-o com o **padrão Capterra-driven** ([ADR 0089](0089-capterra-driven-module-evolution.md)) já em uso. Skill `charter-first` (Tier A dormente) acorda quando a primeira ferramenta `charter-fetch` for entregue.

---

## Contexto

A Constituição V2 declarou "Charter > Spec" como princípio #3 mas a camada L6 (Charters) está marcada 🔲 S4 — não há ferramenta nem template. A única governança de escopo que opera hoje é o Capterra-inventário ([ADR 0089](0089-capterra-driven-module-evolution.md)) — e ela vive **só no nível módulo**.

Wagner, em sessão de diagnóstico de degradação 2026-05-07, articulou:

1. **Capterra continua necessário** — comparativo com mercado evita perda de foco
2. **Telas prontas precisam contrato vivo** — sem charter, agente "estende escopo pra ajudar" e introduz drift
3. **Itens negativos (Non-Goals) reduzem alucinação** — intuição correta, com base científica (ver §Justificativa)
4. **Foco em usabilidade + automação** — Capterra v1 só compara features; falta ergonomia + autonomia

A degradação observada (sinais 1–6 do diagnóstico de 2026-05-07) tem raiz comum: **escopo não é contrato auditável, é prosa em vários lugares**. SPEC.md vira lista de US, RUNBOOK.md vira receita, CHANGELOG.md vira histórico — nenhum dos três responde "**o que essa tela é, o que NÃO é, e como saber se quebrou**" em <30s.

Mercado em 2026 convergiu em algumas práticas:

- **Anthropic Skills SDK** — frontmatter com `description`, `triggers_on`, `does_not_trigger_on` (Non-Goals operacionais)
- **Cursor Rules** — contratos `.cursor/rules/*.mdc` por feature/diretório
- **Cognition agent constitutions** — Goals + Anti-Goals em frontmatter de cada agente
- **OpenAI model spec** — "Behavior contracts" com What it does / What it does NOT do explícitos
- **Convergência distribuída** entre [Goal Drift research arXiv 2505.02709](https://arxiv.org/html/2505.02709v1) (jul/2025), Cursor `does_not_trigger_on`, OpenAI Spec, e Anthropic Skills SDK: listar Non-Goals reduz drift mensuravelmente. Magnitude exata **não consolidada em paper canônico** — virou alvo de medição própria via M4 (Goal Drift Rate) no Sprint S6 F4

Convergência forte. Oimpresso já tem 1/3 do caminho (Capterra). Falta operacionalizar Charter no nível tela, integrado com Capterra no nível módulo, e estender ambos pra **3 eixos** (Features + UX + Automação).

---

## Decisão

Adotamos o **Sistema Charter-Capterra** — uma matriz **2 níveis × 3 eixos** com **4 contratos técnicos versionados** e **5 camadas de segurança**.

### A matriz de governança

```
                Features         Usabilidade       Automação
              (o que faz)        (como faz)       (sem humano)
            ┌─────────────────────────────────────────────────┐
NÍVEL       │ CAPTERRA-FICHA  │ ux_heuristics  │ automation_  │
MÓDULO      │   .capacidades  │  + targets P0  │  targets P0  │
(mercado)   │ → 3 buckets     │ → 3 buckets    │ → 3 buckets  │
            │   ✅🟡❌         │   ✅🟡❌        │   ✅🟡❌      │
            │ → US no SPEC    │ → US no SPEC   │ → US no SPEC │
            ├─────────────────────────────────────────────────┤
NÍVEL       │ Goals /         │ UX targets /   │ Automation   │
TELA        │ Non-Goals       │ Anti-patterns  │ Hooks /      │
(interno)   │                 │                │ Anti-hooks   │
            │ → Pest GUARD    │ → Pest GUARD   │ → Pest GUARD │
            │ → CI gate       │ → CI gate      │ → CI gate    │
            └─────────────────────────────────────────────────┘
                  ↑                  ↑                ↑
                  └──────────────────┴────────────────┘
                          charter:health daily
                       (drift detector + ratchet)
```

### Os 4 contratos técnicos

| # | Artefato | Localização | Quem mantém | Frequência |
|---|---|---|---|---|
| 1 | `CAPTERRA-FICHA.md` v2 (3 seções) | `memory/requisitos/{Modulo}/` | Wagner (curadoria) | Trimestral |
| 2 | `CAPTERRA-INVENTARIO.md` v2 (3 eixos) | `memory/requisitos/{Modulo}/` | Skill regenera | Mensal ou cycle |
| 3 | `*.charter.md` (Page Charter) | Ao lado do `.tsx` | Owner da tela | A cada PR que toca a tela |
| 4 | `charter:health` (artisan command) | `Modules/Governance/` | Sistema (cron) | Daily 06:00 BRT |

### Estrutura de Page Charter (frontmatter + 7 seções)

```yaml
---
page: /repair/dashboard
component: resources/js/Pages/Repair/Dashboard/Index.tsx
owner: wagner
status: live | wip | sunsetting
last_validated: 2026-05-07
parent_module: Repair
parent_capterra: memory/requisitos/Repair/CAPTERRA-FICHA.md
related_adrs: [0101]
tier: A | B | C
---

## Mission (1 frase)

## Goals — Features (faz)

## Non-Goals — Features (NÃO faz)        ← anti-alucinação enforced

## UX Targets                              ← usabilidade quantitativa

## UX Anti-patterns                        ← anti-padrões UX (modal indevido, etc.)

## Automation Hooks                        ← o que a tela dispara automático

## Automation Anti-hooks                   ← o que NUNCA dispara

## Métricas vivas (Pest GUARD)
```

Ver exemplo completo em [resources/js/Pages/Repair/Dashboard/Index.charter.md](../../resources/js/Pages/Repair/Dashboard/Index.charter.md) (rascunho gerado junto a esta ADR).

### Os 3 eixos do Capterra v2

```yaml
# CAPTERRA-FICHA.md v2 — fragmento exemplo (RecurringBilling)
capacidades:
  - id: boleto-registrado
    nome: Boleto registrado API
    score: P0
    auditoria: "driver instanciável + cert válido + teste sandbox"

ux_heuristics:                    # ← NOVO em v2
  - id: emit-nfe-clicks
    nome: "Cliques pra emitir NFe a partir de boleto pago"
    score: P0
    benchmark: "Asaas: 1 (auto). Iugu: 5. Vindi: 3."
    target: "<= 2"
    metrica: "navegacao_steps_emit_nfe"

automation_targets:               # ← NOVO em v2
  - id: nfe-on-boleto-paid
    nome: "Auto-emitir NFe quando boleto pago"
    score: P0
    benchmark: "Asaas SIM, Iugu SIM, Vindi PARCIAL"
    target: "Listener invoice.paid → EmitirNfeJob, p95 < 30s"
    metrica: "auto_nfe_p95_seconds"
```

### As 4 ferramentas + extras (build em 3 fases — Sprint S6)

| # | Ferramenta | Tipo | O que faz | Fase |
|---|---|---|---|---|
| 1 | `charter-fetch <page>` | tool MCP | Carrega charter (~500 tok) em vez de CLAUDE.md (~30k) | F1 |
| 2 | `charter-validate <page>` | Pest test | Roda charter como contrato; viola = CI quebra | F1 |
| 3 | `charter-audit <module>` | artisan | Lista telas sem charter + charters >30d | F2 |
| 4 | `charter-write <page>` | skill + agent | Lê código + gera draft pra Wagner revisar | F2 |
| + | `charter:health` | cron daily | Métrica daily ratchet (igual `jana:health-check`) | F3 |
| + | Skill `comparativo` v2 | atualização | 3 eixos (features+ux+automação) | F3 |

### As 5 camadas de segurança

| Camada | Mecanismo | Anti-padrão que neutraliza |
|---|---|---|
| L1 — Append-only | Charter nunca editado em-place — supersede com `*.charter-v2.md` | Drift silencioso |
| L2 — Owner sigado | `owner:` no frontmatter; CI exige aprovação dele em PR que toca a tela | Mudança sem dono |
| L3 — Pest GUARD | Cada Non-Goal vira `it("não faz X")` test que falha se a tela faz | Alucinação ("agente quis ajudar e estendeu") |
| L4 — Métrica daily | `charter:health` 06:00 BRT alerta drift charter ↔ tela | Charter velho virando ficção |
| L5 — Ratchet baseline | Dívida atual aceita; CI só falha se piorar | Regressão silenciosa |

---

## Justificativa

### Por que 2 níveis (não 1)
- Capterra responde "o mercado faz X?" — pergunta de produto, escala módulo
- Charter responde "essa tela faz X?" — pergunta de contrato, escala componente
- Sem Capterra: charter vira ficção interna sem lente de mercado
- Sem Charter: Capterra detecta gap mas time não tem onde "agendar" o contrato vivo da feature implementada

### Por que 3 eixos (não 1)
- Capterra v1 mede só features → competidor com mesmas features mas UX 5× pior parece "empate"
- Usabilidade é onde oimpresso pode liderar (com IA: cliques < 2 vs concorrente 5)
- Automação é onde ERP vira valor real (R$ [redacted Tier 0]mi/ano só com auto-emissão fiscal pós-pagamento — meta [ADR 0022](0022-meta-5mi-ano-financeira.md))

### Por que Non-Goals como contrato (não opcional)
- **Convergência distribuída** entre Goal Drift research, Cursor `does_not_trigger_on`, OpenAI Spec e Anthropic Skills SDK indica que listar Non-Goals reduz drift mensuravelmente. Magnitude exata não consolidada em paper canônico — alvo de medição própria via M4 (Goal Drift Rate) em F4
- Intuição de Wagner é correta: agente LLM tende a "estender escopo pra ser útil"; Non-Goals dá permissão pra parar
- Quando vira Pest test, fica enforced — não só sugestivo

### Por que append-only (não editar)
- Charter é decisão; histórico precisa sobreviver auditoria
- ADR 0094 §princípio #5 (SoC brutal) — uma coisa, um lugar
- Git diff já é histórico — `*.charter-v2.md` torna explícito o que é nova versão

### Por que skill `charter-first` Tier A
- Substitui leitura do CLAUDE.md (~30k tok) por leitura do charter da tela mexida (~500 tok)
- Economia ~90% por sessão de tela = mais sessões viáveis com mesmo budget
- Convergência com `brief-first` que já é Tier A — mesma lógica de "carrega só o que importa"

### Por que paralelo com agentes (Anthropic 2026)
- `Agent({ isolation: "worktree" })` cria git worktree por agente
- 3-5 paralelos máximo (custo crescente; sincronização piora >5)
- Trabalho INDEPENDENTE só (charters de telas que não importam uma a outra)
- Briefing self-contained (agente não sabe da conversa)
- Custo ~+20% token vs sequencial, mas ~4× wall-clock melhor

---

## Consequências

### Positivas
- Single contract per page — IA, dev, owner consultam mesma referência
- Anti-alucinação enforced (Pest, não sugestão)
- Capterra v2 cobre mercado em 3 dimensões (features + UX + auto) — diferencial real
- Token /sessão -90% em tela com charter ativo
- Fecha lacuna de princípio #3 da V2 (Charter > Spec deixa de ser dormente)

### Negativas / Trade-offs
- ~33h em 1 sprint (S6) pra entregar sistema completo
- Toda tela em prod precisa de charter (~120 telas) — não é viável tudo de uma vez; tier-driven
- Atualizar charter virou requisito de PR — atrito (mitigado por CI helpful, não punitivo)
- Capterra v2 exige ficha por módulo com 3 eixos — re-curadoria das 5 fichas existentes

### Mitigações
- Tier A/B/C de charters (mesma lógica de Skills) — Tier A = telas em prod com bug; Tier C = legacy Blade
- Skill `charter-write` lê código e gera draft (Wagner só revisa)
- Ratchet baseline aceita dívida atual; CI gate só pra novo
- 3 fases de 1 semana cada — sem big-bang

---

## Métricas de sucesso (mede 30 dias após F3)

| Métrica | Como mede | Alvo |
|---|---|---|
| Charters Tier A escritos | `find resources/js/Pages -name "*.charter.md"` | ≥10 (top telas em prod) |
| Cobertura Pest GUARD por charter | linhas charter / linhas test | ≥80% |
| `charter:health` alertas/dia | `mcp_audit_log` | <2 |
| Token médio /sessão tela com charter | `mcp_audit_log` agg | -50% vs sem charter |
| Skill `charter-first` ativações/sessão tela | hook telemetry | ≥70% |
| Capterra v2 fichas convertidas | grep `automation_targets:` em fichas | 5/5 (RB, Fin, NfeBrasil, Repair, Project) |
| Drift detectado em PR antes de merge | CI logs | ≥90% dos charter violations |

---

## Onde NÃO inventar (Tier 0 dentro deste sistema)

- ❌ Editar charter aceito em-place — sempre supersede
- ❌ Charter sem `owner:` — CI bloqueia
- ❌ Pest GUARD comentado/skipado sem ADR — drift escondido
- ❌ `CAPTERRA-INVENTARIO.md` editado à mão — só skill regenera
- ❌ Charter sem `last_validated` — bloqueia CI

---

## Implementação (Sprint S6 — 4 fases × ~1 semana cada)

Plano detalhado em [memory/sprints/s6-charter-capterra/README.md](../sprints/s6-charter-capterra/README.md).

Resumo:

```
F1 (sem 1) — Foundation                ~12h
  · Template _TEMPLATE_charter.md
  · 5 charters Tier A (telas em prod)
  · charter-fetch tool MCP
  · Pest GUARD test runner
  · CI gate (workflow)

F2 (sem 2) — Tooling                   ~10h
  · charter-audit artisan
  · charter-write skill + agent
  · Skill charter-first ativada Tier A
  · charter:health daily cron
  · Ratchet baseline aceita

F3 (sem 3) — Capterra v2               ~11h
  · Skill comparativo v2 (3 eixos)
  · 5 fichas convertidas (RB+Fin+NfeBrasil+Repair+Project)
  · 1 inventário v2 gerado (RecurringBilling)

F4 (sem 4) — Performance Testing       ~6h
  · 6 métricas (M1 token economy, M2 GUARD pass rate,
    M3 charter coverage, M4 goal drift rate,
    M5 detector latency, M6 anti-hallucination ratchet)
  · 3 níveis automação (L1 detect / L2 propose / L3 self-improve)
  · Pest agregadores + dashboard /copiloto/admin/qualidade
  · Postmortem com baseline + alvos
```

---

## Referências

- [ADR 0089](0089-capterra-driven-module-evolution.md) — Capterra-driven (pai do nível módulo)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição V2 §princípio #3 (Charter > Spec)
- [ADR 0095](0095-skills-tiers-convencao-interna.md) — Skills Tier A/B/C
- Skill `charter-first` (Tier A dormente) — acorda em F1
- Skill `comparativo-do-modulo` v1.0 → v2.0 em F3
- [arXiv 2505.02709](https://arxiv.org/html/2505.02709v1) — Goal Drift in Language Model Agents (Anthropic, jul/2025) — base teórica pra M4 Goal Drift Rate
- Anthropic Skills SDK — `triggers_on`/`does_not_trigger_on` como inspiração de Non-Goals operacionais
- Sessão de diagnóstico 2026-05-07 (Wagner ↔ Opus) — origem desta ADR

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-07 | Wagner + Opus | ADR draft em sessão de diagnóstico (Onda C+ do plano de organização) |
| 2026-05-07 | Wagner | ✅ ACEITA. Correções aplicadas: trecho NeurIPS substituído por "convergência distribuída" (sem paper canônico — virou M4 alvo de medição). F4 Performance Testing adicionada ao Sprint S6 (~6h, 6 métricas + 3 níveis de automação) na mesma sessão. |
