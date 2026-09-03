---
date: "2026-09-02"
topic: "Forja Onda 3 — /forja/aprovacoes vira a view `hoje` do protótipo (PARIDADE §11)"
module: Forja
related_adrs:
  - 0368-funil-admissao-feature-pesquisa-propoe-w-admite
  - 0385-sidebar-alinhado-ao-prototipo-diferenca-em-tres-categorias
  - 0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias
  - 0283-handoff-loop-zero-paste
---

# Onda 3 da PARIDADE §11 — Aprovações vira a view `hoje`

**Pedido:** deixar `/forja/aprovacoes` igual à view `hoje` do protótipo, sob a
[ADR 0388](../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)
("réplica primeiro"). **Entrega:** [PR #6571](https://github.com/wagnerra23/oimpresso.com/pull/6571).

## Duas correções ao enunciado, feitas por medição

O pedido nomeava a Page como `team-mcp/Forja/Cockpit.tsx`, aba `aprovacoes`, e a fonte como
`forja-page.jsx`. **Os dois estavam errados**, e cada um teria feito a onda inteira no lugar errado:

1. **A Page.** O `Cockpit.tsx` não tem aba `aprovacoes` — a rota `/forja/aprovacoes` é servida por
   uma Page própria, `Forja/Aprovacoes/Index.tsx` (nasceu na Onda 1, [#5931](https://github.com/wagnerra23/oimpresso.com/pull/5931)).
   O `ForjaHub` confirma: `href: '/forja/aprovacoes'`.
2. **A fonte.** O `forja-page.jsx` só **monta** a view (linha 1229); o markup mora em
   **`forja-aprova.jsx`**, um arquivo que o charter não citava. Quem copiasse do arquivo declarado
   não acharia a tela. O charter foi corrigido (`charter_version: 2`).

Nenhuma das duas apareceu por leitura atenta do pedido: apareceram por **abrir os dois lados**
antes de escrever — `find` na árvore de Pages e `grep` da copy do herói no espelho inteiro.

## A fonte foi provada, não suposta

O ledger `.cowork-freshness-ledger.json` registra a rodada de **2026-09-02T11:17:56Z** com
`forja-aprova.jsx` entre os **14 arquivos SYNC**. Os 3 arquivos que usei batem byte a byte com os
hashes do recibo (`cc4cde36…`, `f043f5bd…`, `9c180a5d…`). Isso importa porque a mesma rodada é
**parcial** (14 de 258 medidos) — "0 stale" ali só cobre o que foi medido, e sem conferir o hash
do arquivo específico eu estaria confiando num verde que não fala do meu arquivo (§5 2026-08-11).

## O que foi construído

Markup 1:1 do protótipo com as classes do bundle da Onda 1 — **zero linha de CSS nova**. As 4
seções: herói (`.fj-hj-n`), faixa "Ao vivo no MCP" (`.ap-vivo`), mesa (`.ap-mesa` = fila +
painel) e placar (`.fj-hj-team`), mais o toast de desfazer.

**Backend novo** para os dois itens que o [W] pediu em **2026-08-08** e que o `casos.md` guardava
como `[BACKLOG] … ainda sem backend`:

| método | fonte |
|---|---|
| `aoVivo()` | `mcp_actors` × `mcp_cc_sessions` × `mcp_audit_log` |
| `placar()` | `cowork_handoffs` por `created_by`, 7d — critique é o `gate_status.critique_score` que o `handoff-ack` já exige ≥ 80 |
| `handoffsComProblema()` | delega ao `ForjaMcpService`, dono do tema |

## Onde eu recusei inventar

Duas colunas do placar (**sessões hoje**, **custo/quota**) são por **usuário**, e o schema não tem
vínculo papel→usuário: os atores semeados são `wagner`/`felipe`/`maira`/`luiz`/`eliana`/
`claude-code-wagner-laptop`, nunca `CC`/`CD`/`CL`. Mostram **"—"** com o motivo no `title`. O eixo
`nivel` do protótipo (sênior/júnior/artista) também não existe: `mcp_actors` declara `type` e
`trust_level`, que é outra coisa — o selo mostra o que É declarado.

Preencher qualquer um dos dois exigiria inventar semântica que ninguém decidiu. **Criar o vínculo
é decisão [W]** (campo novo = ADR mãe).

## Três coisas que a medição pegou e a leitura não

1. **A11y regredida pela réplica.** A 1ª versão copiou o `<li onClick>` cru do protótipo; o
   `eslint-baseline` acusou 2 regressões `jsx-a11y` novas. A versão anterior da tela já era
   navegável por teclado — réplica não regride isso. Corrigido com `role=listbox/option` +
   `onKeyDown`, mantendo a classe no `<li>` (o `:last-child` do `.ap-item` depende disso).
   Re-medido: as duas foram a zero. **A 0388 tira o veto do DS, nunca o da acessibilidade.**

2. **O typecheck que não typechecava.** `tsconfig.json` tem `include` só de `resources/js/**` —
   `Modules/**` fica **fora do programa**. Rodar `tsc -p tsconfig.json` e ler "0 erro no meu
   arquivo" seria `0 failed` de suíte que não rodou (§5 2026-07-24). Com um config temporário que
   **inclui** o arquivo, o compilador achou um `TS2532` real (índice do atalho `j`/`k`).
   Corrigido; depois: 0 no arquivo, 278 no repo (os mesmos de antes).

3. **Duas diferenças no placar que a cópia à mão deixou passar.** Servi o protótipo local e li a
   estrutura **depois** do `__oiLazyDone`, com duas contagens iguais de nós (1007 = 1007). O
   `thead` tem **8** colunas, não 7 — a 8ª guarda o botão "verificar" do papel sem sinal; e o
   rótulo é **"Agente"**, que eu tinha trocado por "Papel" sem lei que mandasse. As duas
   corrigidas antes do commit.

## Baseline visual: por que rodei duas vezes

A 1ª run gerou a baseline de `da94ac39bb` — **antes** do conserto do placar. Confiar nela seria
travar o gate contra um código que já não era o meu. Re-despachei do HEAD e o step respondeu, com
todas as letras: **"Baselines já em dia — nada a commitar."** Essa frase é o que prova que a 8ª
coluna não muda o pixel neste ambiente (sem `cowork_handoffs` semeado, o placar não renderiza) —
não é inferência minha a partir de "não criou branch".

`snap-diff` decodificou o antes×depois (diff de `.snap` é base64 numa linha, ilegível por
construção): **24,88% dos px, Δmax 253 → CONTEÚDO**, linhas 2-8. A **linha 1 não mudou** — o
header do `ForjaHub` (Onda 2) ficou intacto. Fosse a linha 1, eu teria regredido a Onda 2 sem ver.

## O que NÃO está provado

A comparação prod×protótipo por sonda (`design-diff --probe` nos dois lados) **não rodou** — a
produção ainda não tem este código, e o merge de `.tsx` é humano ([ADR 0283](../decisions/0283-handoff-loop-zero-paste.md)).
A linha 3 do §11 está **em andamento**, não ✅. Afirmar paridade antes de medir é o strike que a
[LC-06](../LICOES_CODE.md) catalogou, e a conferência de estrutura que fiz **no protótipo** não
substitui o compare: ela prova que a cópia saiu fiel, não que os dois lados batem.

## Colisão de branch evitada no início

`git checkout -B claude/forja-onda3-aprovacoes` falhou: outro worktree já segurava esse nome.
Investiguei em vez de renomear no reflexo — a branch era do recibo da Onda 2.1 (#6565), **já
mergeada**, e o worktree estava limpo. Ninguém estava a meio da Onda 3. Segui em
`claude/forja-onda3-hoje`. (É o hábito que a §5 2026-08-13 pede: sintoma compartilhado é o caso de
maior chance de colisão.)

## Estado no fechamento

- **PR [#6571](https://github.com/wagnerra23/oimpresso.com/pull/6571)** aberto, CI rodando, merge é [W].
- Gates locais verdes: foundation · conformance · css-size · stylelint · layout · casos · deadlink
  · ds-guard `--report` · tsc · eslint-baseline.
- `BASELINE-ABSORB:` 5 `ds/no-os-btn` — a classe de botão do protótipo, item que a 0388 D-2 manda
  reportar em vez de vetar. Diff da baseline auditado nas duas vezes: só a minha entrada mudou.
- Lista de inconsistências: **101 → 100** (o `FLEX-CRU` desta tela saiu; `R1`/`R3` subiram com a
  cópia; `R4` registra a saída do `PageHeader`).
- Recibo por seção em [forja-cockpit-visual-comparison.md §2026-09-02 noite](../requisitos/TeamMcp/forja-cockpit-visual-comparison.md).
