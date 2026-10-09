---
id: session-2026-10-09-arte-composicao-vs-producao
date: "2026-10-09"
topic: "Estado da arte — onde mora a composição (ficha técnica) e onde mora a produção, com foco em comunicação visual"
tipo: estado-da-arte
pii: false
---

# Estado da arte — onde mora a COMPOSIÇÃO e onde mora a PRODUÇÃO

> Pedido do [W]: confirmar com o mercado a separação que ele já decidiu.
> **Composição** (ficha técnica / BOM): monta o produto mesmo com item em falta. Não olha estoque e não tem desperdício.
> **Produção** (OP / PCP): não produz sem material, e é ali que o desperdício existe.
> Fontes acessadas em **2026-10-09**. Onde o fornecedor não publica o detalhe, está escrito **não confirmado**.
> Código medido em `origin/main` `0b30481697`.

## 1 · Pesquisa: como os melhores separam as duas coisas

| Player | Composição: onde fica, olha estoque?, tem perda? | Produção: onde fica, o que faz na falta, onde entra a perda |
|---|---|---|
| **Odoo 18 MRP** (referência global) | A BoM fica no app Manufacturing e também se abre pela ficha do produto. Não reserva estoque. Não tem campo de perda nativo, só "Flexible Consumption" (quanto o consumo pode fugir da BoM). Um módulo de terceiro acrescenta "% de perda" | A MO é um documento separado. Com falta de material ela fica em **Waiting**, e "Check Availability" força a reserva. Com consumo **Strict**, a MO não fecha fora da BoM. O **scrap** (perda) é lançado na MO, tanto de componente quanto de produto acabado |
| **Katana** (SMB global) | A receita/BOM fica na ficha do produto. Ela não reserva nada | A MO copia a receita: mudar a receita depois **não altera MO aberta**. A falta aparece como *Not available* ou *Expected* (já há compra ou OP a caminho), e a prioridade das ordens decide quem fica com o estoque. Da própria linha do insumo dá para criar o pedido de compra |
| **Omie** (horizontal BR) | A "Estrutura do Produto" (ficha técnica) fica no cadastro do produto, com custo de mão de obra direta (MOD) e gastos gerais (GGF). Perda: não confirmado | A OP fica num kanban de etapas e mostra se há estoque. A reserva é opcional e só reserva o que existe ("Somente itens com estoque disponível serão reservados"). O blog da Omie recomenda registrar perda e retrabalho na OP, mas o recurso não está documentado na ajuda |
| **Bling** (horizontal BR) | Primeiro se cadastram componentes e composição | A ordem de produção é um módulo separado, criado em 2020. Nasce de um pedido de venda ou para repor estoque, e tem calendário de início e fim. Comportamento na falta e perda: não confirmado |
| **Calcgraf** (gráfica BR, desde 1983) | A **Ficha técnica** é uma solução à parte, dentro da área de Produção. O orçamento a usa quando o produto é reproduzido. Em comunicação visual, o orçamento calcula o **aproveitamento de bobinas e emendas** | O PCP recebe do orçamento os tempos previstos. O material é **"reservado após a emissão da ordem de produção"**, não no orçamento. Há pós-cálculo (orçado × realizado) |

**Comunicação visual BR, com menos detalhe publicado:**
- **Mubisys** anuncia precificação no **orçamento** com "algoritmos de aproveitamento de materiais". Anuncia também PCP por setor, terminal de apontamento pelo celular e estoque fracionado que mostra em que serviço cada mídia foi usada. Onde fica a composição e o que acontece na falta: **não confirmado**.
- **Zênite (ZSL)** anuncia orçamento, mapa de custos, OS/PCP, apontamento automático lido do equipamento e estoque para "evitar desperdícios". Ficha técnica, m² e perda: **não confirmado**.
- **Calcme** anuncia orçamento automático, plano de corte por item e escolha de máquina por "aproveitamento". As OPs nascem conforme os pedidos e o kanban é por item ou por pedido. Baixa de estoque: **não confirmado**.

