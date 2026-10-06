---
casos: Ajustes de estoque · /stock-adjustments
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista é onde se confere perda e quebra; se ela mistura empresa ou não filtra, o inventário é conferido errado.
owner: wagner
last_run: "2026-10-06"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

# Casos de Uso & Aceite — Ajustes de estoque (lista)

> **Fonte:** [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) §3/§6/§7 + RUNBOOK da tela (`memory/requisitos/Estoque/_telas/`); o controller só confirma. Nada aqui deriva do `.tsx`.
> Ajuste e transferência gravam quantidade: conserto de qualquer item do Backlog segue a REGRA MESTRE e o merge é do [W].
> **Teste:** `tests/Feature/Stock/MovimentacoesTelasContratoTest.php` (só leitura · tenants 98 × 99 · lane `PHP / Pest (Estoque · MySQL)`).
> **Status:** ✅ passa (G-7) · 🧪 cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-AJIDX-01 · A lista só mostra ajustes do próprio business `[T0]` `[must]`
- **Aceite:** Dado um ajuste no meu business e outro no business 99 · Quando abro `/stock-adjustments?v=2` · Então vejo o meu e não vejo o do 99.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJIDX-01 [T0] a lista de ajustes só mostra ajustes do próprio business`.
- **Regressão que defende:** consulta da lista sem `transactions.business_id` (ADR 0093). O caso exige o próprio ajuste na lista, para não passar com lista vazia.
- **Status: ⬜**

## UC-AJIDX-02 · Sem permissão de compra, não abre `[must]`
- **Aceite:** Dado um usuário sem `purchase.view`, `purchase.create` e `view_own_purchase` · Quando abre a lista · Então recebe 403.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJIDX-02 e UC-TRIDX-02 sem permissão de compra as duas listas respondem 403`.
- **Status: ⬜**

## Backlog
- [BACKLOG] O filtro de filial/período não volta a página: `index()` testa `request()->ajax()` antes de `X-Inertia`, e o Inertia 3.6.1 manda `X-Requested-With` em toda visita XHR — o `router.get(..., { only: ['rows','filters'] })` recebe o JSON do DataTables. Achado por leitura em 2026-10-06; conserto é só de leitura, fora desta thread.
- [BACKLOG] `[T0]` `destroy()` busca o ajuste por `id` + `type`, sem `business_id`: quem tem `purchase.delete` num business exclui ajuste de outro e devolve a quantidade ao estoque dele (`updateProductQuantity`). Achado por leitura em 2026-10-06; grava quantidade, decisão [W].
- [BACKLOG] A lista corta em 200 linhas (`limit(200)`) sem paginação nem aviso.
