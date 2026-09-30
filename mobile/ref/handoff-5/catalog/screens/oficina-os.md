# Tela: Detalhe da OS

- **rota (chave nav):** `manut-os` · **tipo:** push
- **arquivo-fonte:** `app/screens-oficina-os.jsx` · símbolo `ManutOsDetalheScreen`
- **referência visual:** `reference/oficina-os.png` (estado default)
- **navegação:** `nav.push("manut-os")`

> Chave de navegação real: `manut-os` (ver `catalog/routes.md`). O slug do arquivo (`oficina-os`) é só o nome do doc.

## Como montar
1. Ler a árvore de layout **na fonte** `app/screens-oficina-os.jsx` (render de `ManutOsDetalheScreen`) — é o contrato estrutural exato.
2. Reproduzir os componentes compartilhados abaixo (assinaturas em `catalog/components.md`).
3. Aplicar as classes abaixo (definições em `catalog/classes.md`) — não inventar valores.
4. Trocar leitura de `window.MOCK`/`OIStore` por dados reais na forma do contrato.
5. Conferir contra `reference/oficina-os.png` (estrutura + estilo + estados).

## Componentes compartilhados usados
DetailHeader (OIUi), OriginBadge (OIUi), Placa (OIManut), mDue (OIManut), statusTone (OIManut), MANUT_TONE (OIManut), ITEM_STATUS (OIManut), OISheet (OIStore)

## Classes usadas nesta tela
`oi-av` · `oi-btn` · `oi-card` · `oi-dl` · `oi-hint` · `oi-iconbtn` · `oi-list` · `oi-list-row` · `oi-money` · `oi-mono` · `oi-progress` · `oi-scroll` · `oi-search` · `oi-section` · `oi-section-h` · `oi-seg` · `oi-sheet` · `oi-sheet-backdrop` · `oi-sheet-grip` · `oi-sheet-h` · `oi-status`

## Estados a reproduzir
- **default** (ver referência)
- **vazio** → `.oi-empty` quando a lista/coleção estiver vazia
- **carregando** → skeleton dos cards/linhas
- **erro** → faixa `.oi-status.danger` + ação de repetir

## Dados / contrato
- Fonte no protótipo: `window.MOCK` / `OIStore` (treatment **replace**).
- Forma canônica: `backend/packages/shared/src/contracts.ts`.
- Regra de negócio (valores/estados): **importar** de `backend/.../domain` — nunca calcular na tela.
