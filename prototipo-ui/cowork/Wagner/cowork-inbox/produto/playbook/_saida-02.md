---
sessao: "02"
titulo: "Produto/Cadastros — abas Unidades e Marcas (Categorias pendente) — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: 0754c7213
thread: 05-cadastros.md
veredito: "entregue parcial — tela Produto/Cadastros nasce com 6 abas, Unidades e Marcas vivas; Categorias e criar/editar em modal ficam pendentes (motivos abaixo)."
---

# _saída 02 · Cadastros de apoio — Unidades · Marcas

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

1. **Categorias** — a ficha permite ("se passar de ~300 linhas, entregue Unidades + Marcas"). O PR
   já tem ~600 linhas com trio + teste + runbook. Categorias tem hierarquia e o `taxonomy/destroy`
   leva as filhas junto (R7, UC-CAD-10/11): precisa de teste próprio. Aba aponta pra `/taxonomies?type=product`.
2. **Criar/editar em modal na tela (R2, UC-CAD-01/02)** — segue nos modais da Blade (`?classico=1`),
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
