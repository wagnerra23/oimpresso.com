---
status: ativo
last_reviewed: "2026-06-06"
next_review: "2026-09-06"
related_adrs: [0230, 0231, 0232, 0250, 0254]
---

# SCREEN-GRADE — método de maturidade de tela (nota /100 comparativa)

> **O que é:** o `module-grade` ([ADR 0155](../../decisions/0155-rubrica-module-grade-v3.md)/[0160](../../decisions/0160-governance-v4-scoped-scorecards-buckets.md)) aplicado **por tela**. Nota 0-100 comparativa vs golden do arquétipo + ≥3 best-of-class, ponderada por persona e por Peso Real.
> **Linhagem:** Método Governance Scorecard ([ADR 0230](../../decisions/0230-metodo-governance-scorecard.md)) + processo especialista-por-área ([ADR 0231](../../decisions/0231-processo-trabalho-canonico-especialista-por-area.md)) + Peso Real ([ADR 0232](../../decisions/0232-modelo-peso-real-classificacao-por-meta.md)) + `framework-15-dimensoes.md` + escala de maturidade USWDS/Figma DesignOps + score-as-code Backstage Soundcheck/Cortex.
> **Origem:** 2026-05-30 (sessão `2026-05-30-screen-grade-metodo-estado-arte.md`). Candidata a ADR canon.

---

## 1. Fórmula

```
NOTA_TELA (0-100) = Σ(dim_i × peso_persona_i) / Σ(peso_persona_max) × 100 × modulador_peso_real
```

- **dim_i** = nota 0-100 da dimensão i (16 dimensões, §3)
- **peso_persona_i** = 1-3× pela persona dona da tela (tabela `framework-15-dimensoes.md`)
- **modulador_peso_real** = 0.85-1.0 por contribuição à meta R$5M ([ADR 0232](../../decisions/0232-modelo-peso-real-classificacao-por-meta.md)) — tela de receita direta (Sells/Create, Larissa 99%) pesa cheio; tela de apoio raro modula pra baixo a urgência, não a nota

---

## 2. Níveis nomeados (escala USWDS/Figma — cada tela cai num nível)

| Nível | Faixa | Significa |
|---|---|---|
| 🥉 **Beginner** | 0-49 | inventa/repete erro, sem charter, drift alto |
| **Developing** | 50-69 | charter existe, segue golden parcial, dívida `ds/*` |
| 🥈 **Advanced** | 70-84 | golden seguido, tokens v4, lint passando |
| **Leader** | 85-94 | 10 regras binárias 10/10 + testes + a11y AA |
| 🏆 **Champion** | 95-100 | benchmark do arquétipo + grade rodada em prod + ratchet verde |

> **Meta de programa:** Champion = grade rodada em **todas as 272 telas** (USWDS: *"scoring run on all live applications"*).

---

## 3. As 16 dimensões (15 do framework + Pré-Flight)

1-15 = `framework-15-dimensoes.md` (Density · Discoverability · Speed-to-task · Error recovery · Cognitive load · Aesthetic-usability · Affordance · Brand confidence · Mobile fit · A11y WCAG · i18n PT-BR · Performance perceived · Information hierarchy · Microcopy · Internal consistency).

**16. Pré-Flight conformance** (nova, [PRE-FLIGHT-TELA.md](../../../prototipo-ui/PRE-FLIGHT-TELA.md)): tem charter live? usa só `@/Components/ui`? tokens v4 (`primary`, zero `blue-*`)? zero anti-padrão repetido (LICOES_F3)? — mede objetivamente **"não inventou / não repetiu erro"**.

---

## 4. As 4 etapas por tela (ADR 0230)

1. **Dividir** — cada tela é uma unidade pontuável (o agente especialista pega um módulo).
2. **Pontuar** — 16 dim × persona, nível Beginner→Champion, **score-as-code** YAML (§5).
3. **Comparar com o melhor — e POR QUÊ** — cada dim fraca vs **≥3 best-of-class** (Linear/Shopify/Stripe/Notion + Bling/Tiny BR) **com o mecanismo** (não basta citar).
4. **Roadmap** — gaps por impacto×esforço → ondas → meta. **Invariante A:** todo fix cria teste anti-regressão com a justificativa medida. **Invariante B:** cada achado cita a memória de origem (RTM).

