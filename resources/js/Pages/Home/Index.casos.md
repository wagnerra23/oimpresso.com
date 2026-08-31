---
id: resources-js-pages-home-index-casos
casos: Dashboard · Visão geral · /dashboard-legacy
irmaos: Index.charter.md (lei) · Index.tsx (tela) · _components/GradesPainel.tsx
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-28"
---

# Casos de uso — /dashboard-legacy (Visão geral)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Trio nascendo **forward-only** sobre tela já viva (`status: live`, charter v5). Os UC abaixo derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei, §Goals + §Non-Goals + §Test plan) + [`memory/requisitos/Dashboard/SPEC.md`](../../../../memory/requisitos/Dashboard/SPEC.md) (US-DASH-001..006) + [`RUNBOOK-home-index.md`](../../../../memory/requisitos/Dashboard/RUNBOOK-home-index.md) — **nunca** do `.tsx`. Persona: **Larissa [L]** (dona PME vestuário ROTA LIVRE) + qualquer user logado UPOS. Desktop ≥1024px.

> **Por que todos nascem 🧪 e não ✅:** os 18 testes existem e rodam na lane `dashboard-pest.yml`, mas o `✅` é **derivado**, não declarado — vem do manifesto [`scripts/casos-test-results.json`](../../../../scripts/casos-test-results.json) que o `casos:results` gera a partir do JUnit do CI (gate G-7). Até a primeira run publicar o veredito por-UC, escrever `✅` aqui seria afirmar sem recibo. O estado honesto é 🧪.

> **Cobertura:** 16 UC ↔ 18 testes. Dois UC são defendidos por um **par** de testes (positivo + controle negativo), e isso é proposital: UC-DASH-10 e UC-DASH-15 protegem uma allowlist, e allowlist só está provada quando se mostra que ela **deixa passar o certo E barra o errado**. Um teste só de cada lado passaria com a allowlist desligada.

---

## UC-DASH-01 — A tela responde "como foi o período" numa carga só
Status: 🧪 (1 teste em `HomeIndexInertiaTest` cita este UC — o componente Inertia e as 5 chaves de payload que o charter promete.)
Missão do charter: o usuário abre `/dashboard-legacy` e a janela escolhida responde sem segunda navegação. O controller devolve o componente `Home/Index` com `user_name`, `is_admin`, `can_dashboard_data`, `totals` e `endpoints`.
**Pronto quando:** a rota renderiza o componente `Home/Index` com as 5 chaves presentes, sem 500 e sem tela branca.

## UC-DASH-02 — Cliente final não entra no painel interno
Status: 🧪 (1 teste em `HomeIndexInertiaTest` cita este UC.)
Goal do charter: *customer redirect preservado*. Usuário com `user_type=user_customer` não vê o painel do lojista — é redirecionado pro dashboard de cliente (`Modules/Crm/Http/Controllers/DashboardController`). É separação de audiência, não permissão: o cliente **tem** login válido.
**Pronto quando:** `user_type=user_customer` recebe 302 pro dashboard de cliente, e nenhum dado do painel interno é serializado na resposta.

## UC-DASH-03 — Sem `dashboard.data` a tela abre em shell minimal
Status: 🧪 (1 teste em `HomeIndexInertiaTest` cita este UC.)
Goal do charter: *permission gate `dashboard.data` — sem permission, KPI cards somem*. A tela **não** dá 403: ela abre, mas `totals` vem `null` e os cards não pintam. Quem não pode ver número entra e navega; só não recebe o número.
**Pronto quando:** usuário logado sem `dashboard.data` recebe 200 com `can_dashboard_data=false` e `totals=null` — nunca um `totals` parcial ou zerado, que seria indistinguível de um período sem movimento.

## UC-DASH-04 — Não existe mais fallback Blade
Status: 🧪 (1 teste em `HomeIndexInertiaTest` cita este UC.)
Charter v5: o Blade legado saiu (`views/home/index.blade.php`, os 8 partials, `public/js/home.js`, `indexLegacy()` e o ramo `?legacy=1`). A query string sobrevive como **inerte** — quem tiver o link velho salvo cai na tela React em vez de num 404.
**Pronto quando:** `GET /dashboard-legacy?legacy=1` devolve 200 renderizando o componente React `Home/Index`, e nenhuma view Blade de home é resolvida no caminho.

