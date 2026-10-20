---
id: session-2026-10-20-arte-casos-de-uso-fabricacao
date: "2026-10-20"
topic: "Estado da arte — casos de uso que os concorrentes cobrem nas telas de Fabricação e o que ainda não tem caso aqui"
tipo: estado-da-arte
pii: false
---

# Estado da arte — casos de uso da Fabricação comparados com o mercado

## TL;DR

- Comparei Odoo 18, Katana, MRPeasy, Omie e Bling com os **62 casos de uso** das 6 telas da Fabricação (`resources/js/Pages/Manufacturing/*.casos.md`, `origin/main` `2985d3776`, 2026-10-20).
- Saíram **11 casos de mercado**: 3 sem caso de uso aqui, 3 parciais, 1 coberto, 3 de outro módulo e 1 que não se aplica a comunicação visual.
- Os 3 sem caso: **falta de material** (decisão [W] pendente), **lote e validade do produto fabricado** (o servidor grava, a tela nova não mostra e nada protege) e **receita com sub-receita** (premissa não confirmada com cliente).
- Nada aqui é contrato: os itens não têm UC-id. Um item vira caso de uso quando entrar num `.casos.md` com teste que o defenda.

> Pedido da [M]: pesquisar nos concorrentes os casos de uso que essas telas devem cobrir.
> Fontes públicas (documentação, central de ajuda, blog, análise de terceiros) acessadas em **2026-10-20**. Nada foi testado em conta.
> Onde o fornecedor não publica o detalhe, está escrito **não confirmado**.
> Complementa, não repete, o [estado da arte de 2026-10-09](2026-10-09-arte-composicao-vs-producao.md) (onde mora a composição e onde mora a produção). Premissa de cada item traduzida, não copiada (§5 2026-07-16).

## 1 · Os casos de mercado

| # | Tela | Caso de mercado | Quem faz | Vale aqui? | No oimpresso hoje | Situação | Próximo passo |
|---|---|---|---|---|---|---|---|
| M-01 | Ordens | A ordem é criada mesmo faltando material; a falta aparece e o que se bloqueia é concluir | Odoo deixa a ordem em *Waiting* e, no modo *Strict*, não deixa fechar. Katana mostra *Not available* / *Expected* e cria a compra pela linha do insumo. Omie reserva só o que existe em estoque | Sim: ao finalizar, a Fabricação baixa os insumos | O protótipo avisa "Estoque insuficiente… Finalizar vai deixar saldo negativo" e deixa finalizar, sempre. A tela Blade bloqueia no navegador quando a empresa não permite vender sem estoque. A ordem não tem estado "aguardando material" ([PARIDADE](../requisitos/Manufacturing/PARIDADE-ordem-producao-blade-vs-prototipo.md), item 15) | **Sem caso** | Decisão [W] sobre o bloqueio (PARIDADE §6, passo 3). Depois, caso com teste |
| M-02 | Ordens | Lote e validade do produto fabricado | Katana rastreia lote e série (fora do plano Essential). MRPeasy rastreia lote sem configurar item a item. Omie controla lote e validade no estoque e consome por FEFO, FIFO ou LIFO | Sim, para quem usa insumo perecível (tinta, pó DTF) | `ProductionController::store` grava lote e validade quando a empresa liga `enable_lot_number` / `enable_product_expiry`. O protótipo não tem os campos; a PARIDADE (item 7) manda mantê-los. Nenhum caso protege | **Sem caso** | Caso: a tela nova grava lote e validade quando a empresa usa |
| M-03 | Ordens | Perda real por insumo, registrada na ordem, com motivo | Odoo lança o refugo (*scrap*) na ordem, de insumo e de produto acabado, e tem *Flexible Consumption*. Katana compara consumo previsto com real | Sim: o desperdício real é da produção, não da receita | A ordem registra só a perda do produto acabado (UC-OP-09). A perda por insumo é o % copiado da receita, planejado e sem motivo. Na Blade a coluna é só informativa (PARIDADE, item 12) | Parcial | Caso para a perda real por insumo, se a Fabricação passar a registrá-la |
| M-04 | Receitas | Versão da receita, e ordem em rascunho que não muda quando a receita muda | Odoo tem versão na BoM; a ordem de mudança (ECO) sobe a versão. Na Katana a ordem copia a receita ao ser criada | Sim: receita muda com preço e material | A ordem finalizada guarda o custo do dia (UC-OP-02); a receita recalcula a cada leitura. `MfgRecipe` usa `LogsActivity`, mas nenhuma tela mostra o histórico. No módulo Produção a pergunta "congela ou vivo" já foi decidida para a OS aberta (US-PCP-022) | Parcial | Decidir se a receita ganha histórico visível e se o rascunho da ordem congela a receita |
| M-05 | Receitas | Receita com sub-receita (semiacabado em vários níveis) | Katana e MRPeasy têm BOM em vários níveis; MRPeasy também tem BOM fantasma. Omie monta a estrutura da matéria-prima aos subcomponentes | **Não confirmado** com cliente. Exemplo possível: estrutura de metalon usada numa fachada | Produto fabricado entra como ingrediente com o custo de compra da variação (`dpp_inc_tax`), sem abrir a receita dele. A BOM em vários níveis existe no pipeline de vendas (`BomResolver`), que não lê as receitas da Fabricação | **Sem caso** | Perguntar a um cliente se fabrica semiacabado para usar em outro produto |
| M-06 | Receitas | Mão de obra e custo indireto na receita | Omie põe MOD e GGF na estrutura do produto. MRPeasy calcula material, mão de obra e indireto | Sim | Custo extra da receita: valor fixo, % dos ingredientes ou por unidade produzida (UC-RECIPE-04) | Coberto | — |
| M-07 | Ordens | Imprimir a ordem e o mapa de custo dela | Omie tem "Visualizar Mapa de Custo" em cada ordem, ao lado de imprimir a ordem | Sim | O painel da ordem mostra o custo (UC-OP-07). O protótipo imprime a ficha técnica, em lote e numa versão "chão de fábrica" sem custo (`manufacturing-print.jsx`). A ordem não imprime, e **nenhum caso** cobre impressão, nem da ficha nem da ordem | Parcial | Caso para a ficha impressa (o protótipo já tem). Ordem impressa só se um cliente pedir |
| M-08 | Relatório | Produtividade por máquina, setor ou operador | Calcgraf e Corebridge anunciam; os outros 8 da [grade de produção](../research/2026-05-prospeccao/33-grade-producao-concorrentes.md) anunciam em parte | Parcial: por operador depende de apontamento de tempo | O relatório mostra custo por produto no período (UC-REPORT-02 a 04) | Outro módulo | Levar para o módulo Produção, onde está o apontamento |
| M-09 | Ordens | Ordem nascida do pedido de venda (sob encomenda) | Bling cria ordem para produto vendido ou para repor estoque. MRPeasy gera a ordem pelo pedido e confere o material antes de programar | Não nesta tela: sob encomenda é a OS do módulo Produção | A Fabricação transforma insumo em produto acabado no estoque (SCOPE do módulo) | Outro módulo | — |
| M-10 | Receitas | Kit vendido sem montagem | Odoo tem a BoM do tipo *Kit* | Não aqui: é o produto combo do cadastro de Produto | Fora da Fabricação | Outro módulo | — |
| M-11 | Ordens | Subprodutos que saem da produção | Odoo tem a aba *By-Products* na ordem | Não para comunicação visual: a sobra da bobina é perda planejada, que entra no preço do orçamento ([2026-10-09](2026-10-09-arte-composicao-vs-producao.md)) | — | Não se aplica | — |

