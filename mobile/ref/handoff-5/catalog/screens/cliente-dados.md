# Tela: Dados do cliente

- **rota:** `cliente-dados` · **tipo:** push
- **arquivo-fonte:** `app/screens-novo-cliente.jsx` · símbolo `ClienteDadosScreen`
- **referência visual:** `reference/cliente-dados.png` (estado default)
- **navegação:** `nav.push("cliente-dados")`

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-novo-cliente.jsx` (render de `ClienteDadosScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/cliente-dados.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
DetailHeader (OIUi)

## Classes usadas nesta tela
`oi-btn` · `oi-card` · `oi-checkrow` · `oi-dl` · `oi-field` · `oi-hint` · `oi-iconbtn` · `oi-input` · `oi-money` · `oi-mono` · `oi-scroll` · `oi-section` · `oi-section-h` · `oi-seg` · `oi-select` · `oi-status` · `oi-step` · `oi-steps`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
