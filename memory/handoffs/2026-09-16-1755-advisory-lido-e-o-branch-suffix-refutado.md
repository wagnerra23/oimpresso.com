---
date: "2026-09-16"
time: "17:55 UTC"
slug: advisory-lido-e-o-branch-suffix-refutado
tldr: "Verifiquei o #7431 pelo contador de assertions (4599 to 4629, +3 testes = os 3 casos do arquivo). Depois abri o watchdog de cron que eu tinha chamado de ruido na sessao anterior — e ele estava CERTO: verdadeiro positivo no eixo entrega. Lapide LC-08 em #7440. Recomendei branch-suffix como conserto; uma sessao irma ja tinha MEDIDO e DESCARTADO exatamente isso (34/348 PRs duplicados) e mergeado o conserto certo 29s depois do meu PR nascer. Errata em #7441."
prs: [7440, 7441]
decided_by: [W]
next_steps:
  - "Nada pendente desta sessao — os 2 PRs mergearam e o conserto da corrida (#7438) e de outra sessao."
  - "Se alguem reabrir branch-suffix no sdd-scorecard-publish: esta PROIBIDO por medicao (34/348), nao por hesitacao — ver o YAML do #7438."
---

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **sem tasks ativas** pra `@wr23`
- `decisions-search "cron watchdog entrega heartbeat advisory"` → 0234 (registry de automações), 0133 (health audit), nenhuma ADR dona do watchdog G6 em si (ele é ADR 0317, fora do top-3 do full-text)
- **6 handoffs irmãos hoje** (11:00 · 13:30 · 14:15 · 14:45 · 15:15 · 17:32) — dia de muita sessão paralela, e isso é parte da lição abaixo

## O que aconteceu

**1. Fechei a verificação do #7431** que a sessão anterior deixou prometida. A régua é a mesma que pegou o defeito: o contador de assertions da lane sqlite. Antes `1215 passed (4599 assertions)`, depois `1218 passed (4629 assertions)` — **+3 testes / +30 assertions**, e o arquivo tem exatamente 3 casos. Atribuição provada, não por proximidade: `CredentialShapes` aparece **0×** no log de antes e **3×** no de depois, e os 2 commits entre os runs são docs (`git log --stat ... -- tests/**` vazio). Ressalva: o run do próprio `e769befc36b` foi **cancelado** por concorrência; a medição é no descendente `ce9e8adadaf`.

**2. Peguei o watchdog de cron que eu tinha sinalizado como ruído — e eu estava errado.** Ele é verdadeiro positivo. Heartbeat `26/26` verde; quem reprovava era o eixo **entrega**: `sdd-scorecard-publish.yml` com a última run agendada em `failure`. Os **dois números** que eu havia publicado ao [W] eram não-medidos: "constante" (medido: 1 de 15 runs agendadas) e "4 de 4 PRs" (medido: **7 branches distintas**). Lápide §5 + recibo LC-08 no **#7440**.

**3. O [W] mandou fazer o `branch-suffix` e eu não fiz — porque já estava medido e descartado.** Ao abrir o arquivo pra editar, a linha 146 não era o que eu esperava: o **#7438**, de sessão irmã, já estava em `main` com o mecanismo real (**tracking ref residual** do checkout `fetch-depth: 0` virando o lease implícito do `--force-with-lease`) e com a minha proposta descartada por medição no próprio YAML — *"sufixo por run duplicaria PR em **34 dos 348 runs**, porque o dedup do peter-evans é load-bearing"*. Frequência deles também mais forte: 3 em ~348, correlação 4/4, com controle negativo.

**4. O #7440 mergeou 29s depois do #7438**, carregando a recomendação falsa pra canon append-only → errata no **#7441** (recomendação errada preservada, errata ao lado, que é a convenção da casa).

## Artefatos gerados

| Arquivo | Δ | Canon |
|---|---|---|
| `memory/licoes-rejeitadas.md` | +20 / +10 | fonte do §5 (append-only Tier 0) |
| `memory/proibicoes.md` | +8 / +6 | **derivado** (`sec5-derive --write`, nunca à mão) |
| `memory/LICOES_CODE.md` | +1 rec LC-08, +1 rec LC-19, 1 rec atualizado | ledger (contador derivado, número não editado) |

## Persistência

- **git**: #7440 (`81dadc3f648`) + #7441 (`bc99568a9a4`), ambos em `main`, mergeados por `wagnerra23`
- **MCP**: webhook propaga `memory/*` em ~2min — sem task criada (o trabalho é ledger, não backlog)
- **BRIEFING**: não aplicável (nenhum `Modules/<X>` tocado)

## Lições catalogadas

- **LC-08 (limite novo):** ao medir para dissolver **objeção própria**, escreva antes a pergunta que a objeção faz e confira que a sonda a responde. Eu levantei o risco certo (acumular PR), medi **15 PRs mergeados (420–665s, 0 abertos)** e li como "auto-merge nunca trava" — sonda da pergunta **vizinha**. A pergunta era *"quantos runs começam com o PR anterior ainda ABERTO?"*, a mesma janela da corrida: **34/348**. Número tranquilizador sobre pergunta vizinha encerra o debate com aparência de rigor.
- **LC-08 (o eixo da lápide):** vermelho de check que lê **estado compartilhado** em N PRs é **uma observação replicada N vezes**, nunca N evidências. Não sustenta "constante" — cronicidade exige **série temporal do produtor**, não corte transversal de PRs abertos no mesmo instante.
- **LC-19 (near-miss, nada editado):** as duas defesas rodaram e nenhuma pegou. `whats-active` mede **Edit/Write por sessão**, não **PR aberto por tema**; e o probe de estado mergeado eu rodei no path do **instrumento** (`*cron-watchdog*`) em vez do path do **sujeito acusado** — que, mesmo certo, sairia vazio porque o #7438 ainda estava **aberto**. Regra: sondar o path do sujeito nos **dois** estados.
- **Higiene, 4× na mesma sessão:** medi a coisa vizinha da que perguntei — `$2` do `gh pr checks` (nome tem espaço), `jq` inexistente na máquina (o hook `block-sonda-que-mente` mordeu e estava certo), os 15 PRs acima, e `git log --not` lido como "conteúdo único" quando mede **alcance de commit** (com squash e cherry-pick os SHAs são outros por construção). Só a 3ª chegou a virar afirmação publicada; as outras morreram na própria saída contradizendo o rótulo.

## Pointers detalhados

- §5 `2026-09-16 — Chamar de "alarme que se aprende a ignorar"…` em [`memory/licoes-rejeitadas.md`](../licoes-rejeitadas.md) (lápide + errata no mesmo verbete)
- O mecanismo real da corrida está **documentado no próprio YAML**: [`.github/workflows/sdd-scorecard-publish.yml`](../../.github/workflows/sdd-scorecard-publish.yml), bloco `CORRIDA DO TRACKING REF` — é a melhor fonte, não este handoff
- Handoff irmão de 14:45 ([`token-pares-revogacao`](2026-09-16-1445-token-pares-revogacao-e-o-dia-de-medir-errado.md)) registra a **mesma classe** no mesmo dia, em outra sessão
