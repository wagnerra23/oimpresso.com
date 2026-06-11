---
date: "2026-06-11"
topic: "Ciclo de vida da informação (o 'elefante branco') — diagnóstico da IA + auditoria do caminho de leitura + ADR 0270"
authors: [W, C]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"]
prs: [2527]
---

# Sessão 2026-06-11 — Ciclo de vida da informação (o "elefante branco")

## TL;DR

Wagner perguntou "o que a IA ainda não faz bem / como deixá-la focada", depois "como deve ser o ciclo de vida da informação? **parece um elefante branco**". Diagnóstico da Jana: saturada em isolamento/PII/governança, **fraca em qualidade-de-resposta-medida-em-prod + degradação silenciosa**. Auditoria do caminho de leitura achou o número do elefante: **2.438 .md, 22 de 58 módulos (38%) sem porta da frente, sessions 40→192/mês**. Causa-raiz: sistema otimizado pra ESCREVER, não pra LER; append-only aplicado a conhecimento que devia morrer. Entregue **[ADR 0270]** (porta única + destilação + decaimento + medir leitura), draft PR #2527, status `proposto`. Código (F2-F5) fica pra CT 100.

## O que rolou

Wagner abriu com 3 perguntas encadeadas:

1. **"O que a IA ainda não faz bem? Testes? Qualidade? Como deixá-la focada/controlada?"**
2. **"Como deve ser o ciclo de vida da informação? Por que ter mais não tá fácil de usar. Parece um elefante branco."**
3. **"Acho que vou fazer todas faz muito sentido. É exatamente o que eu sinto. Faça."**

## Diagnóstico da IA (Jana) — entregue no chat

Cruzei código vivo (`Modules/Jana`, `Modules/ADS`) + auditorias canon + 64 gates de CI. Síntese:

- A IA é **saturada em isolamento/PII/governança** (Tier 0, multi-tenant) — acima do estado-da-arte.
- O risco real é **(a) qualidade de resposta não-medida-em-prod** e **(b) degradação silenciosa**.
- **TOP gaps:** Cockpit.tsx responde MOCK hardcoded em prod (risco de confiança nº1); testes anti-alucinação validam *fixture*, não o LLM; RAGAS em mock-only sem gate canário diário; distiller/recall caem em silêncio; self-audit falso-verde (`checkEvalCiGate` aponta arquivo inexistente); Meilisearch SPOF; OTel collector desligado; ADS Dual-Brain (a "coleira") inteiro **desligado por custo**.

## Auditoria do caminho de leitura (o número do elefante)

| Métrica | Valor |
|---|---|
| `.md` total em `memory/` | 2.438 |
| ADRs / Sessions / Handoffs / Docs requisitos | 280 / 262 / 101 / 821 |
| Crescimento sessions | 40 (abr) → 192 (mai) → 26 (jun parcial) |
| Módulos SEM porta da frente (BRIEFING) | **22 de 58 (38%)** |
| Piores | MemCofre 33 docs/0 porta · Inventory 29/0 · ComunicacaoVisual 10/0 |
| Docs concorrendo por "verdade" | 38 BRIEFING + 57 SPEC + 10 CAPTERRA + 17 AUDIT + 140 RUNBOOK |
| `_INDEX-LIFECYCLE` | 13 colisões de número; renumber bloqueado pelo gate append-only |

**Conclusão:** sistema otimizado pra ESCREVER, não pra LER. Diário gigante usado como manual. Append-only aplicado a conhecimento que devia morrer. Mais doc = menos sinal.

## Entregue

- **[ADR 0270]** — Ciclo de vida da informação: porta única + destilação + decaimento + medir o caminho de leitura. 6 decisões (D-1 tipagem da informação · D-2 porta única por módulo · D-3 destilar em cadência · D-4 decaimento + time-decay · D-5 medir leitura não escrita · D-6 parar de crescer ruído) + roadmap faseado F1-F5.
- Status `proposto` — ratificação = merge (sai de draft). Direção aprovada por Wagner no chat.

## Linha que NÃO cruzei

Mudanças de **código** da Jana (distiller estendido, time-decay no recall, matar Cockpit mock, checks no health-check) tocam comportamento de IA em prod → **exigem CT 100** (Tier 0). Ficam como roadmap F2-F5, não mergeadas às cegas nesta sessão de nuvem.

## Ato 2 da sessão — MemCofre, detector, e os 64 gates (append 07:58 BRT)

- **ADR 0270 ACEITA** por Wagner no chat ("Adr aceita") — status atualizado, PR #2527 saiu de draft.
- **Lápide MemCofre** (`memory/requisitos/MemCofre/BRIEFING.md`): MemCofre = DocVault→SRS, ZUMBI (deprecação aprovada nunca executada), 33 docs congelados pré-rename citando `Modules/MemCofre` inexistente, module-grade 73 + auto-audit 97 elogiando o cadáver. **read_path 33→1 medido.**
- **`scripts/governance/knowledge-drift.mjs`** (1ª batida do batimento): hops + identity-drift + staleness por módulo. **Achado: 39/61 módulos citam `Modules/X` inexistente** (renames nunca propagaram).
- **"Os portões têm que ser revistos. Estão defasados e conflitantes"** → auditoria de CONTEÚDO dos 64 workflows → **ADR 0271 ACEITA** + onda 1 executada (`ce41d592`, +225/−666): 6 deletados, RAGAS-teatro desarmado (verificado ao vivo), deadlock ui-architecture desarmado (verificado ao vivo), fonte única do vocabulário ADR, proibicoes §MWART corrigida. **64→58.**
- Meta-lições (Wagner): ter≠qualidade · sistema não preparado pro tempo (derivada>nível) · ADR usada como memória (174 "decisões"/mês) · teto honesto ~85 não 100 · decisão por âncora (invariante→sinal→meta), não por Wagner.

## Próximos passos

- **MERGE do PR #2527** (ready, CI verde) — contém ADRs 0270+0271 aceitas + lápide + detector + onda 1.
- **ONDA 2 dos gates — APROVADA ("pode fazer todos") com condição "consultar o mcp antes"** → plano completo no handoff [2026-06-11-0758](../handoffs/2026-06-11-0758-elefante-branco-adr0270-0271-gates-onda1.md). Sessão nuvem não tem MCP → reabrir com MCP, brief-fetch primeiro, executar.
- F1 da 0270 (portas nos 22 órfãos) + F2-F5 (código, CT 100) + matar Cockpit mock + RAGAS real diário + fix self-audit falso-verde.
