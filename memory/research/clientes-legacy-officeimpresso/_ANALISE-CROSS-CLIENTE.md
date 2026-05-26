---
title: Análise cross-cliente — 5 clientes legacy OfficeImpresso amostrados
status: live
date: 2026-05-11
audience: time interno + IA-pair
purpose: identificar padrões entre clientes pra calibrar Modules/<Vertical>, ordenar fila migração, e decidir features prioritárias
---

# Análise cross-cliente

> Comparativo entre os 5 clientes analisados em 2026-05-11. Cada perfil individual fica em `NN-slug/01-perfil.md`. Esta página identifica **padrões cross-cliente** que viram decisão arquitetural.

## 1. Matriz de classificação

| Cliente | Hash | Vertical real | Porte | Sinais-chave |
|---------|------|---------------|------:|--------------|
| [WR Sistemas](01-wr-sistemas/) | `Cliente_498223` | ERP fornecedor (empresa-mãe) | toy | uso interno |
| [Vargas](02-vargas-recapagem/) | `Cliente_874398` | **Oficina recapagem caminhão** | grande | 1.064 veículos · PLACA2 20% · CHASSI2 8% (cavalo+reboque) |
| [Extreme](03-extreme-grafica/) | `Cliente_6928E8` | **Gráfica industrial PCP** | muito grande | 52k linhas centro_trabalho |
| [Gold](04-gold-comvis/) | `Cliente_09FEB1` | **Comunicação visual** | grande | DT_PROMETIDO 85% (único do sample) · 29k EM PRODUÇÃO |
| [Martinho](05-martinho-cacambas/) | `Cliente_731814` | **Mecânica pesada caminhão basculante** (sub-vertical 4 ADR 0194 — pré-correção dizia "Oficina caçambas avulsas") | médio-grande | 91 veículos de CLIENTES · 96% PLACA pura · FSM 2 estados |

## 2. Segmentação por vertical (4 candidatos comerciais)

| Vertical | Clientes | % do sample (excluindo WR) | Pra Modules/<Vertical> |
|----------|---------:|---------------------------:|------------------------|
| **Oficina** (auto/caçamba/recapagem) | 2 (Vargas + Martinho) | **50%** | `Modules/OficinaAuto` — **agora qualificada** (ADR 0121 ⏸️ → ✅) |
| **Comunicação visual** | 1 (Gold) | 25% | `Modules/ComunicacaoVisual` — em construção (alinha) |
| **Gráfica industrial PCP** | 1 (Extreme) | 25% | `Modules/ComunicacaoVisual` com sub-feature PCP **ou** novo `Modules/Pcp` separado |

**Descoberta arquitetural mais importante:**

🎯 **2 dos 4 candidatos saudáveis são OFICINA** (Vargas + Martinho). Isso **qualifica o sinal pra `Modules/OficinaAuto`** ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) listava como "⏸️ aguardando sinal qualificado" — agora qualificado).

⚠️ **Estes 4 candidatos** representam os 4 nomes que Wagner amostrou em sessão 2026-05-11. O **sample total dos 38 clientes legacy** pode ter distribuição diferente — outros podem ser gráficas puras (Mhundo, Produart, Fixar, Zoom, etc). Sample expandido ([_ANALISE-CROSS-CLIENTE-COMPLETA-YYYY-MM-DD.md] futuro) recalibra.

## 3. Padrões cross-cliente

### 3.1 Datas de venda (uso real)

| Campo | WR2 | Vargas | Extreme | Gold | Martinho* | Mediana |
|-------|----:|-------:|--------:|-----:|----------:|--------:|
| DT_EMISSAO | 99.9% | 100% | 100% | 100% | (a coletar) | **~100%** |
| DT_FATURAMENTO | 52.7% | 35.0% | 92.9% | 92.4% | — | **~92%** (sem Vargas) |
| DT_COMPETENCIA | 18.6% | 100% | 75.3% | 7.5% | — | **disperso** |
| DT_PROMETIDO | (ausente) | (ausente) | (ausente) | 85.2% | — | **só comvis usa** |
| DT_ENVIO_FATURAMENTO | 3.2% | 47.4% | 3.8% | 6.5% | — | **só Vargas usa** |

