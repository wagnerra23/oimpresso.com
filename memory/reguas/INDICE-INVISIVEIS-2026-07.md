---
title: Índice dos mecanismos existia-mas-invisível — insumo pro 0330-sucessor
date: "2026-07-18"
authors: [C]
fonte_de_verdade: memory/reguas/fraquezas.json (campo existia_invisivel + onde_indexar)
---

# Mecanismos que EXISTIAM mas estavam fora do mapa (2026-07-18/19)

> **Por que existe:** o sangramento nº 1 do ciclo de réguas — a pesquisa re-descobre como "gap" mecanismos que JÁ existem (7 na grade completa + 15/16 na parcial). Este é o índice durável desses achados, gerado do ledger `memory/reguas/fraquezas.json`. **NÃO é um mapa paralelo** ao [0330](decisions/0330-mapa-dos-niveis-estado-real-2026-07-constituicao.md) (append-only, não se edita) — é o INSUMO que o próximo 0330-sucessor absorve. O detector `scripts/governance/reguas-indexar.mjs` marca cada linha como indexada quando ela entra aqui.

**19 mecanismos, 5 alvos.**

## → mapa 0330-sucessor

| id | mecanismo | evidência (repo vivo) | dim |
|---|---|---|---|
| `spec-agentsmd-port` | AGENTS.md/portabilidade | AGENTS.md existe + agents-md-staleness no CI; hooks trackeados 39 .mjs+24 .ps1, wired 23+18 (@811da8e7) | spec-governanca |

## → mapa

| id | mecanismo | evidência (repo vivo) | dim |
|---|---|---|---|
| `spec-testes-assertions` | Testes ligados a assertions do spec | 3 camadas required (casos-gate G-2, @covers-us, status-derivado); gap real só regeneração full-app | spec-governanca |
| `spec-hooks-desarmados` | Hooks desarmados/desacoplados | metaguard settings-registration ×9 + gate-selftest + protection-drift; resíduo L2 decide() dormente | spec-governanca |
| `dtc-visreg-pixel-only` | visual-regression seria pixel-only | VisregThreshold double-threshold + style-fingerprint DOM-semântico 25 campos; ML/SSIM deliberado ADR 0290 | design-to-code |
| `dtc-tokens-dtcg` | Tokens fora do DTCG / sem Style Dictionary | *.tokens.json DTCG + Style Dictionary ^4.4 + dtcg-equivalence.mjs; gap = linter WCAG/dedup | design-to-code |
| `mem-bitemporal` | Sem bi-temporal | jana_memoria_facts valid_from/until + BiTemporalResolver + SupersedeDetector (ADR 0074/0295; modelo do Zep — +18,5% é número publicado DELES, arXiv 2501.13956) | memoria-conhecimento |
| `mem-ttl-morto` | TTL last_tested morto | doc-freshness-score.mjs 0-100 (#4031); TTL declarado rejeitado de propósito | memoria-conhecimento |
| `mem-recall-benchmark` | Sem benchmark de recall da memória | jana:recall-eval recall@K + recall-golden.yaml 28 queries + gate CI | memoria-conhecimento |
| `orq-adversarial-demanda` | Adversarial só sob demanda | pr-critic.yml on pull_request todo PR de produto, contexto-zero (#4029/#4058) | orquestracao-adversarial |
| `ev-trajectory` | Zero trajectory/step-level | MetricasReflexivasCommand pontua tool-calls porém SEM INVOCADOR (chokepoint fantasma §5) | evals-outcome |
| `ev-coorte-trend` | Sem coorte nem trend | trend JÁ existe (US-GOV-052 #4053, brief 6×/dia); caveats janela sobreposta + sem recibo de prod | evals-outcome |
| `ev-medidores-consolidados` | Fronteira: medidores consolidados | 4 medidores deconflitados vivos + 2 harnesses; row custo×outcome pertence a custo-eficiência (dono único) | evals-outcome |
| `erp-agente-processo` | Nenhum agente de processo | ADR 0145 aceita 0 commits; chassi VIVO DecisionRouter (PolicyEngine+HITL 4 níveis); degrau 1 shipped 07-17 (chat tool-loop read-only) | erp-ia-produto |
| `erp-extensibilidade-ia` | Sem extensibilidade de IA | Skill Studio ADR 0076 end-to-end (skills per-tenant + PR GitHub automático) + Automation Registry 0234; gap: 14 agentes runtime PHP hardcoded | erp-ia-produto |
| `erp-ia-formulario` | Sem IA em formulário | existe em ≥4 forms (Ponto IA-classify, OCR-boleto, Cliente IATab, ComposerV4); ausente form de PRODUTO | erp-ia-produto |
| `erp-loop-qualidade-prod` | Loop de qualidade em prod desligado | ragas-real-eval não-tautológico pisos MEDIDOS + invocador CT100 provado + heartbeat DURO; online-eval flag OFF deliberado (gates [W]/LGPD) | erp-ia-produto |

## → mapa 0330 stale

| id | mecanismo | evidência (repo vivo) | dim |
|---|---|---|---|
| `mem-fonte-unica-skills` | Fonte-única violada (tier skill 4 fontes) | skills-index-generate --check (#4032) | memoria-conhecimento |

## → BRIEFING Compras + mapa

| id | mecanismo | evidência (repo vivo) | dim |
|---|---|---|---|
| `erp-captura-documental` | Captura documental parcial | PurchaseXmlController completo ÓRFÃO sem rota; DFe SEFAZ automática SHIPPED daily 06:15 + manifestação testada; OFX shipped; Pluggy flag OFF; ausências provadas: fatura cartão, DDA | erp-ia-produto |

## → BRIEFINGs Financeiro/Jana

| id | mecanismo | evidência (repo vivo) | dim |
|---|---|---|---|
| `erp-preditivo` | Zero preditivo | fluxo projetado 35d LIVE + projeção fechamento no brief + alertas estoque; ausente demanda por SKU | erp-ia-produto |

---
_Gerado de `memory/reguas/fraquezas.json` em 2026-07-18/19. Re-gerar: filtrar `existia_invisivel:true`. Ao absorver no 0330-sucessor, manter os ids pra rastreabilidade._
