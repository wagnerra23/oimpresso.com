# Tela de orçamento / pedido em softwares internacionais de gestão para comunicação visual (sign shops e wide-format)

> Escopo: casos de uso suportados (ou disparados) pela tela de orçamento/estimate/quote/sales order de 8 sistemas. Os mais bem documentados são **shopVOX**, **CoreBridge**, **Cyrious Control** (antecessor/legado), **Ordant** e **Sign OS** (signos.com.au). Os demais aparecem só como apoio: **DecoNetwork** (foco em vestuário), **ePS/EFI PrintSmith Vision**, **EstiMate Desktop** e **SignTracker** (legado).
> Data de referência: 20/10/2026. Cada afirmação cita uma página que foi efetivamente aberta. O que vem de snippet de busca sem a página aberta foi para "Gaps". Inferências aparecem marcadas como tal.
> Legenda de datas: [2017], [2021] etc. indicam a data da fonte. Fontes anteriores a 2023 estão marcadas como **possivelmente desatualizadas**.

## 1. Motor de preço na linha do item (área, linear, faixas por quantidade, fórmulas/templates, tabela por cliente, modificadores, custo/markup/margem)

### Takeaway
Todos os sistemas sérios de comunicação visual calculam o preço da linha a partir de um **produto-modelo**, não de um preço digitado. O modelo combina dimensões (L × A × qtd → m²/ft²; perímetro; metro linear), materiais, mão de obra e máquina, cada um com seu markup. Somam-se **modificadores** (checkbox, número, lista ou faixa: dupla face, ilhós, bainha, laminação), um **nível de preço por cliente** (varejo/atacado/revenda) e **descontos por quantidade**. Custo, markup e margem ficam visíveis internamente na própria tela. O Sign OS (2026) acrescenta faixas de preço por m², nesting, desperdício/rendimento e alerta de margem baixa feito por IA.

### Cited Findings

