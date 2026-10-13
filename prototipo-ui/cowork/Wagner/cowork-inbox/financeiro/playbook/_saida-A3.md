---
sessao: "A3"
titulo: "ALVO de Despesas — saída da thread"
autor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 8fe939db25
thread: A3-alvos-despesas.md
veredito: "entregue — 2 alvos medidos (8 seções cada, 0 ausentes), byte-idênticos na re-medição; secao-check conforme nos dois."
---
# _saida-A3 · Alvos de Despesas e Categorias de despesa

## Premissa conferida antes de medir
- Nenhum `financeiro--despesas*` nem `financeiro--categorias-despesa*` em `governance/design/targets/` no `main`. Os alvos do Financeiro lá são `financeiro--unificado` e `financeiro--dre--index`.
- As rotas existem no protótipo: `fin-despesas` e `fin-categorias` (`data.jsx:100-101`), renderizadas por `window.FinanceiroLegadoPage view={route}` (`app.jsx:845`, `financeiro-legado.jsx`).

## O que saiu
| tela | slug | rota | seções |
|---|---|---|---|
| Despesas | `financeiro--despesas--index` | `fin-despesas` → `telaDespesas()` | 8 · cabecalho · abas · kpis · titulo · toolbar · tabela · linha · rodape |
| Categorias de despesa | `financeiro--categorias-despesa--index` | `fin-categorias` → `telaCategorias()` | 8 · cabecalho · abas · kpis · titulo · toolbar · tabela · linha · rodape |

Arquivos: `governance/design/targets/<slug>.{secoes,alvo}.json`. O `.alvo.json` é saída do `alvo.mjs`, não editado à mão. Os seletores vieram do `alvo:mapa` (raízes `.pb-root`, `.pb-body`, `.pb-widget`, `.pb-widget-b`, `.pb-grid-pro tbody`) e estão em `_mapa` no `.secoes.json`. Fica de fora o 3º filho de `.pb-root`: div vazio do aviso, sem filhos no render.

## Como foi medido
- Espelho `prototipo-ui/cowork/Wagner/` servido por `servirEstatico` (`scripts/design/render-proto-baseline.mjs`) em `http://127.0.0.1:5561/`.
- **Sanidade antes:** `alvo.mjs --selftest --browser` → `27/27 ok`.
- Por tela: `node scripts/design-sync/alvo.mjs --alvo http://127.0.0.1:5561/ --tela <slug> --rota <rota> --secoes governance/design/targets/<slug>.secoes.json --quieto-ms 2000` → `seções medidas: 8 · ausentes no DOM: 0`, rc=0 nas duas.
- Viewport 1280×900, tema escuro (`base.theme = dark` nas duas).
- **Não é esqueleto:** a `base.assinatura` traz o conteúdo da tela ("Despesas em ago/26", "Categorias de despesas"), e a seção `linha` tem 15 filhos em Despesas e 6 em Categorias, o número de colunas de cada grade.
- **Determinismo:** re-medida com `--saida` num arquivo temporário; `cmp` byte-idêntico, rc=0, nas duas. Ressalva medida: a 1ª re-medida de Despesas diferiu só no relógio do cabeçalho ("Atualizado 16:06" × "16:07", dentro de `base.assinatura`), porque virou o minuto entre as duas medidas. Re-gravada e re-medida no mesmo minuto: idêntica. Os outros alvos do repo também carregam esse relógio (`grep -o "Atualizado [0-9:]*" governance/design/targets/*.alvo.json`), e o `secao-check` o trata como `~ (página) → base.assinatura`, sem bloquear.
- **A-LOTE:** hashes diferentes — sha256 (16 primeiros) `e58a47d4c471424c` (despesas) · `67a56ca5c1fafb34` (categorias). `cabecalho` e `abas` têm o mesmo estilo nas duas porque são a mesma casca da `FinanceiroLegadoPage`; `kpis` (3 × 2), `tabela` e `linha` (15 × 6 colunas) diferem.
- `node scripts/qa/secao-check.mjs --tela <slug> --servir-espelho`: **conforme**, 8 seções, rc=0, nas duas.

## Fora deste PR
1. A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi atualizada: está fora do prefixo.
2. Os drawers (`despesa`, `cat`) ficam fora: só existem depois de clicar numa linha. Se a thread 13 quiser, é outro alvo com `--clicar`.
3. O alvo mede o protótipo. O que a Page Inertia da thread 13 faz com cada seção é dela.

## Placar
entregue 2 de 2 alvos · 16 de 16 seções medidas · ausentes 0.
