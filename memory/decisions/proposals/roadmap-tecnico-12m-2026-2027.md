# Roadmap técnico 12 meses oimpresso — jun/2026 → mai/2027

> **Status:** PROPOSTA — não aceita até Wagner aprovar.
> **Autor:** VP Eng + product strategist (Claude, sessão 2026-05-09)
> **Substitui:** N/A (primeiro roadmap consolidado pós-Constituição v2)
> **Refs:** ADR 0094 (Constituição v2), ADR 0105 (cliente sinal), ADR 0106 (10x IA-pair), ADR 0094 §6 (multi-tenant Tier 0)

---

## Premissas

- **Time fixo de 5 pessoas.** Wagner [W] + Maiara [M] + Felipe [F] + Luiz [L] + Eliana [E]. Nenhum hire planejado nos 12m. Mudar isso = nova ADR (ex.: contratar BDR ou +1 dev).
- **Fator 10x IA-pair em codáveis** (ADR 0106). NÃO se aplica a:
  - Wallclock cliente (canary 7d, monitor 30d são relógio do mundo real)
  - Smoke fiscal real SEFAZ (depende de vendas reais + SEFAZ-SC respondendo)
  - Vendas / discovery / demo (humano-limitado)
  - Aprovação Wagner (1 humano, WIP máx 2)
- **Compromisso comercial:** 2º + 3º cliente ERP em 90d (até ~set/2026), 5 clientes em 12m (até mai/2027)
- **Cliente como sinal qualificado** (ADR 0105): backlog só recebe item se cliente paga + reporta OU métrica detecta drift. Hipótese sem sinal = ADR feature-wish, não US ativa
- **Tasks fora do roadmap viram ADR feature-wish** até cliente pedir (App mobile, DAM nativo, IoT, BI próprio, NFSe, MDFe)
- **Cycle = 2 semanas.** 26 cycles/ano. Roadmap cobre **24 cycles** (2 semanas de buffer férias dez/2026 + 2 mai/2027)
- **WIP máx Wagner=2, Felipe=2, Maiara=2, Luiz=1, Eliana=1.** Cycle Goals respeitam isso.

---

## Estrutura: 6 milestones × 2 cycles cada (12 cycles operacionais — restantes 12 são execução continuada dentro dos milestones)

### M1 (jun/2026) — Goal: Smoke fiscal real + saúde tech

**Cycle 25 (16-29 jun)**
- **Goal primary:** Smoke SEFAZ NFC-e biz=1 emitida em homologação (CYCLE-02 carryover)
- **Goal secondary:** Bug crítico wipe-DB fix + tests órfãos Ponto+ADS no `phpunit.xml` + `adr-lint required` no CI
- **Owner:** [W] (smoke SEFAZ + flag .env Hostinger), [F] (phpunit.xml + adr-lint)
- **Esforço IA-pair:** smoke ~4h wallclock (humano-limitado SEFAZ), bugs/tests ~8h codáveis = ~1h IA-pair
- **KPI exit:** 1 NFC-e cstat=100 em homologação biz=1 + CI verde com adr-lint

**Cycle 26 (30 jun-13 jul)**
- **Goal primary:** mwart-gate HÍBRIDO live (warning-only 14d → enforce)
- **Goal secondary:** KPI D+30 medido (uptime, p95 CI, bugs abertos, multi-tenant isolation)
- **Owner:** [F] (mwart-gate workflow), [W] (review)
- **Esforço IA-pair:** ~12h codáveis = ~1.2h IA-pair
- **KPI exit:** mwart-gate enforce + dashboard saúde rodando daily

**KPI M1:**
- 0 bugs críticos abertos (era 2-3 fim de mai/2026)
- 1 NFC-e real emitida em homologação biz=1 (destrava biz=4 cliente em M2)
- mwart-gate HÍBRIDO em enforce (não mais aviso)
- Cobertura tests módulos críticos ≥70% (NfeBrasil, Repair, Jana)

**Capacidade alocada:** 95% técnica, 5% pré-vendas (Wagner conversa com prospect quente — pipeline)

---

### M2 (jul/2026) — Goal: 2º cliente ERP + API docs MVP + smoke fiscal prod biz=4