\* Martinho Q2 não rodada v2 — pendente coleta complementar.

**Conclusão:**
- `DT_EMISSAO` e `DT_FATURAMENTO` são universais
- `DT_PROMETIDO` é **assinatura de comunicação visual** (Gold único do sample com >50%)
- `DT_COMPETENCIA` muito disperso — feature contábil que varia por contador externo de cada cliente
- `DT_ENVIO_FATURAMENTO` muito disperso — algumas oficinas usam (Vargas), maioria não

### 3.2 Status produção (3-4 fontes diferentes)

| Cliente | Fonte usada | Sinal |
|---------|-------------|-------|
| WR2 | catálogo vazio | toy |
| **Vargas** | **nenhuma** | oficina sem status estruturado — controla via OS aberta/fechada |
| **Extreme** | **PCP centro_trabalho** | controla via máquina, não via status |
| **Gold** | **VENDA.SITUACAO inline + lookup** | funil textual maduro |
| **Martinho** | **VENDA_SITUACAO + VENDA_ESTAGIO FSM** | único com FSM ativa |

**Implicação arquitetural:**
- "Status de produção" no oimpresso.com **NÃO É feature do core** — é feature do módulo vertical
- `Modules/OficinaAuto` precisa FSM simples (Vargas dispensa, Martinho usa 2 estados)
- `Modules/ComunicacaoVisual` precisa state-machine textual rico (Gold usa 7+ estados)
- `Modules/Manufacturing` ou novo `Modules/Pcp` cobre Extreme (PCP por centro)

### 3.3 Veículos (sinal oficina)

| Cliente | Total cadastrados | PLACA % | PLACA2 % | CHASSI % | CHASSI2 % | Interpretação |
|---------|------------------:|--------:|---------:|---------:|----------:|---------------|
| WR2 | 102 sujos | 0% | 0% | 0% | 0% | dados de teste |
| **Vargas** | **1.064** | **80%** | **20%** | **19%** | **8%** | **cavalo+reboque** (recapagem caminhão grande) |
| Extreme | 0 | — | — | — | — | gráfica pura |
| Gold | 0 | — | — | — | — | comvis pura |
| **Martinho** | **91** | **96%** | 0% | 0% | 0% | **PLACA simples** (caminhão basculante de cliente · sub-vertical 4 ADR 0194; ~~caçamba avulsa~~ era leitura pré-correção) |

**Conclusão Modules/OficinaAuto:**
- **PLACA simples cobre 80% dos casos** (Martinho 96% sem 2ª placa)
- **PLACA secundária + CHASSI secundário** é feature **avançada** pra atender Vargas (recapagem cavalo+reboque)
- Modelo `Veiculo` em `Modules/OficinaAuto` deve:
  - PLACA obrigatório
  - PLACA_SECUNDARIA opcional (cavalo+reboque)
  - CHASSI opcional (registrar quando OS pede)
  - CHASSI_SECUNDARIO opcional

### 3.4 Agrupamento financeiro (universal)

`CODFINANCEIRO_GRUPO` em FINANCEIRO usado por TODOS clientes (34-65%). Confirma que **Grade Avançada US-SELL-019/024** vale pra todo OfficeImpresso migrado.

### 3.5 Itens por venda

| Cliente | Média | 1 item | 2-5 | 6-10 | 11+ |
|---------|------:|------:|----:|----:|----:|
| WR2 | 1.30 | 92% | 7% | 0.5% | 0.3% |
| **Vargas** | **3.08** | 37% | **47%** | 12% | 3% |
| Extreme | 1.47 | 74% | 25% | 1% | 0.2% |
| Gold | 1.58 | 72% | 27% | 1% | 0.4% |
| Martinho | ~1 | (alta concentração 1 item — OS de mecânica pesada · troca peça hidráulica per-OS sub-vertical 4 ADR 0194) | — | — | — |

