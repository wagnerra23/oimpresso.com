---
slug: 0274-referencia-adr-por-slug-alias-map-13-colisoes
number: 274
title: "Referência canônica a ADR = SLUG completo (NNNN-titulo) + alias map das 13 colisões de número — sem renumerar (append-only)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-12"
module: governance
tags: [adr, governanca, slug, alias-map, colisao, append-only, sdd-semana-0]
supersedes: []
superseded_by: []
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0180-drift-numero-adr-0178-conflito-paralelo
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
---

# ADR 0274 — Referência canônica a ADR por SLUG completo + alias map das 13 colisões

> Frente KL-B1 da Semana 0 do plano [2026-06-12-plano-reestruturacao-sdd-ondas-paralelas](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) (§1 "13 colisões ADR → referência canônica por slug + alias map (sem violar append-only)" + §4 frente KL). Origem: [audit SDD 2026-06-12](../sessions/2026-06-12-audit-sdd-pesquisa-reclassificacao.md).

## Contexto

Sessões paralelas escolhem número de ADR sem visibilidade mútua. Resultado medido em origin/main (`afecf98f6`, 2026-06-12) via `node scripts/governance/adr-index-generate.mjs`: **277 arquivos · 262 números únicos · 13 números colididos** (11 duplos + 2 triplos = 28 arquivos). O precedente [ADR 0180-drift-numero-adr-0178-conflito-paralelo](0180-drift-numero-adr-0178-conflito-paralelo.md) já decidiu pra UMA colisão: não renumerar (append-only, ADR 0094 Art. 3 + workflow `append-only-canon.yml` bloqueia rename R092+) e preferir referência por slug. Faltava: (a) generalizar a regra pra TODAS as colisões, (b) registro **machine-readable** consumível por gates/tools (hoje só existe registro loose em `_INDEX-LIFECYCLE.md`, consumido pelo Check A do `memory-health.mjs`), (c) desambiguador curado pra resolver referências legadas "ADR NNNN" por contexto.

## Decisão

1. **Referência canônica a ADR é o SLUG completo** (`NNNN-titulo`, ex.: `0178-sells-unified-tabs-visao-supersede-0136`) — em ADRs novas, SPECs, RUNBOOKs, charters, session logs, PRs e código. Número cru ("ADR 0178") só é aceitável quando o número NÃO está na lista de colisões.
2. **Alias map versionado**: [`governance/adr-alias-map.json`](../../governance/adr-alias-map.json) registra cada número colidido → slugs + `hint` desambiguador. Derivado da seção "Colisões de número" do `_INDEX-GENERATED.md` (fonte gerada, ADR 0258); o `hint` é a única parte curada. Append-only: colisão nova ENTRA no mesmo PR que a criar; entrada existente nunca sai.
3. **Regra de bloqueio futuro**: doc/ADR novo citando número colidido SEM slug = violação. O gate que enforça nasce em **fase 2** (Semanas 1-2 do plano), como ADVISORY (gates novos nunca nascem required — ADR 0271), lendo o alias map como fonte. Esta ADR registra só a decisão; nenhum workflow é criado aqui.
4. **NUNCA renumerar/renomear** ADR colidida (reafirma 0180 + ADR 0094 Art. 3). O alias map é a alternativa permanente à renumeração.

## As 13 colisões (auto-detectadas, re-derivadas de origin/main em 2026-06-12)

| Nº | Slugs colididos (desambiguador no alias map) |
|---|---|
| 0101 | `0101-sistema-charter-capterra-governanca-escopo` · `0101-tests-business-id-1-nunca-cliente` |
| 0102 | `0102-nfce-status-polling-vs-broadcast` · `0102-s6-charter-capterra-postmortem-s7-backlog` |
| 0119 | `0119-migration-factory-capacidade-institucional` · `0119-paralelismo-sessoes-whats-active-tier-1` |
| 0126 | `0126-mcp-jira-projects-modulos-verticais` · `0126-vault-chunked-encryption-sprint-2` |
| 0141 | `0141-agents-tool-use-pattern-claude-code` · `0141-skill-migracao-blade-react` |
| 0170 ×3 | `0170-bancos-nativos-top5-drivers-separados` · `0170-onda5-simplificada` · `0170-paymentgateway-extracao-camada-cobranca` |
| 0178 | `0178-restauracao-campos-fiscais-br-canon` · `0178-sells-unified-tabs-visao-supersede-0136` |
| 0180 | `0180-drift-numero-adr-0178-conflito-paralelo` · `0180-sidebar-v3-5-grupos-ghosts-header` |
| 0195 | `0195-feedback-relevance-scoring-decay-adaptativo` · `0195-tabs-autosave-mount-sempre-hidden` |
| 0216 | `0216-deploy-webhook-rodar-composer-dump-autoload` · `0216-governance-drift-framework-driftchecker-plugavel` |
| 0235 | `0235-ds-v4-accent-roxo-universal` · `0235-staging-ct100-clone-anonimizado` |
| 0236 ×3 | `0236-extrato-conciliacao-modelo-unificado` · `0236-governanca-evolucao-doc-design` · `0236-scorecard-universal-entidade-arbitraria` |
| 0246 | `0246-sessao-2026-05-30-ds-harmonizacao` · `0246-tipo-outros-default-migracoes-legacy` |

## Consequências

### Positivas
- ✅ Referência inequívoca sem violar append-only (zero rename, zero edição em ADR ratificada)
- ✅ Fonte machine-readable pro gate fase 2, pro recall do Jana/MCP e pro golden set KL-C2 (que depende deste alias map no DAG do plano)
- ✅ `hint` curado resolve referências legadas "ADR NNNN" por contexto, sem reescrever docs antigos

### Negativas / trade-offs
- ⚠ Duas fontes sobre colisões até a fase 2 unificar: `_INDEX-LIFECYCLE.md` (loose, Check A do memory-health) e o alias map (estruturado). O gate fase 2 deve apontar Check A pro alias map.
- ⚠ Disciplina manual até o gate existir: nada bloqueia número cru hoje (janela advisory consciente).

## Refs

- [ADR 0180-drift-numero-adr-0178-conflito-paralelo](0180-drift-numero-adr-0178-conflito-paralelo.md) — precedente (caso 0178)
- [ADR 0258](0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md) — índice gerado fonte-única (`_INDEX-GENERATED.md` lista as colisões)
- `scripts/governance/adr-index-generate.mjs` (detector) · `scripts/governance/memory-health.mjs` Check A (registro loose atual)
- Plano-mãe: [memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md](../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) — Semana 0, frente KL
