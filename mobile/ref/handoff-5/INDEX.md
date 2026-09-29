# INDEX — Catálogo de arquivos do pacote

Índice humano de **todos** os arquivos. Camadas: **DESIGN** (`app/` — protótipo, vira referência de UX) · **BACKEND** (`backend/` — produção, regra de negócio real) · **DOC** (raiz). Versão máquina: [`manifest.yaml`](manifest.yaml).

---

## DOC — raiz (ler nesta ordem)

| Arquivo | Papel |
|---|---|
| `README.md` | Porta de entrada; aponta SDD/GUIA como prioridade |
| `SDD-app-oimpresso-mobile.md` | **Prioridade 1** — o que o app é/por quê |
| `GUIA-CONSTRUCAO-MOBILE.md` | **Prioridade 2** — como construir sem regressão |
| `HANDOFF-PROTOCOL.md` | **Prioridade 3** — export design→code + aplicação |
| `INDEX.md` | este catálogo (humano) |
| `manifest.yaml` | catálogo (máquina): path/role/layer/deps/load_order |

---

## DESIGN — `app/` (protótipo operacional + skin)

### Shell, skin e infraestrutura
| Arquivo | Papel | Depende de |
|---|---|---|
| `Oimpresso Mobile.html` | Shell + **ordem de carga** dos scripts + 3 stages (iOS/Android/Login) | tudo abaixo |
| `oimpresso-tokens.css` | **SKIN**: tokens (claro/escuro/densidade) + classes `oi-*` | — |
| `icons.jsx` | `window.Ic` — todos os ícones SVG | — |
| `oi-store.jsx` | `OIStore`/`useStore` (localStorage pub/sub) · `OISheet` · `OITasks` · `OIPedidos` (pipeline) | React |
| `oi-flow.jsx` | `OIFlow` — faturamento (parcelas, idempotência, vínculo por id) | `oi-store` |
| `mobile-app.jsx` | Shell: tab bar + nav stack; lê `window.Screens` | store, screens |
| `ios-frame.jsx` / `android-frame.jsx` | Molduras de device (status bar, teclado, safe-area) | React |
| `tweaks-panel.jsx` | Painel de tweaks (tema/densidade/empresa/papel/perfil/visão) | React |

### Dados simulados (trocar por tRPC na produção)
| Arquivo | Papel |
|---|---|
| `mock-data.jsx` | `window.MOCK` — tenants, pedidos, produtos, KPIs, dashboard, produção |
| `oficina-data.jsx` | OS de oficina, pipeline `MANUT_PIPE`, mecânicos, locais, catálogo |
| `finance-data.jsx` | Ledger financeiro (`FIN_LANC`), contas, `finResumo`, coerência Início |
| `menu-perfis.jsx` | Perfis de menu por papel (`MENU_PERFIS`) |

### Componentes compartilhados (camadas 1/2 — carregam antes das telas)
| Arquivo | Papel | Depende de |
|---|---|---|
| `oi-ui.jsx` | **Camada 1 — UI KIT** (`window.OIUi`): `ScreenHeader`, `DetailHeader`, `HeaderTopRight`, `OriginBadge`, `StageStatus` | `Ic`, `MOCK`, `useStore` |
| `oi-manutencao.jsx` | **Camada 2 — helpers de oficina** (`window.OIManut`): `Placa`, `mDue`, `mDur`, `statusTone`, `MANUT_TONE`, `ITEM_STATUS` | `MOCK` |

> Regra de camadas: tela depende destas; estas não dependem de nenhuma tela. Cada `screens-*.jsx` exporta só suas próprias rotas em `window.Screens`.

### Telas (`screens-*.jsx`) + widgets
| Arquivo | Tela |
|---|---|
| `screens-home-tasks.jsx` | Início (tarefas) — consome `OIUi` |
| `dashboard-widgets.jsx` | Widgets do Início (KPIs derivados) |
| `task-viewers.jsx` | Visualizadores de tarefa |
| `screens-modules.jsx` | Pedidos (lista+detalhe+faturar), Produtos, Notificações, Perfil, Empresa, Login |
| `screens-novo-pedido.jsx` | Wizard de novo pedido |
| `screens-novo-cliente.jsx` / `screens-novo-produto.jsx` | Cadastros inline |
| `screens-clientes-producao.jsx` | Clientes |
| `screens-producao.jsx` | Produção (triagem/fila/estações) |
| `screens-oficina.jsx` | Oficina (pátio) — consome `OIUi` + `OIManut`; `OsCard`/`MiniPipe` locais |
| `screens-oficina-os.jsx` | Detalhe da OS (itens/aprovação/faturar) |
| `screens-oficina-cadastro.jsx` | Cadastro de oficina |
| `screens-equipamentos.jsx` | Frota/veículos |
| `screens-financeiro.jsx` | Financeiro (caixa/receber/pagar/extrato; lê `fin.extra`) |
| `screens-relatorios.jsx` / `screens-relatorios-equipe.jsx` | Relatórios / equipe |
| `screens-perfis.jsx` | Perfis/permissões |

