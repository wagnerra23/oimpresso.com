# Plano Migração 6 Saudáveis OfficeImpresso → Modules/ComunicacaoVisual — 2026-05-10

> **Autor:** Claude (sub-agent migration engineer + customer success) sob direção Wagner [W]
> **Status:** `draft` — Wagner valida antes de qualquer outreach
> **Tier:** plano operacional (não ADR), subordinado a [ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md) (Migration Factory) + [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) (modular por vertical) + [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (sinal qualificado)
> **Pasta canônica:** `memory/requisitos/ComunicacaoVisual/` — primeiro doc da pasta (módulo em construção)

---

## Sumário executivo

- **Universo:** 6 saudáveis OfficeImpresso (originalmente 7 — Gold já trocou pra Mubisys conforme [post-mortem 2026-05-09](../../research/2026-05-prospeccao/07-post-mortem-gold-comunicacao-mubisys.md))
- **GMV agregado anual** (oimpresso Insights snapshot): **R$ [redacted Tier 0]M/ano** combinado
- **Receita atual oimpresso desses clientes:** **R$ [redacted Tier 0]** — eles ainda pagam **mensalidade WR Sistemas legacy estimada R$ [redacted Tier 0]-850/m cada** (não confirmado por banco; estimativa baseada em proposta Mubisys vazada R$ [redacted Tier 0]/m e tier típico OfficeImpresso ~R$ [redacted Tier 0]-800/m)
- **Receita alvo pós-migração 12m:** R$ [redacted Tier 0]-50k/mês incremental se 3 fecharem em Enterprise grandfathered
- **Esforço por piloto:** ~16h IA-pair Felipe [F] + 8h Wagner [W] (calls + decisão) + 2h Maiara [M] (suporte L1) = **~26h total/cliente** ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) recalibração 10x já aplicada)
- **ROI alvo:** 1 cliente Enterprise R$ [redacted Tier 0]/m × 12 = **R$ [redacted Tier 0]k/ano novo** + setup R$ [redacted Tier 0]k = **R$ [redacted Tier 0]k year-1 por cliente** (cap teórico Enterprise+ R$ [redacted Tier 0]-100k/ano se add-ons + multi-business)
- **Capacidade time:** ~130h ao longo de 12m = **11h/m** = sustentável sem comprometer Sprint principal

### Premissa-chave (declarada — Wagner valida)

Receita atual WR Sistemas é **estimativa**, não dado confirmado. Pra fechar essa lacuna: rodar [`officeimpresso-financial-snapshot`](../../requisitos/Officeimpresso/RUNBOOK-financial-snapshot-cliente.md) skill em cada banco Firebird antes da abordagem — extrai ticket pago, recência último update Delphi, sinais de churn. **Sem snapshot = chute, não plano.**

---

## Por cliente (6 blocos)

### ~~1. Vargas (R$ [redacted Tier 0]M GMV/ano)~~ → **REMOVIDO de Modules/ComunicacaoVisual** (Wagner confirmou 2026-05-10: vertical = **autopeças**)

> 🚨 **Atualização 2026-05-10**: Wagner confirmou que Vargas é **autopeças** (CNAE 4530-X), não comunicação visual. Vargas portanto NÃO entra no plano migração ComunicacaoVisual.
>
> **Vargas vira candidato do módulo Autopecas (planejado — não existe)** — vertical novo a ativar com base em sinal qualificado real (cliente saudável R$ [redacted Tier 0]M GMV, 26 anos relação, Wagner conhece direto). Tratamento separado em `memory/requisitos/Autopecas/PLANO-MIGRACAO-VARGAS.md` (a criar).
>
> **Impacto neste plano**: top 1 piloto Modules/ComunicacaoVisual passa a ser **Extreme** (R$ [redacted Tier 0]M GMV, "EXTREMA LED" = com.visual nativo confirmado por nome).

**Status atual (2026-05-10):**
- ✅ saudável OfficeImpresso (Delphi versão 1468, banco em servidor remoto Wagner)
- ⚠️ **build antigo** que NÃO chama backend Connector ([reference_delphi_wr_comercial.md](../../claude/reference_delphi_wr_comercial.md)) — sinal de cliente que não atualiza Delphi recentemente
- Razão social: Vargas Jato de Granalha LTDA (autopeças/serviço industrial), banco "Jardel Acessorios" no registry sugere filial autopecas tradicional
- Localização: a confirmar via banco quando 192.168.0.55 voltar online

