# Como sistemas de comunicação visual / sign shops dividem UMA venda entre pessoas e departamentos: papéis, filas, travas, permissões e comissão

> Pesquisa feita em 20/10/2026. Todas as URLs citadas foram abertas nesta sessão. "Inferência" marca conclusão minha, não afirmada pela fonte.
>
> **Ponto de partida.** Estas notas complementam `Nova venda casos de uso concorrentes\br_comunicacao_visual.md` e `internacional_sign_shop.md`. Não repito o que aquelas notas já provam: existência de link de aprovação, sinal de 50%, NF-e + NFS-e a partir do pedido, aprovação de arte, kanban de etapas, app de instalação etc. Quando cito um desses fatos aqui, é só para mostrar **quem faz, quando e com qual trava**.
>
> **Método e datas:**
> - **Mubisys.** A Central de Ajuda (ajuda.mubisys.com) é uma SPA. Li os dados públicos que a própria página carrega: menu de 47 itens, com última atualização em 03/12/2025, e 5 artigos publicados, de dez/2025 a jan/2026. Os artigos de "Faturamento", "O.S.s com restrição" e "Permissão de acesso" **não têm conteúdo publicado**; só o item de menu existe.
> - **Holdprint.** O site é uma SPA Angular. As frases citadas são as strings pt-BR do pacote JS que a home renderiza.
> - **shopVOX.** A maioria dos artigos de ajuda mostra só "Updated 2 years ago" (≈2024), por isso são **possivelmente desatualizados**. Os itens "What's New" têm data (2024–2025).
> - **Sign OS.** Produto australiano lançado em 2026 e mantido por um empreendedor individual. A documentação é a mais detalhada de todas, mas a base instalada é pequena.
> - **Signs101.** Threads de 2015 (**desatualizado**), 2022 e jul/2026.
> - **Nortgraf.** Artigos com "Última atualização" entre 15 e 20/08/2026.
>
> **Profundidade da evidência por sistema:**
> - **Forte** (ajuda/manual com o fluxo): Sign OS, Nortgraf, shopVOX.
> - **Média** (página de produto detalhada): GestãoClick, VisualCalc, Calcgraf, CoreBridge, Ordant.
> - **Fraca** (menu/marketing): Mubisys, Ajors SIGN, Everflow, Holdprint, Zênite.

## 1. Quem cria o pedido e quem aprova o quê (orçamento, desconto, crédito, arte, liberação para produção)

### Takeaway
Quem cria é sempre o comercial (vendedor, atendente, CSR, estimator). Quem aprova o orçamento é o cliente, por link. As aprovações **internas** variam muito:
- **Sign OS** é o único que documenta uma caixa de aprovações gerencial configurável por ação: envio de orçamento, emissão de fatura, envio de PO, variação de escopo, override de bloqueio de crédito.
- **Calcgraf** descreve vendedores e atendentes "supervisionados" por orçamentistas e gestores, com restrição automática de crédito e liberação/recusa de crédito no pedido.
- **shopVOX** restringe por papel quem vê ou edita desconto e custo na linha.

A liberação para produção segue dois modelos:
- **Ato explícito de alguém:** Release no Sign OS, Create Job no shopVOX, "envio para produção" na Nortgraf.
- **Automática na aprovação do orçamento:** VisualCalc, Everflow, Ajors, Ordant e GestãoClick (via "Gerar OS").

Nenhuma fonte documenta alçada de desconto por percentual ou valor.

### Cited Findings

