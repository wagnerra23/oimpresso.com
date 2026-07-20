---
date: "2026-07-20"
topic: "BRIEFING como porta-de-entrada: apodrecimento de link tem DUAS direções; como fazer um processo 'sempre ser seguido'; e a correção de rota — o Wagner já resolveu via two-strikes (ADR 0344), não uma skill de briefing"
authors: [M, C]
prs: [4581]
related_adrs:
  - 0344-two-strikes-cobre-processo
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0094-constituicao-v2-7-camadas-8-principios
---

# Sessão 2026-07-20 — BRIEFING porta-de-entrada, anti-órfão bidirecional e "processo sempre seguido"

> **TL;DR:** Maiara pediu o BRIEFING do Produto + verificação anti-apodrecimento. Achado central: **apodrecimento de link tem DUAS direções** — saída (link quebrado) e **entrada** (arquivo do corpus que ninguém linka), e **nenhum gate cobre a de entrada**. A conversa evoluiu pra "como fazer o BRIEFING/metodologia SEMPRE ser seguido". Propus um `briefing-completeness.mjs`; o **adversário refutou** (presence-gate disfarçado, duplica o `system-map`, briga com o BRIEFING destilado). Síntese do "sábio": **gerar a espinha factual** em vez de verificar depois — mecânico garante existência, humano garante correção. No fim, **corrigi um erro meu**: o Wagner já resolveu o problema geral via **two-strikes / ADR 0344** (~10 PRs desta sessão), não uma "skill de briefing".

## O que foi DISCUTIDO

1. **BRIEFING do Produto** como porta-de-entrada do módulo inteiro (não "N telas") — lido de `origin/main` porque o checkout local está ~5,5k commits atrás (base stale).
2. **Anti-apodrecimento de links** — as duas direções: (a) **saída** = todo link do BRIEFING resolve; (b) **entrada** = todo arquivo do corpus é alcançável a partir do BRIEFING. No Produto, 4 arquivos eram órfãos de entrada (`ANTI-REGRESSAO-*` ×2, `PARIDADE-charter-vs-legado.md`, `produtos-gap.md`).
3. **Como garantir que o BRIEFING/metodologia seja SEMPRE seguido** — o coração da conversa.
4. **Verificação de gate:** `charter-refs.mjs` **NÃO** cobre BRIEFING (só `*.charter.md` em `resources/js/Pages/`); `briefing-code-staleness.mjs` mede frescor, não link; nenhum `.github/workflows/*.yml` roda link-checker de markdown. Logo o apodrecimento de link de BRIEFING **não tem gate mecânico** hoje.
5. **Proposta `briefing-completeness.mjs`** → passe adversarial → **refutada** (ver desacordos).
6. **Síntese "sábio":** dividir a metodologia em 3 classes — gerável (gerador), chokepoint (hook/gate na ação), julgamento (revisão humana); "sempre" só existe pras duas primeiras.
7. **Descoberta/correção:** o que o Wagner fez não é skill de briefing — é o **two-strikes (ADR 0344)**.

## O que foi ACORDADO

- **Regra zero-órfãos, nas duas direções:** nenhum arquivo do corpus de um BRIEFING pode ficar solto — sempre linkado. Link de pasta (`_telas/`) conta como aresta (cobre a subárvore). Capturada como feedback recorrente da Maiara.
- **Sempre trabalhar de `origin/main` fresco** (worktree novo) — o working tree local não é canon.
- **NÃO construir `briefing-completeness.mjs` como gate** — refutado; se algum dia virar máquina, **estende o dono do tema** (`system-map`/`charter-refs`), advisory-first, com selftest de mordida, e nunca toca a prosa.
- **A divisão da garantia:** mecânico garante **completude/existência** (determinístico); humano garante **correção/interpretação** (o Achado). Nenhum finge ser o outro. Prometer gate que garante a interpretação = teatro (F6).
- **O mecanismo real de "processo sempre seguido" já existe e está pronto:** o **two-strikes / ADR 0344** do Wagner (merged) — reincidência de erro de processo ≥2× sem gate mecânico (advisory conta como "sem gate") → alarme no SessionStart exigindo defesa mecânica.

## O que foi DESACORDADO / CORRIGIDO

- **Minha proposta `briefing-completeness.mjs` foi refutada** pelo passe adversarial (F1–F6): (F1) "cobertura, não presença" colapsa — verificar que um controller é *referenciado* É presence-gate; (F2) duplica o `system-map`; (F3) briga com o BRIEFING destilado (1 página), e o folder-link esvazia o gate; (F4) sem exceção pra arquivo morto/arquivado; (F5) enshrina a lápide 2026-07-17 (deduzir React-vs-Blade por `Inertia::render` grep); (F6) verde pode fazer confiar demais na prosa não-checada.
- **Minha resposta "não existe skill / nada pronto" estava ERRADA.** Procurei o artefato errado (uma "skill de briefing") e não vi que o Wagner resolveu o problema mais geral via **two-strikes / ADR 0344** (hook `licoes-code-two-strikes.mjs` + `LICOES_CODE.md` + auto-feed #4599). É — ironicamente — instância da classe **LC-08 "afirmar/derivar a partir da fonte/varredura errada"** que o próprio two-strikes já está alarmando (5×, sem gate).
- **Meu PR #4581 virou redundante:** o rewrite do Produto (#4579 → mergeado como #4601 `[M+CC]`) já linka os 4 órfãos. Recomendo **fechar o #4581**.

## PENDÊNCIAS / próximos passos

- **Fechar PR #4581** (redundante com o BRIEFING porta-de-entrada já mergeado).
- **LC-08** (afirmar/medir da fonte errada, 5×, sem gate) — o two-strikes já pede defesa mecânica pra essa classe; candidato a próximo trabalho.
- Se um dia o "briefing sempre seguido" precisar de trilho próprio: skill (carrega o método) + gerador de espinha (Fase 1 não-pulável) + hook no commit — no padrão que o repo já usa pro MWART.

## Estado no fechamento

- `origin/main` @ `1c6110ee2` (#4601 — BRIEFING do Produto como porta-de-entrada, `[M+CC]`, mergeado).
- PR #4581 (SupportWR) aberto e agora redundante.
- two-strikes / ADR 0344 `aceito` + merged; hook registrado em `.claude/settings.json` (SessionStart) + selftest + CI `governance-script-tests.yml`.
