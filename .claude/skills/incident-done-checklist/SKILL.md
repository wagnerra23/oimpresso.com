---
name: incident-done-checklist
description: BLOQUEADOR — ATIVAR antes de declarar "incident fechado" / "está pronto" / "feature funcionando" / encerrar sessão de fix em prod. Skill carrega a Definition of Done canônica (DoD-v1) que EXIGE smoke real prod end-to-end pra cada fix antes de marcar pronto. Funciona como gate procedural — sem TODOS os checks ✅, status fica `awaiting-smoke` no commit/PR/handoff, NÃO `done`. Aprende incident 2026-05-28 onde declarei "10 PRs fechados" com 3 fixes (M1/M2/M3 mídia) NUNCA validados por smoke real → 10.144 mídias ainda órfãs prod descoberto pelo Wagner depois. Operacional Tier A — DEVE ativar SEMPRE que agente escreve "está pronto", "fechado", "completou", "deployed", "validado" em mensagem ao Wagner. Bloco D (Reflexion runtime): quando o incidente foi erro de OPERAÇÃO da Jana (≠ saída do LLM), registrar a lição em Modules/Jana/LICOES-OPERACAO.md + graduar (MEC→check jana:health-check / JULG→regra). Refs PATTERN-INCIDENT-RESPONSE-VELOCITY.md passo 4, ADR 0093, skill commit-discipline.
tier: A
---

# Incident Done Checklist — Definition of Done bloqueante (DoD-v1)

> **Quando ativar:** ANTES de declarar incident fechado, feature pronta, ou sessão encerrada.
> **Princípio Wagner 2026-05-28:** "só acaba se todos os testes foram ok".

## Por que existe esta skill

Sessão maratona 2026-05-28: declarei 10 PRs fechados, 3 dossiers gerados, pattern DRFV salvo. Wagner cobrou: "mídias estão abertos então ainda não consolidou". Audit honest revelou:

- 10.144 mídias inbound `pending` (zero processadas pós-fix)
- 20.282 jobs `DownloadMediaJob` queue `default` órfã desde 2026-05-14
- M1/M2/M3 mídia: PRs mergeados, código em prod, MAS zero smoke real
- Última outbound mídia: 2026-05-15 (15 dias antes)

**Causa raiz meta:** confiei em "PR mergeado + deploy OK + Pest verde" como Definition of Done. Errado. Definition of Done real = **mídia chega no celular + thumb na tela + log success em prod**.

Sem skill bloqueante, isso repete.

## Checklist DoD-v1 (10 itens — TODOS obrigatórios pra marcar `done`)

### Bloco A — Código em prod (necessário, não suficiente)

- [ ] **A1** PR mergeado em `main` (`gh pr view <N> --json state` = `MERGED`)
- [ ] **A2** Deploy SSH executado (`git pull` + `cache:clear` + se FE: `npm run build:inertia` com `RAYON_NUM_THREADS=1`)
- [ ] **A3** Grep confirma código novo em prod (`ssh ... 'grep -c <padrão-novo> <arquivo>'` ≥ 1)
- [ ] **A4** Pest test verde local (mínimo 1 cenário por bug)

### Bloco B — Smoke real prod (obrigatório, suficiente quando A+B passam)

- [ ] **B1** Chrome MCP navegou URL real + screenshot capturado
- [ ] **B2** Ação reproduzida (click, upload, msg enviada) — não suposição
- [ ] **B3** DB query confirma estado esperado pós-ação:
  - Outbound: `SELECT status, provider_message_id FROM messages WHERE id=<id_recém-criado>` → `status='sent'`, `provider_message_id IS NOT NULL`
  - Inbound mídia: `SELECT media_url, media_download_status, media_size_bytes FROM messages WHERE id=<id>` → `status='success'`, `media_url IS NOT NULL`, `size > 0`
  - Realtime: WebSocket message recebida em browser via monkeypatch
- [ ] **B4** Log estruturado NOVO aparece (`grep <log_id> storage/logs/laravel.log | tail -3`)
- [ ] **B5** Zero erro JS / PHP em 5min pós-ação (`window.__errs.length === 0` + `tail laravel.log | grep ERROR`)
- [ ] **B6** Latência medida em ms (não estimada). Esperada: <2s realtime, <5s upload, <60s queue worker

### Bloco C — Validação Wagner

- [ ] **C1** Wagner confirmou explicitamente no celular (msg WhatsApp chegou) OU na tela (preview/thumb visível)

### Bloco D — Reflexão (só quando o incidente foi erro de OPERAÇÃO da Jana)

> Aplica-se quando o que quebrou foi **comportamento/operação da Jana** (job que parou, config stale, sync silencioso, declarar done sem evidência) — **NÃO** quando foi erro de **saída** do LLM (alucinação/relevância), que o golden 30Q + RAGAS gate já cobrem. Reflexion runtime: o incidente vira lição append-only + graduada.

