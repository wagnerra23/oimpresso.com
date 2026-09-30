---
slug: app-mobile-sdd
title: "SDD — App Oimpresso Mobile (iOS + Android)"
type: sdd
module: AppMobile
status: ativo
owner: wagner
version: 1.0.0
last_updated: 2026-06-19
related_docs:
  - SDD-tela-vendas.md
  - GUIA-CONSTRUCAO-VENDAS.md
  - GUIA-CONSTRUCAO-MOBILE.md
  - design_handoff_oimpresso_mobile/README.md
  - design_handoff_oimpresso_mobile/DESIGN-SYSTEM.md
  - design_handoff_oimpresso_mobile/ARCHITECTURE.md
  - design_handoff_oimpresso_mobile/BUILD-GUIDE.md
  - Plano de Refundação e Desenvolvimento.html
  - Avaliação Hostil v3.html
related_adrs:
  - M-0001-prototipo-window-globals-sem-bundler
  - M-0002-oistore-localstorage-fonte-unica
  - M-0003-oiflow-faturamento-vinculo-por-id
  - M-0004-pipeline-fsm-pedido-e-os-mobile
  - M-0005-shell-tabbar-nav-stack-bottom-sheet
  - M-0006-tokens-claro-escuro-densidade
  - M-0007-multi-tenant-papeis-perfil-menu
---

# SDD — Software Design Document · App Oimpresso Mobile

> **Escopo deste documento:** consolidar, num único design document, a arquitetura, governança, design system e casos de uso do **aplicativo móvel** do oimpresso (clientes nativos iOS + Android). Este SDD é o **mapa de cima** que amarra o protótipo interativo atual (`Oimpresso Mobile.html` + ~28 módulos `.jsx`) ao backend canônico compartilhado com o web (`oimpresso-erp/`) e ao plano de refundação.
>
> **Documento par:** [GUIA-CONSTRUCAO-MOBILE.md](GUIA-CONSTRUCAO-MOBILE.md) — o SDD diz *o que* o app é; o guia diz *como* construir nele sem regressão. Mesma relação que [SDD-tela-vendas.md](SDD-tela-vendas.md) ↔ [GUIA-CONSTRUCAO-VENDAS.md](GUIA-CONSTRUCAO-VENDAS.md).
>
> **Fontes canônicas:** protótipo em `Oimpresso Mobile.html` + `*.jsx`; design system em `oimpresso-tokens.css`; backend/domínio em `oimpresso-erp/`; diagnóstico em `Avaliação Hostil v1–v3`; sequência em `Plano de Refundação e Desenvolvimento`.

---

## 1. Visão geral

O app mobile é o **companheiro de operação de chão** do oimpresso — o ERP no bolso de quem está longe do balcão: o operador na produção, o mecânico na oficina, o vendedor em visita, o dono conferindo o caixa fora da loja. Cobre, em formato de celular, o mesmo ciclo do web — **pedido → orçamento → aprovação → produção → faturamento → financeiro** e **OS de oficina → diagnóstico → aprovação → execução → faturamento** — sobre o **mesmo backend tRPC** e o **mesmo modelo de dados** do desktop (não é um produto separado; é outra cabeça sobre o mesmo corpo).

O artefato atual é um **protótipo interativo de alta fidelidade** (HTML + React 18 via Babel standalone, sem bundler), que renaiza simultaneamente em molduras **iOS (iPhone 15)** e **Android (Pixel 8)** lado a lado, mais uma moldura de **Login**. O protótipo já tem estado real compartilhado (`OIStore` sobre `localStorage`), navegação em pilha, fluxos ponta-a-ponta e um design system de tokens com tema claro/escuro — mas é um **protótipo de validação**, não o app de produção (que seria React Native / PWA consumindo o backend real). Esta distinção é tratada como cidadã de primeira classe ao longo do documento (maturidade marcada ✅/🟡/⚪).

### 1.0 As duas verticais (espelham o web)

