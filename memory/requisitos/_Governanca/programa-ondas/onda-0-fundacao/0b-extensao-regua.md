---
titulo: Onda 0b — Extensão da régua (casos_coverage + dente de cálculo)
status: proposto
owner: W
criado: '2026-07-02'
etapa: onda-0-fundacao
related: ../PLANO-MESTRE.md
---

# Onda 0b — Extensão da régua por tela

## Objetivo

Plugar a dimensão de **comportamento/valor** na régua de tela que já existe, em vez de criar
régua nova. Hoje `screen-grade` mede só UX; `.casos.md` mede comportamento mas é ortogonal e
**não entra em nenhuma nota**. É esse desligamento que deixou a `/perfil` "ok" no visual e o
`Financeiro` com 82 e cálculo indefeso.

## Passos

### 1. Campo `casos_coverage` no scorecard de tela

Adicionar ao YAML `memory/governance/scorecards/screens/<mod>-<tela>.yaml`:

```yaml
screen: Sells/Create
nota: 88            # UX (screen-grade, inalterado)
casos_coverage:     # NOVO — cobertura de comportamento
  - uc: UC-S01
    desc: "Venda balcão a prazo gera título a receber"
    status: "✅"     # ✅ passa · 🧪 parcial · ⬜ sem teste
  - uc: UC-S02
    desc: "Cálculo de desconto fracionário não infla total"
    status: "⬜"
cobertura_uc: 50%   # derivado
```

Fonte da verdade: `scripts/casos-coverage-guard.mjs` (já existe) — cruza `.casos.md` ↔ testes.

### 2. Dimensão D1 (cálculo de valor) no método

Adicionar ao `memory/requisitos/_DesignSystem/SCREEN-GRADE-METODO.md` a dimensão D1 para telas/serviços
que tocam dinheiro: exige **property test** (`parse∘format==id`) + **golden fixtures** de borda
(desconto fracionário, arredondamento, devolução). Âncora: fintech QA 2026.

### 3. Foto lado a lado

`screen-grade` passa a exibir **UX (nota)** e **cobertura de comportamento (casos_coverage)** juntas —
a foto que hoje não existe. Tela pode ser 90 de UX e 0% de comportamento; isso fica **visível**.

## Critério de pronto

Um scorecard de exemplo (`sells-create.yaml`) mostra nota UX **+** `casos_coverage` com os UCs
órfãos visíveis.

## Verificação

- `node scripts/casos-coverage-guard.mjs` cruza casos↔telas sem erro.
- O scorecard de exemplo renderiza as duas leituras.

## Nota de design

**Plugar, não fundir** (recomendação do inventário de réguas 2026-07-02): as 3 réguas
continuam separadas (UX / estrutura / comportamento); a extensão só as **liga na foto por
tela**. Fundir confundiria "bonita" com "funciona".
