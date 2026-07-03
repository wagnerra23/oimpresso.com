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
- **modulador_peso_real** = 0.85-1.0 por contribuição à meta R$ [redacted Tier 0]M ([ADR 0232](../../decisions/0232-modelo-peso-real-classificacao-por-meta.md)) — tela de receita direta (Sells/Create, Larissa 99%) pesa cheio; tela de apoio raro modula pra baixo a urgência, não a nota

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

## 3-bis. Dimensão D1 — cálculo de valor (o "dente", plugar não fundir · Onda 0b / [ADR 0320](../../decisions/proposals/0320-programa-ondas-regua-correcao.md))

> **PLUGAR, NÃO FUNDIR.** As 16 dimensões medem **UX** (a tela é bonita/usável). D1 mede
> **valor** (a conta está certa). São réguas ortogonais: uma tela pode ser **90 de UX e ter
> cálculo indefeso** — o caso `Financeiro 82` / o incidente `num_uf` (valor inflado ~×100k,
> [proibicoes §CÁLCULO DE VALOR](../../proibicoes.md)). D1 **não entra** na fórmula da §1 (não
> dilui a nota de UX); é um **gate lado a lado**, exibido junto (§8). Fundir confundiria
> "bonita" com "funciona".

**Quando D1 se aplica:** toda tela/serviço que toca **dinheiro ou estoque** — preço, total,
subtotal, desconto, imposto, frete, `final_total`, pagamento, comissão, parsing/format de
número (`num_uf`/`parseDecimalPtBR`), quantidade/movimentação/reserva/baixa. Telas de leitura
pura (dashboards sem cálculo próprio, listagens) → D1 **n/a** (não penaliza).

**O que D1 exige (âncora: property-based + golden money datasets — fintech QA 2026):**

| Prova | O que é | Aceite |
|---|---|---|
| **P1 · Property test** | Invariante do round-trip de número: `parse ∘ format == id` (e `format ∘ parse == id` na precisão de 2 casas) sobre valores gerados | passa em N casos aleatórios incl. bordas de locale pt-BR (ponto/vírgula, milhar de 3 dígitos) |
| **P2 · Golden fixtures de borda** | Conjunto fixo entrada→saída esperada pros casos que já mordiram: **desconto fracionário** (`204.99605`), **arredondamento** (metade-par), **devolução/estorno**, subtotal com imposto | cada golden bate o número exato (não "≈") |
| **P3 · Cross-check 2 caminhos** | O total é provado por **dois caminhos independentes** (frontend×backend, ou recompute à mão) — pareia com a [regra-mestre de valor](../../proibicoes.md) | os dois caminhos concordam no centavo |

**Nível D1 por tela (separado do nível UX):**

| D1 | Significa |
|---|---|
| 🔴 **indefeso** | toca valor/estoque, **zero** prova de cálculo (P1/P2/P3 ausentes) — o estado do incidente `num_uf` |
| 🟡 **parcial** | tem golden OU property, falta o cross-check dos 2 caminhos (ou vice-versa) |
| 🟢 **defendido** | P1 + P2 + P3 verdes no CT100 (Pest/MySQL real, [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) |

> **Implementação de referência (o dente real):** Onda 1.4 — `property num_uf` + golden
> `calculateInvoiceTotal` + divergência de pagamento (Sells). D1 aqui é o **critério**; o teste
> que o satisfaz é código de módulo (não deste método). D1 🟢 exige rodar no CT100 — nunca local
> ([proibicoes §Testes no CT100](../../proibicoes.md)).

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

# ───────── NOVO (Onda 0b / ADR 0320): comportamento/valor AO LADO da UX ─────────
# PLUGAR não fundir — estes blocos NÃO entram na `nota` de UX; são a outra leitura da foto.
casos_coverage:                # cobertura de COMPORTAMENTO — espelha o trio <Tela>.casos.md
  fonte: resources/js/Pages/Sells/Create.casos.md   # source of truth
  guard: scripts/casos-coverage-guard.mjs           # quem cruza casos↔teste (ADR 0264)
  ucs:                         # 1 entrada por UC declarado no casos.md
    - uc: UC-S01
      desc: "Venda balcão a prazo (fiado) gera saldo devedor sem bloquear"
      status: "🧪"             # ✅ provado (manifesto verde) · 🧪 parcial · ⬜ sem teste · ❌ quebrou
  cobertura_uc: "0%"           # derivado: % de UCs declarados com Status ✅ (0 de 1 provado)
d1_calculo:                    # dente de cálculo (§3-bis) — só p/ tela que toca valor/estoque
  aplica: true                 # false → n/a (tela sem cálculo próprio)
  nivel: "🔴"                  # 🔴 indefeso · 🟡 parcial · 🟢 defendido (P1+P2+P3 no CT100)
  provas: { property: false, golden: false, cross_check: false }
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

---

## 8. Foto lado a lado — UX × comportamento (Onda 0b / [ADR 0320](../../decisions/proposals/0320-programa-ondas-regua-correcao.md))

O buraco que a Onda 0b fecha: **UX** (§1-3) e **comportamento** (`.casos.md`) eram
ortogonais e nunca apareciam na mesma foto — por isso `/perfil` era "ok" no visual e o
cálculo ficava indefeso. Agora as duas leituras vivem no mesmo scorecard (§5) e são exibidas
**juntas** por `scripts/qa/screen-grade-report.mjs` (`npm run screen-grade:report`):

```
Tela                     UX  Nível      Comportamento (casos)   D1 cálculo
Sells/Create             88  Leader     0% · UC 1 (🧪1)          🔴 indefeso
```

- **UX** vem do scorecard (`nota`/`nivel`) — LLM-as-judge, cacheado + ratcheteado (§7).
- **Comportamento** é **derivado ao vivo** do `<Tela>.casos.md` ao lado do `.tsx` (fonte da
  verdade, mesmo `uc-regex` do `casos-coverage-guard`) — a foto não pode mentir por YAML
  velho: se o `cobertura_uc` gravado no scorecard divergir do vivo, o report marca `⚠ drift`.
- **D1** vem do bloco `d1_calculo` (§3-bis).

Regra de leitura: **UX alto não compra comportamento** — uma tela pode ser Leader (88) e ter
`cobertura_uc: 0%` + D1 🔴. A foto expõe isso em vez de esconder; a evolução (fechar UC,
armar o dente) sobe a coluna de comportamento sem tocar a de UX. **Plugar, não fundir.**
