# App Mobile `/m` — mapa de dados por tela (v1 + resumo v2)

> **Para quê:** antes de cada sessão construir uma tela do app das lojas, saber de onde vem cada
> dado e cada ação no ERP — e o que **não existe** e vira decisão do [W].
> **Medido em:** 2026-10-01, sobre `origin/main` em `b0541293d`. É retrato datado: o código anda,
> então **re-meça antes de citar um ❌** (a varredura de cada ausência está na §9).
> **Escopo:** só leitura. Este doc não muda código. Decisão de origem: [W] 2026-10-01 — o app das
> lojas mostra as telas do protótipo Mobile, construídas como páginas Inertia sob `/m`.

**Fontes de design:** `mobile/ref/design-v3/oimpresso-mobile/project/design_handoff_oimpresso_mobile/README.md`
(spec das 15 telas) + `design/screens-*.jsx` + `design/mock-data.jsx` (forma dos dados).
Ponto: `prototipo-ui/cowork/Wagner/ponto-mobile.jsx` + [RUNBOOK-mobile](../Ponto/RUNBOOK-mobile.md);
o encaixe do Ponto no app está pedido ao Design em
`prototipo-ui/cowork/Wagner/cowork-inbox/app-lojas/playbook/01-mobile-com-ponto.md`.

**Legenda:** ✅ existe pronto · 🟡 existe, precisa adaptar (diz o quê) · ❌ não existe no ERP ·
💰 mexe em VALOR · 📦 mexe em ESTOQUE (os dois caem na regra mestre: dupla prova + antes→depois +
aprovação [W] antes de ir para produção).

---

## 0. Três fatos que valem para todas as telas

1. **Não existe nada sob `/m` hoje**, nem `resources/js/Pages/Mobile*`. O único "mobile" no Laravel
   é o Ponto (`/ponto/mobile`, sessão web). A pasta `mobile/` da raiz é um app **Expo** com backend
   próprio (tRPC + Drizzle) que **não lê o ERP** — serve de referência de UX, não de fonte de dado.
2. **Tenant vem da sessão.** Página Inertia com a pilha web (`SetSessionData`) já recebe
   `session('user.business_id')`; nada extra para escopar. Toda query nova segue Tier 0
   (`business_id` + Pest cross-tenant no tenant fictício 98).
3. **Muitos KPIs moram em método privado de controller** (Repair, OficinaAuto). Para servir `/m`
   eles precisam virar Service — senão a tela mobile duplica a query.

---

## 1. Início

| Dado / ação | Fonte no ERP | St | O que falta |
|---|---|---|---|
| Saudação + papel | nome: `session('user.first_name')` (`HomeController::index`); papel sem `#id`: `HandleInertiaRequests::cockpitShellProps` → `shell.cockpit.usuarioCargo` | ✅ | `shell.cockpit` é lazy — a página pede |
| Faturado hoje | `HomeController::index` com `preset=dia` → `TransactionUtil::getSellTotals` (`total_sell_inc_tax`) | ✅ | gate `dashboard.data`; replicar no `/m` |
| Variação vs ontem | `HomeController::index` → `periodTotals`, `deltas.total_sell` | ✅ | delta `null` quando ontem = 0 |
| **Meta do dia** | metas existem em `Modules/Jana/Entities/Meta.php` (`jana_metas`, alvo em `jana_meta_periodos.valor_alvo`); farol/projeção em `ApuracaoService` | 🟡/❌ | período só `mes/trim/ano/custom` — **não há meta diária**. Derivar (mensal ÷ dias, ou projeção) ou criar período de 1 dia → **D1** |
| KPI "OS hoje" / "Em aberto" | depende de qual entidade é "OS" — ver §3. Repair: `DashboardController::buildDashboardKpis` (privado); Oficina: `ServiceOrderController::buildBoardKpis` (privado) | 🟡 | extrair para Service; "OS hoje" (= `created_at` hoje) não é contado em lugar nenhum |
| KPI "Urgentes" | — | ❌ | não há campo de urgência/prioridade em OS nenhuma. O que existe é **atrasada** (prazo vencido) → **D3** |
| KPI "Estoque baixo" | `GradesDoPainelService::pendencias` (aba `estoque`) / `ProductUtil::getProductAlert` (`qty_available <= alert_quantity`) | ✅ | gate `stock_report.view` |
| Cards A receber / A pagar | `Modules/Financeiro/Services/UnificadoService::kpis` (`total_receber`, `total_pagar`, `atrasados_*`) sobre `fin_titulos` | ✅ | Service público, pronto |
| Próximas tarefas (3) | — | ❌ | depende da inbox de Tarefas (§2), que não existe |
| Sino de notificações | tabela `notifications`; `HomeController::getTotalUnreadNotifications` (`/get-total-unread`) | 🟡 | devolve HTML/efeito colateral (marca lida); falta endpoint JSON. Sino não existe no shell Inertia |
| Pílula **empresa ativa** | `SetSessionData` + `users.business_id` (coluna única) | ❌ | **ninguém troca de empresa**: usuário pertence a 1 business; o seletor do sidebar é placeholder (`Sidebar.tsx`, TODO + `alert`). Só superadmin vê várias → **D2** |
| Atalho Novo pedido | `/sells/create?sale_type=sales_order` | 🟡 | tela desktop |
| Atalho Venda rápida | `/pos/create`, `/sells/create-v3` | 🟡 | v2 (§7) |
| Atalho Cobrar PIX | `CobrancaController::store` (`POST /financeiro/cobranca/emitir`, `tipo=pix_*`) | ✅ 💰 | |
| Atalho Conciliar | `ConciliacaoController` (`/financeiro/conciliacao`) | ✅ 💰 | tela desktop |

