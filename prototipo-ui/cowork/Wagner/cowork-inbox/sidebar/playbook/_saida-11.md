---
sessao: "11"
titulo: MODOS · rail — ícone do grupo + grupo ativo + dica em camada fixa
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main 63d943794 (o #7950, thread 10, já mergeado)
---

# _saida-11 · Rail

## Aberta apesar do placar

`placar.mjs --thread 11` deu **`pendente`**, não `proximo`. O único item nomeado é da própria thread: sem `_saida` e a prova `GROUP_ICON_MAP[g.key]`. Nenhuma dependência aparece como faltando. As duas dependências (07 e 10) têm `_saida` no main, e a 10 foi entregue pelo #7950. O placar não chega a `proximo` porque as provas de recibo dependem do avaliador que não foi portado (ADR 0397), o mesmo caso registrado no `_saida-09` e no `_saida-10`. Na 1ª abertura desta sessão a 10 ainda não estava no main, e eu parei. [W] mandou seguir ("pode continuar") depois que o #7950 entrou.

## Feito

Entregue **3 de 3** itens do "Alvo", em `SidebarMenuRail` (`Sidebar.tsx`) e `cockpit.css`:

| # | o que | antes (vivo) | depois |
|---|---|---|---|
| 1 | ícone do botão do grupo | ícone do **1º item** (`findMenuIcon(firstItem.label)`) | ícone do **grupo**, `GROUP_ICON_MAP[g.key]`, o mesmo do cabeçalho expandido. A cor pelo hue já vinha de `.sb-rail-group .ic` |
| 2 | grupo ativo | nunca ganhava `.active` | `.active` quando a rota atual é item **ou ghost** do grupo, pelo `rotaAtiva` que já existia (o mesmo predicado do `temAtivo` do modo expandido) |
| 3 | dica | `[data-tip]::after` absoluto (`cockpit.css`) | camada fixa `.sb-rail-tip` (`position:fixed`, `role="presentation"`) montada pelo `SidebarMenuRail`. Aparece por `mouseover`/`focusin` e some ao sair e com o flyout aberto |

- Fallback: grupo fora do `GROUP_ICON_MAP` cai em `Hash`, o fallback que o rail já usava. O protótipo desenha uma pílula de 2 letras, mas o CSS dela (`.sb-rail-group-pill`) não existe no vivo e nenhum grupo vivo fica sem ícone (o `mais` já é `Hash` no mapa).
- A cor e o fundo da dica reusam os tokens da dica antiga (`--sb-bg-2`, `--sb-text-hi`, `--sb-border`). O `oklch` literal do protótipo não foi copiado, e não há token novo.

## Medição que manteve o item 3

A ficha pedia medir antes se o overflow do protótipo se reproduz no vivo. Ele se reproduz. Montei um harness com o `cockpit.css` do main (`.cockpit > aside.sb.sb--rail > nav.sb-body > .sb-menu-rail` e 2 botões com `data-tip`) e medi no browser:

- `.sb-body`: `overflow-y:auto` resulta em `overflow-x:auto` computado, com **scrollWidth 224 × clientWidth 55**
- controle, com `[data-tip]::after{content:none}`: **55 × 55**

Ou seja, a dica abria barra horizontal no corpo do rail mesmo invisível (o `::after` com `opacity:0` ocupa a área de rolagem).

## Recibos

- **Pré-condição:** `Sidebar.tsx` contém `GROUP_ICON_MAP[g.key]` dentro do `SidebarMenuRail` ✓.
- **Teste novo:** `tests/js/sidebar-rail.test.tsx`, com 5 casos. Três são de contrato: (a) CADASTRO usa `lucide-book-open` e COMERCIAL usa `lucide-shopping-cart`, não o ícone de Produtos/Vendas; (b) em `/products` só CADASTRO fica `.active`; (c) a dica `.sb-rail-tip` aparece no hover e no foco com o rótulo e some no `mouseout`. Os outros dois são controles: fora de qualquer grupo nenhum botão fica `.active`, e com o flyout aberto não há dica.
- **Mordida:** com o `Sidebar.tsx` do main, os **3 de contrato falham e os 2 controles passam**. Restaurei por cópia e o sha256 bateu (`8646663efc650886`).
- **vitest:** `sidebar-rail` (5) + `sidebar-ghosts-teto` (4) + `sidebar-item-ativo` (5) + `sidebar-plataforma-forja` (6) + `sidebar-atalho-render` (4) → **24 passed**.
- **contrato-de-tela:** `--contract` ✅ limpo · `--omission origin/main` ✅ limpo.
- **tsc:** nenhum erro novo. Os 2 erros em `Sidebar.tsx` (434 e 756) já estão no main.
- **ds-guard:** acusa `paleta --sb-*(10)` no `cockpit.css`, mas o **mesmo resultado sai no `cockpit.css` do main**. Não foi introduzido aqui, e o §5 2026-09-04 registra que o §8 não governa `resources/css/`.

## Não feito, e por quê

- **Prova `comparacao` (`${REC}/11-comparacao.json`, seção `sb-modos`, estado rail):** não escrevi o recibo JSON porque o avaliador de recibo não foi portado (ADR 0397) e o placar diz que essa prova não morde. O que ela compararia está coberto pela medição de overflow e pelo teste acima.

## Descobertas

- Todos os `data-tip` do vivo moram no `SidebarMenuRail` (4 sites). O `CompanyPicker` e o rodapé do rail vivo não têm dica, diferente do protótipo. Por isso a camada escuta o `.sb-menu-rail`, e não a `<aside>`: a `<aside>` fica no `AppShellV2.tsx`, que está no `nao_toca`. Se um dia o topo ou o rodapé do rail ganharem `data-tip`, a escuta precisa subir para um ancestral comum.
- `cowork-canon-financeiro-bundle.css` e `sells-cowork.css` ainda carregam a dica em `::after`, escopada em `.fin-cowork`/`.sells-cowork`. Ficaram fora: são bundles de módulo, fora do `prefixo`, e o shell não renderiza dentro desses wrappers.

## Prefixo tocado

`resources/js/Components/cockpit/Sidebar.tsx` · `resources/css/cockpit.css` · `tests/js/sidebar-rail.test.tsx`. O `tests/js/` não está no `prefixo` da 11 (só `Sidebar.tsx` e `cockpit.css`), mas é o mesmo diretório de teste das threads 09 e 10, e é onde a prova de execução mora. Registrado aqui em vez de alterar o índice. Nada do `nao_toca` foi alterado.