**Cycle 27 (14-27 jul)**
- **Goal primary:** API docs Swagger MVP (16-24h IA-pair = ~2-3h wallclock real, 1-2 dias úteis)
- **Goal secondary:** Smoke NFC-e prod biz=4 (ROTA LIVRE) — depende de M1 verde
- **Owner:** [F] (Swagger), [W] + [E] (smoke biz=4 com Larissa)
- **Esforço:** Swagger ~2h IA-pair, smoke biz=4 wallclock ~1d (humano-limitado)
- **KPI exit:** `/api/docs` público + 1 NFC-e biz=4 em prod

**Cycle 28 (28 jul-10 ago)**
- **Goal primary:** 1ª demo paga 2º cliente (1 prospect SP) + ajustes pós-discovery
- **Goal secondary:** ADS Universal MVP (S5 ativando ads-route Tier A — ADR 0106 antecipou pra ~30 mai)
- **Owner:** [W] (demo), [F] + [L] (ADS + ajustes pós-discovery)
- **Esforço:** demo wallclock 4-8h (humano), ADS ~12h IA-pair = ~1.5h
- **KPI exit:** 2º contrato assinado OU pipeline qualificado 5+ leads quentes

**KPI M2:**
- 2º cliente ERP assinou (era 1 = ROTA LIVRE → 2 com novo cliente SP)
- API docs Swagger pública em `oimpresso.com/api/docs`
- ADS Universal Tier A ativada via `decide()` em domínios críticos
- 1 NFC-e real prod biz=4 (Larissa emitiu 1 nota legal)

**Capacidade alocada:** 60% técnica, 40% vendas/onboarding

---

### M3 (ago-set/2026) — Goal: 3º + 4º cliente + onboarding playbook

**Cycle 29 (11-24 ago)**
- **Goal primary:** Onboarding playbook canônico (RUNBOOK 1-cliente-novo) — checklist tenant, NFC-e cert, treinamento Larissa-style
- **Goal secondary:** 3º cliente discovery + assinatura
- **Owner:** [E] + [W] (playbook), [W] (vendas)
- **Esforço:** playbook ~16h codáveis (~2h IA-pair) + vendas wallclock 1 semana

**Cycle 30 (25 ago-7 set)**
- **Goal primary:** 3º cliente onboarded (canary 7d wallclock real)
- **Goal secondary:** Comparativos competitivos atualizados (Mubisys, Zênite, Calcgraf 2026)
- **Owner:** [E] (onboarding), [F] + [W] (comparativos)
- **Esforço:** onboarding wallclock 7d + comparativos 8h IA-pair = ~1h

**Cycle 31 (8-21 set)**
- **Goal primary:** 4º cliente discovery + assinatura
- **Goal secondary:** Bulk update Jana (sinal? aguardar M2 feedback de cliente)
- **Owner:** [W] (vendas), [F] (Jana se sinal)

**Cycle 32 (22 set-5 out)**
- **Goal primary:** 4º cliente onboarded
- **Goal secondary:** Pricing tiers validados em 4 contratos (R$ 299/599/1.499 — testar premium tem demanda)
- **Owner:** [E] (onboarding), [W] (pricing review)

**KPI M3:**
- 4 clientes ERP ativos (era 1 → 4) — bate compromisso 90d (2 + 3) e antecipa 12m
- ARR rodando ≥R$ 30k (4 × ~R$ 600 médio × 12)
- Churn = 0
- Onboarding p95 ≤14 dias (canary 7d + 7d ajustes)

**Capacidade alocada:** 50% técnica, 50% vendas/onboarding

---

### M4 (out-nov/2026) — Goal: Hardening + endorsement setorial + decisão DAM

**Cycle 33 (6-19 out)**
- **Goal primary:** ABICOMV outreach (endorsement setorial — comunicação visual)
- **Goal secondary:** NFSe MVP (sinal? só se 2+ clientes pediram em M3)
- **Owner:** [W] (ABICOMV), [F] (NFSe se sinal)

**Cycle 34 (20 out-2 nov)**
- **Goal primary:** Tech debt cleanup — Form:: shim migration parcial (~1k chamadas das 6.4k), procedure_drift = 0
- **Goal secondary:** Decisão DAM revisitada (waiting-list 3 clientes Enterprise pagos? GO/NO-GO formal)
- **Owner:** [F] (shim), [W] (decisão DAM)