**GMV ano anterior:** R$ [redacted Tier 0]M/ano (snapshot Insights — fonte: Wagner reportou)

**Receita histórica WR Sistemas (estimativa):** ~R$ [redacted Tier 0]-850/m
- Baseline: tier OfficeImpresso completo, multi-usuário, 26 anos relação
- **Confirmar via** `firebird_query` no banco Vargas: `SELECT * FROM CONFIGURACOES WHERE CONFIG LIKE '%LICENCA%'` ou `RUNBOOK-financial-snapshot` extraindo extrato pago Wagner→Vargas

**Riscos específicos:**
- 🔴 **Maior cliente do top — perda massiva.** R$ [redacted Tier 0]M GMV é 30% do agregado. Se migração der ruim, churn de Vargas inviabiliza Modules/ComunicacaoVisual como vertical comercial
- 🔴 **Build Delphi desatualizado** = cliente conservador, não corre pra novidade. Resistência natural a mudança
- 🟡 **Vertical exato a confirmar** — "Vargas Acessorios" sugere acessórios automotivos, NÃO comunicação visual. Validar via banco antes de positionar como piloto Modules/ComunicacaoVisual
- 🟢 **Histórico longo Wagner-Vargas** = ativo de relacionamento que Mubisys/Zênite não têm

**Estratégia de abordagem:**
- **Quem aborda:** Wagner [W] **direto** — relação 26 anos, não tercerizar pra SDR
- **Quando:** Q3/26 julho — após Modules/ComunicacaoVisual ter Sprint 1 entregue (cálculo m² + spool plotter funcional em ROTA LIVRE-style demo)
- **Como:** **call presencial ou Zoom 60min** — não cold email. Pitch: *"Tô construindo um sistema novo que mantém tudo seu histórico Delphi e adiciona NFe automática + IA. Quer ser o piloto?"* Migration Factory captura banco Firebird inteiro

**Pacote oferecido (pioneer):**
- **Setup R$ [redacted Tier 0]** (pioneer) — normalmente R$ [redacted Tier 0] Enterprise
- **Enterprise R$ [redacted Tier 0]/m grandfathered por 24m** + 50% off primeiros 6m = **R$ [redacted Tier 0]/m × 6m → R$ [redacted Tier 0]/m × 18m** = R$ [redacted Tier 0] year-2 incluso
- **Migração full** (Migration Factory pattern Strangler Fig + parallel run 30d Delphi+oimpresso lado a lado)
- **Modules/ComunicacaoVisual completo** (cálculo m², spool plotter, PCP gráfico, NFe-de-boleto)
- **Jana IA ilimitada** com memória 26 anos de histórico
- **Compromisso:** virar **case público** anonimizável (vídeo 90s + autoriza menção em battle card)

**Timing realista:**
- Q3/26 jul-set: abordagem + assinatura piloto (90d outreach + decisão)
- Q4/26 out-dez: migração + smoke + cutover (90d)
- Q1/27 jan-mar: estabilização + case público publicado

**Plano B se recusar:**
- Manter Vargas no OfficeImpresso, sem forçar
- Continuar releases manutenção legacy WR Sistemas pra ele (paid as-is)
- Re-tentar em 12-18m com case ROTA LIVRE+1 outro cliente migrado já estabilizado
- **NÃO churnar Vargas do OfficeImpresso por pressão de migração** — princípio [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) "guiar sem mandar"

---

### 2. Extreme (R$ [redacted Tier 0]M GMV/ano) — biz_id legacy mapeado: 196 ("EXTREMA LED")

**Status atual:**
- ✅ saudável OfficeImpresso (Delphi versão 1472, build NOVO que chama backend Connector)
- 🟢 **Build atualizado** = cliente que acompanha releases, mais aberto a novidade
- Razão social provável: EXTREMA LED (vertical: comunicação visual + LED — encaixa Modules/ComunicacaoVisual nativo)
- Localização: a confirmar via banco

**GMV ano anterior:** R$ [redacted Tier 0]M/ano

**Receita histórica WR Sistemas (estimativa):** ~R$ [redacted Tier 0]-850/m