O app atende **as mesmas duas verticais** do negócio, e — como no web — o estado de maturidade é assimétrico:

| Vertical | Natureza no mobile | Estado atual |
|---|---|---|
| **Gráfica / Comunicação visual** | Pedido sob medida acompanhado em campo: consulta de etapa, avanço do pipeline, faturamento na entrega. | ✅ Fluxo E2E ligado (pedido → faturar → financeiro) · 🟡 item personalizado (L×A/m²/material) ainda como produto de prateleira |
| **Oficina / Manutenção de frota** | OS de veículo: triagem no pátio, diagnóstico, aprovação de itens, execução, faturamento. | ✅ Pipeline + aprovação de itens + faturamento E2E · 🟡 PCP de capacidade e checklist por tipo ainda rasos |

Ambas hoje rodam sobre **dados simulados** (`mock-data.jsx`, `oficina-data.jsx`, `finance-data.jsx`) com mutações persistidas via `OIStore`. A ligação ao backend real (`oimpresso-erp/`) é a fronteira que separa protótipo de produto.

### 1.1 Família de telas (mobile)

| Módulo | Arquivo(s) | Papel | Status |
|---|---|---|---|
| **Início (cockpit)** | `screens-home-tasks.jsx` + `dashboard-widgets.jsx` | Dashboard de tarefas + KPIs derivados (faturamento, a receber, produção) | ✅ maduro (números derivados do ledger/jobs) |
| **Pedidos** | `screens-modules.jsx` (`PedidosScreen`, `PedidoDetalheScreen`) | Lista + detalhe com stepper de pipeline + faturamento | ✅ E2E |
| **Novo pedido** | `screens-novo-pedido.jsx` | Wizard que cria OS na lista (store) | ✅ |
| **Produção** | `screens-producao.jsx` | Triagem por urgência + fila + carga de estações | ✅ consulta · 🟡 capacidade |
| **Oficina** | `screens-oficina.jsx`, `screens-oficina-os.jsx`, `screens-oficina-cadastro.jsx` | Pátio + detalhe da OS (itens/aprovação/local/mecânico) + cadastro | ✅ E2E |
| **Equipamentos** | `screens-equipamentos.jsx` | Frota/veículos vinculados à oficina | 🟡 consulta |
| **Financeiro** | `screens-financeiro.jsx` + `finance-data.jsx` | Caixa · A receber · A pagar · Extrato (lê `fin.extra`) | ✅ recebe títulos do faturamento |
| **Clientes** | `screens-clientes-producao.jsx`, `screens-novo-cliente.jsx` | Lista/cadastro de pessoas multi-papel | ✅ |
| **Produtos** | `screens-modules.jsx` (`ProdutosScreen`), `screens-novo-produto.jsx` | Catálogo + estoque (margem no cadastro) | ✅ |
| **Relatórios / Equipe** | `screens-relatorios.jsx`, `screens-relatorios-equipe.jsx` | Indicadores e equipe | 🟡 |
| **Notificações** | `screens-modules.jsx` (`NotifsScreen`) | Lidas + navegação à origem | ✅ |
| **Menu / Perfil / Empresa / Perfis** | `screens-modules.jsx`, `screens-perfis.jsx`, `menu-perfis.jsx` | Troca de empresa, papel, perfil de menu | ✅ |
| **Login** | `screens-modules.jsx` (`LoginScreen`) | Marca Oimpresso (moldura separada) | ✅ estático |
| **Shell + molduras** | `mobile-app.jsx`, `ios-frame.jsx`, `android-frame.jsx` | Tab bar, nav stack, status bar, teclado | ✅ |

---

## 2. Público-alvo e personas

O design do app é dirigido por **personas de mobilidade** — pessoas que usam o celular *porque não estão na frente do balcão*. Diferem das personas do web ([SDD-tela-vendas §2](SDD-tela-vendas.md)) na intenção: no web a tarefa é digitação densa (orçar, faturar em lote); no mobile é **consulta rápida, apontamento e decisão em movimento**.