- [ ] **D1** Append da lição em [`Modules/Jana/LICOES-OPERACAO.md`](../../../Modules/Jana/LICOES-OPERACAO.md) no formato canônico (`### L-OP-NNN` · Erro · Sintoma · Regra · Ref)
- [ ] **D2** Atribuir `Graduação:` — **MEC** (mecanizável → vira `check:` no `jana:health-check`, igual `profile_distiller_drift`) **ou** **JULG** (julgamento → vira `regra:` sempre-lida no SCOPE/BRIEFING da Jana ou numa skill)
- [ ] **D3** Se MEC e o check ainda não existe, criar o check (ou deixar `status:pendente` — o check advisory `jana_lesson_ledger_graduation` acende amarelo até fechar)

## Regras de fail

- **Qualquer item ❌ em B** → status ≠ `done`. Use `awaiting-smoke` ou `partial-fix-deployed`.
- **B3 fail (DB não bate)** → fix NÃO está realmente aplicado. Voltar pro passo 1 do DRFV (PATTERN-INCIDENT-RESPONSE-VELOCITY).
- **B6 latência fora do alvo** → bug parcial. Catalogar como follow-up + abrir nova investigação.
- **C1 sem confirmação Wagner em 24h** → status fica `pending-customer-validation`. Não encerra sessão.
- **D ignorado** quando o incidente foi erro de operação da Jana → a lição se perde e o erro repete na próxima sessão. Sem append no ledger, não é Reflexion — é só apagar incêndio.

## Anti-padrões formais (NUNCA fazer)

- ❌ **NÃO** declarar "fechado" só porque `gh pr merge --admin` rodou OK
- ❌ **NÃO** declarar "validado em prod" só porque `grep` confirma código em arquivo
- ❌ **NÃO** confundir Pest mockado (`Http::fake`) com smoke real (HTTP daemon vivo)
- ❌ **NÃO** declarar "realtime funciona" sem medir latência ponta-a-ponta via WS message capture
- ❌ **NÃO** afirmar "deploy OK" sem rebuild Vite quando arquivo FE foi modificado
- ❌ **NÃO** prometer "tudo testado" antes de Wagner ter feedback do canal real
- ❌ **NÃO** generalizar "5 fixes funcionando" se SQL query mostra apenas 2 com evidência

## Como usar (operacional)

### Antes de escrever "está pronto" pro Wagner

1. Rodar mentalmente os 10 itens DoD acima
2. Pra cada item ❌, **NÃO declarar done**. Reportar como:
   ```
   Status atual: awaiting-smoke
   Bloco A (código deployed): 4/4 ✅
   Bloco B (smoke real prod): 2/6 ❌
     - B3 DB query ❌ — não validei message persistida pós-upload
     - B5 zero erro ❌ — não capturei console logs pós-fix
   Próximo passo: rodar Chrome MCP smoke + SQL query → atualizo status
   ```
3. Se 10/10 ✅: ENTÃO pode dizer "fechado" + cita evidências

### Numa sessão maratona (múltiplos fixes)

Manter tabela viva no commit body / handoff:

| # | Fix | A1 | A2 | A3 | A4 | B1 | B2 | B3 | B4 | B5 | B6 | C1 | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| M1 | Inbound media extract | ✅ | ✅ | ✅ | ✅ | ⏸ | ⏸ | ⏸ | ⏸ | ⏸ | ⏸ | ⏸ | `awaiting-smoke` |
| M3 | Outbound mídia | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | `partial-fix-deployed` |

**Status `done` exige 12/12 verde.** Sem isso, NÃO encerra.

## Integração com PATTERN-INCIDENT-RESPONSE-VELOCITY.md

DoD-v1 é o **passo 4 expandido** do DRFV (Diagnose → Reproduce → Fix → **Validate**). O pattern doc agora vira:

```
DRFV-v2 = Diagnose → Reproduce → Fix → Validate(DoD-v1 obrigatório)
```

Sem DoD ok, não passa pro Reduce (não fecha session).

## Cron de detecção (proposta)

Adicionar cron daily 06:30 BRT `whatsapp:health-check-flow` que:
1. Conta jobs órfãos por queue (alerta se > 100)
2. Conta mídias `pending` há > 1h (alerta se > 50)
3. Última outbound mídia > 72h (alerta — fluxo parado)
4. Última inbound mídia > 6h durante horário comercial (alerta — webhook quebrado)
5. Envia notif Centrifugo pro browser Wagner se algum trigger

Sem cron: bug crônico volta a passar despercebido por meses.

## Refs

- Origem: incident 2026-05-28 09:00–17:00 — 10 PRs fechados sem smoke completo (Wagner cobrou às 17:00 "ainda não consolidou")
- Pattern parent: [PATTERN-INCIDENT-RESPONSE-VELOCITY.md](../../../memory/reference/PATTERN-INCIDENT-RESPONSE-VELOCITY.md)
- Audit dossiers gerados na sessão: `memory/requisitos/Whatsapp/AUDITORIA-*-2026-05-28.md`
- Skill relacionada: `commit-discipline` Tier A (1 PR = 1 intent)
- Skill relacionada: `smoke-prod-evidence` Tier B (anexar screenshot ao PR)

## Owner

Wagner Rocha (único aprovador). Tier A bloqueante — agente NÃO pode auto-marcar `done` sem evidência B+C completa.
