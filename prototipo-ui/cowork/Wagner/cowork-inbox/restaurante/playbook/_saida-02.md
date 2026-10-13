---
sessao: "_saida-02"
thread: "02 · Modificadores (ModifierSetsController@index) → Inertia"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/restaurante-02-modificadores)"
base_lida: wagnerra23/oimpresso.com@main 94c005aec5 + thread 01 (#9272)
---
# _saida-02

## Entregue: Modificadores em React, atrás da flag OFF
`GET /modules/modifiers` (`ModifierSetsController::index`) responde Inertia `Restaurante/Modificadores/Index` quando a
flag `useV2RestauranteModificadores` está ligada para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade
`restaurant.modifier_sets.index`, que hospeda os modais de cadastrar, editar, ligar produtos e excluir. A DataTable da
Blade continua respondendo o `ajax()` sem `X-Inertia` (`ajax() && ! inertia()`).

Prova da thread (`contem`): `app/Http/Controllers/Restaurant/ModifierSetsController.php` contém `Inertia::render(`.

Forma: o alvo da A1, `governance/design/targets/restaurante--modificadores--index.alvo.json` (#9256) — cabeçalho, abas
de Configurações, toolbar, tabela e nota. As abas vêm do `ConfiguracoesSubNav`: em produção Modificadores mora no dropdown
de Configurações (`AdminSidebarMenu.php:956`), como a aba `cfg-modificadores` do protótipo.

## Processo MWART (ADR 0104)
| fase | o quê |
|---|---|
| F1 | `memory/requisitos/Restaurante/RUNBOOK-modificadores.md` |
| F2 | `tests/Feature/Restaurante/ModificadoresBaselineTest.php` trava a Blade de hoje: DataTable só com conjuntos do negócio (98 × 99) e com os nomes dos modificadores, 403 sem `product.view` nem `product.create`, editar só com `product.update`, excluir só com `product.delete` |
| F3 | ramo Inertia no controller + `Index.tsx` + charter + casos (UC-MODIF-01..04) + `ModificadoresContratoTest.php` |
| F4 | smoke biz=1 com a flag ligada — pendente, depois do merge |
| F5 | cutover — decisão [W] |

Os testes rodam na lane `acessos-pest.yml`, que já executa `tests/Feature/Restaurante/` desde a thread 01 e agora também
dispara com o `ModifierSetsController`. Não rodei Pest local (regra: CT 100 ou CI).

## Divergências do protótipo (no charter)
- **Preço do modificador.** O protótipo mostra o preço ao lado de cada modificador. A Blade mostra só os nomes; exibir preço é valor
  (regra mestre): fica fora até dupla prova e decisão [W].
- **Produto ligado de outro negócio.** O pivô `res_product_modifier_sets` não tem `business_id`; a Blade lista o que o
  pivô tiver, a tela nova filtra pelo negócio (Tier 0).

## Gates locais
`casos-coverage-guard` sem violação nova · `screen-coverage-map --check` ok · `pt-conformance --check` ok ·
`uc-id-lint` ok · `memory-schemas/validate.mjs` RUNBOOK e charter ok · `tsc` sem erro em `Restaurante/`.

## Dependência
Empilhado sobre a thread 01 (#9272), que liga `tests/Feature/Restaurante/` na lane. Mergear depois do #9272.