### P1 · Beto — OPERADOR DE PRODUÇÃO / MECÂNICO (chão de fábrica) — maior volume de toques
- Está de pé, mãos ocupadas, luz forte, às vezes luva. Abre o app pra **ver o que é urgente, avançar etapa, apontar conclusão**.
- **Decisões de design derivadas:** triagem por urgência como visão padrão (`prodView`), alvos de toque ≥ 44px, status badge legível sob sol (dot + texto, alto contraste), avanço de etapa em 1 toque com confirmação por toast, ações destrutivas/financeiras **bloqueadas pelo papel** (`papel: "operador"`), tema escuro como padrão de galpão.
- **Regra de ouro:** operador **nunca** fatura, nunca muda preço/margem, nunca pausa execução sem autorização (gate de papel).

### P2 · Wagner — DONO / GERENTE (fora da loja) — decisão e conferência
- Confere **caixa, a receber, vencidos e produção** do celular, entre reuniões. Quer o número certo, não o painel cheio.
- **Decisões derivadas:** Início com KPIs **derivados do ledger real** (a receber, vencidos, recebido/pago do mês) e dos jobs reais (em produção, prontos, atrasados) — nunca literal escrito à mão; troca de empresa no topo; acesso a faturamento e fiscal; alerta de caixa negativo na projeção.

### P3 · Vivi — VENDEDORA EM CAMPO / BALCÃO MÓVEL — cadastro e acompanhamento
- Visita cliente, abre orçamento, acompanha o pedido, cobra. Conexão instável.
- **Decisões derivadas:** cadastro inline de cliente sem perder o contexto (bottom sheet), acompanhamento do pedido pelo stepper, faturamento com escolha de parcelas/meio, **margem travada vinda do cadastro do produto** (vendedor não decide lucro). Offline-first é requisito (🟡 ainda não implementado — §9).

> **Princípio herdado do web ([ADR 0105](SDD-tela-vendas.md)):** só entra no backlog item com **sinal qualificado** — cliente pede/reporta ou métrica de uso comprova. Pedido vago → perguntar antes de construir.

---

## 3. Governança aplicável

Mesma hierarquia em camadas do web (Constituição v2), herdada porque o mobile compartilha o backend. Em caso de conflito, **a camada de cima vence**; mudar camada de cima exige ADR nova append-only.

### 3.1 Tier 0 — IRREVOGÁVEL
- **Multi-tenant isolation:** `tenant_id` (no protótipo, a empresa ativa de `TENANTS`/tweak `tenant`) em todo dado e toda query do backend; cross-tenant → **404**. No protótipo, `localStorage` é sempre prefixado `oimpresso.*` (ver `oi-store.jsx`, `PREFIX`+`VERSION`) — nunca `sessionStorage`, nunca cruza empresa.
- **LGPD/PII:** documento de cliente mascarado no backend antes de chegar à listagem; histórico de etapa (FSM) append-only (`audit_log` no backend `oimpresso-erp/lib/audit`).
- **Fiscal:** emissão SEFAZ **sempre por clique humano** (nunca on-mount); numeração sequencial atômica server-side; NF-e cancelada nunca reaproveita número. No mobile, a ação "Faturar" cria **título financeiro** (a receber); a **emissão fiscal** é ação fiscal separada e explícita (módulo Fiscal), nunca implícita no faturamento.

### 3.2 Processo de mudança
- **Charter por tela** (Tier A) ao lado de cada módulo `.jsx` quando promovido a produção; mudança = PR aprovado por Wagner, append-only.
- **Componente local primeiro**, extrai pra compartilhado só no 2º uso (regra R-DS-001). No protótipo isso já acontece: `OISheet`, `OrderStepper`, `ProductThumb`, `DetailHeader` viraram compartilhados por reuso real.
- **Estado compartilhado é o único caminho de mutação:** toda mudança que precisa refletir entre lista/detalhe/dashboard passa por `OIStore.set` (ver `oi-store.jsx`). Mutar React state local sem propagar ao store é bug de governança (equivale ao GAP G4 do web — UPDATE direto fora da máquina).

