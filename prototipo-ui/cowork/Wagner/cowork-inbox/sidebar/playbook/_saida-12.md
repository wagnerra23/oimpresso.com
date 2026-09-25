---
sessao: "12"
titulo: RODAPÉ · menu da conta — valor do modo + "Buscar tela ⌘K" + tirar atalho morto
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main (rebaseada sobre o #7952, thread 11, antes do PR)
---

# _saida-12 · Rodapé

## Aberta apesar do placar

`placar.mjs --thread 12` deu **`pendente`**, não `proximo`. A causa era a dependência: a 11 ainda estava aberta no #7952, e as duas threads escrevem o mesmo `Sidebar.tsx` (Lei 1). A sessão parou e avisou o [W], que respondeu "pode continuar". Preparei a 12 em cima do `main` e abri o PR só depois de rebasear sobre a 11. O #7952 não toca no `SidebarUserMenu`: as mudanças dele são no rail. A prova de recibo `execucao` continua não medida, como nas threads 09 e 10, porque o avaliador de recibo não foi portado (ADR 0397).

## Feito

Entregue **3 de 3** itens do "Faz", todos em `SidebarUserMenu` (`Sidebar.tsx`):

| # | o que | antes (vivo) | depois |
|---|---|---|---|
| 1 | trigger **Modo de trabalho** | sem valor | mostra o modo atual (`workspace`/`daylight`/`focus`) no `<span className="kbd">`, o mesmo padrão do valor de Aparência |
| 2 | **Buscar tela** `⌘K` | não existia | item novo logo depois do separador. Fecha o menu e dispara o `keydown` ⌘K que o listener do `AppShellV2` já trata, então abre a **mesma** `CommandPalette`, sem um segundo caminho |
| 3 | `⌘/` do item Atalhos | kbd sem nenhum listener | removido. O item Atalhos continua, com o mesmo `title` |

- Como o `AppShellV2.tsx` está no `nao_toca`, o item não chama a paleta diretamente: ele gera o evento que o listener existente (`AppShellV2.tsx:399`) já escuta.
- O ícone do "Buscar tela" é o `Keyboard`, igual ao protótipo (`I.keyboard`). Com isso, Buscar tela e Atalhos ficam lado a lado com o mesmo ícone. Não troquei, porque a forma é do protótipo (UI-0029); fica registrado como descoberta.
- O rótulo `Buscar tela` é literal da ficha e do protótipo. Não criei CSS novo.
- Contrato: a copy `Buscar tela` entrou em `sb-rodape` do `cockpit-sidebar.contract.json`.

## Recibos

- **Pré-condição:** `Sidebar.tsx` contém `Buscar tela` e não contém `<span className="kbd">⌘/</span>`.
- **Teste:** `tests/sidebarAparencia.spec.tsx` foi estendido, e é o arquivo que a prova nomeia. Os 3 casos novos são: (a) o trigger mostra `daylight` e depois `focus` conforme a prop; (b) clicar em Buscar tela emite exatamente 1 `keydown` que satisfaz o predicado do listener (`(meta||ctrl) && key 'k'`) e fecha o menu; (c) Atalhos continua no menu, e o menu não contém `⌘/`.
- **Mordida:** com o `Sidebar.tsx` do main, **os 3 novos falham e os 4 antigos passam**. Restaurei por cópia e o sha256 bateu (`d4904fa4bdb6141a`).
- **vitest:** `sidebarAparencia.spec.tsx` → **7 passed**.
- **contrato-de-tela:** `--contract` dá ✅ limpo, com as 5 seções OK. A mordida da copy nova foi medida: trocar o rótulo no único sítio de código dá `❌ 1 falha`. Para isso, tirei a palavra do meu próprio comentário; senão ele seria um segundo sítio e o contrato viraria carimbo. `--anti-tautologia` segue em 5/20 no `cockpit-sidebar`, os mesmos 5 de antes, porque `Buscar tela` existe na fonte. `--omission origin/main` dá ✅ limpo.
- **tsc:** nenhum erro novo. Os 2 erros em `Sidebar.tsx` (434 e 756) já estão no main.

## Não feito, e por quê

- **Presença clicável** (4 estados do protótipo): não há receptor no backend, e o próprio protótipo declara o estado como local. Fica no RESIDUO-6, como a ficha já dizia.
- **Prova `execucao` (`${REC}/12-execucao.json`):** não escrevi o JSON, pelo mesmo motivo da 10: o avaliador de recibo não foi portado, e o placar diz que essa prova não morde. O teste acima é a execução real.
- **Abertura da paleta no browser:** não medi no runtime. O teste prova que o evento sai com o predicado certo; que o `AppShellV2` abre a paleta com ele é leitura do listener (`AppShellV2.tsx:399`). Um evento sintético tem `isTrusted=false`, e o listener não olha esse campo. Fica para o smoke pós-merge.

## Descobertas

- **Ícone duplicado:** o protótipo não tem o item Atalhos, então nele o `Keyboard` do Buscar tela aparece sozinho. No vivo, a ficha manda manter Atalhos, e os dois ficam com o mesmo ícone, um em cima do outro. Decidir se Atalhos sai ou troca de ícone é forma, e vai para o Cowork.
- **Rótulo do modo:** o trigger mostra o id cru (`daylight`), igual ao protótipo (`{vibe}`) e ao `VIBES.label` do vivo, que também é o id.

## Prefixo tocado

`resources/js/Components/cockpit/Sidebar.tsx` · `tests/sidebarAparencia.spec.tsx` · `governance/design/contracts/cockpit-sidebar.contract.json` · este `_saida-12.md`. Tudo está no `prefixo` da thread, e nada do `nao_toca` foi alterado.