**Riscos específicos:**
- 🟢 **Vertical encaixa perfeitamente** — "EXTREMA LED" = comunicação visual + LED, sweet spot Modules/ComunicacaoVisual
- 🟢 **Cliente atualizado** = receptivo
- 🟡 **2º maior cliente** — perda dói mas não é catastrófica
- 🟡 **Concorrência potencial:** se Mubisys já contatou Extrema LED (AFACOM+ braço Centro-Oeste pode ter mapeado), corrida

**Estratégia de abordagem:**
- **Quem aborda:** Wagner [W] inicial + Felipe [F] técnico em call follow-up
- **Quando:** Q3/26 setembro — depois do piloto Vargas confirmado (case interno usável: "Vargas migrou em 90d com X resultado")
- **Como:** call 45min com demo ROTA LIVRE Jana + roadmap Modules/ComunicacaoVisual ETA Q4/26

**Pacote oferecido:**
- **Setup R$ [redacted Tier 0]** (pioneer 2º — janela limitada)
- **Enterprise R$ [redacted Tier 0]/m grandfathered 24m** + 30% off primeiros 6m
- Migração full + Modules/ComunicacaoVisual + Jana ilimitada
- **Compromisso:** depoimento escrito (não vídeo, mais leve)

**Timing realista:**
- Q3/26 set: outreach + assinatura
- Q1/27 jan-mar: migração e cutover
- Q2/27 abr-jun: estabilizado

**Plano B:** mesmo Vargas — manter no legacy, re-tentar em 12m

---

### 3. Gold (R$ [redacted Tier 0]M GMV/ano) — biz_id legacy mapeado: ? (Gold no registry, versão 1466)

**Status atual:**
- ⚠️ **AMBIGUIDADE NOMENCLATURA** — *Gold* no registry WR Sistemas (versão 1466, banco "Gold") **pode OU não** ser o **Gold Comunicação** (Três Lagoas/MS, CNPJ 11.222.333/0001-81) que trocou pra Mubisys conforme [post-mortem](../../research/2026-05-prospeccao/07-post-mortem-gold-comunicacao-mubisys.md) <!-- pii-allowlist: CNPJ real do cliente substituído pelo fake canônico (oimpresso) — LGPD -->
- 🔴 **Hipótese 1:** mesmo cliente — Gold Comunicação Visual já churned pra Mubisys = perdido
- 🟡 **Hipótese 2:** Gold no registry é cliente diferente (Goiás? Outra Gold?) — ainda saudável
- **AÇÃO BLOQUEANTE:** Wagner valida qual Gold é qual antes de qualquer ação

**GMV ano anterior:** R$ [redacted Tier 0]M/ano (atribuído ao Gold do snapshot Insights — confirmar identidade)

**Receita histórica WR Sistemas:** R$ [redacted Tier 0]/m (se for o mesmo Gold de Três Lagoas/MS — valor confirmado em post-mortem 2026-05-09)

**Riscos específicos:**
- 🔴 **Provavelmente perdido** — se confirmar Hipótese 1
- 🔴 **Win-back é caríssimo** — cliente que acabou de assinar Mubisys 24m won't churn Mubisys em <12m sem dor concreta
- 🟢 **Gatilho D+90 Mubisys** documentado em post-mortem — outreach "como tá indo a parte de integração" pode plantar semente

