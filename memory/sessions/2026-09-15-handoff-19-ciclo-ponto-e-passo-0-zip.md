---
date: "2026-09-15"
hour: "12:20 BRT"
duration: "~7h (atravessa 14→15/09)"
topic: "Importar o handoff 19 do Cowork, aplicar o ciclo Ponto no produto e ligar o PASSO 0 na rota ZIP"
authors: [W, C]
outcomes:
  - "Handoff 19 importado pela rota ZIP canônica — 7 build-only promovidos atomicamente, 17 docs de cowork-inbox recusados de propósito"
  - "PASSO 0 (--de-quem) ligado na rota ZIP, que não o chamava — buraco de arquivo órfão fechado com --conta declarado"
  - "2 telas do Ponto aplicadas: redação de CPF/PIS na lista de colaboradores e remoção de escala travada por vínculo (com defeito de integridade achado no destroy)"
  - "3 restrições [W] do Fechamento registradas como US-PONTO-015, blocked_by decisão dele"
  - "Um diagnóstico meu REFUTADO por medição própria: o distiller_freshness era meu, não herdado"
prs: [7272, 7275, 7277, 7278, 7279]
us: ["US-PONTO-015"]
related_adrs: ["0390-espelho-cowork-build-only", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0325-projetos-cowork-por-id"]
---

# Sessão 2026-09-15 — handoff 19: ciclo Ponto no produto + PASSO 0 na rota ZIP

## TL;DR

[W] entregou o zip `handoff (19)` e disse "pode importar e aplicar". Importei pela rota canônica, apliquei as decisões do ciclo que tinham alvo no produto, e consertei um passo obrigatório do protocolo que a rota ZIP **não chamava**. 5 PRs, todos mergeados. Dois erros meus no caminho, um deles um diagnóstico que eu havia publicado duas vezes como "herdado" e que a medição derrubou.

## Contexto

O worktree estava **47 commits atrás** de `origin/main`, e isso importou: nos 47 o canon do protocolo tinha **mudado de lugar** (`prototipo-ui/PROTOCOL.md` → `memory/reference/prototipo-ui/PROTOCOL.md`; `protocolo.config.mjs` → `scripts/design/`). Trabalhei de `origin/main` fresco em branch nova; a branch antiga (`claude/oimpresso-erp-visual-69f8b4`, 6 commits sem PR, tema já landado via #7224) ficou intacta em `fb32e23b3b`.

## Cronologia

1. **Painel + selftest** antes de tocar em nada (`receber-handoff.test.mjs` 22/22).
2. **PASSO 0** deu `indeterminado` (exit 4) — e a medição mostrou que é **estrutural**: o zip do Cowork nomeia a raiz pelo *slug*, e o id do projeto de telas aparece **0 vezes em 816 arquivos**. Segui com a resolução que o próprio passo prescreve (perguntar a quem exportou), corroborada por 580/653 paths batendo com o espelho registrado.
3. **Import** (#7272): 7 build-only promovidos; o `sync/` que veio no pacote estava fora do contrato (19 sha256 divergentes) e a máquina o regerou.
4. **PASSO 0 na rota ZIP** (#7275): o passo obrigatório não era chamado — `grep` de `de-quem` no `receber-handoff` dava zero.
5. **Colaboradores/Index** (#7277) e **Escalas/Index** (#7278): as duas decisões com alvo no produto.
6. **US-PONTO-015** (#7279): as 3 restrições do Fechamento, registradas sem inventar especificação.

## Entregas

| PR | O que |
|---|---|
| [#7272](https://github.com/wagnerra23/oimpresso.com/pull/7272) | import do handoff 19 · bundle `b19625fb` · 7 arquivos, promoção atômica |
| [#7275](https://github.com/wagnerra23/oimpresso.com/pull/7275) | PASSO 0 embutido na rota ZIP + `--conta` declarado · 37 asserts, bite-test no CLI |
| [#7277](https://github.com/wagnerra23/oimpresso.com/pull/7277) | `Colaboradores/Index` redige CPF/PIS (`D-COLAB-CPF`) + `redigirDigitos` no `format-br` |
| [#7278](https://github.com/wagnerra23/oimpresso.com/pull/7278) | `Escalas/Index` ganha "Remover" + **trava de vínculo no servidor** |
| [#7279](https://github.com/wagnerra23/oimpresso.com/pull/7279) | US-PONTO-015 (restrições D1/D2/D4 do Fechamento) + BRIEFING redestilado |

**Defeito real achado ao aplicar o design:** `EscalaController@destroy` apagava **sem condição nenhuma**, e `ponto_colaborador_config.escala_atual_id` **não tem FK** — apagar escala em uso deixava a coluna apontando pra linha morta, e escala é o que define a jornada esperada na apuração (CLT Art. 58/59). Não era UX: era integridade com efeito em folha.

## Decisões cinzentas resolvidas

- **PII no Ponto** — [W] levantou "o PII não pode ser aplicado no módulo Ponto" no meio da sessão. Medi antes de agir: 8 dos 9 CPFs do protótipo têm dígito verificador **inválido** (mock), o Ponto **não pode** perder PIS (o AFD da Portaria 671/2021 é chaveado por ele) e `UC-COLIDX-01` é `must [T0]` de busca por CPF. Perguntei o alcance; [W]: *"é liberado ser igual ao protótipo"*. Resultado: exibição redigida, **busca inteira** — e isso ficou escrito no charter, porque confundir os dois quebra o UC.
- **Precedência aplicada duas vezes** — a resposta do [W] superseded a posição de **2026-08-21** (*"pode deixar os dados sim é um ERP"*) para a coluna de CPF, e respondeu o Non-Goal do charter de Escalas que **perguntava** se a UI devia expor a remoção. Nos dois casos corrigi o perdedor no mesmo PR, preservando o fato datado.
- **Fechamento não foi construído** — as 3 restrições dizem o que ele **não** faz; nenhuma diz o que ele faz com o dado. Registrei como US `blocked_by` em vez de inventar domínio de folha.

## Aprendizados / pegadinhas

- **Meu diagnóstico caiu por medição minha** (§5 2026-09-15, emenda da 2026-08-20): reportei o `distiller_freshness` como herdado testando por reversão **no working tree** um valor que a sonda lê do **git** — com a mudança já commitada. Teste vacuoso por construção. Era meu: meu `SPEC.md` deixou o BRIEFING do Ponto 10d atrás dos eventos do módulo. Conserto foi **redestilar de verdade**, não re-carimbar.
- **O sinal que quase passei batido:** meu proxy dava 11 portas stale e o ratchet dizia 2. Divergência de medidor é convite pra ler a função, não pra ajustar o proxy.
- **Rodei ferramenta de governança pra espiar um número e ela escreveu** `governance/sdd-scorecard.json` — a lápide LC-32, de 10 dias antes, na veia. Restaurei antes de qualquer `git add`.
- **Validei um gate rodando o modo errado:** `sdd-scorecard --check` sai 0; o CI roda `--ratchet`. Modo diferente é gate diferente (§5 2026-07-28).
- **Prova citada errada:** listei `reuse --check 0` como recibo no commit do #7277 — `--check` não é flag daquele script, ele imprimiu o *uso* e saiu 0. Errata no corpo do PR, com o comando certo (`--gate`).
- **Três consertos de gate que eram meus:** cor crua `text-amber-600` → token `text-warning`; `<div flex>` → `<Inline>`; e o `foundation-ratchet` subindo porque meu **docblock** citava `Business::first()` em prosa (o contador não distingue menção de uso).

## Próximos passos (não-bloqueante)

1. **Paridade visual das ~20 Pages do Ponto** — apliquei as 7 decisões do ciclo; a fidelidade visual contra o protótipo nunca foi medida (exige app no ar + `design-diff --probe` nos dois lados).
2. **Tela de Fechamento** — MWART do zero, travada pela decisão [W] da US-PONTO-015.
3. **Shell do espelho referencia `_ds/`** — o hook de SessionStart acusa 3 refs a um cache aposentado; conserto nasce no Cowork vivo.
4. **Watchdog G6** — outra sessão (`claude/re-cure-baseline-ct-100-48d292`) assumiu. A medição e os limites estão na lápide §5 de hoje; **atenção ao CT 100**, que mede ~+1 sistemático.

## Referências

- §5 `memory/proibicoes.md` — duas lápides de 2026-09-15 (fonte em `memory/licoes-rejeitadas.md`)
- `memory/LICOES_CODE.md` — LC-08 ocorrência 155
- Handoff: `memory/handoffs/2026-09-15-1220-handoff-19-ciclo-ponto.md`