### 3.3 Pipeline FSM (pedido e OS) — espelho do backend
- **Pedido (gráfica):** `orc → aprov → prod → faturar → entrega → done` (`window.OIPedidos.ORDER` em `oi-store.jsx`). Avanço por `OIPedidos.advance()`; a etapa **faturar** não avança direto — abre a folha de faturamento (regra de negócio).
- **OS (oficina):** `Triagem → Diagnóstico → Aprovação → Execução → Qualidade → Pronto` (`MANUT_PIPE` em `oficina-data.jsx`), seguida de **Faturar → Entregar veículo** (`screens-oficina-os.jsx`).
- **RBAC por transição:** ações críticas gated por papel (`operador`/`gerente`/`admin`). Ex.: pausar execução exige gerente/admin (`podeAutorizar` em `screens-oficina-os.jsx`); faturar não é papel de operador.
- **Faturamento idempotente:** `OIFlow.faturar()` é vinculado por **id da OS** (`origem: "OS-3041"`), nunca por string solta, e bloqueia faturamento duplicado (`jaFaturado`). Espelha `domain/finance/titulos.ts` do backend.

---

## 4. Design system aplicável

Hierarquia idêntica à Constituição UI v2 (Fundações → Shell → Padrão de Tela → Módulo). Fonte única: **`oimpresso-tokens.css`** + classes `oi-*`.

| Camada | O que vale pro mobile |
|---|---|
| **1 · Fundações** | Tokens de cor/tipo/espaço/raio/sombra em `oimpresso-tokens.css`, com **tema claro e escuro** e **3 densidades** (compact/normal/comfy, tweak `density`). Números/moeda/datas com `tabular-nums` (classe `oi-mono`/`oi-money`). Acento roxo da marca + verde de ação. |
| **2 · Shell** | `mobile-app.jsx` — **tab bar** inferior + **nav stack** (`nav.push/pop/replace`) + header de detalhe (`DetailHeader`). Molduras de device (`ios-frame.jsx`/`android-frame.jsx`) com status bar e teclado. |
| **3 · Padrão de Tela** | **Lista-detalhe mobile:** lista rolável (`oi-scroll` + `oi-list`/`oi-card`) → FAB de criação (`oi-fab`) → detalhe empilhado → **bottom sheet** (`OISheet`) para ações pontuais (nunca modal sobre modal). Cockpit do Início = KPIs (`oi-cubetile`/`oi-sparkcard`) + tarefas. |
| **4 · Módulo** | Classes semânticas `oi-*` escopadas — `oi-card`, `oi-btn` (`primary`/`secondary`/`action`/`sm`/`block`/`ghost`), `oi-status` (dot + texto, sem bg-fill), `oi-chips`, `oi-dl`, `oi-money`, `oi-section`, `oi-empty`. **Nunca cor crua/hex** fora dos tokens; ícones via `window.Ic` (`icons.jsx`), nunca emoji decorativo. |

**Regras de ouro mobile que o protótipo cumpre:** status badge sem preenchimento (dot Stripe-style); ação destrutiva por último em vermelho; bottom sheet único (Copiloto/conversa abrem rota própria, não empilham modal); alvos ≥ 44px; estado vazio com `oi-empty` (ícone + título + dica); toast para confirmação efêmera, `FieldError` inline para erro de campo.

---

## 5. Arquitetura

### 5.1 Visão em camadas (protótipo atual)

