---
page: /asset/asset-maintenance
component: resources/js/Pages/Patrimonio/Manutencoes.tsx
owner: wagner
status: draft
parent_module: AssetManagement
related_us: [US-ASSET-004]
related_adrs: [0394-endereco-de-ui-do-patrimonio-pages-patrimonio, 0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header, 0253-primitivos-layout]
related_prototype: prototipo-ui/cowork/patrimonio-page.jsx
related_runbook: memory/requisitos/AssetManagement/RUNBOOK-manutencoes.md
tier: B
charter_version: 1
last_validated: "2026-09-08"
---

# Page Charter — Patrimonio/Manutencoes (DRAFT)

> **Segunda tela Inertia do `Modules/AssetManagement`.** Não funda nada: herda o
> `_shared/PatrimonioSubNav.tsx` que [Bens](./Bens.charter.md) criou em 2026-09-08 (#7035).
> Padrão de Tela: **PT-01 Lista** ([PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)).

> **`related_runbook` acima não é decoração — é o que destrava o hook.** O
> `block-mwart-violation` deriva o RUNBOOK do nome da pasta de `Pages/`
> (`Pages/Patrimonio/` ⇒ `requisitos/Patrimonio/`, que não existe e não deve existir) e
> bloqueia; o caminho previsto pelo próprio hook é este campo, apontando pro RUNBOOK real em
> `requisitos/AssetManagement/`. É por isso que **este charter foi escrito antes do `.tsx`** —
> o [charter de Bens](./Bens.charter.md) deixou o aviso, e ele valeu.

## Mission

Mostrar a fila de manutenções do patrimônio como lista operável — o que está fora de operação,
com quem, em que prioridade e há quanto tempo — **e dizer ao usuário quando ele está vendo
apenas um recorte**. A tela é de acompanhamento: a manutenção *nasce* em Bens ("enviar pra
manutenção"), não aqui.

## Goals — Features (faz)

- Lista paginada com as **10 colunas que o Blade já entregava**: código, bem, situação,
  prioridade, garantia, detalhes, enviado em, atribuído a, criado por e ações.
- **Três filtros do Blade**, na URL (link compartilhável, botão voltar honesto): situação,
  prioridade e responsável.
- **Busca** por código da manutenção, nome/código do bem e detalhes.
- **Aviso de escopo restrito**: quem tem apenas `asset.view_own_maintenance` lê, acima da
  tabela, que a lista mostra só onde ele é responsável. **Ganho real sobre o Blade**, que
  filtrava calado — e o filtro já existia no servidor (`index()` `:73`).
- **Ações de linha do Blade**: editar (modal Blade) e excluir (com confirmação nomeando a
  manutenção).
- **Realce da linha em andamento** (`rowState: 'urgent'`), como o protótipo faz.
- Sub-navegação do módulo via `_shared/PatrimonioSubNav`, com `hidePrimary`.

## Non-Goals — Features (NÃO faz)

- ❌ **Coluna de custo e KPIs de dinheiro** ("Custo no ano", "Maior conserto"). O protótipo os
  mostra; **a tabela `asset_maintenances` não tem coluna de valor** e **o Blade não mostra**
  (medido 2026-09-08: 0 menções a `cost|custo|amount|valor|price` nas 3 views, com controle
  positivo). **Decisão [W] 2026-09-08 — "o custo já foi decidido nas regras do Blade, deve ser
  igual"**: a tela migrada é igual ao Blade, sem custo. Não foi inferência do agente; foi
  resposta do dono, confirmada contra as três fontes.
- ❌ **Colunas "Prestador" e "Devolvido"** (protótipo): não existem no banco.
- ❌ **KPIs de contagem no topo** (ex.: "Em aberto"): a contagem é sobre o conjunto INTEIRO, e
  derivá-la da página corrente daria número que mente — mesmo critério que
  [Bens §Non-Goals](./Bens.charter.md) usou para adiar os sub-recortes. Exige agregação no
  servidor.
- ❌ **Ação "Concluir"** (primária no protótipo): não há endpoint, e o efeito declarado é criar
  título a pagar no Financeiro — é dinheiro, pede a REGRA MESTRE de VALOR, não cabe em migração
  de tela.
- ❌ **Criar manutenção a partir daqui.** O Blade não tem botão de criar nesta listagem e o
  fluxo nasce em Bens (`/asset/asset-maintenance/create?asset_id=N`). Pôr um CTA aqui inventaria
  fluxo.
- ❌ **Edição inline / modal React.** O `edit` segue Blade nesta onda.

## Anti-hooks (NÃO faz automaticamente)

- ❌ **Não derivar custo, total ou média de nada** — não há fonte, e valor tem regra própria.
- ❌ **Não replicar os nomes de `getActivitylogOptions()`**: o Model audita `start_date`,
  `end_date` e `amount`, e **as três não existem** na tabela (`_saida-04.md §2a-bis`).
- ❌ **Não reimplementar a guarda de permissão na tela** — ela é do controller (#7034), onde os
  dois `if` sequenciais substituíram o `&&` insatisfazível e o `|| subscription` que anulava.
- ❌ **Não esconder ação por permissão que o módulo não declara.** Não existe permissão de
  escrita de manutenção; o Blade mostra editar/excluir em toda linha sem `can()`. A tela
  **preserva** isso — inventar um gate aqui seria decidir produto no `.tsx`.
- ❌ **Não derivar "garantia crítica"** — recorte que o servidor não tem.
- ❌ **Não passar `<SelectItem value="">`** ao Radix (§5 2026-06-29): sentinela `TODAS`.

## Dívida herdada — declarada, não escondida

- **Escopo de escrita sem dono.** `edit`, `update` e `destroy` filtram só por `business_id`,
  **não por dono**: quem tem apenas `view_own_maintenance` vê só as suas na lista, mas pode
  editar/remover a de outro se souber o id. **Não é regressão** desta tela nem do #7034 — antes
  dele, *qualquer* usuário do business já podia. Fechar exige permissão de escrita que o módulo
  não declara: **decisão de produto, de [W]**.
- **`maitenance_id` sem o segundo `n`** — o typo está na coluna, no Model e no nome do
  controller. A tela lê a coluna real e **expõe o rótulo correto** ("Código"); renomear é
  migration, fora do escopo.

## UX Targets

- Primeiro paint sem esperar a lista (`Inertia::defer` + esqueleto).
- Tabela legível a **1280px**, o monitor do piloto — larguras declaradas por coluna
  (`meta.width`), com a coluna "Bem" fluida absorvendo a sobra.
- Vazio de dado e vazio de filtro **distinguíveis** — e, no papel restrito, o aviso de escopo
  fica visível junto do vazio, porque "não há manutenção" e "não há manutenção **sua**" são
  respostas diferentes.

## Pendências antes de `status: live`

- [ ] Smoke com sessão real nos dois papéis (vê-todas × vê-só-as-suas) — o `curl` sem sessão
      devolve 302 do middleware `auth` e **não exercita** nem a guarda nem o recorte.
- [ ] Decisão [W] sobre o escopo de escrita (dívida herdada acima).
- [ ] Comparação visual medida contra o protótipo (`design-diff --probe` nos dois lados), com a
      ressalva de que 3 colunas e 2 KPIs do protótipo **não têm fonte de dado** e a divergência
      neles é esperada e declarada.

## Refs

- RUNBOOK: [`RUNBOOK-manutencoes.md`](../../../../memory/requisitos/AssetManagement/RUNBOOK-manutencoes.md)
- Casos: [`Manutencoes.casos.md`](./Manutencoes.casos.md)
- Blade de origem: `Modules/AssetManagement/Resources/views/asset_maintenance/index.blade.php`
- Fonte visual: `prototipo-ui/cowork/patrimonio-page.jsx`, `AbaManutencoes` (`:475`)
- Guarda de permissão: [#7034](https://github.com/wagnerra23/oimpresso.com/pull/7034)
- Playbook: [`_saida-06-manutencoes.md`](../../../../prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/_saida-06-manutencoes.md)
