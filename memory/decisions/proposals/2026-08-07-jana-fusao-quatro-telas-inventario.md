---
title: "Jana — fusão das telas: 5 achados de execução que a US-COPI-148 não cobre"
status: proposta
date: "2026-08-07"
owners: [W]
parent_module: Jana
related_adrs: [180, 182, 264, 286, 315]
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-148)
related_charters:
  - resources/js/Pages/Jana/Chat.charter.md
  - resources/js/Pages/Jana/Dashboard.charter.md
  - resources/js/Pages/Jana/Cockpit.charter.md
  - resources/js/Pages/Jana/Memoria.charter.md
---

# Jana — fusão das telas: achados de execução

> ## ⚠️ O dono deste tema é a **`US-COPI-148`**
>
> [`memory/requisitos/Jana/SPEC.md`](../../requisitos/Jana/SPEC.md) **§US-COPI-148** — *"Fundir
> as telas da Jana numa tela única `/ia` com abas Painel | Conversa | Memória"*. Ela já
> tem as 4 ondas, o que está fora da fusão, as pendências [W] e o DoD; a onda 1 já saiu
> ([#5357](https://github.com/wagnerra23/oimpresso.com/pull/5357)) e o estado está no
> [handoff de 08-07 08:46](../../handoffs/2026-08-07-0846-jana-onda1-e-o-vermelho-de-15-runs.md).
>
> **Este documento não replaneja nada.** A 1ª versão dele fazia isso — nasceu como um
> plano paralelo de 7 PRs porque eu li o `routes.php` e **não li o SPEC**, que o
> pré-flight de [`.claude/rules/modules.md`](../../../.claude/rules/modules.md) manda ler.
> Classe **LC-19** (abrir paralelo ao dono existente), ocorrência 3 — recibo em
> [`LICOES_CODE.md`](../../LICOES_CODE.md) e lápide em [`proibicoes.md` §5](../../proibicoes.md).
> Sobrou só o **delta**: 5 achados de execução que a US não carrega e que a próxima
> sessão precisaria remedir.

---

## 1. Remover `Cockpit.tsx` tem um 3º apontador — os baselines de lint

A US-COPI-148 §Onda 4 nomeia dois: a Page e o `ChatController@cockpit` (`:666`). Há um
terceiro, e ele não quebra nada — só apodrece calado:

| Apontador | Onde |
|---|---|
| Rota + render | `ChatController.php:636` / `:666` (US já cobre) |
| Ghost do menu | `DataController.php:317` — `['key' => 'cockpit', 'href' => '/ia/cockpit']` |
| **Baseline ESLint** | `config/eslint-baseline.json:235` |
| **Baseline ui:lint** | `config/ui-lint-baseline.json:509` |

Os dois baselines saem no **mesmo PR** do delete. Chave de baseline apontando pra arquivo
inexistente é dívida que gate nenhum enxerga — o `baseline-tamper-guard` vigia crescimento,
não órfão.

## 2. As permissões são `jana.*` — `copiloto.*` não existe como permission

O pedido [CC] manda "não renomear permissões `copiloto.*`". Medido: essa instrução protege
um conjunto vazio.

[`Modules/Jana/Resources/permissions.php`](../../../Modules/Jana/Resources/permissions.php)
declara `jana.access`, `jana.chat`, `jana.metas.manage`, `jana.mcp.tasks.read`,
`jana.superadmin` — e é o que as rotas aplicam (`can:jana.access`, `routes.php:50`).
`copiloto` aparece em dois lugares que **não são permission**: o `'group' => 'Copiloto'`
(rótulo humano do registry) e `config('copiloto.*')` (`ui_judge`, `dry_run`, `memoria.*`).

A instrução equivalente que **vale**: não renomear `jana.*` — permission Spatie vive por
**id de linha**, então renomear revoga acesso em silêncio, sem erro e sem log.

## 3. Os 4 charters declaram `page:` de prefixo morto

| Charter | `page:` declarado | URL viva | `status:` |
|---|---|---|---|
| `Chat.charter.md` | `/jana/chat` | `/ia` | `live` |
| `Dashboard.charter.md` | `/copiloto/dashboard` | `/ia/dashboard` | `live` |
| `Cockpit.charter.md` | `/jana/cockpit` | `/ia/cockpit` | `draft` (`spec-ahead-of-impl`) |
| `Memoria.charter.md` | `/copiloto/memoria` | `/ia/memoria` | `draft` (corpo diz live desde 2026-04) |

Os 4 apontam pra prefixos que hoje só existem como **301**: a [ADR 0180](../0180-sidebar-v3-5-grupos-ghosts-header.md)
renomeou `/jana` → `/ia` em 2026-05-22 e os charters não acompanharam. Cada charter tocado
numa onda corrige o próprio `page:` **no mesmo PR** — forward-only, **não** backfill dos 4
de uma vez (§5 2026-07-12: tocar legado em massa acorda gate diff-aware que o grandfather
protegia).

A incoerência do `Memoria.charter.md` (`draft` × "live desde 2026-04") resolve na onda que
tocar a tela: vence o comportamento vivo → `live`.

## 4. O prefixo do pedido (`/jana`) contradiz a ADR 0180 — e não precisa de decisão

O pedido [CC] §1 fixa as rotas em `/jana`, `/jana/conversa`, `/jana/memoria`. Medido:
**`/jana/*` é ele próprio um 301 pra `/ia/*`** desde 2026-05-22 (`routes.php:367`), e o
título da própria US-COPI-148 já diz `/ia`.

Registrar rota real sob `/jana` seria **reverter a 0180** — ADR sucessora, append-only,
fora do escopo de uma fusão de telas. Fica registrado só para que a próxima sessão não
releia o pedido e reabra a questão: **o canon é `/ia`**, e os 301 de `/jana/*` e
`/copiloto/*` já cobrem bookmark antigo sem uma linha nova.

## 5. Duas leituras do protótipo que mudam o escopo das ondas 2-3

Fonte: `jana-merge.jsx` no DesignSync (projeto `019dcfd3…`, leitura livre — [ADR 0315](../0315-design-sync-claude-design-vs-cowork-charter.md)).

- **`metasMode` é um switch do protótipo**, com `"aba"` (Metas vira 4ª aba) e `"secao"`
  (Metas vira seção do Painel). O pedido escolheu `"secao"`. Não há divergência — registro
  aqui só pra não ser redecidido na onda 3.
- **O protótipo desenha 6 KPI cards**; o pedido §2 diz "4 KPIs". Não resolvi por leitura:
  quem arbitra é o `--check` do **contrato de tela** —
  [`scripts/contrato-de-tela.mjs`](../../../scripts/contrato-de-tela.mjs) +
  [`RUNBOOK-contrato-de-tela.md`](../../requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md) —
  que lê o `.jsx`. Registrado como divergência **aberta**, não como fato.

  > ⚠️ O pedido [CC] §1.5 atribui esse gate à **"ADR 0286"**. A 0286 deste repo é
  > `0286-channel-health-corroborado-por-mensagem-real` — outro assunto. Não localizei ADR
  > que governe o contrato de tela sob esse número, então aponto pro **mecanismo**, que
  > verifiquei existir, em vez de repetir um número que não confere. Quem souber o id certo,
  > corrija aqui.

> Nota sobre o `jana-merge.jsx`: o handoff de 08-07 diz que ele *"nunca chegou ao repo e
> deixou de ser necessário"*, porque as peças equivalentes existem no Design System
> (`TabBar` com `count`, `PeriodBar`, `KpiCard`, `Progress`, `Chart`, `pt-05-dashboard`).
> As duas coisas são verdade e não conflitam: ele **não está no git** e **está no
> DesignSync** — que é o outro dono do inventário de design (§5 2026-07-28).

---

## O que este documento **não** decide

Ondas, sequência, DoD e o que fica fora da fusão — tudo isso é da **US-COPI-148**. Quem
for executar lê a US primeiro e usa este arquivo só como lista de armadilhas.

Duas coisas da US que valem repetir aqui **porque são proibições**, e proibição repetida
custa menos que proibição perdida:

- ⛔ **Não criar `JanaTabs.tsx`** — `JanaAreaHeader` + `JanaSubNav` já existem e já servem
  as 4 telas; os ghosts vêm do `DataController` (PHP), não do React. Criar o componente
  seria LC-19 dentro da própria onda que este arquivo corrige.
- ⚠️ **`US-COPI-123` (p0, em REVIEW) toca a mesma tela** (`/ia/dashboard`) — reconciliar
  antes, não em paralelo.
