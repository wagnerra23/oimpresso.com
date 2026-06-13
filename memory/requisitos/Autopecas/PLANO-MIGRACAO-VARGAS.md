# Plano Migração Vargas → Autopecas (planejado — não existe) — 2026-05-10

> **Autor:** Claude (sub-agent migration engineer + customer success) sob direção Wagner [W]
> **Status:** `draft` — Wagner valida antes de qualquer outreach
> **Tier:** plano operacional (não ADR), subordinado a [ADR 0125](../../decisions/0125-modules-autopecas-feature-wish.md) (Autopecas (planejado — não existe) feature-wish) + [ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md) (Migration Factory) + [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) (modular por vertical) + [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (sinal qualificado)
> **Pasta canônica:** `memory/requisitos/Autopecas/` — primeiro doc operacional do módulo (vertical em feature-wish)
> **Origem:** removido do `PLANO-MIGRACAO-6-SAUDAVEIS.md` (Modules/ComunicacaoVisual) após Wagner confirmar 2026-05-10 que Vargas é **autopeças** (CNAE 4530-X), não comunicação visual

---

## Sumário executivo

- **Cliente alvo:** Vargas (saudável OfficeImpresso, build Delphi versão 1468)
- **GMV anual snapshot Insights:** **R$ [redacted Tier 0]M/ano** (maior dos 7 saudáveis WR Sistemas)
- **Receita atual oimpresso:** **R$ [redacted Tier 0]** — Vargas paga **mensalidade WR Sistemas legacy estimada R$ [redacted Tier 0]-850/m** (não confirmado por banco; estimativa baseada em tier OfficeImpresso completo + 26 anos relação)
- **Receita alvo pós-migração 12m:** R$ [redacted Tier 0]k/ano (Enterprise pioneer R$ [redacted Tier 0]/m × 12) + setup R$ [redacted Tier 0] + add-ons potenciais
- **Esforço migração:** ~24h IA-pair Felipe [F] + 12h Wagner [W] (calls + decisão + presencial) + 4h Maiara [M] (suporte L1 + treinamento) = **~40h total** ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) recalibração 10x já aplicada)
- **ROI alvo:** 1 cliente Enterprise R$ [redacted Tier 0]/m × 12 = **R$ [redacted Tier 0]k/ano novo** (cap teórico Enterprise+ R$ [redacted Tier 0]-100k/ano se add-ons + multi-business)
- **Capacidade time:** ~40h ao longo de 6m = **~7h/m** = sustentável sem comprometer Sprint principal
- **Gatilho ADR 0125:** Vargas assinatura pioneer **DESBLOQUEIA** Autopecas (planejado — não existe) `feature-wish` → `em_construcao`. Sem assinatura, módulo permanece dormente

### Premissa-chave (declarada — Wagner valida)

Receita atual WR Sistemas é **estimativa**, não dado confirmado. Pra fechar essa lacuna: rodar [`officeimpresso-financial-snapshot`](../Officeimpresso/RUNBOOK-financial-snapshot-cliente.md) skill no banco Firebird Vargas antes da abordagem — extrai ticket pago, recência último update Delphi, sinais de churn. **Sem snapshot = chute, não plano** (Wagner exigência 2026-05-10).

---

## Histórico Vargas (contexto pra abordagem)

### Relação Wagner ↔ Vargas

- **26 anos relação** — Vargas é cliente fundador WR Sistemas (1999-2000 era primeiros), antes mesmo do produto OfficeImpresso atual
- **Wagner conhece dono direto** — telefone, WhatsApp, encontros pessoais ao longo de duas décadas
- **Histórico de chamados/upgrades** — Vargas paga upgrades Delphi quando libera (build 1468 sugere paid upgrades históricos, embora não a versão mais recente 1474 do parque)
- **Confiança alta**, baixa hostilidade

### Status atual técnico (2026-05-10)

- ✅ saudável OfficeImpresso (Delphi versão 1468, banco em servidor remoto Wagner)
- ⚠️ **build antigo** que NÃO chama backend Connector ([reference_delphi_wr_comercial.md](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/reference_delphi_wr_comercial.md)) — sinal de cliente que não atualiza Delphi recentemente (~2 anos sem upgrade)
- Razão social provável: "Vargas Acessorios" / "Vargas Jato de Granalha" — banco no registry "Jardel Acessorios" sugere CNAE 4530-X autopeças/acessórios
- **Vertical confirmado:** autopeças (Wagner 2026-05-10 textualmente: *"autopecas"*)
- **Localização:** a confirmar via banco quando 192.168.0.55 voltar online (servidor remoto Vargas)

