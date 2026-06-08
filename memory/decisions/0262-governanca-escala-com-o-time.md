---
slug: 0262-governanca-escala-com-o-time
number: 262
title: "Governança escala com o time: review opcional pra dev solo + evolução = mais fácil, não só mais controle"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-06-07"
accepted_at: "2026-06-07"
accepted_via: "Wagner na sessão: 'parece que o sistema ficou burro e burocrático... fica trancado'. Decisão tomada + aplicada (required_reviews 1→0) na mesma sessão."
module: governance
quarter: 2026-Q2
tags: [governance, ci, branch-protection, enforcement, dev-experience, right-sizing]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0261-enforcement-faseado-gates-ci", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
---

# ADR 0262 — Governança escala com o time

## Contexto

Logo depois de apertar o enforcement ([ADR 0261](0261-enforcement-faseado-gates-ci.md): required 1→4 + skip-as-pass), Wagner reportou o custo na pele: _"parece que o sistema ficou burro e burocrático... decisões não estão fáceis de tomar... fica trancado sempre perguntando coisas simples."_

Duas verdades nesse feedback:

1. **A regra "1 review obrigatório" é cerimônia pura pra dev solo.** O GitHub bloqueia auto-aprovação — então um único dono não consegue satisfazer a regra, e cada merge vira `admin-merge` (válvula de escape virando o caminho normal). A regra protege contra "ninguém revisou" — situação que **só existe com time**, não com 1 pessoa.

2. **Evolução que só adiciona controle não é evolução.** O princípio da catraca ([ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md)) mira "não regredir". Mas um sistema que fica mais rígido sem ficar mais **fácil de usar** apenas engorda regra. Governança que trava o próprio dono é insurance mal-calibrada — o custo está invertido.

## Decisão

**A governança escala com o tamanho do time, não com o medo.**

1. **`required_pull_request_reviews.required_approving_review_count` = 0 enquanto solo.** PR verde mergeia direto, sem admin-merge. Os gates de TESTE permanecem required (Pest, build, module-grades — esses pegam bug real). Some só a cerimônia de peer-review, que não tem par.

2. **Religar review quando o time MCP (Felipe/Maiara/Eliana/Luiz) entrar.** Peer-review é seguro pra time; é overhead pra solo. O gatilho é "≥2 devs ativos no repo", não uma data.

3. **Princípio durável — toda adição de controle deve vir com a pergunta "isto fica mais fácil ou só mais travado pro operador atual?".** Se a resposta for "mais travado e o operador é solo", a regra é prematura: pertence ao backlog "quando o time entrar", não ao enforcement de hoje.

## Consequências

**Positivas.** Merge solo deixa de exigir ginástica de admin. O nível de governança passa a refletir a realidade (1 dev), não a aspiração (time futuro). O enforcement de teste — a parte que evita bug — fica intacto.

**Negativas / riscos.** Sem review, um PR ruim que passa nos testes mergeia sem segundo par de olhos. Mitigação: é exatamente a realidade de um dev solo (já era assim na prática via admin-merge); o ganho de fluidez supera, e a reativação é 1 comando quando o time chegar.

**Meta-lição (pra o operador IA também).** Parte do "burocrático" não era o sistema — era o agente devolvendo menu a cada passo em vez de decidir e reportar. Recomendar-não-perguntar ([skill `nudge-recommend-not-menu`](../../.claude/skills/)) vale tanto quanto right-sizing de gate. Burocracia é decisão empurrada de volta pra quem já disse "vai".
