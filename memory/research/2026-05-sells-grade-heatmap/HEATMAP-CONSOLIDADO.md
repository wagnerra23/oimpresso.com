---
title: Heatmap consolidado v2 — uso real do grid Delphi vs 13 US Sells Grade Avançada
status: live
date: 2026-05-11
audience: time interno (Wagner / Felipe / Maiara / Eliana / Luiz) + IA-pair
samples: 5 bancos Firebird (WR Sistemas + Vargas + Extreme + Gold + Martinho)
purpose: qualificar (sinal real) ou desqualificar (feature-wish) cada US-SELL-018..027 antes de comprometer escopo
adr: 0136
version: v2 (correções Wagner — Q7 lê EQUIPAMENTO_VEICULO, Q3 lê 3 fontes, +Q8 PCP, +Q9 obra)
---

# Heatmap UI Vendas — consolidado 5 clientes (v2)

> **v2 correções (Wagner 2026-05-11 tarde):** Q7 estava buscando PLACA na tabela errada (`MENSALIDADE_FINANCEIRO`). Real fica em `EQUIPAMENTO_VEICULO`. Q3 também — status produção real vive em `VENDA_SITUACAO` (lookup) + `VENDA_ESTAGIO` (FSM) + `VENDA_PRODUTO_CENTRO_TRABALHO` (PCP), não em `VENDA.SITUACAO`. +Q8 (PCP) +Q9 (obra/instalação) adicionadas.
>
> **Refs:** [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md), [Sells/SPEC.md](../../requisitos/Sells/SPEC.md), [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).
> **Relatórios anonimizados** (commitáveis): [01-wr2](01-wr2-grade-usage-anonimizada.md) · [02-vargas](02-vargas-grade-usage-anonimizada.md) · [03-extreme](03-extreme-grade-usage-anonimizada.md) · [04-gold](04-gold-grade-usage-anonimizada.md) · [05-martinho](05-martinho-grade-usage-anonimizada.md).
> **Relatórios COM NOMES** (gitignored): `*-COM-NOMES.md`.

## 1. O que cada cliente realmente é (revisado com sinais corretos)

| Cliente | Vendas 24m | Veículos | PLACA | PLACA2 | CHASSI | PCP (centro_trab) | Status (situacao+estagio) | **Vertical real** |
|---------|-----------:|---------:|------:|-------:|-------:|------------------:|--------------------------:|-------------------|
| **WR Sistemas (Wagner)** | 180 | 102 (sujos) | 0% | 0% | 0% | 0 | 16+10 (catalog vazio) | ERP fornecedor (toy) |
| **Vargas** | 3.979 | **1.064** | **80%** | **20%** | **19%** | 0 | 1 distinct (vazio) | **oficina recapagem caminhão** (cavalo+reboque) |
| **Extreme** | 16.910 | 0 | — | — | — | **52.473** | 1 distinct (vazio) | **gráfica industrial PCP** |
| **Gold** | 8.176 | 0 | — | — | — | 0 | 7+5 (29k EM PRODUÇÃO) | **comunicação visual** (sob demanda c/ prazo) |
| **Martinho Caçambas** | (n/a Q1) | 91 | **96%** | 0% | 0% | 0 | 8+6 (status inline) | **mecânica pesada caminhão basculante** (sub-vertical 4 ADR 0194 — pré-correção dizia "oficina caçamba avulsa") |

**Correção 2026-05-11 (Wagner):** v2 inicial classificou Vargas como "gráfica + frota" e Gold como "gráfica genérica". Errado:
- **Vargas é oficina GRANDE de recapagem de caçamba de caminhão** — os 1.064 veículos são os caminhões dos clientes deles. PLACA2/CHASSI2 = cavalo+reboque (semi-reboque tem placa+chassi próprios).
- **Gold é comunicação visual** — banner/fachada/sinalização sob demanda. DT_PROMETIDO 85% + 29k EM PRODUÇÃO = funil produção formal pra comvis personalizada.

**Descoberta arquitetural mais importante do exercício:**

