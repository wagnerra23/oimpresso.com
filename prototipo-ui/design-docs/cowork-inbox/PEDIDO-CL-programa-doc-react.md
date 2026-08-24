# Pedido ao [CL] — tela do Programa (Trilha D) em React/Inertia

> [CC] F1. **Não commitado.** Cole pro Code ou abra Issue `cowork-intake`.
> Decisões de [W] 2026-08-06: **quero React** e **sem convivência Blade** — a `/documentacao` migra inteira.
> Base: leitura do `main` em 2026-08-06 16:21Z. A parte A (patch do plano + `reference/GOV-PROGRAMA-DOCUMENTACAO.md`) **já está no `main`**.

## O que está pronto neste pacote

| Arquivo (aqui) | Destino no repo |
|---|---|
| `prototipo-ui-patch/resources/js/Pages/Documentacao/Programa.tsx` | `resources/js/Pages/Documentacao/Programa.tsx` |
| `prototipo-ui-patch/resources/js/Pages/Documentacao/Programa.charter.md` | idem `.charter.md` |
| `prototipo-ui-patch/resources/js/Pages/Documentacao/Programa.casos.md` | idem `.casos.md` |
| `prototipo-ui-patch/prototipo-ui/contrato/programa-doc.contract.json` | `prototipo-ui/contrato/programa-doc.contract.json` |
| `prototipo-ui-patch/app/Http/Controllers/DocumentacaoProgramaController.php` | `app/Http/Controllers/` |

O `.tsx` segue o canon lido no `main` (`AppShellV2` + `PageHeader` canon + lucide + Tailwind, padrão
de `Financeiro/PlanoContas/Index.tsx`). Tabs **underline-active em primary**, nunca pill.

## 1. Rota e superfície — `/documentacao` **migra inteira** pra Inertia

Decisão de [W] 2026-08-06: **não** haverá convivência Blade↔React. A superfície inteira vira Inertia,
e a tela do Programa entra como parte dela — não como exceção.

### 1.1 O que existe hoje (lido no `main`)

`DocumentacaoController` serve três views Blade — índice/documento (`documentacao.doc`) e busca —
com o rail **derivado do disco** (`File::glob(memory/reference/*.md)` + frontmatter `nav_group` /
`nav_order` / `lente`) e o conteúdo lido do corpus (`mcp_memory_documents`), com o fallback de disco
pedido na fase 2. Nada disso muda de fonte: **o que muda é a camada de render**.

### 1.2 Alvo

| Rota | Página Inertia | Substitui |
|---|---|---|
| `/documentacao` | `Documentacao/Index.tsx` | `documentacao.index` |
| `/documentacao/{slug}` | `Documentacao/Doc.tsx` | `documentacao.doc` |
| `/documentacao/busca` | `Documentacao/Busca.tsx` (ou painel dentro de `Index`) | `documentacao.busca` |
| `/documentacao/programa` | `Documentacao/Programa.tsx` ✅ neste pacote | — (nova) |

Props: `nav` (grupos derivados), `lente`, `doc` (`{slug, title, type, authority, updated_at, git_path, html}`),
`toc`, `atual`. O markdown continua convertido **no servidor** (`paraHtml`) — o React recebe HTML
sanitizado, não roda parser de markdown no cliente.

Layout: `AppShellV2` + `PageHeader` canon + `TabBar` das lentes abaixo do header (não segmented no
canto — foi o feedback de [W] 2026-08-06) + rail derivado + coluna de leitura + TOC.

### 1.3 Paridade é obrigatória — não é migração "no olho"

Esta é exatamente a pior dimensão da régua do projeto (**8/100**, "migração preservou função") e a
Onda 0d existe pra isso. Portanto:

- rodar o **artefato + gate de paridade Blade↔React** da Onda 0d nesta migração — sem ele, o PR não
  deve ser aberto;
- capturar as saídas Blade atuais (índice, 3 documentos representativos, busca com e sem resultado,
  404, 503 sem corpus) **antes** de trocar a camada, e comparar depois;
- o fallback de disco e o `abort(503)` da busca **precisam sobreviver** — hoje eles são a diferença
  entre "menu que abre" e "menu que dá 404".

### 1.4 Trio por página

Cada uma das quatro páginas precisa de `.charter.md` + `.casos.md` (o `Programa` já vem com os dois).
Sem isso `prototipo-readiness` não marca ✅ e o `casos-gate` conta débito novo — migração é o momento
em que esse débito é mais barato de pagar.

### 1.5 Referência visual

O protótipo Cowork `documentacao-page.jsx` (rota `documentacao`) já resolve a arquitetura de
informação: lentes com contagem, rail agrupado com ordinal derivado, linha "oculto nesta lente",
TOC com scroll-spy, ⌘K e a faceta de módulo na busca. Use como referência de estrutura — o que
atravessa é a IA e o contrato, não o código.

