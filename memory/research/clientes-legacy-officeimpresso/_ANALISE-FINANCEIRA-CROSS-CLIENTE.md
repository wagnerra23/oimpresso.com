---
title: Analise financeira cross-cliente — 4 candidatos saudaveis OfficeImpresso
status: live
date: 2026-05-11
audience: time interno (Wagner / Felipe / Maiara) + Eliana [E] (revisao LGPD)
purpose: calibrar pricing de migracao Delphi → oimpresso.com cruzando dados financeiros
fonte: scripts/financial_snapshot.py (skill officeimpresso-financial-snapshot) — 8 queries SQL Firebird
sample: 4 candidatos pre-qualificados pela analise heatmap UI v2 (2026-05-11)
---

# Analise financeira cross-cliente — 4 candidatos saudaveis

> Snapshot financeiro 12m gerado em **2026-05-11** rodando `scripts/financial_snapshot.py --batch` contra os 4 bancos Firebird identificados em [README.md](README.md). **Apenas SELECT** — nenhuma mutacao nos bancos legacy. Anonimizacao via sha1(razao_social)[:6] (ver [_LGPD.md](_LGPD.md) §4).

## 1. Tabela comparativa (Q1 + Q5)

> Valores na faixa "R$ X-Y M" pra preservar sensibilidade comercial. Detalhe granular em [`{slug}/03-financeiro-2026-05-11.md`](.) anonimizado.

| # | Cliente | Vertical | Receita 12m | Resultado 12m | A receber vencidas | A pagar vencidas | MRR atual | Contratos ativos | Flags |
|---|---------|----------|------------:|--------------:|-------------------:|-----------------:|----------:|-----------------:|------:|
| 1 | [`Cliente_874398`](02-vargas-recapagem/03-financeiro-2026-05-11.md) | recapagem caminhao | R$ 7,9 M | + R$ 0,4 M | R$ 0,3 M | R$ 1,5 M | R$ 0 | 0 | 🟡 2 |
| 2 | [`Cliente_6928E8`](03-extreme-grafica/03-financeiro-2026-05-11.md) | grafica industrial PCP | R$ 6,3 M | + R$ 0,7 M | R$ 1,2 M | R$ 0,2 M | R$ 0 | 0 | 🟡 3 |
| 3 | [`Cliente_09FEB1`](04-gold-comvis/03-financeiro-2026-05-11.md) | comunicacao visual | R$ 6,1 M | **- R$ 0,7 M** | R$ 1,1 M | R$ 0,7 M | R$ 0 | 0 | 🔴 3 |
| 4 | [`Cliente_731814`](05-martinho-cacambas/03-financeiro-2026-05-11.md) | mecanica pesada caminhao basculante (ADR 0194) | R$ 6,3 M | + R$ 1,3 M | **R$ 4,8 M** | R$ 3,4 M | R$ 0 | 0 | 🔴 2 |

**Total agregado:** receita combinada 12m ≈ R$ 26,6 M · resultado combinado ≈ + R$ 1,7 M (com 1 cliente em deficit).

### Observacao MRR / contratos ativos

**Todos os 4 clientes apresentam MRR = R$ 0 e zero contratos ativos** na tabela `CONTRATO`. Isso confirma o que [01-wr-sistemas/01-perfil.md](01-wr-sistemas/01-perfil.md) ja indicava: **OfficeImpresso legacy nao tem feature de recurring billing implantada nesses 4 clientes** — toda receita vem de OS/vendas avulsas via tabela `FINANCEIRO` com `TIPO='RECEBIDA'`. **Implicacao comercial:** nenhum dos 4 tera "MRR perdido" durante migracao — sao operacoes 100% transacionais (sells + financeiro), o que **simplifica o cutover** (sem precisar mapear recorrencias).

## 2. Concentracao Top 1 (Q3)

| Cliente | Top 1 cliente do cliente | % da receita 12m | Risco concentracao |
|---------|--------------------------|------------------:|--------------------|
| `Cliente_874398` (Vargas) | `Cliente_58E550` | 23.5% | baixo |
| `Cliente_6928E8` (Extreme) | `Cliente_*` (top) | **49.0%** | **moderado** (proximo a 50%) |
| `Cliente_09FEB1` (Gold) | `Cliente_*` (top) | 25.4% | baixo |
| `Cliente_731814` (Martinho) | `Cliente_*` (top) | 2.1% | muito baixo (carteira pulverizada) |

