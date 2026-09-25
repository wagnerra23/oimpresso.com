---
sessao: "10"
titulo: CORPO · sub-telas — promover a ativa + "mostrar menos"
autor: "[CL]"
data: 2026-09-25
base: wagnerra23/oimpresso.com@main f2157a6c8 (o #7947, thread 09, já mergeado)
---

# _saida-10 · Ghosts

## Aberta apesar do placar

`placar.mjs --thread 10` deu **`pendente`**, não `proximo`. A 09 já estava entregue: o #7947 foi mergeado e o `_saida-09.md` está no main. O placar não chega a `proximo` porque as provas de recibo da 07 e da 09 dependem do avaliador de recibo, que não foi portado (ADR 0397). Por isso nenhuma thread com dependência passa de `em curso`. É o mesmo caso registrado no `_saida-09`. [W] mandou seguir nesta sessão ("continue") depois de saber que a 09 era a dependência.

## Feito

Entregue **2 de 2** itens do "Faz", em `SidebarMenuItem` (`Sidebar.tsx`):

| # | o que | antes (vivo) | depois |
|---|---|---|---|
| 1 | sub-tela ativa além do teto | ficava escondida atrás do "⋯ mais N" | é **promovida** para a 5ª vaga visível, e o contador passa a contar só o que continua escondido |
| 2 | "mais N" | sem `aria-expanded` | `aria-expanded="false"` |
| 2 | excedente aberto | não havia como fechar | botão **"⌃ mostrar menos"** com `aria-expanded="true"`, que volta ao teto |

- O ghost ativo é identificado por `rotaAtiva(g.href)`, o detector que já existia. Não criei um segundo.
- O CSS `.sb-ghost-more*` foi reusado. Não há CSS novo.
- A lógica segue `GhostList` do protótipo linha a linha: `slice(0, TETO-1)` mais o promovido, e o resto como excedente.
- Contrato: a copy `mostrar menos` entrou em `sb-corpo` do `cockpit-sidebar.contract.json`.

## Recibos

- **Pré-condição:** `Sidebar.tsx` contém `mostrar menos` ✓. O placar deixou de acusar o item.
- **Teste novo:** `tests/js/sidebar-ghosts-teto.test.tsx`, com 4 casos. Dois são de contrato: (a) 8 ghosts com o 7º ativo, que fica visível sem clique na 5ª vaga com `aria-current`, "mais 3" e `aria-expanded="false"`; (b) ida e volta: "mais 3" mostra os 8, some e aparece "mostrar menos" com `aria-expanded="true"`, e o clique volta ao teto com o 7º promovido. Os outros dois são controles: ativa dentro do teto não muda a ordem, e com até 5 ghosts não aparece botão nenhum.
- **Mordida:** com o `Sidebar.tsx` do main, os **2 de contrato falham e os 2 controles passam**. Restaurei por cópia e o sha256 bateu (`0ef6086dc20acf6e`).
- **vitest:** `sidebar-ghosts-teto` (4) + `sidebar-item-ativo` (5) + `sidebar-plataforma-forja` (6) + `sidebar-atalho-render` (4) → **19 passed**.
- **contrato-de-tela:** `--contract` dá ✅ limpo, com as 5 seções OK. `--omission origin/main` dá ✅ limpo, nenhum símbolo removido.
- **tsc:** nenhum erro novo. Os 2 erros em `Sidebar.tsx` (434 e 756) já estão no main; o 756 é o antigo 733 deslocado pelas linhas novas.

## Não feito, e por quê

- **Ícone por sub-tela:** o protótipo desenha, mas `ShellMenuItem.ghosts` é `{key,label,href}`, sem `icon` (`shared.ts`). Sem esse campo não há como renderizar, então fica como RESIDUO-7, como a ficha já dizia.
- **Prova `execucao` (`${REC}/10-execucao.json`):** não escrevi o recibo JSON porque o avaliador de recibo não foi portado (ADR 0397), e o placar diz que essa prova não morde. O teste acima é a execução real que ela pediria.

## Descobertas

- O "mais N" do vivo contava `ghosts.length - visíveis`. Com a promoção, o número continua certo sem ajuste, porque o promovido sai do excedente. Isso está coberto pelo "mais 3" no caso com o 7º ativo.
- O estado "mostrar menos" é local ao item (`useState`). Trocar de rota remonta o componente e volta ao teto, igual ao protótipo.

## Prefixo tocado

`resources/js/Components/cockpit/Sidebar.tsx` · `tests/js/sidebar-ghosts-teto.test.tsx` · `governance/design/contracts/cockpit-sidebar.contract.json`. Todos estão no `prefixo` da thread, e nada do `nao_toca` foi alterado.
