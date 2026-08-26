---
id: requisitos-jana-runbook-metas
title: "RUNBOOK — Metas da Jana (Blade → Inertia)"
type: runbook
authority: canonical
lifecycle: ativo
status: ativo
owner: W
created: '2026-08-26'
last_validated: "2026-08-26"
modulo: Jana
telas:
  - Jana/Metas/Index
  - Jana/Metas/Create
  - Jana/Metas/Show
  - Jana/Metas/Edit
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w
---

# RUNBOOK — Metas da Jana

> **F1 do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Este documento existe porque o hook `block-mwart-violation` barra o primeiro `Edit` em
> `resources/js/Pages/Jana/Metas/*.tsx` enquanto ele não existir — e **não há override**
> (o `/mwart-override` que a mensagem do hook anuncia não tem handler; ver lápide §5 2026-08-08).

## 0. O que `last_validated` cobre (e o que NÃO cobre)

O schema define `last_validated` como *"última data que rodou o RUNBOOK e o resultado bateu"*.
Em **2026-08-26** rodou e bateu o **inventário** (§2) e o **contrato** (§3): as 9 rotas, o
`MetasController` inteiro e as 4 views Blade foram lidos em `origin/main` e conferem com o que
está escrito aqui.

**NÃO cobre a migração** — as ondas do §6 (F2 baseline → PR-1..PR-4) não rodaram; nesta data
elas não existiam. Quem reabrir isto depois de um PR de Metas tem que **re-rodar o inventário**
e bumpar a data; um campo dizendo 2026-08-26 depois que a tela virou React é carimbo, não recibo.

## 1. Por que agora

[W] dirigiu o trabalho em 2026-08-26. A trava de sinal **não se aplica** ([ADR 0382](../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)).

