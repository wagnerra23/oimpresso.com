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
  - Pedido: P-020
  - Âncora: `UC-FLX-05`
  - Fazer: Contador e total no relatório de comissões (thread `comissoes/02`), não na tela de comissionados, que não apura período.
  - Hoje: miss | Não tem | 
  - Comunicação visual: ok | Sim | modelo CV média com PCP
  - Clientes: Techpress
  - Etiquetas: miss, cv, decisao
- [BACKLOG] Agência como comissionado (pedido de 2026-10-23) `[V0]` — Dado uma agência cadastrada com
  10%, Quando a venda indicada por ela é faturada, Então a comissão da agência nasce sobre o valor
  faturado, separada da do vendedor. Fonte: o representante externo já existe em
  `sales_commission_agents`.
  - Pedido: P-021
  - Âncora: Representante externo (`sales_commission_agents`)
  - Fazer: Tipo "agência" no cadastro, ou usar o representante; comissão separada.
  - Hoje: part | Em parte | Representante externo existe
  - Comunicação visual: ok | Sim | agência comum em CV
  - Etiquetas: valor, part, cv, decisao
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
  - Pedido: P-067
  - Âncora: US-COMM-015 (PR #9971) · `Comissionados/Index.casos.md`
  - Fazer: Regra de comissão por produto, categoria ou geral, em % ou valor por unidade, sobre o item com desconto, sem imposto e sem o devolvido; vale a mais específica. O mesmo percentual entra na formação de preço (caso 107).
  - Hoje: miss | Não tem | Em andamento no PR #9971: o cálculo pela regra, atrás de uma flag desligada; falta a tela das regras. No main a comissão usa só o percentual do comissionado
  - Comunicação visual: ok | Sim | comissão diferente por serviço
  - Clientes: CyberStudio
  - Etiquetas: valor, miss, cv

## Decisões pendentes [W]

Perguntas do documento "Casos de uso por tela" que só o [W] responde. Cada uma diz quais pedidos ela destrava.

- **[DECISÃO] D-04 · Agência é um tipo próprio de comissionado?**
  - Estado: Em aberto
  - Opção: Tipo "agência" — Muda o cadastro de comissionados e separa a comissão da agência da do vendedor.
  - Opção: Entra como representante — Usa o representante externo que já existe, sem campo novo.
  - Destrava: P-021, P-014
  - Ligação: o documento não cita caso para esta decisão; a ligação com P-021 e P-014 (escolher o comissionado na venda, que inclui a agência) é inferida pelo assunto.
- **[DECISÃO] D-11 · Comissão: sobre qual valor e em que momento?**
  - Estado: Em aberto
  - Texto: Três textos discordam: o UC-FLX-05 lança no faturamento sobre o valor faturado, a US-COMM-015 calcula sobre o preço sem imposto, e o caso 109 calcula na leitura. A pesquisa de mercado de 28/10/2026 está no PR #10102.
  - Opção: Apura no faturamento, libera no recebimento — A comissão é gravada no faturamento, como no UC-FLX-05, e liberada por regra do vendedor (padrão: proporcional às parcelas recebidas). A base também é por vendedor. O caso 109 passa a ler o registro gravado. É a proposta.
  - Opção: Tudo no faturamento — Mais simples e igual ao fluxo atual do Wagner, mas pode contrariar a lei para representante e para vendedor que vende a prazo.
  - Destrava: P-109, P-156, P-020
  - Ligação: P-156 (comissão do representante liberada por parcela, como no Sabor Brasil) e P-020 (vendas sem comissionado no relatório, que depende da D-COM-2, comissão sobre venda paga ou faturada) são inferidas pelo assunto.

## Trilha do tempo
- 2026-10-30 · [W+C] O caso 14 passa a destravar com a D-04, e os casos 156 e 20 com a D-11; nenhum deles tinha pergunta.
- 2026-10-28 · [W+C] Decisões pendentes D-04, D-11 do documento "Casos de uso por tela", sem id de caso.
- 2026-10-27 · [F+C] Backlog do percentual de comissão no produto: a ordem vem da US-COMM-015 e entra o pedido da CyberStudio.
- 2026-10-23 · [M+C] Backlog de vendas sem comissionado e de agência como comissionado, sem id.
- 2026-10-06 · [CL] criado com a thread `sistema/playbook/03`.