## UC-DASH-05 — Tier 0: o filtro de loja não vaza location de outro business
Status: 🧪 (1 teste em `HomeIndexInertiaTest` cita este UC.)
Invariante [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0, IRREVOGÁVEL). O seletor de loja é alimentado por `BusinessLocation::forDropdown` sob `session('user.business_id')`. Location é nome de unidade física — vazar a lista já entrega a topologia do concorrente, antes de qualquer número.
**Pronto quando:** o payload de um usuário do business A não contém **nenhuma** location pertencente ao business B.

## UC-DASH-06 — `totals` expõe os 8 campos canônicos, nem mais nem menos
Status: 🧪 (1 teste em `HomeIndexInertiaTest` cita este UC.)
Guard de contrato do charter: `total_sell`, `net`, `invoice_due`, `total_expense`, `total_purchase`, `purchase_due`, `total_sell_return`, `total_purchase_return`. São os 4 KPI mais as 4 contrapartidas. Campo a menos quebra card; campo a mais é capacidade entrando sem passar pelo charter.
**Pronto quando:** `totals` tem exatamente essas 8 chaves — o teste falha tanto na ausência quanto no excesso.

## UC-DASH-07 — Cada aba de grade tem o seu próprio gate
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC.)
US-DASH-005. As 8 grades herdadas do Blade têm gates distintos (`sell.view`/`direct_sell.view`, `purchase.view`, `stock_report.view`, `so.view_*`, `purchase_order.view_*`, `purchase_requisition.view_*`, `access_shipping` e variantes). Sem permissão a aba **some** — não aparece desabilitada. Aba cinza anuncia a existência de um dado que a pessoa não pode ver.
**Pronto quando:** usuário sem a permissão de uma grade não recebe aquela aba no catálogo servido, e as demais continuam presentes.

## UC-DASH-08 — Sem `dashboard.data` não há aba nenhuma
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC.)
O Blade tinha **duas camadas**: um `@if(can('dashboard.data'))` externo (linhas 369→1013) e, dentro dele, um `@can` por grade. As duas são reproduzidas. Este UC defende a externa: ela é a que um refactor "simplificador" apaga por parecer redundante.
**Pronto quando:** usuário **com** a permissão de uma grade específica mas **sem** `dashboard.data` não recebe aba alguma — a camada externa vence a interna.

## UC-DASH-09 — Aba condicional some quando o setting do business está desligado
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC — usa "Lotes a vencer" / `enable_product_expiry` como a instância medida.)
Três das 8 grades dependem de setting do business, não só de permissão: `enable_product_expiry`, `enable_purchase_order`, `enable_purchase_requisition`. É por isso que uma sondagem de runtime vê 5 abas num business e 8 noutro — e por que "some uma aba" não é bug antes de checar o setting.
**Pronto quando:** com `enable_product_expiry` desligado a aba de validade não é servida, mesmo pra usuário que tem `stock_report.view`.

## UC-DASH-10 — Aba inválida ou não-permitida na URL não estoura nem vaza
Status: 🧪 (2 testes em `GradesDoPainelTest` citam este UC — o par completo: aba **desconhecida** cai na primeira permitida, e aba **conhecida mas não-permitida** não é servida nem quando pedida explicitamente. Só o primeiro seria alarme sem controle: um servidor que aceitasse qualquer aba pedida passaria nele.)
Goal do charter: o estado da aba vive em **query string**, nunca em session. Query string é editável pelo usuário, então ela é superfície de entrada: `?aba=qualquer-coisa` degrada pra primeira aba permitida, e `?aba=<uma que ele não pode ver>` é recusada como se não tivesse sido pedida.
**Pronto quando:** aba desconhecida cai na primeira **permitida** sem exceção, e aba sem permissão não é servida nem sob pedido explícito na URL.

## UC-DASH-11 — Tier 0: a grade não devolve linha de outro business
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC.)
Invariante [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) aplicada ao conteúdo, não só ao filtro (UC-DASH-05 cobre o filtro). O [`GradesDoPainelService`](../../../../app/Services/Dashboard/GradesDoPainelService.php) tem query própria — e query própria é exatamente onde o global scope costuma ser esquecido.
**Pronto quando:** a grade servida a um usuário do business A não contém nenhuma linha do business B, em nenhuma das abas.

