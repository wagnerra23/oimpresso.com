---
sessao: "15"
titulo: "Forma — Aprovações — saída da thread"
autor: "[CL]"
criado: 2026-09-29
base: 5606344ca
thread: 15-forma-aprovacoes.md
veredito: "entregue em 2 PRs (W15: sem rejeitar em lote, 15c saiu) — KPI-filtro, barra com Estado·Tipo·Prioridade, fila como widget do protótipo com tabela densa e rodapé legal; Toolbar não entrou (colide com PageFilters) e a frase de contagem não entrou (sem fonte)"
---

# _saída 15 · Forma das Aprovações

## Decisão que abriu a thread

**W15** respondida em 2026-09-29 (§7 do `00-INDICE.md`): não se cria endpoint de rejeitar em lote; a
15 fica em 2 PRs, **15a filtros+kpis** e **15b lista**. A **15c (lote)** saiu. Por isso o "motivo do
lote" dentro do `BulkActionBar`, que o texto da thread pedia, **não foi desenhado**: o único endpoint
de lote é `aprovarEmLote` (só `ids`), e um campo de motivo sem destino seria afordância falsa (LC-15,
o próprio PARAR SE da thread). A barra de lote segue com "Aprovar selecionadas" e o diálogo do DS.

## Entregue

| PR | parte | o que muda em `Pages/Ponto/Aprovacoes/Index.tsx` |
|---|---|---|
| #8205 | 15a | KPIs como `KpiCard variant="filter"` (`filterTone` do `TOM_KPI_FILTRO` do protótipo), 6 numa linha a 1280 com gap 10px, âncora `aprovacoes-kpis-estado` no DOM · seletor **Estado** no `PageFilters` (3 colunas) · "Todos" literal |
| #8216 | 15b | fila vira o widget do protótipo: `section[data-contract]` + `Card flush`, `h2` "Fila de aprovações" com ícone e badge "(N itens)" · tabela densa com as strings do `density="dense"` do `shared/DataTable` · colunas "Data / intervalo" e "Ação" · coluna de seleção sempre presente com o `Checkbox` do DS (desabilitado fora de pendente) · Aprovar/Rejeitar nas variantes do `Button` · rodapé legal `pt-legal` |

Testes: `tests/js/ponto-aprovacoes-forma.test.tsx` (novo, 4 casos) e
`tests/js/ponto-aprovacoes-lote-dialogo.test.tsx` (UC-PAPR-05, sem mudança) — 8 verdes.

## Comportamento que a forma exigiu

"Todos" em Estado manda `estado=` vazio. O `AprovacaoController@index` faz
`input('estado', PENDENTE)`: sem o parâmetro, cai em pendente, e o "Todos" nunca mostraria
todos. Abrir a tela sem filtro segue em pendente (UC-PAPR-03 intacto), e "Limpar tudo" também.
Controller não foi tocado.

## Não entrou, e por quê

- **`Toolbar` da barra** — o lugar dele na produção é o `PageFilters`, que a thread manda manter.
  Os dois disputariam a mesma faixa: é o PARAR SE da thread, aplicado a essa peça só.
- **"N pendentes no filtro · selecione para decidir em lote"** — não há fonte: a paginação é no
  servidor e `contagens` não aplica tipo/prioridade. Número inventado é proibido pelo bloco B.
- **Coluna "Criada"** fica — a produção a tem e o protótipo não; remover seria regressão (§"o que esta
  thread não é").
- **Cargo do colaborador** na sub-linha (`0007 · Atendimento balcão` no protótipo) — o payload só traz
  matrícula. Fica só a matrícula.
- **Migrar a fila para `shared/DataTable`** (W11) — a fila pagina com partial reload (`only:`, D-14);
  a tabela ficou própria com as mesmas classes do `dense`.

## Medição

Prod pós-15a (`0c02c9554`, 1280×900, dark, WR2 biz=1) × espelho servido por `servirEstatico`
(rota `pt-aprovacoes`), mesma sonda do `design-diff --probe` nos dois lados:

- `design-diff --compare prod design --check` → **D2 layout · D4 tipografia · D6 cor · D8 alinhamento
  do KPI · D9 texto: IGUAL · DIVERGE(bug) 0**. KPIs: 6 `BUTTON` `data-variant="filter"`, alinhados à
  esquerda, valor 18px; título 22/600; barra em 1 linha visual.
- **rc=2 (NÃO MEDI)**, e fica dito: (a) o frescor do espelho não está provado nesta rodada
  (`cowork-mirror-freshness` precisa de `DesignSync.get_file`); (b) a sonda de saúde procura tokens com
  nome do protótipo (`--accent`, `--pos`…) que o app não declara. Logo "0 divergências" é "igual à
  cópia do espelho", não "igual ao design".
- Grid dos KPIs em prod: 6 trilhas de ~151px, `gap: 10px` (alvo: gap 10px, 6 filhos); nenhum rótulo
  trunca.
- **Linha da tabela: SEM-DADO** — o biz=1 de prod não tem intercorrência (fila vazia), então a célula
  não pôde ser medida contra o protótipo. A forma da fila fica provada pelo teste
  `ponto-aprovacoes-forma.test.tsx` (colunas, badge, caixa desabilitada, intervalo).

## Placar

entregue 5 de 5 seções do alvo com a forma do protótipo (header · tabs já vinham da 28/W9 · kpis ·
barra · fila) + `pt-legal` · ausentes dentro das seções: `Toolbar` (colide com `PageFilters`), frase
de contagem (sem fonte), cargo (sem fonte).

## PARAR SE

Disparou parcialmente (Toolbar × PageFilters) e foi resolvido mantendo o `PageFilters`. O do motivo
de lote não chegou a disparar: W15 tirou a 15c antes.
