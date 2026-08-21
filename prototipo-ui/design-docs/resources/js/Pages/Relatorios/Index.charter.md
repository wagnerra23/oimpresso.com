---
id: resources-js-pages-relatorios-index-charter
page: /relatorios
component: resources/js/Pages/Relatorios/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-21"
parent_module: Relatórios
states: [indice, relatorio, sem-resultado]
related_prototype: prototipo-ui/cowork/relatorios/relatorios-page.jsx
contrato: prototipo-ui/contrato/relatorios.contract.json
tier: A
charter_version: 1
---

# Page Charter — /relatorios (o menu de relatórios do vivo, com cabeça de gráfica)

> **Import, não invenção.** 23 dos 27 relatórios são tradução 1:1 de `resources/views/report/*`:
> mesmos filtros (`Form::label`/`Form::select` lidos do blade), mesmas colunas (`<th>`), mesmo
> rodapé de total (`<tfoot>`). Os 4 restantes (grupo **Gráfica**) não existem no Blade e estão
> marcados como tal — dependem de decisão [W] antes de virar tela.
>
> **Persona:** Wagner (escritório 1440px, decide preço e margem) e Eliana (financeiro, tabelas
> densas) vivem aqui. Larissa entra pontualmente — pra conferir estoque e venda do dia.

---

## Mission (1 frase)

Dar a todo relatório do ERP um único lugar com a mesma anatomia — filtros do domínio → apuração →
tabela densa com totais → destino no módulo de origem — sem que ninguém precise decorar em qual
menu o número mora.

---

## Goals — faz

- Índice agrupado (Financeiro · Comercial · Estoque · Fiscal · Gráfica · Sistema) com busca por
  nome, caminho do blade ou pelo nome antigo do menu
- Cada card declara **de onde vem**: caminho do blade + rótulo do menu legado (ou "não existe no vivo")
- Tela de relatório com a anatomia fixa: barra de origem → filtros → KPI/resumo → abas → tabela
- Filtros **exatamente** os do blade — inclusive dois períodos onde o blade tem dois
  (`items_report`: data da compra e data da venda) e faixa de hora (`product_sell_report`)
- Colunas condicionais do vivo (`$product_custom_field1..4`, `current_stock_mfg`) como colunas
  opcionais no menu **Colunas**, ocultas por padrão e persistidas por relatório/aba
- Coluna de **Ação** (a `messages.action` do blade) levando ao registro de origem no módulo
- Linha clicável abre **Drawer PT-02** com todos os valores da linha e de onde ela vem
- Seleção múltipla com **BulkBar** (exportar / imprimir / abrir no módulo)
- Paginação real (fatia de 10) e totais que somam a apuração inteira, não a página
- Modal de validade do lote (`partials/stock_expiry_edit_modal.blade.php`)
- Formatos especiais preservados: resumo de dois painéis (`profit_loss`, `purchase_sell`,
  `product_stock_details`), abas (`tax_report`, `product_sell_report`, `sales_representative`,
  `service_staff_report`), 4 totais (`stock_adjustment_report`), gráfico (`trending_products`)
- Impressão que sai só com o relatório — sem sidebar, sem filtros, sem barra de ações

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO cria arquivo novo por relatório** — tudo é rota do shell Cockpit V2; variação é Tweak
- ❌ **NÃO inventa filtro**: filtro que não está no blade não entra; filtro do blade não é omitido
- ❌ **NÃO traduz GST** (`gst_sales_report`, `gst_purchase_report`) — fiscal da Índia, declarado fora
- ❌ **NÃO recalcula regra fiscal nem margem** aqui: a apuração é do `ReportService`; a tela lê
- ❌ **NÃO esconde a origem** — todo relatório mostra seu blade; todo relatório novo diz que é novo
- ❌ **NÃO mostra número sem destino**: toda linha tem ação que leva ao registro no módulo
- ❌ **NÃO soma só a página visível** no rodapé de total — isso mentiria sobre o período

---

## UX targets

- Achar o relatório certo em ≤ 2 interações (busca ou grupo), inclusive quem só sabe o nome antigo
- Entender de onde o número vem sem sair da tela (rodapé mono + drawer "De onde vem")
- Tabela densa legível em 1280px (Larissa) e confortável em 1440px (Wagner)
- 0 erro de console; nenhuma cor fora dos tokens do DS; sem emoji

---

## Anti-hooks (sinais de drift)

- ⚠️ Aparecer relatório **sem blade declarado** e sem marca de "novo" — vira alucinação de escopo
- ⚠️ Aparecer **coluna que não existe no `<th>`** do blade sem virar coluna opcional documentada
- ⚠️ Aparecer **filtro genérico** ("status", "tipo") não rastreável a um `Form::select`
- ⚠️ Aparecer **tabela própria** com sort/resize em vez de `DataTablePro`
- ⚠️ Aparecer **modal full-screen** pra detalhe de linha (o canon é Drawer PT-02)
- ⚠️ Aparecer **total do rodapé** que muda ao paginar
- ⚠️ Aparecer **`.html` novo** pra um relatório ou variação

---

## Dependências de decisão ([W])

1. **Grupo Gráfica entra?** m² produzido vs vendido, sobra de bobina, lucro por OS e retrabalho não
   existem no vivo — precisam de fonte de dado (OP + apontamento de máquina) antes de virar tela.
2. **Custo de hora de máquina** — de onde vem (cadastro por máquina? rateio do fixo?) pra "Lucro por OS".
3. **Permissão** — `relatorios.view` única ou por grupo (financeiro vê lucro, balcão não)?
4. **Export real** — CSV/PDF pelo `ReportService` ou pelo DataTables do legado?
5. **`sale_report`/`purchase_report`** herdam os filtros da tela de Vendas/Compras (o blade é só a
   tabela) — mantemos filtro duplicado aqui ou a tela abre já filtrada pelo módulo?
