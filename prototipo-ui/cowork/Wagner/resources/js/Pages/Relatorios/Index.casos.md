---
id: resources-js-pages-relatorios-index-casos
casos: Relatórios · /relatorios
irmaos: Index.charter.md (lei) · prototipo-ui/contrato/relatorios.contract.json (copy)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — "todo número tem origem declarada e destino no módulo" não muda quando um relatório ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Relatórios (`/relatorios`)

> **Âncora:** os UC derivam do charter irmão e dos blades de origem
> (`resources/views/report/*` — `Form::label` para filtro, `<th>` para coluna, `<tfoot>` para total),
> **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Regra de origem | Teste | Status |
|----|-------------|------|-----------------|-------|--------|
| UC-REL-01 | Todo relatório declara o blade de origem | must | charter §Goals · §Anti-hooks | — | ⬜ |
| UC-REL-02 | Filtros da tela = filtros do blade, sem sobra e sem falta | must | `Form::label` de cada blade | — | ⬜ |
| UC-REL-03 | Colunas = `<th>` do blade; condicionais entram ocultas | must | `partials/stock_report_table.blade.php` | — | ⬜ |
| UC-REL-04 | Total do rodapé soma a apuração inteira, não a página | must | `<tfoot>` dos blades | — | ⬜ |
| UC-REL-05 | Toda linha tem ação que leva ao registro de origem | must | coluna `messages.action` | — | ⬜ |
| UC-REL-06 | Detalhe de linha abre em Drawer, nunca modal full-screen | must | PT-02 · ADR do DS | — | ⬜ |
| UC-REL-07 | Relatório sem blade é marcado como novo | must `[T0]` | charter §Non-Goals | — | ⬜ |
| UC-REL-08 | Busca acha pelo nome antigo do menu | should | menu legado (screenshot [W]) | — | ⬜ |
| UC-REL-09 | Blades fora de escopo são declarados, não escondidos | must | GST/mesas | — | ⬜ |
| UC-REL-10 | Colunas visíveis persistem por relatório e aba | should | colvis do DataTable do vivo | — | ⬜ |
| UC-REL-11 | Impressão sai só com o relatório | should | charter §Goals | — | ⬜ |
| UC-REL-12 | Nenhum relatório vira arquivo novo | must `[T0]` | CLAUDE.md §App único | — | ⬜ |
| UC-REL-13 | Dois períodos onde o blade tem dois | must | `items_report.blade.php` | — | ⬜ |
| UC-REL-14 | Seleção habilita ações em massa e nada mais | should | BulkBar do DS | — | ⬜ |

---

## UC-REL-01 · Todo relatório declara o blade de origem · `must`

- **Persona:** [CL] Claude Code, pegando o protótipo pra traduzir em Inertia.
- **Aceite:** Dado qualquer relatório do índice · Quando [CL] abre o card ou a tela · Então o
  caminho do blade aparece em mono no card, na barra de origem e no rodapé da tabela — e, quando o
  relatório não tem blade, no lugar dele aparece "tela nova — sem blade de origem".

## UC-REL-02 · Filtros da tela = filtros do blade · `must`

- **Persona:** Eliana, conferindo se o relatório do Cowork filtra igual ao do sistema.
- **Aceite:** Dado o conjunto de `Form::label` do blade · Quando ela compara com a barra de filtros
  · Então cada rótulo tem um controle correspondente e **nenhum controle extra existe**. Casos
  verificados: `register_report` (usuário + status do caixa, sem local), `expense_report` (local +
  categoria, sem usuário), `activity_log` (por + tipo de registro, sem ação),
  `stock_report`/`lot_report`/`stock_expiry_report` (local + categoria + subcategoria + marca +
  unidade), `tax_report` (local + contato), `sales_representative` (agente + local).

## UC-REL-03 · Colunas = `<th>` do blade; condicionais ocultas · `must`

