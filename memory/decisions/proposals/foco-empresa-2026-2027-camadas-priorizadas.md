---
title: ADR proposta — Foco da empresa 2026-2027 (4 camadas priorizadas)
status: proposed (Wagner valida)
date: 2026-05-09
author: Claude Opus 4.7 (sub-agent autônomo)
relates: ADR 0022 (meta R$ 5M), ADR 0105 (cliente sinal qualificado), ADR 0094 (Constituição v2)
---

# Foco da empresa 2026-2027 — 4 camadas priorizadas baseadas em dados reais

## Pergunta central

> **Qual deve ser o foco da empresa nos próximos 12 meses?**

## Contexto descoberto via análise direta de 37 bancos OfficeImpresso

### Diagnóstico cru
- ARR atual real: **R$ 487k** (10% da meta R$ 5M)
- Operação 12m: **déficit -R$ 68k** (10/12 meses negativos, recente positiva)
- A receber vencidas (clientes te devendo): R$ 292.964
- A pagar vencidas (você devendo): R$ 575.334
- **GMV agregado dos 37 clientes OfficeImpresso**: R$ 45.071.561 (você recebe 1%)
- **132.149 clientes finais nos bancos** (clientes-dos-clientes — prospects 2º grau)
- Cobertura geográfica: 12 UFs com cliente direto · 27 UFs com cliente final indireto
- 60% dos bancos clientes em CHURN_PROVAVEL (>180d sem atividade) — sangria silenciosa
- 6 saudáveis ativos (Vargas, Extreme, Gold, MoveisSul, Zoom, Fixar, Mhundo, Produart)

### O que isso significa
**WR Sistemas é negócio estável mas estagnado**. Não é "startup buscando 1º cliente" — é negócio com 26 anos, 41 clientes legacy, deficitário recente, com 60% de churn silencioso. Reposicionamento técnico (oimpresso.com novo) é janela de saída — não opcional.

## Decisão proposta — 4 camadas em ordem de prioridade

### 🎯 Camada 1 (0-90d) — REAQUECER BASE PRÓPRIA
**Objetivo**: parar a sangria + extrair mais valor dos clientes que já tem.

**Ações**:
1. **Win-back 6 churned quentes** (Safety, TechPress, GSX, NewPrintFoz, Fluxo, Estilo) — GMV agregado R$ 9,7M. 1 voltando = +R$ 42k/ano. 2 = +R$ 84k.
2. **Plano anti-churn pros 14 churn-leve** (Mecanica Lebrinha, MilLetras, etc) — cold WhatsApp acolhedor + lead magnet snapshot grátis.
3. **Upgrade voluntário 6 saudáveis** com pricing por GMV — Vargas/Extreme/Gold pagam R$ 850/m hoje (0,17% do GMV deles), deveriam pagar R$ 3-5k/m (0,5-1%). Voluntário com features novas (NFe automática, Jana ilimitada, multi-business) — grandfather 12m pra preservar relação.
4. **Cobrar 50% dos R$ 292k a receber vencidas em 90d** = R$ 146k entrando.
5. **Renegociar R$ 575k a pagar vencidas** — auditar imposto vs fornecedor.

**ROI esperado 90d**: +R$ 200-300k cash + redução de 60% churn silencioso + base preparada pra Camada 2.

**Skills/runbooks/features que sustentam**:
- `officeimpresso-financial-snapshot` (skill já criada)
- Runbook `RUNBOOK-financial-snapshot-cliente.md`
- Feature paga `feature-financial-snapshot-multi-cliente.md` (ADR 0121 proposta)

### 🎯 Camada 2 (90-180d) — MIGRAÇÃO + LEADS DE 2º GRAU
**Objetivo**: virar 6 saudáveis em receita oimpresso.com novo + capturar clientes-dos-clientes.

**Ações**:
1. **Migrar 3 saudáveis maiores** (Vargas R$ 7,9M GMV, Extreme R$ 6,36M, Gold R$ 6,13M) pro oimpresso.com com pricing por GMV (cada um vira R$ 30-50k/ano = R$ 90-150k ARR adicionado).
2. **Cold approach top 20 leads 2º grau** (Samarco/Eldorado/G7 Log/Pro3/Viferro/etc) — abertura: *"sou fornecedor de software do seu fornecedor de gráfica X"*. Conversão alta porque tem confiança indireta.
3. **API docs MVP Swagger** (Felipe 20h IA-pair) — destrava 5 P0 integrações.
4. **Smoke SEFAZ NFC-e biz=1** — destrava goal CYCLE-02 + selling point.

**ROI esperado 180d**: +R$ 100-200k ARR + 1 fechamento de lead 2º grau Enterprise + plataforma pronta pra escalar.

### 🎯 Camada 3 (180-360d) — COLD PROSPECTING UFs SEM COBERTURA
**Objetivo**: expandir geograficamente onde não há cobertura indireta.

**Foco**: 15 UFs SEM cliente direto OfficeImpresso → AC, AL, AM, AP, CE, DF, MA, MT, PE, PI, RJ, RO, RN, SE, TO.
- **PE** (3.130 clientes finais indiretos via CopyLanLocal/BA) — mercado com sinal mais forte
- **DF** (316), **RJ** (733), **CE** (237), **MA** (226) — sinais médios
- Resto = só com canal (ABICOMV/AFACOM) ou parceria fornecedor (HP Latex/Mimaki)