**Vargas é outlier** — 47% das OS têm 2-5 itens. Faz sentido em recapagem: 1 banda + 1 câmera de ar + 1 serviço aplicação = 3 itens.

**US-SELL-022 (sub-linha produtos) qualifica P1 SÓ se cliente é oficina-recapagem** (não generaliza).

## 4. Ordem sugerida de cutover (preliminar)

Critério: porte × custo migração × pré-requisito de módulo pronto.

| Ordem | Cliente | Razão | Pré-requisito |
|------:|---------|-------|---------------|
| 1º | **Martinho** | menor + mais simples — piloto `Modules/OficinaAuto` | Modules/OficinaAuto V0 (PLACA simples + FSM 2 estados) |
| 2º | **Gold** | bom fit `Modules/ComVis` em construção — canary de comvis maduro | Modules/ComVis V1 (DT_PROMETIDO + status produção textual) |
| 3º | **Vargas** | grande + custom (cavalo+reboque) — só após Martinho validar | Modules/OficinaAuto V1 (PLACA dupla + CHASSI duplo) |
| 4º | **Extreme** | maior + PCP industrial — exige `Modules/Pcp` ou Modules/ComVis avançado | Modules/Pcp V0 ou ComVis com sub-feature PCP |
| ... | demais 33 clientes | rodar análise individual em rounds futuros | — |

## 5. Decisões arquiteturais que esse heatmap qualifica

### 5.1 ADR 0121 — Modules/OficinaAuto ⏸️ → ✅

[ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) lista `Modules/OficinaAuto` como "⏸️ aguardando sinal qualificado". Com 2 clientes oficina identificados (Vargas grande + Martinho menor), **sinal qualificado obtido**.

**Próximo passo:** PR amend ADR 0121 movendo OficinaAuto pra "🟡 em construção" + criar `Modules/OficinaAuto/` scaffold seguindo skill `criar-modulo`.

### 5.2 Modules/ComVis precisa cobrir PCP

Extreme tem PCP estruturado (52k linhas centro_trabalho). 2 caminhos:
- (a) Sub-feature em `Modules/ComunicacaoVisual` (mais simples, segue ADR 0121)
- (b) Novo `Modules/Pcp` separado (independência maior, mais código)

**Recomendação preliminar:** (a) — sub-feature em ComVis, ativada por flag `business.legacy_features.pcp_centro_trabalho = true`. Mas exige ADR formal (futuro).

### 5.3 US-SELL-027 (schema discovery) P0 confirmada

Heatmap mostra que schema do Delphi varia entre clientes em 4 dimensões — sem schema discovery dinâmico, Grade Avançada quebra.

## 6. Limitações desta análise

- **Sample pequeno** (5 de 38 = 13%) — outros 33 podem ter padrões diferentes
- **Wagner deve aprovar** cada classificação aqui antes de virar decisão arquitetural (eu posso ter classificado errado de novo, como aconteceu com Vargas/Gold inicialmente)
- **Volume Vargas atípico** (3.979/24m mas 3.981 total = 2 anos só de dados) — outros clientes podem ter padrões mais antigos
- **WR2 = toy** (180 vendas/24m) — não conta como cliente real
- **Falta dado financeiro** — pendente rodar skill `officeimpresso-financial-snapshot` em todos

## 7. Próximos rounds sugeridos

| Round | Cliente | Por quê |
|-------|---------|---------|
| 6 | rodar Q2/Q6 completos em Martinho | completar perfil oficina simples |
| 7 | Mhundo OU Produart (gráfica orgânica) | mais 1 candidato pra qualificar "gráfica padrão" |
| 8 | rodar financial-snapshot nos 4 candidatos saudáveis | calibrar pricing migração |
| 9 | Wagner valida cada perfil aqui (correções factuais) | reduzir risco de classificação errada |

---

**Última atualização:** 2026-05-11 — 5 perfis iniciais criados após correções Wagner sobre Vargas (recapagem) e Gold (comvis). Próxima atualização: após Wagner validar perfis individuais.