🎯 **2 de 4 candidatos saudáveis são OFICINA** (Vargas grande + Martinho menor). Isso **qualifica o sinal pra `Modules/OficinaAuto`** ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) listava como "⏸️ aguardando sinal qualificado" — agora qualificado).

**Demais descobertas:**
- **Extreme é gráfica industrial com PCP por centro de trabalho** (52k linhas em `VENDA_PRODUTO_CENTRO_TRABALHO`) — feature `Modules/ComunicacaoVisual` ou novo `Modules/Pcp` precisa cobrir
- **Gold é canary natural pra `Modules/ComunicacaoVisual`** — DT_PROMETIDO + status produção textual + zero veículos/PCP = comvis "padrão" sob demanda
- **Martinho é piloto natural pra `Modules/OficinaAuto`** — caso simples (PLACA única, 8 status) antes de migrar Vargas (cavalo+reboque, custom)
- **Schema do Delphi varia muito entre clientes** — DT_PROMETIDO só Gold; PCP só Extreme; multi-placa só Vargas → **US-SELL-027 (schema discovery) é peça arquitetural central**, P0 confirmada

## 2. Heatmap por dimensão

### 2.1 Datas (Q2) — uso real de cada campo

| Campo | WR2 | Vargas | Extreme | Gold | Martinho | Sinal final |
|-------|-----|--------|---------|------|----------|-------------|
| `DT_EMISSAO` | 99.9% | 100% | 100% | 100% | 100% | ✅ default universal |
| `DT_FATURAMENTO` | 52.7% | 35.0% | **92.9%** | **92.4%** | (verificar) | ✅ segundo mais usado |
| `DT_COMPETENCIA` | 18.6% | **100%** | 75.3% | 7.5% | (verificar) | ⚠️ Vargas atípico — varia muito |
| `DT_PROMETIDO` | (ausente) | (ausente) | (ausente) | **85.2%** | (verificar) | 🎯 **schema varia** — só Gold |
| `DT_ENVIO_FATURAMENTO` | 3.2% | 47.4% | 3.8% | 6.5% | (verificar) | ⚠️ Vargas usa, outros não |
| `DT_ALTERACAO` | 92.3% | 99.9% | 100% | 100% | (verificar) | ✅ útil pra "última mexida" |

(Martinho Q2 não rodado v2 — script v2 não foi re-rodado nele. Já temos sinal robusto dos outros 4.)

### 2.2 Status estruturado (Q3 v2) — **CORRIGIDO**

3 fontes distintas:
- **Inline `VENDA.SITUACAO`** (string) — denormalizado direto na venda
- **Tabela `VENDA_SITUACAO`** (lookup) — catalog de situações
- **Tabela `VENDA_ESTAGIO`** (FSM) — máquina de estados do funil

| Cliente | Inline distinct | VENDA_SITUACAO | VENDA_ESTAGIO | Interpretação |
|---------|----------------:|---------------:|--------------:|---------------|
| WR2 | 5 (Finalizado domina) | 16 | 10 | Catalog rico mas zero uso prático |
| Vargas | **1 (vazio)** | 0 | 0 | **Não usa status nenhum** — opera só financeiro |
| Extreme | **1 (vazio)** | 0 | 0 | **Não usa status nenhum** — mas usa PCP por centro_trabalho (Q8) |
| **Gold** | **7** (29k EM PRODUÇÃO) | 5 | 0 | Funil textual maduro, sem FSM formal |
| **Martinho** | **8** | 6 | **2** | Funil + FSM em uso (oficina precisa rastrear OS) |

**Padrões diferentes entre clientes:**
- Vargas/Extreme não usam status — gerência fluxo via tabelas filhas (`VENDA_PRODUTO_CENTRO_TRABALHO`)
- Gold/Martinho usam `VENDA.SITUACAO` inline + lookup
- Só Martinho tem FSM (`VENDA_ESTAGIO` ativo)

Conclusão: **US-SELL-020 e US-SELL-023 dependem de qual fonte de status o cliente usa.** Schema discovery US-SELL-027 detecta qual ativar.

### 2.3 Agrupamento (Q4) — uso de `CODFINANCEIRO_GRUPO`