**Insight comercial:** Martinho tem **carteira mais pulverizada** dos 4 (top 1 = 2.1%) → operacao com muitos clientes pequenos avulsos (compativel com aluguel de cacamba pra construtoras). Extreme tem **concentracao moderada** (49%) → grafica industrial provavelmente atende 1-2 grandes contratos B2B.

## 3. Flags detectadas (ordem de severidade)

### 3.1 Vermelhas (atencao imediata)

| Cliente | Flag | Implicacao migracao |
|---------|------|----------------------|
| **`Cliente_09FEB1` (Gold)** | `deficit_operacional` — Resultado 12m = -R$ 0,7 M | Negocio com pressao de caixa atual. Migracao pode ser bem-recebida se reduzir custos operacionais (planos mensais vs licenca Delphi), mas **negociar com cautela sobre prazo de pagamento** |
| **`Cliente_731814` (Martinho)** | `inadimplencia_alta` — 76.7% da receita 12m em a_receber vencidas | **R$ 4,8 M em titulos vencidos vs R$ 6,3 M de receita anual = lastreio operacional fragil**. Resultado +R$ 1,3 M nao reflete realidade se titulos sao incobraveis. Migracao deve **priorizar feature de cobranca/inadimplencia automatica** (Asaas integration US-RB-044) |

### 3.2 Amarelas (monitorar)

| Cliente | Flag |
|---------|------|
| `Cliente_874398` (Vargas) | `pagar_maior_que_receber` — A pagar vencidas (R$ 1,5 M) > A receber vencidas (R$ 0,3 M) |
| `Cliente_6928E8` (Extreme) | `inadimplencia_moderada` — 19.2% da receita 12m vencidas + `concentracao_top_1_moderada` 49% |
| `Cliente_09FEB1` (Gold) | `inadimplencia_moderada` — 18.8% da receita 12m vencidas |

### 3.3 Info (nao bloqueante)

Todos os 4: `sem_mrr` — operacao puramente transacional (sem recurring). Nao e flag negativa — e caracteristica do segmento (clientes WR Sistemas legacy nao adotaram cobranca recorrente do OfficeImpresso).

## 4. Ordenacao sugerida pra pricing de migracao

> Criterio: cliente com mais receita + saude financeira solida + complexidade tecnica realista paga mais. Cliente em deficit ou inadimplencia alta recebe **pricing diferenciado** (planos pagaveis em parcelas, sem upfront).

### Tier S (premium) — abordar primeiro com proposta padrao

**1. `Cliente_874398` (Vargas — recapagem)**
- Receita 12m: R$ 7,9 M (maior do sample)
- Resultado: + R$ 0,4 M (operacao saudavel)
- Top 1 concentracao: 23.5% (carteira diversificada)
- **Flags:** apenas 1 amarela (a pagar > a receber — pode ser dinamica de fluxo de caixa normal)
- **Custo migracao tecnica:** moderado-alto (multi-placa cavalo+reboque e custom — ver perfil)
- **Pricing sugerido:** premium tier. Aceita pagar por modulo especializado Modules/OficinaAuto

**2. `Cliente_6928E8` (Extreme — grafica industrial PCP)**
- Receita 12m: R$ 6,3 M
- Resultado: + R$ 0,7 M (melhor margem do sample)
- Top 1 concentracao: 49% (risco moderado, mas estabilidade alta com cliente ancora)
- **Flags:** 2 amarelas, sem vermelhas
- **Custo migracao tecnica:** alto (PCP custom por centro de trabalho, 52k linhas — ver perfil)
- **Pricing sugerido:** premium tier + cobrar setup PCP custom como projeto separado

### Tier A (saudavel mas com flags) — abordar com cuidado

**3. `Cliente_09FEB1` (Gold — comunicacao visual)**
- Receita 12m: R$ 6,1 M (similar Extreme/Martinho)
- Resultado: **- R$ 0,7 M** (deficit operacional 12m)
- Inadimplencia: 18.8% (moderada)
- **Flags:** 1 vermelha + 1 amarela
- **Custo migracao tecnica:** medio (mapeia bem em Modules/ComunicacaoVisual ja em construcao — DT_PROMETIDO + status producao)
- **Pricing sugerido:** plano mensal (sem upfront grande). Foco em **caso de uso ROI** (mostrar como oimpresso reduz custo operacional → ajuda a sair do deficit). Bom **canary de cutover** pra Modules/ComunicacaoVisual mesmo assim — feature de prazo prometido + funil status = match ideal

### Tier B (atencao financeira) — abordar com plano de saude antes

