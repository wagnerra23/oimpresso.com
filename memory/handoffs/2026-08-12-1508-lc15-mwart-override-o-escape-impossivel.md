---
date: "2026-08-12"
time: "15:08 UTC"
slug: lc15-mwart-override-o-escape-impossivel
tldr: "O block-mwart-violation oferecia um /mwart-override que nunca existiu — e o assert do selftest EXIGIA a oferta. Medindo o wiring, a saída anunciada é impossível: o hook é PreToolUse e dispara antes de existir PR pra comentar. PR #5683 mergeado: mensagem nomeia a fronteira, assert vira controle negativo, e o site que enganava ([W] decidiu em cima dele) era a canon always-on."
prs: [5683]
decided_by: [W]
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0344-two-strikes-cobre-processo
next_steps:
  - "Fechar mwart-comparative/SKILL.md:249 — promete o override sem a ressalva 'não mexe em CI' que a skill irmã tem"
  - "Mergear #5679 (.claude/rules/pages.md), que fecha o 5º site"
  - "Decidir se D:/oimpresso.com sai do detached HEAD — está 73 commits atrás e roda o hook velho"
---

# LC-15 — o `/mwart-override` era um escape impossível, não só não-implementado

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **10 tasks em REVIEW**, nenhuma tocada nesta sessão (trabalho foi de governança, fora do backlog)
- Handoffs irmãos de hoje: **7** já em `origin/main` (índice altamente disputado — toquei por último, com `fetch` imediatamente antes)

## O que aconteceu

[W] trouxe o achado pronto e o enquadrou como **fork de decisão**: (a) implementar o `/mwart-override`, ou (b) a mensagem parar de prometer. A recomendação vinha em (b). **Medir mudou o fork em vez de confirmá-lo.**

**(a) não é "talvez não permitida" — é impossível na forma anunciada.** O `settings.json` registra o hook como **`PreToolUse`** com matcher `Write|Edit|MultiEdit`: ele dispara no **Edit local**, antes de existir PR pra comentar. Comentário de PR não pode destravá-lo, por construção. Implementar exigiria inventar *outro* mecanismo (env var / marcador) = decisão [W] nova. Confirmado no código: **zero `process.env`**, único `argv` é `--selftest`, e os exit paths são só `0` (fail-open) e `2` (veto).

**E o fork já estava decidido no repo.** Em **2026-06-11** o [`visual-regression.yml:709`](../../.github/workflows/visual-regression.yml) removeu a **mesma** oferta com a nota *"nunca houve handler que a processasse"* — e a linha 671 do mesmo arquivo registra que aquela era a **1ª vez** do texto anunciar caminho inexistente. A 3ª ocorrência da classe reabriu um fork que o projeto já havia fechado, por não ter medido.

**Não apaguei a linha — nomeei a fronteira.** O `/mwart-override` **não é ficção**: é exceção de **processo** real, concedida por [W] em PR e registrada em ADR per-tela ([0112](../decisions/0112-mwart-excecao-whatsapp-settings-fix-bugs-2026-05-09.md), [0177](../decisions/0177-mwart-excecao-cliente-show-wave-paralela.md)). Falso era ser escape **deste hook**. Apagar deixaria quem conhece o override — está na canon always-on — ainda achando que valia ali. A redação reusa a que a skill irmã já assentara em 2026-06-11: [`mwart-process/SKILL.md:131`](../../.claude/skills/mwart-process/SKILL.md), *"registro humano, não comando de máquina"*.

**A propagação era 5×, não 1.** O briefing citava o chokepoint; medindo `git grep` no repo inteiro (40 arquivos, 83 linhas): hook · teste · **`proibicoes.md:213`** · `mwart-comparative/SKILL.md:249` · `pages.md`. O terceiro é o que dói — dizia **"Override *runtime*"**, é canon **always-on** importada por `CLAUDE.md`, e foi ela a afordância sobre a qual [W] decidiu. Corrigida no mesmo PR, com a forma **copiável** do comando **descrita em vez de reproduzida** (instrução falsa copiável num doc injetado em toda sessão é o próprio vetor).