### 1.6 Custo honesto

Isto deixa de ser "uma página nova" e passa a ser **migração de superfície**: 4 páginas, 4 trios,
parser/serviços do Programa, gate de paridade e o rail derivado reimplementado em props. Recomendo
partir em dois PRs — (i) migração das três telas existentes com paridade provada, (ii) o Programa em
cima da superfície já migrada.

## 2. O que falta do lado do servidor — é aqui que mora o trabalho

O `.tsx` é **100% props-driven de propósito**: sem esses dois serviços a tela não deve subir, porque
subir com conteúdo estático viola `UC-PROGD-01` e `UC-PROGD-02`.

**a) `App\Support\Documentacao\TrilhaDParser`** — lê a § Trilha D do
`memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` e devolve:

| chave | forma | origem na § Trilha D |
|---|---|---|
| `atualizado_em` | string | frontmatter / `reviewed_at` do plano |
| `estacoes` | `[{n, fase, titulo, resumo, entrada, maquina, regra}]` | D.2 (o ciclo) — 11 itens |
| `ondas` | `[{id, nome, escopo, saida, gate}]` **sem status** | D.5 (tabela D0–D10) |
| `caminhos` | `[{tipo, resumo, fluxo[], campos[]}]` | D.4/D.5 caminho por tipo |
| `camadas` | `[{camada, componentes, dono}]` | D.1 (seis camadas) |
| `dod` | `[{texto, onda, fechado, parcial}]` | D.8 |
| `batimento` | `[{momento, maquina, efeito}]` | D.7 |
| `estacao_de_retorno` | `{de:'11', para:'02'}` | a volta do ciclo |

O parser **não** normaliza status nem inventa campo: o que não está no plano volta vazio, e a tela
mostra vazio. Se o plano mudar de forma, o parser falha alto (teste de contrato), não adivinha.

**b) `App\Support\Documentacao\EstadoDasOndas`** — projeção das tasks MCP
(`parent_plan=programa-ondas`): `aplicarEm(array $ondas)` injeta `status` (`todo|doing|done`) e
`tasks_abertas`; `statusDaTask('US-INFRA-048')`. **Bloqueio conhecido:** o MCP está sem credencial
local desde o handoff de 05/08 — enquanto isso, o serviço deve devolver estado **indisponível** e a
tela renderiza sem estado, nunca com `doing` chumbado.

Vocabulário travado no `acordos_estado` do contrato: `todo · doing · done`. Rótulo humano
("na fila", "em execução") é tradução na borda, no `.tsx`, e está declarado lá.

## 3. Testes que fecham os 5 UC (hoje todos ❌)

| UC | Teste sugerido | Tipo |
|---|---|---|
| 01 | task `done` no fake do MCP → payload da onda vem `done`; e `grep` de `'doing'`/`'em execução'` no parser = 0 | Pest feature + guard |
| 02 | alterar a § Trilha D numa fixture → payload muda sem tocar PHP/TSX | Pest feature |
| 03 | render do componente: nenhum `<input>`/`<button>` que dispare mutação (só navegação) | Vitest |
| 04 | `?vista=ondas` seleciona a aba; aba ativa tem `border-primary` (não pill) | Vitest |
| 05 | visitante anônimo → redirect/403; payload sem `business_id`, host ou token | Pest feature `[T0]` |

Contrato de tela roda **advisory** no `contrato-de-tela.yml` antes de promover — 13 seções com
âncora `data-contract` já marcadas no `.tsx`.

## 4. Ordem sugerida

**PR 1 — migrar a superfície** (`/documentacao`, `/{slug}`, `/busca`)
1. Capturar a linha de base Blade (índice · 3 docs · busca com/sem resultado · 404 · 503 sem corpus).
2. Controller devolvendo props (rail derivado, lente, html do servidor); páginas `Index`/`Doc`/`Busca`.
3. Gate de paridade da Onda 0d verde + trio das três páginas.

**PR 2 — o Programa em cima da superfície migrada**
4. Serviços `TrilhaDParser` + `EstadoDasOndas` com fixture do plano — sem UI.
5. Rota + controller + `Programa.tsx` com o contrato advisory.
6. Testes dos 5 UC → `casos_coverage` sai de 0%.
7. Item no rail (grupo `governanca`) apontando pra rota nomeada.
8. [W] ratifica no merge; screenshot 1280/1440 pra [W2].

## Fora de escopo

- Portar `programa-doc-page.jsx` / `documentacao-page.jsx` (Cowork) linha a linha — são referência visual; o que atravessa é o contrato.
- Marcar DoD/onda pela UI: a tela é read-only (`UC-PROGD-03`).
- Trocar a fonte do rail ou do conteúdo: continua disco (frontmatter) + corpus, com o fallback de disco intacto.
- Promover os hues de fase (220/295/155/75) a token: só quando a tela sair de `proposta`.
