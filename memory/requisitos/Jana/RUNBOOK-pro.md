---
id: requisitos-jana-runbook-pro
slug: jana-runbook-pro
title: "Jana — Runbook da tela Jana Pro (/ia/pro)"
type: runbook
module: Jana
tela: Jana/Pro
owner: W
status: ativo
date: "2026-08-18"
last_validated: "2026-08-18"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0110-cockpit-pattern-v2-canon-list-detail
  - 0140-jana-pro-produto-comercial-saas
  - 0190-primary-button-roxo-universal-295
preconditions:
  - "Usuário autenticado num business (o grupo /ia já garante auth — o upsell é aberto a qualquer user auth)"
  - "Nenhuma outra: a tela é leitura pura — não consulta driver de memória, não chama LLM, não toca billing"
steps:
  - "Abrir /ia/pro autenticado e conferir o modo FOCO (sem sub-navegação de abas)"
  - "Conferir o card de prova: 3 ângulos (Bruto/Líquido/Caixa) vindos das props, não hardcoded no componente"
  - "Clicar Ativar Jana Pro e conferir o ciclo idle → Ativando… → Pro ativo (mock client-side, Sprint A)"
  - "Testar os atalhos: Ctrl/Cmd+Enter ativa · Esc sai da tela"
  - "Conferir Tier 0: passar ?business_id=999 na URL não muda o business exibido"
  - "Conferir que abrir a tela duas vezes devolve os mesmos props (render idempotente, sem efeito colateral)"
  - "Smoke real com screenshot em 1280px após qualquer merge que toque a tela"
---

# RUNBOOK — Jana Pro (`/ia/pro`)

> **Tipo:** runbook reproduzível
> **Irmãos:** [`Pro.charter.md`](../../../resources/js/Pages/Jana/Pro.charter.md) (lei) · [`Pro.casos.md`](../../../resources/js/Pages/Jana/Pro.casos.md) (contrato UC) · [`Pro-visual-comparison.md`](Pro-visual-comparison.md) (protótipo × tela viva)
> **Validado:** **estático** contra `origin/main` em 2026-08-18 — rota, controller, componente, charter, casos e o manifesto do visreg conferidos arquivo a arquivo; o protótipo lido no Cowork via `DesignSync.get_file`.
> ⚠️ **Fluxo vivo contra prod NÃO exercitado nesta data.** O smoke real com screenshot é o passo 7 e é a evidência que fecha a R1.

Paywall de conversão do plano **Grátis** pro **Jana Pro**: uma tela de decisão (estilo checkout), com prova ao vivo, comparação de planos, preço e sinais de confiança, e **uma** ação primária. Persona: Larissa (ROTA LIVRE, monitor 1280px), decisão rápida.

> **Por que este RUNBOOK nasceu em 2026-08-18, com a tela viva desde 2026-06-01.** Ele não existia — a tela shipou sem a F1 do MWART. O `screen-coverage --screen Jana/Pro` resolvia o RUNBOOK como **`⚠ AMBÍGUO (2)`** (`RUNBOOK-jana-advisor-proativo.md` · `RUNBOOK-jana-pro-concierge.md`), e **nenhum dos dois é desta tela**: o concierge é a operação do brief diário, o advisor é outra capacidade. Consequência medida: o hook `block-mwart-violation` **bloqueava** qualquer Edit em `Pro.tsx` (`exit 2`), por não achar `RUNBOOK-pro.md` nem `RUNBOOK-jana-pro.md`. Este arquivo é a F1 que faltava.

## Superfície (medida em `origin/main` `139cde249`, 2026-08-18)

| Peça | Onde |
|---|---|
| Page Inertia | [`resources/js/Pages/Jana/Pro.tsx`](../../../resources/js/Pages/Jana/Pro.tsx) — `ProPage` + `CmpRow` + `TrustRow` no mesmo arquivo |
| Controller | [`Modules/Jana/Http/Controllers/ProController.php`](../../../Modules/Jana/Http/Controllers/ProController.php) — leitura pura, 4 props |
| Rota | [`Modules/Jana/Http/routes.php`](../../../Modules/Jana/Http/routes.php) — `jana.pro.index` (GET `/ia/pro`), grupo `/ia` (auth) |
| Contrato Pest | [`Modules/Jana/Tests/Feature/ProContractTest.php`](../../../Modules/Jana/Tests/Feature/ProContractTest.php) — P1..P6 = UC-PRO-01..06, lane `jana-pest.yml` |
| Shell | `AppShellV2` — **modo FOCO**: sem `JanaSubNav` (é decisão de compra, não de navegação) |
| Scorecard | `memory/governance/scorecards/screens/jana-pro.yaml` |

> ⚠️ **Distinto de `Admin\JanaProController`** (preview JSON do brief, superadmin, `/copiloto/admin/jana-pro/preview`). Mesmo nome de produto, outra tela, outro público.

