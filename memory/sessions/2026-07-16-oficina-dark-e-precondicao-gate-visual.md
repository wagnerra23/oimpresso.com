---
title: "Oficina/Board — dark quebrado + gate visual herdando vazamento (2 defeitos ligados)"
date: "2026-07-16"
type: session
authority: informativa
module: OficinaAuto
related_adrs:
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0261-gate-novo-nasce-advisory
  - 0101-tests-business-id-1-nunca-cliente
  - 0093-multi-tenant-isolation-tier-0
---

# Oficina/Board — dark quebrado + gate visual herdando vazamento

Origem: destravar o [PR #4358](https://github.com/wagnerra23/oimpresso.com/pull/4358) (baselines de
estados) expôs **dois defeitos ligados**. Nenhum é flake — ambos com evidência dura e reprodutível.

PRs: [#4366](https://github.com/wagnerra23/oimpresso.com/pull/4366) (mecanismo) ·
[#4367](https://github.com/wagnerra23/oimpresso.com/pull/4367) (produto).

## Defeito 1 (produto) — o dark renderizava branco, e o gate protegia isso

`Board.tsx` tinha **14 `bg-white` e ZERO `dark:`**. O tema é resolvido por token; branco hardcodado
continua branco no dark → header/KPIs/toolbar viravam caixas brancas com `text-foreground` (claro)
por cima.

**Medido** (PNG do artifact `pixel-diff-views` do run 29514784095 decodificado — não é impressão
visual): título "Oficina Auto" = `rgb(241,244,246)` sobre `rgb(255,255,255)` → **1.10:1**. WCAG AA
exige 4.5:1. Branco sobre branco, invisível, em tela de cliente **LIVE** (Martinho, biz=164).

E a baseline `oficina-os · dark` **fotografava o bug**, travando-o como contrato de não-regressão —
**o gate defendia o defeito**.

Fix: 12 superfícies → `bg-card`; 4 `text-white` → `text-primary-foreground`; 2 overlays →
`bg-primary-foreground/20`. Efeito (tokens OKLCH→sRGB): **1.10:1 → 12.40:1**; light **zero-delta**
nas superfícies (`--color-card` light = `oklch(1 0 0)` = branco puro).

### Escopo aferido (o que impediu inflar o trabalho)

Das 6 telas do gate visual, **`oficina-os` é a única com `bg-white`** — as outras 5 já usam token
(financeiro-unificado/sells/compras/caixa = 0/0; clientes = 0 `bg-white` com 14 `dark:`). O Board era
o outlier; escopo fechado nele.

## Defeito 2 (mecanismo) — o gate HERDAVA vazamento em vez de ESTABELECER pré-condição

O Quadro é data-driven pelo FSM `oficina_mecanica_os`: sem o processo, cai no `EmptyProcessState`.
**O FSM é pré-condição de RENDER** — e nenhum seeder do `visual-regression.yml` o semeava (varrido e
contado: DatabaseSeeder, VisregTenant, VisregFinanceiroFlow, VisregTenantBLeak, VisregEmptyTenant =
0; a migration `2026_06_10_000000` é no-op num CI de schema novo).

O que fazia o kanban aparecer era **vazamento**: o `ConformanceProbesTest` chama
`OficinaAutoFsmSeeder::runForBusiness()` pra si e o `afterEach` limpa só OS/veículo — **nunca o
processo**. O teste **documenta o buraco em comentário** (`ConformanceProbesTest.php:110-118`).

O vazamento é **assimétrico**, e é isso que quebrava:

| modo | "Run Pest Browser tests" | FSM | render fotografado |
|---|---|---|---|
| verify (`pull_request`) | roda | existe | kanban (6 colunas) |
| update (`workflow_dispatch`) | **pulado** (`if: != 'workflow_dispatch'`) | ausente | **"Quadro ainda não configurado"** |

→ baseline do update rejeitada pelo verify: **6.7793% > τ_alto 2%**, delta idêntico em 2 runs
(29512533782, 29514784095) = determinístico. A baseline literalmente exibia *"Rode o seeder
OficinaAutoFsmSeeder"*.

Fix: `VisregOficinaFsmSeeder` no step "Seed demo tenant" (roda nos **dois** modos). Zero-delta no
verify por construção (é o **mesmo** seeder que já rodava de carona). **Só biz=1** — `run()` iteraria
todo business e semearia o biz=98, trocando o snapshot do estado `empty`
(`IsolatedStatesBaselineTest:110`). Sonda `oficina_stages_biz1=` no step: `0` = pré-condição perdida.

## Lições

1. **Corrigir a foto não corrige a causa.** O [#4248](https://github.com/wagnerra23/oimpresso.com/pull/4248)
   regravou a baseline à mão pro kanban e declarou o drift "resolvido" — sem tocar na pré-condição.
   Voltou no primeiro dispatch seguinte. Comentário do workflow corrigido pra fato datado (afirmação
   em presente apodrece — `proibicoes.md` §5 2026-07-16). **Se `oficina-os · dark` divergir de novo,
   cheque a sonda ANTES de regravar.**
2. **Gate que herda estado de teste não é gate.** Instância da lápide *"chokepoint de guard em
   comando fantasma"*: o mecanismo existia, mas o caminho real (o modo update) não o atravessava.
3. **O RUNBOOK já sabia.** `RUNBOOK-board.md §preconditions` exigia esse seeder **desde 2026-06-02**.
   O gate é que não lia a própria receita.
4. **Buraco de lint medido, não "consertado" no susto:** `ds/no-raw-palette-color` exige step
   numérico (`bg-<cor>-<n>`) → `bg-white` passa batido. **Não** criei regra: são **221 ocorrências em
   60 arquivos** — regra hoje reprovaria em massa (lápide do guard `@scope` 100%-FP). Vira onda
   própria com [W]. Defesa aplicada onde cabe **hoje**: anti-hook no charter (v6).
5. **Sonda validada por canário** (protocolo LC-06): a conversão OKLCH→sRGB previu
   `rgb(241,244,246)`/`1.10:1` e o PNG real mediu exatamente isso → a medida é confiável.

## Achado colateral (não corrigido — fora de escopo)

`block-mwart-violation.mjs` resolve `Pages/<Mod>/<Sub>/<Tela>.tsx` **descartando a subpasta**
(`Board.tsx` → `RUNBOOK-board.md`). O RUNBOOK do Board existia como `RUNBOOK-serviceorders-board.md`
→ **nunca era encontrado**: editar a Page batia em bloqueio como se o F1 PLAN não existisse.
Resolvido pelo caminho mínimo (`git mv` alinhando o nome à convenção canônica + aos 4 irmãos; zero
referências, `grep` = 0). **Não** mexi no hook — é o único enforcement de RUNBOOK desde a ADR 0271.

Contexto medido: o hook bloqueia **170 telas** hoje (a maioria não tem RUNBOOK) — o Board não era
exceção. Duas fragilidades registradas para decisão [W]:
- **colisão**: `ServiceOrders/Create` e `Vehicles/Create` mapeiam ambos pro mesmo `RUNBOOK-create.md`;
- **proveniência**: o RUNBOOK **declara** `tela: OficinaAuto/ServiceOrders/Board` no frontmatter —
  casar por esse campo seria mais fiel à doutrina *"proveniência é o que o artefato declara, não a
  string do path"* (lápide 2026-06-30) do que adivinhar pelo nome do arquivo.

## Honestidade (ADR 0108)

- **`php -l` não rodou** — CT 100 fora do ar (502); PHP não existe local; teste local é proibido.
- **typecheck/eslint não rodaram** — worktree sem `node_modules`; criar junction é proibição Tier 0.
  Diff revisado linha a linha (16 trocas de string em `className`, zero mudança estrutural).
- Gates locais verdes (Node puro): `visreg:states:lint`, `casos:check`, `dominio:check`,
  `no-mock:check`, `foundation:check`. `contrato:check` crasha — **pré-existente** (provado por
  controle-negativo com as mudanças em stash).
- **Baseline dark NÃO regravada** — depende do #4366 em main + do fix do dark; regenerar antes
  travaria o bug outra vez.
