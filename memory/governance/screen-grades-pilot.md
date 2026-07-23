---
id: governance-screen-grades-pilot
---

# Screen-Grade — Ledger do Piloto (evoluções gravadas)

> **Método:** [SCREEN-GRADE-METODO.md](../requisitos/_DesignSystem/SCREEN-GRADE-METODO.md) §7. 6 telas-piloto × 3 simulações (S0 atual → S1 próxima onda → S2 Champion), 6 agentes especialistas em paralelo (ADR 0231), code-first/READ-ONLY.
> **Data:** 2026-05-30 · sessão `2026-05-30-screen-grade-metodo-estado-arte.md` · PR #1991.

---

## Evoluções (S0 → S1 → S2)

| Tela | Arquétipo | Persona | S0 | nível S0 | S1 | S2 | nível S2 | ΔS0→S1 | ΔS1→S2 | T1 confiança |
|---|---|---|---:|---|---:|---:|---|---:|---:|---|
| **Sells/Create** | form | Larissa | **89** | Leader | 93 | 97 | Champion | +4 | +4 | alta |
| **Cliente/Create** | form | Larissa | **89** | Leader | 93 | 97 | Leader→Champion | +4 | +4 | alta |
| **Cliente/Index** | lista | Larissa+Eliana | **82** | Advanced | 89 | 95 | Champion | +7 | +6 | alta |
| **Sells/Edit** | form | Larissa | **71** | Advanced | 84 | 95 | Champion | +13 | +11 | média |
| **Financeiro/Unificado** | dashboard | Eliana | **71** | Advanced | 80 | 90 | Leader | +9 | +10 | média |
| **Repair/ProducaoOficina** | kanban | Técnico | **68** | Developing | 80 | 92 | Leader | +12 | +12 | média |

> Média S0 piloto: **78,3** · S1: **86,5** · S2: **94,3**.

---

## Os 3 testes de validade do modelo

### T1 · Confiabilidade (test-retest, σ ≤ 3) — ⚠️ PARCIAL
- **Alta** onde a dimensão é **binária/grep-able**: violações `ds/*`, header sticky, pills, footer, tokens v4, uso de `@/Components/ui` — reproduzem ±0-1pt.
- **Média** nas dimensões **perceptuais** (mobile_fit, a11y, aesthetic, cognitive_load) — gradadas code-only (READ-ONLY, sem screenshot/Lighthouse/cronômetro de tarefa). Os 3 agentes "média" citaram o MESMO motivo: σ pode passar de 3pt nessas dims sem smoke real.
- **Veredito:** o modelo é confiável onde é objetivo; as dims perceptuais **exigem o upgrade #5 (visual-regression + Chrome MCP smoke)** pra fechar σ≤3. Isso confirma o roadmap, não o contradiz.

### T2 · Validade discriminante — ✅ PASS
- `Sells/Create` (89, golden) **≫** `Sells/Edit` (71) — **mesmo arquétipo, mesma persona**, gap de 18 explicado por 7 elementos nativos + `rounded-xl` que o Edit replicou e o Create não tem. Direção correta.
- As notas mais baixas (Repair 68 Developing, Financeiro 71) são exatamente as de **menor adoção de DS / mais drift** — o modelo aponta a fraqueza certa.

### T3 · Monotonicidade da evolução — ✅ PASS
- **Todas** as 6 telas: S0 < S1 < S2 (crescente, sem exceção).
- **Calibração correta:** telas já-boas (Sells/Create, Cliente/Create) têm Δ pequeno (+4/+4, pouco espaço) e teto 97; telas com drift (Sells/Edit, Repair, Financeiro) têm Δ grande (+11 a +12, muito a ganhar). O modelo mede "espaço de evolução" corretamente.
- **Teto por arquétipo:** forms capam 95-97 (calibrado contra golden Sells/Create); **dashboard capa em 90 — sinalizado pelo agente porque NÃO existe golden-dashboard pra calibrar** (gap conhecido do método).

---

## Meta-achado (o "não inventar / não repetir" funcionou)

Dois agentes **refutaram a documentação anterior** lendo o código real (code-first):
- **Cliente/Index:** a `MATRIZ_MIGRACAO_DS` listava "modais hand-rolled fixed inset-0 ~2301/2445" → na verdade são `CommandPalette`/`CheatSheet` (⌘K/?, `fixed inset-0` é o padrão correto, não drift); "select nativo ~2150" → é `<Select>` shadcn (falso-positivo); `rounded-xl` → hoje já é `rounded-lg`. Os drifts **reais** são `STATUS_STYLE` com bg-fill + header/filtros hand-rolled.
- **Financeiro/Unificado:** separou "A+ 9,75 visual" (estética) de "adoção real de DS" (45/100 preflight) — exatamente a confusão E3 do diagnóstico inicial. Confirmou ~25 violações `ds/no-adhoc-status-text` (pior arquivo do projeto) + bundle CSS paralelo de 8.663 LOC.

**Conclusão:** o método code-first pega erro de doc e separa estética de adoção — as duas dores originais do Wagner.

---

## Veredito do piloto

✅ **Modelo validado pra escalar.** T2 e T3 passam limpo; T1 passa nas dims objetivas e mapeia exatamente onde precisa de smoke real (upgrade #5). O método discrimina, é monotônico, calibra teto por arquétipo, e o pré-flight pegou erro de documentação.

**Antes de rodar nas 272:**
1. Eleger **golden-dashboard** (calibra teto — hoje estimado vs Stripe/Linear externo).
2. Ligar **Chrome MCP smoke** nas 4 dims perceptuais (fecha T1 σ≤3).
3. `ScreenGradeCommand` persiste cada linha como `scorecards/screens/<tela>.yaml` + baseline ratchet.

**Top fixes baratos já revelados (Δ alto, esforço baixo):** Sells/Edit 7 nativos→`@/ui` (+13); Repair colunas flex + drag-handle (+12); Financeiro ~25 `ds/no-adhoc-status-text`→`<Badge>` (+9).
