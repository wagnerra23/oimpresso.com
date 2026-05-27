# Plano UI — Venda por Estágio (FSM canônica)

> **Status:** rascunho [CC] · 2026-05-10
> **ADR mãe:** 0129 — State Machine canônica · accepted 2026-05-10
> **Caso prático canônico:** [`memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — Vargas → Pizzaria Nicola (banner R$350 NFe55 + instalação R$200 NFSe56 = 1 transação, 2 documentos)
> **US referenciadas:** US-SELL-010 (FSM 4 tabelas), US-SELL-011 (FSM core), US-SELL-012 (gate emissão), US-SELL-013 (reserva estoque), US-SELL-014 (poly N notas), US-NFE-059 (smoke prod), US-NFE-060 (NFSe 56 nacional)

---

## 1. Nome canônico da estrutura

**Venda por Estágio** (FSM = Finite State Machine) — 1 transação `sales_transactions` com 1..N estágios percorridos, 1..N OS internas, 1..N documentos fiscais polimórficos, 0..N reservas de estoque.

Substitui o modelo "venda = ato único + nota = ato único" dos concorrentes (Mubisys/Bling/Tiny). Fonte canônica: case Vargas/Pizzaria + ADR 0129.

## 2. Modelo de dados (resumo, fonte = caso prático §FSM concreto)

```
sales_transactions               (cabeçalho — estado: draft → quote_approved → … → closed)
 ├─ sales_transaction_items      (mix produto+serviço, NCM/cod_serv/CFOP por linha)
 ├─ sales_transaction_stages     (FSM: estado atual + histórico de transições com actor + timestamp)
 ├─ sales_transaction_actions    (transições permitidas + side_effect_class)
 ├─ sale_stage_action_roles      (RBAC granular por transição — Spatie Permission)
 ├─ stock_reservations           (não baixa estoque, só reserva — expires_at, status=active|consumed|released)
 └─ transaction_documents        (polimórfico: nfe55 | nfce65 | nfse56 | mdfe58 | cte57)
      ├─ doc_class (Eloquent morph)
      ├─ doc_id, value, status_sefaz, chave_acesso, xml_path, danfe_pdf_path
      └─ trigger_stage (qual transição emitiu)
```

Por venda, **N documentos fiscais** ficam atrelados em momentos diferentes do FSM, cada um com status SEFAZ próprio.

## 3. Aplicabilidade por vertical (3 perfis)

| Vertical | Caso típico | Estágios usados | Documentos típicos | Complexidade UI |
|---|---|---|---|---|
| **Comunicação Visual** (foco P0) | banner + instalação | 9 estágios completos (draft→closed) | **NFe55 + NFSe56** (mesma transação) + opcional MDFe58 | **alta** — multi-doc, reserva estoque (m²), produção+instalação |
| **Mecânica/OS** (Repair, OficinaAuto) | conserto com peça | mesmo FSM, item LC 14.01/14.05 | **NFe55 (peça) + NFSe56 (mão obra)** | média — 2 docs, sem reserva m² |
| **Vestuário** (ROTA LIVRE) | venda balcão | FSM curto (draft→invoiced→closed) | **1 NFC-e 65** (ou nenhuma — "venda sem nota") | baixa — fluxo express |

**Insight chave (do caso prático):** o FSM é o mesmo. **O que muda por vertical é o seed de "processo padrão"** instalado em `sales_transaction_actions` — não as telas. Não bifurcar UI; configurar processo.

## 4. Mapeamento UI — telas que já existem neste protótipo

| Tela atual | Papel novo no modelo FSM | Mudança necessária |
|---|---|---|
| `vendas-page.jsx` (Index) | mantém — lista `sales_transactions` com estágio atual visível | adicionar coluna **Estágio** (badge colorido: draft/aprovada/produção/instalação/faturada/entregue/fechada/cancelada) e coluna **Docs** (chips: 55/56/58 com status SEFAZ) |
| `VendaCreateDrawer` (atual em `vendas-page.jsx`) | **redefinir:** drawer só cria `draft` (cliente + itens + observação) | remove escolha de pagamento e nota — esses não pertencem ao draft |
| `VendaDetailDrawer` (atual) | **promover a página `/vendas/:id`** com 5 cards | tela detalhe persistente em vez de drawer (caso prático §UI tela `/sells/12345`) |
| `vendas-create-completo.jsx` (escrito hoje) | **descartado** — modelava wizard linear, não FSM | apagar ou mover pra `prototipo-ui-patch/_descartados/` como referência |
| `os-page.jsx` | OS interna agora é **filha da transação**, não entidade separada | adicionar link bidirecional OS ↔ Venda; OS herda estado de produção da FSM da venda |
| `producao-page.jsx` | recebe ações `iniciar_producao`/`concluir_producao` da FSM | cada card de fila lê `sales_transaction_stages` da venda pai |

## 5. Página `/vendas/:id` — 5 cards (proposto)

```
┌─ Cabeçalho — Venda #V-7831  · cliente: Pizzaria Nicola · total R$ 550,00 · Estágio: ready_to_install ─┐
│                                                                                                       │
│  [1] FSM atual ──────────────────────────────────────┐  [2] Itens (mix produto+serviço) ─┐           │
│  ●─●─●─○─○─○─○─○─○                                  │  • Banner lona 3×2m   R$ 350     │           │
│  draft  apr  prod  rti  inst  comp  inv  del  cls   │    NCM 5907 · CFOP 5101 · 6m²    │           │
│  Última transição: concluir_producao · op:Carlos    │  • Instalação fachada R$ 200     │           │
│  Próxima ação: [iniciar_instalacao]                 │    LC 17.06 · ISS 5%             │           │
│  RBAC: instalador, gerente                          │                                  │           │
│                                                     │                                  │           │
│  [3] Reserva de estoque ────────────────────────────│  [4] Documentos fiscais (timeline)│           │
│  ✓ 6m² lona 380gr · status: consumed (em produção) │  ⏳ NFe55 — agendado pra fatu-    │           │
│    expirou em 2026-06-09 (não usado por agora)     │     rar_os                        │           │
│  Sem mais reservas.                                 │  ⏳ NFSe56 — agendado idem        │           │
│                                                     │  — MDFe58 dispensado (mesmo mun.)│           │
│  [5] Pagamentos ────────────────────────────────────┤                                  │           │
│  • PIX R$ 550 · agendado pra invoiced               │  Total documentado: R$ 0 / 550   │           │
└─────────────────────────────────────────────────────────────────────────────────────────┘           │
└───────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

Botão de ação principal flutuante: a transição habilitada agora (`iniciar_instalacao` neste exemplo) — o ÚNICO botão visível pro usuário; resto do FSM é informativo.

## 6. Diferença chave vs. drawer wizard que estávamos construindo

| Wizard linear (descartado) | Página por estágio (proposto) |
|---|---|
| Usuário escolhe nota fiscal **antes** de a OS rodar | Notas são emitidas como **side-effect da transição** (`faturar_os`) — usuário não escolhe nota, escolhe transição |
| Modela só venda balcão simples | Modela balcão (Vestuário) E venda longa (ComVis/Mecânica) com mesmo modelo |
| Bifurca UI por vertical (3 wizards) | UI única; vertical = seed de processo no FSM |
| Estoque baixa na confirmação | Reserva no `quote_approved`; baixa em `concluir_producao` (caso prático §estoque) |
| MDFe é caixa de seleção | MDFe é side-effect opcional de `entregar_ao_cliente` quando carga > R$ 500 e município destino ≠ origem |
| Não tem RBAC | Cada transição valida `sale_stage_action_roles` (caso prático §RBAC) |

## 7. Qual a melhor forma de fazer (resposta direta)

**Ordem recomendada:**

1. **Backend primeiro (Claude Code):** US-SELL-011 instala 4 tabelas FSM + Spatie Permission roles. Sem isso, a UI seria mock duplicado.
2. **UI shell (eu, [CC]):** redesenhar `/vendas/:id` com os 5 cards do §5 — em **modo read-only com mock** seguindo o caso prático Vargas. Isso valida a forma da tela antes de ter backend real.
3. **US-SELL-013 + 014 paralelos:** reservas + poly N notas — desbloqueiam os cards [3] e [4] da tela.
4. **US-NFE-060:** NFSe 56 nacional — desbloqueia o caso prático ponta-a-ponta.
5. **US-SELL-012:** gate emissão (não permite `faturar_os` se reserva não foi consumida ou cliente sem CNPJ).
6. **US-NFE-059:** smoke produção — Vargas como cliente piloto natural.

**O que [CC] entrega na próxima rodada (Sprint atual deste cowork):**

- Página `/vendas/:id` em `vendas-detalhe.jsx` (substitui o drawer detail) — read-only com 5 cards
- Mock state usando dados do caso prático: V-7831 Pizzaria Nicola, banner+instalação, estágio `ready_to_install`
- Index `vendas-page.jsx` ganha colunas Estágio + Docs
- Listagem mostra também uma venda Vestuário (V-7832, 1 camiseta, NFC-e direta) e uma Mecânica (V-7833, troca fonte, NFe55+NFSe56) — pra provar que o modelo único cobre os 3 perfis

**O que NÃO entra agora:**
- Drawer wizard novo — descartar `vendas-create-completo.jsx`
- Configuração de processos por vertical (UI admin) — Sprint posterior
- Cancelamento NFe pós-prazo — já coberto em US-NFE-004

## 8. Pendências pra Wagner decidir antes de eu começar

1. **Confirma que ADR 0129 vai existir** com path `memory/decisions/0129-venda-por-estagio-fsm-canonica.md`? (Hoje retorna 404 no repo — vou referenciar mesmo assim, criação fica com Claude Code via US-SELL-010)
2. **Nome da entidade na UI:** "Venda" ou "OS"? Caso prático fala "OS Vargas" mas modelo é `sales_transactions`. Recomendo **Venda** na UI (engloba balcão Vestuário onde "OS" não faz sentido), com sub-tag "OS" quando a venda tem produção.
3. **Página detalhe:** rota `/vendas/:id` ou `/sells/:id`? Caso prático usa `/sells/12345`. Confirmo `/sells/:id` pra alinhar com nome técnico do módulo? (sidebar continuaria em PT: "Vendas")
4. **Vestuário:** seed de processo deve ter estágios curtos (`draft → invoiced → closed`) ou usar o mesmo de 9 estágios pulando os irrelevantes? Recomendo **seed curto** — Larissa não vê 9 estágios numa venda de camiseta.

---

**Próxima ação canônica:** após Wagner responder §8.1–§8.4, [CC] redesenha `vendas-detalhe.jsx` + ajusta `vendas-page.jsx` Index com mock do caso prático Vargas como tela de referência.
