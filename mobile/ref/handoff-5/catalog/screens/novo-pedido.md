# Tela: Novo pedido (wizard)

- **rota:** `novo-pedido` · **tipo:** push
- **arquivo-fonte:** `app/screens-novo-pedido.jsx` · símbolo `NovoPedidoScreen`
- **referência visual:** `reference/novo-pedido.png` (estado default)
- **navegação:** `nav.push("novo-pedido")`

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-novo-pedido.jsx` (render de `NovoPedidoScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/novo-pedido.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
DetailHeader (OIUi), OISheet (OIStore)

## Classes usadas nesta tela
`oi-av` · `oi-btn` · `oi-card` · `oi-chip` · `oi-chips` · `oi-dl` · `oi-empty` · `oi-empty-ico` · `oi-iconbtn` · `oi-input` · `oi-list` · `oi-list-row` · `oi-money` · `oi-mono` · `oi-scroll` · `oi-search` · `oi-section` · `oi-section-h`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