## 2. Tarefas (inbox unificada)

**Achado central: a inbox nunca foi construída.** A arquitetura `TaskProvider`/`TaskRegistry` →
`/api/tasks/inbox` do handoff foi planejada (ADR 0039 Fase 4) e não existe no código; a lápide
está em [`Tarefas/BRIEFING.md`](../Tarefas/BRIEFING.md) (tarefa de cliente → Essentials ToDo).
O `TaskRegistry` que existe (`Modules/Jana/Services/TaskRegistry/`) é das tasks **do time**
(`mcp_tasks`, sem `business_id`) — **não serve** para cliente.

| Dado / ação | Fonte no ERP | St | O que falta |
|---|---|---|---|
| Agregador (origem, cor, contadores, Hoje/Amanhã/Semana, busca) | — | ❌ | interface + providers + endpoint, tudo novo → **D4** |
| Tarefa manual (FAB) | `Modules/Essentials/Entities/ToDo.php` (`essentials_to_dos`, business-scoped, prioridade inclui `urgent`), `ToDoController` | ✅ | controller devolve Inertia/redirect; falta resposta JSON enxuta |
| Concluir | ToDo: `ToDoController::update` (`only_status`); CRM: `ScheduleController::update`; OS: ação FSM do estágio | 🟡 | não há "concluir" cross-módulo |
| Adiar | — | ❌ | dá para trocar a data (ToDo/Schedule), sem endpoint dedicado |
| Viewer **OS · Aprovar arte** | ação FSM `aprovar_arte`/`rejeitar_arte` no seed de Comunicação Visual; coluna `cv_ordens_producao.arte_url` | 🟡 | seed não roda por nenhum seeder raiz; `OrdemProducao` sem controller/rota/upload; "reprovar" só existe como `cancelar_os` |
| Viewer **FIN · Boleto** (linha digitável, PIX) | `UnificadoController` / `ContaReceberController` + drivers do PaymentGateway | ✅ 💰 | |
| FIN · Prorrogar | `UnificadoController::update` (`vencimento`) | 🟡 💰 | muda só a data local, não altera o boleto no banco emissor |
| FIN · enviar boleto por WhatsApp | — | ❌ | |
| Viewer **FIN · Conciliar PIX sem cliente** | `ConciliacaoController::match` | 🟡 💰 | o match liga extrato a **título**, não a cliente |
| Viewer **CRM · Contato / Orçamento** | `Modules/Crm` `ScheduleController` (follow-ups); orçamento: `/sells/quotations` + FSM `enviar_orcamento` | 🟡 | sem ação "registrar contato" de um toque |
| Viewer **MFG · Liberar produção** | ação FSM `iniciar_producao` (Sells) / `iniciar_impressao` (CV) | 🟡 | Manufacturing só tem `finalize` |
| Viewer **OS · Confirmar entrega** | `entregar_ao_cliente` (Repair), `entregar` (Sells), `entregar_balcao` (CV) | ✅ | uma rota por domínio — o viewer precisa saber qual |
| Viewer **PNT · Justificar** | `POST /ponto/api/intercorrencias` (`MobileMarcacaoController::criarIntercorrencia`) | ✅ | pronto e pensado para mobile |

