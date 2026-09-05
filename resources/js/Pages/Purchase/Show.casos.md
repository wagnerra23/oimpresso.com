---
id: resources-js-pages-purchase-show-casos
casos: Detalhe da Compra · /purchases/{id}
irmaos: Show.charter.md (lei) · Show.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o escopo por tenant e a ausência do barcode são duráveis — não mudam quando o detalhe ganhar card novo.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "nasce com dívida de prova DECLARADA — os testes que citam estes UC são ESTRUTURAIS (grep no fonte), não exercitam request. Ver §Dívida de prova."
---

# Casos de Uso & Aceite — Detalhe da Compra (`/purchases/{id}`)

> **Âncora:** os UC derivam do [`Show.charter.md`](Show.charter.md) (Mission · Goals · Non-Goals ·
> Anti-hooks) cruzado com o
> [`show-visual-comparison.md`](../../../../memory/requisitos/Compras/_telas/show-visual-comparison.md)
> — **nunca do `Show.tsx`**: teste derivado do código é tautológico e trava o desvio em vez de
> pegá-lo ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> ⚠️ **A 2ª âncora passou a existir em 2026-09-04, e os UC ainda NÃO foram repromovidos.** Quando
> este arquivo nasceu, o Show era a única das quatro telas sem RUNBOOK; agora tem o
> [`RUNBOOK-purchase-show.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-show.md),
> irmão dos de [Index/Create/Edit](../../../../memory/requisitos/Compras/_telas/). O charter segue
> `status: draft` com Non-Goals marcados *"inferência pendente de Wagner"*, então o que o RUNBOOK
> destrava é o que ele **mediu** (dual-path, 404 vs 403, barcode por omissão) — não a intenção, que
> segue com [W]. A contagem de UC abaixo ainda é a de antes: promover exige teste que cite o UC,
> senão nasce órfão e o `casos-gate` G-2 (required) bloqueia quem for atendê-lo.

---

## ⚠️ Dívida de prova — o que os testes desta tela **não** provam

Medição em `origin/main` (2026-09-04):

| teste | requests HTTP | asserts de presença | o que de fato prova |
|---|---:|---:|---|
| [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) | **0** | **44** | que certas *strings* existem em `Show.tsx` e em `PurchaseController.php` |

`ShowPageTest` lê os arquivos-fonte e casa texto. Ele pega a **remoção** de um trecho, mas não monta
tenant, não emite request e não valida resposta — classe
[LC-11](../../../../memory/LICOES_CODE.md) (presence-gate), que o ledger alarma com 11 ocorrências.

**Consequência honesta:** nenhum UC recebe `Status: ✅`. Todos carregam **⚠️ 🧪 estrutural** — exceto
o UC-PURSHW-03, cuja natureza *é* estrutural (ausência de um literal no arquivo **é** o contrato).

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PURSHW-01 | Detalhe nunca resolve compra de outro tenant | must `[T0]` | charter Non-Goal 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ShowPageTest` | ⚠️ 🧪 estrutural |
| UC-PURSHW-02 | Dual-path: AJAX puro recebe Blade; navegação recebe Inertia | must | charter §Backend · [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) | `ShowPageTest` | ⚠️ 🧪 estrutural |
| UC-PURSHW-03 | A tela **não** renderiza barcode (mata o 500 do legado) | must `[reg]` | charter §Backend (bug-fix declarado) | `ShowPageTest` | 🧪 estrutural (correto) |
| UC-PURSHW-04 | Editar/Excluir só aparecem com a permissão | must | charter Goals · Anti-hooks | `ShowPageTest` | ⚠️ 🧪 estrutural |
| UC-PURSHW-05 | A tela não recalcula totais — exibe o que o controller mandou | must `[V0]` | charter Non-Goal 3 | `ShowPageTest` | ⚠️ 🧪 estrutural |
| UC-PURSHW-06 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | charter Non-Goal 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ShowPageTest` | 🧪 estrutural (correto) |

---

## UC-PURSHW-01 · Detalhe nunca resolve compra de outro tenant · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — o detalhe expõe fornecedor, custo unitário e margem; um
  vazamento aqui entrega a estrutura de custo do concorrente.
- **Aceite:** Dado uma compra do negócio 98 · Quando o usuário do negócio 1 pede
  `GET /purchases/{id}` · Então **não** recebe o detalhe (404/403 conforme o canon da rota). E,
  como **controle positivo**, o mesmo endpoint para uma compra do próprio negócio responde 200 —
  sem o par, um `abort` incondicional passaria no teste.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Controller
  showInertia PRESERVA Tier 0 (recebe $purchase já scopado por business_id)"* · *"Controller
  showInertia NÃO usa withoutGlobalScopes sem comentário SUPERADMIN"*.
- **Contrato:** charter §Non-Goals item 4 (*"IDs de outro negócio devem retornar 404/403"*) ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o model `Transaction` **não tem global scope** — o isolamento é escrito
  à mão em cada query. Essa mesma ausência produziu um IDOR de escrita real no `update` desta mesma
  controller ([`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php)).