| Cliente | % linhas FINANCEIRO com grupo | avg linhas/grupo |
|---------|------------------------------:|-----------------:|
| WR2 | 34.5% | 2.32 |
| Vargas | 65.1% | 1.35 |
| Extreme | 43.3% | 1.05 |
| Gold | 53.1% | 1.27 |

Todos > 30%. **US-SELL-019 (agrupamento) + US-SELL-024 (is_grouped explícito) P1 confirmadas.**

### 2.4 Itens por venda (Q5)

| Cliente | Média itens/venda | 11+ itens |
|---------|------------------:|----------:|
| WR2 | 1.30 | 5 |
| **Vargas** | **3.08** | 96 |
| Extreme | 1.47 | 129 |
| Gold | 1.58 | 232 |

Vargas é único outlier alto. **US-SELL-022 P2 mantida.**

### 2.5 Range temporal (Q6)

| Cliente | últimos 30d | últimos 90d | últimos 365d |
|---------|------------:|------------:|-------------:|
| WR2 | 3 | 5 | 39 |
| Vargas | 0 | 270 | 2.477 |
| Extreme | 5 | 1.162 | 7.958 |
| Gold | 0 | 508 | 4.343 |

Preset Ano essencial. Preset Mês útil. Dia/Semana = baixo volume.

### 2.6 Veículos (Q7 v2 — CORRIGIDO em EQUIPAMENTO_VEICULO)

| Cliente | Total veículos | PLACA | PLACA2 | CHASSI | CHASSI2 | TIPO |
|---------|---------------:|------:|-------:|-------:|--------:|-----:|
| WR2 | 102 (lixo) | 0% | 0% | 0% | 0% | 0% |
| **Vargas** | **1.064** | **80%** | **20%** | **19%** | **8%** | 10% |
| Extreme | 0 | — | — | — | — | — |
| Gold | 0 | — | — | — | — | — |
| **Martinho** | **91** | **96%** | 0% | 0% | 0% | 0% |

**Vargas e Martinho usam veículo.** Vargas tem campos secundários (PLACA2/CHASSI2/CHASSI) — frota mista (cavalo+reboque OU múltiplos veículos por cliente). Martinho só PLACA pura (caminhões de CLIENTES que entram pra peça/serviço · sub-vertical 4 mecânica pesada ADR 0194 — pré-correção dizia "caçamba avulsa").

### 2.7 PCP estruturado (Q8 — nova)

| Cliente | VENDA_PRODUTO_ETAPA | VENDA_PRODUTO_CENTRO_TRABALHO |
|---------|--------------------:|-----------------------------:|
| WR2 | 0 | 0 |
| Vargas | 0 | 0 |
| **Extreme** | 0 | **52.473** |
| Gold | 0 | 0 |
| Martinho | 0 | 0 |

**Extreme é a única gráfica industrial PCP do sample.** 52k linhas em `VENDA_PRODUTO_CENTRO_TRABALHO` = rastreia cada item de cada venda por centro/máquina. Sinal forte: Grade Avançada precisa coluna opcional "Centro Trabalho" quando essa tabela está populada.

### 2.8 VENDA_OBRA (Q9 — nova)

Tabela **não existe** em nenhum dos 5 bancos. Conceito de "obra/instalação física" não é nativo do OfficeImpresso. Descartar — pra Modules/ComunicacaoVisual usar `VENDA_ENDERECO_ENTREGA` (já existe).

## 3. Decisão final por US (v2 com correções)