```
┌─────────────────────────────────────────────────────────────────────┐
│ APRESENTAÇÃO — React 18 + Babel standalone (sem bundler)             │
│  Oimpresso Mobile.html (3 stages: iOS · Android · Login)            │
│  mobile-app.jsx (shell: tab bar + nav stack)                        │
│  ~24 telas screens-*.jsx + dashboard-widgets.jsx + task-viewers.jsx │
├─────────────────────────────────────────────────────────────────────┤
│ COMPONENTES & ÍCONES                                                 │
│  icons.jsx (window.Ic) · OISheet (bottom sheet) · DetailHeader ·    │
│  OrderStepper · ProductThumb · molduras ios/android-frame.jsx       │
├─────────────────────────────────────────────────────────────────────┤
│ ESTADO & DOMÍNIO (cliente)                                           │
│  oi-store.jsx → OIStore (localStorage pub/sub) + useStore + OISheet  │
│                 OITasks · OIPedidos (pipeline+apply+advance)         │
│  oi-flow.jsx  → OIFlow (faturamento: gerarParcelas/faturar/estornar) │
├─────────────────────────────────────────────────────────────────────┤
│ DADOS (simulados no protótipo)                                       │
│  mock-data.jsx · oficina-data.jsx · finance-data.jsx · menu-perfis  │
│  Persistência de mutações: localStorage (chaves oimpresso.*)        │
├─────────────────────────────────────────────────────────────────────┤
│ BACKEND CANÔNICO (compartilhado com o web — oimpresso-erp/)          │
│  tRPC routers: pedidos · orcamento · estoque · producao ·           │
│   financeiro · fiscal | Postgres+Drizzle (UUID, tenant_id, RLS,     │
│   timestamptz, money=cents, numeração atômica) | domain/* + testes  │
└─────────────────────────────────────────────────────────────────────┘
```

> **Fronteira protótipo↔produção:** as camadas de cima (apresentação, componentes, estado) são reais e reaproveitáveis como especificação de UX. A camada de DADOS hoje é simulada; promover a produto = trocar `mock-data`/`OIStore` por chamadas tRPC ao `oimpresso-erp/` (mesmos contratos zod), mantendo telas e fluxos.

### 5.2 Estado compartilhado (`OIStore`)

`OIStore` é a **fonte única de verdade do cliente**: `init/get/set/subscribe` sobre `localStorage` com pub/sub; o hook `useStore(key, initial)` re-renderiza todos os inscritos na mesma chave e persiste. Chaves canônicas em uso: `pedidos.mut` (overrides de etapa + criados), `oficina.os` (estado por OS), `fin.extra` (títulos emitidos pelo faturamento), `fin.liq` (liquidações), `produtos.estoque`. Versionamento por `VERSION` invalida dados antigos. **Toda** mutação cross-tela passa por aqui.

### 5.3 Faturamento (`OIFlow`) — o elo entre módulos

`oi-flow.jsx` fecha o ciclo operação→dinheiro:
- `gerarParcelas(total, n, vencDias)` — divide em centavos **sem perder centavo** (última parcela absorve a sobra); espelha `domain/finance/titulos.ts`.
- `faturar({origemId, parte, valor, parcelas, meio, ...})` — emite título(s) a receber em `fin.extra` (a chave que o **Financeiro já lê**), com **vínculo por id** (`origem`) e **idempotência** (`jaFaturado`).
- `estornar(origemId)` / `faturamentoDe(origemId)` — reverter e consultar.

Como o Financeiro (`screens-financeiro.jsx`) já consome `useStore("fin.extra")` e funde com `FIN_LANC`, os títulos aparecem automaticamente em **A receber**, no Resumo e na projeção de caixa — sem fetch paralelo.

### 5.4 Fluxos críticos

**F1 · Pedido gráfica E2E:** `PedidoDetalheScreen` → avança `orc→aprov→prod` (1 toque) → em **faturar**, abre `OISheet` de parcelas (1×/2×/3×/6× + Boleto/PIX/Cartão + prévia) → `OIFlow.faturar` cria títulos → avança a `entrega` → bloco "Faturamento" no detalhe com link "Ver em A receber".

