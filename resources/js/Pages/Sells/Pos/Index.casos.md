---
id: resources-js-pages-sells-pos-index-casos
casos: Lista de POS · /pos
irmaos: Index.charter.md (lei) · tests/Feature/Sells/SellsPosIndexContratoTest.php (defesa) · e2e/sells-pos-index.spec.ts
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-01"
---

# Casos de Uso & Aceite — Lista de POS

> Textos revisados em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/ListaPos.casos.md` (2026-08-22). Os casos saem do charter e do legado `sale_pos/index`, não do `.tsx`.
>
> **Status:** ✅ passa (com prova no manifesto G-7) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
> Todos nascem ⬜: o teste roda na lane `sells-pest.yml` (MySQL) e ainda não tem run.

---

## UC-POS-01 · A lista mostra as vendas de POS com o rodapé somado
- **Persona:** Larissa, no fim do turno.
- **Aceite:** Dado 8 vendas de POS, quando abro a lista, então vejo as 8 com total, pago e saldo somados no rodapé.
- **Âncora:** charter R1 + R2.
- **Teste:** `tests/Feature/Sells/SellsPosIndexContratoTest.php` — rodapé vem de `totals` do servidor; venda direta (`is_direct_sale = 1`) e venda de outro business não entram.
- **Status: ⬜**

---

## UC-POS-02 · Filtro "Vencido" deixa só as vencidas e o rodapé recalcula
- **Persona:** Larissa cobrando quem passou do prazo.
- **Aceite:** Dado filtro "Vencido", quando aplico, então só linhas `overdue` restam e o rodapé recalcula.
- **Âncora:** charter R2 + R3.
- **Teste:** `tests/Feature/Sells/SellsPosIndexContratoTest.php`.
- **Status: ⬜**

---

## UC-POS-03 · Venda quitada não oferece "Adicionar pagamento"
- **Persona:** operador de balcão.
- **Aceite:** Dado uma venda quitada, quando abro as ações, então "Adicionar pagamento" não é oferecido.
- **Âncora:** charter R4.
- **Teste:** `tests/Feature/Sells/SellsPosIndexContratoTest.php` — a linha quitada chega com saldo zero (a Page só oferece a ação com saldo > 0). O clique no menu está no backlog do E2E.
- **Status: ⬜**

---

## UC-POS-04 · Sem `sell.delete`, "Excluir" aparece dizendo o motivo
- **Persona:** papel Balcão.
- **Aceite:** Dado papel Balcão, quando abro as ações, então "Excluir" aparece dizendo que falta `sell.delete`.
- **Âncora:** charter R5.
- **Teste:** `tests/Feature/Sells/SellsPosIndexContratoTest.php` — a Page recebe `permissions.delete = false` e o usuário segue vendo a lista.
- **Status: ⬜**

---

## UC-POS-05 · Período "Hoje" deixa só as vendas do dia
- **Persona:** Larissa conferindo o turno.
- **Aceite:** Dado período "Hoje", quando aplico, então só vendas do dia restam.
- **Âncora:** charter R6.
- **Teste:** `tests/Feature/Sells/SellsPosIndexContratoTest.php` — `date_to` com 23:59:59 inclui a venda das 15h do dia.
- **Status: ⬜**

---

## UC-POS-06 · "Ver detalhe" abre o drawer com os itens da venda
- **Aceite:** Dado clique em "Ver detalhe", então abre o drawer com itens que somam exatamente o total da venda.
- **Âncora:** charter Non-Goal 1 (o drawer é o `SaleSheet` do Sells/Index, ponte).
- **Teste:** `e2e/sells-pos-index.spec.ts` (stub `test.fixme`, pendente).
- **Status: ⬜**

---

## UC-POS-07 · "Imprimir recibo" abre a folha de impressão
- **Aceite:** Dado clique em "Imprimir recibo", então abre a folha com os 9 layouts do legado.
- **Teste:** `e2e/sells-pos-index.spec.ts` (stub `test.fixme`, pendente).
- **Status: ⬜**

---

## UC-POS-08 · "Devolver venda" leva à devolução com a venda no contexto
- **Aceite:** Dado clique em "Devolver venda", então vou pra tela de devolução com a venda no contexto.
- **Teste:** `e2e/sells-pos-index.spec.ts` (stub `test.fixme`, pendente).
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Rodapé conta por status de pagamento e por forma (segunda metade da R2).
- **[BACKLOG]** Filtros de local, cliente, vendedor e tipo de serviço.

## Trilha do tempo
- 2026-10-01 · [CL] trio trazido do cowork-inbox para o lado do `.tsx` (thread 01 do playbook venda-menu).
