# ROADMAP — Comissão de Vendedores cross-vertical

> 4 fases sequenciais. Estimates pós-recalibração IA-pair fator 10x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) com margem 2x onde envolve humano (canary, smoke, monitor).
> Última atualização: 2026-05-12

---

## Fase 1 — Fundação P0 (1 cycle ~2 semanas)

**Goal outcome:** ROTA LIVRE (biz=4) processa 1 venda de teste com cálculo automático funcionando ponta-a-ponta no FSM, sem afetar produção real. Schema + side-effects + fechamento básico no ar.

### Entregas

| US | Feature | Estimate | Owner sugerido |
|----|---------|----------|----------------|
| US-COMM-001 | Schema 6 tabelas + multi-tenant scope + seeder backward-compat | 3d | [W] backend |
| US-COMM-002 | Side-effect `CalcularComissaoSells` no `marcar_pago` | 2d | [W] / [F] |
| US-COMM-003 | Side-effect `EstornarComissao` no `cancelar_venda` (clawback automático pending → void; manual paid → review) | 2d | [W] |
| US-COMM-007 | Comando artisan `commission:close --month` + dry-run | 1d | [F] |
| Pest cobertura | 5 testes mínimo: cross-tenant biz=1/biz=99 + 3 cenários SPEC §2 (A/C/F) | 1d | [F] / [L+C] supervisão |

**Total Fase 1: ~9d IA-pair (1 cycle confortável).**

### Gates Fase 1

- [ ] Pest 100% verde local
- [ ] biz=4 ROTA LIVRE testada em **dev local** (sem prod)
- [ ] Sanity check Eliana[E]: dry-run do fechamento abril/26 bate com planilha manual atual (tolerância 0%)
- [ ] [ADR 0143 §6](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) gateway side-effect respeitado (estoque + comissão são side-effects, não Controllers)

### Bloqueadores potenciais

- Aprovação D1-D5 do [ADR draft](../../decisions/proposals/drafts/comissao-vendedor-cross-vertical.md) por Wagner antes de US-COMM-001 começar
- Confirmação Eliana[E] que CLT Art. 466 não exige tratamento especial pra comissão automática

---

## Fase 2 — UI MWART + multi-papel ComVis (P1, 1 cycle)

**Goal outcome:** ComVis pode ser ativado em cliente piloto (Vargas ou Mhundo se disponível) com multi-papel automático. UI gestão policies via Inertia.

### Entregas

| US | Feature | Estimate |
|----|---------|----------|
| US-COMM-004 | UI cadastro policy + role distributions (MWART Inertia) | 3d (inclui 1d skill `mwart-comparative` V4 + aprovação Wagner SCREENSHOT) |
| US-COMM-009 | Multi-papel ComVis seed + mudança POS UI (designer/instalador dropdown) | 3d |
| US-COMM-008 | Relatório mensal per-vendedor UI (substitui ReportController legacy) | 3d |

**Total Fase 2: ~9d.**

### Gates Fase 2

- [ ] Skill `mwart-comparative` V4 — `Comissao-policies-visual-comparison.md` aprovado Wagner ANTES de Edit
- [ ] Skill `mwart-process` 5 fases cumpridas (PLAN → BACKEND BASELINE → FRONTEND → QA → CUTOVER)
- [ ] Browser MCP smoke biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- [ ] [SPEC ComVis US-COMVIS-011](../ComunicacaoVisual/SPEC.md) marcada `superseded_by: US-COMM-009`

### Bloqueadores potenciais

- Wagner aprovar SCREENSHOT (não tabela) — pode demorar se Wagner está em outras prioridades
- Mudança no POS form (`SellPosController` adiciona seleção designer/instalador) — afeta tela usada por todos clientes → necessário canary
- Cliente ComVis piloto não confirmado ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — sem sinal sem feature ativa)

---

## Fase 3 — Tiers + Accelerator + Approval workflow (P1, 1 cycle)

**Goal outcome:** ROTA LIVRE roda 1 mês completo (mai/26) com policy de tiers + accelerator + approval workflow. Eliana[E] sai da planilha paralela.

### Entregas

| US | Feature | Estimate |
|----|---------|----------|
| US-COMM-005 | Tiers escalonados (per user OU per policy) — schema + UI + resolver service | 2d |
| US-COMM-006 | Metas mensais + accelerator + cron daily achieved update | 2d |
| US-COMM-012 | Approval workflow + audit trail (Spatie permissions + AuditLog) | 2d |
| Canary 7d ROTA LIVRE | F5 cutover process ([ADR 0104 F5](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) + monitor diário | 7d corridos (não trabalho contínuo) |

**Total Fase 3: ~6d trabalho ativo + 7d canary observação.**

### Gates Fase 3

- [ ] Aviso prévio Larissa (cliente ROTA LIVRE) 7 dias ANTES
- [ ] Monitor diário cliente durante canary: 0 reclamações + 0 divergências valor
- [ ] Eliana[E] aprova: fechamento de mai/26 bate ±0% com planilha manual (sanity check)
- [ ] Métrica: tempo Eliana[E] fechar mês cai de ~3h pra <30min
- [ ] Métrica: 0 clawbacks "errados" no mês (ex: cancelar venda paga ≠ disputed manual)

### Bloqueadores potenciais

- ROTA LIVRE precisa concordar canary 7d (Wagner negocia direto com Larissa)
- Eliana[E] estudo LGPD ainda em andamento — sem DPO formal, comissão (PII salário) pode exigir advisor externo se Larissa pedir
- Hostinger SSH flaky pode atrasar deploys ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md))