## 3. Pedidos (lista + detalhe com etapas)

**"Pedido" do protótipo não é uma entidade do ERP — são cinco candidatas:**

| Candidata | Tabela | Valor · Prazo | Etapas (FSM) | Observação |
|---|---|---|---|---|
| Venda (Sells) | `transactions` type=sell | `final_total` · `delivery_date` | ✅ `venda_com_producao` (11 estágios) | lista/detalhe Inertia prontos |
| OS de Repair | `repair_job_sheets` | `estimated_cost` · `delivery_date` | ✅ `os_reparo_padrao` (13) | convive com `status_id` legado |
| Ordem de produção CV | `cv_ordens_producao` | `total` · `prazo_prometido` | 🟡 seed de 16 estágios, **sem endpoint** | é o pipeline mais parecido com o protótipo (tem aprovação de arte) |
| OS legada CV | `comvis_os` | `valor_total` · `data_prazo` | ❌ enum próprio | |
| OS da Oficina | `service_orders` | via transação · `expected_completion` | ✅ `oficina_mecanica_os` | piloto biz=164 |

| Dado / ação | Fonte no ERP | St | O que falta |
|---|---|---|---|
| Lista (id, cliente, valor, prazo, busca) | Sells: `SellController::index` / `inertiaList`; Repair: `JobSheetController` | ✅ | |
| Pill de etapa + barra de progresso | `current_stage` (nome, cor, `sort_order`) | 🟡 | agrupar os estágios reais nas 5 etapas no front |
| Chips Ativos / Concluídos | `is_terminal` | 🟡 | |
| Chip / borda **Urgente** | — | ❌ | → **D3** |
| Scan | — | ❌ | sem leitor por câmera no front |
| Stepper + "próxima: X" | `GET /api/sells/{id}/fsm-actions` (`SaleFsmActionController::actions`, devolve `target_stage` e `can_execute` via `StageActionPolicy`) | 🟡 | mapear 5 etapas ↔ estágios (tabela abaixo) |
| Botão contextual por etapa | `POST /sells/{id}/fsm-action` (`ExecuteStageActionService`); Repair: `/repair/job-sheets/{id}/fsm-action` | ✅ | rótulos vêm do seed, não do protótipo |
| Voltar etapa | só `reabrir_para_revisao` (Sells) e `rejeitar_arte` (CV) | 🟡 | não há "voltar" genérico |
| Pular etapa | — | ❌ | ADR 0143 só transita por ação cadastrada → **D5** |
| Saiu para entrega | — | ❌ | nenhum estágio; só a coluna legada `transactions.shipping_status`, fora da FSM |
| Reabrir pedido | garantia (`registrar_garantia`) | 🟡 | nada sai de `completed` em Sells |
| Cancelar OS | `cancelar_venda` (`CancelarVendaCascade`: NFe, boletos, reserva) / `cancelar_os` | ✅ 💰📦 | regra mestre; ver atenção abaixo |
| Arte | CV: `arte_url` | 🟡 | sem upload, sem UI |
| Atalho WhatsApp / Imprimir | Modules/Whatsapp; impressão da venda | 🟡 | não ligados ao pedido |

**5 etapas do protótipo × estágios reais**

