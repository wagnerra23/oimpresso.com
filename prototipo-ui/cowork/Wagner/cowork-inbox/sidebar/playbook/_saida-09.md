---
sessao: "09"
titulo: CORPO · item ativo — aria-current + grupo abre sozinho
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main f3611e548 (o #7946, thread 08, já mergeado)
---

# _saida-09 · Item ativo

## Aberta apesar do placar

`placar.mjs --thread 09` deu **`pendente`**, não `proximo`. O motivo não era a 08: o `_saida-08.md` já estava no main. A 07 e a 08 aparecem como `em curso` porque as provas de recibo delas (`07-revisao.json`, `08-comparacao.json`) precisam do avaliador de recibo, que não foi portado (ADR 0397). Com isso nenhuma thread com dependência chega a `proximo`. [W] autorizou seguir em 2026-09-25 ("pode resolver tudo sim").

## Feito

Entregue **2 de 2** itens do "Faz".

| # | o que | antes (vivo) | depois |
|---|---|---|---|
| 1 | `aria-current` no item | só a classe `active` | `aria-current={ativo && !ghostAtivo ? 'page' : undefined}` |
| 1 | `aria-current` no ghost | nenhum | `aria-current={rotaAtiva(g.href) ? 'page' : undefined}` |
| 2 | grupo com a tela atual | respeitava o `0` do localStorage e ficava fechado | abre sozinho: `temAtivo` no estado inicial e num `useEffect` |
| 2 | persistência | `useEffect` gravava **qualquer** mudança de `expanded` | só o **clique** grava (`alternar`); abrir por estar na rota não grava |

- `temAtivo` é calculado em `SidebarMenu` com o `rotaAtiva` que já existia, sobre os itens do grupo **e** os ghosts deles. Não criei um segundo detector de rota.
- **Desvio da ficha, deliberado:** a ficha pede `aria-current={ativo ? …}` no item. Mas `rotaAtiva` casa por prefixo, então em `/products/create` o item `/products` e o ghost `/products/create` seriam os dois `page`. Deixei um único `aria-current="page"` por página, no ghost. A prova `contem "aria-current={ativo"` continua valendo.
- Efeito colateral de remover o `useEffect` de gravação: o grupo não grava mais o default na montagem. Quando não há chave, vale o `defaultOpen`, que é o mesmo comportamento de antes.

## Recibos

- **Pré-condição:** `Sidebar.tsx` contém `aria-current={ativo` ✓.
- **Teste novo:** `tests/js/sidebar-item-ativo.test.tsx`, com 5 casos. São 3 do contrato (grupo fechado no LS abre + `aria-current` + LS intacto · só um `aria-current` · ghost ativo sem duplicar no pai) e 2 de controle (grupo sem a rota continua fechado · clique continua persistindo).
- **Mordida, parte 1:** com o `Sidebar.tsx` do main, **3 falham e 2 passam**. Os que passam são os controles, como esperado. Restaurado por cópia, com o sha256 conferido igual.
- **Mordida, parte 2:** com um mutante que devolve o `useEffect` que gravava todo `expanded`, falha o caso "nada é gravado" (1 falha, 4 passam).
- **vitest:** `sidebar-item-ativo` (5) + `sidebar-plataforma-forja` (6) + `sidebar-atalho-render` (4) + `sidebarMenuSemantics.spec` (10) → **25 passed**. `junit-summary.mjs` dá `n_testcases 25 · coherent true · passed 25`, mas `provou_algo false`: o junit do vitest não conta asserções, então esse campo não serve de prova aqui.
- **tsc:** nenhum erro novo. Os 2 que aparecem em `Sidebar.tsx` (linhas 434 e 733) já estão no main.

## Não feito, e por quê

1. **Prova `execucao` (`${REC}/09-execucao.json`) não escrita:** o avaliador de recibo não foi portado (ADR 0397), o mesmo motivo da `_saida-08`.
2. **Nenhuma lane de CI roda os testes de sidebar.** Nem `sidebar-item-ativo`, nem `sidebar-plataforma-forja`, nem `sidebarMenuSemantics`. O `fiscal-cockpit-paginacao-gate.yml` já registra que nenhuma lane roda `vitest run` sem argumento. Criar uma lane fica fora do prefixo desta thread (`${CKPT}/Sidebar.tsx`, `tests/js/`). A cobertura hoje depende de alguém rodar o teste à mão.
3. **Sem smoke no browser:** subir a tela exige o Laravel completo. O comportamento foi medido no DOM do jsdom, não em prod.

## Descobertas (não consertadas, fora do escopo)

- **Ghost ativo fora do prefixo do pai não aparece.** Os ghosts só renderizam quando o **item** está ativo (`ativo && ghostsVisiveis…`). Se a sub-tela tiver uma rota que não começa com a do pai, o grupo abre (`temAtivo` olha os ghosts), mas nem o item nem o ghost aparecem marcados. Isso é o "promover a ativa" da thread 10.
- **Rail:** o flyout do rail já tinha `aria-current` (linha ~1083); o grupo do rail não usa `temAtivo`. É prefixo da thread 11.

## Prefixo tocado

- `resources/js/Components/cockpit/Sidebar.tsx` (`SidebarMenuItem`, `SidebarGroup`, `SidebarMenu`)
- `tests/js/sidebar-item-ativo.test.tsx` (novo)
- este `_saida-09.md`

Nada em `prototipo-ui/cowork/Wagner/` (build), `app/Sidebar/`, `AppShellV2.tsx`, nem no índice ou nas fichas.
