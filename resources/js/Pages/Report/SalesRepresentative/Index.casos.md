---
id: resources-js-pages-report-salesrepresentative-index-casos
casos: Comissão por vendedor · /reports/sales-representative-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto o vendedor recebe; número diferente do relatório antigo ou comissão de outro negócio é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Comissão por vendedor

> Thread `comissoes/playbook/02`. Derivados da ficha da thread e do `ReportController` (linhas 1221–1360) +
> `public/js/report.js` (`salesRepresentativeTotal*`), medidos em 2026-10-06 — não do `Index.tsx`.
> Teste: [`tests/Feature/Users/SalesRepresentativeReportPageTest.php`](../../../../../tests/Feature/Users/SalesRepresentativeReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-COM-03 · Mesmo filtro, mesmo total que o relatório antigo · `must` `[T0]` `[valor]`
- **Aceite:** Dado vendedor (5%), período e local, com uma venda de 2 × 150 · Quando peço
  `/reports/sales-representative-total-commission` · Então `total_sales_with_commission = 300` e
  `total_commission = 15`, igual à conta refeita no teste · E outro vendedor no mesmo filtro dá 0 (controle
  positivo) · E a Page `Report/SalesRepresentative/Index` não recebe total nenhum: mostra o JSON dos mesmos
  três endpoints que a Blade usa.
- **Status: 🧪**

## UC-COM-04 · Mesma permissão da Blade, vendedores só do meu negócio · `must` `[T0]`
- **Medido antes:** a Blade e os três endpoints exigem só `sales_representative.view`, e o filtro de vendedor
  lista todos os usuários do negócio — ela **não** trava no usuário logado. A tela mantém (travar é decisão [W]).
- **Aceite:** Dado `sales_representative.view` · Então `vendedores` traz usuário do meu negócio e não traz o
  de outro negócio · Dado usuário sem a permissão · Então 403, na tela nova e na Blade.
- **Status: 🧪**

## UC-SBCOM-01 · A venda nasce com o % de comissão do cliente congelado · `must` `[T0]` `[valor]`
- **Medido antes (Delphi Sabor Brasil, `COMISSAO_FINANCEIRO`, 2026-10-29):** o % do lançamento é o gravado na
  venda (`VENDA.COMISSAO_REPRESENTANTE`) em 1.318 de 1.643 lançamentos, e o % atual do cliente em só 1.105 —
  o cliente muda de % e a venda antiga não.
- **Aceite:** Dado cliente com `comissao_representante_percentual = 7,5` · Quando nasce a venda dele · Então
  `transactions.comissao_cliente_percentual = 7,5` · E mudar o cliente para 15 depois não muda a venda · Dado
  cliente sem % · Então a venda nasce sem % (a regra não liga para ele).
- Teste: [`tests/Feature/Sells/ComissaoPorClienteContratoTest.php`](../../../../../tests/Feature/Sells/ComissaoPorClienteContratoTest.php), lane `sells-pest.yml`.
- **Status: 🧪**

## Backlog
- [BACKLOG] As 4 abas de listagem da Blade (vendas, vendas com comissão, despesas, pagamentos com comissão) — PR seguinte, antes do cutover.
- [BACKLOG] Travar o vendedor no usuário logado quando ele não for gestor — decisão [W] (a Blade não faz).
- [BACKLOG] Comissão pela regra, com o valor a pagar (pedido de 2026-10-27, US-COMM-016) `[V0]` — Dado um vendedor com vendas no mês, itens com regra de produto de 2% e um item sem regra · Quando abro o relatório do mês · Então ele mostra o total a pagar ao vendedor pela regra da US-COMM-015 e o detalhe por venda e item: base, regra aplicada e comissão; o item sem regra aparece com zero, e o detalhe sai em CSV. Fica atrás da flag `SELLS_COMISSAO_POR_REGRA`; com ela desligada o relatório não muda. O número é calculado na leitura: congelar o que foi pago e estornar são das US-COMM-002 e 003. **Depende do [W]:** nos modelos de Fluxos dele (UC-FLX-05), a comissão nasce no faturamento, lançada sobre o valor faturado; a US-COMM-015 calcula sobre o preço sem imposto, e este caso calcula na leitura. Qual base e qual momento valem é decisão dele. Pedido da CyberStudio, que hoje calcula a comissão à mão a partir do relatório de produtos vendidos.
  - Pedido: P-109
  - Âncora: US-COMM-016 (PR #10076) · relatório de comissão por vendedor
  - Fazer: Somar a regra da US-COMM-015 por vendedor no período, dentro do relatório de comissão que já existe. O número é calculado na leitura; congelar o que foi pago e estornar ficam para as US-COMM-002 e 003.
  - Hoje: miss | Não tem | O relatório usa só o percentual fixo do vendedor
  - Comunicação visual: ok | Sim | comissão diferente por serviço
  - Clientes: CyberStudio
  - Etiquetas: valor, miss, cv, decisao

## Pedidos de clientes

> Pedidos de clientes registrados com `node scripts/casos-backlog/novo.mjs`. Cada um aparece na
> página "Casos de uso por tela"; quando ganhar teste, vira caso de uso com id e a linha `Pedido de origem`.

- `[BACKLOG]` **Comissão do representante por cliente, liberada quando o cliente paga a parcela** `[V0]` — Dado
  um representante com 10% no cliente A e 2% no cliente B, Quando o cliente A paga a primeira de três
  parcelas, Então só a comissão dessa parcela fica liberada para pagar; as outras duas seguem em aberto e a
  parcela vencida fica pendente. O percentual mora no cadastro do cliente. O Sabor Brasil trabalha assim no
  Delphi: 1.643 lançamentos de comissão por parcela, percentuais de 2% a 15%, três representantes ativos. Mexe
  em valor: Regra Mestre. Pedido de Sabor Brasil. Falta decisão [W]: Momento da comissão: na parcela recebida
  (como o Sabor Brasil) ou no faturamento (modelos de Fluxos do Wagner). Mexe em valor ou estoque: Regra
  Mestre.
  - Pedido: P-156
  - Âncora: US-COMM-016 · Comissionados/Index.casos.md
  - Fazer: Percentual de comissão por cliente e liberação por parcela recebida, com o relatório mostrando aberto, liberado e vencido por representante.
  - Hoje: part | UC-SBCOM-01 | a venda já nasce com o % do cliente; falta o cálculo por parcela (PR seguinte), a seção na tela e o campo % no cadastro do cliente
  - Clientes: Sabor Brasil
  - Etiquetas: valor, miss, decisao
