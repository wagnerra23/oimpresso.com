---
sessao: "08"
titulo: CORPO · cabeçalho do grupo — seta à direita + cor/raio do protótipo
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 610fff15c6ac · Sidebar.tsx lido inteiro, 1.680 linhas, 2026-09-25 11:01 UTC)
onda: 2 — protótipo → vivo (UI-0029: protótipo soberano na forma)
---

# 08 · Cabeçalho do grupo

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 do índice + a âncora abaixo, **relida no main**.
Lei 1: TODAS as threads da onda 2 escrevem `resources/js/Components/cockpit/Sidebar.tsx` → **seriais**, uma por vaga. `gh pr list --state open` antes de abrir.

## Alvo (como) — protótipo
`prototipo-ui/cowork/Wagner/sidebar.jsx` → `MenuGroup`: ícone do grupo (ou ponto) → rótulo → contador → **seta por último**, girando -90° fechado.
`styles.css` → `.sb-group-h` (gap 8 · padding 6px 10px · raio 4 · cor `--sb-text`) · `.sb-group-ic` 12px.

## Âncora (onde) — vivo
`Sidebar.tsx` → `function SidebarGroup` (o `<ChevronDown className="chev">` hoje é o 1º filho do `<button className="sb-group-h">`) · `cockpit.css` → a 2ª regra `.cockpit .sb-group-h` (a que tem `cursor:pointer`).
**Mantém** o que já está certo no vivo: `GROUP_ICON_MAP` + cor `oklch(0.65 0.15 hue)` no ícone, contador `.sb-group-n`, `defaultOpen` com exceção de PLATAFORMA.

## Faz
1. Mover a seta pro fim do botão (depois do `.sb-group-n`), com `margin-left:auto` no contador ou no rótulo — sem wrapper novo.
2. Aplicar os valores que a 07 mediu para b · c · d. Cor só por token (`--sb-*`, `--gh`) — nenhum hex/oklch cru novo fora do bloco hue já existente.

## Prova
- pré-condição: em `SidebarGroup`, o `ChevronDown` aparece **depois** de `sb-group-n`.
- fecha: `comparacao` contra `cockpit-sidebar.contract.json` (seção `sb-corpo`).
- gates: `php artisan test --filter=Sidebar` e `--filter=Cockpit` com contadores **iguais ao baseline** (a 06 registrou falhas pré-existentes).

## Fechar
`_saida-08.md` nesta pasta (feito · não feito e por quê · descobertas · prefixo tocado) e PARE. Não edita o índice nem esta ficha.