**F2 · OS oficina E2E:** `ManutOsDetalheScreen` → pipeline até **Pronto** → botão **Faturar OS** (pelos itens aprovados/aplicados, `totAprov`) → mesma folha de parcelas → após faturar, libera **Entregar veículo**.

**F3 · Coerência do Início:** dinheiro do dashboard **derivado** de `finResumo(FIN_LANC + fin.extra)` (a receber, vencidos, recebido/pago do mês); tiles de produção **derivados** dos jobs reais (em produção, prontos, atrasados). Nenhum número literal escrito à mão.

**F4 · Navegação por origem:** notificações e blocos de faturamento navegam à origem via `nav.push("transacao"/"pedido"/"producao-job"/...)` — cross-link entre módulos.

### 5.5 Componentes principais

`OISheet` (bottom sheet genérico) · `DetailHeader` (header de detalhe com voltar/ações) · `OrderStepper` (stepper do pipeline) · `ProductThumb` (miniatura por tipo) · widgets do Início (`oi-cubetile`, `oi-sparkcard`) · `FinRow`/`ResumoView`/`FinStat` (financeiro) · folhas de faturamento (pedido e OS) · sheets de oficina (`AddItemSheet`, `PausaSheet`, `LocalSheet`, `MecSheet`) · molduras `IOSDevice`/`AndroidDevice` · painel `TweaksPanel` (tema/densidade/empresa/papel/perfil/visão de produção).

---

## 6. Casos de uso

### 6.1 Núcleo `must`

| CU | Caso | Prio | Status |
|---|---|---|---|
| CU-M01 | Ver tarefas do dia + KPIs reais no Início | must | ✅ |
| CU-M02 | Abrir pedido e avançar etapa do pipeline (1 toque) | must | ✅ |
| CU-M03 | Criar pedido (wizard) que aparece na lista | must | ✅ |
| CU-M04 | Faturar pedido na entrega → parcelas no financeiro | must | ✅ |
| CU-M05 | Consultar produção por urgência / fila / estação | must | ✅ |
| CU-M06 | Abrir OS de oficina, aprovar itens, avançar status | must | ✅ |
| CU-M07 | Faturar OS pronta (itens aprovados) → financeiro | must | ✅ |
| CU-M08 | Financeiro: A receber / vencidos / extrato + liquidar | must | ✅ |
| CU-M09 | Cadastrar cliente inline (bottom sheet) | must | ✅ |
| CU-M10 | Trocar empresa ativa (multi-tenant) | must | ✅ |
| CU-M11 | Papel limita ações (operador não fatura/pausa) | must | ✅ (gate por papel) |
| CU-M12 | Margem do produto trava o preço (vendedor não edita) | must | ✅ (cadastro) |
| CU-M13 | Notificações: ler + navegar à origem | should | ✅ |
| CU-M14 | Item personalizado L×A/m²/material no pedido | should | 🟡 sem cobertura (gap §10) |
| CU-M15 | Emissão fiscal (NF-e/NFS-e) a partir da venda | should | 🟡 backend pronto, UI mobile ausente |
| CU-M16 | Offline-first (apontamento sem rede + sync) | could | ⚪ não implementado |

### 6.2 Non-goals explícitos (por design)
- Edição de venda finalizada (paga ou faturada) — usar estorno (`OIFlow.estornar`).
- Modal sobre modal — bottom sheet único; conversa/Copiloto abrem rota própria.
- Faturar OS sem itens aprovados; faturar a mesma OS 2× (idempotência).
- Operador executando ação financeira/fiscal/de pausa (gate de papel).
- App mobile como produto desconectado: ele **compartilha** o backend do web — não duplica regra de negócio.

---

## 7. Requisitos não-funcionais

