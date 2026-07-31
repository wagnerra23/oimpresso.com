---
id: resources-js-pages-financeiro-caixa-index-casos
casos: Caixa do turno · /financeiro/caixa
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Financeiro · MySQL)"
sdd: memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md
---

# Casos de Uso & Aceite — Caixa do turno

> Tela de **leitura** sobre o núcleo UltimatePOS: lê `cash_registers` (não `fin_*`) e costura cada
> turno ao `fin_titulo` correspondente. Persona: **Larissa** (opera o balcão) e **Eliana [E]**
> (confere o turno no fechamento).
>
> Os UC derivam do **SDD `§6.3 CU-FIN-20..22`**
> (`memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md`) + do `Index.charter.md` — **não** do
> `.tsx` nem do corpo do teste.
>
> **Lane:** `PHP / Pest (Financeiro · MySQL)` — **required** (`governance/required-checks-baseline.json`):
> reprovar aqui **bloqueia merge**. O `CaixaControllerTest` entrou na allowlist nesta onda.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## Rastreabilidade UC → CU

> **Âncora:** `CU-FIN-20` · `CU-FIN-21` · `CU-FIN-22` do
> [SDD do Financeiro](../../../../../memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md) §6.3.
> **Sem US no SPEC** — o Caixa nunca ganhou US própria (gap declarado, não inventado: o docblock
> antigo do teste citava um "US-FIN-CAIXA" inexistente).

| UC | Comportamento | Força | Âncora de contrato | Teste | Status |
|---|---|---|---|---|---|
| UC-FCX-01 | Lista turnos com o shape que a tela consome | must | `CU-FIN-20` | `CaixaControllerTest` | 🧪 |
| UC-FCX-02 | Filtro por turno aberto/fechado | should | `CU-FIN-20` | `CaixaControllerTest` | 🧪 |
| UC-FCX-03 | `?limit` é clampado (10..200), não recusado | should | `CU-FIN-20` | `CaixaControllerTest` | 🧪 |
| UC-FCX-04 | Turno de outro negócio nunca aparece | must `[T0]` | `CU-FIN-21` + ADR 0093 | `CaixaControllerTest` | 🧪 |
| UC-FCX-05 | Sem `view_cash_register` a tela é 403 | must | `CU-FIN-22` | `CaixaControllerTest` | 🧪 |

---

## UC-FCX-01 · Listar os turnos de caixa com o shape que a tela consome `[must]`
- **Persona:** Eliana [E] — abre a tela e vê os turnos do negócio sem depender do POS legado.
- **Aceite:** Dado usuário com `view_cash_register` · Quando abre `/financeiro/caixa` · Então recebe 200 renderizando o componente `Financeiro/Caixa/Index` com as props que a tela usa (lista de caixas, estatísticas, filtros e links) — **não** uma página vazia nem um redirect.
- **Teste:** `CaixaControllerTest` ("UC-FCX-01 · renderiza Inertia component Financeiro/Caixa/Index com shape esperado").
- **Regressão que defende:** refactor do controller que troca o nome do componente Inertia ou some com uma prop e deixa a tela em branco.
- **Status: 🧪**

## UC-FCX-02 · Filtrar por turno aberto/fechado `[should]`
- **Persona:** Larissa — no fim do dia quer só o que está **aberto** para fechar.
- **Aceite:** Dado turnos em estados diferentes · Quando aplica `?status=open` · Então só turnos abertos voltam na listagem.
- **Teste:** `CaixaControllerTest` ("UC-FCX-02 · aplica filtro ?status=open na query").
- **Regressão que defende:** filtro virar decorativo (o `<select>` muda a URL e a query ignora).
- **Status: 🧪**

## UC-FCX-03 · Limite de página é **clampado**, não recusado `[should]`
- **Persona:** qualquer — deep-link/URL editada à mão não pode derrubar a tela.
- **Aceite:** Dado `?limit` fora da faixa aceita · Quando a tela carrega · Então o valor é **ajustado para a borda** (teto 200 · piso 10) e a página responde normalmente — nunca 500, nunca "carrega tudo".
- **Teste:** `CaixaControllerTest` ("UC-FCX-03 · clamp ?limit acima de 200 vira 200" e "UC-FCX-03 · clamp ?limit abaixo de 10 vira 10").
- **Regressão que defende:** `?limit=999999` virando full-table-scan em produção.
- **Status: 🧪**

## UC-FCX-04 · Turno de outro negócio nunca aparece `[T0]` `[must]`
- **Persona:** invariante de plataforma ([ADR 0093] — Tier 0 IRREVOGÁVEL).
- **Aceite:** Dado dois negócios com turnos · Quando o usuário do negócio A abre a tela · Então **nenhum** turno do negócio B aparece na listagem nem nas estatísticas.
- **Teste:** `CaixaControllerTest` ("UC-FCX-04 · Tier 0 multi-tenant — não vaza caixa de outro business").
- **Regressão que defende:** query montada em `DB::table('cash_registers')` cru esquecer o `business_id` — risco real, porque esta tela **não** usa Eloquent com global scope.
- **Status: 🧪**

## UC-FCX-05 · Sem permissão, a tela é negada `[must]`
- **Persona:** operador sem alçada de caixa.
- **Aceite:** Dado usuário **sem** a permissão `view_cash_register` · Quando tenta abrir `/financeiro/caixa` · Então recebe **403** (negado), não a tela vazia.
- **Teste:** `CaixaControllerTest` ("UC-FCX-05 · bloqueia user sem permission view_cash_register (403)").
- **Regressão que defende:** middleware de permissão removido num refactor de rota, expondo o caixa a todo mundo do negócio.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Abrir turno de caixa pela tela React** — hoje **não existe**: o React linka
  `links.cash_register_legacy` (Blade `resources/views/cash_register/index.blade.php`, botão "add").
  **É paridade pendente, e a decisão de virar Non-Goal ou US é do [W]** (SDD §5.4/§6.3 CU-FIN-23).
- **[BACKLOG] Fechar turno pela tela React** — idem; a Blade tem `close_register_modal.blade.php`.
- **[BACKLOG] Detalhe do turno (vendas/produtos/pagamentos do período)** — Blade tem
  `register_details` + `register_product_details` + `payment_details`; o React só lista.
- **[BACKLOG] Lançar `fin_titulo` retroativo pro turno fechado sem vínculo** — o CTA já existe na
  tela (linha "Financeiro" sem título), sem teste que defenda o efeito.

## Como rodar a suíte
1. **Pest (MySQL real):** lane `financeiro-pest.yml` (check **required** `PHP / Pest (Financeiro · MySQL)`).
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-27 · [CC] **criado** na onda `sdd-from-source` (passo 5 · Onda 2), junto com o primeiro SDD
  do módulo. Os 5 UC derivam do SDD §6.3; o `CaixaControllerTest` (que já existia e **não rodava em
  lane nenhuma**) passa a citar os UC no título e entra na allowlist da `financeiro-pest.yml` —
  antes disso era cobertura que nunca produzia veredito ("verde impossível"). Nenhum corpo de teste
  foi alterado; nenhum status nasceu ✅ (o veredito é da lane — G-7). Achado de paridade registrado
  no backlog: **abrir/fechar turno não existe no React**, vive na Blade do core.

[ADR 0093]: ../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md
