---
id: requisitos-fiscal-fiscal-cockpit-visual-comparison
tela: Fiscal/Cockpit
url: /fiscal
status: approved
approver: wagner
approved_at: 2026-05-20
prototype_source: "prototipo-ui/.../fiscal-page.jsx §8 FiscalCockpit"
implementation: resources/js/Pages/Fiscal/Cockpit.tsx
adr: 0107
---

# Visual Comparison — Fiscal/Cockpit (PR #2 Wave)

## Blueprint Cowork

`prototipo-ui/.../fiscal-page.jsx §8 FiscalCockpit` + `fiscal-data.jsx::FISCAL_KPIS/SPARKLINES/FISCAL_ALERTS` (R#1 KB-9.75).

## Approval

Wagner aprovou Wave consolidada (Cockpit + NFS-e + Eventos) — 2026-05-20.

## 8 dimensões

### 1. Layout grid

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPIs grid | grid-template-columns: 1.4fr repeat(5, 1fr) | ✅ idem | ✅ |
| Alertas card branco | bg white + border + padding 14px | ✅ `.fx-alerts` | ✅ |
| Quick links 3 cols | grid-template-columns: repeat(3, 1fr) | ✅ idem | ✅ |

### 2. Tipografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPI big number | 22px font-weight 700 | ✅ `.fx-kpi b` | ✅ |
| KPI small label | 10.5px uppercase + tracking | ✅ idem | ✅ |
| Alert título | 12.5px font-weight 600 | ✅ idem | ✅ |

### 3. Densidade

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Gap entre KPIs | 8px | ✅ idem | ✅ |
| Padding KPI card | 12px 14px | ✅ idem | ✅ |
| Alertas inset border-left 3px | inset esquerdo colorido por level | ✅ `.fx-alert.{crit,warn,info}` | ✅ |

### 4. Iconografia

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Icon hero KPI | sparkline SVG inline branco | ❌→✅ ver nota A | ⚠️→✅ |
| Icon alert | ShieldAlert/Shield/Receipt/RefreshCw lucide | ✅ ICON map dinâmico | ✅ |
| Icon quick card | Receipt/FileText/Archive/Shield etc. lucide | ✅ idem | ✅ |

### 5. Cores/Estados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| KPI hero (emitidas) bg fis | rosa fiscal saturado | ✅ `.fx-kpi.hero` | ✅ |
| KPI rejeitadas pulse | animação box-shadow infinite | ✅ `@keyframes fx-pulse` | ✅ |
| Alert color tone | bad/warn/info via border-left | ✅ idem | ✅ |

### 6. Animações

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Pulse rejeitadas | 2.5s infinite (oklch bad 50%→0%) | ✅ idem | ✅ |
| Hover quick card | border-color → fis transition .12s | ✅ idem | ✅ |
| Hover alert | bg fx-bg-2 transition .12s | ✅ idem | ✅ |

### 7. Estados condicionais

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| Alertas hide se vazio | `alerts.length > 0` render condicional | ✅ idem | ✅ |
| Pulse só se rejeitadas > 0 | classe condicional | ✅ idem | ✅ |
| Cert sem dados → "—" | fallback null | ✅ idem | ✅ |
| Quick cards disabled (4/6/7) | opacity 0.55 | ✅ inline style | ✅ |

### 8. Componentes reutilizados

| Aspecto | Cowork | Inertia | OK? |
|---|---|---|---|
| FxShell wrapper | sub-nav + cheats + atalhos 1-7 | ✅ `_components/FxShell.tsx` | ✅ |
| MiniSparkline SVG | `<polyline>` (não `path + circle`) | ❌→✅ ver nota A | ⚠️→✅ |
| brl helper | format moeda | ✅ `_lib/fiscal-helpers.ts` | ✅ |

> **Nota A — ⚠️ correção de veredito, 2026-09-04.** As duas linhas acima diziam **✅** e citavam
> um `MiniSparkline component` que **nunca existiu**. Medido em `origin/main` (tip `d23bc3df34`)
> por dois oráculos independentes com controle positivo — `rg --hidden -g '!.git/**'` e
> `git grep` —, o identificador `MiniSparkline` aparecia em **2 arquivos, nenhum de código**:
> este doc e `governance/sdd-verification-ledger.json`. Em `resources/js/`, **zero**. No mesmo
> tip, `grep -c polyline resources/js/Pages/Fiscal/Cockpit.tsx` dava **0** e a prop `sparklines`
> nunca era desestruturada.
>
> O registro original fica **preservado**: ele é o fato datado do que se afirmou em 2026-05-20,
> e apagá-lo esconderia justamente o mecanismo do defeito. **Este `✅` é a causa de o gap ter
> ficado invisível por ~3,5 meses** — sendo este doc o dono do inventário por tela, quem o
> consultasse concluiria que a peça estava pronta e não olharia o `.tsx`. Um refutador
> adversarial já havia registrado a discrepância no `sdd-verification-ledger.json` (append-only,
> por isso não tocado aqui); o que faltou foi ela voltar para cá.
>
> **Fechado em 2026-09-04** pelo item A2 (autorizado por [W]): `_components/RibbonSpark.tsx`,
> porte 1:1 do `FxSpark` de `fiscal-page.jsx:80-84`, aplicado aos **3** KPIs que o protótipo
> marca (`:114-116`). Contrato em `Cockpit.casos.md` **UC-FCKP-10**, lane
> `fiscal-cockpit-sparklines-gate.yml` — agora existe máquina vigiando, e um `✅` sem recibo
> volta a ficar vermelho. Detalhe de forma: o protótipo desenha `<polyline>`, **sem** o
> `circle` de endpoint que a linha original descrevia.

## Histórico

- **2026-05-20** — Wave consolidada PR #2 (Cockpit + NFS-e + Eventos). Implementação fiel ao protótipo Cowork.
- **2026-09-04** — Item A2 ([W]): as sparklines passam a ser DESENHADAS (`_components/RibbonSpark.tsx`, 3 KPIs, UC-FCKP-10 + lane própria). Corrigido o veredito de 05-20 nas duas linhas do `MiniSparkline` — ver **Nota A**. O Goal #2 do charter também cedeu de *4 KPIs* para *3*, pela cadeia de FORMA (ADR UI-0029).