- **Status: ⚠️ 🧪 estrutural** — o assert casa texto no fonte. **Não existe** teste que crie a compra
  no tenant vizinho e prove o 404 desta rota. É o UC mais caro do arquivo e o menos defendido.

---

## UC-PURSHW-02 · Dual-path: AJAX puro recebe Blade; navegação recebe Inertia · `must`

- **Persona:** Maiara/Felipe abrem o detalhe pelo SPA; o modal AJAX legado ainda é usado por telas
  antigas que não foram migradas.
- **Aceite:** Dado `PurchaseController@show` · Quando o request é AJAX puro · Então devolve a view
  Blade legacy (compatibilidade do modal). Quando é navegação normal · Então devolve a Page Inertia
  `Purchase/Show`. Os dois caminhos coexistem — nenhum foi substituído.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Controller@show
  tem dual path (Blade legacy se AJAX puro + Inertia default)"* · *"Controller@show PRESERVA Blade
  legacy path (compat retro AJAX modal)"* · *"Controller@show tem permission re-adicionada (não
  comentada)"*.
- **Contrato:** charter §Backend · [ADR 0104 MWART](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) (F5 CUTOVER é humano e não ocorreu).
- **Regressão que defende:** a asserção *"permission re-adicionada (não comentada)"* existe porque a
  permissão **já esteve comentada** neste método. Um `//` numa linha de autorização é a mudança mais
  barata de escrever e a mais cara de descobrir.
- **Status: ⚠️ 🧪 estrutural** — casamento de texto; nenhum request AJAX é emitido para provar a
  bifurcação.

---

## UC-PURSHW-03 · A tela **não** renderiza barcode (mata o 500 do legado) · `must` `[reg]`

- **Persona:** qualquer operador abrindo o detalhe de uma compra — o legado devolvia **500**, ou
  seja, a tela simplesmente não abria.
- **Aceite:** Dado `Show.tsx` · Quando o arquivo é lido · Então **não** existe geração de código de
  barras no detalhe (o Blade legado gerava via DNS1D e estourava). A impressão de etiqueta é fluxo
  dedicado (`/labels/show`), não responsabilidade desta tela.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page NÃO
  renderiza barcode (bug-fix por omissão — linha 430 Blade DNS1D)"*.
