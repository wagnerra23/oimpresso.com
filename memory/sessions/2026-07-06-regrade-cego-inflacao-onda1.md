---
date: "2026-07-06"
topic: "Re-grade CEGO de 10 telas — inflação da Onda 1 CONFIRMADA (média −13.7 pontos, 10/10 abaixo do registro)"
authors: [W, C]
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Re-grade cego · teste de inflação da Onda 1 (2026-07-06)

> **TL;DR:** 2 avaliadores independentes re-notaram 10 telas SEM acesso aos scorecards
> existentes (proibição explícita; método SCREEN-GRADE 16-dim; leitura integral de
> .tsx+charter+casos+Controller em `origin/main`). Resultado: **10/10 telas abaixo do
> registro, média −13.7 pontos, 9/10 além do σ≤3 do método**. A hipótese do adversário
> (V4b — regrade 2026-07-05 ancorado na nota antiga, sem abrir tela, 0↓ em 217 = viés
> estrutural) está **CONFIRMADA**. Bônus: o cego achou 1 bug real de prod (Ponto/Dashboard).

## A tabela

| Tela | Registro (Onda 1) | Cego | Δ |
|---|---:|---:|---:|
| Sells/Index | 91 | 76 | **−15** |
| Financeiro/Unificado/Index | 92 | 78 | **−14** |
| Ponto/Dashboard/Index | 85 | 64 | **−21** |
| Cliente/Index | 87 | 77 | **−10** |
| Admin/FeatureFlags/Index | 73 | 70 | −3 |
| Repair/Index | 78 | 61 | **−17** |
| Whatsapp/Settings | 86 | 71 | **−15** |
| Jana/Dashboard | 76 | 60 | **−16** |
| kb/Index | 80 | 63 | **−17** |
| ads/Admin/Confidence | 78 | 69 | **−9** |
| **média** | **82.6** | **68.9** | **−13.7** |

Critério pré-registrado: divergência >3 (o σ test-retest do método) = Onda 1 suspeita.
**9/10 divergem >3.** A única dentro do σ (Admin/FF, −3) é justamente uma tela que a
Onda 1 manteve baixa (73).

## Caveat honesto (viés do experimento)

Os avaliadores cegos foram instruídos "seja rigoroso, não seja gentil, o experimento mede
inflação" — isso puxa pra BAIXO (demand characteristics). A verdade provavelmente está
entre 68.9 e 82.6. **Mas** a direção e a magnitude não se explicam só por isso:

1. Os cegos citaram defeitos CONCRETOS e verificáveis que notas 85-92 não comportam:
   - **Ponto/Dashboard (85→64): BUG REAL** — Controller entrega TODAS as props via
     `Inertia::defer` (DashboardController:27-29) mas a página NÃO importa `<Deferred>`,
     sem fallback, destructuring sem default (linha 98) e uso direto (`kpis.colaboradores_ativos`
     linha 148) → `kpis === undefined` no first render → crash estrutural provável.
     Verificado por mim no origin/main após o relatório. Task de fix spawnada.
   - Sells/Index (91→76): fetch engole erro em silêncio (tela vira "sem vendas"); KPIs
     computados só sobre a página de 50 rows (número global mentiroso); sparkline fake.
   - Jana/Dashboard (76→60): **viola anti-hook do próprio charter** ("⛔ cálculo de farol
     no frontend" — `calcularFarol` roda no client); farol só por cor (WCAG 1.4.1); KPIs mock em prod.
   - kb/Index (80→63): `dangerouslySetInnerHTML` na paginação; cores cruas; charter promete
     tri-pane/⌘K que a tela não tem.
   - Repair/Index (78→61): header exibe "Listagem MWART (Sprint 2). Port 1:1 da tela Blade"
     pro OPERADOR em prod; sem charter/casos.
   - ads/Confidence (78→69): Controller lê `mcp_confidence_scores` só com `auth` — sem
     permissão nem escopo de tenant visível no código da tela.
2. O mecanismo da inflação é estrutural, não má-fé: a Onda 1 ancorou na nota antiga +
   "descer exige justificar" + catraca = subir barato, descer caro → 0↓/217.

## Consequências pra máquina MV

- A fila do metabolismo prioriza por `(100 − nota)` — **nota inflada suprime tela ruim da
  fila** ("verde+fresca pula"). kb/Index a 80 pula ciclo; a 63 estaria na fila.
- O ratchet recém-ligado (screen-grades-ratchet.yml) **trava a inflação como piso**:
  corrigir pra baixo agora exige `SCREEN_RATCHET_ALLOW_REGRESSION=1` + OK Wagner — que é
  exatamente a válvula de regressão consciente funcionando.

## Recomendações (decisão Wagner)

1. **Campo `evidence:` no scorecard** (`git-log-only` | `code-blind` | `browser`) — nota
   sem evidência de render vale menos; o metabolismo pode ponderar.
2. **Correção gradual, não em massa**: cada tela que entrar em batch MV ganha re-grade
   honesto (com browser/smoke) DENTRO do ciclo — a nota converge pro real em semanas, sem
   um "dia da correção" que quebre 217 baselines de uma vez.
3. **Regra pro próximo regrade em massa**: NUNCA ancorar na nota antiga; amostra cega de
   controle (~5%) obrigatória; `0 quedas` em lote grande = alarme, não sucesso.
4. Fix do Ponto/Dashboard (task spawnada) — smoke prod primeiro; regravar a nota com
   regressão consciente.

## Método (reprodutibilidade)

2 agentes independentes (lote A: Sells/Index, Financeiro/Unificado, Ponto/Dashboard,
Cliente/Index, Admin/FF · lote B: Repair/Index, Whatsapp/Settings, Jana/Dashboard,
kb/Index, ads/Confidence). Proibição dura: abrir `memory/governance/scorecards/` ou
procurar notas em sessions. Insumo: SCREEN-GRADE-METODO 16-dim + .tsx integral + charter +
casos + Controller, tudo `git show origin/main@4f5d2fbe4a`. Régua declarada: 50 mediano ·
70 bom · 80+ excelente raro · 90+ estado-da-arte.
