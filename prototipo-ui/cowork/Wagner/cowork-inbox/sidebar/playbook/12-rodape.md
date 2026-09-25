---
sessao: "12"
titulo: RODAPÉ · menu da conta — valor do modo + "Buscar tela ⌘K" + tirar atalho morto
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 610fff15c6ac · Sidebar.tsx lido inteiro, 1.680 linhas, 2026-09-25 11:01 UTC)
onda: 2 — protótipo → vivo (UI-0029: protótipo soberano na forma)
---

# 12 · Rodapé (menu da conta)

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 do índice + a âncora abaixo, **relida no main**.
Lei 1: TODAS as threads da onda 2 escrevem `resources/js/Components/cockpit/Sidebar.tsx` → **seriais**, uma por vaga. `gh pr list --state open` antes de abrir.

## Alvo — protótipo
`sidebar.jsx` → `UserMenu`:
1. o gatilho **Modo de trabalho** mostra o valor atual (`um-vibe-cur`), como Aparência já mostra no vivo;
2. item **"Buscar tela"** com `⌘K` que abre a paleta;
3. **sem** o `⌘/` do item Atalhos — nenhum listener liga essa tecla (o `AppShellV2` liga só ⌘K e ⌘\\).

## Âncora — vivo (linhas medidas 2026-09-25)
`Sidebar.tsx` → `SidebarUserMenu`: gatilho Modo de trabalho `:1312-1316` · Aparência com valor `:1296-1305` (padrão a copiar: `<span className="kbd">`) · Atalhos `:1322-1325` · separador antes de Documentação. ⌘K: `AppShellV2.tsx:399` já abre a `CommandPalette` — o item **dispara o mesmo caminho**, não cria segundo.

## Faz
Os 3 itens. Rótulo literal `Buscar tela`.

## Não faz
Presença clicável (4 estados do protótipo): **sem receptor** no backend — o protótipo mesmo declara estado local. Fica em RESIDUO-6.

## Prova
- pré-condição: `Sidebar.tsx` contém `Buscar tela` e não contém `<span className="kbd">⌘/</span>`.
- contrato: copy `Buscar tela` em `sb-rodape` (mesmo PR) — rodar `--contract` + `--anti-tautologia`.
- fecha: `execucao` (teste do menu em `tests/sidebarAparencia.spec.tsx` estendido, ou novo).

## Fechar
`_saida-12.md` nesta pasta (feito · não feito e por quê · descobertas · prefixo tocado) e PARE. Não edita o índice nem esta ficha.