**shopVOX (Pro/Express)**
- Na criação do orçamento, o usuário escolhe um "base product" ou um item custom, renomeia o produto e preenche os campos restantes; "a price is generated automatically" — [How to Create a Quote](https://shopvox-pro.helpdocs.io/article/q3j7kil5u1-how-to-create-a-quote)
- Três métodos de preço por produto:
  - **Standard** (padrão): taxas de materiais, mão de obra e maquinário, cada componente com seu markup.
  - **Product Pricing**: preço fixo, independentemente dos componentes.
  - **Cost Plus**: markup sobre o custo total. Exemplo do artigo: custo unitário US$ 83,07 + markup de 150% (US$ 124,61) = US$ 207,68.
  - O "Pricing Breakdown" mostra Cost, Markup e Final Price por pé quadrado de cada material — [Pricing Methods](https://shopvox-pro.helpdocs.io/article/umk75fccmv-pricing-methods-standard-product-and-cost-plus-pricing)
- O tipo de preço "Formula" traz fórmulas prontas:
  - Area (em pés ou polegadas), Area in Square Yards, Total Square Feet (digitar a área total sem L×A);
  - Height, Width, Perimeter in Yards;
  - Volume, Board Feet, Cylindrical Surface Area/Volume.
  - O artigo não fala de faixas de quantidade nem de preço mínimo — [Products - Pricing Type - Formulas](https://shopvox-pro.helpdocs.io/article/t6cpucl51f-products-pricing-type-formulas)
- **Modifiers** são atributos preenchidos no orçamento/pedido que alimentam a fórmula de preço:
  - **Boolean** (ex.: "Double-Sided");
  - **Numeric** (ex.: "Number of Colors");
  - **Range** (valor limitado, com slider);
  - qualquer um pode ser marcado como opcional ("This Attribute is Optional").
  - Um Boolean pode somar um valor fixo e um Numeric pode multiplicar por um custo base — [Understanding Modifiers](https://shopvox-pro.helpdocs.io/article/xdnxvk5k50-understanding-modifiers-in-shop-vox-enhancing-product-pricing-flexibility)
- **Pricing Levels** (ex.: Wholesale/Retail): o nível é definido no cadastro do cliente, e o produto tem uma variante de preço por nível (markup ou desconto). "The Wholesale quote will reflect a discounted rate" — [Understanding Pricing Levels](https://shopvox-pro.helpdocs.io/article/41el5yiek9-understanding-pricing-levels-in-shop-vox)
- A página de vendas para sign shops cita níveis de preço "retail, wholesale, and nonprofit" (Pro) e templates "Golden" pré-montados que calculam custo e markup — [shopVOX Sign Shop Software](https://shopvox.com/sign-shop-software/)
- O índice da central de ajuda confirma a existência (pelos títulos) de:
  - "Products: Grid Pricing Example" (tabela/grade de preço);
  - "Understanding Sell/Buy Ratio";
  - "Roll-Up Line Items to Combine Pricing";
  - "How to Create a Custom Line Item";
  - "Custom Product, Template, and Code Modifiers";
  - "Product Templates - User Created Formulas".
  - (Conteúdo não aberto; existência inferida pelo título) — [shopVOX Pro Help Center](https://shopvox-pro.helpdocs.io/)
- **Instalação como produto**: "Field Install" tem custo/hora e preço/hora (ou % de markup), e a quantidade da linha é em horas (0,5 = 30 min). "The system automatically multiplies the hours entered by the configured hourly rate" — [Field Installation - Golden Product](https://shopvox-pro.helpdocs.io/article/drj750nl5s-field-installation-golden-product)
- A margem aparece na tela do orçamento em "Show All Information" → seção "Financial Details" — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote)

**CoreBridge**
- "Guided quoting":
  - largura, altura, originais e acabamentos ("grommets and hemming") alimentam o orçamento;
  - acabamentos entram sem sair do estimate;
  - "Linked modifiers keep shop standards in the quote";
  - material, tamanho e quantidade definem o preço automaticamente;
  - "Material, labor, markup, and vendor costs applied automatically";
  - templates prontos para trabalhos de sign, print e fabricação — [CoreBridge Estimating Intelligence](https://www.corebridge.net/estimating-intelligence)
- Planos: "quick product templates" estão em todos. "Estimate variations / company quick products" e "AI product and modifier builder" só a partir do Plus (US$ 549/mês) — [CoreBridge Pricing](https://www.corebridge.net/pricing)
- Review de usuário [2021]: preço cost-plus e "market-based" (preço direto); descontos por volume ajustáveis de 5% até centésimos de %; **8 níveis de preço** com o cliente atribuído a um nível. O revisor achou o sistema mais flexível em preço entre os que avaliou — [IRgA Cyrious Report (review do Corebridge), mai/2021](https://www.irga.com/cyrious-report/)
- Fórum [2017, possivelmente desatualizado]: ex-usuário do Cyrious diz que o Control "provides more fine-tuning in pricing"; "quickproducts" reaproveitam produtos já montados — [Signs101: Shopvox vs Corebridge](https://www.signs101.com/threads/shopvox-vs-corebridge.142694)

**Cyrious Control (legado)**
- Separa **Products** (o que se vende) de **Parts** (insumos), que têm 6 tipos: Material, Labor Time, Equipment Usage/Time, Outsource, Freight e Other.
  - Cada Part tem custo fixo ou por tempo, comprimento ou área.
  - "A product's cost is the sum of the cost of its components."
  - **Pricing Forms** são desenhados no setup e "used to enter information during order and estimate entry"; mostram Online Help e **Flags** (avisos) ao operador.
  - O Unit Type (each, length, area, weight, rate) rege as dimensões.
  - Mecanismos: Default Cost Multipliers (cost-plus), Saved Pricing Formulas, Lookup Tables, Discount Tables e Modifiers (Selection Lists + Variables).
  - Pricing Levels (cliente ou funcionário, ± %), Pricing Plans, Pricing Families (desconto agregado por família) e Promotions — [Cyrious Wiki: Cyrious and Pricing](https://control.cyriouswiki.com/pricing_ch_02-cyrious_and_pricing)
- Material de marketing: mostra o "estimated profit" antes de enviar o orçamento e ajuda a garantir que refugo e extras sejam cobrados — [SourceForge: Cyrious](https://sourceforge.net/software/product/Cyrious/)

**Ordant**
- Cada linha traz custos por tipo de processo, quantidades de material, mão de obra e uma **margem-alvo**.
  - Cadastro de processos, máquinas, materiais e "pricing formulas"; preço de urgência ("expedited pricing").
  - A margem-alvo vale por processo ou varia por tipo de trabalho.
  - Dá para comparar o custo de rodar o trabalho em outro processo (wide-format × impressora digital).
  - O módulo não descreve preço por área nem faixas de quantidade — [Ordant Estimating Module](https://www.ordant.com/module/best-print-estimating-software/)
- O módulo de Vehicle Wrap estima tempo, material e mão de obra de envelopamento. A página de signage fala em orçar "projetos complexos com design, fabricação e instalação" — [Ordant home](https://www.ordant.com/)

**Sign OS (signos.com.au, Austrália)**
- "Product recipes" com materiais, tempo de máquina, mão de obra e markup; receitas "sentence-based" com prévia de preço ao vivo.
- Cálculo automático de consumo, **desperdício** e minutos de máquina.
- "Tiered price-per-square-metre, so bigger jobs price the way you actually sell them"; preço por m² e por metro linear, com desperdício e rendimento.
- Opções que **não podem** ser combinadas ou que **devem** ser combinadas.
- Desconto por quantidade; opções percentuais (ex.: urgência).
- Produto avulso (one-off); **override manual de preço em qualquer linha, sempre visível**.
- "AI pricing advisor that flags quotes below your usual margin".
- No plano Scale: designer de letra-caixa (channel letters), layout de LED, preço de corte por DXF/SVG e **nesting** de chapas — [Sign OS](https://signos.com.au/)
- A listagem do Slashdot repete: materiais vendidos por chapa, rolo, m² ou metro linear; desperdício/rendimento, tempo de máquina, mão de obra e "tiered markups" para todos tirarem o mesmo preço — [Slashdot: Sign OS](https://slashdot.org/software/p/Sign-OS/)

**DecoNetwork (foco em vestuário; signage é secundário)**
- As price tables de impressão aceitam preço fixo por área de decoração ou por faixa de tamanho, e preço escalável "per square inch". Faixas de quantidade só aparecem descritas para bordado. O artigo [out/2024] não fala de placas ou banners — [Help Chronicles #21](https://www.deconetwork.com/help-chronicles-21-configure-price-tables/)
- No orçamento, preço e impostos se atualizam conforme os itens entram, e a taxa de arte é calculada automaticamente — [DecoNetwork Quotes](https://www.deconetwork.com/home/features/quotes/)

**EstiMate Desktop (Windows, sign pricing)**
- "Use customizable upcharges on any of EstiMate's pricing screens"; "Stair stepped quantity discounts are available" — [EstiMate features](https://estimatesoftware.com/features)

**SignTracker [2007, desatualizado; baseado em Excel]**
- Templates de estimativa para channel letters, caixas (cabinets) e toldos.
- Módulo "master formula" (mão de obra + material + tamanho/tipo) e lista de 500 materiais customizável.
- Comissões, markup, licenças (permits) e frete entram no total do projeto — [Signs of the Times: SignTracker, abr/2007](https://signsofthetimes.com/signtracker/)

**PrintSmith Vision (ePS, ex-EFI)**
- Usuário [ago/2022] relata que o módulo wide-format "based off their cut sheet/offset pricing matrices", o que é "a little counterintuitive" para quem precifica por pé quadrado — [Signs101: anyone using PrintSmith](https://www.signs101.com/threads/anyone-using-printsmith.169979)

### Inferences
- **Inferência:** o mínimo para uma "Nova venda" de comunicação visual competir com esses produtos:
  - (a) linha com L × A × qtd e unidade (m² e metro linear/perímetro);
  - (b) produto-modelo com materiais, mão de obra e máquina, cada um com custo e markup;
  - (c) modificadores com tipos booleano/numérico/lista/faixa, cada um somando valor fixo, por m², por unidade ou %;
  - (d) nível de preço do cliente aplicado automaticamente;
  - (e) desconto por faixa de quantidade;
  - (f) custo/markup/margem visíveis só internamente;
  - (g) override manual do preço com rastro.
- **Inferência:** desperdício/rendimento de rolo e chapa (Sign OS; o shopVOX tem um artigo "Material Wastage Calculator" que não foi aberto) e o alerta de margem abaixo do alvo são diferenciais recentes, relevantes para o Brasil, onde lona e adesivo saem de bobinas de larguras fixas.
- **Inferência:** a instalação é tratada como **linha de produto** (horas × taxa) e não como campo separado (shopVOX). Isso facilita custo e margem, mas não cobre deslocamento ou equipamento (andaime, munck), que nenhuma fonte aberta detalha.

### Gaps
- Não abri a documentação de faixas de quantidade ("Volume Pricing") nem a de "Grid Pricing" do shopVOX. Também não abri "Product Pricing: Material Wastage Calculator", que apareceu só como título em resultado de busca.
- A documentação de suporte do CoreBridge (KB) não foi encontrada publicamente. Detalhes de fórmula, faixa de quantidade e exibição de margem na tela vêm só de marketing ou de review.
- O CFL (Cyrious Formula Language) e a tela de entrada de pedido do Control não foram abertos (só o capítulo de visão geral de preço).
- PrintSmith Vision: não consegui ler o folheto PDF da EFI (arquivo comprimido). A falta de suporte a tamanhos/orientações mistos em uma mesma chapa veio de review no Capterra que não abri (403). Nada verificado sobre preço por área.
- Capterra/G2 bloquearam o acesso (HTTP 403). As notas e contagens de reviews (ex.: shopVOX 4,6/5) vieram só de snippets de busca e **não foram verificadas**.

## 2. Ciclo de vida do orçamento (alternativas, revisões, validade, envio, aprovação online/assinatura, sinal via portal, conversão)

### Takeaway
O padrão do mercado é este:
- enviar um **link de revisão online** (não um PDF solto);
- rastrear abertura e visualização;
- deixar o cliente **aprovar, pedir alterações ou recusar**, às vezes **por linha**;
- cobrar o **sinal na própria tela de aprovação**;
- converter em pedido/OS **com um clique, sem redigitar**.

O shopVOX tem o fluxo mais documentado: status, "Approve with Changes", edição após envio e múltiplas quantidades por linha. Validade automática e versionamento formal de orçamento quase não aparecem documentados. O Ordant e o Sign OS são os que falam explicitamente de validade e de política de aprovação antes do envio.

### Cited Findings

**shopVOX**
- Envio: "Download PDF" e "Email PDF" (contato existente ou novo, CC, template de e-mail).
- O botão de ação azul traz: To Sales Order, To Invoice, **Create Copy**, Sales Commissions, **Send Review Email**, Attach Sales Lead, **Apply Discount Code**, Attach to Project; PDFs (Quote Order, **No Total**, **Work Order**, **Down payment Invoice**, **Bill of Materials**); Quote - Export to XLS; Void — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote)
- Aprovação:
  - "Send Review Email" (diferente de "Email PDF");
  - o sistema rastreia quando o cliente visualiza e se aprova ou pede mudanças;
  - o cliente clica em "Approve" e "reaches a screen to pay for the order";
  - o status vira Approved, com alerta configurável;
  - a aprovação manual fica em "Reviews" (ícone de polegar) — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote)
- Status do orçamento:
  - Draft; Delivered (automático no Email PDF); Ordered (automático ao converter); Lost;
  - Hold (põe em espera também os jobs ligados);
  - Pending, Internally Approved, No Charge, No Charge Approved;
  - **Expired (manual; sem expiração automática descrita)**.
  - Status de revisão pelo cliente: Customer Review, **Revise**, **Approve with Changes**, Approved — [Quote States](https://shopvox-pro.helpdocs.io/article/ffdzllzkww-quote-states)
- [jan/2025] Flag "Online Quote Review By Customer". Se o cliente ainda não revisou, dá para **editar o orçamento depois de enviado** e o cliente vê o preço novo no mesmo link — [Quote Review and online approval](https://shopvox-pro.helpdocs.io/article/l98d79i5hy-fri-01-24-2025-highlighted-feature-quote-review-and-online-approval-pro)
- **Múltiplas quantidades na mesma linha**:
  - "+Add New Quantity" (ex.: 1, 25, 50, 75, 100), com % de desconto por faixa;
  - o PDF mostra o preço unitário de cada quantidade;
  - na conversão, o vendedor escolhe a quantidade que vira a "final line item" do pedido.
  - O artigo não fala de alternativas separadas — [Multiple Quantities](https://shopvox-pro.helpdocs.io/article/ypey7v4668-sending-quotes-with-multiple-quanitites-on-a-line-item)
- Ciclo completo: Quote → "Send review" → Sales Order (centro financeiro: pagamentos/sinais, jobs, POs) → Jobs/Proofs → Invoice → "Post to Accounting" — [Quote to Invoice Cheat Sheet](https://shopvox-pro.helpdocs.io/article/x92fvfaaaa-shop-vox-pro-order-to-production-cheat-sheet)
- Se o cliente já aprovou o preço, dá para pular o orçamento e abrir direto um New Sales Order — [How to Create a Quote](https://shopvox-pro.helpdocs.io/article/q3j7kil5u1-how-to-create-a-quote)
- A página de vendas promete "convert it to a work order with one click" — [shopVOX Sign Shop Software](https://shopvox.com/sign-shop-software/)

**CoreBridge**
- Portal com marca própria mostra estimates, orders e payments.
- O cliente pode **aprovar ou pedir alterações em cada linha**.
- Saldo e pagamentos visíveis ao cliente — [Estimating Intelligence](https://www.corebridge.net/estimating-intelligence)
- "One-click estimate → invoice conversion"; estimates aprovados viram pedido no mesmo registro — [Feature overview](https://www.corebridge.net/feature-overview); [CoreBridge home](https://www.corebridge.net/)
- "Estimate variations" (alternativas no mesmo orçamento — **inferência pelo nome**) só a partir do Plus. Portal do cliente a partir do Small Business; "Reorder from portal" a partir do Plus — [CoreBridge Pricing](https://www.corebridge.net/pricing)
- [2017, desatualizado] Um usuário desativou o portal por achá-lo "underdeveloped" — [Signs101: corebridge pos software](https://www.signs101.com/threads/corebridge-pos-software.138577)
- [2021] No portal, os contatos de cobrança veem faturas e pagam online (só cartão, na época) — [IRgA Cyrious Report](https://www.irga.com/cyrious-report/)

**Cyrious Control**
- "converts an estimate into a work order and then an invoice" sem redigitar — [SourceForge: Cyrious](https://sourceforge.net/software/product/Cyrious/)

**Ordant**
- "Set estimate expiration dates"; "Set up approval processes for each client".
- Limite de pedido online conforme o histórico de crédito do cliente.
- Rastreia "which estimates are accepted and rejected and why" e a taxa de aceitação por cliente.
- Clientes ou CSRs autorizados podem criar estimates sozinhos — [Ordant Estimating Module](https://www.ordant.com/module/best-print-estimating-software/)
- Email Order Approval:
  - estimate enviado por e-mail com a marca da gráfica;
  - o cliente aprova **aceitando os termos e condições** (sem e-signature descrita);
  - cada linha vai automaticamente para produção e a equipe é notificada;
  - o cliente recebe e-mail confirmando o que foi pedido — [Ordant Email Order Approval](https://www.ordant.com/module/print-order-approval-automation/)
- "Convert estimates to invoices as each line item is completed" — [Ordant Sign Shop Software](https://www.ordant.com/sign-shop-software/)

**Sign OS**
- PDF com marca enviado do domínio de e-mail da própria loja.
- O cliente aprova online e pode recusar na mesma página.
- **"Approval policies can gate quote sends"** (aprovação interna antes do envio).
- Rascunho de orçamento gerado a partir de e-mail de pedido colado.
- Orçamento aceito vira job "in one click, with prices frozen".
- Job pode nascer sem orçamento; job ganho pode ser repetido como novo orçamento — [Sign OS](https://signos.com.au/)

**DecoNetwork**
- "Professional quote link": o cliente revisa, aprova ou pede mudanças no navegador.
- Lembretes automáticos para o cliente aprovar.
- Status ao vivo: pending, approved, rejected, converted.
- Vendedores e gerentes comentam no mesmo orçamento.
- Aprovado, vira pedido de produção com produto, preço e arte transportados — [DecoNetwork Quotes](https://www.deconetwork.com/home/features/quotes/)

**EstiMate Desktop**
- "Customizable status flags" para as etapas do estimate; "Conversion of estimates to jobs" — [EstiMate features](https://estimatesoftware.com/features)

**SignTracker [2007]**
- A estimativa alimenta o "quote and contract" (proposta + contrato) — [Signs of the Times: SignTracker](https://signsofthetimes.com/signtracker/)

### Inferences
- **Inferência:** casos de uso obrigatórios para a "Nova venda":
  - salvar como orçamento ou abrir direto como pedido;
  - enviar link de aprovação (e-mail/WhatsApp, no lugar do SMS dos gringos) com rastreio de abertura;
  - aprovar, pedir alteração ou recusar (de preferência por item);
  - status com motivo de perda;
  - conversão em pedido/OS sem redigitar, **congelando preços**.
- **Inferência:** "múltiplas quantidades por linha" (shopVOX) e "estimate variations" (CoreBridge) mostram que o cliente compara opções. Para comunicação visual no Brasil, isso pede **opções por item** (ex.: lona 440 g × 280 g; com ou sem instalação) e **faixas de quantidade** no mesmo orçamento.
- **Inferência:** validade automática com expiração e versionamento formal (v1, v2) são pouco documentados no mercado (o shopVOX só tem "Expired" manual). Isso é oportunidade de diferenciação de baixo custo.
- **Inferência:** a aprovação interna antes do envio (Sign OS "approval policies"; shopVOX "Internally Approved") serve para proteger margem e desconto quando vendedores júnior orçam.

### Gaps
- Não encontrei documentação de **assinatura eletrônica** propriamente dita (desenhada) na aprovação de **orçamento** em nenhum sistema. O Sign OS tem assinatura por dedo ou mouse, mas é na **prova de arte** (seção 3). O Ordant usa aceite de termos.
- SMS como canal de envio não apareceu nas páginas abertas.
- O comportamento exato de "Estimate variations" no CoreBridge não foi documentado (só o nome no quadro de planos).

## 3. Arte e provas (upload, aprovação de prova pelo cliente, ligação com produção)

### Takeaway
A prova fica **ligada à linha do item / job**, não ao pedido inteiro. Ela é versionada (V1, V2…), enviada por link e aprovada ou recusada com comentários. A aprovação **libera a etapa de produção**. Sign OS e Ordant acrescentam assinatura na prova, múltiplos aprovadores e lembretes. Arquivos do cliente também entram pela própria venda ou por um portal.

### Cited Findings
- **shopVOX: anexos e notas**
  - "+ Add New Asset" anexa arquivos à linha, com ações Download, **Calculate Print Size**, Edit e Delete.
  - Notas com @menção — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote)
- **shopVOX: fluxo da prova**
  - Prova por job/linha: "Add Proof" → "Send V1 For Review". Para várias linhas, "Send Review Email" no Sales Order manda todas num e-mail só.
  - O cliente dá zoom, baixa, aprova ou pede mudanças com comentários.
  - Selo "approved", histórico com data/hora e versões ("Version 1" dropdown).
  - O Job Board mostra "Current Proof Status", "Proof Version" e "Approved Proof At". Aprovada a prova, o job avança de etapa — [Uploading and Sending Proofs](https://shopvox-pro.helpdocs.io/article/1b28ummawc-uploading-and-sending-proofs-for-approval)
  - O índice tem um artigo sobre anexar prova já no novo Sales Order ("Streamlining Proof Attachment to New Sales Orders"; título apenas) — [shopVOX Help Center](https://shopvox-pro.helpdocs.io/)
- **CoreBridge**
  - "Proofs attach directly to line items"; dá para postar a prova ao cliente ou aprovar internamente sem postar.
  - Notas de revisão ficam no job — [Estimating Intelligence](https://www.corebridge.net/estimating-intelligence)
  - Limite de provas por plano: Starter "1 proof", Small Business "2 proofs", Plus+ ilimitado. O significado exato do limite não foi explicado — [CoreBridge Pricing](https://www.corebridge.net/pricing)
  - [2017] Provas em JPEG/PNG/BMP/PDF ficam "on the system forever"; arquivos de design grandes (EPS/AI) exigem armazenamento pago — [Signs101: corebridge pos software](https://www.signs101.com/threads/corebridge-pos-software.138577)
- **Ordant**
  - Status de cada prova por linha num pedido multi-item; e-mail para todos os aprovadores com data-limite de aprovação.
  - O cliente aprova ou recusa uma a uma ou em lote; revisões viram novas versões — [Ordant Print Proofing](https://www.ordant.com/module/print-proofing-software/)
  - Lembretes automáticos quando falta alguém aprovar; preflight automático dos arquivos do cliente; envio a hot folders por equipamento.
  - Logos e ativos do cliente ficam guardados na conta para trabalhos futuros — [Ordant Sign Shop Software](https://www.ordant.com/sign-shop-software/)
- **Sign OS**
  - Provas versionadas com aceite **por página**, assinatura "by finger or mouse".
  - "Approved proofs gate production"; a aprovação pode ser retirada.
  - Registro de quando a prova foi enviada e aberta; o cliente pode subir arte direto no job — [Sign OS](https://signos.com.au/)
- **DecoNetwork**: detalhes da arte entram no orçamento e passam para o pedido. Aprovação de arte é uma feature separada ("Artwork Approvals") — [DecoNetwork Quotes](https://www.deconetwork.com/home/features/quotes/)
- **EstiMate Desktop**: imagens de prova com marca d'água customizável — [EstiMate features](https://estimatesoftware.com/features)
- **SignTracker [2007]**: documentos de "art requests" e aprovação de desenho no checklist do job — [Signs of the Times: SignTracker](https://signsofthetimes.com/signtracker/)

### Inferences
- **Inferência:** na tela de venda, o mínimo é: anexar arquivo/arte **por item**; marcar "precisa de prova"; e, depois da conversão, um fluxo de prova versionada com link de aprovação que **bloqueia a liberação para produção** até aprovar. Isso é o que shopVOX, CoreBridge e Sign OS fazem.
- **Inferência:** "Calculate Print Size" a partir do arquivo (shopVOX) sugere um caso de uso útil: pegar L × A do arquivo de arte e jogar na linha do orçamento.

### Gaps
- Não verifiquei se alguma ferramenta faz a marcação gráfica (anotação desenhada sobre a prova) dentro do próprio sistema. O Ordant diz que revisores "annotate", sem detalhar a ferramenta.

## 4. Produção e instalação (prazos, etapas, vistoria técnica, agendamento, instalador, fotos, expedição)

### Takeaway
Da linha do pedido nascem **jobs** com um workflow de etapas (template por produto). A instalação vira uma etapa com endereço, data (mesmo tentativa), instalador e calendário (shopVOX). Vistoria e instalação são agendáveis, com app de campo para fotos e aceite no local (shopVOX GO). O Sign OS manda a instalação para terceirizados via portal e cuida de docket de entrega e retirada.

### Cited Findings
- **shopVOX: na tela de venda**
  - "Additional Info" por linha guarda notas Internas, de **Production, Design, Install, Shipping Details e Tracking**.
  - Tasks com responsável, data, início/fim e lembrete.
  - "Hold" no orçamento também segura os jobs — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote); [Quote States](https://shopvox-pro.helpdocs.io/article/ffdzllzkww-quote-states)
- **shopVOX: Installation Job Board**
  - Usuários marcados como "Installer"; etapa Install no workflow.
  - "Create Job" a partir da linha do Sales Order, com endereço e detalhes da instalação e data ("even a tentative date is helpful").
  - Instalador atribuído; calendário dia/semana/2 semanas/mês/ano; visões Users e Machines.
  - Data confirmada (cor sólida) × não confirmada (listrada); ícone de mapa abre o Google Maps — [Installation Job Board](https://shopvox-pro.helpdocs.io/article/ryx40qddgr-installation-job-board-shop-vox-pro)
- **shopVOX: página de vendas**
  - Calendário para "site surveys and installs" (Pro).
  - App shopVOX GO para fotos e aceite do cliente no local.
  - "Visual Production Dashboard"; tarefas e alertas de prazo; smartWrap (medidas e templates de envelopamento veicular) — [shopVOX Sign Shop Software](https://shopvox.com/sign-shop-software/)
- **shopVOX: outros**
  - Jobs vêm das linhas do Sales Order; boards em lista, card ou calendário — [Cheat Sheet](https://shopvox-pro.helpdocs.io/article/x92fvfaaaa-shop-vox-pro-order-to-production-cheat-sheet)
  - Existe add-on "Service Job" (título no índice) — [shopVOX Help Center](https://shopvox-pro.helpdocs.io/)
- **CoreBridge**
  - Job boards visuais de Pre-WIP até Built; atualizações de status e marcos com **fotos**; notificação automática ao cliente nos marcos.
  - Instalação não tem módulo próprio: calendários Outlook/Google sincronizam jobs, **installs** e prazos — [CoreBridge home](https://www.corebridge.net/); [Feature overview](https://www.corebridge.net/feature-overview)
  - Job time tracking a partir do Plus; job costing (estimado × real) no Professional — [CoreBridge Pricing](https://www.corebridge.net/pricing)
- **Cyrious Control**: lista de peças e estoque gerados ao criar o pedido "helps set realistic due dates"; dashboards de status e prazos — [SourceForge: Cyrious](https://sourceforge.net/software/product/Cyrious/)
- **Ordant**
  - Acompanha cada job por preflight, aprovação de prova, **change orders**, expedição e faturamento.
  - FedEx/UPS; cálculo de frete, impostos e seguro para um ou vários endereços — [Ordant Sign Shop Software](https://www.ordant.com/sign-shop-software/)
  - Resource Planner (coleta de dados de chão de fábrica); automação de workflow — [Ordant home](https://www.ordant.com/)
  - A página de sign shop cita vistoria e instalação só como tipos de trabalho, não como features — [Ordant Sign Shop Software](https://www.ordant.com/sign-shop-software/)
- **Sign OS**
  - Timeline de produção por departamento com **alertas de capacidade**; QR code para apontar tempo em tablets — [Slashdot: Sign OS](https://slashdot.org/software/p/Sign-OS/)
  - "Outbound board" para retiradas, entregas e instalações; dockets de entrega assinados pelo motorista; status "Ready for pick-up" e "Picked up" com lembrete automático.
  - Aba de jobs terceirizados; job de **retrabalho** vinculado ao job original — [Sign OS](https://signos.com.au/)
  - Instalação despachada a terceirizados via "contractor portal" — [Slashdot: Sign OS](https://slashdot.org/software/p/Sign-OS/)
- **SignTracker [2007]**: checklist do job "from site survey through permits and drawing approvals"; programação pelo tempo de fabricação de cada componente; relatório diário de quando iniciar fabricação, instalação ou envio — [Signs of the Times: SignTracker](https://signsofthetimes.com/signtracker/)

### Inferences
- **Inferência:** campos que a "Nova venda" deve capturar já no orçamento: prazo de entrega prometido; **tipo de entrega** (retirada / entrega / instalação); **endereço da instalação** (diferente do endereço do cliente); necessidade de **vistoria técnica** (visita de medição) antes de fechar o preço; e observações separadas por destino (produção, arte, instalação, expedição). shopVOX e Sign OS separam isso explicitamente.
- **Inferência:** a vistoria técnica muitas vezes acontece **antes** do orçamento final. O shopVOX trata a vistoria como evento de calendário, não como um tipo de documento. Um caso de uso "agendar vistoria a partir do orçamento" seria diferencial.

### Gaps
- Nenhuma fonte aberta detalha fotos de instalação com geolocalização ou checklist de instalação, além da menção ao shopVOX GO e às fotos de marco do CoreBridge.

## 5. Dinheiro (sinal, pagamentos parciais, condições, impostos, comissões, descontos e regras de aprovação)

### Takeaway
O sinal é de primeira classe: **% ou valor fixo**, cobrado na tela de aprovação online, com fatura de sinal e job em "Aguardando sinal" até o pagamento (Sign OS). O pedido funciona como "hub financeiro" que recebe pagamentos antes da fatura final (shopVOX). Comissão é calculada **por linha**, sobre preço, custo ou lucro. Desconto vem por código promocional, por nível de cliente ou por faixa de quantidade. Imposto é por linha, com override de tributável e Avalara.

### Cited Findings
- **shopVOX: sinal e pagamentos**
  - [fev/2025] O sinal ("down payment") pode ser % **ou valor em dólar** — [Down payment dollar amount](https://shopvox-pro.helpdocs.io/article/3xit1agaux-thu-02-27-2025-update-down-payment-can-now-be-a-dollar-amount-pro-express)
  - O orçamento gera o PDF "Down payment Invoice". O cliente, ao aprovar online, cai numa tela de pagamento — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote)
  - O Sales Order recebe pagamentos/sinais e envia PDF de fatura sem converter; a fatura final só sai com a produção concluída — [Cheat Sheet](https://shopvox-pro.helpdocs.io/article/x92fvfaaaa-shop-vox-pro-order-to-production-cheat-sheet)
  - Títulos no índice: "How to create Partial Invoices", "shopVOX Pay", "Add your Payment Gateway", "Avalara AvaTax", "Allow Line Item Taxable Override" — [shopVOX Help Center](https://shopvox-pro.helpdocs.io/)
  - Pagamento online via Stripe, Square, QuickBooks Payments e Authorize.net; maquininha (POS) presencial; sincronização com QuickBooks Online/Xero — [shopVOX Sign Shop Software](https://shopvox.com/sign-shop-software/)
- **shopVOX: impostos, descontos e comissões**
  - Ícone "T" mostra os impostos da linha; "Apply Discount Code" no orçamento — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote)
  - Comissão:
    - taxa por vendedor (In-House / Outsourced) ou por produto (a do produto prevalece);
    - base **Price, Cost ou Profit** (ex.: 10% → US$ 10 / 5 / 2,50);
    - "always calculated per line item and never per invoice total";
    - o vendedor deve ser escolhido antes de adicionar itens — [Sales Commissions](https://shopvox-pro.helpdocs.io/article/7q20136doe-sales-commission-in-progress)
- **CoreBridge**
  - Pagamento por cartão e ACH em todos os planos; **progressive billing** (faturamento por etapas) só no Professional (US$ 899/mês) — [CoreBridge Pricing](https://www.corebridge.net/pricing)
  - Lembretes automáticos de pagamento e recibos; Stripe e Fiserv — [CoreBridge home](https://www.corebridge.net/)
  - Faturas "Approve, bill, and collect payments automatically" — [Feature overview](https://www.corebridge.net/feature-overview)
  - Não encontrei menção explícita a sinal/depósito nas páginas abertas.
- **Cyrious Control**: Pricing Levels por cliente **ou funcionário**, Discount Tables, Pricing Families (desconto agregado) e Promotions aplicadas a pedidos/orçamentos — [Cyrious Wiki](https://control.cyriouswiki.com/pricing_ch_02-cyrious_and_pricing)
- **Ordant**
  - "Request deposits before large projects"; limite de pedido online pelo histórico de crédito — [Ordant Estimating Module](https://www.ordant.com/module/best-print-estimating-software/)
  - Vencimento e condições da fatura conforme o histórico de pagamento do cliente; pagamento por link, telefone ou site; visão diária de recebidos, em aberto e vencidos — [Ordant Order Management](https://www.ordant.com/module/print-estimating-order-management/)
- **Sign OS**
  - Aceitar o orçamento pode disparar uma **fatura de sinal**; a confirmação do pedido pode esperar o sinal; o job mostra "Waiting for deposit".
  - Condições do cliente (ex.: 50% de sinal) aparecem no job; cartão via Stripe.
  - Permissões por papel ("floor staff never see dollar figures").
  - "Approval policies can gate quote sends" — [Sign OS](https://signos.com.au/)
  - Faturas de sinal e final com sync bidirecional com Xero — [Slashdot: Sign OS](https://slashdot.org/software/p/Sign-OS/)
- **SignTracker [2007]**: "Accounting Tracker" acompanha contratos vendidos por pagamentos, encerramento e **comissões** — [Signs of the Times: SignTracker](https://signsofthetimes.com/signtracker/)

### Inferences
- **Inferência:** casos de uso financeiros essenciais na "Nova venda":
  - condição de pagamento do cliente sugerida automaticamente (ex.: 50% de sinal + 50% na entrega);
  - sinal em % ou R$ com geração de cobrança (Pix/boleto, no lugar de Stripe);
  - status "aguardando sinal" bloqueando a produção (opcional);
  - vendedor obrigatório **antes** dos itens, para a comissão por linha;
  - desconto com **teto por perfil e aprovação gerencial**;
  - imposto por item (no Brasil, a tributação ISS × ICMS por item/serviço pesa mais — instalação é serviço).
- **Inferência:** regras formais de aprovação de desconto aparecem só indiretamente: Sign OS (approval policies), shopVOX (status "Internally Approved") e Ordant (processo de aprovação por cliente). Nenhuma fonte aberta descreve um "teto de desconto por vendedor" explícito.

### Gaps
- Não encontrei documentação de regra de alçada de desconto (ex.: desconto acima de X% exige gerente) em nenhum sistema.
- O CoreBridge não documenta publicamente como o sinal (deposit) é cobrado no estimate.

## 6. Outros casos de uso na tela (template/duplicar, histórico do cliente, CRM, estoque, compra de material)

### Takeaway
A tela de venda internacional é um "hub" que também:
- duplica orçamentos e jobs anteriores (recompra);
- liga a venda a lead/projeto;
- cria tarefas e notas com @menção;
- gera **lista de materiais (BOM)**, **pedido de compra** e **RFQ a fornecedor/terceirizado** direto da venda.

IA já aparece para montar produto/modificador (CoreBridge), redigir orçamento a partir de e-mail e alertar margem (Sign OS).

### Cited Findings
- **shopVOX**
  - "Create Copy", "Attach Sales Lead", "Attach to Project", PDF "Bill of Materials", export para XLS e Tasks/Notes com @ na tela do orçamento — [Transactions: New Quote](https://shopvox-pro.helpdocs.io/article/avhotlqqzj-transactions-new-quote)
  - Do Sales Order: criar PO ou mandar itens para "Needs Purchasing" (vários pedidos consolidam numa PO); recebimento e QC — [Cheat Sheet](https://shopvox-pro.helpdocs.io/article/x92fvfaaaa-shop-vox-pro-order-to-production-cheat-sheet)
  - "Past job specs are saved, so reorders take just a few clicks" — [shopVOX Sign Shop Software](https://shopvox.com/sign-shop-software/)
  - Add-ons: Inventory, Divisions, Vehicle Wrap, Ecommerce; cPortal gratuito (títulos no índice) — [shopVOX Help Center](https://shopvox-pro.helpdocs.io/)
- **CoreBridge**
  - CRM com histórico completo de orçamentos e jobs e notas por conta.
  - AI Assistant para "Create and update records instantly" e tarefas de orçamento/preço — [Feature overview](https://www.corebridge.net/feature-overview)
  - PO para fornecedor (Small Business+); **Request for quote** (Plus+); estoque (Professional+); AI product and modifier builder (Plus+) — [CoreBridge Pricing](https://www.corebridge.net/pricing)
  - [2021] Estoque fraco: POs sem recebimento no estoque e sem baixa automática de material usado — [IRgA Cyrious Report](https://www.irga.com/cyrious-report/)
  - Concorrente [mai/2026] afirma que o CRM do CoreBridge não tem pipeline de vendas completo e que estoque e job costing ficam em planos superiores — [Striven vs CoreBridge (blog de concorrente)](https://www.striven.com/?p=77402)
- **Cyrious Control**: ao criar o pedido, gera automaticamente a lista de peças (BOM) e mostra o estoque — [SourceForge: Cyrious](https://sourceforge.net/software/product/Cyrious/)
- **Ordant**
  - Templates para itens recorrentes; RFQ para trabalhos terceirizados; ações de follow-up para a equipe de vendas; perfil do cliente com produtos que compra e taxa de aceitação — [Ordant Estimating Module](https://www.ordant.com/module/best-print-estimating-software/)
  - "While estimating a multi-item order, quickly get quotes from outsourcing partners"; campanhas e mensagens automáticas a clientes e prospects; alerta de estoque mínimo — [Ordant Sign Shop Software](https://www.ordant.com/sign-shop-software/)
  - Módulos de CRM, Purchase Order & Inventory, Multi-locations e QuickBooks — [Ordant home](https://www.ordant.com/)
- **Sign OS**
  - BOM gerada das receitas com **POs em rascunho para faltas**; repetir job ganho como novo orçamento.
  - Portais VIP com catálogo e preço acordado; loja online onde o cliente configura e precifica.
  - Assistente ReAct que age sob as permissões do usuário, com confirmação — [Sign OS](https://signos.com.au/)
  - POs específicas por job e gestão de estoque — [Slashdot: Sign OS](https://slashdot.org/software/p/Sign-OS/)
- **DecoNetwork**: produtos de catálogos de fornecedores dentro do orçamento — [DecoNetwork Quotes](https://www.deconetwork.com/home/features/quotes/)
- **SignTracker [2007]**: preço de fornecedor coletado por planilha Excel enviada aos fornecedores; mais de 40 documentos por projeto (vistoria, pedido de arte, controle de change orders) — [Signs of the Times: SignTracker](https://signsofthetimes.com/signtracker/)

### Inferences
- **Inferência:** para a "Nova venda" brasileira, os atalhos de maior valor são:
  - **duplicar venda anterior / recompra** a partir do histórico do cliente;
  - ver o histórico do cliente sem sair da tela;
  - conferir estoque e gerar compra/cotação de terceirizado (ex.: letra-caixa, ACM cortado fora) a partir do item.
  - Todos os 5 principais sistemas oferecem pelo menos um desses.

### Gaps
- Não ficou documentado se alguma ferramenta **checa estoque em tempo real na linha do orçamento** (o Cyrious gera a BOM e mostra o estoque no pedido; o Sign OS gera POs para faltas).

## 7. Recursos que os fornecedores destacam como razões de escolha (e o que usuários elogiam ou criticam)

### Takeaway
Os argumentos se repetem entre fornecedores:
- **preço consistente** (todo vendedor tira o mesmo preço);
- **orçamento em minutos**;
- **um registro só do orçamento ao recebimento**, sem redigitar;
- aprovação e pagamento online;
- visibilidade de margem.

As queixas recorrentes de usuários são setup de preço pesado, curva de aprendizado e lentidão para lançar trabalhos pequenos — o principal risco para uma tela "Nova venda".

### Cited Findings
- **shopVOX**
  - "Generate professional estimates in minutes"; conversão a OS com um clique; prova com um clique.
  - "most sign shops set up their shopVOX Express account in under an hour"; "Trusted by over 2,000 shops in 26 countries".
  - Preços: Express US$ 109/mês + US$ 19/usuário; Pro US$ 249/mês + US$ 49/usuário; onboarding a partir de US$ 499; trial de 14 dias — [shopVOX Sign Shop Software](https://shopvox.com/sign-shop-software/)
- **CoreBridge**
  - "One record from first quote to final payment"; promete "+26%" de margem, "2.5×" mais rapidez de orçamento a fatura e lucratividade em tempo real.
  - "70%+" das grandes redes de franquia de comunicação visual usariam o CoreBridge (alegação do fornecedor) — [CoreBridge home](https://www.corebridge.net/)
  - Benefícios citados: preço consistente na equipe, menos treinamento de orçamentista e menos erros de planilha — [Estimating Intelligence](https://www.corebridge.net/estimating-intelligence)
  - Planos: Starter US$ 129, Small Business US$ 339, Plus US$ 549, Professional US$ 899 (mensais), mais taxa de onboarding — [CoreBridge Pricing](https://www.corebridge.net/pricing)
  - **Conflito:** o blog da Striven diz que o Starter tem 3 usuários e que estoque/PO/job costing pedem o SMB de US$ 339 — [Striven](https://www.striven.com/?p=77402). A página oficial mostra 1 usuário padrão + 1 de produção no Starter, PO a partir do Small Business e estoque/job costing só no Professional — [CoreBridge Pricing](https://www.corebridge.net/pricing)
  - Usuários [2017]: setup de preço "very tedious" mas depois "a breeze"; "designed for companies that have strict roles"; para outro usuário, lançar uma OS simples "takes a lot of time".
  - Mesmo fórum [2017]: CoreBridge teria comprado a Cyrious em 2016, e o suporte ao Control "completely evaporated" (alegação de fórum, não verificada) — [Signs101: corebridge pos software](https://www.signs101.com/threads/corebridge-pos-software.138577); [Signs101: Shopvox vs Corebridge](https://www.signs101.com/threads/shopvox-vs-corebridge.142694)
  - [2021] Pontos fortes: flexibilidade de preço, suporte (resposta em ~30 min), pagamento online e integração contábil. Pontos fracos: estoque/compras e treinamento curto — [IRgA Cyrious Report](https://www.irga.com/cyrious-report/)
- **Cyrious Control**: orçamentos consistentes "regardless of who prepares them"; lucro estimado antes de enviar; estimate → OS → fatura sem redigitar — [SourceForge: Cyrious](https://sourceforge.net/software/product/Cyrious/)
- **Ordant**
  - Módulos integrados sem "silos"; "creating estimates takes only seconds" (depoimento).
  - Alegações de "$1 Billion +" em orçamentos e "575,000 +" pedidos processados — [Ordant home](https://www.ordant.com/); [Ordant Sign Shop Software](https://www.ordant.com/sign-shop-software/)
- **Sign OS**
  - "Built for sign work: sqm, lm, waste, yield, nesting"; um registro da consulta à fatura; permissões por papel; IA com aprovação.
  - A partir de AUD 89/mês, onboarding grátis, "30-day free trial" — [Sign OS](https://signos.com.au/)
  - **Conflito:** o Slashdot diz trial de 7 dias e fundação em 2026 (produto muito novo, sem reviews) — [Slashdot: Sign OS](https://slashdot.org/software/p/Sign-OS/)
  - O próprio site admite limitações: MYOB desligado; Sign OS Sync em beta (sem Mac) — [Sign OS](https://signos.com.au/)
- **PrintSmith Vision**: [2022] wide-format considerado contraintuitivo por usar matrizes de corte/offset — [Signs101: anyone using PrintSmith](https://www.signs101.com/threads/anyone-using-printsmith.169979)

### Inferences
- **Inferência: matriz consolidada de casos de uso da tela de orçamento/pedido** (✔ = documentado em página aberta; ~ = parcial/indireto; — = não encontrado). Sistemas: shopVOX | CoreBridge | Cyrious Control | Ordant | Sign OS
  - Preço por área (L×A×qtd): ✔ | ✔ | ✔ (Unit Type área) | — (não descrito) | ✔ (m² com faixas)
  - Metro linear/perímetro: ✔ (Perimeter) | — | ✔ (length) | — | ✔
  - Modificadores/acabamentos: ✔ | ✔ (ilhós, bainha) | ✔ | ~ | ✔ (+ regras de combinação)
  - Nível de preço por cliente: ✔ | ✔ (8 níveis, 2021) | ✔ | ~ (perfil/crédito) | ✔ (portal VIP com preço acordado)
  - Desconto por quantidade: ✔ (múltiplas qtds com % por faixa) | ✔ (2021) | ✔ (Discount Tables) | — | ✔
  - Custo/markup/margem visível: ✔ | ✔ (BI; tela não verificada) | ✔ (lucro estimado) | ✔ (margem-alvo) | ✔ (alerta de margem por IA)
  - Override manual de preço: ~ (Product Pricing) | — | — | — | ✔
  - Alternativas/opções no orçamento: ~ (multi-qtd) | ~ ("Estimate variations") | — | ~ (comparar processos) | —
  - Validade/expiração: ~ (status manual) | — | — | ✔ | —
  - Aprovação online do cliente: ✔ | ✔ (por linha, portal) | — | ✔ (aceite de termos) | ✔
  - Pagamento/sinal na aprovação: ✔ | ~ (pagamentos no portal) | — | ✔ (pede depósito) | ✔ (fatura de sinal; bloqueio)
  - Conversão orçamento → pedido/OS sem redigitar: ✔ | ✔ | ✔ | ✔ | ✔ (preços congelados)
  - Prova versionada ligada à linha: ✔ | ✔ | — | ✔ | ✔ (assinatura; gate de produção)
  - Instalação/agenda/instalador: ✔ | ~ (calendário externo) | — | — | ✔ (portal de terceirizados)
  - Comissão por linha: ✔ | — | — | — | — (o SignTracker [2007] tinha comissões no total do projeto)
  - BOM/compra a partir da venda: ✔ | ✔ (PO/RFQ por plano) | ✔ | ✔ (RFQ) | ✔ (PO de faltas)
  - Duplicar/recompra: ✔ | ✔ (portal reorder) | — | ✔ (templates) | ✔
- **Inferência:** o maior risco para a "Nova venda" é a **velocidade para trabalhos pequenos**. As críticas ao CoreBridge (2017) e ao PrintSmith mostram que motor de preço poderoso mais setup pesado gera rejeição. Recomenda-se ter um modo rápido (item avulso com preço manual, como o "Custom Line Item" do shopVOX e o "Custom one-off products" do Sign OS) ao lado do modo produto-modelo.

### Gaps
- Reviews do Capterra, G2 e GetApp não puderam ser abertos (HTTP 403). Avaliações agregadas recentes (2025–2026) de shopVOX e CoreBridge ficaram sem verificação.
- A relação societária Cyrious → CoreBridge (compra em 2016) e o status atual do Cyrious Control em 2026 não foram confirmados por fonte primária. O wiki do Control continua no ar.
- DecoNetwork: um artigo de ajuda que recomendaria "Business Hub quotes" para signage apareceu só em snippet (página 403). O DecoNetwork não tem módulo específico de comunicação visual documentado nas páginas abertas.
- Printavo não foi pesquisado a fundo, por ser focado em vestuário e serigrafia, sem evidência de recursos de signage.
- SignTracker: a única fonte é de 2007. Não sei se o produto ainda existe em 2026.
