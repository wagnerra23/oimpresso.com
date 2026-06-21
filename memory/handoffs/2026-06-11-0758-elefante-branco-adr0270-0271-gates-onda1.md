---
date: "2026-06-11"
hour: "07:58 BRT"
slug: elefante-branco-adr0270-0271-gates-onda1
topic: "Sessão 'elefante branco': diagnóstico IA + ciclo de vida da informação (ADR 0270 aceita) + lápide MemCofre 33→1 + knowledge-drift.mjs + auditoria dos 64 gates (ADR 0271 aceita, onda 1 executada 64→58) — ONDA 2 APROVADA mas NÃO executada (fechei pra reabrir com MCP)"
tldr: "PR #2527 (ready, CI verde): ADR 0270 aceita (porta única + destilação + decaimento + medir leitura) · lápide MemCofre 33→1 · knowledge-drift.mjs (39/61 módulos citam Modules/X inexistente) · ADR 0271 aceita (auditoria dos 64 gates: 12 conflitos, 1 teatro, 1 deadlock; onda 1 = 64→58 + fonte única vocab). ONDA 2 APROVADA ('pode fazer todos') NÃO executada — condição 'consultar o mcp antes' e a nuvem não tem MCP → reabrir com MCP, plano detalhado abaixo."
duration: "~5h"
authors: [CL, W]
session: claude-code-web-ai-limitations-qa-4h10n0
---

# Sessão "elefante branco" — ADRs 0270/0271 + onda 1 dos gates (PR #2527)

> Ambiente: **claude.ai/code (nuvem)**, branch `claude/ai-limitations-qa-4h10n0`, PR **#2527** (ready-for-review, não-draft, CI verde na última leva). Sem tools MCP oimpresso (confirmado via ToolSearch — só GitHub MCP). Wagner encerrou com *"salve para iniciar uma sessão com mcp"*.

## Estado MCP no momento

⚠️ **Tools MCP oimpresso INDISPONÍVEIS neste ambiente** (`brief-fetch`/`my-work`/`cycles-active`/`decisions-search` não existem na sessão nuvem — verificado via ToolSearch, 2 buscas, zero match). Este é o MOTIVO do fechamento: Wagner autorizou a onda 2 com a condição *"só tem consultar o mcp antes"* — impossível daqui. **Próxima sessão (com MCP): começar por `brief-fetch` + `my-work` + `whats-active` ANTES de executar a onda 2.** Snapshot impossível = registro honesto da ausência (não vou inventar estado).

## O arco da sessão (5 atos)