### GMV e financeiro

- **GMV ano anterior:** R$ [redacted Tier 0]M/ano (snapshot Insights — fonte: Wagner reportou)
- **30% do agregado** dos 7 saudáveis OfficeImpresso top
- **Receita histórica WR Sistemas (estimativa):** ~R$ [redacted Tier 0]-850/m
  - Baseline: tier OfficeImpresso completo, multi-usuário, 26 anos relação
  - **Confirmar via** `firebird_query` no banco Vargas: `SELECT * FROM CONFIGURACOES WHERE CONFIG LIKE '%LICENCA%'` ou `RUNBOOK-financial-snapshot` extraindo extrato pago Wagner→Vargas

---

## Riscos específicos Vargas

- 🔴 **Maior cliente saudável WR Sistemas — perda massiva.** R$ [redacted Tier 0]M GMV é 30% do agregado. Se migração der ruim, churn de Vargas inviabiliza Autopecas (planejado — não existe) como vertical comercial + abala 26 anos relação
- 🔴 **Build Delphi desatualizado** = cliente conservador, não corre pra novidade. Resistência natural a mudança. Pode interpretar "novo sistema" como "mais um upgrade que não preciso"
- 🟡 **Vertical confirmado mas detalhe a aprofundar** — "autopeças" é amplo (varejo balcão? atacado? marketplace?). Confirmar via banco antes de positionar produto
- 🟡 **Concorrência potencial** — Auto Manager / Lokoz / Linx Microvix podem ter mapeado Vargas como prospect (R$ [redacted Tier 0]M GMV é alvo natural mid-market)
- 🟡 **Localização sensível** — se Vargas for fora SC (provável SP/MG/RJ), suporte presencial = viagem Wagner. Se SC ou perto, presença mensal possível
- 🟢 **Histórico longo Wagner-Vargas** = ativo de relacionamento que Mubisys/Auto Manager/Lokoz não têm
- 🟢 **Wagner conhece dono** = call discovery não-cold, abertura natural

---

## Estratégia de abordagem

### Quem aborda

- **Wagner [W] direto** — relação 26 anos, **NÃO terceirizar pra SDR ou Maiara**
- Wagner agenda call discovery; Felipe [F] entra em call follow-up técnico se Vargas avança

### Quando

- **Q4/26 outubro-dezembro** — após Modules/ComunicacaoVisual ter Sprint 1 entregue + 1ª piloto comvisual estabilizado em prod (Q3/26)
- **Não antes** — sem Modules/ComunicacaoVisual piloto verde, demo Vargas frustra ("você diz que tem ERP mas só ROTA LIVRE vestuário existe")
- **Janela ideal:** **out-nov 2026** — Vargas ainda no clima de planejamento 2027, antes do recesso fim de ano BR

### Como (tática)

1. **Pré-call (semana -2):**
   - Snapshot financeiro Vargas via skill `officeimpresso-financial-snapshot` (1-2h Felipe [F] roda em banco Firebird remoto)
   - Wagner revisa snapshot: ticket pago real, recência update Delphi, sinais churn
   - Wagner prepara pitch personalizado baseado em snapshot (não genérico)

2. **Call discovery (60min Wagner direto):**
   - **NÃO Zoom genérico** — preferencial **presencial** se Vargas for SP/MG/RJ (Wagner viaja); senão Zoom **com câmera** (relação humana)
   - Pitch: *"Vargas, tô construindo um sistema novo que mantém todo seu histórico Delphi e adiciona NFC-e ágil em <500ms balcão, NFe-de-boleto-pago automática, IA conversacional respondendo 'qual peça pra Civic 2015 freio dianteiro'. Quer ser o piloto pioneiro autopeças? Setup zero, 50% off primeiros 6 meses, e quando virar case público, vídeo 90s da sua loja viraliza."*
   - Demo ROTA LIVRE (Vestuario live) + Modules/ComunicacaoVisual em prod (1ª piloto comvisual quando estabilizar)
   - Discovery questões: "qual sua maior dor hoje no Delphi?", "quanto tempo perde digitando NF?", "já pensou trocar pra Auto Manager/Lokoz?", "tem cliente B2B oficina pedindo WhatsApp?"

