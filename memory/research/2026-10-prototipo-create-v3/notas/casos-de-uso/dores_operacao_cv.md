# Como a comunicação visual vende (orçar, fechar, cobrar, produzir, instalar) e dores com software de orçamento/pedido

Notas de pesquisa (pt-BR) para o time de produto definir os casos de uso da tela "Nova venda". Pesquisa feita em 20/10/2026.

**Limitações de fonte:** Capterra (shopVOX/CoreBridge), G2, Software Advice e Reclame Aqui devolveram HTTP 403, então não abri as páginas de review. Nada que só apareceu em snippet de busca entra como achado: está na seção Gaps, marcado como "não verificado". Dois PDFs de compras públicas (Unicamp e Prefeitura de Santa Cruz do Rio Pardo) baixaram, mas não deu para extrair o texto. Há pouco conteúdo brasileiro com a voz do dono de comunicação visual (CV) que seja indexável e abrível. Boa parte da evidência de "dor do dono" vem de fóruns americanos (Signs101) e de material de fornecedor brasileiro, e isso está sinalizado em cada item.

---

## 1. Como é uma venda típica (pedido → medição → orçamento com opções → aprovação → sinal → arte/prova → produção → instalação → entrega → saldo) e onde dá errado

### Takeaway
O fluxo real é de várias etapas e vários canais. O pedido chega por WhatsApp, telefone, e-mail, balcão ou texto. A proposta pode ter várias opções. Há uma aprovação comercial e outra aprovação de arte, com sinal antes da arte ou da produção, e a instalação pode ser incluída ou não. Os pontos de falha mais citados são: pedidos que se perdem entre canais, demora na resposta do orçamento, aprovação de arte travando a produção, arte entregue antes do sinal (o cliente leva para o concorrente), a confusão "instalado ou só fabricado" e as aprovações externas (prefeitura, locador) atrasando a obra.