## Contrato de props (o que o Controller entrega)

| prop | shape | origem hoje |
|---|---|---|
| `plan` | `'free'` ou `'pro'` | **fixo `'free'`** — Sprint A. Billing real (Asaas) é Sprint JANA-B (ADR 0140) |
| `pricing` | `{ monthly, trialDays }` | constantes do ADR 0140 |
| `proof` | `{ bruto, liquido, caixa }` | **mock** — a Onda B liga em `BriefDiarioService::snapshot()` |
| `business` | `{ id, name }` | **sempre** `session('user.business_id')` — nunca de input (Tier 0) |

## Passos

### 1. Abrir a tela

Login → `https://oimpresso.com/ia/pro`. Esperado: header com breadcrumb `Jana · Plano`, título `Jana Pro` + tag `UPGRADE`, e **nenhuma barra de abas** (modo FOCO). O body rola; header e footer ficam fixos.

### 2. Conferir o card de prova

O card dark à direita mostra duas bolhas de conversa e três ângulos de faturamento (Bruto · Líquido · Caixa, o último em verde). Os números vêm de `props.proof` — **não** estão escritos no componente.

⚠️ A copy diz *"Números reais das suas tabelas"* sobre valores que hoje são **mock**. É divergência conhecida e registrada no charter (Onda B); não é defeito de render.

### 3. Comparação e preço

Seis linhas Grátis × Pro, com a coluna Pro destacada. Abaixo, o bloco de preço com a mensalidade, a comparação com concorrentes e três garantias.

⚠️ **Defeito conhecido, aberto:** os dois preços de concorrente saem como o **sentinela de redação** em vez do número. É resíduo do `git filter-repo` de 2026-06-08, que atingiu código e não só documentação. Registrado em [`Pro-visual-comparison.md`](Pro-visual-comparison.md) §R6 — **decisão [W] pendente** (restaurar × remover a frase). Não "consertar" sem essa decisão: o hook `block-brl-values-in-memory` bloqueia pelo padrão, não pela origem do número.

### 4. Ciclo da CTA

Clicar **Ativar Jana Pro**: `idle` → `Ativando…` (botão desabilitado, ~900ms) → botão verde com *"Jana Pro ativo · N dias grátis"*. Clicar de novo não repete — o guard de estado segura.

**É mock client-side.** Não existe endpoint POST, nada é cobrado, nada é persistido.

### 5. Atalhos de teclado

`Ctrl/Cmd+Enter` ativa. `Esc` sai da tela. Os dois são registrados em `useEffect` com `removeEventListener` no cleanup — o protótipo não tinha `Esc` nem cleanup.

### 6. Tier 0 — o business é o da sessão

Abrir `/ia/pro?business_id=999` autenticado num business diferente. Esperado: a tela mostra **o business da sessão**, nunca o 999. É o **UC-PRO-05**, coberto por Pest.

### 7. Smoke real (a evidência que fecha a R1)

Após qualquer merge que toque esta tela: abrir em prod com o browser, screenshot em **1280px** (Larissa) e relatar o que se viu **antes** de declarar pronto.

⚠️ **Esta tela NÃO tem rede de pixel.** `"Jana/Pro"` tem **0 ocorrências** em [`tests/Browser/visreg-screens.json`](../../../tests/Browser/visreg-screens.json) — medido 2026-08-18. Das telas Jana com charter, é a única sem baseline. Enquanto for assim, o passo 7 é a **única** verificação visual que existe: sem ele, mudança de layout entra sem ninguém ver.

## Pegadinhas catalogadas

| # | Pegadinha | Detalhe |
|---|---|---|
| 1 | **Editar `Pro.tsx` era bloqueado** | O hook MWART exige `RUNBOOK-pro.md` ou `RUNBOOK-jana-pro.md`; `RUNBOOK-jana-pro-**concierge**.md` **não** casa. Não há escape — `/mwart-override` é registro humano no PR, não comando que o hook honre |
| 2 | **RUNBOOK resolvido por nome é ambíguo** | Com 2 candidatos parecidos, `screen-coverage` não escolhe. A cura é o campo `runbook:` no charter — declaração vence nome |
| 3 | **`plan` é sempre `'free'`** | Ao ligar o billing (Sprint B), o **UC-PRO-04 muda de sentido** (de "sempre free" pra "reflete a assinatura") e o teste tem de ser atualizado junto |
| 4 | **Sem `visreg`** | Ver passo 7 |
| 5 | **Preços redigidos** | Ver passo 3 |

## Referências

- [ADR 0140](../../decisions/0140-jana-pro-produto-comercial-saas.md) — Jana Pro como produto SaaS (pricing + roadmap Sprint A-D)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0190](../../decisions/0190-primary-button-roxo-universal-295.md) — primary roxo universal
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART, o processo que exige este arquivo