3. **Follow-up técnico (45min Felipe + Wagner):**
   - Migration Factory demo: como banco Firebird Vargas migra preservando 100% histórico
   - Strangler Fig + parallel run 30d explicado: Delphi continua read-only enquanto oimpresso vira write canônico, rollback grátis 30d
   - Roadmap Autopecas (planejado — não existe) Sprint 1 (4 meses) + commitment release date

4. **Decisão (semana +4 a +8):**
   - Vargas decide com cônjuge/sócio (provável) — dar tempo
   - Wagner faz check-in WhatsApp casual (não pressão)
   - Se sim: contrato escrito + assinatura

---

## Pacote oferecido (pioneer)

### Pacote pioneer Vargas

- **Setup R$ [redacted Tier 0]** (pioneer) — normalmente R$ [redacted Tier 0] Enterprise
- **Enterprise R$ [redacted Tier 0]/m grandfathered por 24m** + 50% off primeiros 6m
  - **Cálculo total ano-1:** (R$ [redacted Tier 0]/m × 6m) + (R$ [redacted Tier 0]/m × 6m) = R$ [redacted Tier 0] + R$ [redacted Tier 0] = **R$ [redacted Tier 0] ano-1**
  - **Cálculo total ano-2:** R$ [redacted Tier 0]/m × 12m = **R$ [redacted Tier 0] ano-2**
  - **Total 24m grandfathered:** R$ [redacted Tier 0]
- **Migração full** (Migration Factory pattern Strangler Fig + parallel run 30d Delphi+oimpresso lado a lado)
- **Autopecas (planejado — não existe) completo** quando entregue:
  - Catálogo SKU + tabela aplicação por veículo
  - Venda balcão p95<1500ms
  - NFC-e ágil + NFe-de-boleto auto
  - Tabela preço por categoria/montadora
  - Estoque mínimo + alertas
  - Devolução + garantia loja vs fabricante
  - Crediário interno B2B oficinas (30/60/90d)
  - WhatsApp consulta peça (Jana IA)
  - Multi-depósito se Vargas tiver filial
- **Jana IA ilimitada** com memória 26 anos histórico Vargas (extraído do banco Firebird)
- **Compromisso Vargas:** virar **case público** anonimizável (vídeo 90s + autoriza menção em battle card oimpresso)

### Pacote regular pós-pioneer (se Vargas indica oficinas parceiras)

- Setup R$ [redacted Tier 0] (50% off do regular R$ [redacted Tier 0])
- Enterprise R$ [redacted Tier 0]/m sem grandfathering
- Migration Factory completa

---

## Timing realista

| Período | Marco | Detalhe |
|---|---|---|
| **Q3/26 jul-set** | Modules/ComunicacaoVisual Sprint 1 entregue + 1ª piloto comvisual estabilizado | **gate-check** antes outreach Vargas |
| **Q4/26 out (semana 1-2)** | Snapshot financeiro Vargas + Wagner prepara pitch | 4-8h Felipe + 4h Wagner |
| **Q4/26 out (semana 3-4)** | Call discovery Wagner direto | 60min sync + 30min preparação Wagner |
| **Q4/26 nov** | Follow-up técnico + demo Autopecas (planejado — não existe) roadmap | 45min Felipe + Wagner |
| **Q4/26 dez** | Decisão Vargas (sim/não/postergar) | Vargas + sócio decidem |
| **Q1/27 jan** | Se SIM: assinatura contrato + ADR ativação Autopecas (planejado — não existe) (`feature-wish` → `em_construcao`) | Wagner aprova; Felipe scaffolda módulo |
| **Q1/27 jan-mar** | Autopecas (planejado — não existe) Sprint 1 (6 features mínimas US-AP-001..006) | ~10-12 dias úteis Felipe IA-pair + 6h Wagner aprovação |
| **Q1/27 mar** | Snapshot financeiro Vargas re-rodado + Pattern 07 dry-run banco Firebird local | 1-2 dias Felipe |
| **Q2/27 abr-mai** | Migração Vargas: cutover + parallel run 30d Delphi+oimpresso | 2-4 semanas wallclock + smoke biz Vargas + canary 7d |
| **Q2/27 jun** | Vargas estabilizado em prod + survey NPS D+30 | 2-4 calls Maiara |
| **Q3/27 jul-set** | Estabilização longa (D+30 a D+90) + Vargas autoriza case público | 1-2 calls Wagner |
| **Q3/27 set** | Case público publicado (vídeo 90s + landing `oimpresso.com/cases/vargas`) | 4-8h Wagner + designer externo |

