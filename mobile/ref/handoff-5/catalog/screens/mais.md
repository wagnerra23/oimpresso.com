# Tela: Mais (menu por perfil)

- **rota:** `mais` · **tipo:** tab
- **arquivo-fonte:** `app/screens-clientes-producao.jsx` · símbolo `MaisScreen`
- **referência visual:** `reference/mais.png` (estado default)
- **navegação:** `nav.push("mais")` (também aba da tab bar)

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-clientes-producao.jsx` (render de `MaisScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/mais.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
ScreenHeader (OIUi), DetailHeader (OIUi), HeaderTopRight (OIUi), OriginBadge (OIUi), OISheet (OIStore)

## Classes usadas nesta tela
`oi-av` · `oi-btn` · `oi-btn-row` · `oi-card` · `oi-chip` · `oi-chips` · `oi-empty` · `oi-empty-ico` · `oi-fab` · `oi-head` · `oi-iconbtn` · `oi-input` · `oi-kpi` · `oi-kpis` · `oi-list` · `oi-list-row` · `oi-money` · `oi-mono` · `oi-scanline` · `oi-scroll` · `oi-search` · `oi-section` · `oi-section-h` · `oi-sheet` · `oi-sheet-backdrop` · `oi-sheet-grip` · `oi-sheet-h` · `oi-status`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
