---
sessao: "09"
titulo: CORPO · item ativo — aria-current + grupo abre sozinho
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 610fff15c6ac · Sidebar.tsx lido inteiro, 1.680 linhas, 2026-09-25 11:01 UTC)
onda: 2 — protótipo → vivo (UI-0029: protótipo soberano na forma)
---

# 09 · Item ativo

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 do índice + a âncora abaixo, **relida no main**.
Lei 1: TODAS as threads da onda 2 escrevem `resources/js/Components/cockpit/Sidebar.tsx` → **seriais**, uma por vaga. `gh pr list --state open` antes de abrir.

## Alvo — protótipo
`sidebar.jsx` → `ItemRow` põe `aria-current="page"` no item ativo · `MenuGroup` tem `useEffect(() => { if (hasActive && !open) setOpen(true) }, [hasActive])` — o grupo que contém a tela atual abre mesmo se o usuário o fechou antes.

## Âncora — vivo
`Sidebar.tsx` → `function SidebarMenuItem` (link `<a className="sb-item sb-sub…">` só recebe a classe `active`; `aria-current` hoje existe só na landing, dentro de `SidebarShortcuts`) · `function SidebarGroup` (estado `expanded` lido do localStorage `oimpresso.cockpit.group.v2.<key>.expanded`).
`rotaAtiva(href)` já existe — reusar; não criar segundo detector de rota.

## Faz
1. `aria-current={ativo ? 'page' : undefined}` no `<a>` do item e no ghost ativo.
2. `SidebarGroup` recebe `temAtivo` (calculado em `SidebarMenu` com `rotaAtiva` sobre os itens do grupo **e** seus ghosts) e abre quando `true`. **Não** grava no localStorage por causa disso (só clique do usuário persiste — mesma regra do auto-rail, UI-0030).

## Também (sobra da 08)
3. Contador de telas `.sb-ghost-count` e dica `.sb-kbd`: cor `--text-mute` como no protótipo (vivo usa `--sb-text-dim`) — linha **e** da `_saida-07`, que a 08 não aplicou por não estar no "Faz" dela.

## Prova
- pré-condição: `Sidebar.tsx` contém `aria-current={ativo`.
- fecha: `execucao` — teste novo em `tests/js/` (render com URL de um item de grupo fechado no LS → grupo aberto + `aria-current`), recibo `junit-summary.mjs`.

## Fechar
`_saida-09.md` nesta pasta (feito · não feito e por quê · descobertas · prefixo tocado) e PARE. Não edita o índice nem esta ficha.
