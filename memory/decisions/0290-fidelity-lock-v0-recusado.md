---
slug: 0290-fidelity-lock-v0-recusado
number: 290
title: "v0 'Fidelity Lock' (screenshot pareado em CI) — RECUSADO: fidelidade visual não se prova por render pareado"
type: adr
status: recusado
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-18"
rejected_at: "2026-06-18"
rejected_via: "2 adversários (processo + técnico) em 2026-06-18, ratificado por [W] — ver RUNBOOK-contrato-de-tela §4 'O que morreu (v0)'"
rejected_reason: "Inviável + tautológico + backdoor de prosa (3 motivos na Decisão). REABRE só se surgir um check de fidelidade HERMÉTICO (render-free, sem auth/CDN, sem mapa-de-classes mantido à mão)."
tags: [design-system, contrato-de-tela, fidelidade-visual, adversarial, recusado]
supersedes: []
superseded_by: []
related:
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0114-prototipo-ui-cowork-loop-formalizado
---

# ADR 0290 — v0 "Fidelity Lock" RECUSADO

> **Primeiro `status: recusado` canônico** (proposal `recusado-com-motivo`, o NÃO consultável). Registra uma abordagem que foi **projetada e rejeitada** na mesma sessão — pra que ninguém re-proponha sem ler por que morreu.

## Contexto

Sessão 2026-06-18: o design Cowork não estava chegando fiel em produção (Inertia/React). A 1ª ideia pra travar isso — o **"Fidelity Lock" (v0)** — era um gate de CI que **renderiza o protótipo e o prod e compara screenshots pareados**, falhando na 1ª divergência visual "injustificada", com match de cor OKLCH↔Tailwind.

## Decisão

**RECUSADO.** Dois adversários (processo + técnico) derrubaram o v0 com evidência do próprio repo, antes de aplicar:

1. **Screenshot pareado em CI é inviável.** O protótipo precisa de servidor + 3 CDNs + Babel; o prod precisa de login + tenant + PII. Em CI passa **VERDE quando os dois lados renderizam erro** (tela de login vs CDN 429) — o pior tipo de falso-positivo: verde por coincidência de quebra.
2. **Match OKLCH↔Tailwind é tautológico.** Exige um mapa-de-equivalência cor↔classe **mantido à mão** — a whitelist que engole divergência (o mesmo anti-padrão que aposentou o match de classe↔classe).
3. **"Falha na 1ª divergência injustificada" é backdoor de prosa.** "Injustificada" é um campo que o réu (o agente) preenche livremente pra passar — auto-certificação com mais YAML.

## Consequências

- ✅ Substituído pelo **v1 (catraca "Contrato de Tela", aceita)**: checagens **determinísticas, sem render, sem auth** — âncora `data-contract` + copy literal + ordem + acordo semântico de `state` backend↔frontend ([ADR 0286](0286-channel-health-corroborado-por-mensagem-real.md) §5). O juízo visual subjetivo (cor/ícone/densidade) fica com o humano (screenshot · [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)), **não** automatizado.
- ✅ O NÃO fica **consultável**: `decisions-search` responde "por que não temos screenshot-diff de fidelidade?" — corta o re-litígio na entrada.
- 📝 **Critério de reabertura** (recusa é condicional, não cemitério): reabre se surgir um check de fidelidade **hermético** — render-free, sem auth/CDN, e sem mapa de cor mantido à mão. Até lá, fidelidade de cor = `ds-guard` (higiene) + olho humano.

## Anchor

**Documentado em:** `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` §4 ("O que morreu — v0") + §3 (as 3 condições inegociáveis que o v1 cumpre). Mecanismo v1: `scripts/contrato-de-tela.mjs`.