| Protótipo | Sells `venda_com_producao` | Repair `os_reparo_padrao` | CV (seed) |
|---|---|---|---|
| Orçamento | `quote_draft`, `quote_sent` | `recebido_para_diagnostico` … `diagnosticado_aguardando_aprovacao` | `quote_draft`, `quote_sent` |
| Aprovação | `quote_approved` (aprova orçamento, **não arte**) | `orcamento_aprovado` | `quote_approved`, `arte_em_aprovacao`, `arte_aprovada` |
| Produção | `in_production`, `on_hold` | `aguardando_pecas` … `pausado` | `aguardando_maquina` … `acabamento_concluido` |
| Entrega | `ready_for_invoice`, `invoiced`, `paid`, `delivered` (o financeiro fica no meio) | `concluido_aguardando_retirada` | `aguardando_instalacao` … `instalado_aguardando_aprovacao_final` |
| Concluído | `completed` | `entregue_completo` | `entregue_completo` |

**Atenção (achado da varredura, não medido em runtime):** `sale_stage_history` não tem
`subject_type` — o Repair grava o id da OS em `transaction_id` (`RepairFsmActionController`), e o
`CancelarVendaCascade` usa `$subject->getKey()` como `transaction_id`, o que na CV seria o id da
`OrdemProducao`. Se o `/m` executar ações de OS/CV, isso precisa de olho antes.

## 4. Produção (fila por estação)

| Dado / ação | Fonte no ERP | St | O que falta |
|---|---|---|---|
| Estações com carga % | — | ❌ | **não há cadastro de estação/máquina** com fila ou capacidade → **D6** |
| Máquina e setor como texto | `comvis_apontamentos.maquina` (string); `business.repair_settings.slots` (rótulos Plotter, Acabamento, Expedição…) | 🟡 | dá para agregar, sem capacidade |
| Jobs com operador, início/fim | `comvis_apontamentos` (`operador_id`, `iniciado_em`, `finalizado_em`) | 🟡 | |
| Iniciar / Concluir etapa | `ApontamentoController` + `ApontamentoTracker` (CV) | ✅ | |
| Stepper do job (Em fila → Imprimindo → Acabamento → Pronto) | estágios CV `aguardando_maquina` → `em_impressao` → `aguardando_acabamento` → `acabamento_concluido` | 🟡 | sem endpoint |
| Prioridade do job | — | ❌ | → **D3** |
| Enviar para expedição | — | ❌ | |
| Alternativas existentes | Kanban Produção Oficina (`ProducaoOficinaController`, 5 colunas fixas, `move` grava `status_id` legado **fora da FSM**); board da Oficina (`box_label`); Manufacturing (receitas, sem estações) | 🟡 | |

A própria SPEC de CV registra o Kanban PCP como não construído (`ComunicacaoVisual/SPEC.md`).

## 5. Pessoas (papéis múltiplos)

| Dado / ação | Fonte no ERP | St | O que falta |
|---|---|---|---|
| Papéis Cliente / Fornecedor / Funcionário | flags `is_customer`, `is_supplier`, `is_employee` (+ `is_representative`, `is_other`) em `contacts` | ✅ | |
| Papel Transportadora | tabela separada `transportadoras`, usada só na NF-e | ❌ | não é papel de pessoa → **D7** |
| Funcionário ↔ RH/Ponto | `is_employee` é só flag | 🟡 | sem elo com `users` / `ponto_colaborador_config` |
| Filtros por papel + contadores | `ContactController::applyContactTypeFilter`, `buildClienteIndexTabCounts` | ✅ | escopos antigos do model (`scopeOnlyCustomers`…) ainda leem só `type` |
| Filtro "Em débito" | total no servidor (`kpis.com_atraso`); filtro roda no navegador | 🟡 | falta filtro no servidor |
| Saldo / valor em aberto na lista | `buildClienteIndexCustomers` (`valor_aberto`, `saldo_devedor`, `os_abertas`) | ✅ | |
| PF/PJ, doc, limite, bloqueado, situação | colunas em `contacts` (`tipo`, `credit_limit`, `bloqueado`, `situacao`) | ✅ | |
| KPIs Pedidos / Ticket médio | `ClienteIaController::calcularStatsCliente` | ❌ bug | usa `total_paid`, coluna ausente no schema (`database/schema/mysql-schema.sql`); o `catch` zera tudo → ticket médio sai 0. Ver **D8** |
| Pedidos recentes | `ContactController::salesJson` (`/cliente/{id}/sales-json`) | ✅ | |
| Novo pedido para X | `SellPosController` lê `contact_id` da URL | 🟡 | |
| Ficha + edição por seção | `ClienteAutosaveController` (identificação, contato, endereço, comercial, classificação, papéis) | ✅ | |
| Vendedor | `contacts.sales_rep_contact_id` + relação no model | 🟡 | sem UI/endpoint |
| LGPD consentimentos | `whatsapp_consent`, `email_consent`; `Contact::canReceive*` | 🟡 | só leitura; nada grava |
| Busca CNPJ / CEP | `Modules/Crm/Services/BrLookupService` (BrasilAPI / ViaCEP), `/cliente/lookup/*` | ✅ | |
| Detalhe de fornecedor puro | `/cliente/{id}` aceita só `type` customer/both | 🟡 | fornecedor puro dá 404 |
| **Permissão `view_own` na lista** | `buildClienteIndexCustomers` | ❌ achado | conferido por leitura: a lista não aplica `created_by`/`contactAccess` (o detalhe aplica, `ContactController::show`). Quem só tem `view_own` vê todas as pessoas **da própria empresa** (não cruza tenant) → **D8** |