**Estratégia de abordagem:**
- **Q3/26 (jul):** Wagner valida identidade Gold registry vs Gold Mubisys
- **Se Hipótese 1 (mesmo Gold, perdido):** SKIP — nenhuma abordagem ativa, registrar como perdido [ADR 0119 review_trigger #4 "voltar pro velho em <30d"] + outreach D+90 só se Mubisys deteriorar (cold DM)
- **Se Hipótese 2 (Gold diferente, saudável):** abordar via Wagner Q4/26 com mesmo pacote Vargas/Extreme

**Pacote oferecido:**
- **Hipótese 1 (win-back D+90 Mubisys):** Migration Factory recupera histórico Delphi pré-Mubisys + setup R$ [redacted Tier 0] + Enterprise R$ [redacted Tier 0]/m primeiros 12m (desconto agressivo win-back) — SÓ se cliente sinalizar dor real
- **Hipótese 2:** mesmo Vargas/Extreme

**Timing realista:**
- Q3/26 jul: validação identidade
- Q4/26 dez: outreach D+90 Mubisys (se Hipótese 1) — sem expectativa
- Q2/27: revisão win-back se sinal positivo

**Plano B:**
- Hipótese 1: aceitar perda, usar como case interno "como NÃO perder próximo prospect" (já documentado em post-mortem)
- Hipótese 2: re-tentar 12-18m

---

### 4. Zoom (R$ [redacted Tier 0]M GMV/ano) — biz_id legacy: ? (Zoom no registry, versão 1474 = MAIS NOVA observada)

**Status atual:**
- ✅ saudável OfficeImpresso
- 🟢 **Versão Delphi 1474** = mais recente do parque (49 clientes mapeados) — cliente que paga upgrades, alta retenção
- Razão social: a confirmar — "Zoom" pode ser Zoom Sinalização, Zoom Comunicação ou Zoom Gráfica

**GMV ano anterior:** R$ [redacted Tier 0]M/ano

**Receita histórica WR Sistemas (estimativa):** ~R$ [redacted Tier 0]-900/m (cliente premium, paga upgrades regulares)

**Riscos específicos:**
- 🟢 **Cliente top-tier OfficeImpresso** — receptivo a tecnologia nova se valor evidente
- 🟡 **Mid-size** = decisão potencialmente comitê (não dono-único). Ciclo venda mais longo
- 🟡 **Pode estar satisfeito demais** com OfficeImpresso atual — diferencial precisa ser CONCRETO

**Estratégia de abordagem:**
- **Quem:** Wagner [W] + Felipe [F] técnico
- **Quando:** Q4/26 outubro (após Vargas piloto confirmado, antes Extreme migração concluir)
- **Como:** demo Zoom 60min focada em Jana IA ("pergunte ao seu negócio") + NFe automática + cálculo m²

**Pacote:**
- **Setup R$ [redacted Tier 0]** (50% off do Enterprise R$ [redacted Tier 0])
- **Enterprise R$ [redacted Tier 0]/m grandfathered 18m** (entre Vargas R$ [redacted Tier 0] e Pro R$ [redacted Tier 0])
- Migração full + Modules/ComunicacaoVisual

**Timing realista:**
- Q4/26 out-dez: outreach + decisão
- Q2/27 abr-jun: migração + cutover

**Plano B:** retain no OfficeImpresso, re-tentar 12m

---

### 5. Fixar (R$ [redacted Tier 0]M GMV/ano) — biz_id legacy: ? (Fixar no registry, versão 1421)

**Status atual:**
- ✅ saudável OfficeImpresso (versão 1421 — meio do parque, sem update recente)
- 🟡 **Tamanho menor** (R$ [redacted Tier 0]M GMV vs Vargas R$ [redacted Tier 0]M) — escala diferente, decisão mais rápida
- Razão social: a confirmar — nome sugere fixação/sinalização

**GMV ano anterior:** R$ [redacted Tier 0]M/ano

**Receita histórica WR Sistemas (estimativa):** ~R$ [redacted Tier 0]-600/m (cliente menor, tier mid)

**Riscos específicos:**
- 🟢 **Decisão dono-único provável** — ciclo venda curto
- 🟡 **Versão Delphi sem update recente** = pode estar avaliando alternativa (sinal de churn latente)
- 🟢 **Tamanho menor** = piloto barato pra time interno, errar custa pouco
- 🟡 **Margem operacional menor** — pode bater com R$ [redacted Tier 0]/m Enterprise. Pro R$ [redacted Tier 0] mais realista

**Estratégia de abordagem:**
- **Quem:** Felipe [F] (Wagner aprova proposta) — aliviar carga Wagner
- **Quando:** Q1/27 fev (4º cliente da fila)
- **Como:** cold email Wagner-style + demo 30min Felipe

**Pacote:**
- **Setup R$ [redacted Tier 0]** (50% off Pro R$ [redacted Tier 0])
- **Pro R$ [redacted Tier 0]/m grandfathered 12m** (não Enterprise — não justifica)
- Modules/ComunicacaoVisual + NFe automática + Jana 500 perguntas/m
- Migração assistida (não full-service) — Maiara [M] guia cliente operar Migration Factory

**Timing realista:**
- Q1/27 jan-mar: outreach + decisão
- Q2/27 abr-jun: migração + cutover
- Q3/27: estabilizado

**Plano B:** se recusar, plausible churn natural pra Mubisys/Zênite — perda não-fatal

---

### 6. Mhundo (R$ [redacted Tier 0]k GMV/ano) — biz_id legacy: ? (Mhundo no registry, versão 1429)

**Status atual:**
- ✅ saudável OfficeImpresso (versão 1429)
- 🟡 Cliente pequeno-médio
- Razão social: a confirmar — "Mhundo" sugere "Meu Mundo" ou similar

**GMV ano anterior:** R$ [redacted Tier 0]k/ano

**Receita histórica WR Sistemas (estimativa):** ~R$ [redacted Tier 0]-500/m

**Riscos específicos:**
- 🟢 **Decisão dono-único** — ciclo curto
- 🟡 **GMV pequeno** — pode não justificar Pro R$ [redacted Tier 0] (margem apertada)
- 🟡 **Vertical a confirmar** — pode não ser comunicação visual estrita

**Estratégia de abordagem:**
- **Quem:** Maiara [M] outreach + Felipe [F] técnico se avançar
- **Quando:** Q1/27 mar (5º cliente da fila)
- **Como:** cold email + WhatsApp follow-up

**Pacote:**
- **Setup R$ [redacted Tier 0]** (entry-level)
- **Pro R$ [redacted Tier 0]/m grandfathered 12m** (10% off Pro padrão)
- Modules/ComunicacaoVisual lite (só features essenciais) + NFe + Jana 200 perguntas/m

**Timing realista:**
- Q1/27 mar: outreach
- Q2/27 abr-jun: decisão + migração assistida

**Plano B:** plausible churn — perda absorvível. Se recusar, manter no legacy até cliente decidir migrar sozinho

---

### 7. Produart (R$ [redacted Tier 0]k GMV/ano) — biz_id legacy: ? (Produart no registry, versão 1472, "Banco antigo")

**Status atual:**
- ⚠️ **"Banco de Dados muito antigo"** marcado no Editor de Registros — sinal de cliente que NÃO paga upgrades, possível candidato a churn natural
- Cliente pequeno (R$ [redacted Tier 0]k GMV)
- Vertical: a confirmar

**GMV ano anterior:** R$ [redacted Tier 0]k/ano

**Receita histórica WR Sistemas (estimativa):** ~R$ [redacted Tier 0]-450/m

**Riscos específicos:**
- 🔴 **"Banco antigo" + sem upgrades** = cliente em fase tail-end de vida útil OfficeImpresso. Pode estar avaliando saída ou já desistiu de evoluir
- 🟡 **Pode ser oportunidade** — cliente sabe que precisa trocar; oimpresso pode ser opção natural se chegar primeiro
- 🟢 **Decisão rápida** — dono pequeno, sem comitê

**Estratégia de abordagem:**
- **Quem:** Maiara [M]
- **Quando:** Q2/27 abril (6º cliente, último da fila)
- **Como:** **abordagem direta valor:** *"Sabemos que seu banco WR Sistemas tá antigo. Migração full em 30d sem perder histórico, depois você fica em sistema atualizado mensalmente. Quer ver?"*

**Pacote:**
- **Setup R$ [redacted Tier 0]** (oferta agressiva — cliente em tail-end)
- **Pro R$ [redacted Tier 0]/m grandfathered 6m → R$ [redacted Tier 0]/m** (entrada agressiva)
- Modules/ComunicacaoVisual lite + Jana 200 perguntas

**Timing realista:**
- Q2/27 abr: outreach
- Q3/27: decisão + migração se sim

**Plano B:** churn natural mais provável que migração — registrar como **clientes WR Sistemas em fim-de-vida** sem stress; aceitar como dado de mercado

---

## Cronograma trimestral (12 meses)

| Trimestre | Vargas | Extreme | Gold | Zoom | Fixar | Mhundo | Produart |
|-----------|--------|---------|------|------|-------|--------|----------|
| **Q3/26 jul-set** | 🎯 outreach + piloto fechado | 📋 outreach inicial | ⚠️ validar identidade | — | — | — | — |
| **Q4/26 out-dez** | 🛠️ migração + smoke + cutover | 📋 fechamento contrato | ⚠️ outreach D+90 Mubisys (se aplicável) | 🎯 outreach + demo | — | — | — |
| **Q1/27 jan-mar** | ✅ estabilizado + case público | 🛠️ migração + cutover | — | 📋 fechamento | 🎯 outreach + decisão | 📋 outreach inicial | — |
| **Q2/27 abr-jun** | — | ✅ estabilizado | 🔁 review win-back se sinal | 🛠️ migração + cutover | 🛠️ migração assistida | 🎯 outreach + decisão | 🎯 outreach |

**Legenda:**
- 🎯 outreach inicial / call discovery
- 📋 negociação / fechamento contrato
- 🛠️ migração + smoke + cutover (Migration Factory)
- ✅ estabilizado / case público
- 🔁 win-back review
- ⚠️ validação identidade ou ação dependente

### Meta cumulativa por trimestre

| Trimestre | Clientes assinados (acumulado) | Clientes migrados em prod |
|-----------|-------------------------------:|---------------------------:|
| Q3/26 | 1 (Vargas) | 0 |
| Q4/26 | 2 (+ Extreme) | 1 (Vargas) |
| Q1/27 | 4 (+ Zoom + Fixar) | 2 (+ Extreme) |
| Q2/27 | 5 (+ Mhundo) | 4 (+ Zoom + Fixar) |

**Gold/Produart:** fora da meta-base. Bônus se acontecerem.

---

## Capacidade do time pra suportar (12 meses)

| Pessoa | Hora/cliente | × Clientes (5 ativos: Vargas, Extreme, Zoom, Fixar, Mhundo) | Total ano | Mensal |
|--------|--------------|--------------------------------------------------:|----------:|-------:|
| **Felipe [F]** dev IA-pair (migração + Modules/ComunicacaoVisual features sob demanda) | 16h | × 5 | 80h | 6,7h/m |
| **Wagner [W]** discovery + decisão + go/no-go | 8h | × 5 + 4h Gold validação | 44h | 3,7h/m |
| **Maiara [M]** suporte L1 + treinamento + migração assistida (Fixar/Mhundo/Produart) | 6h | × 5 | 30h | 2,5h/m |
| **TOTAL TIME** | — | — | **154h** | **12,8h/m** |

**Sustentável** — 12,8h/m do time é absorvível sem comprometer Sprint principal Modules/Vestuario (ROTA LIVRE manutenção) + Modules/ComunicacaoVisual desenvolvimento (Sprint 1 entrega Q3/26).

**Bottleneck Wagner [W]:** 3,7h/m em calls é razoável MAS Wagner já é gargalo geral ([CLAUDE.md](../../../CLAUDE.md) §regras-time *"Wagner deve evitar virar bottleneck"*). Mitigação: delegar code review Migration Factory pra Felipe + Maiara faz outreach low-touch (Mhundo/Produart) sem Wagner.

---

## Métricas de sucesso 12 meses

### Métricas-meta (Q3/26 → Q2/27)

| Métrica | Alvo | Como medir |
|---------|------|------------|
| **Clientes Modules/ComunicacaoVisual em prod pagando** | ≥3 | `business.vertical_id = comvisual` AND `subscription_status = active` |
| **ARR adicional dos migrados** | ≥R$ [redacted Tier 0]k | sum(monthly_revenue) × 12 dos 3+ migrados |
| **Churn 90d pós-migração** | 0% | nenhum cliente migrado volta pro Delphi ou cancela em <90d |
| **NPS migrados** | ≥40 | survey 90d pós-cutover (NPS clássico 0-10) |
| **Tempo médio migração (signature → cutover)** | ≤120 dias | `cutover_date - contract_signed_date` |
| **Cases públicos publicados** | ≥1 (Vargas) | `oimpresso.com/cases/<cliente>` no ar com autorização |

### Sinais de alerta (revisão imediata se baterem)

- 🔴 **Cliente migrar e voltar pro Delphi em <30d** → ADR 0119 review_trigger #4 acionado, revisar Migration Factory end-to-end
- 🔴 **Vargas perdido pro Mubisys/Zênite durante outreach** → racha narrativa "26 anos relação"; revisar cold email + pricing pioneer
- 🟡 **Zero pilotos fechados até fim Q3/26** → Modules/ComunicacaoVisual atrasado ou pricing errado; reduzir ambição cronograma 50%
- 🟡 **Churn pós-migração entre 30-90d** → onboarding insuficiente, Maiara reforça com 2 calls follow-up extras

---

## Riscos sistêmicos

### 1. Gold provavelmente perdido pra Mubisys
- **Impacto:** -R$ [redacted Tier 0]M GMV do funil (perde 23% do agregado teórico)
- **Mitigação:** já documentado em post-mortem; ABICOMV inscrição P0 (semana 1) pra evitar próximo Gold; outreach D+90 win-back só se sinal real
- **Pior caso:** confirma Hipótese 1 + zero migração subsequente Gold-style → Modules/ComunicacaoVisual fica com 5 candidatos efetivos

### 2. Modules/ComunicacaoVisual incompleto na hora da migração 1ª (Vargas Q3/26)
- **Impacto:** Vargas espera Modules/ComunicacaoVisual completo (cálculo m² + spool plotter + PCP gráfico + NFe-de-boleto). Se Sprint 1 entregar só 2 de 4 features, piloto frustra
- **Mitigação:** **gate-check Sprint 1 ANTES de assinar Vargas** — Felipe entrega Modules/ComunicacaoVisual Sprint 1 em julho/26, Wagner aprova baseado em demo, então abre outreach Vargas. Sem Sprint 1 verde, atrasar outreach
- **Plano B:** se Sprint 1 atrasar 60d, postergar Vargas pra Q4/26 inteiro; Extreme passa a ser piloto Q3/26

### 3. Multi-tenant Tier 0 + Migration Factory cross-business risk
- **Impacto:** Migration Factory rodando 5 imports em paralelo pode vazar dado entre tenants se Pattern 02 (`bridge tables _legacy_map` com business_id global scope) falhar
- **Mitigação:** [Pattern 02](../../dominios/_patterns/02-bridge-tables-para-core.md) é Tier 0 obrigatório; Pest test com 2 imports concorrentes verde antes de smoke prod; [feedback](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md) Wagner 2026-05-09 exige Pest local antes de PR multi-tenant

### 4. Capacidade Wagner [W] vs hours-spent migration
- **Impacto:** se Wagner virar bottleneck (3,7h/m × 12m = 44h/ano só pra migração), Sprint Modules/Vestuario manutenção sofre + ROTA LIVRE customer success degrada
- **Mitigação:** delegar Felipe migração técnica + Maiara suporte L1 + Wagner SÓ entra em discovery + go/no-go contratual

### 5. Pricing Enterprise R$ [redacted Tier 0]/m bate com Mubisys R$ [redacted Tier 0]/m proposta — concorrência pode reduzir
- **Impacto:** Mubisys pode oferecer R$ [redacted Tier 0]/m em retaliação pros mesmos prospects
- **Mitigação:** **defender pelo diferencial NFe automática + Jana**, não pelo preço. Battle card já documentado em post-mortem Gold seção "3 falas-killer"
- **Plano B:** se 2+ clientes em sequência rejeitarem por preço, validar redução Pro Tier (R$ [redacted Tier 0] → R$ [redacted Tier 0]) com Wagner — não Enterprise

### 6. Banco Firebird Vargas/Extreme pode ter quirks não-mapeados (drift schema)
- **Impacto:** Migration Factory atual (8 PRs validados) testou em **Wagner biz=1** + **3 contas reais** — não testou em Vargas/Extreme. Pode ter triggers, procedures, customizações por cliente
- **Mitigação:** **dry-run Pattern 07** obrigatório antes de cutover prod; comparar count + totals legacy vs migrado; rollback 30d garantido

---

## Migration Factory ([ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md)) aplicada — checklist pros 6 casos

Por cada cliente migrado, executar checklist baseado em [`memory/dominios/_patterns/`](../../dominios/_patterns/):

### Pré-migração (D-30 contractual)
- [ ] Snapshot financeiro skill `officeimpresso-financial-snapshot` rodada → arquivo em `memory/clientes-legacy/<alias>.md`
- [ ] Banco Firebird identificado em `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos` (Wagner valida path)
- [ ] Versão Delphi confirmada via `SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'`
- [ ] `business_id` novo provisionado em produção oimpresso (Wagner via `superadmin/businesses/create`)
- [ ] Bridge tables `accounts_legacy_map`, `customers_legacy_map`, `products_legacy_map` etc com `business_id` global scope ([Pattern 02](../../dominios/_patterns/02-bridge-tables-para-core.md))
- [ ] Vaultwarden segredo migration credentials cadastrado (cliente WR Sistemas DB user/pass)

### Migração (D-15 a D-1)
- [ ] **Dry-run** ([Pattern 07 three-mode](../../dominios/_patterns/07-three-mode-dry-run-local-prod.md)) em cópia local Firebird
- [ ] **Validators** rodam: count match (NF emitidas legacy = NF migradas), totals match (faturamento ano = sum migrado ±0,1%), drift check (campos NULL inesperados)
- [ ] Cliente notificado por escrito (Wagner email + WhatsApp) data exata cutover + comunicação 7d antes ([proibicoes.md](../../proibicoes.md) §F5 CUTOVER sem aviso prévio)
- [ ] Pest test multi-tenant verde local (Felipe roda; Wagner exige conforme [feedback 2026-05-09](../../../C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_tenancy_changes_require_pest_local.md))

### Cutover (D-Day)
- [ ] **Parallel run** Delphi + oimpresso 30d pós-cutover (Pattern Strangler Fig — Delphi continua read-only, oimpresso é write canônico)
- [ ] Smoke test **biz cliente real** (não biz=1, conforme [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- [ ] Treinamento Maiara [M] síncrono 2-8h conforme tier (cobre Modules/ComunicacaoVisual + Jana + NFe automática)
- [ ] Rollback 30d documentado (cliente sabe pode voltar Delphi sem custo)

### Pós-migração (D+1 a D+90)
- [ ] Smoke diário 7d ([Pattern 04](../../dominios/_patterns/04-smoke-canary-30-7-1.md) canary)
- [ ] Survey NPS D+30 + D+60 + D+90 (Maiara conduz)
- [ ] Case interno escrito em `memory/sales/2026-05/cases/` D+90
- [ ] Decisão D+90: cliente autoriza case público sim/não → se sim, vídeo 90s + landing `oimpresso.com/cases/<cliente>`
- [ ] Aposentar Delphi WR Sistemas pro cliente (status `🔒 retired` em [`_index.md`](../../clientes-legacy/_index.md))

---

## Próximos passos imediatos (semana 1 — Wagner aprova ou ajusta)

1. **Wagner valida este plano** — toda assumption marcada como estimativa precisa confirmação ou rejeição
2. **Snapshot financeiro 6 candidatos** via skill `officeimpresso-financial-snapshot` — rodar em batch, ~1h cada cliente, 6h total
3. **Validar identidade Gold** (registry vs Mubisys) — Wagner cruza CNPJ
4. **Confirmar vertical Vargas** — se "Vargas Acessorios" é automotivo, Modules/ComunicacaoVisual não encaixa; reavaliar pra Modules/OficinaAuto futuro
5. **Definir gate-check Sprint 1 Modules/ComunicacaoVisual** — Felipe + Wagner alinham 4 features mínimas pra abrir outreach Vargas Q3/26
6. **Criar tasks MCP** ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md)) — `tasks-create` pra cada outreach + cada migração no `cycle:current` quando Wagner aprovar este plano

---

## Referências

- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Tiers + 8 princípios)
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração 10x IA-pair
- [ADR 0118](../../decisions/0118-segregacao-dominios-externos-clientes-legacy.md) — Segregação domínios externos
- [ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md) — Migration Factory (mãe deste plano)
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical
- [memory/clientes-legacy/_index.md](../../clientes-legacy/_index.md) — matriz 49 clientes Delphi
- [memory/research/2026-05-prospeccao/07-post-mortem-gold-comunicacao-mubisys.md](../../research/2026-05-prospeccao/07-post-mortem-gold-comunicacao-mubisys.md) — post-mortem Gold (referência Mubisys + battle card)
- [memory/sales/2026-05/06-pricing-tiers.md](../../sales/2026-05/06-pricing-tiers.md) — tiers oficiais (Starter R$ [redacted Tier 0] Pro R$ [redacted Tier 0] Enterprise R$ [redacted Tier 0])
- [memory/dominios/_patterns/](../../dominios/_patterns/) — 7 patterns reusáveis Migration Factory
- [memory/requisitos/Officeimpresso/RUNBOOK-financial-snapshot-cliente.md](../Officeimpresso/RUNBOOK-financial-snapshot-cliente.md) — receita extração receita por cliente Firebird

---

**Última atualização:** 2026-05-10 · **Próximo review:** após Wagner aprovar/ajustar plano + 1º snapshot financeiro Vargas executado (estimativa 7-14 dias)