- **Contrato:** charter §Backend (*"substitui os Blade legacy show + show_details (430+ linhas) e
  mata o bug 500 do barcode"*).
- **Regressão que defende:** um bug-fix **por omissão** é o mais frágil que existe — nada no código
  explica por que aquilo *não* está lá, então a próxima pessoa que buscar paridade com o Blade
  reintroduz o barcode de boa-fé e ressuscita o 500.
- **Status: 🧪 estrutural (correto)** — **sem ⚠️.** Aqui o contrato *é* a ausência de um literal no
  arquivo; o presence-gate é o instrumento certo, não um substituto de teste de comportamento.

---

## UC-PURSHW-04 · Editar/Excluir só aparecem com a permissão · `must`

- **Persona:** operador de conferência (só leitura) não pode ver botão que ele não pode usar.
- **Aceite:** Dado o detalhe renderizado · Quando o usuário não tem `purchase.update` /
  `purchase.delete` · Então os botões Editar e Excluir **não** são renderizados — e a exclusão,
  quando disponível, exige `confirm()` com a referência da compra antes do `router.delete`.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page respeita
  permissions (update/delete renderizam condicionalmente)"* · *"Page tem botões ação
  Voltar/Imprimir/Editar/Excluir"* · *"Controller@showInertia retorna permissions completas"*.
- **Contrato:** charter §Goals · §Anti-hooks (*"Exclusão nunca dispara sozinha"*).
- **Regressão que defende:** esconder o botão é UX; **o backend continua sendo a autoridade**. Este
  UC defende a metade visível — a metade de servidor pertence ao Edit/Destroy e está registrada em
  `[BACKLOG]` porque não há teste que a exercite.
- **Status: ⚠️ 🧪 estrutural** — casamento de texto no `.tsx`; nenhum usuário sem permissão é montado.

---

## UC-PURSHW-05 · A tela não recalcula totais — exibe o que o controller mandou · `must` `[V0]`

- **Persona:** **Larissa @ ROTA LIVRE (biz=4)** — o total exibido tem que ser o total gravado; um
  arredondamento diferente no cliente faz a tela discordar do financeiro.
- **Aceite:** Dado o detalhe de uma compra · Quando os totais (subtotal, desconto, impostos, frete,
  total, pago, a pagar) são exibidos · Então os valores vêm prontos do controller e a tela apenas
  **formata** em pt-BR — nunca recalcula, nunca reagrega.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page formata
  BRL (Intl.NumberFormat pt-BR)"* · *"Page tem Total geral (PT-BR)"* · *"Page tem 3 Cards top e 2
  Cards mid (Pagamentos/Totais)"*.
- **Contrato:** charter §Non-Goals item 3 (*"Não recalcula totais no cliente — os valores vêm
  prontos do controller"*) · [proibicoes §REGRA MESTRE — CÁLCULO DE VALOR](../../../../memory/proibicoes.md).
- **Regressão que defende:** o incidente de 2026-06-05 (biz=4) inflou 16 vendas ~×100k porque um
  número atravessou uma fronteira de formatação com a interpretação errada de separador. Cálculo em
  duas camadas é a porta por onde isso entra; esta tela é explicitamente **uma camada só**.
- **Status: ⚠️ 🧪 estrutural** — os asserts provam que a *formatação* pt-BR está no arquivo; **não**
  provam a ausência de recálculo. É `[V0]` com defesa fraca — declarado, não maquiado.

---

## UC-PURSHW-06 · A Page não decide tenant — `business_id` vem das props · `must` `[T0]`

- **Persona:** Wagner — o front nunca é a autoridade sobre de quem é o dado.
- **Aceite:** Dado `Show.tsx` · Quando o arquivo é lido · Então **não** existe `business_id`
  hardcoded; o recorte de tenant é resolvido no controller e chega pronto nas props.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page NÃO tem
  business_id hardcoded (vem das props via Controller)"*.
- **Contrato:** charter §Non-Goals item 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** um `business_id` fixo numa Page parece constante de config em review e
  só se revela no segundo tenant.
- **Status: 🧪 estrutural (correto)** — **sem ⚠️.** Ausência de literal *é* o contrato.

---

## `[BACKLOG]` — achados sem contrato em 2 fontes, ou sem teste que os defenda

> Regra dura: comportamento com contrato em **≥2 fontes** vira UC com id; contrato em 1 fonte só, ou
> achado sem âncora, vira bullet sem id. UC com id sem teste é **órfão**, e o `casos-gate` G-2
> (required) bloqueia o merge de quem for atendê-lo
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

- `[BACKLOG]` **Repromover os UC agora que a 2ª âncora existe.** O pré-requisito foi pago em
  2026-09-04 — o [`RUNBOOK-purchase-show.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-show.md)
  mede dual-path, ordem 403→404, props e o barcode-por-omissão. O que **não** foi pago: cada
  promoção exige um teste que cite o UC (G-2), e o `ShowPageTest` de hoje é estrutural. Non-Goal
  marcado *"inferência pendente de Wagner"* segue fora — RUNBOOK mede comportamento, não decide
  intenção.
- `[BACKLOG]` **Sem timeline/histórico de auditoria.** O charter declara como Non-Goal, mas com a
  ressalva *"inferência pendente de Wagner"*. Enquanto for inferência do agente e não decisão de
  [W], não vira UC (*"UC não é canal de pedido"*).
- `[BACKLOG]` **Pagamento não é lançado nesta tela.** Mesmo caso do item acima: Non-Goal marcado
  como inferência pendente.
- `[BACKLOG]` **A autorização de servidor no destroy/update não é exercitada por teste algum desta
  tela.** UC-PURSHW-04 cobre só a face visível (o botão). A metade que importa — o backend recusar a
  ação de quem não pode — pertence ao Edit e hoje só tem
  [`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php),
  que está **em quarentena** (ver `Edit.casos.md`).

---

## ⚠️ Divergências que precisam de [W] (não corrigidas aqui — são INTENÇÃO)

1. **O charter está `status: draft`** e diz explicitamente que [W] aprova Non-Goals + Anti-hooks
   antes de virar `live`. Três dos quatro Non-Goals trazem *"inferência pendente de Wagner"* — este
   `casos.md` só promoveu a UC os que têm apoio em outra fonte.
2. **A tela não tem teste de comportamento nem E2E.** `ShowPageTest` tem 44 asserts e 0 requests, e
   o `screen-coverage` marca `Purchase · 0 de 4 E2E · 0 de 4 VRT`. O verde da lane **não** significa
   que o detalhe isola tenant.
