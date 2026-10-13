---
casos: Financeiro/Despesas/Index · /expenses
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Financeiro/Despesas/Index

> Derivados do RUNBOOK e do mapa de paridade (Blade + `ExpenseController` lidos em 2026-10-13), não do `.tsx`.
> Teste: `tests/Feature/Expense/DespesasIndexContratoTest.php` (lane `financeiro-pest`), tenant 98 × business 2.
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.

---

## UC-DESPES-01 `[T0]` · A lista da tela nova mostra só a despesa do meu negócio
- **Persona:** quem lança despesa no escritório.
- **Aceite:** Dado uma despesa do meu negócio e uma de outro negócio no MEU local · Quando abro a lista com a flag ligada · Então vejo a minha e não a outra.
- **Regressão que defende:** paridade itens 1 e 5 — o payload Inertia usa a mesma consulta do DataTable.
- **Status: 🧪**

## UC-DESPES-02 `[V0]` · Total e devido batem com a tela antiga, com o estorno negativo
- **Aceite:** Dado uma despesa de 150 com 50 pago e um estorno de 150 com 50 pago · Quando abro a lista nova e a antiga · Então total e devido são os mesmos nas duas (150/100 e −150/−100).
- **Regressão que defende:** paridade itens 6 e 7. É o cruzamento por dois caminhos da regra de VALOR (payload Inertia × `data-orig-value` do DataTable).
- **Status: 🧪**

## UC-DESPES-03 · Sem período, a lista abre no ano fiscal
- **Aceite:** Dado nenhuma data na URL · Quando abro a lista · Então `filtros.start_date`/`end_date` são o ano fiscal do negócio.
- **Regressão que defende:** paridade item 12.
- **Status: 🧪**

## UC-DESPES-04 · Com a flag desligada, ou com `?classico=1`, a tela é a Blade
- **Aceite:** Dado a flag desligada · Quando abro `/expenses` · Então recebo a Blade; e com a flag ligada e `?classico=1`, também.
- **Regressão que defende:** a ação dupla do F2 (US-MWART-015).
- **Status: 🧪**

## UC-DESPES-05 `[T0]` · Excluir pela tela apaga a minha despesa e não alcança a de outro negócio
- **Aceite:** Dado uma despesa minha e uma de outro negócio · Quando excluo pelo drawer (a mesma chamada `DELETE /expenses/{id}` da Blade) · Então a minha some e a outra continua.
- **Regressão que defende:** paridade item 20 — a tela nova não reimplementa a exclusão, chama o `destroy` existente.
- **Status: 🧪**

## UC-DESPES-06 `[V0]` · Nova despesa grava o total que digitei, e as duas listas mostram o mesmo número
- **Aceite:** Dado que digito 1.234,56 na Nova despesa · Quando cadastro · Então a tela envia "1234,56", o banco grava 1234.56, e a lista nova e a antiga mostram 1234.56.
- **Teste:** `DespesasIndexContratoTest` (servidor + duas leituras) e `tests/js/despesas-total-envio.test.ts` (o que a tela envia; nunca ponto, que o `num_uf` leria como milhar).
- **Status: 🧪**

## UC-DESPES-07 · A data da Nova despesa e a da Blade gravam o mesmo instante
- **Aceite:** Dado 05/03/2026 14:30 · Quando cadastro pela tela nova (ISO) e pela Blade (formato da empresa) · Então as duas gravam 2026-03-05 14:30:00.
- **Regressão que defende:** a troca de `uf_date` por `uf_datetime_input` no `createExpense`.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego à tela pelo menu: o dropdown core "Despesas" fica escondido com o Financeiro habilitado (decisão [W] 2026-05-20), e a sub-navegação do Financeiro ainda não tem a aba. O caminho de entrada é decisão antes de ligar a flag.
- **[BACKLOG]** Pagamento (adicionar e ver) direto na tela nova. Hoje o drawer manda para a tela clássica: as rotas de pagamento só devolvem fragmento de modal da Blade, e escrever valor aqui é decisão [W] (regra mestre de VALOR).
- **[BACKLOG]** Nova despesa: documento anexo, recorrência e linha de pagamento seguem na tela clássica.
- **[BACKLOG]** Categorias de despesa em React — resto da US-MWART-018.

## Trilha do tempo
- 2026-10-13 · [CL] carimbado por criar-tela.mjs e preenchido na US-MWART-015/016 (thread financeiro/13).
- 2026-10-13 · [CL] US-MWART-017: drawer 760 com os dados da linha + Editar, Documento, Excluir; UC-DESPES-05.
- 2026-10-13 · [CL] US-MWART-018 (parte 1): Nova despesa; UC-DESPES-06/07.