### Respostas às 4 perguntas

1. **Onde fica a composição.** Nos 5 com fonte, ela é dado de cadastro: no produto (Katana, Omie, Bling) ou numa área própria de engenharia ou ficha técnica (Odoo, Calcgraf). **Nenhum** checa estoque na composição. O desperdício **real** fica na produção (Odoo: scrap na MO; Calcgraf: pós-cálculo).
2. **O que a produção faz na falta.** Ninguém impede a OP de **existir**. O que se bloqueia é **começar ou concluir**: Odoo deixa a MO em *Waiting* e, no modo Strict, ela não fecha; Katana mostra *Not available* e sugere a compra; Omie reserva só o que existe. O padrão do mercado é **criar a OP e mostrar a falta**, não recusar a OP.
3. **Comunicação visual.** O preço sai do **orçamento** (área × material × acabamento, mais aproveitamento da bobina) e a execução é OS/PCP por setor (Mubisys, Calcgraf). A ficha técnica de CV nasce no **orçamento ou pedido**, porque cada peça tem medida própria. O cadastro de produto guarda só o material com preço por m². Exceção: na Calcgraf a ficha técnica fica no cadastro e serve à reprodução.
4. **O vínculo entre os dois.** A ordem **copia** a composição no momento em que é criada (Katana documenta isso). O consumo é registrado na conclusão (Odoo "Produce All", SAP), com a entrada do produto acabado no mesmo passo. A reserva acontece na emissão da OP (Calcgraf, Omie).

**Um ajuste à regra do [W], sem contradizê-la.** O mercado separa duas perdas que costumam levar o mesmo nome:
- **perda planejada**: sobra da bobina, sangria, emenda. Ela entra no **preço**, antes de a produção existir.
- **desperdício real**: refugo, reimpressão. Ele é **registrado na produção**.

Em CV a premissa vale: uma bobina de 3,20 m que imprime um banner de 1 m paga a sobra no preço. Por isso a perda planejada mora no **cálculo do orçamento**, não na composição. Assim a regra do [W] fica inteira: a composição não tem desperdício, o orçamento cobra o aproveitamento e a produção registra o desperdício real.

## 2 · Comparação com o oimpresso de hoje

| Dimensão | Mercado | oimpresso hoje (medido) | Distância |
|---|---|---|---|
| Onde mora a composição | Um lugar só | **3 tabelas**: `mfg_recipes` (Manufacturing), `product_bom` (Inventory, criada em 2026-05-12, só API: `git grep` em `resources/js` e `resources/views` deu 0 ocorrências) e `combo_variations` (combo do UltimatePOS). Em CV ainda há o material/m² no orçamento | **longa** |
| Qual composição a produção lê | A mesma do cadastro | O pipeline de produção que já existe (FSM, `ReservarEstoque` e `ConsumirEstoque` via `BomResolver`) lê **`product_bom` ou combo, nunca `mfg_recipes`**. A OP do Manufacturing lê `mfg_recipes`. São duas produções lendo duas composições diferentes | **longa** |
| Composição olha estoque? | Não | Não. O editor (React) não consulta estoque. A aba Insumos só **mostra** o saldo | **bate** |
| Desperdício na composição | Não existe (Odoo nativo); perda planejada fica no preço | **Existe**: `waste_percent` na receita e em cada ingrediente, e o editor mostra "Desperdício (%)". Isso contradiz a decisão do [W] | **média** |
| Custo congelado na OP | A ordem copia a receita (Katana) | **Bate**: a OP grava `unit_price` = `dpp` do dia em `transaction_sell_lines`. A receita recalcula o custo a cada leitura | **bate** |
| Falta de material | A OP existe e fica "aguardando"; reserva; compra sugerida | Tudo ou nada: ou bloqueia ao finalizar (`PurchaseSellMismatch` quando `allow_overselling=false`, e o JS limita a quantidade), ou deixa negativo. A OP não tem estado de espera e não reserva, embora `stock_reservations` já exista no FSM | **longa** |
| Execução da OP | Etapas: confirmada, em produção, concluída; apontamento | A OP Blade tem só rascunho ou final. Ao **finalizar**, baixa os insumos e dá entrada no acabado num passo só. Etapas e apontamento existem só no FSM de CV/Venda e na API de apontamento CV (US-COMVIS-004, parcial) | **média** |
| Desperdício real na produção | Scrap por insumo e por acabado, com motivo (Odoo) | `mfg_wasted_units` cobre só o **acabado**. A perda por insumo é o % **copiado da receita** (planejado, não real). Sem motivo. `ConsumirEstoqueExtra` (reimpressão) existe só no SPEC de CV, o arquivo não existe | **média** |
| Perda planejada no preço (CV) | Aproveitamento da bobina no orçamento | `OrcamentoCalculator` faz área × preço/m² + extras. **Não calcula aproveitamento** | **média** |

