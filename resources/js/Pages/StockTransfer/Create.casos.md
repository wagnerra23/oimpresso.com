---
casos: Nova transferência de estoque · /stock-transfers/create
irmaos: Create.charter.md (lei) · Create.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a transferência tira de uma filial e põe em outra; as duas pontas têm de ser da mesma empresa.
owner: wagner
last_run: "2026-10-06"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Estoque · MySQL)"
---

# Casos de Uso & Aceite — Nova transferência de estoque

> **Fonte:** [DOC-RAIZ-ESTOQUE](../../../../memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md) §3/§6/§7 + RUNBOOK da tela (`memory/requisitos/Estoque/_telas/`); o controller só confirma. Nada aqui deriva do `.tsx`.
> Ajuste e transferência gravam quantidade: conserto de qualquer item do Backlog segue a REGRA MESTRE e o merge é do [W].
> **Teste:** `tests/Feature/Stock/MovimentacoesTelasContratoTest.php` (só leitura · tenants 98 × 99 · lane `PHP / Pest (Estoque · MySQL)`).
> **Status:** ✅ passa (G-7) · 🧪 cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-TRCRT-01 · Só abre para quem pode lançar `[must]`
- **Aceite:** Dado um usuário só com `purchase.view` · Quando abre `/stock-transfers/create` · Então recebe 403.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-AJCRT-01 e UC-TRCRT-01 sem purchase.create os dois formulários respondem 403`.
- **Status: ⬜**

## UC-TRCRT-02 · Origem e destino só oferecem filiais do próprio business `[T0]` `[must]`
- **Aceite:** Dado filiais no business 98 e no 99 · Quando abro o formulário pelo browser · Então `business_locations` traz a minha filial e não a do 99, e `statuses` traz `pending`, `in_transit` e `completed`.
- **Teste:** `MovimentacoesTelasContratoTest` — `UC-TRCRT-02 [T0] origem e destino só oferecem filiais do próprio business, com os 3 status`.
- **Regressão que defende:** RUNBOOK §3 ("origem E destino DEVEM ser da MESMA business").
- **Status: ⬜**

## Backlog
- [BACKLOG] R-XFER-004 (origem ≠ destino) só é conferida no cliente (botão desabilitado). `store()` não compara `location_id` com `transfer_location_id`, nem confere se as duas filiais são do business da sessão. Grava quantidade: decisão [W].