**Cycle 35 (3-16 nov)**
- **Goal primary:** App mobile decisão (sinal? 3+ clientes pediram?) — se SIM, RFC; se NÃO, ADR feature-wish formal
- **Goal secondary:** Hardening security (audit OWASP top 10 nos módulos críticos)
- **Owner:** [W] (decisão), [F] + [L] (security audit)

**Cycle 36 (17-30 nov)**
- **Goal primary:** Performance hardening — p95 endpoints críticos ≤300ms
- **Goal secondary:** Backup/DR runbook validado (restore real biz=2 staging)
- **Owner:** [F] (perf), [E] (DR runbook)

**KPI M4:**
- Endorsement ABICOMV em discussão formal (não fechado ainda — wallclock setorial)
- Form:: shim ≤5k chamadas (era 6.4k)
- DAM decisão GO/NO-GO documentada em ADR
- Backup/DR validado em restore real

**Capacidade alocada:** 70% técnica (hardening), 30% comercial/setorial

---

### M5 (dez/2026 - jan/2027) — Goal: Diferenciação Enterprise (DAM nativo OU integração)

> **Gate:** este milestone só executa se M3+M4 entregaram 3 contratos Enterprise pagos (waiting-list DAM atingida). Se NÃO, M5 vira "consolidação 5º cliente + Mubisys-migration prep".

**Cycle 37 (1-14 dez) — buffer férias 22dez-5jan**
- **Goal primary:** Se DAM-GO: arquitetura DAM nativo (RFC + ADR). Se DAM-NO-GO: 5º cliente discovery
- **Owner:** [W] + [F] (RFC), ou [W] (vendas)

**Cycle 38 (15-28 dez)** — capacidade reduzida (recesso parcial)
- **Goal primary:** Tech debt + bug bash + retro 2026
- **Goal secondary:** Planning 2027 H1
- **Owner:** todos (cycle leve)

**Cycle 39 (5-18 jan)**
- **Goal primary:** Se DAM-GO: implementação core DAM (upload + tagging + busca) ~80h codáveis = ~10h IA-pair (2 semanas)
- **Goal secondary:** 5º cliente onboarded
- **Owner:** [F] + [L] (DAM), [E] (5º cliente)

**Cycle 40 (19 jan-1 fev)**
- **Goal primary:** Se DAM: integração com Repair (anexar arte ao OS) + smoke biz=4
- **Goal secondary:** Mubisys-migration kit (importer + mapping CSV→oimpresso) — preparação M6
- **Owner:** [F] (DAM Repair), [E] + [L] (importer)

**KPI M5:**
- Se DAM-GO: DAM nativo MVP em prod biz=4 + 1 cliente Enterprise usando
- Se DAM-NO-GO: integração Bynder/Cloudinary documentada como alternativa
- 5 clientes ERP ativos (compromisso 12m batido — antecipado se M3 entregou 4)
- Mubisys-migration importer testado em fixture real

**Capacidade alocada:** 80% técnica (DAM ou Mubisys-prep), 20% suporte clientes

---

### M6 (fev-mai/2027) — Goal: Endorsement setorial + Mubisys-migration ready + 6º+ clientes

**Cycle 41 (2-15 fev)**
- **Goal primary:** Endorsement ABICOMV fechado (carta + co-marketing)
- **Goal secondary:** Mubisys-migration importer validado em 1 cliente real
- **Owner:** [W] (ABICOMV), [E] + [F] (importer)

**Cycle 42 (16 fev-1 mar)**
- **Goal primary:** 6º cliente (1º via Mubisys-migration) onboarded
- **Goal secondary:** Marketing setorial (case study ROTA LIVRE + 2º cliente)
- **Owner:** [E] (onboarding), [W] + [E] (case study)

**Cycle 43 (2-15 mar)**
- **Goal primary:** Charters S4 entregue (`charter-fetch` MCP + `charter-first` Tier A ativada)
- **Goal secondary:** 7º cliente discovery
- **Owner:** [F] (charter-fetch), [W] (vendas)

**Cycle 44 (16-29 mar)**
- **Goal primary:** 7º cliente onboarded
- **Goal secondary:** Pricing review (validar tiers com 7 contratos — adjust premium se take-rate baixo)
- **Owner:** [E] (onboarding), [W] (pricing)