**Total wallclock:** ~12 meses (out/26 → set/27) do primeiro outreach até case público publicado.

---

## Plano B se Vargas recusar

### Cenário: Vargas diz "não" ou "talvez ano que vem"

1. **NÃO churnar Vargas do OfficeImpresso por pressão** — princípio [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) "guiar sem mandar"
2. **Manter Vargas no OfficeImpresso, sem forçar** — releases manutenção legacy WR Sistemas continuam pago as-is
3. **Re-tentar em 12-18 meses** com case público de outro cliente migrado já estabilizado (idealmente 2º cliente autopeças OU Vargas vê ROTA LIVRE+ComunicacaoVisual prosperando)
4. **Autopecas (planejado — não existe) permanece `feature-wish`** até 2º cliente autopeças saudável sinalizar interesse (ADR 0125 §Trigger Cenário B)
5. **Registrar resposta de Vargas** em `memory/research/vargas-2026-Q4-outreach-resultado.md` pra calibrar abordagens futuras (o quê funcionou? o quê travou?)

### Cenário: Vargas pede tempo (>3 meses sem decisão)

1. Wagner check-in WhatsApp casual a cada 30-45d (não pressão)
2. Compartilhar 1 update significativo: "olha, ROTA LIVRE viralizou no Instagram", "Mubisys está dando problema com NFS-e nos clientes que migraram", etc
3. Após 6 meses sem decisão, pausar outreach + esperar Vargas trazer ele mesmo

### Cenário: Vargas migra pra concorrente (Auto Manager/Lokoz/Linx Microvix)