| Categoria | Alvo | Observação |
|---|---|---|
| Toque | Alvos ≥ 44px · gestos de voltar nativos · sem hover-dependência | chão de fábrica (Beto) |
| Legibilidade | Tema escuro padrão de galpão · contraste sob sol · `tabular-nums` | persona P1 |
| Performance | Lista < 16ms/frame · transição de tela < 250ms · sheet < 200ms | percepção mobile |
| Compatibilidade | 360px (Android low-end) usável · safe-area iOS · teclado não cobre campo | molduras testadas |
| Confiabilidade | 0 erro JS no console · estado sobrevive a refresh (`OIStore`) · rollback de tema/empresa instantâneo | protótipo |
| Offline | Apontamento e consulta sem rede + fila de sync idempotente | ⚪ pendente (§9) |
| Segurança | `tenant_id` no backend · 404 cross-tenant · PII mascarada · RBAC por papel/transição | herdado do web |
| Fiscal | Emissão só por clique · numeração atômica · idempotência | backend `oimpresso-erp/` |

---

## 8. Estratégia de qualidade e rollout

### 8.1 Verificação
- **Carga limpa:** 0 erro no console em ambas as molduras (iOS/Android) — gate a cada entrega.
- **Coerência de dados:** todo número de dashboard tem que ser **derivado** de fonte única (`finResumo`, jobs) — número literal é bug (varredura de coerência §10).
- **Fluxo E2E:** pedido e OS percorrem o pipeline até faturar e o título aparece no financeiro (verificado ao vivo via `OIFlow`/`fin.extra`).
- **Gate de papel:** operador não vê/usa ações financeiras, fiscais ou de pausa.

### 8.2 Rollout (quando promovido a produção)
1. Trocar camada de dados simulada por tRPC ao `oimpresso-erp/` mantendo telas/fluxos.
2. Feature flag por empresa; canary com Wagner (biz dele) antes de liberar a operadores.
3. Aviso humano prévio à equipe de chão + canal de report.
4. Monitorar 30 dias antes de aposentar qualquer caminho antigo.

---

## 9. Riscos e dívidas conhecidas

| Item | Risco | Plano |
|---|---|---|
| Dados simulados (mock + `OIStore`) | Protótipo parece produto em demo, mas não sincroniza entre aparelhos reais | Ligar ao backend `oimpresso-erp/` (mesmos contratos) — fronteira §5.1 |
| Sem offline real | Apontamento no galpão sem rede falha | Service Worker + fila idempotente (Plano de Refundação A10) |
| Item personalizado raso (CU-M14) | Comvis tratada como prateleira | Reusar `domain/quote` (imposição + custeio) no form mobile |
| Emissão fiscal sem UI mobile (CU-M15) | Vender sem nota no celular | UI mínima sobre `routers/fiscal` (NFS-e/NF-e) |
| PCP de produção raso | Prazo/carga sem capacidade real | Reusar `domain/production` do backend |
| Sincronização "ilusória" no protótipo | Os 2 devices compartilham `localStorage` da mesma aba — não é multiusuário real | Explícito no §5.1; resolvido só com backend |

---

## 10. Roadmap de evolução

> Diagnóstico (alinhado às Avaliações Hostis v1–v3 e ao Plano de Refundação): o app mobile tem **UX e fluxos fortes** sobre um **backend que já foi refundado** (Fases 0–2 do `oimpresso-erp/`: modelo de dados, orçamento, estoque, produção, financeiro, fiscal). O maior retorno agora é **ligar o protótipo ao backend** e **fechar as varreduras de coerência** — não redesenhar telas.

### 10.1 Construção (destrava o resto)
| ID | Melhoria | Motivação |
|---|---|---|
| **CM1** | Camada de dados real: trocar `mock-data`/`OIStore` por client tRPC do `oimpresso-erp/` | Fim da "sincronização ilusória"; multiusuário real |
| **CM2** | Offline-first (Service Worker + IndexedDB + fila idempotente) | Apontamento no galpão sem rede (P1 Beto) |
| **CM3** | Formalizar papéis/perfil de menu como gate declarativo (não ifs espalhados) | 3 papéis × 2 verticais = emaranhado de flags |