**Cycle 45 (30 mar-12 abr)**
- **Goal primary:** Hardening pré-escala (deploy CT 100 redundância? PMM monitor MySQL? RDS migration RFC?)
- **Goal secondary:** Bulk update Jana V2 (sinal cliente)
- **Owner:** [F] (infra), [F] (Jana)

**Cycle 46 (13-26 abr)**
- **Goal primary:** Retro anual + planning H2 2027
- **Goal secondary:** Documentação completa do produto pra novos devs (onboarding kit time ≥6 pessoas se hire decisão)
- **Owner:** todos

**KPI M6:**
- 7 clientes ativos (excedeu compromisso 5 em 12m — pulled-forward por M3 entregar 4)
- ARR ≥R$ 100k (validar com pricing tiers — ADR proposals/06)
- Endorsement ABICOMV fechado (carta + 1 co-marketing post)
- Case study publicado (ROTA LIVRE depoimento)
- Charter S4 ativada em ≥10 telas críticas

**Capacidade alocada:** 60% técnica, 40% comercial/setorial

---

## Dependências críticas

| De | Pra | Razão |
|---|---|---|
| M1 saúde tech | M2 API docs | Não documentar com bugs ativos. Swagger expõe API que precisa estar estável |
| M2 smoke fiscal prod | M3 3º cliente | NFC-e prod biz=4 funcionando = pitch de vendas com proof real |
| M3 4 clientes | M4 ABICOMV | ABICOMV só endossa com >3 clientes ativos no setor |
| M4 decisão DAM | M5 execução DAM | Sem 3 Enterprise pagos = DAM adiado infinito (ADR 0105) |
| M5 Mubisys importer | M6 6º cliente Mubisys | Importer = unlock pra clientes legacy Delphi/Mubisys |
| Wagner aprovar tudo | M1-M6 | Wagner WIP máx 2 — bottleneck conhecido |

---

## Tabela master por cycle

| Cycle | Datas | Goal primary | Owner | h IA-pair | Wallclock dias | KPI exit |
|---|---|---|---|---|---|---|
| 25 | 16-29 jun/26 | Smoke SEFAZ biz=1 + tests órfãos | W+F | ~1h codáveis + 4h wallclock SEFAZ | 5d | 1 NFC-e cstat=100 |
| 26 | 30 jun-13 jul | mwart-gate enforce + KPI D+30 | F+W | ~1.2h | 3d | dashboard saúde live |
| 27 | 14-27 jul | API docs Swagger MVP | F | ~2h | 2d | /api/docs público |
| 28 | 28 jul-10 ago | 2º cliente assinou | W | wallclock 5-10d | 10d | contrato assinado |
| 29 | 11-24 ago | Onboarding playbook | E+W | ~2h + 5d wallclock | 7d | RUNBOOK validado |
| 30 | 25 ago-7 set | 3º cliente onboarded | E | wallclock 7d canary | 7d | canary verde |
| 31 | 8-21 set | 4º cliente discovery | W | wallclock 5-10d | 10d | contrato 4º |
| 32 | 22 set-5 out | 4º cliente onboarded | E | wallclock 7d | 7d | canary verde |
| 33 | 6-19 out | ABICOMV outreach | W | wallclock 10d | 10d | reunião agendada |
| 34 | 20 out-2 nov | Form shim parcial + DAM decisão | F+W | ~3h shim + decisão | 5d | shim ≤5k + ADR DAM |
| 35 | 3-16 nov | App mobile decisão + security audit | W+F+L | ~2h audit + decisão | 5d | ADR mobile + audit OWASP |
| 36 | 17-30 nov | Perf hardening + DR | F+E | ~3h + DR run | 7d | p95 ≤300ms + DR ok |
| 37 | 1-14 dez | DAM RFC OR 5º cliente | W+F | ~2h ou wallclock | 7d | RFC ou contrato |
| 38 | 15-28 dez | Bug bash + retro | todos | ~5h | 5d (recesso) | 0 bugs P1 |
| 39 | 5-18 jan/27 | DAM core OR 5º onboarded | F+L+E | ~10h DAM ou wallclock | 10d | DAM MVP ou canary |
| 40 | 19 jan-1 fev | DAM Repair integ + Mubisys importer | F+E+L | ~6h + ~5h | 10d | DAM em biz=4 + importer test |
| 41 | 2-15 fev | ABICOMV fechado + importer real | W+E+F | wallclock 10d + ~3h | 10d | carta + 1 import real |
| 42 | 16 fev-1 mar | 6º cliente Mubisys + case study | E+W | wallclock 7d + ~4h | 10d | 6º onboarded |
| 43 | 2-15 mar | charter-fetch S4 entregue | F | ~5h | 5d | tool MCP live |
| 44 | 16-29 mar | 7º cliente + pricing review | E+W | wallclock 7d + ~2h | 10d | 7º + pricing v2 |
| 45 | 30 mar-12 abr | Infra hardening pré-escala | F | ~6h | 7d | RFC infra escala |
| 46 | 13-26 abr | Retro anual + plan H2 27 | todos | ~5h | 5d | plano H2 |

