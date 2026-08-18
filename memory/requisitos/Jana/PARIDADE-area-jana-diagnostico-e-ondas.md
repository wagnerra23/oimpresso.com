# Área Jana — paridade protótipo × produção: diagnóstico medido e ondas de correção

- **Data da medição:** 2026-08-18 · **base:** `origin/main` `4177c033a`
- **Âncora:** `prototipo-ui/cowork/jana-merge.jsx` (§`JanaHeader` vive em `chat-jana.jsx`) — servido em `localhost:5577` via `launch.json` → `cowork-jana-2`; `window.JmMemoria` resolve `function`, e o `sha256` do arquivo bate com `origin/main` (`057bd8ae…`) nos 3 worktrees conferidos
- **Origem:** [W] abriu o protótipo e apontou, um a um: *"não copiou as actions dos botões"* · *"botão configurar, plano pro"* · *"os drawer das metas faltou muita coisa"* · *"não copiou o esqueleton"*. Os quatro se confirmaram.

> **Limite deste documento.** Tudo abaixo é **estrutural** (leitura de código + runtime do protótipo). Não mede fidelidade visual: isso exige `cowork-mirror-freshness --compare --check` = SYNC + sonda `design-diff --probe` nos dois renders, e **nenhum dos dois rodou**.

---

## 1 · Quais páginas a área tem

**Inertia — 4** (o resto de `Pages/Jana/**` é `_components/`, `_shared/`, `components/`, que o Inertia não resolve):

| rota | Page | controller |
|---|---|---|
| `/ia` | `Jana/Index` (Painel) | `IndexController` |
| `/ia/conversa` · `/ia/conversas/{id}` | `Jana/Chat` | `ChatController` |
| `/ia/memoria` | `Jana/Memoria` | `MemoriaController` — **no módulo KB** |
| `/ia/pro` | `Jana/Pro` | `ProController` |

**Blade — 4**, servidas por rotas `/ia/*` e portanto **invisíveis a todo gate de tela** (charter, casos, visreg, screen-coverage):

`/ia/alertas` · `/ia/alertas/config` → `view('copiloto::alertas.*')` · `/ia/superadmin/metas` → `view('copiloto::superadmin.metas')` · `/ia/metas/{id}/fonte` → `view('copiloto::fontes.show')`

## 2 · O que o PageHeader canon oferece

Sete props (`Components/PageHeader/PageHeader.tsx`, ADR 0189 v3.2 + 0190): `leading` · `title` (**única obrigatória**) · `suffix` · `subtitle` · `subnav` · `actions` · `className`.

## 3 · Onde moram os arquivos

| caminho | estado | consumidores |
|---|---|---|
| `Components/PageHeader/` (`PageHeader.tsx`, `PageHeaderPrimary.tsx`, `index.ts`) | **canon** | 31 |
| `Components/shared/PageHeader.tsx` | **`@deprecated` CONGELADO** (ratchet `pageheader-gate`, baseline `count: 97`) | 88 |

⚠️ **`shared/PageHeaderTabs.tsx` NÃO é deprecated** — ele se declara *"slot action canônico do PageHeader (ADR 0180)"*. Está na mesma pasta do congelado, e é canon. Julgar por **pasta** produziria a conclusão errada de que o `JanaSubNav` (que o importa) está em dívida — ele não está. A área Jana **está toda no canon**.

Wrapper da área: `Pages/Jana/components/JanaAreaHeader.tsx` (usa o canon) + `Pages/Jana/_shared/JanaSubNav.tsx`.

## 4 · O header de cada página

| Página | header | `active` | business no subtitle | `actions` |
|---|---|---|---|---|
| Index | `JanaAreaHeader` | `dashboard` | **sim** | Configurar + Exportar |
| Chat | `JanaAreaHeader` | `chat` | **não** | — |
| Memoria | `JanaAreaHeader` | `memoria` | **não** | — |
| **Pro** | **`<header>` hand-rolled** | — | — | Voltar ao chat |

O Pro é a única fora do header do sistema. O charter justifica com "modo FOCO" — mas modo FOCO é *sem SubNav*, não *sem PageHeader*: um `<PageHeader>` sem a prop `subnav` daria o mesmo resultado dentro do canon.

`janaContext` tem **0 hits** em `ChatController` e `MemoriaController`; o `IndexController` tem. Por isso só o Painel mostra empresa e `biz=`.

## 5 · Colunas e títulos

| Página | layout |
|---|---|
| Index | `sm:grid-cols-3` (KPIs) + `sm:grid-cols-2 xl:grid-cols-3` (análises) |
| Chat | `copiloto-chat-layout` — master/detail, 320px lista + 1fr thread |
| Memoria | `max-w-4xl mx-auto p-6` — coluna única centrada |
| Pro | `max-w-[1060px]`, hero `lg:grid-cols-[1.05fr_0.95fr]`, tabela `grid-cols-[1fr_130px_150px]` |

**Breadcrumb:** os 4 são inertes — `AppShellV2:559` só renderiza sob `{!hideTopbar && …}` e `hideTopbar` é `true` por default (`:243`). Index e Memoria declaravam `breadcrumbItems` que nunca chegou à tela (removido na Onda 3).

---

## 6 · O que faltou copiar — medido item a item

