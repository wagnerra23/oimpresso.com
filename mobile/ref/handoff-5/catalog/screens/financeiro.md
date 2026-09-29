# Tela: Financeiro

- **rota (chave nav):** `financas` · **tipo:** tab
- **arquivo-fonte:** `app/screens-financeiro.jsx` · símbolo `FinancasScreen`
- **referência visual:** `reference/financeiro.png` (estado default)
- **navegação:** `nav.push("financas")` (também aba da tab bar)

> Chave de navegação real: `financas` (ver `catalog/routes.md`). O slug do arquivo (`financeiro`) é só o nome do doc.

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-financeiro.jsx` (render de `FinancasScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/financeiro.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
DetailHeader (OIUi), OISheet (OIStore)

## Classes usadas nesta tela
`oi-btn` · `oi-card` · `oi-chip` · `oi-chips` · `oi-dl` · `oi-empty` · `oi-empty-ico` · `oi-fab` · `oi-iconbtn` · `oi-input` · `oi-list` · `oi-list-row` · `oi-money` · `oi-mono` · `oi-scroll` · `oi-search` · `oi-section` · `oi-section-h` · `oi-status`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