## UC-DASH-12 — Paridade de critério com o endpoint legado
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC.)
O Non-Goal dos endpoints AJAX cria uma duplicação deliberada: o React lê pelo `GradesDoPainelService` (query própria, porque os endpoints legados devolvem HTML dentro das células), enquanto `/home/sales-payment-dues` segue de pé. Duas implementações do mesmo critério **divergem em silêncio** — este UC é o que torna a divergência barulhenta.
**Pronto quando:** a aba `venc-venda` e o endpoint `/home/sales-payment-dues` reportam o **mesmo total** de títulos de venda vencendo, pro mesmo business e período.

## UC-DASH-13 — Non-Goal GUARD: os 4 endpoints AJAX seguem respondendo
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC.)
Non-Goal do charter: `/home/get-totals`, `/home/product-stock-alert`, `/home/purchase-payment-dues`, `/home/sales-payment-dues` ficam intactos. ⚠️ A **razão original caducou** — eles serviam o `home.js` do Blade, que saiu, e hoje são código sem chamador. O guard não afirma que são usados; afirma que **não foram removidos de carona**. Aposentá-los é decisão [W] e PR próprio.
**Pronto quando:** as 4 rotas respondem 200 pra usuário autorizado. Quando [W] decidir aposentá-las, este UC sai no **mesmo** PR que as remove — guard órfão defendendo código morto é pior que guard nenhum.

## UC-DASH-14 — O catálogo declara as 8 grades do Blade, e nenhuma inventada
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC.)
Medição que definiu o escopo (charter v4): o Blade tem **8** grades — não 5, não 9. O protótipo desenha 9; a nona ("Fluxo de caixa") **não tem fonte no Blade** e por isso não existe aqui. Este UC trava os dois lados: grade que sumiu é capacidade perdida, grade a mais é capacidade **inventada**, que é o modo de falha mais caro porque parece feature.
**Pronto quando:** o catálogo tem exatamente as 8 chaves canônicas, e "Fluxo de caixa" não está entre elas.

## UC-DASH-15 — Ordenação só aceita coluna da allowlist
Status: 🧪 (2 testes em `GradesDoPainelTest` citam este UC — o par positivo/negativo: coluna **da** allowlist ordena de verdade, coluna **fora** dela não vira SQL. O positivo sozinho passaria com a ordenação inerte; o negativo sozinho passaria com a ordenação desligada. Só o par prova que ela funciona **e** é fechada.)
A coluna de ordenação chega por query string, então é entrada não-confiável interpolada perto de SQL. A allowlist é a fronteira. Contexto que o par cobre: a ordenação já esteve **inerte** em produção uma vez (corrigida em 2026-08-28) — um teste que só verificasse a recusa teria ficado verde o tempo todo.
**Pronto quando:** coluna da allowlist muda de fato a ordem das linhas, **e** coluna fora dela não altera a query nem estoura — degrada pro default.

## UC-DASH-16 — `ordenaveis()` espelha o `sortable` da âncora
Status: 🧪 (1 teste em `GradesDoPainelTest` cita este UC — "situação NÃO ordena" é a instância medida.)
O backend expõe quais colunas são ordenáveis e o frontend pinta o cabeçalho clicável a partir disso. Se as duas listas divergem, a UI oferece uma ordenação que o servidor recusa — e o usuário lê o silêncio como bug do sistema. `situacao` é derivada em PHP (não é coluna de banco) e por isso **não** ordena.
**Pronto quando:** o conjunto devolvido por `ordenaveis()` é idêntico ao `sortable` declarado na âncora da grade, com `situacao` fora dos dois.

---

## Refs

- Lei: [`Index.charter.md`](Index.charter.md) v5 — §Goals, §Non-Goals (cada um vira GUARD), §Test plan
- Tela: [`Index.tsx`](Index.tsx) + [`_components/GradesPainel.tsx`](_components/GradesPainel.tsx)
- SPEC: [`memory/requisitos/Dashboard/SPEC.md`](../../../../memory/requisitos/Dashboard/SPEC.md) — US-DASH-001..006
- RUNBOOK: [`RUNBOOK-home-index.md`](../../../../memory/requisitos/Dashboard/RUNBOOK-home-index.md)
- Testes: `tests/Feature/Home/HomeIndexInertiaTest.php` (6) · `tests/Feature/Home/GradesDoPainelTest.php` (12)
- Lane: `.github/workflows/dashboard-pest.yml` (MySQL real)
- Serviço das grades: [`GradesDoPainelService.php`](../../../../app/Services/Dashboard/GradesDoPainelService.php)
