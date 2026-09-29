---
slug: handoff-protocol
title: "Protocolo de Handoff — Claude Design → Claude Code"
type: protocolo
module: AppMobile
status: ativo
owner: wagner
version: 2.0.0
last_updated: 2026-06-19
---

# Protocolo de Handoff — Claude Design → Claude Code

> **Objetivo:** transformar o protótipo de design (HTML + React via Babel, dados simulados) num pacote que o **Claude Code** (ou qualquer dev) consegue **adaptar ao sistema em produção sem adivinhar nada**. Este protocolo define o que vai no pacote, como os arquivos se relacionam, como tratá-los e como aplicá-los.
>
> **Princípio-mestre:** o design **não é descartável** — ele é a especificação executável de UX. O código de produção deve **reproduzir o comportamento**, trocando a camada de dados simulada por dados reais, sem reinventar telas, tokens ou fluxos.

---

## 0. As duas naturezas do que está sendo entregue

O pacote mistura **dois tipos de artefato** que devem ser tratados de forma diferente:

| Tipo | O que é | Tratamento no destino |
|---|---|---|
| **DESIGN (protótipo)** | `Oimpresso Mobile.html` + `*.jsx` + `oimpresso-tokens.css`. React via Babel standalone, `window.*` globals, dados em `mock-*.jsx`, estado em `localStorage` (`OIStore`). | **Referência de UX/visual.** Vira componentes do framework de produção (React Native / PWA / Inertia). Telas, tokens e fluxos são reproduzidos fielmente; a camada de dados é substituída. |
| **PRODUÇÃO (backend)** | `oimpresso-erp/` — TypeScript real: `domain/*`, `routers/*` (tRPC), `db/schema.ts` + `rls.sql`, `packages/shared`. | **Código real, reutilizável quase direto.** É a fonte única de regra de negócio. O cliente mobile consome esses contratos. |

> **Regra de ouro do handoff:** regra de negócio (preço, fiscal, estoque, parcelas) vem do **backend**; aparência e fluxo vêm do **design**. Nunca reimplementar regra no front.

---

## 1. Estrutura do pacote (.zip)

```
handoff_export_oimpresso/
├── README.md                       ← PORTA DE ENTRADA (leia primeiro)
├── SDD-app-oimpresso-mobile.md     ← LEITURA PRIORITÁRIA 1 (o quê / por quê)
├── GUIA-CONSTRUCAO-MOBILE.md       ← LEITURA PRIORITÁRIA 2 (como construir)
├── HANDOFF-PROTOCOL.md             ← este documento
├── INDEX.md                        ← índice humano de todos os arquivos
├── manifest.yaml                   ← índice máquina (papel, deps, ordem de carga)
│
├── app/                            ← DESIGN (protótipo operacional + skin)
│   ├── Oimpresso Mobile.html       ← shell + ordem de carga dos scripts
│   ├── oimpresso-tokens.css        ← SKIN: tokens (claro/escuro/densidade) + classes oi-*
│   ├── oi-store.jsx                ← estado (OIStore/useStore) + OISheet + OIPedidos
│   ├── oi-flow.jsx                 ← faturamento (OIFlow) — elo entre módulos
│   ├── icons.jsx                   ← window.Ic (todos os ícones)
│   ├── mobile-app.jsx              ← shell: tab bar + nav stack
│   ├── ios-frame.jsx / android-frame.jsx / tweaks-panel.jsx  ← molduras + painel
│   ├── *-data.jsx                  ← DADOS SIMULADOS (mock/oficina/finance/menu-perfis)
│   └── screens-*.jsx + dashboard-widgets.jsx + task-viewers.jsx  ← TELAS
│
└── backend/                        ← PRODUÇÃO (oimpresso-erp/)
    ├── apps/server/src/
    │   ├── domain/*                ← regra de negócio (quote, inventory, production, finance, fiscal, policy)
    │   ├── routers/*               ← tRPC (pedidos, orcamento, estoque, producao, financeiro, fiscal)
    │   ├── db/schema.ts + rls.sql  ← modelo de dados + Row-Level Security
    │   ├── lib/* + auth/* + trpc/* ← helpers, sessão, contexto
    │   └── *.test.ts               ← testes das regras (dinheiro, fiscal, parcelas…)
    ├── packages/shared/            ← contracts.ts (zod) + money.ts (centavos)
    ├── packages/tokens/tokens.css  ← tokens canônicos do backend (espelho do skin)
    └── *.json / *.yml / *.ts       ← configs (package, tsconfig, drizzle, docker, vitest)
```