**Ações**:
1. Cold email + LinkedIn outbound persona "Herdeiro recém-empossado" (skill `linkedin-outbound-playbook` ROI alto)
2. Inscrever oimpresso na ABICOMV (criada jan/2025, janela aberta antes Mubisys/Zênite travarem)
3. Partnership HP Latex Partner First (programa self-service, único)
4. Cold email Tier 1 SP (10 emails personalizados já prontos)

**ROI esperado 360d**: +R$ 100-200k ARR de clientes net-new + 1 partnership oficial + 1 case publicado em revista institucional.

### 🎯 Camada 4 (futuro condicionado) — MULTI-VERTICAL SE HOUVER SINAL
**Objetivo**: explorar adjacências SE 3+ pilotos pagantes aparecerem em 90d.

**Verticais possíveis** (apenas se sinal qualificado real):
- **Oficina auto/mecânica** — Modules/Repair cobre 55-60%. Mas histórico WR Sistemas mostra 6 churns 2009-2013. STAY-FOCUSED até gatilho.
- **Cofre de senhas (MemCofre)** — produto separado pra time de 3-15 pessoas.
- **PontoWr2** (controle eletrônico Portaria 671/2021) — vertical legado, possível upsell.

**Critério de mudança**:
- 3 oficinas auto pagando piloto R$ 199-399/m por 6m com aceite formal = ativa Camada 4
- Sem isso = backlog ADR feature-wish.

## Não-foco (o que parar de fazer)

❌ **Não construir DAM nativo agora** — cenário D waiting-list (3 contratos Enterprise pagos antes do build).

❌ **Não expandir vertical auto** — histórico provou que não pega + sem sinal qualificado real (Martinho é 1 cliente, não vertical).

❌ **Não atender cliente fora do ICP** — aceitar pagamentos pequenos (<R$ 200/m) é dispersão; oferecer Snapshot Free (R$ 99/m) como alternativa qualifica antes.

❌ **Não construir features sem sinal** — toda hipótese sem cliente pagando vira ADR feature-wish.

## Métricas de sucesso (90d / 180d / 360d)

### 90d
- [ ] 1 win-back fechado (Safety/TechPress/GSX/etc)
- [ ] R$ 100k+ recuperado de a receber vencidas
- [ ] 1 saudável aceitar trial Pro Plus
- [ ] Smoke SEFAZ biz=1 executado
- [ ] Pricing recalibrado em produção

### 180d
- [ ] 1 saudável migrado pro oimpresso.com com upgrade pricing (Vargas/Extreme/Gold)
- [ ] 1 cliente 2º grau fechado Enterprise
- [ ] API docs Swagger live em /api/docs
- [ ] ABICOMV parceria assinada
- [ ] ARR cresceu 30%+ vs hoje (R$ 487k → R$ 633k+)

### 360d
- [ ] 5 saudáveis migrados pricing por GMV
- [ ] 3 cold-net-new fechados (PE/DF/RJ)
- [ ] 1 partnership oficial fornecedor máquina
- [ ] ARR cresceu 100% (R$ 487k → R$ 974k+)
- [ ] Operação 12m positiva (resultado >0)

### Re-avaliar Camada 4 (multi-vertical) somente se:
- ARR ≥ R$ 1M E 3+ pilotos auto pagando

## Riscos identificados

1. **Wagner como bottleneck único** — Camadas 1-3 todas dependem dele pra discovery/decisão. Mitigação: delegar parte pra Felipe + Maiara + Eliana, mas calls de fechamento permanecem dele.
2. **Time legado vs nova arquitetura** — manter 41 OfficeImpresso enquanto constrói oimpresso.com novo divide foco. Mitigação: deprecar OfficeImpresso em 24m (ADR formal) com migração assistida.
3. **Choque cultural cliente legacy** — pricing por GMV pode parecer extorsivo. Mitigação: grandfather 12m + comunicação centrada em valor (NFe, Jana, multi-business) antes do preço.
4. **Caixa apertado pra investir em aquisição** — operação deficitária recente. Mitigação: Camada 1 (cobrança + retenção) gera caixa antes de Camada 3 (custosa).

## Decisão pendente de Wagner

- [ ] Aprovar este foco (4 camadas) ou ajustar?
- [ ] Confirmar: Camada 1 começa imediatamente?
- [ ] Confirmar: Camada 4 (vertical auto) backlog até gatilho?
- [ ] Formalizar como ADR canonica (próximo número, ~0121-0125)?

## Apêndice — Por que essa decisão (resumo de evidências)

| Evidência | Implicação |
|-----------|-----------|
| Receita 12m R$ 487k (-R$ 68k déficit) | Crescimento exige aquisição, não apenas retenção |
| 60% bancos churned silenciosamente | Reaquecer base é low-hanging fruit antes de cold |
| GMV agregado clientes R$ 45M | Pricing por % GMV é caminho racional |
| 132k clientes finais 27 UFs | Universo prospects 2º grau gigante via referral |
| Histórico vertical auto 6 churns 2009-2013 | Multi-vertical é distração até sinal real |
| 6 saudáveis com GMV >R$ 700k | Esses são âncoras pra migração + caso público |

---

**Esse documento sintetiza tudo descoberto na execução autônoma 2026-05-09 (~30 sub-agents, ~8M tokens, 51 artefatos commitados, 5h wallclock vs 36h sequencial).**