**O número, dito uma vez e não repetido:** `jana_metas` = **0** nos 88 businesses (medido no
PR #6116, 2026-08-21, com controle positivo do instrumento na mesma sessão). Está aqui como
dado de contexto para quem for testar — **não** como argumento contra fazer.

**O motivo técnico independente:** as 4 telas são Blade dentro de um app Inertia. O ghost
`metas` foi **removido da faixa de abas** (`DataController.php:329-332`) porque *"MetasController@index
ainda retorna Blade view, o que faz Inertia `<Link>` silenciar (click no-op)"*. Ou seja: a
capacidade existe, está roteada, e **está fora da navegação por ser Blade**. Migrar devolve o
acesso — isso não depende de haver meta cadastrada.

## 2. Superfície atual (medida em `origin/main`, 2026-08-26)

| Rota | Verbo | Controller | View Blade | Tamanho |
|---|---|---|---|---|
| `/ia/metas` | GET | `MetasController@index` | `copiloto::metas.index` | 983 B |
| `/ia/metas/create` | GET | `MetasController@create` | `copiloto::metas.create` | 1.390 B |
| `/ia/metas` | POST | `MetasController@store` | — (redirect) | — |
| `/ia/metas/{id}` | GET | `MetasController@show` | `copiloto::metas.show` | 1.516 B |
| `/ia/metas/{id}/edit` | GET | `MetasController@edit` | `copiloto::metas.edit` | 1.059 B |
| `/ia/metas/{id}` | PATCH | `MetasController@update` | — (redirect) | — |
| `/ia/metas/{id}` | DELETE | `MetasController@destroy` | — (redirect) | — |
| `/ia/metas/{id}/reapurar` | POST | `MetasController@reapurar` | — (redirect) | — |
| `/ia/metas/{id}/fonte` | GET | `KB\FontesController@show` | `copiloto::fontes.show` | — |

O docblock do controller se declara **STUB spec-ready**. As views são AdminLTE cru
(`@extends('layouts.app')`, `.box`, `.form-control`, `.btn`), fora do sistema de token.

## 3. Contrato preservado (o que NÃO pode mudar)

Derivado das views, não inventado:

**Index** — tabela `Nome · Unidade · Origem · Ativo`; nome é link pro detalhe; botão primário
`Nova meta`; vazio literal **"Nenhuma meta cadastrada."**

**Create** — campos `nome` (obrigatório), `slug` (obrigatório, `pattern="[a-z0-9_]+"`),
`unidade` (`R$` · `qtd` · `%` · `dias`), `tipo_agregacao` (`soma` · `media` · `ultimo` ·
`contagem`). Botão `Criar`. Validação real é `StoreMetaRequest` — **não reimplementar no front**.

**Show** — `Slug`, `Tipo`, `Origem`, `Escopo` (`Business #N` ou **`Plataforma`** quando
`business_id` é null); tabela `Últimas apurações` (`Data` · `Valor realizado`, `number_format`
pt-BR 2 casas, **limite 12**); vazio **"Nenhuma apuração ainda."**; 3 ações — `Forçar reapuração`
(POST), `Editar`, `Fonte`.

**Edit** — só `nome` e `unidade` (o create tem 4 campos, o edit tem 2 — **é assim de propósito**,
`slug` e `tipo_agregacao` não se editam). Botões `Salvar` + `Cancelar`.

⚠️ **`destroy` é soft**: faz `update(['ativo' => false])`, não apaga linha. A UI não pode dizer
"excluir" — o verbo honesto é **desativar**.

## 4. Multi-tenant — Tier 0, e aqui tem armadilha

`Meta` tem `business_id` e global scope. **`MetaApuracao` NÃO tem coluna `business_id`** — o
escopo é **indireto, via `meta_id`**. O próprio controller documenta: tocar apuração a partir
do `$id` cru da URL **vazaria entre tenants**; carregue a `Meta` pelo global scope ANTES.

Consequência para a migração: qualquer payload novo que leia `MetaApuracao` tem de partir da
`Meta` já resolvida. Pest cross-tenant obrigatório (tenant fictício **98** vs **99**,
[ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **nunca biz=4**.

`reapurar` já passa `businessId` explícito ao `ApurarMetaJob` porque o worker do CT 100 não tem
`session()`. **Preservar.**

## 5. Padrão de Tela

| Tela | PT | Razão |
|---|---|---|
| `Metas/Index` | **PT-01 Lista** | lista paginável de entidade |
| `Metas/Create` · `Metas/Edit` | **PT-02 Form/Drawer** | form de cadastro (modo FOCO — o SPEC põe `/ia/metas*` **fora da fusão**) |
| `Metas/Show` | **PT-03 Detalhe** | detalhe full-page com histórico |

⚠️ **`/ia/metas*` fica FORA das abas** — o SPEC (US-COPI-148) diz literalmente *"Fora da fusão
(ficam FOCO, sem abas): `/ia/pro` e `/ia/metas*`"*. Não trazer para dentro do `JanaSubNav`.

## 6. Ondas (1 PR = 1 intent, ≤300 linhas)

- **F2 · baseline** — Pest do `store`/`update`/`destroy`/`reapurar` **antes** de tocar em UI.
  Sem baseline, regressão silenciosa (proibição MWART).
- **PR-1 · `Metas/Index`** — PT-01, `Inertia::defer` na lista, vazio com a copy literal.
- **PR-2 · `Metas/Show`** — PT-03 + as 3 ações; `Fonte` continua saindo pro KB.
- **PR-3 · `Metas/Create` + `Metas/Edit`** — PT-02; validação continua no FormRequest.
- **PR-4 · cutover** — remover as 4 views Blade, **devolver o ghost `metas`** ao `DataController`
  (é o que fecha o defeito real), lápide no lugar das views.

Cada PR: charter + `casos.md` + teste citando o UC (`casos-gate` é required).

## 7. Riscos declarados

1. **Sem rede de pixel** — `visual-regression` saiu do required em 2026-08-26 (#6278, decisão [W]).
   O gate é olho humano + `contrato-de-tela`.
2. **`ativo=false` não some da lista** — o `index` ordena por `ativo` desc mas **não filtra**.
   Meta desativada continua listada. É o comportamento atual; mudá-lo é decisão [W], não conserto.
3. **US-COPI-031 tem metade aberta** — `reapurar` não aceita range; apagar apurações destruiria
   as 12 janelas que a US-COPI-011 exige. Contrato de rota novo = decisão [W]. **Não resolver de passagem.**
4. **`slug` é imutável de fato** (não está no edit) mas nada no banco impede. Não introduzir
   edição de slug nesta migração.

## 8. Definição de pronto

`/ia/metas` renderiza Inertia · ghost `metas` de volta na SubNav e o `<Link>` navega · 4 telas
com charter + casos + teste · Pest cross-tenant 98×99 verde no CT 100 · zero view Blade em
`Modules/Jana/Resources/views/metas/` · smoke real em prod colado no PR do cutover (R1).
