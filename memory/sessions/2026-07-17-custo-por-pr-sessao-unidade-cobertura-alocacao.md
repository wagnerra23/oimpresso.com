---
date: "2026-07-17"
hour: "17:49 BRT"
topic: "Custo por PR — unidade=SESSÃO, cobertura de ALOCAÇÃO (3,2%→81%), calibração derivada, revisão adversarial 2 agentes; landing rebaseado no main fresco (PR #4488)"
authors: [C]
prs: [4488]
related_adrs:
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Custo por PR — a sessão é a unidade, a cobertura é do dinheiro

Registro do arco do `agent-cost-per-pr` (governança advisory de custo-eficiência, item #7 do ranking adversarial grade-das-réguas 2026-07-12) — do diagnóstico à revisão adversarial — **e** do landing desta sessão (rebase dos 5 commits sobre `origin/main` fresco, que estava 60 commits à frente da base do branch).

## O arco (o que os 5 commits resolveram)

1. **A premissa da grade estava STALE.** A grade 2026-07-17 mandou "consertar o join" com a manchete *"`matched_por_branch: 0` está morto, 96,8% do gasto fora da conta"*. Rodado AO VIVO: o `matched_por_branch: 0` vinha do snapshot de **07-13**; ao vivo o join por branch casava **12/20**. O número que motivava o conserto era um retrato de 4 dias atrás. (Corolário perene, virou §5: **antes de consertar um medidor, RODE-O ao vivo.**)

2. **O "órfão" não era join — era bookkeeping, decomposto em 3.** (a) **artefato de janela**: o scan cobria ~8,8d contra ~1,8d de PRs (~28 pontos de "órfão" eram só janelas incoerentes); (b) **cauda-de-branch**: `if (casouPorBranch) { fora += resto; continue }` descartava o CORPO da sessão e suprimia o fallback → PRs de sessão inteira apareciam a $4,22 ao lado de $444; (c) **cap silencioso** do `gh --limit 200` (alcançava ~4d; universo 104 vs 435 PRs reais).

3. **A unidade de custo é a SESSÃO, não a mensagem.** O `gitBranch` é gravado por mensagem, mas o padrão real do projeto é gastar na branch da worktree e criar a branch de tópico só no fim — o branch do PR marca só a CAUDA. Trocar a unidade pra sessão + consertar os 3 defeitos levou a cobertura de **3,2% → ~81%** sem inventar fonte nova.

4. **A cobertura passou pro lado do DINHEIRO (FinOps), não do lado do PR.** Antes dizia "0% de PRs casados" enquanto 88% do dinheiro não tinha dono; agora mede `total_usd_atribuido / total_usd_escaneado` + **resíduo decomposto** (sessão sem PR = overhead genuíno, não buraco de join).

5. **Nada sai sem a IDADE colada.** Snapshot auto-denunciante: `_LEIA_PRIMEIRO` é a **1ª chave em disco** com aviso pra rodar ao vivo (defesa central contra ler um número velho como fresco).

6. **Calibração DERIVADA do dado, não cravada.** Limiar de idade = janela × tolerância (0.2); velocidade PR/dia medida do dado.

7. **Lápide §5** (`proibicoes.md`): casar custo→PR por **SHA** ou **Anthropic Analytics API** pra "fechar o órfão" **não recupera nada** — 5 das 6 branches de maior resíduo **não têm PR algum** no GitHub (sem aresta pra achar), e o cherry-pick de consolidação reescreve o SHA. Não perseguir cobertura → 100%: trabalho-sem-PR é overhead legítimo (FinOps trata como *unallocated*).

## A revisão adversarial (2 agentes) — o coração aguentou, os defeitos eram na borda

Dois revisores general-purpose independentes + probes atacaram por todos os ângulos. O **coração** (join de 2 sinais: unidade=sessão · branch vence citação · nada vaza pra PR alheio) **aguentou**. Os achados, todos na **borda de saída** e nos guardas:

- **H1/H3** — cobertura imprimia **>100% (impossível)**: numerador somava round2 por-PR, denominador era o acumulador cru → atribuído > escaneado. Fix: numerador cru, arredonda só na borda → 100,00% exato, nunca >100, conservação ao centavo.
- **Tautologia** (a mais grave, pela ironia) — o teste "`_LEIA_PRIMEIRO` é a 1ª chave" **reconstruía o literal DENTRO do teste** (truísmo da linguagem), não a escrita real; o agente mutou a produção e passava verde. Era exatamente a classe de mentira que o arquivo existe pra matar. Fix: o teste invoca o CLI real, lê o arquivo, checa a 1ª chave **em disco**. **Controle-negativo**: pôr a chave por último agora FALHA.
- **H2/H4/H5/H6** — largest-remainder no resíduo (Σ categorias == residuo.usd); days<=0 → velocidade null; `--days` negativo/garbage → default; headRefName duplicado → paga o PR mais novo; crash latente do `--render-snapshot` em forma antiga → avisa e sai 0.

Selftest passou de **70 → 90 checks**. Controle-negativo rodado nas 2 correções críticas (H1 e `_LEIA_PRIMEIRO`): ambos ficam **vermelhos** contra o código bugado — não são tautológicos.

## O landing (esta sessão)

A base do branch `claude/competent-bassi-60b5cb` estava **60 commits atrás** do `main`. Rebaseado sobre `origin/main` fresco via cherry-pick dos 5 commits para `claude/land-custo-por-pr`:

- **Conflitos resolvidos:** `proibicoes.md §5` (append-only — mantidas as duas entradas: "Razão de fidelidade" do main + "Casar custo→PR por SHA" do branch); `gates-registry.json` (auto-merge limpo, adições não-sobrepostas).
- **Diff vs main fresco = exatamente 5 arquivos** (`agent-cost-per-pr.mjs` · `.test.mjs` · snapshot `.json` · `gates-registry.json` · `proibicoes.md`) — sem falsas deleções da base stale.
- **Re-verificação na base fresca** (local — .mjs Node, não CT100): selftest **90 checks · SELFTEST OK** · irmão DORA `agent-pr-outcomes` **SELFTEST OK** · `selftest-registry-check` **zero órfãos**.
- **Snapshot regenerado ao vivo** (6º commit de higiene — cumprindo a própria lápide "RODE-O ao vivo"): cobertura **79,25% → 80,67%** (mesma janela 14d, dado local acumulado no mesmo dia). Invariantes exatos: 1ª chave `_LEIA_PRIMEIRO`, sem BOM, cobertura ≤100, `atribuido+residuo == escaneado` (20651,79 ao centavo), `cobertura+residuo.pct == 100`, USD-only (zero R$, Tier 0).

**PR [#4488](https://github.com/wagnerra23/oimpresso.com/pull/4488)** aberto contra `main`. **Advisory de nascença** (ADR 0271/0314) — não é gate, não bloqueia merge. **NÃO mergeado** (R10 — só [W] mergeia).

## Estado / pendências

- PR #4488 aguarda review + merge do [W]. `gh pr checks` reportado ao [W] no fechamento.
- MCP oimpresso **não conectado** nesta sessão (o brief veio do hook SessionStart, não da tool viva) → checklist de fechamento feito por fallback filesystem.
- Off-cycle. Worktree `silly-varahamihira-e2babf` @ base `origin/main`.