### Cited Findings
**Fluxo descrito por uma empresa brasileira de CV (Prolife, Ourinhos-SP, publicado em 02/02/2026)**
- As etapas são: (1) briefing e diagnóstico, para levantar objetivos, público, contexto, referências, prazos e limite de orçamento; (2) proposta com "opções de pacotes (Básico / Profissional / Premium)"; (3) criação e aprovação, com "provas virtuais e revisões guiadas"; (4) produção com controle de qualidade de cor, corte e acabamento; (5) logística e aplicação "quando aplicável", em formato turnkey; (6) pós-entrega e garantia. A proposta traz um "prazo estimado", e as amostras digitais são prometidas "em até 48 horas úteis". A página não fala de sinal nem tem uma etapa formal de visita técnica ou medição — [Solutudo/Prolife](https://www.solutudo.com.br/sp/ourinhos/noticias/prolife-comunicacao-visual/48143/detalhes/do-planejamento-a-entrega-como-funciona-um-projeto-visual-completo/95155)

**Dores no atendimento por WhatsApp e e-mail (conteúdo de fornecedor, SocialHub, 08/04/2026, sem dados quantitativos)**
- O texto fala em orçamentos perdidos porque "o cliente desiste de esperar o e-mail" e em aprovação de arte que "leva dias, travando toda a produção". Cita também "ligações intermináveis sobre o status de um pedido" ocupando a equipe de vendas. O fluxo sugerido é: um chatbot coleta tipo de material, quantidade e prazo e recebe os arquivos, depois encaminha para vendas; o mockup é aprovado pelo WhatsApp; o status sai automático (na fila, em produção, pronto para retirada ou envio). É material de marketing, sem métrica — [SocialHub](https://www.socialhub.pro/blog/whatsapp-business-para-grafica-comunicacao-visual/)

**Pedidos chegando por vários canais sem visão única (EUA)**
- Uma loja (ADVANCED DISPLAY, 14/07/2025) diz estar "uneasy" por não ter uma visão confiável dos pedidos em aberto, que chegam por telefone, e-mail, balcão e texto. Uma tentativa anterior com o SignTracker foi cancelada porque nunca separaram tempo para treinar a equipe — [Signs101, alternativas ao shopVOX, p.2](https://www.signs101.com/threads/good-alternates-to-shopvox-shop-management-software.177150/page-2)
- Os controles reais relatados em 2023 são um quadro branco "not updated often enough", ímãs num arquivo de aço, uma pilha de papéis na mesa e o Trello como quadro de jobs. Uma usuária do ShopTracker descreve colunas que vão de "Opportunity" até "Close Out" e "Archive", e imprime listas de tarefas por pessoa — [Signs101, estimate and accounting software (ago/2023)](https://www.signs101.com/threads/estimate-and-accounting-software.173613/)

**Arte antes do sinal, cliente fazendo cotação no concorrente (EUA, out/2013, conhecimento consolidado)**
- O autor do tópico ("fresh") perdeu dois jobs grandes depois de mandar esboços. Agora não entrega nada sem depósito. Outros membros: "NEVER give them anything, even printouts, unless THEIR signature is on YOUR copy" (J Hill Designs) e "NEVER let finished art out without payment" (Mosh). Há quem recomende cobrar uma taxa de design de prospects novos ou "caçadores de orçamento" e fazer layout de graça só para clientes recorrentes. Também alertam para não virar "control quote" gratuito de concorrente — [Signs101, "Big Jobs - How do you sell them?"](https://signs101.com/threads/big-jobs-how-do-you-sell-them.110833/post-1139947)
- No mesmo tópico: o orçamento leva "1-2 hours max" (Rick); um membro cotou vários tamanhos e tipos de instalação a partir de tabelas de preço (Baz); outro cotou o mesmo produto em quantidades diferentes, 25 e 10 unidades (Gino); e um terceiro recolheu cheque de sinal e arquivos de arte antes de começar. O autor confirmou a legislação de placas com o fiscal de zoneamento antes de fechar — [Signs101](https://signs101.com/threads/big-jobs-how-do-you-sell-them.110833/post-1139947)

**Sinal e cobrança por marcos (EUA, guia de bookkeeping, página sem data e URL datada de 03/10/2026)**
- "Standard practice is a 50% deposit to book the job, with the balance due on completion". Em jobs de vários dias, o guia propõe faturar por marcos: sinal na reserva, uma parcela quando "layout is approved and paint hits the wall" e o saldo na vistoria final. Atrasos por clima ou indecisão do cliente vão para um change order — [Beancount.io](https://beancount.io/blog/2026/10/03/sign-painter-hand-lettering-bookkeeping-pricing-gold-leaf-guide)

**Aprovação digital não é aceite físico**
- Uma aprovação digital confirma composição e conteúdo, mas não cor, espessura, aderência nem durabilidade. Por isso podem ser exigidas amostras físicas (contexto de licitação, página atualizada em 01/09/2026) — [Effecti](https://effecti.com.br/?p=11367)

**Aprovações externas (fachada/rede)**
- Licença municipal e aprovação do locador ("Landlord approvals follow their own timelines") costumam ser o maior gargalo. "Existing sign structures may or may not support the new brand standards" — [Principle Global](https://www.principleglobal.com/en/insights/scaling-brand-change-managing-100-site-programs)

### Inferences
- *(Inferência)* O fluxo tem **duas aprovações distintas**: a comercial (aceite do orçamento ou da opção) e a da arte (prova, com versões). Ambas precisam de registro de quem aprovou, quando e qual versão. Hoje esse rastro se perde no WhatsApp ou no e-mail, segundo SocialHub e Signs101.
- *(Inferência)* **Sinal antes da arte final ou da produção** é uma prática defensiva recorrente. A tela de venda deveria deixar explícito o "gatilho" de cada parcela: sinal na aprovação, parcela intermediária na aprovação da arte e saldo na instalação ou entrega.
- *(Inferência)* Como os pedidos entram por vários canais, a "Nova venda" precisa nascer rápido a partir de um contato de WhatsApp ou balcão, com o mínimo de campos, e virar rascunho ou orçamento sem obrigar o cadastro completo.
- *(Inferência)* Instalação e endereço de instalação (diferente do de cobrança), data agendada e necessidade de licença ou aprovação do locador afetam prazo e risco. Por isso devem ser campos da venda, não observação livre.
- **Must-have (inferência):** registrar o canal de origem; criar orçamento rápido; ter várias opções ou alternativas no mesmo orçamento; validade e prazo estimado; condição de pagamento com sinal e saldo por marco; aceite do orçamento registrado; flag "com instalação" com endereço e data; status visível que o cliente possa consultar, para reduzir ligações.
- **Nice-to-have (inferência):** chatbot de captação; aprovação de arte com link de prova versionada; checklist de licença ou locador; cobrança de taxa de criação para prospects.

### Gaps
- Não achei fonte brasileira abrível que quantifique o percentual de sinal usual (50% é o padrão citado só na fonte americana) nem se a visita técnica ou medição é cobrada à parte no Brasil.
- **Não verificado (só snippet, Reclame Aqui com 403):** reclamações brasileiras falam de fachada de ACM contratada para 15 dias que passou dos 92, lona do luminoso na cor errada, entregas remarcadas duas vezes com material errado e empresa que fabrica mas não instala, deixando a instalação "para parceiros". São padrões plausíveis de "onde dá errado", mas não consegui abrir as páginas.
- Não encontrei conteúdo de YouTube ou de associações (ABCV, Revista Sign, FESPA Brasil) que fosse abrível pela ferramenta.

---

## 2. Como as lojas precificam (m², metro linear, material + mão de obra + máquina + instalação, markup, tabela por quantidade) e os erros comuns

### Takeaway
O preço é montado a partir de **custo direto** (material com perda, mão de obra por tarefa, terceiros, ferragens, deslocamento ou frete, equipamento de acesso) + **rateio de overhead** + **markup**. Para itens comuns, as lojas usam tabelas por m² ou por unidade. Os erros mais citados são: tratar unidades de medida como intercambiáveis, calcular m² líquido e não o consumo real de chapa ou bobina, esquecer acabamento e mão de obra, presumir que "instalado" está incluso, não cobrar criação e não lançar como custo do job o aluguel de plataforma, andaime ou elevador.

### Cited Findings
**Brasil (contexto licitação, mas o checklist vale para qualquer orçamento)**
- As unidades "peça, metro quadrado, metro linear, kit ou serviço" "não são intercambiáveis" e mudam o preço. Os grupos de custo a considerar são pré-produção, materiais, produção, **perdas**, logística, instalação, gestão do contrato, garantia e risco, impostos e margem. Em itens por m², a regra é estimar o **consumo real de chapa ou bobina**, não só a área líquida entregue — [Effecti](https://effecti.com.br/?p=11367)
- Erros comuns listados: cotar materiais parecidos mas tecnicamente incompatíveis; **presumir "instalado"** quando o objeto é só fabricação e entrega (ou o contrário); **subestimar acabamento e tempo de mão de obra**; ignorar a unidade de medida e a estrutura dos lotes. Riscos de instalação: elétrica, cargas estruturais e **queda em altura (NR-10 e NR-35 citadas)**. É preciso definir se o preço inclui só fabricação ou também criação, instalação, estrutura, componentes elétricos, transporte e garantia — [Effecti](https://effecti.com.br/?p=11367)
- Fornecedores brasileiros de software para CV ou gráfica anunciam "Precificação automática" e "Propostas por whatsapp" (Mubisys, que diz ter cerca de 14 mil usuários), ou um módulo de Orçamento com "2000000 orçamentos processados por mês" e Ficha técnica (Calcgraf/NetCalc). Nenhuma das duas home pages detalha cálculo por m², perda ou instalação — [Mubisys](https://www.mubisys.com/); [Calcgraf](https://www.calcgraf.com.br/)

**Internacional**
- "A shop can't determine an accurate selling price without first knowing a sign's production cost." O orçamento cobre labor + materials + overhead, e percorrer a produção antes de orçar gera um bill of materials completo. Estimar **por tarefa** é mais preciso que estimar o job inteiro. "Be conservative. Any underestimation will directly reduce your bottom line." (Dan Hale e Eric Hall/CASper; matéria de 2006/2009, conhecimento consolidado) — [Signs of the Times, Pricing for Profit](https://signsofthetimes.com/pricing-profit/amp/)
- Planilha de custeio recomendada por um dono (mai/2016): custos diretos (subcontratados, substratos, gráficos, ferragens, **viagem**), tempo de mão de obra estimado e, de preferência, o real registrado para calibrar, overhead ("the hardest" part) rateado por hora de mão de obra, e markup do dono. Outro dono pede "easy to understand pricing" para funcionários com pouca experiência em placas — [Signs101, Sign Tracker users (2016)](https://signs101.com/threads/sign-tracker-users-or-other-estimating-job-scheduling-software.132641/)
- Na prática (2023), um dono precifica itens comuns **por sq ft** e faz a conta no papel. Outro diz que lojas sob encomenda precisam de uma **tabela de componentes ou insumos** ("parts, resources, components") custeada separada do produto acabado. Uma terceira valoriza poder importar uma planilha de preços — [Signs101, estimate and accounting (2023)](https://www.signs101.com/threads/estimate-and-accounting-software.173613/)
- Extras que costumam ficar sem preço: "design, access equipment, specialty materials, weather delays". Aluguel de plataforma "is a direct job cost, not overhead": deve ser discriminado (aluguel, entrega, combustível, seguro de dano). Criação se cobra à parte, "with a stated number of revisions". Material especial tem fator de perda; o exemplo é folha de ouro, com 25–30% para iniciantes. O preço por área é ajustado por detalhe, condição da superfície, **altura** e complexidade — [Beancount.io](https://beancount.io/blog/2026/10/03/sign-painter-hand-lettering-bookkeeping-pricing-gold-leaf-guide)
- Dica publicada em mai/2026: fazer um "Job Cost Reality Check" 48 h depois da instalação, comparando horas, material, subcontratados e frete reais contra o orçado e registrando a variação por tipo de job. Isso revela produtos subprecificados e estouros recorrentes de mão de obra antes do fechamento do ano (Derek Atchley, Columbus-OH) — [Signs of the Times, May 2026 Inbox](https://signsofthetimes.com/women-in-signs-big-survey-and-more-in-mays-inbox/)
- Orçar várias opções a partir de tabelas: vários tamanhos e tipos de instalação, ou o mesmo produto em quantidades diferentes (2013) — [Signs101](https://signs101.com/threads/big-jobs-how-do-you-sell-them.110833/post-1139947)
- Falta de catálogo de materiais do setor no software leva a erro de escolha. O shopVOX "doesn't contain lots of industry materials", e a equipe acaba escrevendo notas manuais (Think713, 14/07/2026) — [Signs101, p.2](https://www.signs101.com/threads/good-alternates-to-shopvox-shop-management-software.177150/page-2)

### Inferences
- *(Inferência)* O item da venda precisa de **unidade de cálculo por produto**: m² (L×A×qtd), metro linear, unidade, kit ou serviço/hora. Essa unidade precisa ficar visível, porque misturar unidades é um erro citado.
- *(Inferência)* **Acabamentos** (ilhós, bastão, solda ou vulcanização, laminação, recorte), **instalação** (com faixa de altura e equipamento de acesso), **deslocamento ou frete** e **criação** devem aparecer como adicionais explícitos ou como checklist no item. São justamente os custos "esquecidos".
- *(Inferência)* Mostrar **custo, margem e markup em tempo real** no orçamento (pelo menos para quem tem permissão) responde ao "know your cost first". O "Job Cost Reality Check" sugere guardar o custo orçado por item para comparar depois com o realizado.
- *(Inferência)* Ter **perda ou aproveitamento** por material (largura de bobina, chapa inteira) é diferencial: o cálculo em m² líquido subestima o custo.
- **Must-have (inferência):** unidade por item; dimensões L×A; tabela de preço por faixa de quantidade; adicionais de acabamento e instalação; linha de deslocamento ou frete; margem visível; duplicar item ou opção para cotar alternativas.
- **Nice-to-have (inferência):** cálculo automático de perda por largura de bobina; custo-hora de máquina; fator de altura com custo de plataforma ou andaime; comparação orçado × real pós-instalação; importação de tabela de preços por planilha.

### Gaps
- Não achei fonte brasileira abrível com percentuais típicos de perda (lona, adesivo, ACM) nem markup médio do setor.
- **Não verificado (PDF ilegível pela ferramenta):** a tabela de compras da Unicamp (ofício circular DGA 23/2024) supostamente traz banner de lona por m² com acabamento e instalação de faixas como item separado "até 2 m de altura". O modelo de proposta da Prefeitura de Santa Cruz do Rio Pardo supostamente separa lona por gramatura (280/440 g/m²) e por acabamento (vulcanização, ilhós, presilhas). Ambos foram vistos só em snippet de busca.
- Não achei estudo quantitativo da variação orçado × real específico de sign shops.

---

## 3. Do que os donos reclamam (e o que elogiam) em software ou planilhas de orçamento e pedido

### Takeaway
As queixas recorrentes, mais fortes no material internacional, são: preço alto para lojas pequenas e reajustes, curva de aprendizado e falta de tempo para treinar, integração ruim com a contabilidade (retrabalho e digitação dupla), falta de catálogo de materiais do setor, dados "reféns" do fornecedor e falta de visão única dos pedidos. No Brasil, o discurso de fornecedor foca em lentidão de resposta, aprovação de arte travada e ligações de status. Os elogios vão para simplicidade, quadro de jobs flexível (arrastar e soltar, calendário), conversão de orçamento em fatura e ganho de fluxo de caixa.

### Cited Findings
**Reclamações**
- Preço e reajuste: "$215 → $366 jump with no real upgrade path" no shopVOX, e problemas de exportação descobertos só depois do compromisso. O autor (Mike1024, 07/09/2026) promove um produto concorrente, QuoteJacket — [Signs101, p.2](https://www.signs101.com/threads/good-alternates-to-shopvox-shop-management-software.177150/page-2)
- Sistemas que não se integram e geram trabalho redundante (StacyT, citada em 04/10/2026, que usa Excel para orçar e mais Trello e Xero). O Cyrious é "solid" mas um "heavier lift" e mais caro. O SignTracker é "fine for basic stuff" — [Signs101, p.2](https://www.signs101.com/threads/good-alternates-to-shopvox-shop-management-software.177150/page-2)
- Catálogo pobre de materiais do setor (Think713, 14/07/2026) e falta de visão dos pedidos abertos vindos de vários canais (ADVANCED DISPLAY, 14/07/2025) — [Signs101, p.2](https://www.signs101.com/threads/good-alternates-to-shopvox-shop-management-software.177150/page-2)
- Integração contábil: outras opções "don't communicate well with QB Desktop, which is a huge issue for us". Dados reféns: fornecedores que seguram dados "as hostage", o que torna a migração crítica. Assinatura mensal levou um dono a parar de atualizar. Interface datada ("I came to dislike a lot of the interface") (ago/2023) — [Signs101, estimate and accounting](https://www.signs101.com/threads/estimate-and-accounting-software.173613/)
- "A lot of grumbling about SignVox/ShopVox", preço alto para loja pequena. Um grupo diz ter testado programas de US$ 6.000 e de US$ 130/mês e nenhum "easily replaces the three programs we use now" (mai/2016) — [Signs101, Sign Tracker users](https://signs101.com/threads/sign-tracker-users-or-other-estimating-job-scheduling-software.132641/)
- O Cyrious "works very very well", mas "can't start using it for a hundred bucks or so". Um dono orçava à mão num caderno e queria acelerar o orçamento de uma equipe de vendas que crescia (mai/2017) — [Signs101, estimating software which one to pick](https://www.signs101.com/threads/estimating-software-which-one-to-pick-help.140084)
- Brasil (discurso de fornecedor): orçamento perdido por demora de e-mail, aprovação de arte "leva dias", ligações sobre status — [SocialHub](https://www.socialhub.pro/blog/whatsapp-business-para-grafica-comunicacao-visual/)

**Elogios**
- Uma equipe de 7 pessoas "LOVE Shopvox so far", com ganho em orçamento e fluxo de caixa. "Simplicity" é o que mais importa para uma dona que prefere ferramentas especializadas. O fluxo de jobs do SignTracker agrada a quem "pick and choose" funções (2025–2026) — [Signs101, p.2](https://www.signs101.com/threads/good-alternates-to-shopvox-shop-management-software.177150/page-2)
- "I LOVE ShopTracker!!!": flexibilidade, calendário, quadro drag-and-drop, upload de planilha de preços, atribuição e impressão de tarefas. O QuickBooks Desktop é "great for creating estimates, converting to invoices" (2023) — [Signs101, estimate and accounting](https://www.signs101.com/threads/estimate-and-accounting-software.173613/)
- Fornecedores brasileiros destacam no marketing: precificação automática, proposta por WhatsApp, funil de vendas, comissões, PCP com setorização, baixa de estoque, notas e boletos, conciliação bancária e app para "acompanhe onde estiver como estão as demandas" (Mubisys). Também NF-e e NFS-e, comissões e opção local, nuvem ou mobile (Calcgraf) — [Mubisys](https://www.mubisys.com/); [Calcgraf](https://www.calcgraf.com.br/)

### Inferences
- *(Inferência)* A tela "Nova venda" compete com **Excel + WhatsApp + caderno**, que são rápidos. Se for mais lenta que a planilha para um orçamento simples, perde. A simplicidade é o valor mais citado.
- *(Inferência)* O **catálogo pré-carregado de materiais e produtos de CV** (lona por gramatura, adesivo, ACM, PS, PVC, acabamentos) reduz erro e tempo de implantação, que é uma dor citada (falta de tempo para treinar).
- *(Inferência)* A conversão **orçamento → pedido → fatura/nota** sem redigitação é elogio recorrente. A falta dela é a queixa nº 1 de integração.
- *(Inferência)* Visibilidade de margem e pipeline (orçamentos abertos, aprovados, perdidos) atende à queixa de "não sei o que está aberto".
- **Must-have (inferência):** orçamento simples em poucos cliques; catálogo de CV; conversão sem redigitação; exportar ou enviar PDF/WhatsApp; status do pedido.
- **Nice-to-have (inferência):** quadro kanban de produção e instalação; importação de tabela de preço; exportação de dados (contra o "dados reféns").

### Gaps
- **Não verificado (Capterra/G2/Software Advice com 403, só snippet):** segundo o snippet, o shopVOX teria nota 4,6 com cerca de 241 reviews no Capterra. Contras citados no snippet: curva de aprendizado íngreme sem setup pago, interface complicada para gráfica pequena (melhor para sign shop), integração Sage e QuickBooks fraca, chamados de suporte abertos por meses, timeouts. Para o CoreBridge, o snippet traz uma única crítica (sugestões não implementadas) e preço a partir de US$ 149/mês. Nada disso foi aberto, então não deve ser citado como fato sem nova verificação.
- Não encontrei reviews brasileiros independentes (Reclame Aqui ou reviews de software) sobre sistemas de CV como Mubisys ou Calcgraf.

---

## 4. Dor fiscal no Brasil: produto + serviço na mesma venda (ICMS × ISS, NF-e × NFS-e), retenções para órgãos públicos, Simples Nacional, reforma tributária

### Takeaway
A classificação de placa, banner ou adesivo sob encomenda é **controversa e varia por estado e por fato concreto**. A SEFAZ-SP tratou placas, painéis, banners e totens como industrialização sujeita a ICMS (2016), mas também tratou banners e adesivos personalizados de publicidade como fora do ICMS (2015). A SEF-SC entendeu que a confecção sob encomenda é ISS exclusivo, item 24.01 (2006). A instalação, quando é atividade própria, cai em serviço: o CNAE 4329-1/01 fica no Anexo III do Simples. Em 2026 a reforma tributária passou a exigir o destaque de IBS/CBS na NF-e (desde 03/08/2026) e na NFS-e (desde 01/10/2026) para o regime normal; o Simples Nacional fica obrigado a partir de 01/01/2027. Órgãos federais retêm IR/CSLL/PIS/Cofins, com dispensa para optantes do Simples mediante declaração.

### Cited Findings
**ICMS × ISS: entendimentos conflitantes**
- **SEFAZ-SP, Resposta à Consulta 10406/2016 (14/06/2016):** contribuinte do Simples Nacional, CNAEs 73.19-0/99 e 47.89-0/99, fabricando placas, painéis, banners e totens (e adesivos). A conclusão foi que a confecção por encomenda é **industrialização, sujeita ao ICMS**: "O produto, ainda que personalizado, continua sendo fundamentalmente mercadoria". O argumento do contribuinte pelo item 24.01 da LC 116/2003 foi rejeitado — [Jornal Contábil](https://jornalcontabil.com.br/noticia/fabricacao-por-encomenda-de-produtos-de-comunicacao-visual-esta-sujeita-ao-icms-em-sp/amp/)
- **SP, outra linha (impressos):** o item 13.05 da LC 116 ("composição gráfica, inclusive confecção de impressos gráficos") é ISS, salvo quando o produto se destina a comercialização ou industrialização, caso em que é ICMS. Pela DN CAT 04/2015 não há ICMS sobre impresso personalizado se três condições forem atendidas: personalização evidente, valor e natureza sem predominância de circulação de mercadoria, e uso exclusivo do encomendante. A **Consulta 5413/2015** enquadrou **banners de lona ou vinil e adesivos** como impressos publicitários personalizados, sem exigência de documento fiscal de ICMS para a movimentação em SP. A 5958/2015 enquadrou sacolas e embalagens com nome do cliente em ICMS. A empresa consulente emitia NFS-e (item 13.05). Artigo de 22/04/2019, atualizado em 23/10/2023 — [Portal CPA/NetCPA](https://netcpa.com.br/colunas/icmsiss-atividade-de-grafica-e-comunicacao-visual-tributacao/1353)
- **SEF-SC, Consulta 36/06 (parecer de 20/03/2006, publicado em 19/10/2006):** a confecção sob encomenda de placas, painéis, luminosos, totens, sinalização visual, banners e adesivos **está sujeita exclusivamente ao ISS (item 24.01 da LC 116/03)**, sem ressalva de ICMS. Se o produto for vendido ao público em geral, e não sob encomenda, é mercadoria e cabe ICMS. O parecer não trata da instalação — [SEF-SC](https://legislacao.sef.sc.gov.br/html/Consultas/2006/Con_06_036.htm)
- **Conflito explícito:** as fontes de SP (2015 × 2016) e SC (2006) chegam a conclusões diferentes para produtos parecidos — [Jornal Contábil](https://jornalcontabil.com.br/noticia/fabricacao-por-encomenda-de-produtos-de-comunicacao-visual-esta-sujeita-ao-icms-em-sp/amp/); contraste com [NetCPA](https://netcpa.com.br/colunas/icmsiss-atividade-de-grafica-e-comunicacao-visual-tributacao/1353) e [SEF-SC](https://legislacao.sef.sc.gov.br/html/Consultas/2006/Con_06_036.htm)

**Instalação e Simples Nacional**
- O CNAE **4329-1/01** (instalação de painéis publicitários: outdoors, letreiros, placas, sem fabricação) fica no **Anexo III** do Simples, com alíquota inicial de 6% podendo chegar a 33%. O Fator R não se aplica e o MEI é permitido. A página não trata de ICMS nem da combinação com CNAE de fabricação (publicado em 30/03/2026, atualizado em 19/10/2026) — [Contabilidade.com](https://contabilidade.com/blog/cnae-4329101-instalacao-de-paineis-publicitarios-simples-nacional-mei-e-abertura-de-empresa/)

**Retenções (órgãos públicos)**
- A IN RFB 1.234/2012 trata da retenção de IRPJ, CSLL, PIS/Pasep e Cofins em pagamentos de órgãos federais, autarquias, fundações, empresas públicas e sociedades de economia mista. O optante do Simples é dispensado mediante declaração na assinatura do contrato (art. 6º), ou a fonte pagadora pode consultar o Portal do Simples (§ 4º, incluído pela IN 1.540/2015). Na resposta do autor a um leitor, a dispensa vale para contratos e não para pagamentos avulsos (artigo de 25/10/2016) — [Foco Tributário](https://focotributario.com.br/declaracao-do-simples-para-dispensa-de-retencao-e-obrigatoria/)

**Reforma tributária (vigente em out/2026)**
- O Ato Conjunto RFB/CGIBS nº 04/2026 definiu: NF-e, NFC-e, CT-e e MDF-e com destaque obrigatório de IBS/CBS desde **03/08/2026**; NFS-e adiada para **01/10/2026** (parte), com expansão em 01/12/2026; **Simples Nacional em 01/01/2027**. As alíquotas-teste são 0,1% de IBS e 0,9% de CBS. Documento sem os campos é **rejeitado**. Não há cobrança em 2026; a cobrança efetiva está prevista para 2027 — [Guia Tributário](https://guiatributario.net/2026/08/03/nfs-e-destaque-da-cbs-e-ibs-e-adiado-para-01-10-2026/)

### Inferences
- *(Inferência)* Como o enquadramento varia por UF, município e fato (encomenda × revenda, publicidade × sinalização, com ou sem instalação), a "Nova venda" **não deve fixar o tipo fiscal no código**. Cada item ou produto precisa ter natureza fiscal configurável (mercadoria/NF-e × serviço/NFS-e, com item da LC 116), e a venda precisa conseguir **gerar NF-e e NFS-e a partir do mesmo pedido** quando houver fabricação mais instalação.
- *(Inferência)* Linhas de instalação, criação e deslocamento devem ser itens separáveis, e não embutidos no preço da placa, para permitir o split fiscal e a retenção correta.
- *(Inferência)* O cliente órgão público pede um **perfil fiscal do cliente** (federal, estadual ou municipal, com ou sem retenção) e o cálculo do **valor líquido a receber** já no orçamento. Senão o "saldo a cobrar" não fecha com o que entra.
- *(Inferência)* O regime normal já precisa de IBS/CBS informativos nas notas em out/2026, e o Simples a partir de jan/2027. Os campos fiscais do item (NCM, cClassTrib ou equivalente) precisam estar no cadastro de produto, para não travar o faturamento da venda.
- **Must-have (inferência):** natureza fiscal por item (produto × serviço); uma venda gerando NF-e e NFS-e; regime tributário da empresa; IBS/CBS conforme o calendário.
- **Nice-to-have (inferência):** cálculo automático de retenções por tipo de cliente público; alerta de UF com entendimento divergente; nota de remessa para o local de instalação.

### Gaps
- Não encontrei texto abrível com as alíquotas exatas de retenção federal (os 5,85% para bens e 9,45% para serviços que costumam ser citados **não foram verificados**). Também não verifiquei o texto atual consolidado após as IN 2.145/2023 e IN 2.239/2024, que só apareceram em snippet.
- Não achei fonte abrível sobre a **retenção de ISS pelo tomador** (prefeituras e grandes tomadores) nem sobre retenção de INSS em instalação (cessão de mão de obra) aplicável à CV.
- Não achei decisão recente (STF/STJ, 2020+) abrível especificamente sobre placas e fachadas. A ADI 4389 (embalagens) e a Súmula 156 do STJ apareceram só em snippet.
- O anexo do Simples para o CNAE de fabricação de letreiros (3299-0/03 e 3299-0/04) não foi confirmado em fonte aberta.

---

## 5. Necessidades de grandes clientes: órgãos públicos/licitação, franquias/redes com várias unidades, agências de publicidade (comissão/BV)

### Takeaway
**Órgãos públicos** compram por lote, unidade de medida e especificação rígida (gramatura, tipo de vinil, instalação obrigatória), muitas vezes via ata de registro de preços sem consumo garantido. Por isso os pedidos chegam pequenos e dispersos, e o custo de mobilização e frete precisa ser previsto. **Redes e franquias** exigem levantamento por unidade, aprovação de licença e locador por site, padrão de marca e visão centralizada do portfólio. **Agências** intermediam com honorário sobre produção (referência CENP de 15%) e, às vezes, pedem comissão ao fornecedor ("BV de produção"), prática contestada e que corrói a margem do produtor.

### Cited Findings
**Órgãos públicos e licitação** (página atualizada em 01/09/2026)
- O objeto combina layout aprovado, substrato, impressão ou acabamento, estrutura de fixação e condições de instalação. Se um componente ficar fora do preço, "a contratação pode deixar de ser viável mesmo depois de um lance vencedor" — [Effecti](https://effecti.com.br/?p=11367)
- Na ata de registro de preços, a estimativa "não equivale a consumo garantido". Pedidos pequenos e dispersos multiplicam os custos de mobilização e frete, então é preciso simular pedido mínimo e máximo — [Effecti](https://effecti.com.br/?p=11367)
- Amostras ou prova de conceito podem ser exigidas se o edital previr. Pela Lei 14.133/2021, na fase de julgamento a exigência vale só para o provisoriamente vencedor. Atestados de capacidade técnica devem mostrar se cobrem só fornecimento ou também instalação. Sinalização de acessibilidade, trânsito, emergência e elétrica tem normas próprias — [Effecti](https://effecti.com.br/?p=11367)
- A retenção federal (IR/CSLL/PIS/Cofins) em pagamentos de órgãos federais, com dispensa para o Simples mediante declaração ou consulta ao Portal do Simples, está detalhada na seção 4 — [Foco Tributário](https://focotributario.com.br/declaracao-do-simples-para-dispensa-de-retencao-e-obrigatoria/)

**Franquias e redes (programas com mais de 100 sites, fonte americana sem data)**
- Desafios: "Every site is different"; licenças municipais que "vary wildly"; aprovações do locador com prazo próprio; estruturas existentes que podem não suportar o novo padrão; fragmentação entre fornecedores ("programs quickly fragment"); "no centralized visibility into what's actually happening across the portfolio"; falhas de passagem entre equipes que se multiplicam em escala — [Principle Global](https://www.principleglobal.com/en/insights/scaling-brand-change-managing-100-site-programs)
- Práticas: priorizar sites estratégicos; testar o padrão contra as condições reais (disponibilidade de material, realidade de instalação); coordenar de forma centralizada licença, levantamento, fabricação, instalação e relação com o locador; um responsável único pelo cronograma, pela execução em campo e pelo report ao cliente; feedback estruturado dos instaladores — [Principle Global](https://www.principleglobal.com/en/insights/scaling-brand-change-managing-100-site-programs)

**Agências de publicidade**
- O "BV de produção" é uma comissão caso a caso paga por produtoras às agências. É diferente do BV de veículos (por volume) e é descrito como "não é legal". No caso Borghi/Lowe a comissão relatada era de 10%. As normas-padrão do CENP preveem honorário de 15% sobre serviços de produção, que alguns clientes se recusavam a pagar; as agências repassavam esse custo aos produtores em troca de comissão. Segundo a Apro, o BV "muitas vezes, é maior que o lucro da própria produtora". A Abigraf defendeu comissões como "práticas comerciais consagradas". Matéria de 29/04/2015, focada em produtoras de filme e não em CV — [Meio & Mensagem](https://www.meioemensagem.com.br/comunicacao/bv-de-produ-o-os-perigos-da-rela-o-paralela)

**Clientes recorrentes ou de conta (EUA, 2013)**
- Uma loja mantém "account on file" de US$ 2.000 e fatura contra ele se o cliente levar a arte especificada para outro fornecedor. A recomendação é fazer layouts de graça só para clientes recorrentes — [Signs101](https://signs101.com/threads/big-jobs-how-do-you-sell-them.110833/post-1139947)

### Inferences
- *(Inferência)* **Órgão público:** a venda precisa referenciar o processo (nº do pregão, ata, empenho), respeitar a **especificação e o preço fixados na ata** por item e lote, guardar comprovantes de entrega, instalação e aceite (o pagamento depende deles) e calcular o líquido após retenções. O ideal é o vendedor puxar os itens da ata em vez de digitar preços.
- *(Inferência)* **Rede ou franquia:** um "pedido-mãe" com **vários endereços ou unidades**, cada um com levantamento próprio (medidas, fotos, estrutura existente), status de licença e locador, data de instalação e talvez faturamento por unidade (CNPJ do franqueado × franqueadora). Um padrão de kit replicável por unidade economiza digitação.
- *(Inferência)* **Agência:** a venda tem três papéis (agência intermediária, anunciante final e pagador, que podem diferir) e um campo de comissão ou honorário de agência que afeta a margem. Como o "BV de produção" é contestado, a comissão deveria ser explícita e auditável, não escondida no preço.
- **Must-have (inferência, para quem atende esses segmentos):** cliente × local de instalação × pagador como campos distintos; referência de contrato, ata ou empenho; vários endereços de instalação na mesma venda.
- **Nice-to-have (inferência):** importação de itens da ata; rollout multiunidade com painel por site; cálculo de comissão de agência; conta corrente ou crédito de cliente recorrente.

### Gaps
- Não achei fonte brasileira abrível sobre redes e franquias (padronização de fachada em rollout nacional) nem sobre como CVs faturam franqueado × franqueadora.
- Não achei fonte específica sobre comissão de agência paga por **empresas de CV ou gráficas** (só produtoras), nem sobre o tratamento fiscal dessa comissão.
- Não achei dados sobre prazos médios de pagamento de órgãos públicos para fornecedores de CV, nem sobre o peso da inadimplência pública × privada no setor.
