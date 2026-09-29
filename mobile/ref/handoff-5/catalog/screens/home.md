# Tela: Início (dashboard + tarefas)

- **rota (chave nav):** `inicio` · **tipo:** tab
- **arquivo-fonte:** `app/screens-home-tasks.jsx` · símbolo `HomeScreen`
- **referência visual:** `reference/home.png` (estado default)
- **navegação:** `nav.push("inicio")` (também aba da tab bar)

> Chave de navegação real: `inicio` (ver `catalog/routes.md`). O slug do arquivo (`home`) é só o nome do doc.

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-home-tasks.jsx` (render de `HomeScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/home.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
ScreenHeader (OIUi), DetailHeader (OIUi), HeaderTopRight (OIUi), OriginBadge (OIUi), OISheet (OIStore), TaskViewer (window.TaskViewer), CubeTile (Widgets), SparkCard (Widgets), MiniTrend (Widgets), AssistCard (Widgets)

## Classes usadas nesta tela
`oi-av` · `oi-btn` · `oi-card` · `oi-chip` · `oi-chips` · `oi-cube-wm` · `oi-cubes` · `oi-empty` · `oi-empty-ico` · `oi-fab` · `oi-field-l` · `oi-iconbtn` · `oi-input` · `oi-list` · `oi-list-row` · `oi-minis` · `oi-money` · `oi-mono` · `oi-progress` · `oi-scroll` · `oi-search` · `oi-section` · `oi-section-h` · `oi-status` · `oi-tenant-pill` · `oi-ui`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
