---
slug: 0106-recalibracao-velocidade-fator-10x-ia-pair
number: 106
title: "Recalibração de velocidade — fator 10x em tarefas codáveis (IA-pair)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-08'
quarter: 2026-Q2
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
  - 0104-processo-mwart-canonico-unico-caminho
pii: false
---

# ADR 0106 — Recalibração de velocidade (fator 10x em tarefas codáveis com IA-pair)

**Status:** ✅ Aceita
**Data:** 2026-05-08
**Decisão por:** Wagner Rocha
**Não supersede:** afeta interpretação de estimates em todos os SPECs ativos.

---

## Contexto

Wagner reportou em 2026-05-08:

> *"O atraso do S5 já fez eu errar nas tendências. O rendimento está 20 vezes mais rápido. A cada mês antigamente eu resolvo com IA em 1,5 dia. Pode recalibrar para minha velocidade? Bota para 2 dias para ter margem."*

Estimates em SPECs antigos (RecurringBilling, Copiloto, NfeBrasil, etc) usam horas-humano-pré-IA. Com Claude Code + Cursor + skills Tier A + MCP tools, velocidade observada é 15-20x. Estimates desatualizadas geram:

- Roadmap pessimista (S5 ADS Universal previsto pra jul/2026 — pode ser maio/2026)
- Wagner perde janelas estratégicas porque não vê que a Fase X cabe no mês corrente
- Cycles fecham com goal "incompleto" porque baseline é 60h e real foi 6h
- Brief reporta "burndown lento" quando na verdade já entregou tudo

Recalibração não é reduzir DoD — é alinhar **expectativa de relógio** com observação real.

## Decisão

Aplicar **fator 10x de aceleração em tarefas codáveis**, com **margem de 2x** sobre a observação real (15-20x). **Tarefas humano-limitadas NÃO recalibram** — relógio do mundo real não acelera.

### Tarefas que recalibram (codáveis com IA-pair)

| Categoria | Fator | Exemplo |
|---|---|---|
| Codar controller/job/service Laravel | 10x | 8h → 0.8h ≈ 1h |
| Criar Page Inertia/React + componentes | 10x | 12h → 1.2h |
| Pest tests (unit + feature) | 10x | 6h → 0.6h |
| Migration + seeder + factory | 10x | 4h → 0.4h |
| Documentation (RUNBOOK, SPEC, ADR) | 10x | 3h → 0.3h |
| Audit cockpit-runbook modo B | 10x | 2h → 12min |
| Refactor/rename/move | 10x | 8h → 0.8h |

### Tarefas que NÃO recalibram (humano-limitadas)

| Categoria | Tempo | Razão |
|---|---|---|
| Canary 7 dias usuário real | 7 dias relógio | Tempo de observação não acelera |
| Monitor 30 dias pós-cutover | 30 dias relógio | Janela de regressão de borda |
| Aviso prévio cliente + alinhamento humano | 1-2 dias relógio | Larissa precisa atender telefone, ler email, agendar |
| Backup DB + restore drill | 30min-2h relógio | I/O bound + segurança |
| Smoke real fiscal SEFAZ homologação | 1-2h relógio | Latência API SEFAZ + validação visual |
| Code review humano (Felipe/Wagner aprova) | 30min-2h | Tempo de leitura + atenção |
| SSH em produção (Hostinger/CT 100) | 5-30min | Warm-up + comandos sequenciais |

### Margem de 2x

Aplicar margem **dobrando** o resultado recalibrado. Exemplo:

```
US-SELL-005 estimate antigo: 12h
  ÷ 10 (fator) = 1.2h
  × 2 (margem) = 2.4h declarado

Real esperado: ~1.5h
Buffer: 0.9h pra imprevistos (debug, refactor inesperado, blocker técnico)
```

**Por que 2x e não 1.5x:** Wagner observa 15-20x, fator declarado 10x já é conservador; margem 2x sobre 10x = 20x total worst-case = ainda dentro do observado em case favorável.

## Aplicação retroativa