---

## Capacidade do time (estimativa — validar com Wagner)

| Pessoa | % técnico | % suporte | % comercial | Notas |
|---|---|---|---|---|
| **Wagner** [W] | 60% | 0% | 40% | Bottleneck conhecido — delegar review pra Felipe quando puder |
| **Felipe** [F] | 90% | 10% | 0% | Líder técnico de fato. Owner mwart-gate, Swagger, infra |
| **Maiara** [M] | 50% | 50% | 0% | Suporte clientes existentes + dev features médias |
| **Luiz** [L] | 100% | 0% | 0% | Iniciante IA-pair — pareado Felipe/Wagner. WIP máx 1 |
| **Eliana** [E] | 30% | 0% (financeiro 50% + comercial 20%) | 20% | Financeiro 50%, comercial 20%, dev 30%. Onboarding owner |

**Total dev capacity:**
- Sem IA-pair: ~25h dev/semana (estimativa conservadora time inteiro)
- Com IA-pair (10x em codáveis): ~250h dev-equiv/semana **em tarefas codáveis**
- Wallclock continua relógio do mundo real em smoke/canary/vendas

**Reservas:**
- 20% buffer pra incidentes prod (ROTA LIVRE 99% volume — qualquer P1 lá pausa roadmap)
- 10% buffer pra cliente sinal qualificado novo entrar (ADR 0105)

---

## Riscos do roadmap (top 8)

1. **M1 — Smoke SEFAZ falhar persistente** (cert vencido, regime errado, SEFAZ-SC down): atrasa M2 e M3 inteiros (sem proof fiscal = sem pitch). **Mitigação:** Wagner roda smoke ANTES de M1 começar (ver §Top 3 ações pré-M1).
2. **M3 — 3º cliente não aparecer**: revisar GTM, pode adiar M3 fim em 1 cycle. **Mitigação:** pipeline 5+ leads em pré-vendas durante M2 (capacidade Wagner 40% comercial).
3. **M5 — 3 contratos Enterprise não chegarem**: DAM adiado infinito → Mubisys gap perpetua → 6º+ clientes Mubisys-legacy ficam fora. **Mitigação:** decisão GO/NO-GO em M4 cycle 34 com critério explícito (≥3 contratos Enterprise pagos = GO).
4. **Wagner virar bottleneck** (já regra dura no projeto, WIP 2): M3-M6 dependem de Wagner em vendas E review técnico simultâneos. **Mitigação:** delegar code review pra Felipe em ≥80% PRs durante M3-M6.
5. **Eliana/Maiara saem (BCP?)**: time de 5 vira 4, capacidade onboarding (Eliana) ou suporte (Maiara) some. **Mitigação:** documentação onboarding playbook M3 cycle 29 cobre Eliana; Felipe pode absorver suporte temporário.
6. **ROTA LIVRE incidente P1** durante M3-M5: pausa roadmap por 1-2 cycles. **Mitigação:** 20% buffer reservado + DR validado M4 cycle 36.
7. **Mubisys-migration importer falhar em produção real** (M6): sem 6º+ clientes legacy = ARR não bate R$ 100k. **Mitigação:** validar importer em fixture sintética M5 cycle 40 antes de cliente real M6.
8. **ABICOMV não responde / não endossa**: sem co-marketing setorial = aquisição vira CAC ↑↑. **Mitigação:** outreach paralelo Sebrae-SP, ABRAGRAF, Sindigraf-SP em M4-M6.

