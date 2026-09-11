---
page: /asset/assets
component: resources/js/Pages/Patrimonio/Bens.tsx
owner: wagner
status: draft
parent_module: AssetManagement
related_us: [US-ASSET-001, US-ASSET-W05]
related_adrs: [0394-endereco-de-ui-do-patrimonio-pages-patrimonio, 0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0180-sidebar-v3-5-grupos-ghosts-header]
related_prototype: prototipo-ui/cowork/Wagner/patrimonio-page.jsx
related_runbook: memory/requisitos/AssetManagement/RUNBOOK-bens.md
tier: B
charter_version: 1
last_validated: "2026-09-08"
---

# Page Charter — Patrimonio/Bens (DRAFT)

> **PRIMEIRA tela Inertia do `Modules/AssetManagement`.** Ela funda o `_shared/` e o primeiro
> `Inertia::render` do módulo; as outras 6 telas do playbook herdam o que está aqui.
> Padrão de Tela: **PT-01 Lista** ([PT-01](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)).

> **Pasta ≠ módulo, e isso é decisão, não descuido.** A tela mora em
> `resources/js/Pages/Patrimonio/` por decisão [W] de 2026-09-08
> ([ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md)),
> enquanto o código PHP vive em `Modules/AssetManagement/` e os requisitos em
> `memory/requisitos/AssetManagement/`. **Não existe `Modules/Patrimonio`.**
>
> Consequência prática, medida ao escrever esta tela: o hook `block-mwart-violation` deriva o
> RUNBOOK do **nome da pasta de `Pages/`** (`Pages/Patrimonio/` ⇒ `requisitos/Patrimonio/`) e
> bloqueia — a pasta `requisitos/Patrimonio/` não existe nem deve existir. O caminho previsto
> pelo próprio hook é o campo **`related_runbook` deste charter**, que aponta pro RUNBOOK real:
> *"declaração é autoritativa, adivinhação não"*. **As outras 6 telas do Patrimônio precisam
> escrever o charter ANTES do `.tsx`**, ou batem no mesmo bloqueio.

> **O agrupamento de sidebar não muda.** Patrimônio segue ghost de Estoque no grupo `operar`
> ([ADR 0180](../../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)) —
> endereço de pasta e agrupamento de menu são eixos independentes.

## Mission

Mostrar o patrimônio da empresa como lista operável: o que a casa tem, onde está, com quem
está alocado, quanto vale por unidade e se está em garantia ou em manutenção. É a tela de
partida do módulo — de onde se alcança alocar, mandar pra manutenção, editar e excluir.

## Goals — Features (faz)

- Lista paginada de bens do business corrente, com ordenação e busca **server-side**
  (nome · código · modelo · número de série).
- Os **quatro** filtros que o backend já servia: categoria, local, tipo de compra e
  "somente alocáveis". Todos vivem na URL — link compartilhável, botão voltar honesto.
- Por linha: código, imagem (quando há), nome + modelo, categoria, local, quantidade,
  quantidade alocada, **valor unitário**, janela de garantia com dias restantes, data de
  compra e situação (operando / N em manutenção).
- Ações por linha, cada uma pra rota que já existe: alocar · enviar pra manutenção ·
  editar · excluir — desenhadas conforme a permissão do usuário.
- Sub-navegação do módulo **derivada** de `shell.menu` (`DataController::modifyAdminMenu`),
  nunca declarada aqui.
- Estados: cheia · filtrada-vazia · vazia · carregando (skeleton do `Inertia::defer`) ·
  sem-permissão (403 do gate `asset.view`).

## Non-Goals — Features (NÃO faz)

> Proposta [CC] a partir da medição desta onda — **[W] aprova antes de `status: live`.**
> Cada item vira Pest GUARD quando a onda correspondente entrar. Os quatro primeiros são
> **adiamento com motivo** (§5 do RUNBOOK), não recusa permanente; os demais são limite real.

- ❌ NÃO oferece os sub-recortes "Garantia crítica" e "Em manutenção" do protótipo
  (`patrimonio-page.jsx:355`) — pedem predicado SQL novo. Recorte que filtra só a página
  corrente mente na contagem da pílula.
- ❌ NÃO soma total de valor no rodapé. Valor é **REGRA MESTRE Tier 0**: exige prova por dois
  caminhos independentes + antes→depois apresentado ao [W]. O valor **por linha** entra.
- ❌ NÃO faz seleção em lote nem BulkBar — as duas ações em lote do protótipo (exportar
  seleção, mandar pra manutenção) não têm endpoint hoje.
