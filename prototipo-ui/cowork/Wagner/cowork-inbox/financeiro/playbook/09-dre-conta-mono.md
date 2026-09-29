# 09–10 · DRE — coluna Conta em mono (ALVO + forma)

> Emitida pelo [CC] em 2026-09-29 (plano de export, fase 3). Mesmo playbook do Financeiro — anti-scatter: o módulo já tem índice, não nasce doc novo.
> Base lida: `wagnerra23/oimpresso.com@main` c3f1602e0d97 (2026-09-28) — **reler no turno**.

## Por que existe
O quadro de frescor (`FRESCOR-PRODUCAO-vs-PROTOTIPO.md`, 23/09) marca a DRE como 🟠 com **um** item aberto: a 1ª coluna (Conta) em fonte mono. Medido em 28/09: sob `resources/js/Pages/Financeiro/Dre/` o único `font-mono` é o de `BalanceteView.tsx:147`; `Dre/Index.tsx` (22.989 B) não tem nenhum. A célula de Conta aparece em 3 tipos de linha — `:336` medido; as outras 2 **não li** (inferência), conferir no turno.
`Dre/Index.tsx:24` já importa o PageHeader canon → mexer na tela **não** obriga migrar cabeçalho.

## 09 · ALVO `financeiro--dre--index` (read-only)
- Rota do protótipo: `fin-dre` (`app.jsx`). Rodar `alvo:mapa` para achar a raiz e as seções; gravar `.secoes.json` + `.alvo.json` em `governance/design/targets/`.
- Duas medidas byte-idênticas, tema dark, após `__oiLazyDone` (mesmo rito da `_saida-32` do Ponto).
- **PARAR SE:** a seção da tabela não resolver 1 nó, ou as duas medidas divergirem.

## 10 · Forma — coluna Conta em mono (≤ 30 linhas)
- **Arquivo único:** `resources/js/Pages/Financeiro/Dre/Index.tsx` — as 3 células da coluna Conta ganham `font-mono` (token do DS: IBM Plex Mono via `--font-mono`; nunca fonte crua).
- **Não toca:** `BalanceteView.tsx` (já mono), CSS global, dados.
- **Prova:** `contem Dre/Index.tsx "font-mono"` · `secao-check` da seção da tabela conforme contra o alvo da 09 · `_saida-10.md` com o `getComputedStyle(fontFamily)` da 1ª célula medido no render (não a classe declarada).
- **PARAR SE:** a coluna Conta vier de componente compartilhado (`shared/DataTable.tsx` `meta.mono`) — aí a troca é `meta: { mono: true }` na coluna, não classe na célula; registrar qual foi.
