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

## Também (sobra da 08)
4. **Flyout do rail** no `cockpit.css` (`.sb-rail-flyout`, valores `0.46 0.10` / `0.78 0.08`) ainda com as cores antigas — igualar ao que a 08 pôs no cabeçalho (`0.72 0.09 h` rótulo · `0.65 0.14 h` ícone).
5. **Rótulo no tema claro:** a regra light `.cockpit .sb-group[style*="--gh"] .sb-group-h .sb-group-l` pinta `oklch(0.46 0.10 h)` — escuro sobre a sidebar que é **preta nos dois temas** (UI-0023). **Medir no tema claro primeiro** (a 07 mediu só dark); se o contraste cair abaixo de 4.5:1, remover o override light (o protótipo usa `0.72 0.09` em qualquer tema).

## Prova
- pré-condição: `SidebarMenuRail` usa `GROUP_ICON_MAP`.
- fecha: `comparacao` (seção `sb-modos`, estado rail).

## Fechar
`_saida-11.md` nesta pasta (feito · não feito e por quê · descobertas · prefixo tocado) e PARE. Não edita o índice nem esta ficha.