## 2 · Como a cobertura foi medida

- Casos extraídos dos 6 `.casos.md` da Fabricação em `origin/main` `2985d3776` (2026-10-20), mesmo extrator da página "Casos de uso da Fabricação".
- Cada item foi conferido contra o código e o protótipo antes de entrar: `ProductionController::store` (lote/validade), `ProductionController::destroy` (só apaga ordem não finalizada), `MfgRecipe` (`LogsActivity`), custo do ingrediente por `variation->dpp_inc_tax`, e o espelho `prototipo-ui/cowork/Wagner/manufacturing-*.jsx`.
- Três erros do rascunho foram corrigidos na conferência: a impressão estava dada como coberta por um caso que trata de outra coisa; o desperdício por ingrediente é o item 12 da PARIDADE, não o 13; e um dos casos citados para mão de obra não tratava disso.

## 3 · Fontes (acessadas em 2026-10-20)

- Odoo 18 — [Bill of materials](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/manufacturing/basic_setup/bill_configuration.html) (doc oficial) · [menu Operações](https://www.cybrosys.com/odoo/odoo-books/v18/manufacturing/operation-menu/) e [tipos de BoM](https://www.cybrosys.com/blog/what-are-the-different-types-of-bills-of-materials-in-odoo-18) (Cybrosys, terceiro)
- Katana — [Track production](https://katanamrp.com/solutions/track-production/) · [análise SoftwareConnect](https://softwareconnect.com/reviews/katana-mrp/) (terceiro, planos)
- MRPeasy — [Bill of Materials](https://www.mrpeasy.com/?p=2472) · [recursos por plano (PDF)](https://www.madesmarter.uk/media/b5fpo5ai/mrpeasy_features_by_plan.pdf)
- Omie — [estrutura dos produtos](https://ajuda.omie.com.br/pt-BR/articles/1426253-criando-a-estrutura-dos-seus-produtos) · [ordem de produção e mapa de custo](https://ajuda.omie.com.br/pt-BR/articles/9864358-visualizando-e-imprimindo-a-ordem-de-producao-e-o-mapa-de-custo) · [lote e validade](https://ajuda.omie.com.br/pt-BR/articles/10871466-realizando-o-controle-de-lote-e-validade-de-produtos)
- Bling — [lançamento do módulo de produção](https://blog.bling.com.br/bling-lanca-modulo-de-producao/)
- Internas — [estado da arte composição × produção (2026-10-09)](2026-10-09-arte-composicao-vs-producao.md) · [grade de produção, 10 concorrentes](../research/2026-05-prospeccao/33-grade-producao-concorrentes.md) · [PARIDADE da ordem de produção](../requisitos/Manufacturing/PARIDADE-ordem-producao-blade-vs-prototipo.md)
