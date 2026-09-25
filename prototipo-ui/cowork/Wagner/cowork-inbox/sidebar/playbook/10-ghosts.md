---
sessao: "10"
titulo: CORPO · sub-telas — promover a ativa + "mostrar menos"
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 610fff15c6ac · Sidebar.tsx lido inteiro, 1.680 linhas, 2026-09-25 11:01 UTC)
onda: 2 — protótipo → vivo (UI-0029: protótipo soberano na forma)
---

# 10 · Ghosts (sub-telas sob o item ativo)

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 do índice + a âncora abaixo, **relida no main**.
Lei 1: TODAS as threads da onda 2 escrevem `resources/js/Components/cockpit/Sidebar.tsx` → **seriais**, uma por vaga. `gh pr list --state open` antes de abrir.

## Alvo — protótipo
`sidebar.jsx` → `GhostList`: teto 5 + "⋯ mais N" (igual ao vivo) **e mais dois comportamentos**:
1. se a sub-tela ativa está além do teto, ela é **promovida** pra 5ª posição visível — nunca fica escondida atrás do ⋯;
2. aberto o excedente, aparece **"⌃ mostrar menos"** (`aria-expanded="true"`), e o botão "mais N" leva `aria-expanded="false"`.

## Âncora — vivo
`Sidebar.tsx` → `GHOST_TETO` + `function SidebarMenuItem` (`ghostsAbertos`, `ghostsVisiveis`, botão `.sb-ghost-more`). CSS `.sb-ghost-more*` já existe — reusar.

## Faz
Os dois itens acima, dentro de `SidebarMenuItem`. Ativo do ghost = `rotaAtiva(g.href)`.

## Não faz
Ícone por sub-tela: o protótipo desenha, mas `ShellMenuItem.ghosts` é `{key,label,href}` — **sem `icon`** (`shared.ts`). Sem receptor → fica fora (RESIDUO-7).

## Prova
- pré-condição: `Sidebar.tsx` contém `mostrar menos`.
- fecha: `execucao` — teste em `tests/js/` com 8 ghosts e o 7º ativo (visível sem clicar) + ida-e-volta do "mostrar menos".
- contrato: acrescentar a copy `mostrar menos` em `sb-corpo` de `cockpit-sidebar.contract.json` (mesmo PR).

## Fechar
`_saida-10.md` nesta pasta (feito · não feito e por quê · descobertas · prefixo tocado) e PARE. Não edita o índice nem esta ficha.
