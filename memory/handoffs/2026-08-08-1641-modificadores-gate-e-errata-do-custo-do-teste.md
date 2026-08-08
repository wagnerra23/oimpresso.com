---
date: "2026-08-08"
time: "16:41 BRT"
slug: modificadores-gate-e-errata-do-custo-do-teste
tldr: "Pedido era decidir se ModifierSetsController::index() precisa de gate. Decisao: sim — mas o predicado obvio (product.view sozinho) criaria a classe A que a US-GOV-059 existe pra matar. Duas premissas do enunciado caíram na medicao, e uma afirmacao MINHA sobre o custo do teste foi refutada por um PR mergeado 4h antes."
prs: [5426]
decided_by: [W]
next_steps:
  - "Decisao [W]: escrever o teste do gate — o custo real e UMA LINHA em .github/ci-sqlite-pest.list, nao lane nova"
  - "Decisao [W]: a view modifier_sets/index.blade.php L52 tem a mesma estreiteza (product.view sozinho) — um || de distancia"
---

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `brief-fetch` (SessionStart) → 672 US não atribuídas (519 sem dono) · SDD composta 55,0 · 0 incidentes.
- Handoffs irmãos do dia anterior: [`1840-lanes-required-vermelhas`](2026-08-07-1840-lanes-required-vermelhas-e-quarentena.md) · [`1530-quarentena-era-sqlite`](2026-08-07-1530-quarentena-era-sqlite-piloto-lane-whatsapp.md) · [`1420-cozinha-gate-duas-pernas`](2026-08-07-1420-cozinha-gate-duas-pernas-adversario.md).

## O que aconteceu

Pedido do [W]: *decidir se `ModifierSetsController::index()` deve ter gate de permissão* — achado adjacente que o [#5365](https://github.com/wagnerra23/oimpresso.com/pull/5365) tinha registrado como decisão [W] pendente.

**Decisão: sim.** Gate com `product.view || product.create`, idêntico ao `ProductController::index()`. Modificador **é linha de `products`** (`type = 'modifier'`) e a lista principal desse mesmo dado já exige isso; os irmãos vivos da pasta gateiam o `index()` (`TableController` → `access_tables`; `BookingController` → `crud_all_bookings`/`crud_own_bookings`) e este era o **único sem**.

**O predicado sugerido no enunciado estava errado — e teria criado a classe A que a US-GOV-059 existe pra matar.** `product.view` e `product.create` são checkboxes **independentes** (`role/{create,edit}.blade.php` L331/L339). Quem tem só `product.create` recebe o link no menu (`AdminSidebarMenu` L906 usa exatamente esse `OR`), cria modificador (L91/L107) e vê o botão Adicionar — e levaria **403 na lista que acabou de alimentar**. Mesma forma do `kb.ai`.

**Custo de fechar agora é zero**, e é o que torna a decisão fácil: o menu já exige o par, então ninguém que navega pela UI perde acesso — o gate só alcança acesso direto por URL/AJAX. Com a feature em uso, seria breaking change.

### Duas premissas do enunciado caíram na medição

| premissa | veredito |
|---|---|
| *"`Modules/Restaurant` não existe ⇒ pode ser feature morta"* | **falsa.** Nunca foi módulo nWidart — sempre viveu no core. É a **Mesas**, habilitável em `/business/settings` (Camada 2). O SPEC já media isso, e o próprio [W] confirmou em [#5368](https://github.com/wagnerra23/oimpresso.com/pull/5368): *"existe mas não está em uso agora"* |
| *"provavelmente `product.view`"* | **estreito demais** — ver acima |

### E uma afirmação MINHA caiu (LC-08 · errata registrada, não apagada)

Escrevi no SPEC e no commit que *"o `ci.yml` roda só `tests/Feature/Form`; cobrir exige wirar lane"*. **Falso nas duas metades.** O `ci.yml` (L112) lê a lista curada [`.github/ci-sqlite-pest.list`](../../.github/ci-sqlite-pest.list) — **418 linhas**, e `tests/Feature/Form` é **a L63**. Ligar um teste de Restaurante é **uma linha nessa lista**, não lane nova.

Derivei o custo de `rg` sobre os workflows em vez de **abrir o arquivo que eles consomem**. Quem mediu certo foi o [#5388](https://github.com/wagnerra23/oimpresso.com/pull/5388) (Cozinha) — **4h antes**, e eu só não tinha lido. Peguei no rebase, ao ler o que main tinha andado; corrigi SPEC + `--amend` no commit **antes** de abrir o PR. **Com o custo corrigido, "cobrir" volta a ser barato** — o que trava não é a lane, é escrever o teste sem poder rodá-lo (Pest é CT 100).

### O chip da Cozinha fechou sozinho

Abri chip pro `KitchenController::index()` (gate **comentado**, servindo **pedidos** — mais sensível que nomes de catálogo). [W] iniciou, e ele mergeou como [#5388](https://github.com/wagnerra23/oimpresso.com/pull/5388) **antes** deste PR: gate nas **duas pernas** (endpoint + menu), porque o menu da Cozinha não declarava permissão nenhuma — *"espelhar o predicado do menu" não era executável* ali, ao contrário daqui.

## Artefatos gerados

| arquivo | delta |
|---|---|
| [`app/Http/Controllers/Restaurant/ModifierSetsController.php`](../../app/Http/Controllers/Restaurant/ModifierSetsController.php) | +12 (gate + comentário explicando por que não só `product.view`) |
| [`memory/requisitos/Governance/SPEC.md`](../requisitos/Governance/SPEC.md) §US-GOV-059 L1041 | +22 (decisão + errata do custo do teste) |

## Persistência

- **git:** [#5426](https://github.com/wagnerra23/oimpresso.com/pull/5426), rebase limpo sobre `origin/main` (38 commits à frente; nenhum tocou os 2 arquivos na mesma seção).
- **MCP:** webhook GitHub→MCP propaga o SPEC ~2min pós-merge.
- **BRIEFING:** não aplicável — mudança de autorização em controller do core, sem capacidade nova de módulo.

## Verificação (recibo, não afirmação)

`php -l` limpo (Herd está no PATH do **PowerShell**, não no do bash) · `anchor-lint` nos **DOIS** modos que o job roda no PR (`--check $CHANGED` **e** `--check-entry --check-covers --baseline`) → rc=0 · `memory-schemas/validate.mjs` → OK · gate único pós-rebase (1 `abort(403)` no `index()`).

## Lições catalogadas

1. **LC-08 (afirmar medindo a fonte errada)** — medi o custo de wirar teste com `rg` nos workflows, não no arquivo que o workflow lê. O padrão perene: *quando um workflow consome um arquivo de configuração, a resposta está no arquivo, não no YAML que o cita.*
2. **Rebase é leitura, não só merge** — a errata só apareceu porque li o que os 38 commits de main traziam. Rebasear sem ler o que chegou teria mergeado a afirmação falsa.
3. **Premissa do enunciado é hipótese, não fato** — as duas do pedido caíram, e ambas já tinham sido medidas no próprio SPEC que eu ia editar.

## Pointers detalhados

- Decisão + errata: `memory/requisitos/Governance/SPEC.md` §*"O gate do `index()` — RESOLVIDO 2026-08-07"* (L1041).
- Precedente irmão (Cozinha, e por que lá o menu **não** servia de espelho): mesmo SPEC, L1228.
- Decisão [W] sobre o `@can` da **view** (*"pode deixar os botão"*): mesmo SPEC, L982 + [#5368](https://github.com/wagnerra23/oimpresso.com/pull/5368).
