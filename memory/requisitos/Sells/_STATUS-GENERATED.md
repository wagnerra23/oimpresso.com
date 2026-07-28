<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Sells · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 51 |
| CU no SDD | 6 |
| Telas (.tsx) | 8 |
| Telas com `casos.md` | 2 |
| UC declarados | 9 |
| UC com teste que os cita | 8 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| Tela `Caixa/Index` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Drafts` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Edit` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Quotations` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Show` sem `casos.md` | o contrato da tela (trio incompleto) |
| Tela `Subscriptions` sem `casos.md` | o contrato da tela (trio incompleto) |
| `CU-SELL-05` sem UC | caso de uso que o exercite — Editar linha em pt-BR sem inflar o decimal |
| `CU-SELL-06` sem UC | caso de uso que o exercite — Venda a prazo (fiado) fecha com saldo devedor |
| `CU-SELL-30` sem UC | caso de uso que o exercite — Enxergar a cobrança num relance |
| `CU-SELL-31` sem UC | caso de uso que o exercite — Reconhecer, na lista, o que já teve devolução |
| `US-SELL-002` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Backend dual Inertia/Blade + feature flag + Pest |
| `US-SELL-003` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Frontend skeleton + AppShellV2 + props contract |
| `US-SELL-004` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Triagem visibilidade campos (18 → 8 visíveis + 10 colapsávei |
| `US-SELL-005` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Produtos — busca + tabela + cálculos |
| `US-SELL-006` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Pagamento + frete + descontos colapsáveis |
| `US-SELL-007` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Atalhos + auto-save draft + estados visuais |
| `US-SELL-053` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — FieldError por campo + auto-open details em erro |
| `US-SELL-010` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Investigar State Machines existentes (Repair, Project, mcp_t |
| `US-SELL-011` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Modelar 4 tabelas FSM canônicas (processes + stages + action |
| `US-SELL-012` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Gate de emissão NFe por venda (aplicar FSM canônica em Sale) |
| `US-SELL-013` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Reservas de estoque (stock_reservations) — side-effects FSM  |
| `US-SELL-014` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Multi-documento por venda (transaction_documents poly) — N n |
| `US-SELL-018` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Filtros multi-data com presets Dia/Semana/Mês/Ano + custom · |
| `US-SELL-021` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Especificação campo "Data" (qual data: emissão / NF / fatura |
| `US-SELL-029` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — NFe cancelada via SEFAZ não sofre forceDelete (preserva sequ |
| `US-SELL-030` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — NfeInutilizacaoService — chama SEFAZ + persiste em `nfe_inut |
| `US-SELL-031` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Action FSM crítica (is_critical) exige role explícita (fail- |
| `US-SELL-032` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Observer bloqueia UPDATE direto em current_stage_id (gateway |
| `US-SELL-033` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Processo seed "Venda Com Produção" canônico (9 stages + 12 a |
| `US-SELL-034` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Side-effect `CancelarVendaCascade` orquestra NFe + boleto +  |
| `US-SELL-035` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — UI timeline de transições FSM (drawer + page) · **P2 UX/audi |
| `US-SELL-036` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — FSM rollout — migrar 14 vendas legadas biz=1 via bulk-start- |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-SELL-001 | `desconhecido` | Epic — Migrar /sells/create pra MWART |
| US-SELL-008 | `desconhecido` | QA: audit + smoke biz=1 + canary Wagner 7d + rollback plan |
| US-SELL-040 | `todo` | Pest integration HTTP full do `SellPosController@store` (caminho B) |
| US-SELL-009 | `todo` | Cutover ROTA LIVRE + remover Blade após 30d |
| US-SELL-015 | `todo` | Modo "Grade Avançada" — toggle + layout densa base · **P0** |
| US-SELL-016 | `desconhecido` | Multiseleção + ações em lote (imprimir/exportar/agrupar) · **P0** |
| US-SELL-017 | `desconhecido` | Totalizador rodapé (Qtd vendas + Σ R$ filtrado) · **P0** |
| US-SELL-019 | `todo` | Agrupamento drag-to-group por campo do grid · **P1 confirmado** |
| US-SELL-020 | `todo` | Especificação campo "Status" (financeiro vs produção vs fiscal — badges separado |
| US-SELL-022 | `todo` | Sub-linha de produtos por venda (expandir linha) · **P2 confirmado** |
| US-SELL-023 | `todo` | Status produção visível na lista (badge separado) · **P1 (subido!)** |
| US-SELL-024 | `desconhecido` | Campo "venda agrupada" explícito · **P1 (subido!)** |
| US-SELL-025 | `todo` | Botões agrupamento rápido (1-click) · **P3 confirmado** |
| US-SELL-026 | `todo` | Impressão batch de vendas selecionadas (PDF consolidado) · **P2 (subido)** |
| US-SELL-027 | `todo` | Schema discovery dinâmico Grade Avançada · **P0 (subida v2!)** |
| US-SELL-028 | `todo` | Modules/OficinaAuto — schema com multi-placa (cavalo+reboque) · **P1 (emergente  |
| US-SELL-041 | `todo` | NFC-e "emitir agora" no fim do Create (paridade Bling) |
| US-SELL-042 | `todo` | Batch no handlePriceGroupChange — elimina N+1 em /products/list |
| US-SELL-043 | `todo` | Migrar CSS Cowork (.sells-cowork / vd-*) → tokens DS no Sells/Index |
| US-SELL-045 | `todo` | Bug: payload `totals` morto na rede — backend calcula/envia, frontend nunca lê |
| US-SELL-046 | `todo` | Bug: viewMode `grade-avancada` órfão — middleware roteia 6 clientes legacy pra U |
| US-SELL-047 | `todo` | Teste de isolamento multi-tenant REAL da tela Sells (ADR 0093) — gap mascarado p |
| US-SELL-048 | `todo` | Higiene dos snapshots-grep Sells: DELETE/REWRITE por it() (não quarentena) — gat |
| US-SELL-051 | `todo` | Migrar dados históricos transaction_date (timezone/format) — afeta ROTA LIVRE |
| US-SELL-052 | `todo` | Fechar paridade Sells V2 vs Blade (configure-search · quick-add · preço-diferenc |
| US-SELL-054 | `todo` | Offline-first no PDV — fila IndexedDB + reemissão ao reconectar |
| US-SELL-055 | `todo` | Pix QR no PDV + webhook auto-reconcile ligado à venda/caixa |
| US-SELL-056 | `todo` | Keyboard-first coeso no Create — hotkeys configuráveis + Enter-avança + F-key pa |
| US-SELL-057 | `todo` | Skeleton de carregamento no Create + INP < 200ms |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-11 | Index | 🧪 aguarda veredito da lane |
| UC-S01 | Create | 🧪 aguarda veredito da lane |
| UC-S02 | Create | 🧪 aguarda veredito da lane |
| UC-S10 | Index | 🧪 aguarda veredito da lane |
| UC-S11 | Index | 🧪 aguarda veredito da lane |
| UC-S12 | Index | 🧪 aguarda veredito da lane |
| UC-S1X | Index | 📝 sem_teste |
| UC-SIDX-01 | Index | 🧪 aguarda veredito da lane |
| UC-SIDX-02 | Index | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
