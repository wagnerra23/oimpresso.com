# DS v6 — bundle FULL (2026-06-26) vs kit landado (`ds-v6/`) + repo SSOT

> **Natureza:** auditoria comparativa (read-only). Aditivo / **não-Tier-0**. **Estende** — não substitui —
> [`README.md`](README.md) e [`REUSE_MAPPING.md`](REUSE_MAPPING.md) deste mesmo kit.
>
> **Origem:** handoff bundle do Claude Design empacotado como **skill** (`SKILL.md` `oimpresso-design`,
> user-invocable), entregue por [W] em 2026-06-26 (`Office Impresso — Design System.zip`, 216 arquivos).
> É a evolução **componentizada/superset** do DS v6 landado em 2026-06-03 (PR #2165 referência + PR #2170 tokens).

## TL;DR

- **Mesma família DS v6.** O bundle não é versão nova de design — é a forma **full/componentizada** do mesmo
  DS v6 já canônico no git (SSOT = `resources/css/tokens/semantic.tokens.json` → Style Dictionary).
- **Tokens em sincronia real.** Valores de fundação batem **100%** com `resources/css/tokens/_generated-foundations-light.css`
  (15/15 comuns, 0 divergem). Primary roxo `oklch(0.55 0.15 295)` (ADR 0190/0235), IBM Plex Sans/Mono — tudo canon.
- **2 deltas reais** (nenhum é design): (1) **fork de nomenclatura** `--color-*` no bundle; (2) **cobertura** —
  alguns tokens de domínio o git já tem e o bundle não (e vice-versa).
- **Os 3 gaps Tier-0 do `REUSE_MAPPING` seguem abertos** — o bundle **não** ship ficha-360 (`c-id`),
  timeline unificada (`c-tl`) nem NBA card (`c-nba`). Verificado: sem componente correspondente em `components/`.

## 1. O que o bundle FULL traz de novo vs o `ds-v6/` landado

| Eixo | `ds-v6/` landado (2026-06-03) | Bundle full (2026-06-26) |
|---|---|---|
| Forma | 3 HTML estáticos (`showcase`/`receita`/`gabarito-vendas`) | **40 componentes `.jsx`** (componentizado) + `.d.ts` + `.html` cada |
| Componentes | 11 classes `c-*` (CSS de apresentação) | **43 nomes exportados** (manifest) |
| Templates | — | **7**: PT-01 Lista · PT-05 Dashboard · PT-07 OS-detail · Financeiro · Oficina-Auto · Atendimento · Clientes-CRM |
| Shells navegáveis | gabarito de 1 tela (Vendas) | **2 ui_kits**: `ui_kits/site` (marketing) + `ui_kits/app` (shell operacional) |
| Empacotamento | referência no repo | **skill** `oimpresso-design` (SKILL.md + README 28k + manifest 245 tokens) |

> Os 40 `.jsx` são **protótipo**, não produção (zero `.tsx`). A porta pra Inertia/React segue **inalterada**:
> MWART (ADR 0104) + gate visual (ADR 0107/0114) + [W] aprova screenshot. Importar `.jsx` como produção
> **duplicaria** o que já vive em `@/Components/ui` — ver veredito do `REUSE_MAPPING`.

## 2. Tokens — auditoria (corrigida)

Método: extração das custom properties de `colors_and_type.css` (bundle) × `resources/css/tokens/*.css` (repo).

> ⚠️ **Correção de método:** um primeiro diff cru comparou o bundle (tema **claro**) contra um arquivo de tema
> **escuro** do repo e acusou "65 divergências". Falso — era polaridade de tema (lightness invertida pra
> contraste), não drift. Contra o arquivo **light** correto (`_generated-foundations-light.css`): **0 divergem**.

### 2.1 Valores — ✅ em sincronia
`--color-primary oklch(0.55 0.15 295)`, `--text oklch(0.22 0.01 80)`, `--surface #ffffff` — idênticos ao SSOT light.
Sem drift de cor/marca/tipografia.

### 2.2 Delta 1 — fork de **nomenclatura** (`--color-*`) · 🟡 real
O bundle introduz uma camada de **104 tokens `--color-*` literais** e rebaixa os nomes crus a **alias**
(`--accent: var(--color-accent)`). O git/kit landado usa o **nome cru como literal** (`--accent` = `oklch(...)`).

| Conceito | Bundle | SSOT/kit landado |
|---|---|---|
| Acento | `--color-accent` literal + `--accent` alias | `--accent` literal |
| Canal | `--color-canal-email` + `-soft` | `--canal-email-bg` / `-fg` / `-tint` |
| SLA | `--color-sla-fresh` + `-soft` | `--sla-fresh` + `-dot` / `-line` / `-soft` |
| KPI feature | `--color-kpi-feature-*` | `--kpi-feature-*` |

**Failure mode concreto:** a regra Tier 0 "Pacote Cowork novo" manda **copiar `styles.css` inteiro** na 1ª
aplicação de um módulo. Bundle com `--color-canal-email` num app que espera `--canal-email-bg` → **tokens
órfãos/descasados** no CSS copiado. Não é cosmético.

### 2.3 Delta 2 — cobertura · 🟡 real (mútua)
**Só no git** (bundle não tem) — domínio que o repo já evoluiu (parte via PR #2170): `--stage-*` (esteira FSM),
`--origin-{CRM,FIN,MFG,OS,PNT}`, `--bubble-me/-them`, `--thread-bg-*`, `--plate-*`, `--sla-*-dot/-line`.

**Só no bundle** (git não tem sob esses nomes): `--av-c1..c8` (rampa de avatar), `--color-tipo-pf/-pj`.

## 3. Deltas → encaminhamento (proposto, não executado)

Princípio-mestre: **git SSOT manda; o bundle conforma** (o bundle é gerado pelo Cowork, downstream — não se
edita à mão).

- **Track 1 (naming):** decidir autoridade — default **SSOT bare-name vence**; feed pro Cowork via
  `CODE_DESIGN_CONTRACT.md` pra próximo export emitir nome canônico; defesa via `ds-guard.mjs` (lint de
  `--color-canal-*`/`--color-sla-*` órfão em bundle copiado).
- **Track 2 (cobertura):** consolidar no DTCG SSOT (`semantic.tokens.json`) os tokens de domínio hoje
  hand-rolled em css de módulo (`cockpit.css`/`sells-cowork.css`); `npm run tokens:build`; exportar lista
  canônica pro Cowork. Extras do bundle (`--av-c1..8`, `--color-tipo-pf/pj`) — [W] decide entra/descarta.

> Track 2 mexe em **valor de design** (regra-mestre Tier 0: dupla confirmação + antes→depois + screenshot).
> **Não executar** sem sessão governada e aprovação [W].

## 4. Decisões (delegadas por [W] → CC, 2026-06-27)

1. **Track 1 — autoridade de nomenclatura → ✅ SSOT bare-name vence; Cowork conforma.** git é o SSOT
   (DTCG/Style Dictionary); o bundle é downstream gerado. Caminho de menor churn + mata o failure-mode de
   token órfão no copy-inteiro. Feed pro Cowork via `CODE_DESIGN_CONTRACT.md` + defesa `ds-guard.mjs`.
2. **Extras do bundle (`--av-c1..8`, `--color-tipo-pf/pj`) → ✅ prototype-only, por ora.** Disciplina do DS
   (receita passo 5: não se cunha token sem tela que consuma). Sem consumidor no repo hoje. Revisitar:
   `--color-tipo-pf/pj` quando a ficha CRM 360 portar; avatar-ramp quando uma tela de avatares pedir.
3. **Skill `oimpresso-design` → ✅ kit-only em `ds-v6/`; NÃO registrar como skill Claude Code.** Já há
   ecossistema de skills de design (`cowork-prototype-replication`/`aplicar-prototipo`/`mwart-comparative`/
   `pageheader-canon`/`ui-component-creator`) — mais uma = overlap + matcher confuso; registro Tier A pede ADR.
   O valor do bundle é como kit de referência, que `ds-v6/` já entrega.
4. **Track 2 — consolidar tokens de domínio no SSOT → 🟡 SIM na direção; merge NÃO auto-liberado.** Mexer em
   `semantic.tokens.json` + rebuild toca **valor de design** (regra-mestre Tier 0). Executar como **PR dedicado
   com diff antes→depois do `_generated-*.css`** provando zero mudança de valor (consolidação = mover
   definição, não alterar) — diff vai pro olho de [W] antes de mergear. **Pendente:** investigar o que
   genuinamente falta no DTCG (parte de `--stage-*`/`--origin-*` já está lá; só o residual hand-rolled migra).

## 5. Refs

PR #2165 (DS v6 referência landada) · PR #2170 (tokens `--stage-*`) · [`README.md`](README.md) ·
[`REUSE_MAPPING.md`](REUSE_MAPPING.md) · ADR UI-0013 (Constituição UI v2) · ADR 0190/0235 (roxo 295) ·
ADR 0104 (MWART) · ADR 0107/0114 (gate visual) · ADR 0299 (fonte de design = protótipo Cowork + DS + charter).

---

**Auditoria por:** Claude Code · sessão 2026-06-26 (worktree `vigorous-heyrovsky-664f2a`). Read-only — nenhum
token/código alterado. Próximo passo só após [W] responder §4.
