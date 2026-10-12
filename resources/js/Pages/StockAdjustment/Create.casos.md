---
casos: Novo ajuste de estoque · /stock-adjustments/create
irmaos: Create.charter.md (lei) · Create.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o ajuste baixa saldo; o formulário não pode oferecer filial de outra empresa nem abrir para quem não pode lançar.
owner: wagner
last_run: "2026-10-11"
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

## UC-AJCRT-03 · O ajuste baixa do local a quantidade ajustada, nos dois formatos de envio `[V0]` `[must]`
- **Persona:** quem cuida do estoque e dá baixa de perda, quebra ou correção de contagem.
- **Aceite:** Dado um produto com 10 no local · Quando salvo um ajuste de 3 unidades · Então o local fica com 7. O resultado é o mesmo quando o envio vem no formato da tela React (data ISO, vírgula decimal sem milhar) e no formato da Blade (data e números no formato da empresa). Preço com 3 casas (`12,125`), preço de milhar (`1500`) e valor recuperado (`1234,5`) são gravados sem inflar.
- **Teste:** [`EstoqueAjusteStoreTest`](../../../../tests/Feature/Estoque/EstoqueAjusteStoreTest.php) — os 3 casos `UC-AJCRT-03`.
- **Contrato:** DOC-RAIZ-ESTOQUE §3 (`stock_adjustment`: SAI do local pela quantidade ajustada).
- **Regressão que defende:** a tela React mandava a data em ISO e o `store()` só lia o formato da empresa; e número com ponto decimal é lido como milhar pelo `num_uf` (incidente de 2026-06-05).
- **Status: ⬜** — nasce neste PR; verde esperado. Só a lane decide.

## UC-AJCRT-04 · O ajuste baixa só o local escolhido `[must]`
- **Aceite:** Dado o mesmo produto com saldo em dois locais · Quando ajusto 2 unidades num deles · Então só aquele local baixa; o outro não muda.
- **Teste:** `EstoqueAjusteStoreTest` — `UC-AJCRT-04`.
- **Contrato:** DOC-RAIZ-ESTOQUE §2 (o saldo é por variação × local).
- **Status: ⬜**

## UC-AJCRT-05 · Os itens são produtos do cadastro, escolhidos na busca da filial `[must]`
- **Persona:** quem cuida do estoque, lançando a baixa.
- **Aceite:** Dado a filial escolhida · Quando busco um produto · Então a busca procura no saldo **daquela filial**: produto sem controle de estoque não aparece e produto sem saldo aparece desabilitado. A linha entra com o saldo do local, a unidade e o último custo de compra, **sem** subunidade; o mesmo produto pode entrar em mais de uma linha (lotes diferentes); a quantidade não passa do saldo (nem do lote); e o que vai no salvar são os campos da linha da Blade, com vírgula decimal e sem milhar.
- **Teste:** [`estoque-itens.test.tsx`](../../../../tests/js/estoque-itens.test.tsx) (Vitest, componente real; só o `fetch` é simulado) — `UC-AJCRT-05`.
- **Contrato:** a tela Blade (`public/js/stock_adjustment.js` + `stock_adjustment/partials/product_table_row.blade.php`).
- **Regressão que defende:** a linha voltar a ser texto livre sem `product_id` (como era até 2026-10-11), ou o envio voltar a sair com ponto decimal.
- **Status: 🧪** — rodado à mão em 2026-10-11 (12 de 12); **nenhuma lane de CI roda este arquivo ainda** (cada lane Vitest lista os próprios specs, e `.github/` é do [W]).

## Backlog
- [BACKLOG] `store()` não confere se `location_id` é do business da sessão; a filial chega do formulário e vai direto para `decreaseProductQuantity`. Grava quantidade: decisão [W].
- [BACKLOG] R-ADJ-003 (valor recuperado ≤ total) só é conferida no cliente; `store()` não valida.
