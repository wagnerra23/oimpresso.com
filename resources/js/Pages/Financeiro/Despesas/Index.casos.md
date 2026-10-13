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

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego à tela pelo menu: o dropdown core "Despesas" fica escondido com o Financeiro habilitado (decisão [W] 2026-05-20), e a sub-navegação do Financeiro ainda não tem a aba. O caminho de entrada é decisão antes de ligar a flag.
- **[BACKLOG]** Drawer da despesa e ações (editar, documento, excluir, pagamento) — US-MWART-017.
- **[BACKLOG]** Nova despesa — US-MWART-018.

## Trilha do tempo
- 2026-10-13 · [CL] carimbado por criar-tela.mjs e preenchido na US-MWART-015/016 (thread financeiro/13).
