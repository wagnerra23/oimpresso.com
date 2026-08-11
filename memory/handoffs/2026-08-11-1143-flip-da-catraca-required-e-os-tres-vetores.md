---
date: "2026-08-11"
time: "11:43"
slug: flip-da-catraca-required-e-os-tres-vetores
tldr: "Continuação do handoff 08-10 19:19. [W] mandou promover a catraca de nota a required apesar de 0 mordidas em 241 modificações; a ADR 0373 registra a decisão E a medição contra ela. Flip aplicado (41→42, string-exato, protection-drift verde). Adversário achou 2 buracos que viraram PR e refutou uma claim minha que eu ia canonizar no runbook. 5 PRs, 8 no total da sessão, todos merged."
prs: [5552, 5556, 5570, 5574]
us: []
next_steps:
  - "Destravar os 3 PRs DIRTY (#5501, #5397, #5566) — update-branch após o merge ficar limpo"
  - "Gate de reversão da ADR 0373: rebaixar se FP bloquear PR legítimo ou 90d sem mordida"
related_adrs:
  - 0373-screen-grades-ratchet-required-emenda-0314
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314
---

# Handoff — o flip da catraca, e os três vetores de "required que não nasce"

> Continuação direta do [handoff 2026-08-10 19:19](2026-08-10-1919-scorecard-orfao-e-a-catraca-que-nao-ve-delecao.md), que fechou o gap #22. [W] leu o achado (a catraca não vê deleção) e mandou **promover a required** — daí esta segunda metade.

## A decisão, e a medição registrada CONTRA ela

Eu **recomendei não promover**, com número: **241 modificações de scorecard → 0 quedas**; **258 deleções → 0 fugas**. Zero mordidas, contra as **≥2** que a [ADR 0336](../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) DR-2 exige. É a situação nominalmente lapidada 2× no §5 (`foundation-ratchet` 07-01, `component-registry-check` 07-17).

Achei ainda que a **[ADR 0369](../decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md) (05/08, decisão do próprio [W], 5 dias antes)** tinha deixado este gate de fora **de propósito** — *"amostra pequena demais (5 a 28 runs)"*, `promote_by: 2026-09-05`, razão escrita: *"decidir no escuro seria pior que esperar"*. **O prazo não venceu e a razão não mudou.**

[W] reafirmou. A **[ADR 0373](../decisions/0373-screen-grades-ratchet-required-emenda-0314.md)** registra a promoção **e** os números contra ela, explicitamente: *"não afirma que a evidência sustenta — registra que o dono decidiu sabendo que não sustenta"*. Sem isso, uma sessão futura leria só a decisão e repetiria o `foundation-ratchet` achando que havia base.

## A ordem importava, e foi respeitada

**#5552 antes do #5556.** Promover um gate com o buraco aberto cria o incentivo de usar a saída: se o gate bloqueia e apagar o YAML faz passar, required piora em vez de melhorar.