**`ADR 0104:158` não foi tocada** — o texto dela descreve exceção de *processo* pra falso-positivo de CI, nunca bypass de runtime: é defensável, e é append-only.

## Artefatos gerados

| Arquivo | O quê |
|---|---|
| [`.claude/hooks/block-mwart-violation.mjs`](../../.claude/hooks/block-mwart-violation.mjs) | comentário L12 + mensagem: para de oferecer, nomeia a fronteira |
| [`.claude/hooks/block-mwart-violation.test.mjs`](../../.claude/hooks/block-mwart-violation.test.mjs) | assert vira **controle negativo** (antes **exigia** a oferta) |
| [`memory/proibicoes.md`](../proibicoes.md) L213 | *"Override runtime"* → o que é verdade, sem reproduzir o comando |
| [`memory/LICOES_CODE.md`](../LICOES_CODE.md) LC-15 | `Conserto NÃO feito` → feito, com o porquê da 1ª saída ser impossível |

**Bite-test** (`node .claude/hooks/block-mwart-violation.test.mjs`): antes verde com a promessa · depois verde, **46 checks** · o assert novo **REPROVA** a mensagem antiga **e** uma oferta reformulada (`"Pra pular, comente /mwart-override no PR"`) — não é evadível trocando a palavra `Override:`. Irmãos verdes: `hook-replay.test.mjs`, `settings-automem-mwart-registration.test.mjs`.

⚠️ **A defesa é advisory.** Roda em `governance-script-tests.yml:212` — lane **advisory**, vermelho lá **não bloqueia merge**; o baseline de required não cita o contexto. Por isso o `Gate:` do LC-15 segue **`parcial`** e **não** foi promovido: seria afirmar cobertura que não tem.

**Ocorrências do LC-15 NÃO incrementado** — isto conserta a ocorrência **08-08 já contada**; incrementar gastaria o mesmo recibo duas vezes.

## Persistência

- **git:** [#5683](https://github.com/wagnerra23/oimpresso.com/pull/5683) **MERGED** por [W] — `a78ee26b583`, 15:03:28Z · **102 pass · 0 fail · 3 skipping**
- **verificado no main pós-merge:** `git grep` da forma copiável em `origin/main` → **zero ocorrências**
- **smoke real:** hook do worktree **byte-idêntico ao main**, exit **2** (ainda morde) com a mensagem nova
- **MCP:** propaga via webhook GitHub (~2min)

## Lições catalogadas

1. **O smoke mediu a árvore errada primeiro.** Rodei o hook a partir de `D:/oimpresso.com` e veio a mensagem **antiga** — parecia que o merge não pegou. O repo principal está em **detached HEAD, 73 commits atrás**; o `main` está checkout em *outro* worktree. **LC-08 na veia**: a prova de que o conserto está em `main` é `git grep` **em `origin/main`**, não `node` numa árvore qualquer. Não mexi nas outras árvores (estado de outras sessões).
2. **Quando o briefing traz números, confira-os.** Vieram *"194 linhas"* e *"assert L150"*; medido: **210 linhas**, regex em **L152**. Nada mudou a conclusão — mas o número de sites (1 → **5**) mudou o escopo do PR.
3. **Erro meu, registrado:** disparei um `sleep 1` em background como no-op enquanto esperava o CI. Gastou um ciclo de notificação à toa.

## Pointers detalhados

- Lápide-mãe da classe: [`memory/proibicoes.md`](../proibicoes.md) §5 **2026-08-08** (*"O `block-mwart-violation` anuncia um `/mwart-override` que NÃO existe"*)
- Ledger: [`memory/LICOES_CODE.md`](../LICOES_CODE.md) **LC-15** — inclui o precedente `knowledge-drift-prosa` e por que ampliar **não** é grep de `override`
- Precedente do mesmo veredito: `.github/workflows/visual-regression.yml` L671 + L709 (2026-06-11)
