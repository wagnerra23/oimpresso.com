---
casos: Transferências de estoque · /stock-transfers
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista mostra o que saiu de uma filial e entrou em outra; empresa misturada ou filtro quebrado escondem mercadoria em trânsito.
owner: wagner
last_run: "2026-10-06"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

# Casos de Uso & Aceite — Transferências de estoque (lista)

> **Fonte:** [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) §3/§6/§7 + RUNBOOK da tela (`memory/requisitos/Estoque/_telas/`); o controller só confirma. Nada aqui deriva do `.tsx`.
> Ajuste e transferência gravam quantidade: conserto de qualquer item do Backlog segue a REGRA MESTRE e o merge é do [W].
> **Teste:** `tests/Feature/Stock/MovimentacoesTelasContratoTest.php` (só leitura · tenants 98 × 99 · lane `PHP / Pest (Estoque · MySQL)`).
> **Status:** ✅ passa (G-7) · 🧪 cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-TRIDX-01 · A lista só mostra transferências do próprio business `[T0]` `[must]`
- **Aceite:** Dado uma transferência (par `sell_transfer` + `purchase_transfer`) no meu business e outra no 99 · Quando abro `/stock-transfers?v=2` · Então vejo a minha e não a do 99.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-TRIDX-01 [T0] a lista de transferências só mostra transferências do próprio business`.
- **Regressão que defende:** consulta sem `transactions.business_id` (ADR 0093). O caso exige a própria linha presente.
- **Status: ⬜**

## UC-TRIDX-02 · Sem permissão de compra, não abre `[must]`
- **Aceite:** Dado um usuário sem `purchase.view`, `purchase.create` e `view_own_purchase` · Quando abre a lista · Então recebe 403.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJIDX-02 e UC-TRIDX-02 sem permissão de compra as duas listas respondem 403`.
- **Status: ⬜**

## Backlog
- [BACKLOG] O filtro não volta a página: mesmo defeito de ordem `ajax()` × `X-Inertia` do `index()` de ajustes (ver `StockAdjustment/Index.casos.md`).
- [BACKLOG] `destroy()` busca a transferência só por `id` + `type`, sem `business_id` (mesmo achado do `destroy()` de ajuste). Grava quantidade: decisão [W].
- [BACKLOG] A lista corta em 200 linhas (`limit(200)`) sem paginação nem aviso.