**4. `Cliente_731814` (Martinho — mecanica pesada caminhao basculante · sub-vertical 4 CNAE 4520 · correcao ADR 0194; pre-correcao dizia "cacambas avulsas")**
- Receita 12m: R$ 6,3 M
- Resultado: + R$ 1,3 M (aparente lucro)
- **MAS** inadimplencia critica: R$ 4,8 M em titulos vencidos = **76.7% da receita anual**
- **Flags:** 1 vermelha
- **Custo migracao tecnica:** baixo (caso simples Modules/OficinaAuto — sem cavalo+reboque)
- **Pricing sugerido:** plano mensal flexivel + **proposta enfaticamente focada em feature cobranca automatica/Asaas** (US-RB-044). Sem feature pra resolver inadimplencia, migracao isolada nao agrega. Considerar **pacote consultoria de cobranca + sistema** como diferencial.

## 5. Resumo executivo (ordem de abordagem comercial)

| Ordem | Cliente | Tier | Receita | Flag principal | Estrategia |
|------:|---------|------|---------|----------------|------------|
| 1 | Vargas (recapagem) | S | R$ 7,9 M | dinamica fluxo caixa | Pitch premium tier — modulo OficinaAuto especializado |
| 2 | Extreme (grafica industrial) | S | R$ 6,3 M | concentracao 49% | Pitch premium + setup PCP separado como projeto |
| 3 | Martinho (cacambas) | B | R$ 6,3 M | inadimplencia 76,7% | Pitch focado em **cobranca automatica** (Asaas) como ROI principal |
| 4 | Gold (comvis) | A | R$ 6,1 M | deficit 12m | Pitch ROI redutor de custo + canary Modules/ComunicacaoVisual |

**Total receita ARR potencial dos 4:** ~R$ 26,6 M cobertos. Se take rate medio for 0.5% (proposito SaaS conservador), receita anual potencial dos 4 ≈ R$ 133k/ano = R$ 11k/mes (apenas desses 4 clientes).

## 6. Limitacoes da analise

1. **MRR/ARR aparentem zero** em todos os 4 — pode ser real (operacao avulsa) ou MENSALIDADE_FINANCEIRO subutilizada. Validar com Wagner se algum cliente cobra mensalidade fora dessa tabela
2. **A pagar vencidas** pode incluir lancamentos antigos nao quitados que nao serao pagos (ex: fornecedor que ja morreu, divida prescrita). Numero bruto pode subestimar saude
3. **Inadimplencia** Martinho 76% e suspeita — pode ser que **titulos antigos nunca foram baixados** (uso ruim do sistema, nao inadimplencia real). Confirmar com cliente antes de assumir como sinal
4. **Sample restrito**: so 4 dos 38 clientes legacy. Generalizar com cuidado
5. **Sem cruzamento heatmap UI v2**: cruzar com [HEATMAP-CONSOLIDADO.md](../2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md) pra entender complexidade de migracao por cliente

## 7. Proximos passos

- [ ] Wagner valida ordenacao pricing
- [ ] Para Tier S (Vargas/Extreme): preparar proposta concreta com SOW Modules/OficinaAuto e Modules/ComunicacaoVisual+PCP
- [ ] Para Martinho: investigar com cliente se R$ 4,8 M em vencidas e realidade ou uso ruim do sistema
- [ ] Para Gold: rodar heatmap mais fundo na operacao pra entender origem do deficit antes da call
- [ ] **Eliana [E]:** revisar este doc antes de uso externo (proposta cliente, deck investidor, etc) — ver [_LGPD.md](_LGPD.md) §7
- [ ] Apos primeiras 4 calls: criar `_ANALISE-FINANCEIRA-CROSS-CLIENTE-2026-06-XX.md` (snapshot mensal) — versionamento append-only

## 8. Refs

- [scripts/financial_snapshot.py](../../../scripts/financial_snapshot.py) — script gerador (apenas SELECT)
- [.claude/skills/officeimpresso-financial-snapshot/SKILL.md](../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md) — fluxo 10 passos canonico
- [memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md](../../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) — 8 queries SQL + schema completo
- [_LGPD.md](_LGPD.md) — base legal Art. 7º IX legitimo interesse + anonimizacao
- [_ANALISE-CROSS-CLIENTE.md](_ANALISE-CROSS-CLIENTE.md) — analise de operacao/vertical (sem dados financeiros)
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado por vertical

---

**Geracao:** automatica via `python scripts/financial_snapshot.py --batch` (2026-05-11). Output bruto JSON em `*/raw-03-financeiro-2026-05-11.json` (gitignored). Perfil com nomes reais em `*/03-financeiro-2026-05-11-COM-NOMES.md` (gitignored).
