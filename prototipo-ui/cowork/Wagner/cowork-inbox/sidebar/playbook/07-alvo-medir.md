---
sessao: "07"
titulo: ALVO — medir o sidebar do protótipo contra o vivo (read-only)
dono: "[CL]"
criado: 2026-09-25
base: wagnerra23/oimpresso.com@main (árvore 610fff15c6ac · Sidebar.tsx lido inteiro, 1.680 linhas, 2026-09-25 11:01 UTC)
onda: 2 — protótipo → vivo (UI-0029: protótipo soberano na forma)
---

# 07 · ALVO do sidebar — medir antes de pedir

**Por quê existe:** `pedido.mjs` sai **exit 2 NÃO MEDI** sem `governance/design/targets/<slug>.alvo.json`. Hoje só existe `jana--index`. As threads 08–12 dependem deste alvo.

## Abertura
Sessão limpa · `/onda sidebar --thread NN` · ler só esta ficha + o objeto dela no §7 do índice + a âncora abaixo, **relida no main**.
Lei 1: TODAS as threads da onda 2 escrevem `resources/js/Components/cockpit/Sidebar.tsx` → **seriais**, uma por vaga. `gh pr list --state open` antes de abrir.

## Escopo
- Medir o **protótipo** `prototipo-ui/cowork/Wagner/oimpresso.com.html` (sidebar, 3 modos) e o **vivo** (`AppShellV2` + `Sidebar.tsx`) com a mesma sonda do `design-diff`.
- Tema dark · após `__oiLazyDone` e **duas** leituras iguais de `querySelectorAll('*').length` · `getComputedStyle` · caso de sanidade de valor conhecido antes do veredito.
- Slug e forma do arquivo: **os que `governance/design/targets/README.md` manda** — não inventar.
- Seções = as 5 âncoras do contrato já existente `governance/design/contracts/cockpit-sidebar.contract.json` (`sb-modos · sb-topo · sb-corpo · sb-rodape · sb-alcas`).

## Não faz
Não aplica nada. Não mexe em `Components/` nem `cockpit.css`.

## O que a medição tem de responder (entrada das 08–12)
| # | propriedade | protótipo (lido no build, 2026-09-25) | vivo (lido no main, mesma data) |
|---|---|---|---|
| a | posição da seta no cabeçalho do grupo | depois do contador (à direita) | antes do ícone (à esquerda) |
| b | cor base do cabeçalho | `var(--sb-text)` | `var(--sb-text-dim)` |
| c | raio do cabeçalho | 4px | `var(--radius-sm)` |
| d | cor do rótulo/ponto do grupo | inline `oklch(.72 .09 h)` / `oklch(.65 .14 h)` | CSS `oklch(.78 .08 h)` / `oklch(.68 .13 h)` |
| e | contador de telas / dica de atalho | `--text-mute` | `--sb-text-dim` |

## Fechar
`_saida-07.md` nesta pasta (feito · não feito e por quê · descobertas · prefixo tocado) e PARE. Não edita o índice nem esta ficha.