| PR | O quê | CI |
|---|---|---|
| [#5552](https://github.com/wagnerra23/oimpresso.com/pull/5552) | Fecha o vetor 2 (deleção) · FP 0/258 · bite por mutação | 98 pass |
| [#5556](https://github.com/wagnerra23/oimpresso.com/pull/5556) | ADR 0373 + `paths:` fora + rename + baseline + registry | 105 pass |
| [#5570](https://github.com/wagnerra23/oimpresso.com/pull/5570) | `required-always-run` vê `if:` de JOB (3º vetor) | 98 pass |
| [#5574](https://github.com/wagnerra23/oimpresso.com/pull/5574) | Bite-test da catraca entra no metaguard **required** | verde |

**Flip do vivo executado:** `41 → 42` contexts, string-exato, `protection-drift` **🟢 ok**, `update-branch` em 2 PRs (3 recusaram por conflito).

## O adversário pagou três vezes

1. **Refutou uma claim que eu ia canonizar.** Reportei que `gh api --input <arquivo>` falha no Windows e que só stdin funciona — **e ia escrever isso no runbook**. Testei eu mesmo depois: as duas formas dão `rc=0` e saída **idêntica**. A causa que atribuí é falsa; a próxima pessoa seguiria receita errada.
2. **Corrigiu meu diagnóstico do `DS gate`.** Eu disse "latência de fila"; é **estrutural** — o job tem `needs: [conformance, ui-lint]`, então o check-run não existe até os dois terminarem. Ausência nos primeiros minutos é o **estado normal**, não sinal de nada.
3. **Achou 2 buracos reais** que viraram #5570 e #5574 (abaixo).

E acertou numa crítica ao processo: eu fiz **rename do job + remoção do `paths:` no mesmo commit** — o vetor da lápide 08-08. Mitigado por rodar `update-branch` no mesmo ato (o que o runbook prescreve), mas o padrão foi repetido.

## Os dois buracos que o flip expôs

**O lint era cego ao 3º vetor.** `required-always-run` media `paths:` filtrado e ausência de gatilho de PR, mas não `if:` de **job** — que também impede o check-run de nascer. FP medido antes de armar: **13** `if:` de job, **4 no balde perigoso**, **0 required** ⇒ FP zero, valor preventivo real.

**O gate required tinha bite-test advisory.** O `--selftest` da catraca rodava só no `governance-script-tests.yml`, que não bloqueia. Movido para o job required `gate selftest (GT-G6)` — como **step**, não fixture, porque a fixture é dado em git e a catraca faz `git show <ref>:<path>`, que resolve da raiz do repo.

## Erros meus, registrados

- **Reproduzi o LC-11 dentro do PR que conserta um achado adversarial.** Meus asserts do eixo novo mediam `ifPerigoso.length` — o **dado**. Removi o `process.exit(1)` e o selftest **ficou verde**. Só o E2E que roda o CLI de fora e olha o exit code mata esse mutante.
- **Antes disso li o oposto:** a 1ª tentativa de mutar por replace de string **não aplicou**, e eu concluí "mutante sobreviveu" com o arquivo intacto. Refiz por número de linha com antes/depois impresso. A lápide §5 sobre isso já existia.
- **Meu `grep -c 'paths:'` casou o próprio comentário** que eu tinha acabado de escrever (*"SEM `paths:` DE PROPÓSITO"*) e quase me fez concluir que o `paths:` seguia lá.
- **`git show origin/main:.github/…` falhou 2× em silêncio** (MSYS mangleia o `:` com dot-path). A rota que funciona é `git ls-tree` + `git cat-file -p <blob>`.
- **`rc=$?` depois de pipe** — de novo. A 1ª leitura de `required-always-run` deu "rc=0" que era do `tail`.

## Estado MCP no momento do fechamento

⚠️ **Não medido — o servidor `Oimpresso MCP — Wagner` DESCONECTOU durante a sessão** (as tools saíram do registro; antes disso, as 3 chamadas do checklist já haviam falhado com `-32603 Bridge fetch error` / `unavailable`).

Registro como **ausência de medição, não de pendência**. Único estado conhecido: o brief datado do hook de resume (#494, ~91 min antes do fechamento anterior). Nada nesta sessão criou ou moveu task no MCP.

## Verificado em `main` no fechamento

`required_status_checks` = **42** com o context novo presente · `classificaIfEmPR` e `classificarDelecoes` presentes · step do metaguard wirado · `protection-drift` rc=0 · `required-always-run` rc=0 (43/43) · `gate-selftest` rc=0.

## Resíduo

3 PRs `DIRTY` (#5501, #5397, #5566) sem o check novo — travados pelo próprio conflito, não pelo flip; `update-branch` recusa PR em conflito. **Não confie em "resolver o conflito faz o check nascer"**: não está provado (rebase sem trazer `main` pode não bastar). O caminho determinístico é `gh pr update-branch` depois que o merge ficar limpo.

E o gate de reversão da 0373 está armado: rebaixar se um FP bloquear PR legítimo, ou 90 dias sem mordida, ou a fila de CI virar gargalo.
