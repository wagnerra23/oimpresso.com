---
name: screen-grade
mission: "Substituir avaliação subjetiva de tela por (1) um Pré-Flight resolver que impede a IA de inventar/repetir erro e (2) nota objetiva 0-100 ponderada por persona e Peso Real — o `module-grade` aplicado por tela."
description: ATIVAR quando user pedir "nota da tela X", "gradear tela Y", "/screen-grade Sells/Create", "qual a maturidade da tela Z", "pré-flight da tela W", "screen-grade", "avaliar a tela de venda", OU ANTES de fazer/migrar/gradear qualquer `resources/js/Pages/<Mod>/<Tela>.tsx` (roda o Pré-Flight resolver pra não inventar token/Model/componente nem repetir anti-padrão F3). Carrega o método SCREEN-GRADE (16 dimensões, níveis Beginner→Champion, score-as-code YAML) + o resolvedor PRE-FLIGHT-TELA (4 blocos: identidade/não-inventar/não-repetir/validar) ancorados na tela-ouro `GOLDEN-REFERENCE.md`. Produz a nota + scorecard YAML + roadmap de gaps. NÃO edita a tela nem cria tasks sem aprovação humana — só resolve pré-requisitos e pontua.
type: process-skill
status: active
version: 1.0.0
trust_level: L1
owner: wagner
created_at: 2026-05-30
updated_at: 2026-05-30
charter_adr: 0230
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."
triggers_on:
  - "/screen-grade"
  - "/screen-grade {Mod}/{Tela}"
  - "nota da tela {X}"
  - "maturidade da tela {X}"
  - "gradear tela {X}"
  - "avaliar a tela {X}"
  - "pré-flight da tela {X}"
  - "pré-flight de tela"
  - "screen-grade"
  - "método screen-grade"
related_adrs: [0230, 0231, 0232, 0235, 0155, 0233, 0093, 0101, 0104, 0114]
related_skills: [avaliar-modulo, constituicao-ui-aware, charter-write, design-arte, cowork-prototype-replication, comparativo-do-modulo]
tier: B
---

# screen-grade — Pré-Flight de Tela + nota de maturidade /100

> **Linhagem:** Método Governance Scorecard ([ADR 0230](../../../memory/decisions/0230-metodo-governance-scorecard.md)) + especialista-por-área ([ADR 0231](../../../memory/decisions/0231-processo-trabalho-canonico-especialista-por-area.md)) + Peso Real ([ADR 0232](../../../memory/decisions/0232-modelo-peso-real-classificacao-por-meta.md)) + `framework-15-dimensoes.md` + DS v4 roxo ([ADR 0235](../../../memory/decisions/0235-ds-v4-accent-roxo-universal.md)). É o [`module-grade`](../avaliar-modulo/SKILL.md) aplicado **por tela**.
>
> **Docs canônicos que esta skill operacionaliza** (leia-os, não reinvente):
> - [`prototipo-ui/GOLDEN-REFERENCE.md`](../../../prototipo-ui/GOLDEN-REFERENCE.md) — tela-ouro `Sells/Create` + 10 regras binárias
> - [`prototipo-ui/PRE-FLIGHT-TELA.md`](../../../prototipo-ui/PRE-FLIGHT-TELA.md) — o resolvedor (4 blocos)
> - [`memory/requisitos/_DesignSystem/SCREEN-GRADE-METODO.md`](../../../memory/requisitos/_DesignSystem/SCREEN-GRADE-METODO.md) — método 16-dim + níveis + fórmula

## Quando ativar

1. **Antes de fazer/migrar/gradear** qualquer `resources/js/Pages/<Mod>/<Tela>.tsx` — roda o **Pré-Flight** (Parte 1) pra não inventar nem repetir erro. Sem pré-flight resolvido → não trabalha.
2. **Pedido de nota** — "nota da tela X", "/screen-grade Sells/Create", "maturidade da tela de venda".

> **Princípio (mata invenção):** o agente NUNCA monta o próprio contexto de cabeça. Dado o caminho da tela, **roda o resolvedor** → lê o pacote → trabalha. Nada fora do pacote pode ser inventado (ativação de memória no momento da decisão, [ADR 0233](../../../memory/decisions/0233-ativacao-memoria-momento-decisao.md)).

## Parte 1 — Pré-Flight resolver (read-only probing, antes de tocar a tela)