- ❌ NÃO exporta CSV, não imprime, não configura colunas nem densidade.
- ❌ NÃO cria nem edita bem: `create`/`edit` seguem Blade nesta onda; os botões apontam pra
  essas rotas reais.
- ❌ NÃO cruza tenants — `Asset` não tem global scope, o filtro por `business_id` é manual
  ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0).
- ❌ NÃO afrouxa `permitted_locations()` por parâmetro de query: é restrição de permissão,
  aplicada antes de qualquer filtro escolhido pelo usuário.
- ❌ NÃO renderiza aba que não navega. O protótipo desenha 7 abas; o menu vivo tem 6 ghosts,
  e **Garantias**/**Auditoria** são decisões de produto ABERTAS do [W] (itens 4 e 5 do
  `00-INDICE.md §6`). Enquanto não houver rota, não há aba.

## Anti-hooks (NÃO faz automaticamente)

> Mesma ressalva: proposta [CC], [W] aprova antes de `live`.

- ❌ NÃO escreve nada. A tela é de leitura pura — nenhum caminho aqui grava, apaga ou
  dispara job.
- ❌ NÃO recalcula, arredonda nem converte valor ou quantidade. Exibe o que o backend mandou.
- ❌ NÃO deriva "garantia crítica" no cliente a partir de `dias_restantes` — faixa de
  criticidade é decisão de produto que ninguém tomou.
- ❌ NÃO esconde a coluna `Alocado` por causa do resíduo Tier 0 abaixo: o Blade já a mostrava,
  e escondê-la seria regressão que não conserta o dado.

## Dívida herdada — declarada, não escondida

⚠️ **`Alocado` NÃO é número auditado.** As agregações `allocated_qty` (join `AT`) e
`revoked_qty` (subconsulta `AR`) do `AssetController::index()` **não filtram por
`business_id`** — agregam transação de qualquer empresa. É o gêmeo já catalogado em
`_saida-01.md §9(a)` (*"tem MAIOR alcance — é o índice"*), com thread dona.

Esta onda **preserva a expressão byte-a-byte** e não a conserta, por duas leis que caem
juntas: mexer em quantidade é REGRA MESTRE Tier 0 (prova dupla + antes→depois + [W]) e
1 PR = 1 intent. O que esta onda fez foi dar **um dono só** à expressão
(`AssetController::baseAssetsQuery`), lida pelos dois ramos — antes o cálculo tinha uma
cópia por ramo, e a próxima correção pousaria em só uma delas.

## UX Targets

- Cabe em 1280px (monitor do piloto) sem scroll horizontal **na página**: a tabela rola
  dentro do próprio wrapper (`table-layout: fixed` + `minTableWidth`), em vez de espremer coluna.
- `tabular-nums` em todo número, valor e data.
- Ação por linha nasce à **esquerda** (como o protótipo, `:284`): a tabela é mais larga que
  a janela e o botão primário não pode nascer fora do viewport.
- Nenhum atalho de teclado é anunciado nesta onda — declarar atalho que a tela não implementa
  é afordância falsa.

## Pendências antes de `status: live`

1. [W] aprovar os Non-Goals e Anti-hooks acima (hoje são proposta [CC]).
2. Screenshot aprovado por [W] (gate visual F1.5).
3. `Bens-visual-comparison.md` — comparação medida contra o protótipo (`design-diff --probe`
   nos dois lados), não no olho.
4. Decidir o resíduo Tier 0 de `Alocado` (thread do gêmeo).
5. **US no `SPEC.md` — declarada aqui, ainda 🔒 lá.** O `related_us` deste charter aponta
   `US-ASSET-001` (o registry, cuja superfície de leitura é esta lista) e `US-ASSET-W05`
   (a migração Blade→Inertia). A W05 segue marcada no SPEC como **backlog feature-wish sem
   sinal qualificado**, embora a [ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md)
   e o `SCOPE.md` já a tenham liberado e esta tela seja a entrega dela. Pela regra de
   precedência o SPEC é o elo mais fraco e deveria promovê-la a US ativa, com âncora
   `**Implementado em:**` — fora do prefixo desta onda; registrado em `_saida-06-bens.md §8`.

## Refs

- RUNBOOK: [`memory/requisitos/AssetManagement/RUNBOOK-bens.md`](../../../../memory/requisitos/AssetManagement/RUNBOOK-bens.md)
- Casos: [`./Bens.casos.md`](./Bens.casos.md)
- Fonte visual: `prototipo-ui/cowork/Wagner/patrimonio-page.jsx` (aba `bens`, `:355`) — **alvo**, não
  decisão de produto
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/06-ui-bloqueada.md`
- [PT-01 Lista](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