- **Persona:** Larissa, no balcão a 1280px, sem espaço pra coluna que não usa.
- **Aceite:** Dado o `stock_report` · Quando ela abre a tela · Então as 13 colunas do
  `stock_report_table.blade.php` aparecem e as 4 de campo personalizado + "Estoque atual
  (Manufatura)" começam **ocultas**, disponíveis no menu Colunas marcadas como opcionais.

## UC-REL-04 · Total do rodapé soma a apuração inteira · `must`

- **Persona:** Wagner, lendo o total de estoque a custo.
- **Aceite:** Dado um relatório com 24 linhas em 3 páginas · Quando ele navega da página 1 pra 3 ·
  Então os valores em "Total —" **não mudam** (somam as 24), e a contagem "N linhas apuradas ·
  página X de Y" acompanha a navegação.

## UC-REL-05 · Toda linha tem destino · `must`

- **Persona:** Eliana, achando uma baixa estranha em Pagamentos de venda.
- **Aceite:** Dado uma linha de `sell_payment_report` · Quando ela abre o menu de ação · Então
  existe "Ver pagamento" e "Ver venda", e escolher um navega pro módulo levando o contexto da linha.

## UC-REL-06 · Detalhe em Drawer · `must`

- **Aceite:** Dado uma linha qualquer · Quando o operador clica na linha (não no menu) · Então abre
  um Drawer lateral com todos os valores da linha + a seção "De onde vem"; `Escape` fecha; nenhum
  modal full-screen é usado pra detalhe.

## UC-REL-07 · Relatório novo é marcado · `must` `[T0]`

- **Persona:** [W] revisando escopo — precisa saber o que é import e o que é proposta.
- **Aceite:** Dado um dos 4 relatórios do grupo Gráfica · Quando a tela abre · Então o grupo tem o
  selo "novo — pendente de aprovação [W]", o card tem borda tracejada e a tela mostra um Alert
  dizendo que a leitura não vem de blade nenhum.

## UC-REL-08 · Busca pelo nome antigo · `should`

- **Aceite:** Dado que Larissa só conhece "relatório de ajuste de ações" · Quando ela digita isso na
  busca · Então "Ajustes de estoque" aparece (a busca cobre nome, blade, descrição e nome legado).

## UC-REL-09 · Fora de escopo declarado · `must`

- **Aceite:** Dado `gst_sales_report`, `gst_purchase_report` e `table_report` · Quando alguém abre o
  índice · Então existe o bloco "Blades que não vieram" com o caminho e o motivo de cada um —
  nunca omissão silenciosa.

## UC-REL-10 · Colunas persistem · `should`

- **Aceite:** Dado que Eliana esconde 3 colunas em `items_report` · Quando ela sai e volta (ou
  recarrega) · Então as mesmas colunas seguem escondidas, e a escolha de outra aba não é afetada.

## UC-REL-11 · Impressão limpa · `should`

- **Aceite:** Dado qualquer relatório · Quando o operador imprime · Então a folha sai com título,
  filtros aplicados e a tabela inteira — sem sidebar, sem barra de ações, sem scroll cortando linha.

## UC-REL-12 · Nenhum arquivo novo · `must` `[T0]`

- **Persona:** guard `cowork-ssot-guard.mjs`.
- **Aceite:** Dado o export do Cowork · Quando o guard roda · Então existe **um** `.html`
  (`oimpresso.com.html`), os 27 relatórios são rotas do shell, e o export em
  `prototipo-ui/cowork/relatorios/` tem só jsx/css — sem dupe `?v=`, sem `.bak`, sem memória.

## UC-REL-13 · Dois períodos · `must`

- **Aceite:** Dado `items_report` (o blade tem `ir_purchase_date_filter` e `ir_sale_date_filter`) ·
  Quando a tela abre · Então existem **duas** barras de período rotuladas "Data da compra" e
  "Data da venda", independentes.

## UC-REL-14 · Seleção em massa · `should`

- **Aceite:** Dado 0 linhas selecionadas · Então nenhuma barra de ação em massa existe · Quando 1+
  linha é marcada · Então a BulkBar aparece com contagem correta e as ações Exportar seleção /
  Imprimir seleção / Abrir no módulo — e fechar a barra limpa a seleção.
