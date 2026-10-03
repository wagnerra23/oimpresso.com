---
sessao: "A1"
titulo: "ALVO de Repair — repair--index · --jobsheet--index · --dashboard--index · --producao-oficina--index"
autor: "[CL]"
data: 2026-10-02
base: "claude/repair-thread-00-puxar (fefdf0c467), sobre origin/main 13bc079894"
thread: 02-alvos.md
veredito: "entregue — 4 alvos, 24 seções, 0 ausentes, cada um medido 2× byte-idêntico; secao-check --todos conforme. As 6 medidas Repair--* NÃO foram refeitas (ver abaixo)."
---

# _saída A1 · ALVO de Repair

## O que saiu

| arquivo | seções | rota do protótipo |
|---|---|---|
| `governance/design/targets/repair--index.{secoes,alvo}.json` | 7 · header · tabs · kpis · filtros · status · lista · rodape | `rep-reparos` (Repair/Index) |
| `governance/design/targets/repair--jobsheet--index.{secoes,alvo}.json` | 6 · header · tabs · recorte · filtros · lista · rodape | `rep-folhas` (JobSheet/Index) |
| `governance/design/targets/repair--dashboard--index.{secoes,alvo}.json` | 6 · header · tabs · alerta · kpis · distribuicao · tendencias | `rep-painel` (Dashboard/Index) |
| `governance/design/targets/repair--producao-oficina--index.{secoes,alvo}.json` | 5 · header · tabs · aviso · contagem · kanban | `rep-producao` (ProducaoOficina/Index) |
| 4 linhas novas na tabela "Alvos exportados" do `README.md` da pasta | — | — |

Os `.alvo.json` saíram do `alvo:medir` e não foram editados à mão.

## Como foi medido

- O espelho foi servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`, raiz
  `MIRROR_DIR`), na porta **5577**, livre e só desta sessão. O espelho estava no estado do branch
  da thread 00, que é a base deste PR.
- Os seletores saíram do `alvo:mapa --rota <r> --raiz .rep-root | .rep-list | .rep-painel |
  .rep-board-wrap`. O mapa de cada tela está gravado no campo `_mapa` do `.secoes.json`.
- Comando: `npm run alvo:medir -- http://127.0.0.1:5577/ --tela <slug> --rota <r> --secoes …
  --quieto-ms 2000`. Rodei duas vezes por tela e o `cmp` deu idêntico nas quatro. sha256 (16
  primeiros): index `59fa2b892aeed0d2` · jobsheet `7cf7b57ca4563afa` · dashboard
  `d1b85f6f3136c4fd` · producao `6e9c3de82e25210a`.
- Viewport 1280×900, tema escuro (o padrão do `alvo.mjs`). `nos_totais`: 794 · 989 · 635 · 659.
  `ausentes: []` nas quatro.
- O relógio do header é fixo no protótipo (`09:42`). A virada de minuto, que atrapalhou o A1 de
  Vendas, não acontece aqui.
- `secao-check --todos --servir-espelho --porta 5689`: as 4 telas saíram `conforme` (7 · 6 · 6 · 5
  seções) junto com os alvos que já existiam. rc=0.

## Fora do escopo

- **As 6 medidas `targets/medidas/Repair--*` não foram refeitas.** Refazê-las é um
  `design-diff --compare` contra a tela em produção ou no staging, com sessão logada. Ficaram como
  estão, com o `design.json` antigo e compartilhado. A causa está documentada na `_saida-00`. Quem
  remedir usa a rota `rep-*` da tabela da 00 em cada lado de design.
- O alvo descreve o protótipo com o **papel administrador**. Os avisos que dependem de
  permissão (`view_assigned` nas folhas, arraste desabilitado na produção) não montam e não
  viraram seção.
- **Drawers e formulários** (`rep-folha`, `rep-reparo`, `rep-modelo-*`) existem como rota, mas
  não são deste lote.

## PARAR SE

Nenhum disparou. Não houve endpoint novo nem nada em `resources/js/Pages/`; a medida reproduz
byte a byte.
