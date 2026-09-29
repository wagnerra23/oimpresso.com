# Tela: Empresa

- **rota:** `empresa` · **tipo:** push
- **arquivo-fonte:** `app/screens-modules.jsx` · símbolo `EmpresaScreen`
- **referência visual:** `reference/empresa.png` (estado default)
- **navegação:** `nav.push("empresa")`

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-modules.jsx` (render de `EmpresaScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/empresa.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
ScreenHeader (OIUi), DetailHeader (OIUi), HeaderTopRight (OIUi), OriginBadge (OIUi), StageStatus (OIUi), OISheet (OIStore), ProductThumb (Widgets), OrderStepper (Widgets), OrderCard (Widgets)

## Classes usadas nesta tela
`oi-av` · `oi-av-2` · `oi-btn` · `oi-card` · `oi-chip` · `oi-chips` · `oi-dl` · `oi-empty` · `oi-empty-ico` · `oi-fab` · `oi-iconbtn` · `oi-list` · `oi-list-row` · `oi-money` · `oi-mono` · `oi-progress` · `oi-scroll` · `oi-search` · `oi-section` · `oi-section-h` · `oi-status`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