**Sign OS (AU, 2026)**
- Papéis prontos e o que cada um faz: "Admin (shop owner) sees everything; Sales and Estimator quote; Project manager runs jobs; Production manager and Floor staff see the work without prices; Accounts sees money; Install contractor and Customer are portal-only." — [Sign OS Help: Roles & permissions](https://signos.com.au/help/your-team/roles-permissions)
- Caixa de aprovações interna:
  - "sending a quote, issuing an invoice, sending a purchase order, or a job variation waits here for a manager to approve before it goes ahead".
  - Só aparece se a loja ligar a exigência por ação.
  - O gerente abre o registro e clica Approve ou Reject. — [Sign OS Help: Approvals](https://signos.com.au/help/customers-store/approvals)
- Configuração das aprovações:
  - "Quote send requires approval · Invoice issue requires approval". O item "waits in Approvals until someone with the matching approve permission signs it off".
  - Quem aprova variação é configurável por papel ("Admin is always ticked").
  - Qualquer pessoa com a permissão "Approve job variations" também aprova. — [Sign OS Help: Quotes & invoices settings](https://signos.com.au/help/your-shop/quotes-invoices)
- Crédito:
  - "A hold stops new quotes going out and stops jobs being released for that customer. A credit limit does the same when new work would take them past what you're willing to carry."
  - Para cada caso a loja escolhe "Do nothing, warn, or block".
  - "Someone with the Override credit holds permission can send it anyway — they have to give a reason, and it's recorded against the account." — [Sign OS Help: Customer on hold / credit limit](https://signos.com.au/help/exceptions/customer-on-hold)
- Variação de escopo depois do aceite:
  - É "recorded on the job, not by editing the quote, so the customer's accepted price is never rewritten".
  - "Only approved variations move the Revised target and bill on the next invoice". — [Sign OS Help: Job variations](https://signos.com.au/help/jobs/job-variations)
- Aceite → job:
  - O botão "Accept and make job" exige as datas "Client needs it by" e "Finish production by" e a forma de entrega (fulfilment).
  - "Everything carries over — the lines, the pricing, the customer, the dates and fulfilment — so nothing is re-typed".
  - Na aprovação online, se faltar dado, "you are notified to finish the missing details and accept it". — [Sign OS Help: Quote to job](https://signos.com.au/help/quoting/quote-to-job)
- Liberação para produção é um passo separado do aceite:
  - "An accepted job is not on the floor until it is released. Release is a short wizard — due date, steps, confirm".
  - Na liberação, o sistema reserva os materiais em estoque e agenda o job de trás para frente a partir da data de entrega.
  - "Jobs waiting are listed under Ready to release". — [Sign OS Help: Release to production](https://signos.com.au/help/production/release-to-production)
  - Changelog: "a queued job only reaches production through Release". — [Sign OS Changelog](https://signos.com.au/changelog)

**Calcgraf**
- "o módulo permite elaborar orçamentos de impressos gráficos a partir da operação de atendentes e vendedores, com a supervisão de orçamentistas".
- "Descentralização da operação comercial permitindo que atendentes, representantes e vendedores possam entrar com os pedidos de orçamento com supervisão segura de orçamentistas e gestores".
- "Recursos de segurança ampliados para garantir a operação de usuários de diferentes níveis de responsabilidade".
- "Restrições automáticas de crédito".
- "Controles especiais para ordem de produção reserva, cortesia e reimpressão".
- O orçamento "Permite que o orçamento seja incluído por atendentes e vendedores sem experiência com orçamentação, por solicitar somente características do produto". — [Calcgraf Orçamento](https://www.calcgraf.com.br/solucao/orcamento/)
- No pedido: "Acompanhamento de crédito: liberação e recusa". — [Calcgraf Pedido](https://www.calcgraf.com.br/solucao/pedido/)

**shopVOX (ajuda; datas indicadas)**
- Papéis padrão:
  - CSR, Sales, Designer;
  - Production ("don't need to access anything but Jobs");
  - Super User ("full and complete access").
- Cada usuário deve ter um só papel. Papéis customizados podem ser criados (ex.: "Tom - Operations Manager"). [≈2022–2024] — [shopVOX: Setting Roles](https://shopvox-pro.helpdocs.io/article/kxjbfjtst4-setting-roles-for-your-team)
- Desconto: um flag no papel define "View or Edit the Discounts on the Line Item". [09/09/2024] — [shopVOX: Restrict discounts](https://shopvox-pro.helpdocs.io/article/w4rbdoi2y8-mon-09-09-2024-restrict-users-to-add-change-the-discounts-on-the-line-item-of-the-transactions-pro)
- Custo da linha: "Editable" ou só "Visible" por papel, para "prevent a User from changing any vital information when pricing a Product". [28/10/2024] — [shopVOX: New Permission setting](https://shopvox-pro.helpdocs.io/article/n0e3j5sveu-mon-10-28-2024-new-permission-setting-pro); [shopVOX: Role-based unit cost](https://shopvox-pro.helpdocs.io/article/62bwr9pryt-sun-10-27-2024-role-based-permissions-for-editing-or-overriding-line-item-costs-unit-cost-pro)
- O índice traz "Disabled Adding custom line item to Non Admin Users" [13/05/2024] (só o título), o que impede item avulso fora do catálogo por quem não é admin. — [shopVOX Help Center](https://shopvox-pro.helpdocs.io/)
- Liberação para produção: "create a job" a partir do Sales Order "basically means you are ready to push it into production". Os jobs podem ir separados ou em lote. [≈2024] — [shopVOX: Where do jobs fit](https://shopvox-pro.helpdocs.io/article/yunn3w6v2p-where-do-jobs-fit-in-the-order-process-in-shop-vox)
- Revisão da prova antes do cliente: "shopVOX recommends designating this crucial responsibility to either the Project Manager or the Sales Representative". O PM é o "final checkpoint". [≈2024] — [shopVOX: Project Managers in proofing](https://shopvox-pro.helpdocs.io/article/j8194abdac-empowering-project-managers-in-the-proofing-process-a-guide-to-workflow-enhancement-with-shop-vox)

**Nortgraf**
- O pedido nasce de "balcão, telefone ou orçamentos". O artigo não cita campo de vendedor. — [Nortgraf: Criando Pedidos](https://nortgraf.com/ajuda/artigo/criando-pedidos-no-painel-de-controle)
- Envio para produção é ação manual:
  - "A ordem pode ser criada a partir de um pedido". O usuário confere "itens, arquivos, pagamento e prazo" e usa "envio para produção".
  - O pagamento aparece só como boa prática: "Confira o status do pagamento antes de iniciar trabalhos que exigem aprovação financeira". — [Nortgraf: Produção — Ordens, filas e Kanban](https://nortgraf.com/ajuda/artigo/producao-ordens-filas-e-kanban)
- Pagamento manual (sinal por transferência/comprovante):
  - "Pagamentos manuais exigem conferência do operador".
  - "pedidos com pagamento aguardando aprovação podem ficar travados até que o operador aprove ou reprove o recebimento".
  - "ao aprovar o pagamento, o pedido pode avançar automaticamente para o próximo status configurado".
  - Gateways (PIX, boleto, cartão) aprovam por webhook. — [Nortgraf: Tela de Pagamentos](https://nortgraf.com/ajuda/artigo/tela-de-pagamentos-aprove-e-gerencie-recebimentos)

**Ordant**
- "Enable customers or authorized CSRs to create estimates".
- "Request deposits before starting a big project. Set estimate expiration dates. Set limits for online orders based on customer's credit history. Set up approval processes for each client."
- "Set sales team action items." — [Ordant Estimating](https://www.ordant.com/module/best-print-estimating-software/)
- Aprovação do cliente por aceite de termos; em seguida "Ordant automatically moves each line item into production, the production staff are notified". — [Ordant Order Approval](https://www.ordant.com/module/print-order-approval-automation/)

**CoreBridge**
- A prova pode ser postada ao cliente ou aprovada internamente: "Post a proof to the customer or approve internally from the line item". — [CoreBridge Estimating Intelligence](https://www.corebridge.net/estimating-intelligence)
- A matriz de planos lista "Online Approvals", "Approvals and Proofs" (1 prova no Starter, 2 no Small Business), "Custom Workflow Statuses/Substatuses", "Employee Roles: Customizable" e "Custom User Access Rights". As marcas de plano por recurso são imagens e não deu para ler qual plano tem cada um. — [CoreBridge Pricing](https://www.corebridge.net/pricing)

**GestãoClick (CV)**
- A situação "Aguardando aprovação" envia a proposta e gera a assinatura. "Aprovado, o orçamento vira ordem de serviço, venda ou contrato sem redigitar nada."
- "Cada fachada, frota adesivada ou lote de banners é uma OS, com data de saída, responsável e prioridade." — [GestãoClick CV](https://gestaoclick.com.br/segmentos/programa-para-empresa-de-comunicacao-visual/)

**VisualCalc**
- "Quando um orçamento é aprovado, o pedido entra automaticamente na fila do Kanban."
- "quando o orçamento é aprovado, a produção, o financeiro e o cliente já sabem." — [VisualCalc](https://www.visualcalc.com.br/)

**Everflow / Ajors SIGN / Holdprint / Mubisys**
- Everflow: "Orçamento aprovado gera OS automaticamente, com etapas específicas por tipo de trabalho"; "A requisição de compra nasce vinculada ao projeto, com aprovação e prazo". — [Everflow CV](https://everflow.com.br/segmentos/software-para-empresa-de-comunicacao-visual-erp/)
- Ajors SIGN: "Ordem de serviço gerada do orçamento aprovado"; "Aceite do cliente registrado". — [Ajors SIGN](https://ajors.com.br/ajors-sign)
- Holdprint: compara custos "o que foi orçado, o que foi realmente aprovado e revisado pelo vendedor, ... o que foi planejado para produção, e por fim, o que foi produzido de fato". Isso sugere uma etapa de revisão do vendedor entre a aprovação e o PCP. — [Holdprint](https://www.holdprint.net/)
- Mubisys: o designer compartilha o layout pelo sistema e o cliente "aprove ou solicite ajustes com apenas um clique". — [Mubisys Comercial](https://www.mubisys.com/comercial)
  - O menu de ajuda põe "Orçamentos" e "Ordens de serviços" sob **Comercial** e "O.S.s com restrição" e "Faturamento" sob **Financeiro**. — [Central de Ajuda Mubisys](https://ajuda.mubisys.com/)

### Inferences
- O padrão de mercado separa **três atos** que a "Nova venda" não deveria fundir:
  1. aceite do cliente (orçamento → pedido/OS);
  2. liberação para produção (dados técnicos completos, arte, sinal e crédito ok);
  3. liberação financeira (crédito).

  Sign OS é o caso mais limpo: aceite gera o job, o job fica em "Ready to release" e só entra no chão via Release, e o bloqueio de crédito barra tanto o envio de orçamento quanto a liberação. Os BR de CV mais simples (VisualCalc, GestãoClick, Everflow) fundem 1 e 2. Isso é rápido, mas empurra a checagem de sinal e arte para "boa prática" (é o caso da Nortgraf).
- A "Mubisys O.S.s com restrição" sob o menu Financeiro sugere uma fila do financeiro com OS bloqueadas por pendência (provavelmente crédito ou pagamento). É inferência pelo nome; o conteúdo não foi publicado.
- "Approve with changes" do cliente não deve liberar produção automaticamente. O Sign OS mudou isso de propósito (ver seção 3). É um detalhe de produto relevante quando o vendedor e a arte são pessoas diferentes.

### Gaps
- Nenhuma fonte documenta alçada de desconto com teto por vendedor (% ou R$). O shopVOX só tem ver/editar desconto por papel; o Sign OS tem aprovação do envio do orçamento, mas não por faixa de desconto.
- Não achei como Mubisys, Holdprint e Zênite tratam aprovação interna de orçamento ou desconto: não há ajuda pública com conteúdo.
- "Credit hold" ou status de crédito no shopVOX não aparece nos estados do Sales Order (ver seção 3).

## 2. Como o pedido chega a cada departamento (OS automática, filas/kanban por setor, notificações, atribuição, o que cada papel vê)

### Takeaway
O pedido vira **jobs ou OS por item** que atravessam um fluxo de etapas por departamento. Cada departamento tem uma **fila ou board próprio** (floor board por departamento, job board, kanban de situações, PCP por setor) e cada papel tem **sua tela inicial**. A atribuição a pessoas acontece em dois modelos:
- **Pull:** quem aperta "Start" assume a etapa.
- **Push:** atribuir a etapa a um nome, com notificação.

As notificações internas são por evento e por papel (proof aprovada, etapa concluída, margem baixa). Produção e instalador veem a OS **sem preço** (Sign OS explícito; shopVOX via papel "Production"/Mobile User; Nortgraf "OS para produção"). Os BR documentam menos a visão por papel e mais o calendário e o kanban por setor.

### Cited Findings

**Sign OS**
- Telas por papel:
  - "Owners see pipeline, at-risk jobs and follow-ups. Sales sees quotes waiting. Production sees the floor. Every role opens to exactly what matters to them."
  - "Role dashboards — owner, sales, projects, production, accounts".
  - "production reads the same numbers sales typed" (especificação em campos estruturados, não texto livre). — [Sign OS](https://signos.com.au/)
- Floor boards:
  - "Department tiles — Ready · In progress · Done today"; "Big buttons, live queue, no prices"; colunas "Backlog, Ready, In progress, Done".
  - Atribuição opcional: "Assign steps to people" (Settings → Departments). "A card not yet started says who it was handed to, and Mine shows just your own. Whoever presses Start takes the step"; a pessoa atribuída "get[s] a notice in Sign OS". — [Sign OS Help: Floor boards](https://signos.com.au/help/production/floor-boards)
  - Changelog: "staff can ... be handed a step by name"; "a quote can be marked urgent to jump the production queue"; "proof approvals and dispatch alerts now name the job and the company and reach the whole team". — [Sign OS Changelog](https://signos.com.au/changelog)
- Notificações:
  - "Which alerts you get depends on your role and on the rules under Watchdog and Follow-up automations".
  - Exemplos: cliente aprovou prova, licença vencendo, "a watchdog rule found a low-margin quote". — [Sign OS Help: Notifications](https://signos.com.au/help/getting-started/notifications)
  - Alertas de prova vão por padrão a "admins, project managers, production managers and sales until you change it". — [Sign OS Help: Artwork approvals](https://signos.com.au/help/jobs/artwork-approvals)
- Abas do job por departamento:
  - "Purchasing is what it needs bought or sent out. Dispatch is how it leaves the building. Accounts is the money".
  - Dispatch: "Book install date, confirm site access, permits — ticked as done". O job vai a "Dispatched" quando a guia (docket) é marcada como saída no Outbound. — [Sign OS Help: Job commercial](https://signos.com.au/help/jobs/job-commercial)
- Instalador terceirizado:
  - O portal mostra "only the jobs assigned to them, with site address and install notes"; fotos de vistoria e de conclusão voltam à loja.
  - "a contractor login can never reach the staff app or your pricing". — [Sign OS Help: Installer portal](https://signos.com.au/help/purchasing/installer-portal)
  - Changelog: "an installer cannot be put on a job without valid insurance". — [Sign OS Changelog](https://signos.com.au/changelog)

**shopVOX**
- Tipos de usuário:
  - "Full Users" (donos, administrativo, vendedores, gerentes);
  - "Mobile Users" para "key production employees", que acessam jobs e tarefas atribuídas pelo app shopVOX Go;
  - "Schedule Users" sem login, gratuitos no Pro, que "can be assigned to workflow steps". [≈2025] — [shopVOX: User Types](https://shopvox-pro.helpdocs.io/article/iuh6za3s5o-shop-vox-user-types)
- Workflow templates:
  - Estágios Pre-Production, Production e Post-Production; template ligado ao produto ou escolhido em "Create Job".
  - Cada passo tem nota, prazo, usuário(s) e máquina atribuídos.
  - "Each person interacting with the Job Starts, Stops and Completes the Steps required". O próximo passo fica azul quando o anterior termina.
  - Exemplo: pré-impressão/aprovação → impressão, laminação, refile, QC, embalagem → expedição e faturamento. — [shopVOX: Workflow Templates](https://shopvox-pro.helpdocs.io/article/0vnwpogzpx-workflow-templates-explained)
- Notificações:
  - Por evento (Transactions, Quotes, Sales Orders, Invoices, POs, Payments, Credit Memos, Jobs, Tasks), com "Notify", e-mail ou in-app. O exemplo de destinatário é o Sales Rep.
  - "Currently, we don't have a way to automatically update your clients on job status via email". [≈2024] — [shopVOX: Notifications](https://shopvox-pro.helpdocs.io/article/cad5vf8eed-notifications-keep-your-entire-team-up-to-date-replacement)
  - O índice mostra os eventos "Job step Completed" [24/01/2025] e "In-app Alert for Proof Uploaded" [15/05/2024] (títulos). — [shopVOX Help Center](https://shopvox-pro.helpdocs.io/)
- shopVOX Go:
  - Dashboard com os jobs atribuídos ao usuário; Play/Stop/Complete por passo; QR code no PDF do job abre o job.
  - Fotos anexadas à transação; assinatura do cliente no aparelho, com localização. [≈2024] — [shopVOX Go! App](https://shopvox-pro.helpdocs.io/article/q43khmwvlw-shop-vox-go-app)
- O job board tem visões Lista, Card e Calendário, e cada visão tem URL própria (bookmark por setor). [17/05/2024] — [shopVOX: Job Board Overview](https://shopvox-pro.helpdocs.io/article/gb1ufg0n9x-fri-05-17-2024-job-board-overview-and-best-practices-pro-express)

**CoreBridge**
- Card do board:
  - "Click a job on the board and get status, follow-up dates, roles, and sales details".
  - Campos configuráveis: "Toggle fields like customer, salesperson, and payment terms".
  - Boards por item com "Columns for in production, pending, and quality control".
  - "Automatic movement and notifications as work progresses"; "Schedule installs, assign crews, and monitor field work"; "Boost accountability with clear job ownership". — [CoreBridge Production Management](https://www.corebridge.net/production-management)
- "Design, fabrication, paint, assembly, and install each have their own handoffs. CoreBridge puts your entire pipeline on screen so PMs, fabricators, and installers stay aligned"; "Role-based access for owners, PMs, and installers". — [CoreBridge Sign](https://www.corebridge.net/sign)
- "gives sales and production a shared customer record so reorders are faster and handoffs stay accurate"; "Assign tasks, update statuses". — [CoreBridge Feature overview](https://www.corebridge.net/feature-overview)
- Licença separa "Standard" de "Production Users": Starter "1 Standard & 1 Production User"; Small Business "5 Standard & 5 Production Users". — [CoreBridge Pricing](https://www.corebridge.net/pricing)

**Ordant**
- Automação se/então: "if the proof is approved, then send the file to the production queue for scheduling". Notificações "to automatically remind humans when they need to take actions, such as review proofs". — [Ordant Workflow Automation](https://www.ordant.com/module/workflow-automation-software/)
- "As each step in the prepress, printing or finishing processes of each job is completed, the job moves to the next step. Workstations show employees which jobs to start working on and which to expect soon." — [Ordant Shop Floor Data Collection](https://www.ordant.com/module/shop-floor-data-collection/)
- Expedição: "Shipping department can get boxes ready to load before jobs are completed". — [Ordant Batch Invoicing](https://www.ordant.com/module/print-estimating-using-batch-invoicing/)

**Nortgraf**
- Status de pedido e status de pagamento são tipos distintos. O artigo alerta contra usar status financeiro para produção, e vice-versa.
- Automações por status:
  - etapa de produção ligada a status;
  - papéis de entrega ("entrada na fila, despacho de rota, concluído, problema");
  - baixa de estoque;
  - e-mail/WhatsApp ao cliente;
  - histórico com data. — [Nortgraf: Status de pedidos](https://nortgraf.com/ajuda/artigo/status-de-pedidos-configuracao-e-automacoes)
- Produção:
  - "Cada item pode ser acompanhado separadamente quando o pedido contém trabalhos com fluxos diferentes".
  - Mover o card exige preencher os campos da etapa e pode atualizar item e pedido.
  - Filtro por "responsável".
  - Ao concluir a última etapa, o pedido pode seguir para solicitações de entrega. — [Nortgraf: Produção](https://nortgraf.com/ajuda/artigo/producao-ordens-filas-e-kanban)
- Documento por destino: "Use recibo para confirmação comercial, OS para produção, etiqueta para identificação, romaneio para expedição". O artigo não diz se a OS omite preço. — [Nortgraf: Pedidos — documentos](https://nortgraf.com/ajuda/artigo/pedidos-historico-documentos-e-impressao)
- Central de Notificações: só in-app (sino), "para o usuário atual". Não lista tipos nem regras por perfil. — [Nortgraf: Central de Notificações](https://nortgraf.com/ajuda/artigo/central-de-notificacoes-alertas-e-atualizacoes)

**Mubisys**
- O romaneio de impressão "aprimora a comunicação entre os setores de pré-impressão ou criação e os de impressão ou corte", detalhado por peça. Há "Terminal de apontamento de produção" e apontamento pelo smartphone. — [Mubisys Produção](https://www.mubisys.com/producao)
- Calendário:
  - Filtros "Usuários" ("ver apenas a agenda daquela pessoa"), "Financeiro" (Contas a Receber/Pagar), "Entregas" (prazos de OS) e "Setores Produtivos" (ex.: "Serralheria, Impressão, Acabamento").
  - Ao passar o mouse, o bloco mostra "valores, descrição e participantes".
- Quadro de avisos "Direcionado a" grupos ou usuários; "Apenas usuários com permissões administrativas podem criar, editar ou excluir avisos".
- O menu Produção tem "PCP", "Produção", "Almoxarife", "Diário de bordo" e "Acompanhamento de produção". — [Central de Ajuda Mubisys](https://ajuda.mubisys.com/)
- "Setorização de produção"; "APP MUBISYS ... sem custo adicional" para "Acompanhe onde estiver como estão as demandas". — [Mubisys](https://www.mubisys.com/)

**GestãoClick (CV)**
- A empresa cria as situações "Arte, Impressão, Acabamento e Instalação". "No kanban, cada uma vira coluna, e arrastar o cartão muda a etapa."
- "Mais de um técnico na mesma OS (o designer e o instalador), e o faturamento de cada um no painel."
- "você acompanha o andamento de cada pedido, divide o trabalho da equipe". — [GestãoClick CV](https://gestaoclick.com.br/segmentos/programa-para-empresa-de-comunicacao-visual/)

**VisualCalc**
- Kanban "Fila → Impressão → Acabamento → Montagem → Pronto → Entrega → Entregue"; "registra cada etapa com data e responsável"; "Sua equipe age antes que o cliente ligue" (alerta de atraso).
- "Veja sua produção em tempo real pelo celular". — [VisualCalc](https://www.visualcalc.com.br/)

**Calcgraf**
- "Planejamento sincronizado para supervisores de diferentes áreas da produção (exemplo: impressão e acabamento)"; "Controle de pré-impressão integrado ao planejamento da impressão"; "gerenciamento de datas de entrega pela área comercial"; "Priorização de clientes e serviços especiais"; "Apontamento com reduzida interferência do operador". — [Calcgraf PCP](https://www.calcgraf.com.br/solucao/pcp/)
- Portais para "clientes e vendedores", com "posição de pedidos, faturamento, produtos em processo". — [Calcgraf Pedido](https://www.calcgraf.com.br/solucao/pedido/)

**Holdprint / Zênite / Everflow / Ajors**
- Holdprint: "Com nosso alocador de recursos, escolhemos de forma inteligente quando e quem deve iniciar cada processo"; "Terminal de Apontamento"; "Agenda de Tarefas"; "Linha de Produção Virtual". — [Holdprint](https://www.holdprint.net/)
- Zênite: "Apontamento automático: ... dados obtidos diretamente do equipamento"; "PCP - Chronos"; "Produção (OS)". — [Zênite](https://www.zsl.com.br/)
- Everflow:
  - "O kanban operacional mostra o status de cada projeto em andamento"; "o coordenador acompanha em tempo real".
  - O instalador registra no app "check-in, fotos de antes e depois da fachada, checklist e assinatura do cliente". — [Everflow CV](https://everflow.com.br/segmentos/software-para-empresa-de-comunicacao-visual-erp/)
  - "Crie formulários personalizados trazendo clareza para o técnico em campo do que ele deve fazer." — [Everflow](https://everflow.com.br/)
- Ajors: "PCP e etapas de produção", "Checklist por grupo de produto" e "Agendamento de instalação e entrega" no SIGN. — [Ajors SIGN](https://ajors.com.br/ajors-sign)
  - O app de campo descrito é o do produto OOH (mídia exterior): "O dia da equipe na abertura: instalações e retiradas pendentes". — [Ajors OOH App](https://ajors.com.br/ajors-ooh/app)

### Inferences
- O que a "Nova venda" entrega à produção deve ser **dado estruturado por item**: medidas, material, acabamentos, tipo de entrega/instalação e prazo. O Sign OS chama isso de "production reads the same numbers sales typed". Observações em texto livre não bastam para virar etapas e filas por setor.
- Precisam ficar **no pedido** (não só na produção) o roteiro de setores do item, vindo do produto/receita, e a data "finalizar produção até". Em Sign OS e shopVOX o template de workflow vem do produto e as datas vêm do orçamento.
- O papel "instalador" aparece em todos os sistemas maduros como uma **visão restrita**: só os jobs atribuídos, endereço, notas, fotos e assinatura, sem preço. Nos BR, isso aparece só no Everflow (app) e, por extensão, no app OOH da Ajors.

### Gaps
- Não achei, em nenhum BR de CV, documentação pública de "o que o usuário de produção vê": se a OS sai sem preço e se há tela de chão de fábrica separada. O mais próximo é "OS para produção" (Nortgraf) e o romaneio por setor (Mubisys).
- A atribuição de etapa a pessoa específica com notificação só aparece documentada no Sign OS (e indiretamente no shopVOX, pela atribuição de usuário por passo).

## 3. Travas entre etapas e faturamento: sinal, arte, crédito; quando e por quem sai a nota (NF-e / NFS-e); faturamento parcial

### Takeaway
As travas reais documentadas são:
- **Arte aprovada** antes da produção: gate explícito no Sign OS; status pós-aprovação na Nortgraf.
- **Sinal pago:** o Sign OS tira o job do board até o sinal ser pago, com override de admin; a Nortgraf trava o pedido enquanto o pagamento manual não é conferido.
- **Crédito/bloqueio do cliente:** Sign OS com nada/avisar/bloquear; Calcgraf com liberação e recusa.

O faturamento tem dois modelos:
- **Na aprovação ou a partir do pedido aprovado** (VisualCalc, GestãoClick com sinal e NF pela OS/situação, Nortgraf pelo menu Ações).
- **Fila de faturamento separada,** acionada por conclusão, expedição ou instalação: Sign OS "Ready to invoice", shopVOX "todos os jobs completos → Invoice", Ordant "após todos os itens" ou "conforme expede", Calcgraf "pendências de faturamento", Mubisys menu "Faturamento".

Faturamento parcial **por item** existe em shopVOX, Sign OS e Ordant. Nos BR, só o Calcgraf (nota por pedido, OP ou produto acabado) sugere algo equivalente.

### Cited Findings

**Sign OS (travas)**
- Arte:
  - "Artwork that needs approval should be approved first; a released job with an unapproved proof waits at the gate." — [Sign OS Help: Release](https://signos.com.au/help/production/release-to-production)
  - No upload, a opção "'Require approval before production uses this file'". "Approve with changes deliberately does NOT release production. Somebody has to read the notes and release it, and that decision is recorded against your name." — [Sign OS Help: Artwork approvals](https://signos.com.au/help/jobs/artwork-approvals)
- Sinal:
  - "Ready to release ... shows Waiting for deposit on jobs whose deposit invoice is unpaid, and with a new setting keeps those jobs off the board until the deposit is paid, while an admin can still move one up for artwork".
  - "accepting a quote sends the customer a deposit invoice for the deposit share on their credit terms, and the order confirmation can wait until that deposit is paid".
- Hold: "put a job on hold with a reason, which keeps it from being released until it comes off hold". — [Sign OS Changelog](https://signos.com.au/changelog)
- Crédito: o bloqueio impede envio de orçamento e liberação de job. O chão vê só "On hold — see office". — [Sign OS Help: Customer on hold](https://signos.com.au/help/exceptions/customer-on-hold)

**Sign OS (faturamento)**
- Fila: "Finished work that never gets invoiced is the quietest way a sign shop loses money".
  - Lista com "Job · Customer · Stage · Job value · Invoiced · Left to bill".
  - Filtros "Ready to invoice (finished or dispatched jobs)", "Outstanding", "Sync failed".
  - "DRAFT OPEN" evita uma segunda fatura. — [Sign OS Help: Invoicing queue](https://signos.com.au/help/invoicing/invoicing-queue)
- Fatura:
  - Modos "Selected items · Deposit / part payment · Everything remaining". Só "Everything remaining" fecha o job.
  - "Once an invoice has gone to accounting its lines lock. Fix mistakes with a credit note, not by editing." — [Sign OS Help: Raise invoice](https://signos.com.au/help/invoicing/raise-invoice)
- Sinal no faturamento: o sinal é rateado entre os itens, "so the final invoice later is simply what is left". "A deposit does not close the job". Padrão de 50%. — [Sign OS Help: Deposit / part payment](https://signos.com.au/help/invoicing/deposit-part-payment)
- Valor a faturar: "Bill this job" fatura o valor orçado, o real (horas e material apontados) ou um valor digitado, com linha de ajuste. — [Sign OS Help: Job commercial](https://signos.com.au/help/jobs/job-commercial)
- Entrega parcial: gera automaticamente uma guia (docket) para o saldo. — [Sign OS Help: Partial delivery](https://signos.com.au/help/exceptions/partial-delivery)

**shopVOX**
- Estados do Sales Order: New, In Process, **Hold** ("also puts on hold any Jobs"), Completed ("automatically set by the system when all the jobs are marked as 'Completed'"), No Charge, No Charge Approved, Void. **Não há** estado de crédito ou de "aguardando sinal". [≈2024] — [shopVOX: Sales Order States](https://shopvox-pro.helpdocs.io/article/4zz9xyb5u3-sales-order-states)
- "Once all of the jobs on the Sales Order are completed, you can convert the Sales Order to an Invoice." — [shopVOX: Where do jobs fit](https://shopvox-pro.helpdocs.io/article/yunn3w6v2p-where-do-jobs-fit-in-the-order-process-in-shop-vox)
- O Sales Order recebe pagamento, gera PO e requisição de material; jobs saem do SO; a fatura sai do QT/SO ou é criada direto; depois "Post to QuickBooks/Xero". — [shopVOX Work Flow - Big Picture](https://shopvox-pro.helpdocs.io/article/5afly3ox3z-shop-vox-work-flow-the-big-picture)
- Fatura parcial:
  - Precisa ser ativada pelo suporte.
  - Ação "To Invoice" no SO, selecionando linhas.
  - "your Invoice numbers will no longer match your Sales Order Numbers".
  - O SO "will Open back up again" para faturar o resto. [≈2024] — [shopVOX: Partial Invoices](https://shopvox-pro.helpdocs.io/article/xgzelxopko-how-to-create-partial-invoices)

**Ordant**
- "After all items of the order are complete, use the order data to convert the order into a ready-to-send invoice. If you want to invoice customers as you ship, convert the line items in the packing list into invoices."
- "Set up due dates and terms for each invoice based on the customer's payment history." — [Ordant Order Management](https://www.ordant.com/module/print-estimating-order-management/)
- Faturamento em lote por data de saída: "Staff cam proof all communications for accuracy before they are automatically sent." — [Ordant Batch Invoicing](https://www.ordant.com/module/print-estimating-using-batch-invoicing/)

**CoreBridge**
- "Approved estimates convert to invoices in one click — no re-keying"; o pagamento pode ser lançado no próprio pedido ("apply payment on the order"). — [CoreBridge](https://www.corebridge.net/); [CoreBridge Invoicing & Payments](https://www.corebridge.net/invoicing-payments)
- "Parallel Workflow & Progressive Billing" está no plano Professional. — [CoreBridge Pricing](https://www.corebridge.net/pricing)

**Nortgraf**
- Pagamento:
  - Trava de pagamento manual pendente (seção 1).
  - Na edição do pedido, marcar pagamento como "Pago" afeta a liberação para envio; o artigo manda confirmar o lançamento bancário antes. — [Nortgraf: Editar e Gerenciar Pedidos](https://nortgraf.com/ajuda/artigo/editar-e-gerenciar-pedidos)
- Nota fiscal:
  - Três pontos de emissão: "Fiscal > NF-e > Nova NF-e" / "Fiscal > NFS-e > Nova NFS-e" (tela fiscal separada), menu Ações da página do pedido, ou PDV.
  - No pedido e no PDV, se algo impede a emissão, a nota cai em rascunho "sem gerar um documento incompleto".
  - Ações sobre a NFS-e "de acordo com o status e as permissões".
  - O artigo não diz se a nota pode cobrir só parte dos itens nem agrupar pedidos. [20/08/2026] — [Nortgraf: Emitindo NF-e e NFS-e](https://nortgraf.com/ajuda/artigo/emitindo-nf-e-e-nfs-e)
- Estoque: o pedido reserva estoque, e "Quando finalizado ou faturado" os itens são baixados. — [Nortgraf: Criando Pedidos](https://nortgraf.com/ajuda/artigo/criando-pedidos-no-painel-de-controle)
- Financeiro: lançamento manual em Contas a Receber "não afeta automaticamente pedidos ou faturas, a menos que haja uma integração específica". — [Nortgraf: Contas a Receber](https://nortgraf.com/ajuda/artigo/lancamentos-contas-a-receber)

**GestãoClick (CV)**
- Onde emitir:
  - NF-e: "Menu da OS ou da venda: Emitir NF-e".
  - NFS-e: "Menu da OS: Emitir NFS-e, **ou sozinha pela situação da OS**". A situação do kanban dispara a emissão.
- Quando: no exemplo, "NF-e da placa e NFS-e do serviço saem da OS; o saldo fica em boleto" na instalação.
- "Você decide em qual etapa o estoque baixa e o financeiro é lançado."
- O sinal é "Recebido na aprovação"; o "saldo fica em aberto até a instalação". — [GestãoClick CV](https://gestaoclick.com.br/segmentos/programa-para-empresa-de-comunicacao-visual/)

**VisualCalc**
- "Quando o orçamento é aprovado, o financeiro é lançado automaticamente"; "Aprovado o orçamento, a conta a receber e o pedido no Kanban são criados automaticamente."
- "emite NF-e direto de qualquer pedido aprovado" (plano Avançado). — [VisualCalc](https://www.visualcalc.com.br/)

**Calcgraf**
- Faturamento é um módulo próprio: "Emissão de notas fiscais vinculadas ao pedido, ordem de produção ou produto acabado"; "Facilidade na emissão das notas fiscais, com a visualização da posição de todos os pedidos"; "pendências de faturamento"; "retenção de impostos"; "diversos locais de entrega para o mesmo produto".
- Contas a Receber: o faturamento "envia os dados de faturamento, criando os títulos automaticamente". — [Calcgraf Faturamento](https://www.calcgraf.com.br/solucao/faturamento/)
- "Títulos gerados automaticamente a partir da emissão da Nota Fiscal". O fluxo de caixa usa "Cenários futuros a partir de pedidos em carteira". — [Calcgraf Financeiro](https://www.calcgraf.com.br/solucao/financeiro/)
- O orçamento "envia os dados do pedido, para automatizar a emissão da nota (NF-e e NFS-e)" e "empenha o material necessário para o trabalho após a emissão da ordem de produção". — [Calcgraf Orçamento](https://www.calcgraf.com.br/solucao/orcamento/)

**Mubisys / Everflow / Ajors / Zênite**
- Mubisys: o menu Financeiro tem "Faturamento", "Monitor de notas", "Fiscal", "Sped fiscal", "Lançamentos futuros" e "O.S.s com restrição". São telas do financeiro, separadas de "Ordens de serviços" (Comercial). — [Central de Ajuda Mubisys](https://ajuda.mubisys.com/)
  - "Emita documentos fiscais com apenas um clique" (NFe, NFCe, MDFe, NFSe). A página não diz por quem. — [Mubisys Financeiro](https://www.mubisys.com/financeiro)
- Everflow: "Emissão de NFS-e vinculada à OS de cada projeto"; "O faturamento sai direto do projeto concluído". — [Everflow CV](https://everflow.com.br/segmentos/software-para-empresa-de-comunicacao-visual-erp/)
- Ajors SIGN: módulo "Faturamento, comissões, notas e boletos" no menu do produto. — [Ajors SIGN](https://ajors.com.br/ajors-sign)
- Zênite: "Nota Fiscal Eletrônica: Agilidade e segurança para um faturamento em dia com suas entregas". — [Zênite](https://www.zsl.com.br/)

### Inferences
- Para o Brasil, há duas posições de produto sobre **quem emite a nota**. A primeira é o **vendedor ou operador na própria OS/pedido** (GestãoClick, Nortgraf, VisualCalc), bom para empresa pequena. A segunda é o **financeiro/fiscal numa fila "a faturar"** (Calcgraf "pendências de faturamento", Mubisys "Faturamento"/"Monitor de notas", Sign OS "Ready to invoice"). A "Nova venda" deveria gravar dados fiscais suficientes por item (natureza produto/serviço, local de prestação/entrega) para que **qualquer** dos dois modelos emita sem redigitar. Também precisa expor o status "a faturar / faturado parcial / faturado" por item.
- Emitir a NFS-e "pela situação da OS" (GestãoClick) e o pedido "avançar para o próximo status" ao aprovar o pagamento (Nortgraf) mostram que, nos BR, **status configurável com gatilhos** é o mecanismo de handoff dominante. Nos internacionais o mecanismo dominante são telas e filas próprias (Release, Invoicing, Approvals).
- O sinal deveria ser **rateado entre itens** (Sign OS) para que, em CV com NF-e e NFS-e, cada nota final saia pelo saldo correto do seu grupo de itens. É inferência: nenhuma fonte BR trata do rateio do sinal entre NF-e e NFS-e (gap já apontado na rodada anterior).

### Gaps
- Nenhuma fonte BR documenta faturamento parcial por item (nota de parte da OS) nem agrupamento de várias OS numa nota.
- Não achei regra "não emitir NFS-e de instalação antes da instalação concluída" como trava configurável. Só o exemplo da GestãoClick de emitir na instalação.
- shopVOX: não há documentação de bloqueio de produção por sinal não pago.

## 4. Permissões e perfis: preço, custo, margem, desconto, edição após aprovação, reabertura e trilha de auditoria

### Takeaway
O nível mais fino documentado é o do **Sign OS**:
- papéis prontos mais "70+ per-user grants" até custo de linha, margem e preço de compra;
- chão de fábrica nunca vê valor;
- só o Admin troca papéis;
- override de crédito como permissão individual;
- toda mudança de status com quem e quando;
- fatura travada após envio, com correção por nota de crédito;
- variação em vez de editar o pedido aceito.

O **shopVOX** tem papéis por área (visível/oculto/editável) e flags específicos para desconto, custo da linha, item avulso e exportação. Os **BR** declaram "permissões por perfil/usuário" (Nortgraf com Ver/Criar/Editar/Excluir por módulo, VisualCalc, Ajors, Mubisys), mas não documentam publicamente ocultação de custo ou margem nem bloqueio de edição após aprovação ou nota.

### Cited Findings

**Sign OS**
- Permissões:
  - "Roles plus 70+ per-user grants control everything down to line costs, margins and buy prices. Floor staff never see a dollar figure."
  - "Approval policies can gate quote sends, invoice issues, PO sends and variations."
  - "Financials never appear on floor screens."
- O assistente de IA respeita as mesmas permissões: "Floor staff's ReAct can't see a price — because floor staff can't". "every action lands in the audit trail". — [Sign OS](https://signos.com.au/)
- Ajuste fino por pessoa: "Below the role: switch individual areas on or off for this person only, without inventing a new role".
  - "Only the Admin (shop owner) role can change roles".
  - "Who can override a credit hold is a permission you give per person here".
  - Desativar o usuário mantém o histórico. — [Sign OS Help: Roles & permissions](https://signos.com.au/help/your-team/roles-permissions)
- No orçamento: "Cost only shows if you can see costs"; a margem da linha é visível "for people who can see it". A edição de horas ou custo na linha fica marcada "Edited", com o valor calculado riscado ao lado. — [Sign OS Help: Change line costs](https://signos.com.au/help/quoting/change-line-costs)
- Reabertura: "Every status change is recorded on the job's activity, including who moved it and when"; "For a cancelled job, an admin uses Reopen job". — [Sign OS Help: Reopen job](https://signos.com.au/help/exceptions/reopen-job)
- Pedido aceito não é reeditado: a variação é aprovada e vira "Revised target", e o "Quoted baseline" fica intacto. — [Sign OS Help: Job variations](https://signos.com.au/help/jobs/job-variations)
- Fatura travada após envio à contabilidade; a correção é por nota de crédito. — [Sign OS Help: Raise invoice](https://signos.com.au/help/invoicing/raise-invoice)
- Na revisão de orçamento, "only one can be accepted"; o sistema avisa se alguém tenta aceitar uma revisão mais antiga. — [Sign OS Help: Quotes & invoices](https://signos.com.au/help/your-shop/quotes-invoices); [Sign OS Help: Quote to job](https://signos.com.au/help/quoting/quote-to-job)
- O supervisor pode corrigir horas já apontadas: "supervisors can fix time already recorded from the job page". — [Sign OS Changelog](https://signos.com.au/changelog)

**shopVOX**
- Áreas de permissão: "Transactions, Quotes, Sales Orders, Invoices, Payments, Credit Memos, Refunds, Jobs, Customers, Contacts, and General". Para cada área: visível, oculto, habilitado, desabilitado ou editável. — [shopVOX: Setting Roles](https://shopvox-pro.helpdocs.io/article/kxjbfjtst4-setting-roles-for-your-team)
- Flags específicos:
  - ver/editar desconto na linha [09/09/2024];
  - custo unitário "Editable" ou "Visible" [27–28/10/2024];
  - restringir "Export to CSV/XLS" a não-admins [20/08/2024]. — [shopVOX: Restrict discounts](https://shopvox-pro.helpdocs.io/article/w4rbdoi2y8-mon-09-09-2024-restrict-users-to-add-change-the-discounts-on-the-line-item-of-the-transactions-pro); [shopVOX: Unit cost permission](https://shopvox-pro.helpdocs.io/article/62bwr9pryt-sun-10-27-2024-role-based-permissions-for-editing-or-overriding-line-item-costs-unit-cost-pro); [shopVOX: Export access](https://shopvox-pro.helpdocs.io/article/mzqnfrckh6-tue-08-20-2024-pro-tip-controlling-and-protecting-access-to-your-customer-and-transaction-data-pro)
- Preço na prova: o flag "Show Price on Proof Review" decide se o valor da linha aparece quando o cliente revisa a prova. [30/05/2024] — [shopVOX: Dollar Value Flag](https://shopvox-pro.helpdocs.io/article/nzxf7dqrvf-thu-05-30-2024-mastering-customer-proof-reviews-unveiling-the-dollar-value-flag-feel-free-to-use-or-modify-it-as-needed-pro-express)
- Histórico: abas "History" em orçamento, SO e fatura; seção History no job. O SO herda o histórico do orçamento. O artigo **não diz** se registra o usuário nem o antes/depois dos campos. [≈2024] — [shopVOX: History Tracking](https://shopvox-pro.helpdocs.io/article/qw8d0qtczi-history-in-progress)
- PDFs: a página de configurações não documenta ocultar preço na Work Order. — [shopVOX: PDF Settings](https://shopvox-pro.helpdocs.io/article/odtmiygek1-pdf-settings)

**CoreBridge**
- "Manage permissions, users, and access by role or shop" (multi-loja). — [CoreBridge Multi-Location](https://www.corebridge.net/multi-location-management)
- A matriz de recursos lista "Employee Roles — Customizable" e "Custom User Access Rights", além de "Configurable Password Policy" e "Multi-Factor Authentication". — [CoreBridge Pricing](https://www.corebridge.net/pricing)

**Nortgraf**
- Perfis de acesso:
  - permissões por módulo ("Vendas", "Catálogo" etc.) com ações Ver, Criar, Editar e Excluir;
  - contador "Permissões Ativas"; princípio do menor privilégio;
  - nomes de exemplo: "Vendedor", "Gerente", "Estoquista", "Atendimento SAC", "Financeiro Completo", "Financeiro Leitura".
  - O artigo **não** mostra permissões separadas para ver custo, dar desconto, cancelar ou emitir NF. [16/08/2026] — [Nortgraf: Perfis de Acesso](https://nortgraf.com/ajuda/artigo/perfis-de-acesso-controle-de-permissoes)
- Usuários:
  - o Perfil/Grupo liga o usuário às permissões; "Inativo" bloqueia sem apagar o histórico;
  - "Políticas de Acesso" incluem "restrição de horário de login e regras de comissionamento". — [Nortgraf: Usuários](https://nortgraf.com/ajuda/artigo/usuarios-gestao-de-acesso-e-equipe)
- Funcionários é um cadastro de RH (cargo, admissão, salário) separado de Usuários. Não tem campo de setor nem de comissão. — [Nortgraf: Funcionários](https://nortgraf.com/ajuda/artigo/funcionarios-cadastro-e-gestao-de-equipe)
- Auditoria do pedido:
  - "consulte o histórico (timeline) para ver quem fez a última alteração e quando". — [Nortgraf: Editar e Gerenciar Pedidos](https://nortgraf.com/ajuda/artigo/editar-e-gerenciar-pedidos)
  - O histórico registra "mudanças de estado, pagamentos, arquivos, observações e ações registradas"; "Use data e usuário para entender a sequência". — [Nortgraf: Pedidos — histórico](https://nortgraf.com/ajuda/artigo/pedidos-historico-documentos-e-impressao)
- Visibilidade na produção: "permissões e acesso ao fluxo" afetam quem vê ou move a ordem. — [Nortgraf: Produção](https://nortgraf.com/ajuda/artigo/producao-ordens-filas-e-kanban)

**VisualCalc**
- "Defina permissões por usuário"; "Cada usuário tem acesso com login próprio e você pode definir permissões por perfil". Usuários: Básico 1, Profissional 3, Avançado ilimitados.
- "Event Log & Auditoria — Registro automático de cada ação no sistema. Rastreabilidade completa: quem fez o quê e quando." — [VisualCalc](https://www.visualcalc.com.br/)

**Ajors / Mubisys / GestãoClick / Calcgraf / Holdprint**
- Ajors: "O sistema trabalha com perfis e permissões por usuário, permitindo limitar o que cada área enxerga e altera — comercial, operação, financeiro e direção." — [Ajors Segurança e LGPD](https://ajors.com.br/seguranca-lgpd)
- Mubisys: o menu Sistema tem "Usuários", "Permissão de acesso" e "Log de acesso"; os artigos não foram publicados. — [Central de Ajuda Mubisys](https://ajuda.mubisys.com/)
- GestãoClick: o assistente de IA "responde dentro do que o seu usuário pode ver", o que indica visibilidade por usuário. "Cada mudança de situação fica no histórico do orçamento, com data e observação". — [GestãoClick CV](https://gestaoclick.com.br/segmentos/programa-para-empresa-de-comunicacao-visual/)
- Calcgraf: "Registro e controle de alterações dos orçamentos"; "Recursos de segurança ampliados para ... usuários de diferentes níveis de responsabilidade". — [Calcgraf Orçamento](https://www.calcgraf.com.br/solucao/orcamento/)
- Holdprint: "níveis de autorizações para compra" (compras, não venda). — [Holdprint](https://www.holdprint.net/)

### Inferences
- Para a "Nova venda", a matriz mínima de permissões que o mercado sinaliza é esta:
  - **ver custo/margem** (separado de ver preço);
  - **editar custo da linha;**
  - **dar desconto;**
  - **incluir item avulso;**
  - **override de bloqueio de crédito** (com motivo);
  - **liberar para produção;**
  - **emitir nota/fatura;**
  - **aprovar variação/edição pós-aceite;**
  - **reabrir/cancelar** (só admin).

  Sign OS e shopVOX cobrem quase tudo. Nenhum BR documenta mais que Ver/Criar/Editar/Excluir por módulo.
- A melhor regra para "editar depois de aprovado" no mercado é **não editar o pedido aceito**. Faz-se uma revisão antes do aceite e uma variação ou "Ordered tab" depois, mantendo baseline × revisado (Sign OS). Isso preserva comissão, sinal e notas já emitidas e evita o conflito clássico vendedor × financeiro.
- A trilha de auditoria precisa de **usuário + data + ação** em cada mudança de status, aprovação e override. Nortgraf, VisualCalc e Sign OS documentam isso. O shopVOX documenta histórico mas não o usuário.

### Gaps
- Nenhum BR documenta publicamente perfil "produção sem preço" nem ocultação de custo e margem ao vendedor.
- Não encontrei bloqueio de edição do pedido após nota emitida nos BR. O Sign OS faz isso na fatura; a Nortgraf não diz.
- Restrição "vendedor vê só os próprios clientes/pedidos" não apareceu documentada em nenhum sistema.

## 5. Comissão: base, momento de liberação (pagamento, faturamento, entrega) e quem controla

### Takeaway
Três regras de liberação aparecem:
- **no recebimento do cliente** (Mubisys);
- **após entrega confirmada** (VisualCalc);
- **por critério configurável** — valor faturado, vencimento ou liquidação — com etapa explícita de "liberação das comissões pendentes" que gera título a pagar (Calcgraf).

Os internacionais calculam a comissão **por linha**, sobre preço, custo ou lucro, e exigem o vendedor definido **antes** dos itens (shopVOX). Não documentam o momento de liberação. Hierarquia (gestor ganha sobre a equipe) aparece em Mubisys e Calcgraf.

### Cited Findings
- **Mubisys:** comissão por "margens, percentual ou até mesmo valor fixo, liberando o pagamento somente quando o cliente pagar"; "gere comissão para seus gestores sobre a venda de seus vendedores"; bônus escalável por volume. A página não diz quem libera. — [Mubisys Comercial](https://www.mubisys.com/comercial)
- **VisualCalc:** "Comissões calculadas automaticamente por pedido e liberadas após entrega confirmada"; "acompanhe desempenho por vendedor e controle comissões automaticamente". Disponível do plano Profissional para cima. — [VisualCalc](https://www.visualcalc.com.br/)
- **Calcgraf:**
  - "Controla comissões por vários critérios: valor faturado, data do vencimento ou liquidação".
  - "Títulos de pagamento criados automaticamente após a liberação das comissões pendentes".
  - "Controle de diversos agentes: vendedores, agências, produtores e internos"; "Controle de adiantamentos e reembolsos".
  - "Pós-Cálculo: para cálculo comparativo entre a comissão considerada no Orçamento e a efetivamente paga". — [Calcgraf Comissões](https://www.calcgraf.com.br/solucao/comissoes/)
  - "Hierarquia de comissões (gerentes, supervisores e vendedores)". — [Calcgraf Orçamento](https://www.calcgraf.com.br/solucao/orcamento/)
  - "Consulta de comissões a pagar" pelo pedido. — [Calcgraf Pedido](https://www.calcgraf.com.br/solucao/pedido/)
- **Holdprint:** "Controle de Comissões": "pagar seus vendedores por metas e lucratividade. Quanto mais lucro, mais comissão". — [Holdprint](https://www.holdprint.net/)
- **shopVOX:**
  - "Sales commission is calculated as soon as you add in your first line item"; "Assign the sales rep before you add items".
  - A taxa do produto sobrepõe a do usuário; taxas In-House e Outsource; base Price, Cost ou Profit; "never per invoice total".
  - O artigo não fala de quando a comissão vira devida. [≈2024] — [shopVOX: Sales Commissions](https://shopvox-pro.helpdocs.io/article/7q20136doe-sales-commission-in-progress)
- **CoreBridge:** "Sales Commission Tracking" e "Sales Goals Tracking" na matriz de recursos (plano não legível). — [CoreBridge Pricing](https://www.corebridge.net/pricing)
- **Nortgraf:** "regras de comissionamento" aparecem dentro de "Políticas de Acesso" do usuário, sem detalhe. — [Nortgraf: Usuários](https://nortgraf.com/ajuda/artigo/usuarios-gestao-de-acesso-e-equipe)
- **Ajors:**
  - O menu do SIGN tem "Faturamento, comissões, notas e boletos". — [Ajors SIGN](https://ajors.com.br/ajors-sign)
  - No produto OOH: "Cálculo de comissão para vendedores, executivos de contas e agências de publicidade, conforme a política comercial". Isso é do OOH, não do SIGN. — [Ajors OOH Financeiro e fiscal](https://ajors.com.br/ajors-ooh/financeiro-fiscal)
- **GestãoClick:** mostra "o faturamento de cada um" (designer e instalador) "no painel". É produtividade e receita por técnico, não comissão explícita. — [GestãoClick CV](https://gestaoclick.com.br/segmentos/programa-para-empresa-de-comunicacao-visual/)

### Inferences
- A "Nova venda" deve fixar **no momento da venda**:
  - o(s) comissionado(s): vendedor e, opcionalmente, gestor, indicador/arquiteto ou técnico;
  - a base: preço ou margem;
  - a regra de liberação, herdada do vendedor ou da empresa: aprovação, faturamento, recebimento, entrega ou instalação.

  O cálculo pode ser por item (shopVOX), mas a **liberação** é evento do financeiro ou da operação. Calcgraf e Mubisys a amarram a faturamento ou recebimento.
- A comissão sobre margem (Mubisys, Holdprint, shopVOX "Profit") implica que desconto e custo mexem na comissão. Isso reforça a necessidade de travar desconto e custo por permissão (seção 4).

### Gaps
- Nenhuma fonte documenta comissão proporcional a recebimento parcial (ex.: liberar 50% da comissão quando o sinal cai).
- Sign OS, Ordant, Everflow e Zênite: não achei recurso de comissão nas páginas abertas.
- Quem "controla" a liberação (papel financeiro × gerente comercial) não é dito por nenhuma fonte. Só o Calcgraf menciona uma etapa de "liberação".

## 6. Atrito entre vendas, produção e financeiro (retrabalho, pedidos parados, redigitação): o que fornecedores e usuários dizem

### Takeaway
Os fornecedores vendem o fim de cinco dores:
- **redigitação** entre orçamento, produção e financeiro;
- **arte aprovada "por mensagem"** e reimpressão por conta da gráfica;
- **pedido que ninguém viu vencer**;
- **trabalho pronto que não foi faturado**;
- **margem descoberta só na fatura.**

Os usuários (Signs101) relatam ficha de OS impressa que "perde informação", resistência do chão de fábrica a apps ("só apertar um botão") e a trava prática "desenho aprovado + sinal" antes de produzir. Também pedem ferramenta leve para pedido pequeno.

### Cited Findings

**Fornecedores**
- GestãoClick: "Em comunicação visual, o prejuízo raramente está na impressora. Está no orçamento calculado de cabeça, na arte aprovada 'por mensagem' que o cliente depois não reconhece, e no pedido que ninguém viu que vencia amanhã"; "quando a arte só foi aprovada por mensagem, o reimpresso sai do seu bolso". — [GestãoClick CV](https://gestaoclick.com.br/segmentos/programa-para-empresa-de-comunicacao-visual/)
- VisualCalc:
  - "Planilha para orçamento, outra para financeiro, outra para clientes. Dados duplicados, fórmulas quebradas, informação perdida."
  - "Equipe não sabe o que priorizar. Pedidos atrasam sem que ninguém perceba a tempo."
  - "Nota fiscal emitida manualmente, dias depois."
  - "Orçamento demora dias, aprovação por papel ou mensagem perdida, nota fiscal chega tarde." — [VisualCalc](https://www.visualcalc.com.br/)
- Sign OS:
  - "Every gap between systems is a place where money quietly leaks out"; "Nobody finds out the margin was gone until the invoice". — [Sign OS](https://signos.com.au/)
  - "Finished work that never gets invoiced is the quietest way a sign shop loses money." — [Sign OS Help: Invoicing queue](https://signos.com.au/help/invoicing/invoicing-queue)
  - Exceções que exigem fluxo próprio: "Take back a proof approval that should not have been acted on"; "Approve with changes no longer releases production on its own". — [Sign OS Changelog](https://signos.com.au/changelog)
- CoreBridge: "Eliminate 'who's handling this?' moments on active jobs"; "Missing materials, finished jobs sitting unshipped, and install delays erode profit fast". — [CoreBridge Sign](https://www.corebridge.net/sign)
- Ajors (sobre o produto OOH, mas descrevendo o mesmo atrito comercial → financeiro):
  - "O financeiro trabalha com informação de segunda mão ... Alguém repassa o que foi vendido, alguém confirma o que foi veiculado, e o faturamento é montado a partir desses relatos";
  - "Faturamento montado a partir de planilha do comercial"; "Nota fiscal emitida em outro sistema, com redigitação". — [Ajors OOH Financeiro e fiscal](https://ajors.com.br/ajors-ooh/financeiro-fiscal)
  - Para quem faz CV e OOH juntos: "A equipe entra uma vez e trabalha nas duas operações". — [Ajors OOH + SIGN](https://ajors.com.br/ooh-e-sign-integrados)
- Visua (instalação): "Uma medida errada, uma falta de insumo, um erro de rota, e todo o cronograma se compromete"; "custos dobrados com deslocamento e mão de obra"; checklist para que "o instalador não esqueça ferramentas, materiais ou instruções técnicas". [10/07/2025] — [Visua blog](https://www.visua.com.br/2025/07/10/instalacoes-sem-retrabalho-3-passos-para-evitar-prejuizos/)

**Usuários (Signs101)**
- [2015, **desatualizado**] "we print our work orders from signvox and put the in job jackets for production and finishing", e "there is lost information in this method". Entre vendas, design/pré-impressão e produção/acabamento, "totals out to 45mins just to update and go through the steps per job". Outro usuário: o sistema deve ser a fonte única, ou há "people wandering about the place trying to get answers". — [Signs101: Work order / job flow (2015)](https://www.signs101.com/threads/work-order-job-flow-software-or-process-what-do-you-do.124173)
- [ago/2022]:
  - "we need full approvals of all drawings and deposit" antes de produzir;
  - "the guys on the floor doesn't want to learn how to look at an app";
  - "Most production people should only need to press a button";
  - "Leave any data entry to production managers";
  - "Each employee logs in and out when the job is at their station". — [Signs101: How do you track jobs (p.3, 2022)](https://www.signs101.com/threads/how-do-you-guys-keep-track-of-orders-jobs-currently-in-your-shop.169677/page-3)
- [jul/2026]:
  - "We aren't going to assign tasks to something that takes 20 minutes to complete";
  - faturas do app "clunky and messy" ao ir para a contabilidade;
  - o autor do Sign OS diz que lojas ainda gerenciam produção "depending on the production manager's whims". — [Signs101: early adopters sign shop MIS (2026)](https://www.signs101.com/threads/looking-for-some-early-adoptors-to-help-shape-a-sign-shop-mis.183747/)
- Ver também a rodada anterior (`internacional_sign_shop.md`, seção 7): críticas de 2017 ao CoreBridge, "designed for companies that have strict roles", e lentidão para lançar trabalho simples.

### Inferences
- O atrito acontece em quatro **pontos de passagem**, e a "Nova venda" pode resolver parte deles na origem:
  1. **Comercial → arte:** briefing e arquivos por item, com versão.
  2. **Comercial → produção:** especificação estruturada, prazo de produção e tipo de entrega/instalação obrigatórios na hora de virar pedido. O Sign OS pede datas e fulfilment no aceite.
  3. **Produção → financeiro:** uma lista "pronto/instalado e não faturado".
  4. **Financeiro → comercial:** status de sinal e crédito visível no pedido para o vendedor, sem expor saldo ao chão.
- O chão de fábrica rejeita digitar. Logo, a divisão de tarefas deve exigir dado da **venda** e só **um toque** da produção. Também deve haver um modo rápido para trabalho de balcão, que pula a liberação formal. Isso confirma a recomendação da rodada anterior.

### Gaps
- Não achei depoimento de usuário brasileiro (Reclame Aqui, fóruns) sobre atrito entre setores em Mubisys, Holdprint ou Zênite. A página do Reclame Aqui sobre a Zênite já tinha dado 403 na rodada anterior.
- Faltam dados quantitativos (ex.: % de pedidos parados por falta de sinal ou arte). Nenhuma fonte traz isso.

## 7. Matriz por sistema e implicações para a tela "Nova venda" num fluxo multipessoa

### Takeaway
A matriz abaixo consolida as seções 1 a 6. Sign OS, shopVOX e Nortgraf documentam o "quem faz o quê"; os demais documentam só o "o quê". Para o produto, a lição central é: a "Nova venda" é a **origem de dados de todos os departamentos**, mas **não** deve ser a tela onde todos trabalham. Cada papel precisa de uma fila própria alimentada pelo pedido:
- Liberar (PCP);
- Floor/Setor;
- Agenda de instalação;
- A faturar;
- Aprovações;
- Bloqueados por crédito.

### Cited Findings
- Legenda: **S** = documentado em página aberta; **P** = parcial/indireto; **—** = não encontrado.
- Cada célula se apoia nas citações das seções 1 a 6.

| Mecanismo | Sign OS | shopVOX | CoreBridge | Ordant | Nortgraf | GestãoClick | VisualCalc | Calcgraf | Mubisys | Everflow | Ajors | Holdprint | Zênite |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Papéis prontos (vendas/PM/produção/financeiro/instalador) | S | S (CSR/Sales/Designer/Production) | P | P (CSR) | P (nomes de exemplo) | — | — | P (atendente/vendedor/orçamentista/gestor) | — | P (coordenador/instalador) | P (comercial/operação/financeiro/direção) | — | — |
| Permissão por pessoa além do papel | S | — | S (custom access rights) | — | S (perfil por módulo) | — | S | P | P (menu) | — | S | — | — |
| Produção sem preço | S | P (papel Production só Jobs) | — | — | P ("OS para produção") | — | — | — | — | — | — | — | — |
| Ver/editar custo e margem por papel | S | S | — | — | — | — | — | — | — | — | — | — | — |
| Desconto restrito por papel | — | S (ver/editar) | — | — | — | — | — | — | — | — | — | — | — |
| Aprovação interna (envio orçamento/fatura/variação) | S | P (Internally Approved*) | P (aprova prova internamente) | S (processo por cliente) | — | — | — | S (supervisão orçamentista) | — | P (compra) | — | P (vendedor revisa) | — |
| Crédito: bloqueio/limite com override | S | — | — | S (limite por histórico) | — | — | — | S (liberação/recusa) | P ("O.S.s com restrição") | — | — | P (créditos/permutas) | — |
| Liberação para produção como ato separado | S (Release) | S (Create Job) | — | — (automática) | S ("envio para produção") | P (Gerar OS) | — (automática) | P (ordem de produção) | P (PCP) | — (automática) | — (automática) | P (PlanJobs) | P (PCP) |
| Gate de arte | S | P (prova aprovada avança etapa*) | P | S (se/então) | P (status pós-aprovação*) | P (assinatura) | — | — | P (layout) | — | — | — | — |
| Gate de sinal | S | — | — | P (pede depósito) | P (pagamento manual trava) | — | — | — | — | — | — | — | — |
| Fila por setor/departamento | S | S | S | S (workstations) | S (kanban) | S (situações) | S | S (PCP por área) | S (setores) | P | P | S | P |
| Atribuição de etapa a pessoa + aviso | S | S (usuário por passo) | P (assign crews/tasks) | P (lembretes) | P (filtro responsável) | P (técnicos na OS) | P (responsável) | — | P (agenda por usuário) | — | — | P (alocador, marketing) | — |
| Portal/app restrito do instalador | S (portal) | S (shopVOX Go) | P | — | P (entregas*) | — | — | — | P (app) | S | P (app OOH) | — | — |
| Fila "a faturar" separada | S | P (SO completo → fatura) | — | S (após itens/expedição) | P (tela Fiscal) | — | — | S (pendências de faturamento) | P (menu Faturamento) | P (projeto concluído) | P (módulo) | — | — |
| Nota/fatura emitida do próprio pedido/OS | S (do job) | S | S | S | S (Ações) | S (+ por situação) | S | S | P | S (NFS-e) | P | — | P |
| Faturamento parcial por item | S | S (ativar no suporte) | S (progressive billing) | S (por romaneio) | — | — | — | P (por OP/produto acabado) | — | — | — | — | — |
| Edição pós-aceite controlada (variação/lock) | S | — | — | P (change orders*) | — | — | — | P (registro de alterações) | — | — | — | — | — |
| Auditoria usuário+data por ação | S | P (history sem usuário) | — | — | S | P (situação c/ data) | S | P | P (log de acesso) | — | — | — | — |
| Comissão com regra de liberação | — | P (por linha, sem liberação) | P (tracking) | — | P (regras citadas) | — | S (após entrega) | S (faturado/vencimento/liquidação) | S (no pagamento) | — | P | P (metas/lucro) | — |

\* Itens com asterisco vêm da rodada anterior: shopVOX "Internally Approved", shopVOX "prova aprovada avança etapa" e Ordant "change orders" em `internacional_sign_shop.md`; Nortgraf "Status após aprovação" da arte e "Solicitações de Entrega" em `br_comunicacao_visual.md`.

### Inferences
- **Desenho sugerido para a "Nova venda" num fluxo multipessoa** (inferência a partir da matriz):
  1. **Cabeçalho com donos:** vendedor (comissão) obrigatório antes dos itens, como no shopVOX; responsável/PM opcional; datas "cliente precisa até" e "produção concluir até"; tipo de entrega (retirada/entrega/instalação) com endereço da obra.
  2. **Item com destino:** cada item com natureza fiscal (produto/serviço), roteiro de setores (do produto), flag "requer arte aprovada" e "requer instalação". É isso que gera as filas de Arte, Setores, Instalação e Faturamento.
  3. **Painel de travas visível ao vendedor:**
     - arte (aguardando, aprovada, aprovada com ajustes = não libera);
     - sinal (aguardando, pago);
     - crédito (ok, aviso, bloqueado, com quem pode liberar);
     - dados técnicos completos.

     O pedido só vai para "Pronto para liberar" quando as travas configuradas estiverem verdes, com override por permissão e motivo, como no Sign OS.
  4. **Ação "Liberar para produção"** separada de "Aprovar". Pode ser automática para quem quer simplicidade (modelo VisualCalc/GestãoClick) ou manual pelo PCP (modelo Sign OS/shopVOX/Nortgraf). É configuração da empresa.
  5. **Pós-aceite:** a venda fica travada para preço. Mudança vira "aditivo/variação" aprovado por papel configurável. O histórico mostra usuário + data em cada status.
  6. **Visões por papel:** produção e instalador sem valores; financeiro com "a faturar", "sinal pendente" e "bloqueados"; vendedor com "meus pedidos parados e por quê".
  7. **Faturamento:** botão no pedido (para quem é pequeno) **e** fila "a faturar" por item (para quem tem financeiro/fiscal). Faturamento parcial por item. Sinal rateado nos itens.
  8. **Comissão:** regra de liberação configurável (aprovação, faturamento, recebimento, entrega), herdada pelo pedido. É o diferencial dos verticais BR (Mubisys, Calcgraf, VisualCalc).

### Gaps
- A matriz reflete só o que está público. "—" quer dizer "não encontrado", não "não existe".
- A maior lacuna é dos verticais BR grandes (Mubisys, Holdprint, Zênite): seus fluxos de aprovação interna, liberação e permissões só seriam confirmados por demo, trial ou acesso à base de ajuda autenticada. Vale um teste guiado: trial de 14 dias da VisualCalc, demo da Mubisys e da Holdprint.
- Também não verifiquei:
  - **Ajors SIGN:** detalhamento além da página de produto.
  - **Everflow:** como as permissões funcionam.
  - **CoreBridge:** quais planos incluem "Custom User Access Rights" e "Sales Commission Tracking". As marcas da matriz de preços não puderam ser lidas.