| elemento do protótipo | Index | Chat | Memoria |
|---|---|---|---|
| **selo "plano Pro"** (`jm-plano`) | ❌ | ❌ | ❌ — **0 ocorrências em toda a área** |
| **Configurar** (`onConfig`) | ✅ (abre `JanaConfigDrawer`, #5878) | ❌ | ❌ |
| Exportar | 🟡 (`title="em breve"`) | ❌ | ❌ |
| **Skeleton** (`JmPainelSkeleton`, variante `compacto`) | ✅ `JanaCockpitSkeleton` | ❌ | ❌ |

**Drawer de metas** — `JmMetaDrawer` (protótipo) × `JanaMetaDrawer` (vivo, 219 linhas):

| seção | vivo |
|---|---|
| Situação · Realizado · Projeção · Série · Fechar | ✅ |
| **Origem do número** | ❌ 0 |
| **Escopo** | ❌ 0 |
| **Editar meta** | ❌ 0 |
| **Falar com a Jana** (`onFalarComJana`) | ❌ 0 |

---

## 7 · O que o protocolo fez errado

A desconfiança de [W] é justificada, mas o alvo não é "protocolo corrompido". O protocolo **mede e registra** — os ❌ do selo de plano, do Configurar e do Exportar já estavam escritos no `Memoria-visual-comparison.md` mergeado hoje. O que falta é **morder**. São três buracos, todos medidos:

### 7.1 — A catraca anti-omissão nunca roda

`scripts/contrato-de-tela.mjs` tem o modo `--omission`, descrito no próprio cabeçalho como *"Catraca 3 INVERTIDA (pega o que o handoff OMITIU)"*. É exatamente o mecanismo que pegaria este caso, **sem** depender de alguém declarar item a item.

Ele tem npm script (`contrato:omission`) e tem teste (`contrato-de-tela.test.mjs`). **Zero invocação em CI** — nenhum workflow o chama. É a classe "máquina que existe e ninguém invoca", que o canon já trata como defeito, não como neutralidade.

### 7.2 — O contrato do Painel é curto demais

`prototipo-ui/contrato/jana-painel.contract.json` declara **5 seções**: `painel-cta-conversar` · `painel-metas-header` · `painel-metas-vazio` · `painel-meta-apurando` · `painel-meta-sem-historico`. Nenhuma é header, actions ou drawer.

O contrato é uma **allowlist do que se quer travar**. Mesmo rodando — e ele **roda**, o CI itera `git ls-files '*.contract.json'` —, não morderia nada do §6.

> ⚠️ **Errata de método (mesma sessão).** A 1ª leitura minha foi *"ninguém invoca este contrato"*, a partir de um `grep` pelo **nome literal** do arquivo nos workflows. Falso: o CI usa **glob**, não nome. Grep por nome literal não prova ausência de invocação quando o consumidor enumera por padrão.

### 7.3 — Três das quatro telas não têm contrato

`prototipo-ui/contrato/` tem 5 arquivos; descontando `EXEMPLO`, `schema` e um `.intent.json`, sobram **2 contratos reais** (caixa-unificada e jana-painel). Chat, Memória e Pro: nenhum.

### Por que isso não é "criar um gate novo"

As três correções **estendem o dono do tema** (`contrato-de-tela`). Gate novo para esta classe cairia na família já morta de guard sintático (§5: allowlist-de-pasta · `@scope` · vocabulário 130 FP · `toHaveKey` 100% FP).

---

## 8 · Ondas de correção

| onda | o quê | toca pixel? | estado |
|---|---|---|---|
| **0** | RUNBOOK do Pro + declarações + fix do "Voltar ao chat" + UC-PRO-07 + `Jana/Pro` no visreg + baseline + smoke | sim | **[#5891](https://github.com/wagnerra23/oimpresso.com/pull/5891)** — aguarda merge + F1.5 |
| **3** | breadcrumb morto removido (Index, Memoria) + separador do Chat | **não** | **[#5907](https://github.com/wagnerra23/oimpresso.com/pull/5907)** |
| **P** | **ligar `--omission` no CI** — a catraca que pega omissão sem declarar item a item | não | proposta |
| **1** | Pro entra no `PageHeader` canon (sem `subnav`, preserva modo FOCO) | sim → F1.5 | proposta |
| **2** | `janaContext` no Chat e na Memória (empresa + `biz=` no header) | sim → F1.5 | proposta |
| **4** | selo de plano + Configurar + Exportar + skeleton nas 3 telas | sim → F1.5 | proposta |
| **5** | drawer de metas: Origem do número · Escopo · Editar meta · Falar com a Jana | sim → F1.5 | proposta |
| **6** | Dashboard × Painel (título, breadcrumb, componente exportado) | sim → F1.5 | decisão [W] |
| **7** | as 4 telas Blade da área — uma onda por tela, F1 (RUNBOOK) antes de qualquer `.tsx` | sim | proposta |

**Ordem sugerida:** `P` primeiro. Ligada a catraca de omissão, as ondas 1/2/4/5 param de depender de alguém lembrar do que faltou — o gate passa a dizer.

**Bloqueadas por decisão [W], não por trabalho:** os preços do paywall (restaurar × remover a frase) e o rastro da edição na Memória (DTO da Camada C × prop irmã — ver errata no `Memoria-visual-comparison.md`).
