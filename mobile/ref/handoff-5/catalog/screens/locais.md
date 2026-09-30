# Tela: Locais

- **rota:** `locais` · **tipo:** push
- **arquivo-fonte:** `app/screens-oficina-cadastro.jsx` · símbolo `LocaisScreen`
- **referência visual:** `reference/locais.png` (estado default)
- **navegação:** `nav.push("locais")`

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-oficina-cadastro.jsx` (render de `LocaisScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/locais.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
DetailHeader (OIUi), Placa (OIManut)

## Classes usadas nesta tela
`oi-btn` · `oi-chip` · `oi-chips` · `oi-fab` · `oi-field` · `oi-iconbtn` · `oi-input` · `oi-list` · `oi-list-row` · `oi-mono` · `oi-scroll` · `oi-section` · `oi-seg` · `oi-select` · `oi-status` · `oi-step` · `oi-steps` · `oi-textarea`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
