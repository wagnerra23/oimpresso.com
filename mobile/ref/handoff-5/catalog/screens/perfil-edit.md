# Tela: Editar perfil

- **rota:** `perfil-edit` · **tipo:** push
- **arquivo-fonte:** `app/screens-perfis.jsx` · símbolo `PerfilEditScreen`
- **referência visual:** `reference/perfil-edit.png` (estado default)
- **navegação:** `nav.push("perfil-edit")`

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-perfis.jsx` (render de `PerfilEditScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/perfil-edit.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
DetailHeader (OIUi)

## Classes usadas nesta tela
`oi-btn` · `oi-card` · `oi-field` · `oi-iconbtn` · `oi-input` · `oi-list` · `oi-list-row` · `oi-scroll` · `oi-section` · `oi-section-h` · `oi-status`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