**Onde o oimpresso já está à frente:** reserva com expiração e BOM recursiva no FSM (`stock_reservations` + `BomResolver`), custo da OP congelado, e receita com simulação de preço do insumo (`calculateCostComPrecoSimulado`). Os horizontais BR não documentam nada disso.

**Divergência encontrada (não corrigida aqui):** `memory/research/2026-05-prospeccao/03-concorrentes-alfa-visua-calcgraf-reviews.md` (L169 e L194) diz que *"Bling/Tiny não têm OP"*. **É falso.** O Bling tem módulo de produção desde 2020 ([blog Bling](https://blog.bling.com.br/bling-lanca-modulo-de-producao/)) e o Tiny tem o tipo de produto "fabricado", com aba Produção. O que falta neles é cálculo por m².

**Aviso dado uma vez:** a [ADR 0152](../decisions/0152-modules-pcp-feature-wish.md) (`proposto`) deixa o PCP como wish. O pedido do [W] decide (ADR 0382), então isto é só informação.

## 3 · O que falta, por impacto × esforço

| # | Gap | Impacto | Esforço (IA-pair, ADR 0106) | Pré-requisito |
|---|---|---|---|---|
| 1 | **Registrar a decisão** "composição ≠ produção" e **medir** quanto há em cada uma das 3 tabelas de composição, por business | alto (destrava o resto) | ~1h | nenhum |
| 2 | **Uma fonte só de composição**, lida pela OP e pelo FSM | alto | 1–2 dias + migração de dados | #1 e decisão [W] sobre qual tabela fica. Toca estoque: Regra Mestre (dupla prova + antes→depois) |
| 3 | **OP com estado "aguardando material"** + reserva, reusando `ReservarEstoque`/`stock_reservations`. A OP pode existir; não pode **iniciar ou concluir** sem material | alto | 4–6h | #1. Toca estoque: Regra Mestre |
| 4 | **Tirar "Desperdício (%)" da composição** (receita e ingrediente) e levar para a OP como **perda real lançada** (por insumo e no acabado, com motivo) | médio-alto | 3–5h | #3. Muda a quantidade consumida nas próximas OPs: Regra Mestre |
| 5 | **Módulo Produção** separado do Manufacturing: o Manufacturing vira só Composição (Receitas e Insumos); OP, relatório e configurações vão para Produção | médio | ~1 dia (casca React + migração das telas, processo MWART) | #3 |
| 6 | **Comprar a partir da falta** (botão na linha do insumo, como no Katana) | médio | ~3h | #3 e o módulo Compras |
| 7 | **Aproveitamento da bobina** no `OrcamentoCalculator` de CV (perda planejada no preço) | médio (CV) | 3–4h | nenhum técnico. Toca VALOR: Regra Mestre |

**Pergunta de produto para o [W]**, e só ele responde: "não produzo sem material" quer dizer **não criar** a OP, ou **não iniciar/concluir**?
- O mercado inteiro adota a segunda leitura.
- Em CV a premissa vale: a OS nasce do orçamento aprovado **antes** de a lona ser comprada, e é a OP parada que avisa o comprador.
- Se for a primeira leitura, o gap #6 perde o gatilho.

## Recomendação

**Comece pelo #1**: alto impacto, ~1h, sem pré-requisito, e tudo o que vem depois depende dele. Hoje existem 3 composições e 2 produções que não se enxergam, e qualquer tela nova de Produção criada antes disso vai escolher uma fonte no escuro.

**Próxima ação hoje:** rodar, só leitura, a contagem por business de `mfg_recipes`, `product_bom` e produtos `type='combo'` com `combo_variations` preenchido (tenant 98 / biz=1, nunca biz=4). Com o número na mão, escrever a ADR proposta "Composição × Produção", com a regra do [W], a pergunta acima e a tabela escolhida como fonte única.

## Fontes (acesso 2026-10-09)

- Odoo 18 — [Bill of materials](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/manufacturing/basic_setup/bill_configuration.html) · [One-step manufacturing](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/manufacturing/basic_setup/one_step_manufacturing.html) · scrap na MO e estado Waiting via [resumo da doc oficial (Cleverence)](https://www.cleverence.com/articles/odoo-documentation/scrap-during-manufacturing-odoo-7890/) e [Deploymonkey](https://deploymonkey.com/blog/odoo-manufacturing-order-stuck-fix) (terceiros, não é a doc oficial)
- Katana — [Ingredients availability](https://support.katanamrp.com/en/articles/5914374-ingredients-availability) · [Product recipes / BOM](https://support.katanamrp.com/en/articles/5967075-understanding-product-recipes-boms) · [Make to order](https://support.katanamrp.com/en/articles/5908804-make-to-order-workflow)
- MRPeasy — [Setting up BOMs, routings, workstations](https://mrpeasy.com/setting-up-manufactured-items-boms-routings-workstations) (perda: não confirmado)
- Omie — [Reserva de estoque na OP](https://ajuda.omie.com.br/pt-BR/articles/13621381-reservando-estoque-na-ordem-de-producao) · [Estrutura do produto](https://ajuda.omie.com.br/pt-BR/articles/1426253-criando-a-estrutura-dos-seus-produtos) · [Blog OP](https://www.omie.com.br/blog/ordem-de-producao-como-organizar-a-fabricacao-em-pmes/)
- Bling — [Bling lança módulo de produção](https://blog.bling.com.br/bling-lanca-modulo-de-producao/) · [Ordem de produção](https://blog.bling.com.br/ordem-de-producao-o-que-e-tipos-e-como-emitir/)
- Tiny — [Como cadastrar produtos no Tiny (Qive)](https://qive.com.br/blog/como-cadastrar-produtos-no-tiny) (terceiro)
- Calcgraf — [Home](https://www.calcgraf.com.br/) · [Orçamento](https://www.calcgraf.com.br/solucao/orcamento/)
- Mubisys — [Home](https://mubisys.com/) · [Produção](https://mubisys.com/producao) · [Comercial](https://mubisys.com/comercial)
- Zênite — [zsl.com.br](https://www.zsl.com.br/)
- Calcme — [Sistema para comunicação visual](https://www.calcme.com.br/sistema-para-comunicacao-visual/)
- Código oimpresso: `Modules/Manufacturing/Http/Controllers/ProductionController.php` (store L200-375), `Modules/Manufacturing/Services/RecipeBomService.php`, `app/Domain/Fsm/SideEffects/{ReservarEstoque,ConsumirEstoque}.php`, `app/Domain/Inventory/Services/BomResolver.php`, `database/migrations/2026_05_12_080001_create_product_bom_table.php`, `database/seeders/FsmProcessoComunicacaoVisualSeeder.php`, `memory/requisitos/ComunicacaoVisual/SPEC.md`
