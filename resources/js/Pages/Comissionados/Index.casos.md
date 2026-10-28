---
id: resources-js-pages-comissionados-index-casos
casos: Comissionados · /sales-commission-agents
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o percentual define quanto o vendedor recebe; valor gravado errado ou comissionado de outro negócio na tela é incidente.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Comissionados

> Thread `sistema/playbook/03` (= `comissoes/playbook/01`). Derivados do comportamento do
> `SalesCommissionAgentController` (Blade `sales_commission_agent/*` e as guardas de #5970/#6069/#6072,
> medidos em 2026-10-06) e do protótipo `comissionados-page.jsx` — não do `Index.tsx`.
> Teste: [`tests/Feature/Users/ComissionadosContratoTest.php`](../../../../tests/Feature/Users/ComissionadosContratoTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-CMSN-01 · Abrir os comissionados em React
- **Persona:** quem cadastra os agentes de venda e o percentual de cada um.
- **Aceite:** Dado `commission_agent.manage` · Quando faço `GET /sales-commission-agents` como o browser faz
  (`X-Inertia` **e** `X-Requested-With`) · Então renderiza Inertia **`Comissionados/Index`** com `pode.gerenciar = true`
  — não o JSON da DataTable antiga.
- **Status: 🧪**

## UC-CMSN-02 · A lista é do meu negócio, com quantas vendas apontam para cada um · `[T0]`
- **Aceite:** Dado um comissionado meu com 2 vendas vinculadas e um comissionado de outro negócio · Quando a
  lista carrega (prop deferida `agentes`) · Então vejo o meu com `vendas = 2` e não vejo o alheio.
- **Status: 🧪**

## UC-CMSN-03 · O percentual chega igual · `[valor]`
- **Persona:** quem cadastra "Larissa 2,5%" ou corrige para "3,75%".
- **Aceite:** Dado o texto que a tela manda (`"2,50"`, `"3,75"`, `"10"`, `"0,00"`) · Quando cadastra · Então o
  banco guarda o mesmo número que o `num_uf` devolve para o texto (dois caminhos, um resultado) · E editar
  para `"1,25"` grava 1,25.
- **Status: 🧪**

## UC-CMSN-04 · Editar não alcança comissionado de outro negócio · `[T0]`
- **Aceite:** Dado um comissionado alheio · Quando envio `PUT /sales-commission-agents/{alheio}` · Então `success=false` e nada muda nele
  · E o do meu negócio é editado. A forma Blade `GET …/{id}/edit` não existe mais (404).
- **Status: 🧪** — até 2026-10-06 o `edit()` fazia `findOrFail($id)` sem negócio; em 2026-10-27 a Blade de edição saiu
  (cutover: o drawer React já editava por `PUT`) e o `update()` passou de `first()` (500 com id alheio) para `firstOrFail()`.
  Passou no run 40491610923 (lane Acessos · MySQL, 486 passed / 2718 assertions, PR #9947).

## UC-CMSN-05 · Quem só vê não ganha as ações
- **Aceite:** Dado só `commission_agent.view` · Quando abro a tela · Então ela renderiza com `pode.gerenciar = false`.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] KPIs, período, meta e situação de pagamento do protótipo — dependem da apuração (`comissoes/02`, D-COM-2).
- [BACKLOG] Regra por faixa de meta ou sobre margem — `Modules/Comissao` (ADR 0151, dormente).
- [BACKLOG] "Ver vendas atribuídas" no menu da linha, abrindo as vendas filtradas pelo agente.
- [BACKLOG] Vendas sem comissionado (pedido de 2026-10-23) — Dado vendas do período sem vendedor,
  representante nem agência vinculados, Então o relatório de comissões mostra quantas são e o total
  delas, com link para essas vendas na consulta, e nenhuma comissão é calculada para elas. Fonte:
  UC-FLX-05. Não fica nesta tela: o charter dela não apura período (o relatório é a thread
  `comissoes/02`, ainda sem tela, que depende da D-COM-2 do [W], comissão sobre venda paga ou
  faturada). Verificado contra as regras do [W] em 2026-10-23.
- [BACKLOG] Agência como comissionado (pedido de 2026-10-23) `[V0]` — Dado uma agência cadastrada com
  10%, Quando a venda indicada por ela é faturada, Então a comissão da agência nasce sobre o valor
  faturado, separada da do vendedor. Fonte: o representante externo já existe em
  `sales_commission_agents`.
- [BACKLOG] Percentual de comissão no produto (pedido de 2026-10-23) `[V0]` — Dado o produto
  "Adesivo recortado" com comissão de 8% e o vendedor com 5%, Quando ele vende esse produto e outro sem
  percentual, Então a comissão do adesivo usa 8% e a do outro usa 5%. O percentual fica no cadastro do
  produto. Hoje a comissão é só do comissionado (`users.cmmsn_percent`); o produto não tem o campo.
  A ordem quando os dois existem vem da US-COMM-015 (PR #9971, 2026-10-27, em andamento atrás de
  flag): vale a regra mais específica, a do produto, depois a da categoria, depois a regra geral do
  vendedor. A CyberStudio (pedido de 2026-10-27) guarda 2% por produto na formação de preço do
  sistema que usa hoje e calcula a comissão à mão; o mesmo percentual entra na formação de preço
  (US-PROD-032), e o valor a pagar sai no relatório de comissão
  (`Report/SalesRepresentative/Index.casos.md`, US-COMM-016).

## Trilha do tempo
- 2026-10-27 · [F+C] Backlog do percentual de comissão no produto: a ordem vem da US-COMM-015 e entra o pedido da CyberStudio.
- 2026-10-23 · [M+C] Backlog de vendas sem comissionado e de agência como comissionado, sem id.
- 2026-10-06 · [CL] criado com a thread `sistema/playbook/03`.