---

## BACKEND — `backend/oimpresso-erp/` (produção — TypeScript)

### Domínio (regra de negócio — fonte única)
| Arquivo | Papel |
|---|---|
| `apps/server/src/domain/quote/{types,imposition,quoteEngine}.ts` | Orçamento: imposição (nesting) + custeio por processo + regressiva |
| `apps/server/src/domain/inventory/{types,conversion,inventoryService}.ts` | Estoque dimensional + conversão de unidade + custo médio |
| `apps/server/src/domain/production/consumeStock.ts` | Consumo de material pela OS (mesma imposição do orçamento) |
| `apps/server/src/domain/finance/{titulos,dre}.ts` | Título/parcela (sem perder centavo) + DRE/fluxo projetado |
| `apps/server/src/domain/fiscal/{natureza,documentoStateMachine,provider}.ts` | ISS×ICMS por natureza + máquina de estados + adapter de provedor |
| `apps/server/src/domain/policy.ts` | Autorização RBAC (papéis × recursos × ações) |
| `*.test.ts` (ao lado de cada) | Testes das regras (vitest) |

### API (tRPC) e dados
| Arquivo | Papel |
|---|---|
| `apps/server/src/routers/{pedidos,orcamento,estoque,producao,financeiro,fiscal}.ts` | Routers tRPC por módulo |
| `apps/server/src/trpc/{trpc,context,appRouter}.ts` | Init tRPC, contexto (tenant), router raiz |
| `apps/server/src/db/schema.ts` | Modelo de dados (Drizzle) — UUID, `tenant_id`, `timestamptz`, money cents |
| `apps/server/src/db/rls.sql` | Row-Level Security por tenant |
| `apps/server/src/db/{client,seed}.ts` | Pool/Drizzle + seed que prova a DoD |
| `apps/server/src/lib/{sequences,audit}.ts` | Numeração atômica + audit log append-only |
| `apps/server/src/auth/{session,password,oauth}.ts` | Sessão, senha+2FA (primário), OAuth (secundário) |
| `apps/server/src/index.ts` | Entry HTTP do servidor tRPC |

### Compartilhado, tokens e configs
| Arquivo | Papel |
|---|---|
| `packages/shared/src/contracts.ts` | **Contratos zod** — forma dos dados que as telas esperam |
| `packages/shared/src/money.ts` | Dinheiro em centavos (sem float) + format pt-BR |
| `packages/shared/src/{index,*.test}.ts` | Barrel + testes |
| `packages/tokens/tokens.css` | Tokens canônicos do backend (espelho do skin) |
| `package.json` (raiz/apps/packages) | Workspaces |
| `apps/server/tsconfig.json` · `tsconfig.base.json` | TypeScript |
| `apps/server/drizzle.config.ts` | Drizzle Kit (migrations) |
| `docker-compose.yml` | Postgres 16 local |
| `vitest.config.ts` | Testes |
| `.env.example` | Variáveis de ambiente |
| `README.md` | Setup do backend |

---

## Mapa de ponte design ↔ backend (o que troca na produção)

| Design (`app/`) | Vira (`backend/`) | Contrato |
|---|---|---|
| `mock-data.jsx` + `OIStore` | chamadas tRPC | `packages/shared/contracts.ts` |
| `OIFlow.faturar` | `routers/financeiro.criarReceber` | `domain/finance/titulos.ts` |
| `OIPedidos.advance` | `routers/producao` + FSM | pipeline canônico |
| preço digitado | `routers/orcamento.calcular` | `domain/quote` |
| margem em `*-data.jsx` | `produtos.margemPct` (cadastro) | `domain/quote` (servidor autoritativo) |