---

## Fase 4 — Verticais extras + mobile + migration legacy (P2/P3, 1-2 cycles)

**Goal outcome:** OficinaAuto pronto pra ativar quando sinal qualificado chegar. Marketplaces integrado. Mobile vendedor self-service. Histórico UPos retroagido.

### Entregas (escolher conforme sinal cliente — não tudo)

| US | Feature | Estimate | Trigger |
|----|---------|----------|---------|
| US-COMM-010 | Multi-mecânico Repair/OficinaAuto split apontamentos | 3d | Sinal cliente OficinaAuto chega ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |
| US-COMM-011 | Comissão sobre líquido marketplace (após taxa ML/iFood) | 2d | Modules/Marketplaces existir com `marketplace_net_amount` |
| US-COMM-013 | Mobile self-service vendedor (PWA `/comissao/minhas`) | 2d | Vendedor delegado pede (não Larissa-dona) |
| US-COMM-014 | Migração legacy `users.cmmsn_percent` + `transactions.commission_agent` (backfill 90d) | 2d | Reclamação cliente "perdi histórico" — ainda não houve |

**Total Fase 4: 2-9d dependendo de quais ativar.**

### Gates Fase 4

- Cada US ativada precisa sinal qualificado: cliente paga + reporta OU métrica detecta drift ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
- US-COMM-014 (migration) só se cliente reclamar — não retroagir tudo proativamente (custo > benefício hoje)

### Bloqueadores potenciais

- Dependências externas (Modules/Marketplaces backlog)
- Sem sinal cliente → feature fica em backlog ADR feature-wish

---

## Timeline visual (proposta inicial — sujeita a re-priorização)

```
Cycle N (1 cycle)
├─ Fase 1 — Fundação P0 (9d)
│  ├─ US-COMM-001 schema
│  ├─ US-COMM-002 side-effect calcular
│  ├─ US-COMM-003 clawback
│  ├─ US-COMM-007 close mensal
│  └─ Pest baseline
│
Cycle N+1 (1 cycle)
├─ Fase 2 — UI MWART + ComVis (9d)
│  ├─ US-COMM-004 policy UI
│  ├─ US-COMM-009 multi-papel
│  └─ US-COMM-008 relatório UI
│
Cycle N+2 (1 cycle + 7d observação)
├─ Fase 3 — Tiers/Goals/Workflow (6d + canary)
│  ├─ US-COMM-005 tiers
│  ├─ US-COMM-006 metas + accelerator
│  ├─ US-COMM-012 approval workflow
│  └─ 🚨 Canary 7d ROTA LIVRE
│
Cycle N+3+ (variable, sinal-driven)
└─ Fase 4 — Extras (2-9d à demanda)
   ├─ US-COMM-010 (Repair/Oficina) - quando sinal
   ├─ US-COMM-011 (marketplace) - quando Modules/Marketplaces existir
   ├─ US-COMM-013 (mobile) - quando vendedor delegado pedir
   └─ US-COMM-014 (migration) - se reclamarem
```

**Estimate total caminho P0-P1 (Fases 1-3): ~24d trabalho ativo + 7d canary = ~5-6 semanas calendário.**

---

## Métricas pra cada fase

| Fase | Métrica | Como medir | Sucesso |
|------|---------|------------|---------|
| 1 | Schema operante | `php artisan tinker` testar fluxo end-to-end biz=1 | Side-effect cria assignments corretos |
| 1 | Pest cobertura | `php artisan test --filter=Comissao` | 100% verde, ≥5 testes |
| 2 | UI usável Larissa | Smoke browser MCP biz=4 (review-only) | Wagner aprova SCREENSHOT |
| 2 | Multi-papel funcional | Cenário B SPEC §2 ponta-a-ponta | 3 assignments criados corretos |
| 3 | Tempo Eliana[E] fechar mês | Medir antes (planilha) vs depois (artisan + UI) | <30min vs ~3h hoje |
| 3 | Divergência valor pago | Diff entre planilha Eliana atual e novo cálculo | ±0% no canary |
| 3 | Clawback acurácia | 0 disputes incorretos | Larissa não reclama |
| 4 | Sinal cliente vertical | Cliente OficinaAuto paga + reporta uso | ADR 0105 critério atendido |

---

## Riscos cross-fase

| Risco | Mitigação | Owner |
|-------|-----------|-------|
| Comissão é dinheiro → erro = trust break com cliente | Canary 7d obrigatório + dry-run obrigatório + sanity check Eliana[E] | [W] |
| Mudança POS form pra escolher designer/instalador pode quebrar fluxo Larissa | Feature flag per business: `commission_multi_role_enabled` (default false biz=4 até Fase 2 testada) | [W] |
| CLT Art. 466 / 477 (comissão é salário diferido) — risco trabalhista se errar | Eliana[E] valida + counsel externo se Larissa pedir | [E] |
| Drift entre policy nova e legacy UPos confunde relatórios antigos | ReportController legacy continua funcionando 6 meses após Fase 1 (deprecation aviso UI) | [F] |
| Sem sinal cliente vertical → Fase 4 fica indefinida | OK — ADR 0105 backlog ADR feature-wish é processo correto | [W] |