---

## 2. Relacionamento entre arquivos (mapa de dependências)

### 2.1 Ordem de carga do protótipo (não inverter)
O `Oimpresso Mobile.html` carrega scripts nesta ordem — quebrar a ordem quebra os `window.*` globals:
```
React 18 → ReactDOM → Babel standalone
  → ios-frame · android-frame · tweaks-panel
  → icons (window.Ic)
  → oi-store (window.OIStore, useStore, OISheet, OITasks, OIPedidos)
  → oi-flow (window.OIFlow)          ← depende de OIStore
  → mock-data · oficina-data · finance-data · menu-perfis   ← window.MOCK
  → task-viewers · screens-home-tasks · dashboard-widgets
  → screens-modules · screens-novo-* · screens-clientes-producao
  → screens-producao · screens-oficina* · screens-relatorios* · screens-financeiro
  → screens-equipamentos · screens-perfis
  → mobile-app (monta o shell, lê window.Screens)
```

### 2.2 Grafo de dependências (núcleo)
```
oimpresso-tokens.css ──(classes oi-*)──> todas as screens-*.jsx
icons.jsx (window.Ic) ───────────────────> todas as screens-*.jsx
oi-store.jsx ──> oi-flow.jsx ──> screens-financeiro.jsx (fin.extra)
            └──> OIPedidos ──> screens-modules.jsx (PedidoDetalheScreen)
            └──> oficina.os ──> screens-oficina-os.jsx
*-data.jsx (window.MOCK) ────────────────> screens que listam/detalham
mobile-app.jsx (window.Screens) ─────────> orquestra navegação de todas
```

### 2.3 Ponte design ↔ backend (o que troca na produção)
| Design (protótipo) | Vira na produção | Contrato |
|---|---|---|
| `mock-data.jsx` / `OIStore` | chamadas tRPC ao `oimpresso-erp` | `packages/shared/contracts.ts` |
| `OIFlow.faturar` (client) | `routers/financeiro.criarReceber` | `domain/finance/titulos.ts` |
| `OIPedidos.advance` (client) | `routers/producao` + FSM | pipeline canônico |
| preço/orçamento digitado | `routers/orcamento.calcular` | `domain/quote` (servidor é autoridade) |
| margem no `*-data.jsx` | `produtos.margemPct` (cadastro) | regra: vendedor não edita |

---

## 3. Tratamento dos arquivos no destino (produção)

### 3.1 SKIN / LAYOUT (`oimpresso-tokens.css` + classes `oi-*`)
- **Portar 1:1** para o framework de produção como folha de tokens (CSS vars) — não regravar cores à mão.
- Manter os 3 eixos: **tema** (claro/escuro), **densidade** (compact/normal/comfy), **acento** (roxo marca + verde ação).
- Classes `oi-*` viram componentes/estilos nomeados — preservar a semântica (`oi-status` = dot+texto sem fill, `oi-money` = `tabular-nums`, etc.).

### 3.2 TELAS (`screens-*.jsx`)
- Cada `screens-*.jsx` = uma tela/rota. Reproduzir **layout, estados (empty/loading/erro), e hierarquia visual** fielmente.
- Trocar leitura de `window.MOCK`/`OIStore` por hooks de dados reais (tRPC/React Query), mantendo a forma dos dados (ver `contracts.ts`).
- Manter `OISheet` (bottom sheet), `DetailHeader`, `OrderStepper` como componentes compartilhados.