1. **"O que a IA ainda não faz bem?"** → inventário factual da Jana (agente leu Modules/Jana+ADS+auditorias): controlada em isolamento/PII/governança (saturado), fraca em (a) qualidade-de-resposta não-medida-em-prod (HallucinationEvalTest valida FIXTURE, não o LLM; RAGAS mock-only) e (b) degradação silenciosa (distiller/recall caem sem alarme). Top gaps: Cockpit.tsx responde MOCK hardcoded em rota live; ADS Dual-Brain (PolicyEngine/HITL = a "coleira") inteiro desligado por custo; OTel collector off; self-audit falso-verde (`checkEvalCiGate` aponta `eval-recall-gate.yml` inexistente); Meilisearch SPOF; sem time-decay no recall.
2. **"Ciclo de vida da informação? Parece elefante branco"** → auditoria do caminho de leitura: 2.438 .md, 22/58 módulos sem porta, sessions 40→192/mês, 6 tipos de doc competindo por "verdade". Causa-raiz: otimizado pra ESCREVER não pra LER; append-only aplicado a conhecimento que devia morrer. → **ADR 0270 ACEITA** (Wagner: "Adr aceita"): D-1 informação tipada (lei/decisão/verdade-atual/log) · D-2 porta única por módulo · D-3 destilar em cadência · D-4 decaimento+time-decay · D-5 medir leitura (não escrita) · D-6 parar de crescer ruído. Roadmap F1-F5 (F2-F5 = código, valida CT 100).
3. **Piloto medido**: lápide `memory/requisitos/MemCofre/BRIEFING.md` — descoberta: MemCofre = DocVault→SRS, ZUMBI, deprecação aprovada nunca executada, 33 docs congelados pré-rename descrevendo módulo inexistente, e os 2 grades elogiando o cadáver (73/100 + auto-audit 97/100). **read_path 33→1.** + `scripts/governance/knowledge-drift.mjs` (1ª batida do "batimento"): mede hops/identity-drift/staleness por módulo — **achado: 39/61 módulos citam `Modules/X` inexistente** (renames Copiloto→Jana, MemCofre→SRS nunca propagaram).
4. **"Os portões têm que ser revistos. Estão defasados e conflitantes"** → Wagner escolheu (b) "leia tudo e reavalie" → agente leu o CONTEÚDO dos 64 workflows + scripts + ADRs 0261/0263/0264. Achados: **12 conflitos** (enum module/status em 3 fontes divergentes; 4 réguas de charter; 4 baselines de cor; 3 scanners de secrets com canary ADR 0216 nunca fechado; umbrella duplica execuções), **1 gate de teatro** (jana-ragas exit 1 sobre score mockado), **1 mentira no canon** (proibicoes dizia que mwart-gate bloqueia — é soft), **1 deadlock latente** (ui-architecture required+path-scoped), **~14 mortos**, ADR 0261 defasada em 48h (diz 4 required; real ~9). Proposta: **LEI-11 + 64→33**.
5. **Onda 1 executada** ("pode fazer") → **ADR 0271 ACEITA** + commit `ce41d592` (+225/−666): 6 workflows deletados (4 debug/incidente + ui-canon-notify webhook-fantasma + screen-smoke-after-merge comenta-no-PR-errado) · jana-ragas desarmado (exit 1 só em RAGAS_MODE=real; mock=advisory — **verificado ao vivo no próprio PR**: rodou mock e passou como advisory) · ui-architecture sem paths (always-run — **verificado ao vivo**, passou em PR sem UI) · fonte única vocab (adr.schema.json ganhou enum module ~37 valores idêntico ao linter; _schema.json virou espelho GERADO) · proibicoes §MWART corrigida. **64→58.**

## Meta-lições da sessão (Wagner martelou 3×)

- *"Ter não quer dizer qualidade suprema"* / *"o sistema não está preparado para o tempo evoluir"* / *"ADR está sendo usada como memória, está perdendo o sentido"* — *"Seria assim que você se construiria?"* → resposta honesta: NÃO. Nota do sistema atual na régua-resultado: **~35/100** (forte: invariantes Tier 0; fraco: recuperação/esquecimento/ancoragem). Teto do melhor sistema possível: **~85, não 100** (4 tensões irredutíveis) — perseguir 100 com catraca-só-sobe é ficção (caso SRS 73 elogiado morto). O que importa é a **DERIVADA** (sistema se afia sozinho?), não o nível.
- **Decisões devem ancorar em função, não em Wagner**: ordem invariante→sinal-cliente/métrica→delta-meta; humano só no resíduo de valores. Wagner: *"eu não deveria escolher, deve ter âncoras melhores"*.
- Eu mesmo reproduzi a doença na sessão: resolvi "excesso de docs" adicionando docs; pulei o validador local e briguei 4 rounds com gates pelo enum; propus métrica de presença (front_door_coverage) que se auto-burla (porta da Jana = índice de 27 links conta como ✅).

## ⏭️ ONDA 2 — APROVADA POR WAGNER ("pode fazer todos"), NÃO EXECUTADA

**Pré-condição que faltou aqui: consultar MCP primeiro** (brief-fetch + my-work + whats-active). Depois, executar:

