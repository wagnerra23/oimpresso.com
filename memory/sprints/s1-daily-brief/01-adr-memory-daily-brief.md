# ADR MEMORY-NNNN — Daily Brief: contrato de contexto consolidado L7

> **Renumere para o próximo número canônico antes de commitar** (ex: 0092
> se sua última ADR for 0091). Mantenha em `memory/decisions/`.

- **Status:** proposta
- **Data:** 2026-05-06
- **Autor:** Wagner + Claude (designer)
- **Tier:** CANON
- **Charter pai:** mission.constituicao-v2 (ADR irmã, Sprint 2)
- **Supersedes:** —
- **Referenciada por:** (Sprint 2 ADR Constituição v2 inteira)

---

## Contexto

Hoje toda sessão de Claude Code (humana ou agent) começa com 5–8 tool calls
exploratórios pra reconstruir contexto: `cycles-active`, `sessions-recent`,
`tasks-active`, `decisions-search`, etc. Custo médio por sessão: 15–30k
tokens só de orientação, antes de produzir qualquer coisa. Com 10 agentes
ativos e ~30 sessões/dia, o desperdício diário ultrapassa 500k tokens.

Wagner também reconstrói esse mapa mental várias vezes por dia — sem
fonte única de verdade do "estado agora".

## Decisão

Criar uma camada **L7 Daily Brief**: um único markdown de ~3k tokens
gerado automaticamente 6x/dia (a cada 4h em horário comercial PT-BR),
servido pela tool MCP `brief-fetch`, e consumido **antes de qualquer outra
tool** por toda sessão de Claude via skill `brief-first` (Tier A always-on).

### Contrato do Brief

Markdown rígido com 7 seções fixas, nesta ordem, total ≤3.500 tokens:

1. **ESTADO MACRO** — cycle ativo, mission charters ativas, HITL pending,
   budget Brain B usado hoje
2. **EM VOO AGORA** — PRs/work em curso (humano ou agent), com aging e lock
3. **DECISÕES RECENTES (24h)** — ADRs aprovadas, commits, escalonações ADS,
   incidentes
4. **SKILLS USO 7d** — top 5 skills por trigger_count + candidatas a poda
5. **CHARTERS APODRECENDO** — last_verified >60d
6. **FLAGS** — semáforo de risco (🔴 crítico / 🟡 atenção / 🟢 ok)
7. **METADATA** — generated_at, generator_version, token_count, source_hash

### Fonte dos dados

100% interno (não chama APIs externas). Lê de:

- `mcp_cycles` (cycle ativo — ADR 0070)
- `mcp_tasks` (HITL pending, em voo — ADR 0070)
- `mcp_sessions` (últ. 24h)
- `mcp_ads_decisions` (escalonações, budget)
- `mcp_audit_log` (commits via webhook GitHub)
- `mcp_skill_telemetry` (uso 7d)
- `mcp_page_charters` (last_verified) — quando Sprint 3 entregar
- `mcp_memory_documents` (ADRs aprovadas 24h)

Agregação via tabela cache singleton `mcp_brief_inputs_cache` (ver
`02-schema-aggregator.sql`), atualizada por `CALL refresh_brief_inputs_cache()`
no mesmo cron do gerador, imediatamente antes da chamada Brain B.

### Geração

Cron Laravel `schedule:command brief:generate` 6x/dia (07h, 11h, 14h, 17h,
20h, 23h America/Sao_Paulo). Pipeline:

1. `CALL refresh_brief_inputs_cache()` (TRUNCATE+INSERT singleton)
2. Lê linha única de `mcp_brief_inputs_cache` (já agregada em JSON)
3. Manda pro Brain B (claude-sonnet-4-6) com prompt fixo (ver
   `03-prompt-generator.md`), temperature 0.2, max_tokens 4096
4. Valida output: 7 seções presentes, ≤3500 tokens, headers exatos
5. Grava em `mcp_briefs(id, generated_at, content, token_count, source_hash)`
6. Atualiza ponteiro `brief.current` (latest)

Se geração falhar ou validar falso → mantém brief anterior, alerta no
MCP inbox (channel `ops`).

### Consumo

Tool MCP `brief-fetch` (ver `04-tool-brief-fetch.md`) devolve `brief.current`.
Cache HTTP `Cache-Control: max-age=300` (5min) — mesma tool chamada por 10
agents na mesma janela hits cache.

Skill `brief-first` (Tier A, ver `05-skill-brief-first.md`) força ordem:

```
1. brief-fetch        ← obrigatório, primeira call
2. charter-fetch      ← se for editar arquivo com .charter.md
3. demais tools       ← contexto-específico
```

## Invariantes (não mudar sem nova ADR)

1. Brief ≤3500 tokens. Hard limit. Validador rejeita acima.
2. Geração ≤6x/dia. Mais que isso vira spam de cache + custo.
3. Brief NUNCA chama APIs externas. Tudo interno.
4. Brief NUNCA contém PII de cliente final (filtros explícitos no MV).
5. Skill `brief-first` é Tier A — não pode virar Tier B/C sem ADR.
6. Custo total Brain B do brief ≤ $0.50/dia. Trigger de alerta em $0.40.

## Consequências

### Positivas
- Onboarding de sessão cai de 15-30k para 3k tokens
- Wagner tem fonte única do "estado agora"
- Base de dados pro Cockpit do Sprint 6 (mesmo MV)
- Testabilidade: brief é markdown determinístico, fácil de diff e testar

### Negativas / riscos
- Custo fixo $0.30-0.50/dia (aceitável; ROI compensa em <3 dias)
- Brief pode ficar stale entre gerações (4h gap) — mitigado por
  flag `staleness_minutes` no cabeçalho
- Drift do prompt do gerador: gera resumos diferentes em runs idênticos.
  Mitigação: temperature 0.2 + golden test diário no CI

### Ignored alternatives
- **Streaming/realtime** — descartado: 6x/dia cobre 95% dos casos a custo
  trivial. Realtime triplica custo sem ROI proporcional.
- **Brief por agente** — descartado: mesma info pra todos é o ponto.
  Personalização vira charter, não brief.
- **Sem LLM (puro template)** — descartado: prosa curta humanizada do
  Brain B vale o $0.30/dia (validado em pilot mental).

## Plano de medição

7 dias após deploy, agregar:

```sql
SELECT
  DATE(s.created_at) AS dia,
  AVG(s.tokens_in) AS tokens_in_avg,
  SUM(CASE WHEN s.first_tool = 'brief-fetch' THEN 1 ELSE 0 END) * 1.0 / COUNT(*) AS brief_first_rate
FROM mcp_sessions s
WHERE s.created_at > NOW() - INTERVAL 7 DAY
GROUP BY 1;
```

Sucesso = `tokens_in_avg` cai ≥40% E `brief_first_rate` ≥0.9.

## Status de adoção

- [ ] Migration SQL aplicada
- [ ] Cron rodando 6x/dia sem falha por 48h
- [ ] Tool MCP registrada e auditada
- [ ] Skill commitada em `.claude/skills/brief-first/`
- [ ] Time avisado (Felipe/Maíra/Luiz/Eliana)
- [ ] Métricas semana 1 coletadas → review
