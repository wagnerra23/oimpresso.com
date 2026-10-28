---
casos: Editar transferência de estoque · /stock-transfers/{id}/edit
irmaos: Edit.charter.md (lei) · Edit.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a edição corrige uma transferência ainda não concluída; concluir pela edição move o saldo das duas filiais.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

# Casos de Uso & Aceite — Editar transferência de estoque

> **Fonte:** [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) §3 + [RUNBOOK da tela](../../../../memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-edit.md) (paridade com `stock_transfer/edit.blade.php`); o controller só confirma. Nada aqui deriva do `.tsx`.
> Grava quantidade: conserto de qualquer item segue a REGRA MESTRE e o merge é do [W].
> **Teste:** [`tests/Feature/Estoque/EstoqueTransferenciaEditTest.php`](../../../../tests/Feature/Estoque/EstoqueTransferenciaEditTest.php) (tenant do CI × 2º business · lane `PHP / Pest (Estoque · MySQL)`).
> **Status:** ✅ passa (G-7) · 🧪 cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-TREDT-01 · Só a própria empresa e só quem pode lançar `[T0]` `[must]`
- **Persona:** quem cuida do estoque e corrige uma transferência que ainda não foi concluída.
- **Aceite:** Dado uma transferência pendente do business 98 · Quando outro business abre `/stock-transfers/{id}/edit` · Então recebe 404; e um usuário sem `purchase.create` recebe 403. O próprio usuário abre (par positivo).
- **Teste:** `EstoqueTransferenciaEditTest` — `UC-TREDT-01`.
- **Status: 🧪**

## UC-TREDT-02 · Abre com o que está gravado e com origem e destino travados `[must]`
- **Persona:** quem cuida do estoque e corrige uma transferência que ainda não foi concluída.
- **Aceite:** Dado uma transferência pendente de 3 un · Quando abro a edição · Então a tela React recebe a transferência com status, origem, destino e uma linha de 3 un (ligada à linha de venda gravada) com o saldo da **origem**; `?classico=1` ainda abre a Blade até o cutover.
- **Teste:** `EstoqueTransferenciaEditTest` — `UC-TREDT-02`.
- **Status: 🧪**

## UC-TREDT-03 · Editar e concluir move a quantidade nova das duas filiais `[V0]` `[must]`
- **Persona:** quem cuida do estoque e corrige uma transferência que ainda não foi concluída.
- **Aceite:** Dado a pendente de 3 un com origem 10 e destino 2 · Quando edito para 4 un e salvo como concluída no formato da tela React (data ISO, vírgula decimal) · Então a origem fica com 6, o destino com 6, o total continua 12, a transferência fica `final` com a data nova, e a linha é editada (continua 1, com 4 un a 12,50), não duplicada.
- **Teste:** `EstoqueTransferenciaEditTest` — `UC-TREDT-03`.
- **Regressão que defende:** o `update()` só lia a data no formato da empresa (`uf_date`); a tela manda ISO. Agora usa `uf_datetime_input`, como o `store()`.
- **Status: 🧪**
