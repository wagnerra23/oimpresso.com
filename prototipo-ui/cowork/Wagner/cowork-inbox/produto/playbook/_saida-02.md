---
sessao: "02"
titulo: "Produto/Cadastros — abas Unidades, Marcas e Categorias — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: 0754c7213
thread: 05-cadastros.md
veredito: "entregue — tela Produto/Cadastros com 6 abas; Unidades e Marcas (#8371) e Categorias (continuação) vivas. Criar/editar em modal segue pendente (motivo abaixo)."
---

# _saída 02 · Cadastros de apoio — Unidades · Marcas · Categorias

> Duas entregas: **parte 1** (Unidades + Marcas) no [#8371](https://github.com/wagnerra23/oimpresso.com/pull/8371);
> **parte 2** (Categorias) na continuação — seção própria logo abaixo. O resto do arquivo é a parte 1,
> com as pendências já atualizadas.

## Parte 2 · Categorias

| arquivo | o quê |
|---|---|
| `app/Http/Controllers/UnitController.php` | aba `categorias` em `cadastros()`: `can` por `category.*`, lista só `category_type = 'product'` escopada por `business_id`, pai seguido das filhas (`pai`, `pai_id`), `em_uso` (produto pela categoria **ou** subcategoria) e `filhas` por subconsulta. Subcategoria órfã (pai já apagado) vai pro fim. |
| `app/Http/Controllers/TaxonomyController.php` | `destroy` **recusa** categoria de produto em uso ou com subcategoria, dizendo quantos e quantas. Outras taxonomias (`category_type` de módulo) seguem como eram. |
| `resources/js/Pages/Produto/Cadastros/Index.tsx` | aba Categorias viva no lugar do link: `↳ Lonas · em Comunicação visual`, código, descrição, contagem clicável, busca por nome ou código, confirmação que diz o motivo da recusa (produtos e/ou subcategorias). Novo/Editar abrem `/taxonomies?type=product` (modal da Blade). |
| `…/Index.charter.md` · `…/Index.casos.md` | R7 no charter; UC-PCADAP-09..11 (cada um com teste); o `[BACKLOG]` de Categorias saiu. |
| `tests/Feature/Produto/ProdutoCadastrosContratoTest.php` | +3 testes (tenant 98 × 99), mesma lane. |
| `.github/workflows/estoque-pest.yml` | +`TaxonomyController.php` nos dois filtros (SOB TESTE). |

### Errata — a regra do pai com filhas foi trocada

O protótipo (`produto-cadastros.jsx:367`, charter proposto R7, UC-CAD-11) dizia que **as subcategorias
vão junto** ao excluir o pai. O enunciado desta continuação manda **recusar** categoria com
subcategoria ou produto, e foi o que entrou. Antes desta thread o servidor fazia uma terceira coisa: o
soft delete apagava só o pai e deixava filhas e produtos apontando pra uma categoria apagada. Recusar
é o único dos três que não perde vínculo. **Pedido ao Cowork:** atualizar o R7 / UC-CAD-11 do trio
proposto para "recusa", ou [W] decide que o cascata volta.

### Pendências da parte 2

- **Contagem da subcategoria abre o índice pela categoria pai.** O índice (`/products/unificado`) só
  filtra `categoria` (= `category_id`); filtro por subcategoria tocaria `Pages/Produto/Unificado/`, que é
  `nao_toca` desta thread. Segue o protótipo (`filtro={ cat: nomePai }`).
- **Criar/editar categoria** segue no modal da Blade de `/taxonomies?type=product`, mesmo corte da parte 1.
- **`related_us` do charter** — o advisory `charter related_us join` acusa o charter sem US. O SPEC do
  Produto não tem US de "Cadastros de apoio"; criar uma é decisão de escopo ([W]) e fica fora do prefixo.

## O que entrou

| arquivo | o quê |
|---|---|
| `resources/js/Pages/Produto/Cadastros/Index.tsx` | Tela única com as 6 abas do protótipo. Unidades e Marcas vivas: lista, busca (`/` foca), coluna "Produtos" clicável → `/products/unificado?unidade=` / `?marca=`, exclusão com a recusa dita antes. Variações, Grupos de preço, Categorias e Garantias abrem a tela atual de cada uma. |
| `…/Index.charter.md` · `…/Index.casos.md` | Trio copiado de `cowork-inbox/produto-telas-novas/Cadastros.*`, recortado ao entregue. Casos UC-PCADAP-01..08 (cada um com teste); os UC-CAD não entregues viraram `[BACKLOG]` com a origem citada. |
| `app/Http/Controllers/UnitController.php` | `/units` desvia por `X-Inertia` (§5 2026-09-08) para `cadastros()`: `can` por aba (`unit.*`, `brand.*`), listas escopadas por `business_id`, `em_uso` por subconsulta em `products`. Ajax do DataTables e `?classico=1` seguem na Blade. |
| `app/Http/Controllers/BrandController.php` | `destroy` passa a **recusar marca em uso** (charter R4). Antes apagava e deixava produto apontando pra marca apagada. Muda também a tela clássica. |
| `governance/design/contracts/produto-cadastros.contract.json` | Contrato do `_saida-07` (exceção 3 desta leva). |
| `tests/Feature/Produto/ProdutoCadastrosContratoTest.php` | 8 testes, tenant 98 × 99, lane `estoque-pest` (pega `tests/Feature/Produto/**`). |
| `memory/requisitos/Produto/_telas/RUNBOOK-produto-cadastros.md` | Exigido pelo hook `block-mwart-violation` (ADR 0104 F1) para criar a Page — fora do `prefixo`, sem escape. |
| `.github/workflows/estoque-pest.yml` | +3 paths SOB TESTE (controllers + Page) nos dois filtros (§5 2026-08-02). |
| `SUPERFICIE.md` · `_STATUS-GENERATED.md` (Produto) | Regenerados pelos donos (`module-surface`, `requisitos-status`). |

## Errata do contrato (recorte — declarado, não silencioso)

O `_saida-07` derivou `barra`/`ajuda`/`tabela` da **aba default do protótipo, Variações** (thread 03).
Copiado literal, o contrato obrigaria a Page desta thread a carregar a copy de uma aba que ela não
entrega. As 3 seções foram **re-ancoradas na `AbaUnidades` da mesma fonte**
(`produto-cadastros.jsx:290-294`), com a mesma regra (copy do protótipo, nunca do `.tsx`).
`header`/`widget`/`abas` ficaram idênticas. O motivo está no campo `_recorte` do próprio contrato.
Quando a thread 03 trouxer Variações, ela decide se a aba inicial volta a ser Variações e re-ancora.

`node scripts/contrato-de-tela.mjs --contract governance/design/contracts/produto-cadastros.contract.json` → `✅ limpo` (6/6 seções + ordem).

## Pendências (com o porquê)

1. ~~**Categorias**~~ — entregue na parte 2 (acima). Registro da parte 1, mantido como foi escrito: *a
   ficha permite ("se passar de ~300 linhas, entregue Unidades + Marcas"); Categorias tem hierarquia e
   precisa de teste próprio.* (A frase "o `taxonomy/destroy` leva as filhas junto" estava errada: o
   destroy era soft delete só do pai.)
2. **Criar/editar em modal na tela (R2, UC-CAD-01/02)** — segue nos modais da Blade (`?classico=1`; Categorias em `/taxonomies?type=product`),
   mesmo corte do Crm/03. Motivo técnico: `UnitController@update` **zera** `base_unit_id` quando o
   form não manda `define_base_unit`, e o multiplicador passa por `num_uf` (pt-BR). Um modal novo que
   errasse isso alteraria conversão de unidade — é ESTOQUE (regra mestre: dupla prova + [W]). Fica
   pra uma thread que trate disso com o [W].
3. **`EmptyState variant="no-perm"`** — a ficha pede, mas o `EmptyState` vivo (`Components/shared/EmptyState.tsx`)
   só tem `default|search|error|success`. Variante nova no DS é decisão [W]; usei `default` com ícone
   `lock` e o motivo escrito ("Seu papel não tem unit.view — quem libera é o administrador, em Papéis").
4. **Menu** — `/units` já abre a tela nova; o item de menu e o deep-link `/brands → aba Marcas` são da thread 08.

## Provas do índice (conferidas)

| prova | estado |
|---|---|
| `Pages/Produto/Cadastros/Index.tsx` | ✅ existe |
| `…/Index.charter.md` | ✅ existe |
| `…/Index.casos.md` | ✅ existe |
| `UnitController.php` contém `Inertia::render('Produto/Cadastros/Index'` | ✅ |

**O placar não vai mostrar `feito` sozinho**, por dois motivos que o índice ainda não registra:
`D3` está `respondida:false` no json (respondida por [W] em `_DECISOES-W-2026-10-01.md`: Page
parametrizada) e a dependência `07` segue sem as 4 provas (os contratos só entram com a Page de cada
thread — ver `_saida-07`). Pedido ao Cowork: marcar D3 respondida e tirar `07` do `depende_threads`
da 02 (ou mover cada contrato pro prefixo da thread da Page, como o `_saida-07` recomenda).

## Gates locais

`contrato-de-tela` ✅ · `--map --check` ✅ · `casos-coverage-guard` ✅ (sem violação nova) ·
`screen-coverage --check` ✅ · `design-coverage` ✅ · `pt-conformance` ✅ · `pageheader-migration-guard` ✅ ·
`integrity-check` ✅ · `module-surface --all --check` ✅ · `tsc` (arquivo) ✅ · `eslint` ✅.
Pest não roda local (regra): a prova é a lane `estoque-pest` do PR.
