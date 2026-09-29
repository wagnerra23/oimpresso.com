# Tela: Detalhe de relatório

- **rota:** `relatorio` · **tipo:** push
- **arquivo-fonte:** `app/screens-relatorios.jsx` · símbolo `RelatorioDetalheScreen`
- **referência visual:** `reference/relatorio.png` (estado default)
- **navegação:** `nav.push("relatorio")`

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-relatorios.jsx` (render de `RelatorioDetalheScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/relatorio.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
DetailHeader (OIUi)

## Classes usadas nesta tela
`oi-btn` · `oi-btn-row` · `oi-card` · `oi-chip` · `oi-chips` · `oi-head` · `oi-iconbtn` · `oi-kpi` · `oi-kpis` · `oi-list` · `oi-list-row` · `oi-money` · `oi-mono` · `oi-scroll` · `oi-section` · `oi-section-h`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