Dado o caminho da tela, materialize o pacote exato (4 blocos do `PRE-FLIGHT-TELA.md`):

- **A · IDENTIDADE** → arquétipo (form/lista/dashboard/kanban/detalhe/relatório/drawer) via golden + `padroes-tela/PT-0X`; persona dona via `_DesignSystem/personas-por-modulo.yml`; Peso Real via ADR 0232.
- **B · NÃO INVENTAR** → charter `<Tela>.charter.md` (se faltar, **PARA** e roda `charter-write`); componentes só de `REGISTRY_DS_COMPONENTES.md` → `@/Components/ui` (nunca hand-roll); tokens **DS v4 roxo `primary`** (zero `blue-*`/hex cru); Models/Controller/rotas reais via Glob+Read (não inventa `ChartOfAccount`); estrutura copiada do golden do arquétipo.
- **C · NÃO REPETIR ERRO** → injeta no contexto `LICOES_F3_FINANCEIRO_REJEITADO.md` (21 anti-padrões) + anti-patterns do próprio charter + `PRE-MERGE-UI.md` (AP1-AP8) + `memory/proibicoes.md §UI`.
- **D · VALIDAR** → 10 regras binárias + 16-dim + testes anti-regressão + smoke **biz=1** ([ADR 0101](../../../memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md)) + `ds:report` zero `ds/*`.

Saída do Pré-Flight: bullet "pacote da tela `<Mod>/<Tela>`" com arquétipo + persona + charter status + golden + tokens + lista de erros a evitar. **Determinístico:** `Sells/Create` → form + Larissa + charter live + golden form + v4 + anti-F3 + 4 gates.

## Parte 2 — Nota de maturidade /100

```
NOTA = Σ(dim_i × peso_persona_i) / Σ(peso_max) × 100 × modulador_peso_real
```

16 dimensões (15 do `framework-15-dimensoes.md` + **16. Pré-Flight conformance**: tem charter live? só `@/ui`? tokens v4? zero anti-padrão repetido?). Para cada dimensão fraca: comparar com **≥3 best-of-class** (Linear/Shopify/Stripe/Notion + Bling/Tiny BR) **com o mecanismo** (não basta citar). Níveis: 🥉 Beginner 0-49 · Developing 50-69 · 🥈 Advanced 70-84 · Leader 85-94 · 🏆 Champion 95-100.

**Automação futura (Passo 2 do método — ainda não existe):** `php artisan screen:grade <Mod>/<Tela> --detail` (espelho de `module:grade`, persiste o YAML). Enquanto o command não existe, esta skill roda o método **manualmente** e materializa o scorecard à mão.

## Saída score-as-code (sempre gerar)

```yaml
# memory/governance/scorecards/screens/<modulo>-<tela>.yaml
screen: Sells/Create
path: resources/js/Pages/Sells/Create.tsx
archetype: form
golden: Sells/Create        # âncora do arquétipo, ou o golden do tipo
persona: larissa
peso_real: 1.0
nivel: Champion
nota: 95
dimensoes: { density: 95, discoverability: 90, ... , preflight: 100 }
gaps: []                     # impacto×esforço; cada fix cria teste anti-regressão (Invariante A)
fonte_rtm: []                # cada achado cita a memória de origem (Invariante B)
```

## Como formatar a resposta

1. **Nota grande + nível** no topo (ex: "**Sells/Create: 95/100 — 🏆 Champion**").
2. Tabela 16 dimensões com score + peso persona.
3. Top gaps por impacto×esforço → ondas (com mecanismo do best-of-class em cada um).
4. Scorecard YAML.
5. Se for migrar/refazer a tela: **gate visual + Wagner aprova screenshot** antes de Edit (R2/R7) — esta skill **não edita Page nem cria task** sozinha (publication-policy).

## Guardrails

- ⛔ Não inventar token/Model/componente/padrão — só o que o Pré-Flight materializou.
- ⛔ Não pular o Pré-Flight pra "ir direto gradear" — a dimensão 16 mede exatamente isso.
- ⛔ Não tocar a tela-ouro (`Sells/Create`) nem qualquer Page sem charter + gate visual (zona [`LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)).
- ⛔ Smoke sempre **biz=1** (Wagner), nunca biz=4 (ROTA LIVRE / Larissa) — ADR 0101.