| # | Item | Detalhe | Risco |
|---|---|---|---|
| 1 | **F3 secrets** | Mover cron `secrets:audit --auto-pr` pro `governance-drift.yml` (job scheduled) + deletar `secrets-governance.yml` (fecha o canary 7d da ADR 0216, nunca fechado) | baixo |
| 2 | **F6 ragas** | Deletar `ragas-gate.yml` (W22 MVP superseded pelo jana-ragas-gate, nunca exit 1) — trio→2. Fusão completa trio→1 é opcional | baixo |
| 3 | **Deletar softs superseded** | `charter-gate.yml` (régua migrou pro casos-gate ADR 0264; referencia ADR 0101 colidida + sprint morta) + `mwart-gate.yml` (soft, só comenta; casos-gate+screen-coverage cobrem — ATUALIZAR proibicoes §MWART de novo ao deletar) + `pr-ui-judge.yml` (kill-switch OFF, dormente) | baixo; mwart = voto meu, Wagner aprovou "todos" |
| 4 | **Required-readiness** | `no-mock-gate` e `phpstan-gate` → always-run (sem paths; ratchet passa trivial) · `financeiro-pest` → always-run + skip-as-pass interno · `governance-gate` (pii-scan + append-only) → always-run | médio (custo CI em PR docs) |
| 5 | **LEI-11 flip branch protection** | Promover a required: multi-tenant-gate (nº 1!), financeiro-pest, pii-scan, append-only, no-mock, phpstan, secrets-consolidado. Comando: `gh api -X PATCH repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks` (precisa gh local Wagner OU agente com acesso) | Wagner executa ou supervisiona |
| 6 | **module-grades required→ratchet** | Rebaixar (check mais caro do repo, métrica composta, elogiou SRS zumbi) — branch protection | Wagner |
| 7 | **F1 identity fusão (4 gates cor→1)** | conformance+ui-lint+stylelint+CockpitAccentCanon → 1 workflow 1 baseline. ⚠️ TOCA CHECKS REQUIRED — coordenar rename com branch protection NO MESMO momento (senão deadlock "Expected" geral) | alto — fazer por último |
| 8 | **Fix self-audit falso-verde** | `SystemAuditCommand::checkEvalCiGate` aponta `eval-recall-gate.yml` inexistente (real: `jana-ragas-gate.yml`) — 1 linha | baixo |

**Depois da onda 2 (backlog da sessão, ADR 0270 roadmap):** F1 portas nos 22 módulos órfãos (cada uma destilada de leitura real — padrão lápide MemCofre quando zumbi) · knowledge-drift.mjs como check/cron · F2-F5 (checks no health-check, distiller estendido, time-decay recall, arquivamento) = código IA, CT 100 · matar Cockpit mock · RAGAS real diário (~centavos) · deprecação SRS (plano aprovado parado) · propagação-ou-falha de renames.

## Estado do PR #2527

- **Ready** (saiu de draft), CI **verde** na última leva conferida (RAGAS advisory ✓ · ui-architecture always-run ✓ · ADR gates ✓ · module-grades ✓ · Pest/Vite estavam in-progress, sem falha reportada via webhook depois).
- Contém: ADR 0270 (aceita) + ADR 0271 (aceita) + lápide MemCofre + knowledge-drift.mjs + onda 1 gates + session log + este handoff.
- **Falta: MERGE** (Wagner ou próxima sessão; squash). Auto-merge não habilitado no repo.

## Pegadinhas pra próxima sessão

- Vocabulário ADR agora tem **1 fonte**: `scripts/memory-schemas/adr.schema.json` (enum module ~37 valores). `_schema.json` é espelho GERADO — não editar à mão. Linter Pest ainda é implementação separada do mesmo vocabulário (drift futuro possível — candidato a sync-check na F1).
- ADRs novas: `kind` ∈ {decision, feature-wish, errata, meta} · `module` lowercase canon · validar com AJV **Ajv2020** (draft 2020-12; Ajv normal quebra).
- Editar ADR ainda-não-mergeada (status A no PR) NÃO viola append-only — o gate compara contra main.
- `knowledge-drift.mjs`: thresholds calibrados LINKS_INDICE=15, STALE_DAYS=30 — heurísticos, recalibrar com uso.