### 10.2 Funcionalidade — comunicação visual (perna fraca)
| ID | Melhoria | Amarração |
|---|---|---|
| **FM1** | Item personalizado no pedido (L×A, m², material, acabamento) com preço via `domain/quote` | CU-M14 · depende CM1 |
| **FM2** | Aprovação de orçamento por link público + registro de quem aprovou | espelha `AprovacaoPublica` |
| **FM3** | Arte anexada ao item como gate da produção | retrabalho clássico de gráfica |

### 10.3 Funcionalidade — fiscal & financeiro
| ID | Melhoria | Amarração |
|---|---|---|
| **FM4** | UI mobile de emissão fiscal (NF-e/NFS-e) sobre `routers/fiscal` | CU-M15 · ISS×ICMS por natureza já no backend |
| **FM5** | Conciliação e baixa por meio (PIX/boleto) no celular | `domain/finance` |
| **FM6** | Reserva de estoque visível ("X em estoque · Y reservados") | `domain/inventory` pronto |

### 10.4 Varredura de coerência (higiene — em curso)
| ID | Item | Status |
|---|---|---|
| VC1 | Início: dinheiro derivado do ledger + tiles de produção dos jobs | ✅ feito |
| VC2 | Oficina/Equipamentos: contadores (OS abertas, valores) vs dados reais | 🟡 pendente |
| VC3 | Produção: carga das estações vs nº de jobs | 🟡 pendente |
| VC4 | Notificações/badges vs estado real | 🟡 pendente |

### 10.5 Sequência recomendada (ondas)
1. **Onda dados:** CM1 (+CM3) — liga ao backend, acaba a ilusão de sync.
2. **Onda coerência:** VC2–VC4 — barato, alto valor de confiança (igual VC1).
3. **Onda comvis:** FM1 + FM2 + FM3 — fecha a vertical gráfica no celular.
4. **Onda fiscal/financeiro:** FM4–FM6.
5. **Paralelo:** CM2 (offline) — independe das ondas, alto valor pro chão.

---

## 11. Referências

- **Protótipo:** `Oimpresso Mobile.html` + `oi-store.jsx` · `oi-flow.jsx` · `mock-data.jsx` · `oficina-data.jsx` · `finance-data.jsx` · `screens-*.jsx` · `dashboard-widgets.jsx` · `icons.jsx` · `oimpresso-tokens.css` · `ios-frame.jsx`/`android-frame.jsx`/`tweaks-panel.jsx`
- **Backend canônico (compartilhado):** `oimpresso-erp/` — `apps/server/src/routers/*` (pedidos, orcamento, estoque, producao, financeiro, fiscal), `domain/*` (quote, inventory, production, finance, fiscal), `db/schema.ts` + `rls.sql`, `packages/shared` (money/contracts)
- **Handoff:** `design_handoff_oimpresso_mobile/README.md` · `DESIGN-SYSTEM.md` · `ARCHITECTURE.md` · `BUILD-GUIDE.md`
- **Diagnóstico e plano:** `Avaliação Hostil v1.html` · `v2` · `v3` · `Plano de Refundação e Desenvolvimento.html`
- **Par documental:** [GUIA-CONSTRUCAO-MOBILE.md](GUIA-CONSTRUCAO-MOBILE.md) (a escrever) · referências de estrutura: [SDD-tela-vendas.md](SDD-tela-vendas.md) · [GUIA-CONSTRUCAO-VENDAS.md](GUIA-CONSTRUCAO-VENDAS.md)

---

### Changelog
- **v1.0.0 (2026-06-19)** — Primeira versão. Consolida o protótipo mobile atual (iOS+Android), o estado compartilhado (`OIStore`), o motor de faturamento (`OIFlow`), o fluxo E2E pedido/OS→financeiro e a relação com o backend refundado. Espelha a estrutura do `SDD-tela-vendas.md`.