---

## Backlog ADR-feature-wish (não no roadmap até sinal)

Itens identificados nos playbooks de migração mas SEM sinal qualificado (ADR 0105):

- **App mobile nativo iOS/Android** — sem 3+ pedidos de cliente pagante = NÃO entra no roadmap. ADR feature-wish em M4 cycle 35
- **DAM nativo (Bynder-like)** — sem 3 contratos Enterprise pagos = NÃO entra (decisão M4 cycle 34)
- **IoT máquinas (sensores plotter/laser)** — sem 1 cliente pedindo + pagando POC = NÃO entra
- **BI próprio (dashboard self-service)** — sem 2 pedidos de cliente pagante = NÃO entra. Stop-gap: Metabase/Superset integração
- **NFSe nacional** — depende de adoção Padrão Nacional 2026 (relógio mundo real do governo). Em sinal cliente, entra em M4 cycle 33
- **MDFe (manifesto eletrônico)** — só relevante pra clientes com transporte próprio. Sem sinal cliente = NÃO entra
- **Bulk update Jana V2** — depende de feedback uso V1. Entra em M6 cycle 45 se sinal de M3-M4 confirmar
- **Multi-idioma EN/ES** — sem cliente internacional = NÃO entra. Roadmap H2 2027 se expansão Mercosul

---

## KPIs business 12 meses

| KPI | Baseline mai/26 | Meta mai/27 | Validar |
|---|---|---|---|
| **Clientes ativos** | 1 (ROTA LIVRE) | 5 (compromisso) → 7 (stretch) | `business` table biz com tx últimos 30d |
| **ARR** | ~R$ 0 (assumindo Larissa não paga assinatura formal hoje) | R$ 100k+ | financeiro/asaas |
| **Churn** | 0 | 0 | retenção 12m |
| **NPS** | sem medição | ≥40 | survey trimestral M3+M5+M6 |
| **Endorsement setorial** | 0 | 1 (ABICOMV) | carta + co-marketing post |
| **Tier mix** | n/a | 50% Pro / 30% Standard / 20% Premium | pricing tiers (R$ 299/599/1.499) |
| **Take-rate fiscal** | 0% | ≥30% clientes usando NFC-e auto | telemetria NfeBrasil |
| **CAC** | sem medição | ≤3 meses payback | Wagner tempo + ferramentas |

---

## KPIs tech 12 meses

| KPI | Baseline mai/26 | Meta mai/27 |
|---|---|---|
| **Cobertura tests módulos críticos** (NfeBrasil, Repair, Jana, Financeiro) | ~60% | ≥80% |
| **p95 CI** | ~5min | ≤3min |
| **Uptime prod** (oimpresso.com) | sem medição formal | ≥99.5% |
| **Bugs críticos abertos (P1)** | 2-3 | ≤2 sustentado |
| **ADRs aceitas (canon ativas)** | ~119 (até 0119) | +10 (~129) |
| **Score Lighthouse** Pages MWART migradas | sem medição | ≥85 |
| **multi_tenant_isolation check** | passing | passing 100% (zero leak) |
| **procedure_drift** | passing | 0 drifts em 12m |
| **mwart-gate enforce** | warning-only | enforce + 100% PRs Pages têm RUNBOOK |
| **Charters live (S4)** | 0 | ≥10 telas críticas |
| **MCP server uptime** | sem medição | ≥99% |
| **Form:: shim chamadas** | ~6.4k | ≤4k (migration parcial) |

---

## Lifecycle desta proposta

- **Status atual:** PROPOSTA (não aceita)
- **Aprovação:** Wagner aprova em sessão dedicada — esperado decisão até 13/jun/2026 (último dia útil antes de M1 começar 16/jun)
- **Após aprovação:** vira ADR canon `memory/decisions/01XX-roadmap-12m-2026-2027.md` com lifecycle `accepted`
- **Revisão:** review formal a cada 2 milestones (M2 fim ago/26, M4 fim nov/26, M6 fim mai/27)
- **Mudanças mid-flight:** se cycle entrega ≠ planejado por >2 cycles consecutivos, abre nova ADR `supersedes:` e replaneja milestones afetados

---

**Última atualização:** 2026-05-09 (sessão Claude — VP Eng + product strategist roleplay).