### 3.3 ESTADO (`oi-store.jsx` / `oi-flow.jsx`)
- `OIStore` (localStorage pub/sub) é **andaime de protótipo** → substituir por cache do framework (React Query/Zustand) + servidor como fonte de verdade.
- `OIFlow.faturar` documenta o **contrato de faturamento** (parcelas sem perder centavo, idempotência por id) → implementar via `routers/financeiro` (a regra já está em `domain/finance/titulos.ts`).
- `OIPedidos`/`MANUT_PIPE` documentam o **pipeline** → mapear à FSM do backend.

### 3.4 BACKEND (`oimpresso-erp/`)
- TypeScript real — **aproveitável quase direto**. Subir Postgres (`docker-compose.yml`), `drizzle:migrate`, aplicar `rls.sql`, rodar `seed`, `vitest`.
- É a **fonte única** de: orçamento (`domain/quote`), estoque dimensional (`domain/inventory`), produção (`domain/production`), título/parcela/DRE (`domain/finance`), fiscal ISS×ICMS (`domain/fiscal`), autorização (`domain/policy`).

---

## 4. Indexação (como tudo é catalogado)

Dois índices, sincronizados:
- **`INDEX.md`** — leitura humana: cada arquivo com papel, camada (design/backend), e do que depende.
- **`manifest.yaml`** — leitura por máquina/agente: `path`, `role`, `layer`, `kind`, `deps`, `load_order`, `treatment`. Um agente (Claude Code) lê o manifest pra montar o grafo sem abrir todos os arquivos.

**Regra:** todo arquivo novo entra nos DOIS índices no mesmo PR. Índice desatualizado é bug de handoff.

---

## 5. Passo a passo de aplicação na produção

1. **Ler nesta ordem:** `README.md` → `SDD` → `GUIA` → este protocolo → `INDEX.md`.
2. **Subir o backend** (`backend/oimpresso-erp/`): `docker compose up` → `pnpm i` → `drizzle:migrate` → aplicar `rls.sql` → `seed` → `vitest` (tudo verde). É a fundação.
3. **Mapear contratos:** abrir `packages/shared/contracts.ts` — é a forma dos dados que as telas esperam.
4. **Portar o skin:** `oimpresso-tokens.css` → tokens do framework de produção (CSS vars, 3 eixos).
5. **Reproduzir telas:** tela a tela (`screens-*.jsx`), trocando `window.MOCK`/`OIStore` por dados tRPC reais; preservar layout/estados.
6. **Religar os fluxos:** pedido→faturar→financeiro via `routers/*` (não reimplementar `OIFlow` — usar o backend).
7. **Gates:** 0 erro console · gate de papel · número derivado · fluxo E2E reflete no destino (DoD do GUIA §6).
8. **Rollout:** flag por empresa → canary Wagner → operadores com aviso → monitor 30d.

---

## 6. Checklist de exportação (antes de fechar o zip)

- [ ] `README.md` na raiz, com SDD e GUIA linkados como **leitura prioritária** logo abaixo.
- [ ] `SDD` + `GUIA` + este protocolo presentes e atualizados (mesma versão do código).
- [ ] `INDEX.md` e `manifest.yaml` cobrindo **todos** os arquivos (css, yaml, ts, jsx, sql, json, html).
- [ ] `app/` com protótipo operacional + skin (ordem de carga preservada no HTML).
- [ ] `backend/` com `oimpresso-erp/` completo (domain, routers, schema, rls, shared, configs, testes).
- [ ] Nenhuma regra de negócio só no front (toda regra tem casa no backend).
- [ ] Fronteira design↔produção explícita (§2.3) — o que troca está documentado.