| US | v1 (manhã) | **v2 (correções)** | Mudança v1→v2 | Razão |
|----|------------|--------------------|---------------|-------|
| US-SELL-015 toggle + Grade base | P0 | **P0** | — | Pré-requisito arquitetural |
| US-SELL-016 multiseleção | P0 | **P0** | — | Higiene UX |
| US-SELL-017 totalizador rodapé | P0 | **P0** | — | Higiene UX |
| **US-SELL-021** qual data dropdown | P0 | **P0** | — | DT_PROMETIDO só Gold confirma schema varia |
| US-SELL-018 filtros multi-data | P1 | **P1** | — | uso real >30% em todos |
| US-SELL-019 agrupamento | P1 | **P1** | — | 43-65% das linhas |
| US-SELL-022 sub-linha produtos | P2 | **P2** | — | Vargas 3.08 (outlier) |
| US-SELL-024 is_grouped explícito | P1 | **P1** | — | acompanha 019 |
| US-SELL-025 botões rápidos | P3 | **P3** | — | depende telemetria |
| **US-SELL-026** impressão batch | P2 | **P2** | — | expectativa óbvia OfficeImpresso |
| **US-SELL-020** status badges | P2 | **P2** | — | só 2 de 5 (Gold/Martinho) usam |
| **US-SELL-023** badge produção | P1 | **P1** | — | 3 fontes diferentes detectadas (lookup/FSM/PCP) |
| **US-SELL-027** schema discovery | P1 (nova) | **P0** ⬆️ | **subir** | descoberta Vargas mostra schema varia muito mais do que imaginávamos — pré-requisito pra Grade funcionar com clientes mistos |

### Nova nuance — US-SELL-028 (nova, P2): Auto-deteção de vertical-mixto

> origin: heatmap-v2-2026-05-11-vargas-hibrido
> blocked_by: US-SELL-027

**Contexto.** Vargas é gráfica + frota (1.064 veículos cadastrados COM placa, mas também 3.979 vendas de produtos gráficos em 24m). Premissa "1 business = 1 vertical" do `oimpresso.com/Modules/<Vertical>` quebra. **Discovery deve aceitar features múltiplas no mesmo business.**

**Escopo:**
- [ ] `business.legacy_origin_features` JSON aceita estrutura `{ "verticais_detectados": ["grafica", "frota"], "evidencias": {...} }`
- [ ] `<GradeAvancadaLayout/>` mostra colunas de TODOS verticais detectados (não escolhe um)
- [ ] UI admin permite priorização visual (ex: gráfica primária + frota secundária — colunas frota colapsadas por default)

**Refs:** US-SELL-027, [HEATMAP §1](#1-o-que-cada-cliente-realmente-é-revisado-com-sinais-corretos).

## 4. P0 acionáveis agora (revisado v2)

**5 US prontas pra desenvolvimento, total ~21h codáveis:**

1. **US-SELL-015** (6h) — toggle + Grade base + coluna `business.legacy_origin`
2. **US-SELL-016** (4h) — multiseleção + bulk
3. **US-SELL-017** (2h) — totalizador rodapé
4. **US-SELL-021** (3h) — header dropdown qual data
5. **US-SELL-027** (6h ↑ de 5h) — schema discovery completo (+ Q7 EQUIPAMENTO_VEICULO + Q8 PCP)

Ordem sugerida:
- Sprint A: 015 + 027 (paralelas — 027 não bloqueia 015 mas habilita as outras)
- Sprint B: 021 + 016 + 017 (paralelas após 015)

## 5. Próximos rounds opcionais

| Round | Cliente | Por quê |
|-------|---------|---------|
| 6 | Wagner roda script v2 em Martinho com Q2/Q6 completos | Validar DT_PROMETIDO em oficina vs gráfica |
| 7 | 1 cliente com `VENDA_PRODUTO_ETAPA` populado | Achar gráfica que usa ETAPA (não só CENTRO_TRABALHO como Extreme) |
| 8 | Cliente que reclamou pós-cutover real | Validação pós-migração |

## 6. Notas LGPD

Mesmo padrão da v1: `*-anonimizada.md` commitáveis, `*-COM-NOMES.md` + `raw-*.json` em `.gitignore`. Razão social sha1-hashed; SITUACAO ofuscado.

---

**Última atualização:** 2026-05-11 tarde — correções Q3/Q7 + add Q8/Q9 (Wagner apontou Vargas usa PLACA/CHASSIS). 1 nova US (US-SELL-028 vertical-mixto). US-SELL-027 sobe pra P0 (era P1). Total: **5 P0 + 4 P1 + 4 P2 + 1 P3 = 14 US**.
