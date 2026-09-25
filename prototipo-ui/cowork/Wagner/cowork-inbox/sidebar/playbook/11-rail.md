---
sessao: "11"
titulo: MODOS · rail — ícone do grupo + grupo ativo + dica em camada fixa
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 610fff15c6ac · Sidebar.tsx lido inteiro, 1.680 linhas, 2026-09-25 11:01 UTC)
onda: 2 — protótipo → vivo (UI-0029: protótipo soberano na forma)
---

# 11 · Rail (56px)

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 do índice + a âncora abaixo, **relida no main**.
Lei 1: TODAS as threads da onda 2 escrevem `resources/js/Components/cockpit/Sidebar.tsx` → **seriais**, uma por vaga. `gh pr list --state open` antes de abrir.

## Alvo — protótipo
`sidebar.jsx` → `SidebarMenuRail`:
1. o botão do grupo usa o **ícone do grupo** (o mesmo do cabeçalho), colorido pelo hue — não o ícone do 1º item;
2. o botão ganha `.active` quando a tela atual está no grupo;
3. a dica (`data-tip`) é uma **camada fixa** (`.sb-rail-tip`, `position:fixed`) montada pelo `Sidebar`, não pseudo-elemento — o pseudo absoluto virava overflow horizontal do `.sb-body` (motivo escrito em `Sidebar`, protótipo).

## Âncora — vivo
`Sidebar.tsx` → `function SidebarMenuRail` (`const Icon = firstItem ? findMenuIcon(firstItem.label) : Hash`) · `GROUP_ICON_MAP` (reusar) · `rotaAtiva` (reusar). Dica: localizar no `cockpit.css` a regra que desenha `[data-tip]` — **medir antes** se o overflow do protótipo se reproduz no vivo; se não reproduz, o item 3 cai (registrar no `_saida`).

## Prova
- pré-condição: `SidebarMenuRail` usa `GROUP_ICON_MAP`.
- fecha: `comparacao` (seção `sb-modos`, estado rail).

## Fechar
`_saida-11.md` nesta pasta (feito · não feito e por quê · descobertas · prefixo tocado) e PARE. Não edita o índice nem esta ficha.