## 6. Ponto (colaborador)

O lado mais pronto do app.

| Dado / ação | Fonte no ERP | St | O que falta |
|---|---|---|---|
| Bater ponto (relógio, GPS, 4 tipos, recibo NSR + hash) | `MobileMarcacaoController::tela` → `Pages/Ponto/Mobile/Index.tsx`; `MobileMarcacaoService`; NSR/hash encadeado em `MarcacaoService::registrarInterno` | ✅ | reestilizar no padrão Mobile (pedido ao Design) |
| Anti-fraude | GPS > 500 m recusa; relógio > 30 s recusa; fora do geofence grava e marca revisão | ✅ | geofence só por `config`, sem tela |
| Meu espelho | `_components/MeuEspelho.tsx`, reaproveita `EspelhoController` (deferred) | ✅ web / 🟡 API | API Passport não tem endpoint de espelho |
| Justificar | `_components/Justificar.tsx` → `criarIntercorrencia` (`StoreIntercorrenciaRequest`) | ✅ | |
| Usuário → colaborador | `ponto_colaborador_config` (`business_id`, `user_id`, `controla_ponto=1`) | ✅ | sem cadastro: API 403 `sem_colaborador`, tela mostra vazio |
| Acesso | `/ponto/mobile` fica **fora** do `ponto.access` (colaborador entra sem a permissão do módulo) | ✅ | |
| Fila do gestor | `AprovacaoController::validarMobile/recusarMobile` | ✅ | fica **fora** do app nesta versão (playbook 01) |

Regras que não mudam: sem biometria/câmera (ADR 0383); marcação imutável (Portaria 671/2021).

## 7. Mais (hub) + resumo v2

**Mais**

| Item | Rota existente | St |
|---|---|---|
| Produtos | `/products`, `/products/unificado` | ✅ (desktop) |
| Pessoas / Fornecedores | `/contacts?type=…` (`Cliente/Index`) | ✅ |
| Finanças | `/financeiro/unificado` | ✅ |
| Relatórios | `/reports/*` soltos, `/financeiro/relatorios` | 🟡 sem hub |
| Equipe | `/users`, `/roles` | 🟡 Blade |
| Cobrar PIX | `/financeiro/cobranca` | ✅ 💰 |
| Imprimir etiqueta | `/labels/show`, `/barcodes` | 🟡 Blade |
| Calculadora gráfica | `/comunicacao-visual` + `POST …/api/calcular` | ✅ (gate `comvis.*`) |
| Perfil | `/perfil` (`User/Perfil`) | ✅ |
| Empresa ativa | — | ❌ troca (→ **D2**) |
| Notificações (tela) | só dropdown Blade | ❌ |
| Versão do app | `config/author.php` (versão UltimatePOS) | 🟡 não exposta |

**v2 (resumo)**

