---
sessao: "16"
titulo: ÂNCORA · charter + casos do Sidebar (pedido.mjs para em "sem charter")
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (f3611e548698 · _saida-07/08 lidos 2026-09-25 13:51 UTC)
onda: 2 — nasce do retorno da 07/08
---

# 16 · Charter do Sidebar

## Por quê
`_saida-07` §"Não feito" 2: `pedido.mjs --secao sb-corpo` → `NÃO MEDI: sem charter pra essa tela`; `ancora.mjs cockpit/Sidebar` → sem âncora computável (já era resíduo da `_saida-06` §7). Sem charter, nenhum pedido de seção do sidebar sai pela máquina.

## Abertura
Sessão limpa · `/onda sidebar --thread 16`. Só `.md` → paralela a qualquer outra.

## Faz
`resources/js/Components/cockpit/Sidebar.charter.md` + `Sidebar.casos.md` no formato que `ancora.mjs` resolve (ler antes o par que existe: `resources/js/Layouts/AppShellV2.charter.md` + `.casos.md`). Casos = os comportamentos já no vivo (grupos, landing, ghosts com teto, 3 modos, atalho G X, rodapé com confirmação de sair) **+** os das threads 09–14 marcados como pendentes.
Se `ancora.mjs` só resolve charter em `Pages/`, **não mover o arquivo pra lá**: registrar no `_saida` e parar — a mudança é na máquina.

## Prova
`node prototipo-ui/ancora.mjs cockpit/Sidebar` resolve · `pedido.mjs --tela cockpit/_sidebar --secao sb-corpo` rc 0.

## Fechar
`_saida-16.md` e PARE.