| SPEC | Antes | Depois | Diff |
|---|---|---|---|
| Sells (epic US-SELL-001) | 60h | ~28h (16h codáveis recal × 2 margem + 12h humanos) | -53% |
| Mwart (US-MWART-001..003) | 28h | ~10h (8h codáveis × 0.5 + 6h humanos) | -64% |
| RecurringBilling US-RB-045 | 2h | ~0.5h | -75% |

SPECs em vôo (cycle 03) ficam congelados — recalibração se aplica a SPECs **novos ou em planejamento** + epics ainda não iniciados. Não muda histórico.

## S5 ADS Universal — adiantamento

Era previsto **jul/2026** (skill `ads-decision-flow` lista como S5). Recalibrado:

- Estimate antigo: ~80h núcleo (Risk/Confidence/Policy/Router/HITL)
- Recalibrado: ~12h núcleo (8h codáveis × 1/10 × 2 + 4h teste real humano)
- **Nova janela: ~30 maio/2026** (3 semanas de antecipação)

ADS Universal antecipa pra **viabilizar US-INFRA-002 Sinal Cliente** (que precisa de triage automática) ainda em 2026-Q2.

## Consequências

### Boas

- **Cycles realistas.** Goal "fecha em 14d" passa a ser real — antes era frequente carry-over.
- **Brief mostra progresso real.** Burndown deixa de assustar.
- **Wagner planeja roadmap com confiança.** Janelas estratégicas não são perdidas por estimate desatualizada.
- **S5 ADS adiantado.** Ele mesmo é meta-feature que acelera tudo depois.

### Ruins / mitigações

- **Otimismo cega.** Se eu (agente) errar e tarefa demorar 2x do esperado, Wagner sente como atraso. **Mitigação:** margem 2x já compensa fator de erro normal; se passar margem, registra `velocity_drift` em ADR.
- **Time humano sem IA-pair (Maíra/Felipe sem Claude Code) usa fator 10x e demora mais.** **Mitigação:** ADR ESPECIFICA "com IA-pair". Sem IA-pair, manter estimate antigo; brief diferencia por dev.
- **Tarefas mistas (50% código + 50% humano) ficam ambíguas.** **Mitigação:** quebra em sub-USs separadas (ex: US-X-001 codável + US-X-002 canary).

## Métrica de calibração contínua

Adicionar coluna `actual_h` em `mcp_tasks` (ou usar tempo entre `created_at`→`done_at`). Brief diário inclui:

```
Velocity 7d: estimated 18h | actual 14h | drift +29% (acima do esperado — possível otimismo)
```

Se drift > +50% por 4 cycles consecutivos, registrar ADR de re-recalibração (fator 8x em vez de 10x, ou voltar 12x).

## Alternativas consideradas

- **A — Manter estimates antigos.** Rejeitada: gera roadmap pessimista; Wagner perde janelas.
- **B — Fator único 20x sem margem.** Rejeitada: zero buffer; primeiro imprevisto fura.
- **C — Recalibrar tudo retroativamente.** Rejeitada: SPECs históricos reescritos = ruído. Aplica só pra novo + planejamento futuro.

## Plano de aplicação

1. **Hoje (este PR):** ADR registrado + SPEC Sells/Mwart atualizados + SPEC Infra novo já com recalibração
2. **Próxima sessão:** atualizar SPEC RecurringBilling US-RB-045/046/047 + Copiloto pendentes
3. **Cycle 04 (próximo):** todos SPECs novos nascem recalibrados
4. **Mensal:** verificar `velocity_drift` no brief; recalibrar ADR se necessário (criar 0107)

## Refs

- [ADR 0094 — Constituição V2](0094-constituicao-v2-7-camadas-8-principios.md) — princípio "Loop fechado por métrica"
- [ADR 0095 — Skills Tiers](0095-skills-tiers-convencao-interna.md) — convenção que se aplica
- [ADR 0104 — Processo MWART](0104-processo-mwart-canonico-unico-caminho.md) — primeiro SPEC recalibrado (Sells)
- [skill ads-decision-flow](../../.claude/skills/ads-decision-flow/SKILL.md) — S5 adiantado pra ~30 maio/2026

---

**Última atualização:** 2026-05-08
