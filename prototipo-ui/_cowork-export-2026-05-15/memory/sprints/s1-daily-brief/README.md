# Sprint 1 — Daily Brief (camada L7)

> **Objetivo:** entregar uma tool MCP `brief-fetch` que devolve um markdown
> de ~3k tokens com o estado consolidado do projeto, regenerado 6x/dia.
> Toda sessão de Claude (humana ou agent) começa lendo isso.
>
> **ROI esperado:** -50% tokens médios por sessão · -70% tempo de
> reonboarding humano · base para Cockpit do Sprint 6.

---

## Conteúdo deste pacote

| Arquivo | Tipo | Quem implementa |
|---|---|---|
| `01-adr-memory-daily-brief.md` | ADR canon (L5) | Wagner numera + commita |
| `02-schema-aggregator.sql` | Migration + view materializada | Felipe ou Wagner |
| `03-prompt-generator.md` | Prompt do Brain B (sonnet-4.6) | Wagner cola no module ADS |
| `04-tool-brief-fetch.md` | Spec + handler PHP da tool MCP | Wagner |
| `05-skill-brief-first.md` | Skill Tier A (always-on) | Wagner commita em `.claude/skills/` |
| `06-checklist-wagner.md` | Passo-a-passo de rollout | Wagner segue |

---

## Sequência de rollout (estimado: 1 dia útil)

```
1. Renumerar + commitar ADR (passo 1 do checklist)        ~5min
2. Rodar migration SQL (cria tabela cache + procedures + cron)  ~30min
3. Plug do prompt no ADS module (rota interna)            ~45min
4. Implementar handler MCP brief-fetch                    ~1h
5. Commitar skill .claude/skills/brief-first/             ~10min
6. Testar end-to-end: claude code → mcp brief-fetch       ~30min
7. Anunciar no time (Felipe/Maíra/Luiz/Eliana)            ~10min
8. Monitorar 48h: skill_telemetry + ads_decisions          (passivo)
```

---

## Métricas de sucesso (mede após 7 dias)

| Métrica | Como mede | Alvo |
|---|---|---|
| Token onboarding médio | `mcp_audit_log` agg per session | -40% |
| Skills disparadas | `skill_telemetry` | brief-first ≥ 90% das sessões |
| Custo gerador | Brain B tokens × $rate | ≤ $0.40/dia |
| Drift do brief | manual review semanal | <2 inconsistências/brief |

---

## Rollback

Se brief estourar custo ou gerar drift:

1. Desativar cron (`php artisan schedule:work --silent --no-brief`)
2. Skill `brief-first` cai pra Tier C (on-demand) editando `frontmatter.tier`
3. ADR fica com status `paused`, motivo no campo `lessons`

Sem perda de dados — view materializada continua viva, só não regenera.
