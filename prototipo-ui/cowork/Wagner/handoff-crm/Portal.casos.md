---
id: resources-js-pages-crm-portal-casos
casos: Portal do contato · /contact/*
irmaos: Portal.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente logado + critério de aceite verificável
por_que: é a única superfície do ERP que um terceiro acessa — vazamento aqui é incidente LGPD, não bug de tela
owner: wagner
last_run: "—"
---

# Casos de Uso & Aceite — Portal do contato

> **Status:** ⬜ não verificado. O `main` tem `MultiTenantIsolationTest` e `LgpdComplianceTest` no módulo — os UC de isolamento abaixo devem ser amarrados a eles antes de qualquer coisa nova.

---

## UC-CRMPO-01 · O cliente vê o próprio saldo e só o próprio
- **Persona:** Daniela (Rota Livre) — quer saber quanto está em aberto.
- **Aceite:** Dado dois contatos com títulos · Quando Daniela abre o portal · Então os números somam apenas lançamentos do `contact_id` dela dentro do `business_id` dela, e nenhum documento de outro contato aparece em nenhuma aba.
- **Teste:** ⬜ a escrever (amarrar a `MultiTenantIsolationTest`).

## UC-CRMPO-02 · Os blocos seguem o tipo do contato
- **Persona:** cliente que só compra (type=customer).
- **Aceite:** Dado um contato `customer` · Quando o painel renderiza · Então os blocos de compra não vêm no payload; para `both`, vêm os dois; para `supplier`, só os de compra.
- **Teste:** ⬜ a escrever.

## UC-CRMPO-03 · Solicitação de pedido não vira venda
- **Persona:** Daniela — pede 3 banners às 22h.
- **Aceite:** Dada uma solicitação enviada · Quando ela é gravada · Então nasce como pedido pendente de aprovação, sem transação de venda, sem baixa de estoque e sem documento fiscal; o operador vê na lista de "Pedido de ordem".
- **Teste:** ⬜ a escrever.

## UC-CRMPO-04 · Cadastro fiscal não muda pelo portal
- **Persona:** cliente tentando corrigir a razão social.
- **Aceite:** Dado um contato · Quando ele salva o perfil com nome/CNPJ alterados no payload · Então o backend ignora/recusa esses campos e só persiste contato, e-mail, telefone e endereço.
- **Teste:** ⬜ a escrever.

## UC-CRMPO-05 · Pedido desabilitado esconde a função inteira
- **Persona:** negócio com `enable_order_request` off.
- **Aceite:** Dado o setting off · Quando o cliente abre o portal · Então a aba de pedidos não existe e a rota de criação recusa (não é só botão escondido).
- **Teste:** ⬜ a escrever.

## Backlog de casos

- **[BACKLOG]** Extrato em PDF sai com os mesmos totais da tela (dupla confirmação, padrão do Ledger do Cliente).
- **[BACKLOG]** Agendamento recusa janela no passado.

## Rastreabilidade

| UC | CU (SDD) | US (SPEC) |
|---|---|---|
| UC-CRMPO-01 … 05 | — | — |