1. **Aceitar perda como dado de mercado** — não foi por preço, foi por timing/produto/posicionamento
2. Solicitar exit interview: "o que faltou no oimpresso pra ganhar você?"
3. Documentar em post-mortem `memory/research/2026-Q?-post-mortem-vargas.md` (mesmo padrão Gold Comunicação Mubisys)
4. **Autopecas (planejado — não existe) vira `historical`** se Vargas era único candidato qualificado (ADR 0125 §review_trigger #5: "12 meses sem sinal Vargas + sem 2º cliente autopeças")

---

## Migration Factory Vargas — checklist específico

Por baseado em [`memory/dominios/_patterns/`](../../dominios/_patterns/), executar checklist completo Vargas:

### Pré-migração (D-30 contractual)

- [ ] **Snapshot financeiro Vargas** skill `officeimpresso-financial-snapshot` rodada → arquivo em `memory/clientes-legacy/vargas.md`
- [ ] Banco Firebird Vargas identificado em `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos` (Wagner valida path)
- [ ] Versão Delphi confirmada via `SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'` — esperado: 1468
- [ ] **Localização Vargas confirmada** (SC/SP/MG/RJ?) via consulta `CLIENTES.UF` no banco — define se suporte presencial é viável
- [ ] **Vertical autopeças confirmado** via `SELECT TOP 100 DESCRICAO FROM PRODUTOS` — sample de 100 produtos. Se >50% são "filtro óleo / pastilha freio / amortecedor / pneu" → autopeças confirmado
- [ ] `business_id` novo provisionado em produção oimpresso (Wagner via `superadmin/businesses/create`)
- [ ] Bridge tables `accounts_legacy_map`, `customers_legacy_map`, `products_legacy_map` etc com `business_id` global scope ([Pattern 02](../../dominios/_patterns/02-bridge-tables-para-core.md))
- [ ] Vaultwarden segredo migration credentials Vargas cadastrado (cliente WR Sistemas DB user/pass)

### Migração (D-15 a D-1)

- [ ] **Dry-run** ([Pattern 07 three-mode](../../dominios/_patterns/07-three-mode-dry-run-local-prod.md)) em cópia local Firebird Vargas
- [ ] **Validators** rodam:
  - count match (NF emitidas legacy = NF migradas)
  - totals match (faturamento ano = sum migrado ±0,1%)
  - drift check (campos NULL inesperados, encoding latin1→utf8mb4 sem perda)
  - aplicação peças: aplicações Delphi → tabela `autopecas_aplicacoes` com integridade referencial
- [ ] **Pest test multi-tenant verde local** (Felipe roda; Wagner exige conforme [feedback 2026-05-09](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md))
- [ ] Vargas notificado por escrito (Wagner email + WhatsApp) data exata cutover + comunicação 7d antes ([proibicoes.md](../../proibicoes.md) §F5 CUTOVER sem aviso prévio)
- [ ] **Treinamento Vargas equipe** agendado D-7 a D-1 (Maiara síncrono 4-8h cobrindo Autopecas (planejado — não existe) + Jana + NFC-e ágil)

### Cutover (D-Day)

- [ ] **Parallel run** Delphi + oimpresso 30d pós-cutover (Pattern Strangler Fig — Delphi continua read-only, oimpresso é write canônico)
- [ ] Smoke test **biz Vargas real** (não biz=1, conforme [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- [ ] Smoke checklist autopecas:
  - venda balcão 5 itens + cliente + NFC-e <1500ms p95 ✅
  - busca peça por aplicação (Civic 2015 freio dianteiro) <2s ✅
  - devolução D+0 com motivo + estoque retorna ✅
  - garantia lookup NF + SKU <500ms ✅
  - NFe-de-boleto-pago dispara automática (US-AP-007) ✅
- [ ] Rollback 30d documentado (Vargas sabe pode voltar Delphi sem custo)
- [ ] **Wagner presencial dia cutover** (se Vargas SP/MG/RJ) ou Zoom plantão 8h (se Vargas longe)

### Pós-migração (D+1 a D+90)

- [ ] Smoke diário 7d ([Pattern 04](../../dominios/_patterns/04-smoke-canary-30-7-1.md) canary) — Felipe automatiza Pest browser-test em prod biz Vargas
- [ ] **Survey NPS D+30 + D+60 + D+90** (Maiara conduz; pergunta NPS clássico 0-10 + 1 free text)
- [ ] **Caso interno escrito** em `memory/sales/2026-Q3/cases/vargas-internal.md` D+90
- [ ] **Decisão D+90:** Vargas autoriza case público sim/não → se sim:
  - vídeo 90s (Wagner + designer externo gravam na loja Vargas)
  - landing `oimpresso.com/cases/vargas` ([Modules/Cms](../Cms/) cms_pages)
  - autoriza menção em battle card oimpresso vs Auto Manager/Lokoz/Linx Microvix
- [ ] **Aposentar Delphi WR Sistemas pro Vargas** (status `🔒 retired` em [`_index.md`](../../clientes-legacy/_index.md))
- [ ] **Autopecas (planejado — não existe) promovido `em_construcao` → `piloto`** (ADR 0125 lifecycle)

---

## Riscos sistêmicos específicos Vargas

### 1. Maior cliente legacy = single point of failure
- **Impacto:** se Vargas migrar e voltar pro Delphi em <30d, Autopecas (planejado — não existe) vira módulo morto + 26 anos relação Wagner-Vargas abalada
- **Mitigação:** Migration Factory Pattern Strangler Fig + parallel run 30d garante rollback grátis; Pest validators count/totals match (Pattern 07); Wagner presencial cutover (se viável geo)
- **Ativação review_trigger ADR 0119 #4** se voltar <30d

### 2. Build Delphi 1468 desatualizado pode ter quirks não-mapeados
- **Impacto:** versões Delphi anteriores podem ter triggers, procedures, customizações específicas Vargas não testadas em outros clientes
- **Mitigação:** **dry-run Pattern 07 obrigatório** antes cutover prod; comparar count + totals legacy vs migrado em sample de 6 meses história; teste em cópia local primeiro
- **Plano B:** se dry-run encontrar quirks complexos (ex: customer field custom Vargas-only), Felipe escreve adapter específico antes prod

### 3. Concorrência mid-market autopeças BR pode ter contatado Vargas
- **Impacto:** Auto Manager / Lokoz / Linx Microvix podem ter mapeado Vargas e estar em outreach; corrida
- **Mitigação:** **defender pelo vínculo Wagner-Vargas 26y** (intransferível) + diferencial Jana IA + NFe-de-boleto-pago + multi-tenant Tier 0 + stack moderna
- **Plano B:** se Vargas sinalizar "Auto Manager me ofereceu R$ [redacted Tier 0]/m", Wagner não baixa preço — defende valor Migration Factory + relação

### 4. Autopecas (planejado — não existe) Sprint 1 atrasado quando Vargas estiver pronto
- **Impacto:** Vargas assina out/26 mas Sprint 1 entrega só mar/27 = 5 meses esperando — pode esfriar
- **Mitigação:** Autopecas (planejado — não existe) Sprint 1 **só começa após Vargas assinar** (ADR 0125 enforced). Vargas espera 2-3 meses pós-assinatura é normal pra pioneer; comunicar honesto: "você é o primeiro, vamos construir juntos"
- **Plano B:** se atrasar >6 meses pós-assinatura, oferecer compensação (3 meses adicionais grandfathered grátis)

### 5. Localização Vargas longe Wagner (BR é grande)
- **Impacto:** se Vargas SP capital, viagem Wagner viável. Se Vargas Manaus/Recife/Fortaleza, presencial caro
- **Mitigação:** confirmar localização via banco antes call. Se longe, Zoom plantão 8h dia cutover + Maiara conferencia 2x/semana D+30 to D+60
- **Plano B:** se geografia inviabilizar suporte presencial mensal, ajustar pricing? Não — pricing é pricing. Comprometer com suporte remoto premium (SLA 4h vs 24h)

### 6. Pest local exigência Wagner pode atrasar PRs multi-tenant
- **Impacto:** [Wagner feedback 2026-05-09](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md) exige Pest verde **rodado localmente pelo dev** antes de PR multi-tenant. Autopecas (planejado — não existe) tem ~7 Models multi-tenant novos
- **Mitigação:** Felipe roda Pest local em cada PR Sprint 1 (não delegar pra CI); inclui no estimate ~2h/PR adicional
- **Plano B:** se virar gargalo Felipe, Wagner aprova batch parcial em PR menores

---

## Capacidade do time pra Vargas (12 meses)

| Pessoa | Hora total | Quando | Detalhe |
|--------|-----------:|--------|---------|
| **Wagner [W]** | 12h | Q4/26 (4h call discovery + 4h follow-up + 4h presencial cutover) | Discovery + decisão + presencial cutover |
| **Felipe [F]** dev IA-pair | 24h | Q4/26 (4h snapshot + 4h follow-up técnico) + Q1/27 (12h Sprint 1 features Vargas-críticas) + Q2/27 (4h cutover técnico) | Snapshot + roadmap demo + Sprint 1 + Migration Factory |
| **Maiara [M]** suporte L1 | 4h | Q1/27 (4h treinamento) + Q2/27 (mensais 1h follow-up D+30/+60/+90) | Treinamento + survey NPS |
| **TOTAL** | **40h** | 12 meses | **~3,3h/m médio** |

**Sustentável** — 3,3h/m do time é absorvível sem comprometer Sprint principal.

**Bottleneck Wagner:** 12h em call/presencial é caro mas justificado — Vargas é maior cliente legacy. Mitigação: Felipe absorve carga técnica; Maiara absorve onboarding pós-cutover. Wagner SÓ entra em discovery + go/no-go contratual + presencial cutover.

---

## Métricas de sucesso 12 meses (Vargas-específicas)

### Métricas-meta (out/26 → set/27)

| Métrica | Alvo | Como medir |
|---------|------|------------|
| **Vargas em prod pagando Autopecas (planejado — não existe)** | ✅ sim | `business[vargas].vertical_id = autopecas` AND `subscription_status = active` |
| **ARR Vargas year-1** | R$ [redacted Tier 0] (com 50% off 6m) | sum(monthly_revenue × meses) |
| **Churn Vargas 90d pós-migração** | 0% (não voltar pro Delphi) | review_trigger ADR 0119 #4 |
| **NPS Vargas D+90** | ≥40 | survey clássico 0-10 |
| **Tempo migração (signature → cutover)** | ≤120 dias | `cutover_date - contract_signed_date` |
| **Case público Vargas autorizado** | ≥1 | `oimpresso.com/cases/vargas` no ar com autorização |
| **Performance balcão Vargas (US-AP-002)** | p95 <1500ms | Pest browser-test prod biz Vargas |
| **Autopecas (planejado — não existe) promovido `piloto`** | ✅ sim D+90 | ADR 0125 lifecycle |

### Sinais de alerta (revisão imediata se baterem)

- 🔴 **Vargas migrar e voltar pro Delphi em <30d** → ADR 0119 review_trigger #4 acionado, revisar Migration Factory end-to-end + post-mortem detalhado
- 🔴 **Vargas perdido pro Auto Manager/Lokoz durante outreach** → racha narrativa "26 anos relação"; revisar abordagem + pricing pioneer + post-mortem
- 🟡 **Decisão Vargas >90d sem resposta** → re-priorizar; ofertar tempo extra ou aceitar postergar 12m
- 🟡 **Sprint 1 atraso >60d pós-assinatura** → comunicar honesto + compensação (3m grandfathered grátis adicional)
- 🟡 **NPS Vargas <30 D+90** → reforço suporte Maiara + call Wagner emergencial pra entender dor real

---

## Próximos passos imediatos (semana 1 — Wagner aprova ou ajusta)

1. **Wagner valida este plano** — toda assumption marcada como estimativa precisa confirmação ou rejeição
2. **Confirmar prazo Q4/26 outreach** — Wagner alinha com Felipe entrega Sprint 1 Modules/ComunicacaoVisual + 1ª piloto comvisual estabilizado pré-condição
3. **Snapshot financeiro Vargas** via skill `officeimpresso-financial-snapshot` agendado pra Q3/26 (após 192.168.0.55 servidor remoto Vargas voltar online)
4. **Confirmar localização Vargas** (SC/SP/MG/RJ) via banco — define geografia presencial
5. **Confirmar vertical autopeças** via sample 100 produtos no banco Firebird Vargas
6. **Definir gate-check Modules/ComunicacaoVisual Sprint 1** — Felipe + Wagner alinham 4 features mínimas comvisual pra abrir outreach Vargas Q4/26
7. **Registrar Vargas como candidato piloto Autopecas (planejado — não existe)** em `memory/clientes-legacy/_index.md` (status `🟡 candidato Autopecas (planejado — não existe)`)
8. **NÃO criar tasks MCP Autopecas (planejado — não existe) ainda** — gatilho Vargas assinatura (ADR 0125) é pré-requisito; criar tasks viola ADR 0105

---

## Referências

- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Tiers + 8 princípios)
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração 10x IA-pair
- [ADR 0118](../../decisions/0118-segregacao-dominios-externos-clientes-legacy.md) — Segregação domínios externos
- [ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md) — Migration Factory (mãe deste plano)
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical
- [ADR 0125](../../decisions/0125-modules-autopecas-feature-wish.md) — Autopecas (planejado — não existe) feature-wish (mãe deste plano)
- [SPEC Autopecas (planejado — não existe)](SPEC.md) — contrato funcional do módulo
- [Charter Autopecas (planejado — não existe)](Autopecas.charter.md) — charter v1 antecipatório
- [PLANO-MIGRACAO-6-SAUDAVEIS.md](../ComunicacaoVisual/PLANO-MIGRACAO-6-SAUDAVEIS.md) — Vargas removido pro autopeças, plano comvisual atualizado
- [memory/clientes-legacy/_index.md](../../clientes-legacy/_index.md) — matriz 49 clientes Delphi
- [memory/sales/2026-05/06-pricing-tiers.md](../../sales/2026-05/06-pricing-tiers.md) — tiers oficiais (Starter/Pro/Enterprise)
- [memory/dominios/_patterns/](../../dominios/_patterns/) — 7 patterns reusáveis Migration Factory
- [memory/requisitos/Officeimpresso/RUNBOOK-financial-snapshot-cliente.md](../Officeimpresso/RUNBOOK-financial-snapshot-cliente.md) — receita extração receita por cliente Firebird

---

**Última atualização:** 2026-05-10 · **Próximo review:** após Wagner aprovar/ajustar plano + snapshot financeiro Vargas executado (estimativa Q3/26 quando servidor remoto Vargas voltar online)