---

## 5. Saída score-as-code (template por tela)

```yaml
# memory/governance/scorecards/screens/<modulo>-<tela>.yaml
screen: Sells/Create
path: resources/js/Pages/Sells/Create.tsx
archetype: form
golden: Sells/Create          # é a própria âncora do arquétipo form
persona: larissa
peso_real: 1.0                 # receita direta, 99% volume
nota: 0                        # 0-100 (preenchido pelo agente)
nivel: ""                      # Beginner|Developing|Advanced|Leader|Champion
dimensoes:                     # 0-100 cada
  density: 0
  # ... 16 dimensões
  preflight_conformance: 0
gaps:
  - dim: ""
    nota: 0
    best_of_class: ""          # quem faz melhor
    porque: ""                 # o mecanismo (ADR 0230 etapa 3)
    fix: ""
    esforco: baixo|medio|alto
    origin: ""                 # memória de origem (RTM)
baseline_anterior: null        # ratchet — nota só sobe
```

Agregado → ranking das 272 + dimensão "Design Maturity" no **GovernanceV4** + `screen-grades-baseline.json` (ratchet).

---

## 6. Validação do método vs estado-da-arte (2026)

| Peça do método | SOTA equivalente | Nota | Gap |
|---|---|---:|---|
| Pré-Flight resolver | Spec-Kit grounding hooks + Anthropic JIT | 9/10 | virar tool, não checklist |
| Não-inventar (REGISTRY+token+Model) | Figma Code Connect / MCP | 8/10 | REGISTRY virar índice consultável |
| Não-repetir-erro (LICOES_F3) | Anthropic "erro no contexto" + memory | 9/10 | auto-injetar |
| screen-grade (16-dim×persona×PesoReal, score-as-code, ratchet) | USWDS/VA.gov + Soundcheck/Cortex | 9/10 | **superior** em ROI+persona |
| golden-por-arquétipo | reference implementation / golden path | 7/10 | faltam goldens dashboard/kanban/detalhe/relatório/drawer |
| especialistas paralelos | Anthropic sub-agents (framework 7) | 9/10 | — |
| enforcement (ratchet+GovernanceV4) | Soundcheck CI gate | 7/10 | ligar |

**Nota geral do método:** conceito **85/100 (Advanced→Leader)** · execução atual **40/100 (Developing)** · ponderada **~68/100**. Todos os gaps são **wiring** (doc→tool), nenhum é conceitual. Em 2 pontos (Peso Real ROI + code-first sem Figma) está **à frente** dos frameworks genéricos.

---

## 7. Como testar o modelo (validade de um scorecard / LLM-as-judge)

Um modelo de grade só vale se passar em 3 propriedades (literatura de scorecard + LLM-as-judge):

| Teste | Pergunta | Critério de aceite |
|---|---|---|
| **T1 · Confiabilidade** (test-retest) | gradar a MESMA tela 2-3× dá a mesma nota? | desvio σ ≤ **3 pts**. Se diverge muito → rubrica subjetiva, endurecer critérios binários |
| **T2 · Validade discriminante** | separa tela boa de ruim na direção certa? | gap grande: golden (Sells/Create) ≫ tela com drift alto |
| **T3 · Monotonicidade da evolução** | cada fix sobe a nota, e o teto bate no golden real? | S0 < S1 < S2 e S2(Champion) ≈ nota real do golden do arquétipo (calibração) |

### As 3 simulações por tela (a escada de evolução — "grave as evoluções")

Pra cada tela, 3 simulações **em paralelo** (1 agente especialista por tela, ADR 0231):

- **S0 · Estado atual** — grade as-is das 16 dim → nota + nível
- **S1 · Próxima onda** — top-3 fixes do roadmap aplicados → nota projetada
- **S2 · Champion** — teto do arquétipo (benchmark best-of-class) → nota projetada

### Ledger (grava a evolução)

`memory/governance/screen-grades-pilot.md` — tabela `tela × {arquétipo, persona, S0, S1, S2, ΔS0→S1, ΔS1→S2, nível S0→S2}` + nota de confiabilidade (T1) + veredito discriminante (T2) + monotonicidade (T3). Cada linha vira depois um `scorecards/screens/<tela>.yaml` com baseline ratchet (Invariante A — nota só sobe).