| Tela | Base no ERP | St | Faltas | Risco |
|---|---|---|---|---|
| Produtos | `ProdutoUnificadoController`, `Pages/Produto/{Show,StockHistory,Create,Edit}`, `ProductUtil` | 🟡 | scan por câmera; tipo Serviço/Insumo; NCM/CFOP no produto | 💰 preço/custo · 📦 estoque inicial |
| Venda rápida | `SellPosController` / `Sells/Create`, `TransactionUtil::createSellTransaction`, `createOrUpdatePaymentLines`, recibo `receiptContent` | 🟡 | scan; PIX ligado ao checkout; recibo por WhatsApp | 💰📦 finalizar venda é o ponto mais crítico |
| Finanças | `UnificadoService::kpis`, `ContaBancaria`, `ExtratoController`, `CaixaController`, `TituloAnexo` | ✅ | tela mobile | 💰 baixa de título |

---

## 8. Decisões para o [W]

| # | Pergunta | Por quê | Recomendação desta sessão |
|---|---|---|---|
| **D1** | Meta do dia: derivar da meta mensal da Jana, criar período diário, ou tirar o card da v1? | meta diária não existe | derivar (mensal ÷ dias úteis), rotulado como derivado |
| **D2** | Empresa ativa: tirar a pílula/seletor da v1, ou construir vínculo usuário↔várias empresas? | hoje 1 usuário = 1 business; construir mexe no núcleo multi-tenant (Tier 0, ADR mãe) | tirar da v1; mostrar só o nome da empresa |
| **D3** | "Urgente"/prioridade: tratar como **atrasado** (prazo vencido) ou criar campo de prioridade? | não há campo em OS/pedido/job | atrasado na v1 |
| **D4** | Tarefas: construir o agregador (providers por módulo) agora, ou a aba v1 mostrar só ToDo + justificativas do Ponto? | inbox nunca existiu; há lápide | v1 mínima (ToDo + Ponto), agregador depois |
| **D5** | **Qual entidade é "Pedido"** — Venda, OS de Repair, ou ordem de CV? E "Pular etapa" fica fora? | 5 candidatas; protótipo é de gráfica (arte), o pipeline com arte é o CV, que não tem endpoint | a decidir pelo [W] (muda o escopo da tela inteira) |
| **D6** | Produção: construir cadastro de estações com carga, adaptar a tela para o Kanban existente, ou tirar da v1? | estação com carga % não existe | tirar da v1 ou adaptar ao Kanban |
| **D7** | Transportadora como papel de pessoa: criar flag, ou tirar o chip? | hoje é tabela separada da NF-e | tirar o chip na v1 |
| **D8** | Dois achados fora do escopo do app, mas na rota dele: `view_own` ignorado na lista de pessoas e ticket médio sempre 0 | ambos aparecem assim que Pessoas for para o celular | corrigir antes da tela Pessoas (PRs próprios) |

### 8.1 Respostas do [W] — 2026-10-01

Dadas no chat da sessão de coordenação "Publicar protótipo mobile nas lojas", uma pergunta por
decisão, com as opções lado a lado. Onde a resposta difere da recomendação acima, vale a resposta.

