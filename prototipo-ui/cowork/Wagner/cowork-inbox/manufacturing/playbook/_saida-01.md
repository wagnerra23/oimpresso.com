---
sessao: "01"
titulo: "Contrato de Tela manufacturing-index + data-contract no Index.tsx"
autor: "[CL]"
data: "2026-09-28"
base: "origin/main 8822fb6a5"
prefixo_tocado: "governance/design/contracts/manufacturing-index.contract.json · resources/js/Pages/Manufacturing/Index.tsx"
fora_do_prefixo: "este recibo"
---
# _saida-01 · Contrato `manufacturing-index`

## 1 · Feito

- **Contrato novo** `governance/design/contracts/manufacturing-index.contract.json`, no molde do
  `manufacturing-recipes`: 5 seções `cabecalho → abas → kpis → filtros → lista`, com 26 strings de
  copy. As seções e a copy são as do pedido (§"O que sobra" item 1), conferidas uma a uma contra o
  `Index.tsx` do main **depois** da thread 04, que tirou a lupa "Aplicar intervalo de datas" e
  trocou o header. Nenhuma string do pedido tinha sumido da tela. `fonte` =
  `cowork/Wagner/manufacturing-producao.jsx` (D-MFG-FONTE).
- **5 âncoras** `data-contract` no `Index.tsx`, só atributos em elementos que já existiam (`nav`
  das abas, grid de KPIs, card de filtros, card da lista). **Exceção declarada:** o `cabecalho`
  ganhou um `<div>` wrapper, porque o `PageHeader` canon não repassa props ao `<header>` (a
  interface não tem `...rest`), e um `data-contract` passado a ele sumiria no DOM (LC-30). É o
  mesmo idioma de `Backup/Index.tsx:158` e `Arquivos/Index.tsx:1328`. Conferido antes que o header
  não é `sticky`, porque um wrapper da mesma altura desligaria o sticky.
- Lógica, estilo, filtro De/Até (UC-OP-06) e o header canon ficaram intocados. Diff do `.tsx`
  (`git diff --numstat`): +18/−14. São 4 linhas que só ganharam o atributo, 4 novas (o wrapper
  abre e fecha, mais 2 de comentário) e 10 reindentadas (o `PageHeader` dentro do wrapper).

## 2 · O `gerar-contrato.mjs` rodou, e o esqueleto dele não foi usado

`node scripts/design/gerar-contrato.mjs Manufacturing/Index` → rc 0, **5/12 partes acionáveis**,
derivadas de `memory/requisitos/Manufacturing/manufacturing-index-gap.md`: header-do-modulo · kpis ·
ordenacao-por-coluna · paginacao · drawer-de-detalhe-da-ordem. Três delas não são regiões da tela
viva: são **decisões abertas** (a Ação de cada uma começa com "Decidir"), e o esqueleto também
aponta `fonte: manufacturing-page.jsx`, que é a da aba Receitas. Seguir o esqueleto teria ancorado
regiões que não existem. Fiquei com as seções do pedido. Fica registrado para o Cowork porque o
gap.md ainda descreve estas 3 partes como "Construir ou rejeitar por escrito": nenhuma delas foi
decidida por esta thread.

## 3 · Verificação

| item | resultado |
|---|---|
| `node scripts/contrato-de-tela.mjs --contract governance/design/contracts/manufacturing-index.contract.json` | **exit 0**. 5 seções "âncora + copy presentes", ordem coerente |
| sanidade: `"Custo unit."` → `"Custo unitario"` | **exit 1**, `copy ausente em "lista": "Custo unitario"`. Restaurado por cópia byte a byte, hash `bd01596cc06f` igual antes e depois, e o gate volta a exit 0 |
| `--anti-tautologia` | 3/26 strings existem no alvo e não na fonte declarada. "Receitas" e "Insumos" estão no protótipo, em `manufacturing-page.jsx:11-12` (a barra de abas do módulo mora lá, não no `-producao.jsx`). "Todos os locais" é copy **adaptada**: o protótipo diz `Todos` (`manufacturing-producao.jsx:51`), e o vivo já dizia "Todos os locais" antes desta thread. O pedido lista essa string, então a tela não foi mexida; se o Cowork quiser igualar, é thread nova |
| `layout-primitives-guard` | exit 0, sem regressão |
| `casos-coverage-guard` | exit 0, nada de Manufacturing |
| `pageheader-migration-guard` | exit 0, nenhuma adoção nova nem dívida tocada |
| `eslint Index.tsx` | exit 0 |
| `tsc --noEmit` | 306 erros **pré-existentes** no repo (o mesmo número do `_saida-04`), **0** em `Manufacturing/Index.tsx` |
| runtime em prod | **pendente até o merge** (LC-30): conferir no DOM que os 5 `[data-contract]` existem, na ordem, em `/manufacturing/production` biz=1 |

## 4 · Fora do escopo, declarado

- O subtítulo (contagem de receitas · ordens) não entra na copy: é montado em runtime, e a 3ª parte
  do subtítulo do protótipo ("custo recalculado") é proibida pelo charter §Forma. Registrado em
  `_pendente_w` no próprio contrato.
- Nenhum arquivo do `nao_toca` foi aberto para escrita (`Recipes.tsx`, contrato `recipes`,
  `prototipo-ui/cowork/` fora deste recibo). A thread 03 (`cowork/Felipe/manufacturing-*`) segue
  suspensa enquanto o #7991 estiver aberto.
