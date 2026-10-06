---
casos: Novo ajuste de estoque · /stock-adjustments/create
irmaos: Create.charter.md (lei) · Create.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o ajuste baixa saldo; o formulário não pode oferecer filial de outra empresa nem abrir para quem não pode lançar.
owner: wagner
last_run: "2026-10-06"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

# Casos de Uso & Aceite — Novo ajuste de estoque

> **Fonte:** [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) §3/§6/§7 + RUNBOOK da tela (`memory/requisitos/Estoque/_telas/`); o controller só confirma. Nada aqui deriva do `.tsx`.
> Ajuste e transferência gravam quantidade: conserto de qualquer item do Backlog segue a REGRA MESTRE e o merge é do [W].
> **Teste:** `tests/Feature/Stock/MovimentacoesTelasContratoTest.php` (só leitura · tenants 98 × 99 · lane `PHP / Pest (Estoque · MySQL)`).
> **Status:** ✅ passa (G-7) · 🧪 cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-AJCRT-01 · Só abre para quem pode lançar `[must]`
- **Aceite:** Dado um usuário só com `purchase.view` · Quando abre `/stock-adjustments/create` · Então recebe 403.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJCRT-01 e UC-TRCRT-01 sem purchase.create os dois formulários respondem 403`.
- **Regressão que defende:** R-ADJ-004 do charter (`purchase.create` obrigatória).
- **Status: ⬜**

## UC-AJCRT-02 · Só oferece filiais do próprio business `[T0]` `[must]`
- **Aceite:** Dado filiais no business 98 e no 99 · Quando abro o formulário pelo browser · Então `business_locations` traz a minha filial e não a do 99.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJCRT-02 [T0] o formulário de ajuste só oferece filiais do próprio business`.
- **Regressão que defende:** baixa de saldo em filial alheia escolhida na tela (INV-6).
- **Status: ⬜**

## Backlog
- [BACKLOG] `store()` não confere se `location_id` é do business da sessão; a filial chega do formulário e vai direto para `decreaseProductQuantity`. Grava quantidade: decisão [W].
- [BACKLOG] R-ADJ-003 (valor recuperado ≤ total) só é conferida no cliente; `store()` não valida.
