---
id: resources-js-pages-crm-portal-casos
casos: Portal do contato · /contact/* (lote 3 do Crm, threads 18..25)
irmaos: Portal.charter.md (lei) · crm-portal.jsx + crm-portal.css (forma) · os-acompanhamento.jsx (aba Assistência)
tecnica: Caso de uso = narrativa do contato logado + critério de aceite verificável (Dado/Quando/Então) + o teste que prova
por_que: é a única superfície do ERP que um terceiro acessa com login — vazamento aqui é incidente LGPD, não bug de tela
owner: wagner
base: wagnerra23/oimpresso.com@main 0c29a8505b8b (rotas Modules/Crm/Routes/web.php:6, controllers lidos em 93ff8a960b74)
last_run: "—"
---

# Casos de uso & aceite — Portal do contato

> **Estatuto:** casos derivados do charter e do protótipo (`crm-portal.jsx`), **nunca do `.tsx`**
> (proibições §5). Cada thread 18..25 do `crm/playbook/00-INDICE.md` só fecha com o teste do seu UC
> no `main`. A forma (layout, copy, estados) vem do protótipo medido na A3; o comportamento vem daqui.
>
> **Quem é o usuário:** um `users` com `crm_contact_id` preenchido, que entra pelo grupo
> `prefix('contact')` (middleware `ContactSidebarMenu` + `CheckContactLogin`). **Não** é operador. A
> casca é a própria do contato (D6): sem `AppShellV2` e sem sidebar do ERP.
>
> **Os dois escopos, sempre juntos:** `business_id` da sessão **e** `contact_id` do usuário. Um teste
> que só prova o primeiro não prova o portal.

## Como provar (vale para todo UC)

| Camada | O que roda | Onde |
|---|---|---|
| **Contrato (comportamento)** | Pest, um `it('UC-CRMPO-NN · …')` por UC, tenant 98 (`seededTenant`) × 99 (`seededSupportClientTenant`) **e** contato A × contato B do mesmo negócio. Molde: `CrmPedidosContratoTest.php`. | `Modules/Crm/Tests/Feature/CrmPortal<Tela>ContratoTest.php`, lane `verticais-pest.yml` (MySQL) |
| **Discriminante** | Todo teste semeia **dois** registros que só um critério separa, e prova que o certo aparece e o errado não. Um teste que passa com um registro só não prova nada. | dentro de cada `it` |
| **Controle negativo** | Ao menos um teste por arquivo quebra de propósito (mutação) e o recibo cita que ele falhou. | `_saida-NN.md` da thread |
| **Forma (visual)** | `design-diff --compare --check` da Page contra o alvo `crm--portal-<tela>--index` (A3), tema escuro **e** claro, 1280px **e** 390px. Sem DIVERGE (bug). | recibo da thread |
| **A11y** | A1–A12 na Page: alvo ≥ 44px, foco visível, `aria-current` na navegação, `role="status"` nas trocas, nenhum `div` clicável. | recibo da thread |

`last_run` só muda com run de CI citado. "0 failed" com SKIP em SQLite não é verde (LC-13).

## Rastreabilidade

| UC | Caso de uso | Prio | Tela · thread | Teste (`it` com este id) |
|----|-------------|------|---------------|--------------------------|
| UC-CRMPO-01 | Vejo o meu saldo, e só o meu | must `[T0]` | Painel · 18 | `CrmPortalPainelContratoTest` |
| UC-CRMPO-02 | Os blocos seguem o meu tipo (cliente, fornecedor, os dois) | must | Painel · 18 | `CrmPortalPainelContratoTest` |
| UC-CRMPO-03 | O portal abre na casca do contato, não na do ERP | must | todas · 18 | `CrmPortalPainelContratoTest` |
| UC-CRMPO-04 | Meu cadastro fiscal não muda pelo portal | must `[T0]` | Perfil · 19 | `CrmPortalPerfilContratoTest` |
| UC-CRMPO-05 | Troco a minha senha, e só a minha | must | Perfil · 19 | `CrmPortalPerfilContratoTest` |
| UC-CRMPO-06 | Vejo as minhas compras e vendas, e só as minhas | must `[T0]` | Compras · 20 · Vendas · 21 | `CrmPortalComprasContratoTest` · `CrmPortalVendasContratoTest` |
| UC-CRMPO-07 | Filtro em aberto / quitadas | should | Vendas · 21 | `CrmPortalVendasContratoTest` |
| UC-CRMPO-08 | O PDF da fatura é a minha fatura | must `[T0]` | Vendas · 21 | `CrmPortalVendasContratoTest` |
| UC-CRMPO-09 | O extrato fecha com os meus títulos | must `[T0]` valor | Extrato · 22 | `CrmPortalExtratoContratoTest` |
| UC-CRMPO-10 | O PDF do extrato tem os mesmos totais da tela | must valor | Extrato · 22 | `CrmPortalExtratoContratoTest` |
| UC-CRMPO-11 | Peço um horário, e ele não reserva a produção | must | Agendamentos · 23 | `CrmPortalAgendamentosContratoTest` |
| UC-CRMPO-12 | Não peço horário no passado | must | Agendamentos · 23 | `CrmPortalAgendamentosContratoTest` |
| UC-CRMPO-13 | Minha solicitação de pedido não vira venda | must `[T0]` | Pedidos · 24 | `CrmPortalPedidosContratoTest` |
| UC-CRMPO-14 | O preço do pedido é o da minha tabela | must valor | Pedidos · 24 | `CrmPortalPedidosContratoTest` |
| UC-CRMPO-15 | Pedido desligado esconde a função inteira | must | Pedidos · 24 | `CrmPortalPedidosContratoTest` |
| UC-CRMPO-16 | Vejo as minhas OS na Assistência | must `[T0]` | Assistência · 25 | `CrmPortalAssistenciaContratoTest` (= UC-COS-24) |
| UC-CRMPO-17 | Aviso no Início quando uma OS está pronta ou espera orçamento | should | Painel · 18 + 25 | `CrmPortalPainelContratoTest` |
| UC-CRMPO-18 | Operador não entra no portal; contato não entra no ERP | must `[T0]` | todas · 18 | `CrmPortalPainelContratoTest` |

---

## UC-CRMPO-01 · Vejo o meu saldo, e só o meu · `must` `[T0]`
- **Persona:** Daniela (Rota Livre), quer saber quanto está em aberto.
- **Aceite:** Dado o contato A com vendas de 100,00 (pago 40,00) e o contato B, do mesmo negócio, com vendas de 900,00 · E o contato A' no negócio 99 · Quando A abre `contact/contact-dashboard` · Então "Você tem a pagar" = 60,00 e "em vendas" = 100,00 · E nenhum valor de B ou de A' entra na conta.
- **Teste:** semear os três; assert dos dois números **e** assert de que 900,00 não aparece em nenhuma prop.
- **Forma:** bloco `portal-saldo` (`crm-portal.jsx`, view `painel`).

## UC-CRMPO-02 · Os blocos seguem o meu tipo · `must`
- **Aceite:** Dado um contato `customer` · Então o payload traz o bloco de saldo de vendas e **não** traz compras · Dado `supplier` · Então só compras · Dado `both` · Então os dois · E "Saldo de abertura" só vem quando existe.
- **Teste:** três contatos, um por tipo, mesma requisição; assert das chaves presentes **e** ausentes.
- **Forma:** no `customer` sem abertura não há grade de KPI (os números já estão no bloco de saldo — não repetir).

## UC-CRMPO-03 · O portal abre na casca do contato · `must`
- **Aceite:** Dado o contato logado · Quando abre qualquer rota `contact/*` migrada · Então a Page renderizada usa o layout do contato (D6), e a resposta **não** traz as props de shell do ERP (`shell.menu` do admin).
- **Teste:** `assertInertia` do componente + ausência de `shell.menu` do admin.

## UC-CRMPO-04 · Meu cadastro fiscal não muda pelo portal · `must` `[T0]`
- **Persona:** cliente tentando corrigir a razão social.
- **Aceite:** Dado o contato A · Quando `POST contact-profile-update` leva `name`, `tax_number`, `contact_id`, `business_id` e `type` alterados junto com e-mail e telefone · Então só e-mail, telefone, pessoa de contato e endereço são gravados · E os outros cinco ficam como estavam.
- **Teste:** ler a linha antes e depois; assert campo a campo (os cinco iguais, os quatro trocados).

## UC-CRMPO-05 · Troco a minha senha, e só a minha · `must`
- **Aceite:** Dado a senha atual certa e as duas novas iguais · Então a senha do **usuário logado** muda · Com a atual errada · Então 422 e nada muda · E um `user_id` de outro no corpo é ignorado.
- **Teste:** dois usuários de contato; tentar trocar a do B pelo A; assert de que o hash do B não mudou.

## UC-CRMPO-06 · Vejo as minhas compras e vendas, e só as minhas · `must` `[T0]`
- **Aceite:** Dado vendas de A, de B (mesmo negócio) e de A' (negócio 99) · Quando A abre `contact-sells` / `contact-purchases` · Então vê só as dele · E cada linha traz número, data, local, situação do pagamento, total e saldo.
- **Teste:** número de cada venda semeada; `toContain` só o de A, `assertNotContains` os de B e A'.

## UC-CRMPO-07 · Filtro em aberto / quitadas · `should`
- **Aceite:** Dado uma venda quitada e uma com saldo · Quando A escolhe "Em aberto" · Então só a com saldo · "Quitadas" · Então só a outra · "Todas" · Então as duas · E as contagens dos filtros batem com a lista.
- **Forma:** `Segmented` com contagem no cabeçalho da seção.

## UC-CRMPO-08 · O PDF da fatura é a minha fatura · `must` `[T0]`
- **Aceite:** Dado a fatura de B · Quando A pede o PDF dela pelo id · Então 403/404 e nenhum byte do documento · E o PDF da fatura de A abre.
- **Teste:** requisição direta ao endpoint de PDF com o id de B, logado como A.

## UC-CRMPO-09 · O extrato fecha com os meus títulos · `must` `[T0]` valor
- **Aceite:** Dado A com venda 100,00, pagamento 40,00 e devolução 10,00 · Quando abre o extrato · Então os lançamentos aparecem agrupados por mês, em ordem de data · E o saldo final = 50,00 · E bate com a soma das linhas (dupla prova: o valor semeado e a soma devolvida, como o UC-CRMPED-04).
- **Regra mestre de valor:** se a thread mexer no cálculo do saldo (`getLedger`), precisa de aprovação [W]. Se só trocar a tela, não.

## UC-CRMPO-10 · O PDF do extrato tem os mesmos totais da tela · `must` valor
- **Aceite:** Dado o extrato do UC-CRMPO-09 · Quando A gera o PDF · Então débito, crédito e saldo do PDF são iguais aos da tela no mesmo período.

## UC-CRMPO-11 · Peço um horário, e ele não reserva a produção · `must`
- **Aceite:** Dado A · Quando envia dia, horário (de 30 em 30 min, 8h–17h30), duração e local · Então nasce um agendamento `booked` do contato A naquele negócio · E nada é criado na agenda de produção nem em OS · E a lista de A mostra o pedido.
- **Forma:** `DatePicker` do DS + horário + duração (view `agendar`).

## UC-CRMPO-12 · Não peço horário no passado · `must`
- **Aceite:** Dado um dia anterior a hoje · Quando A envia · Então 422 "Escolha um dia a partir de hoje" e nada é gravado.

## UC-CRMPO-13 · Minha solicitação de pedido não vira venda · `must` `[T0]`
- **Persona:** Daniela pede 3 banners às 22h.
- **Aceite:** Dado A com o pedido ligado · Quando envia 2 itens · Então nasce uma `sales_order` com `crm_is_order_request = 1`, contato A, status pendente · E **não** nasce venda final, baixa de estoque nem documento fiscal · E o operador vê a solicitação em `/crm/order-request` (thread 17).
- **Teste:** contar `transactions` de tipo `sell`, `stock` e notas antes e depois (os três iguais); a `sales_order` existe.

## UC-CRMPO-14 · O preço do pedido é o da minha tabela · `must` valor
- **Aceite:** Dado o produto P com preço de venda 50,00 e preço do grupo de A 42,00 · Quando A monta o pedido · Então a linha mostra 42,00 e o total estimado usa 42,00 · E o servidor grava o preço que **ele** calcula, ignorando um preço mandado no corpo.
- **Teste:** mandar `unit_price = 1,00` no POST; assert de que a linha gravada tem 42,00.

## UC-CRMPO-15 · Pedido desligado esconde a função inteira · `must`
- **Aceite:** Dado `crm_settings.enable_order_request` desligado · Então a aba Pedidos não vem no menu do contato **e** o POST de criação recusa (403) — não é só botão escondido.

## UC-CRMPO-16 · Vejo as minhas OS na Assistência · `must` `[T0]`
- **Aceite:** = **UC-COS-24** (`cowork-inbox/repair/ConsultaOs-link.casos.md`): só OS com `contact_id` = A e `business_id` da sessão; mesmo cartão (`os-acompanhamento.jsx`) e mesma whitelist da página do link.
- **Teste:** OS de A, de B e de A' (negócio 99); só a de A aparece.

## UC-CRMPO-17 · Aviso no Início · `should`
- **Aceite:** Dado uma OS de A pronta há 4 dias e outra com orçamento enviado · Quando A abre o Início · Então aparece "Pronto para retirada há 4 dias" e "Orçamento aguardando sua aprovação", cada um levando à Assistência · Com 7 dias ou mais o aviso de pronto muda de tom · Sem OS nessas situações, nenhum aviso.

## UC-CRMPO-18 · Cada um na sua porta · `must` `[T0]`
- **Aceite:** Dado um operador (sem `crm_contact_id`) · Quando abre `contact/contact-dashboard` · Então é recusado pelo `CheckContactLogin` · Dado o contato A · Quando abre `/home` ou `/crm/leads` · Então é recusado e nada do ERP renderiza.

## Backlog de casos
- **[BACKLOG]** Logout do contato derruba só a sessão dele (multi-dispositivo).
- **[BACKLOG]** Mudança de e-mail pede confirmação no e-mail novo.