> ⚠️ **Mudou o ONDE, não o QUÊ (mesmo dia):** o cabeçalho deste mapa diz que as telas seriam
> páginas Inertia sob `/m`. Isso foi **revertido** pelo [W] depois de ver `/m` no emulador ("não
> gostei dele dentro do sistema" → telas próprias no app; #8472). Vale agora: telas empacotadas no
> repo `oimpresso-app`, falando com o ERP por API Passport por tela. As fontes de dado deste mapa
> e as decisões abaixo **continuam valendo** — mudam só a camada que as entrega (endpoint JSON por
> tela em vez de props Inertia). Registro único: `docs/lojas-app/DECISOES.md` (D5, D11).

| # | Decisão [W] |
|---|---|
| **D1** | Meta do dia = meta **mensal da Jana ÷ dias úteis**, rotulada como derivada. |
| **D2** | **Sem seletor de empresa** na v1; mostra só o nome da empresa. |
| **D3** | **Urgente = atrasado** (prazo vencido). Nenhum campo de prioridade novo. |
| **D4** | Tarefas na v1 = **ToDo + justificativas do Ponto pendentes**. O agregador fica para depois. |
| **D5** | **Pedido = VENDA do ERP** (`Transaction` + pipeline FSM de vendas, ADR 0143). OS de Repair e ordem de CV ficam fora da aba Pedidos. |
| **D6** | Produção = **adaptar ao Kanban existente**, fila por etapa, **sem carga %** por estação. |
| **D7** | **Sem o chip Transportadora** em Pessoas na v1. |
| **D8** | Os dois defeitos viram sessão própria ("Corrigir 2 bugs da lista de Pessoas antes do app"), antes da tela Pessoas. |

---

## 9. Varreduras que sustentam os ❌ (repo inteiro, sem `vendor`/`node_modules`)

- `/m`: `prefix('m')`, `'/m/'`, `Pages/Mobile` → só o Ponto.
- Meta diária: `meta_diaria|meta diária|daily_goal|daily_target|meta_do_dia|alvo_diario` → 0; períodos de `jana_meta_periodos` = `mes|trim|ano|custom`.
- Troca de empresa: `switch.?business|trocar.?(empresa|business)|empresa.?ativa|switchBusiness` → só o TODO do `Sidebar.tsx`; nenhuma tabela pivô `business_user*`; `User` sem `belongsToMany` de Business.
- Urgência: `urgen|prioridade|priority` nas migrations de ComVis, OficinaAuto e Repair → 0.
- Inbox: `TaskProvider|viewerComponent|tasks/inbox|Components/Viewers` no código → 0.
- Adiar: `snooze|adiar|postpone` funcional → 0.
- Aprovar arte fora da FSM: `aprovar.?arte|art_approval|artwork|pedir.?ajuste` → só model/migration/seed CV.
- Pular/saiu para entrega: `pular_etapa|skip_stage|voltar_etapa|saiu_para_entrega|out_for_delivery` → 0.
- Estações: `Schema::create` com `estacao|station|workcenter|maquina|machine|centro_trabalho` → 0.
- Transportadora como papel: `is_carrier`, flag em `contacts` → 0.
- `view_own` na lista: `created_by|contactAccess|_own` dentro de `buildClienteIndexCustomers` → 0.
- `total_paid` em `transactions`: ausente em `database/schema/mysql-schema.sql`.

## 10. Para a conta demo (business 235)

O que cada tela lê e o mínimo para não ficar vazia — **vale só depois das decisões D2–D6**; o que
depende de decisão está marcado.

| Tela | Lê | Mínimo para "não vazia" | Permissão exigida |
|---|---|---|---|
| Início | `transactions` type=sell status=final de hoje e ontem; `fin_titulos` aberto/parcial (receber e pagar); produtos com `qty_available <= alert_quantity` | ≥2 vendas hoje + ≥1 ontem; ≥2 títulos a receber e ≥1 a pagar, um vencido; ≥2 produtos abaixo do mínimo | `dashboard.data`, `stock_report.view`, acesso Financeiro |
| Tarefas | `essentials_to_dos` + intercorrências do Ponto (se D4 = v1 mínima) | ≥3 ToDo (um `urgent`, prazos hoje/amanhã/semana); ≥1 intercorrência pendente | Essentials |
| Pedidos | depende de **D5**; se Venda: `transactions` com `process_id`/`current_stage_id` do `venda_com_producao` | ≥1 venda por etapa (orçamento, aprovado, produção, entrega, concluída) | `sell.view` / FSM roles |
| Produção | depende de **D6** | — | — |
| Pessoas | `contacts` com flags `is_customer/is_supplier/is_employee` | ≥6 pessoas, ≥1 com 2 papéis, ≥1 PF e ≥1 PJ, ≥1 com valor em aberto | `customer.view`, `supplier.view` |
| Ponto | `ponto_colaborador_config` do usuário + marcações | colaborador com `controla_ponto=1`; marcações de alguns dias; 1 intercorrência | nenhuma (fora do `ponto.access`) |
| Mais | — | — | — |

O colaborador (`revisor.ponto`) precisa ver pelo menos **Ponto**; o gestor (`gestor.demo`) vê as
demais conforme as permissões acima.

