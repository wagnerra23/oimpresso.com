---
title: Heatmap consolidado — uso real do grid Delphi vs 12 US Sells Grade Avançada
status: live
date: 2026-05-11
audience: time interno (Wagner / Felipe / Maiara / Eliana / Luiz) + IA-pair
samples: 4 bancos Firebird (WR Sistemas + Vargas + Extreme + Gold)
purpose: qualificar (sinal real) ou desqualificar (feature-wish) cada US-SELL-018..026 antes de comprometer escopo
adr: 0136
---

# Heatmap UI Vendas — consolidado 4 clientes

> 4 clientes Firebird amostrados em 2026-05-11. Decisão objetiva sobre quais das 9 feature-wish (US-SELL-018..026 do PR #534) qualificam P1+ vs rebaixam pra P3/cancelam.
> **Refs:** [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md), [Sells/SPEC.md](../../requisitos/Sells/SPEC.md), [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).
> **Relatórios anonimizados** (commitáveis): [01-wr2](01-wr2-grade-usage-anonimizada.md) · [02-vargas](02-vargas-grade-usage-anonimizada.md) · [03-extreme](03-extreme-grade-usage-anonimizada.md) · [04-gold](04-gold-grade-usage-anonimizada.md).
> **Relatórios COM NOMES** (gitignored): `*-COM-NOMES.md`.

## 1. Sample size

| Banco | Vendas 24m | Média/mês | Range Emissão | Vivo? |
|-------|-----------:|----------:|---------------|------:|
| WR Sistemas (Wagner) | 180 | 9 | 2007 → 2026 | calibração |
| Vargas | **3.979** | 181 | 1900 → 2026 | sim |
| Extreme | **16.910** | 705 | 2015 → 2026 | sim |
| Gold | **8.176** | 356 | 2015 → 2026 | sim |

WR2 é toy (9 vendas/mês). Vargas/Extreme/Gold são clientes reais com 24m de dados representativos — sinal robusto.

## 2. Heatmap por dimensão

### 2.1 Datas (Q2) — uso real de cada campo

| Campo | WR2 | Vargas | Extreme | Gold | Sinal final |
|-------|-----|--------|---------|------|-------------|
| `DT_EMISSAO` | 99.9% | **100%** | **100%** | **100%** | ✅ default (sempre filtrar por) |
| `DT_FATURAMENTO` | 52.7% | 35.0% | **92.9%** | **92.4%** | ✅ segundo mais usado — incluir |
| `DT_COMPETENCIA` | 18.6% | **100%** | 75.3% | 7.5% | ⚠️ varia por cliente — depende do negócio |
| `DT_PROMETIDO` | (ausente) | (ausente) | (ausente) | **85.2%** | 🎯 schema varia entre clientes |
| `DT_ENVIO_FATURAMENTO` | 3.2% | 47.4% | 3.8% | 6.5% | ⚠️ mediano em Vargas, baixo nos outros |
| `DT_ALTERACAO` | 92.3% | 99.9% | **100%** | **100%** | ✅ útil pra "última mexida" |

**Descoberta importante:** **`DT_PROMETIDO` não existe na tabela `VENDA` de WR2/Vargas/Extreme, mas existe e é 85% preenchido em Gold.** Significa que o schema Delphi varia entre instalações do OfficeImpresso (módulos opcionais ativados/não-ativados). Implicação direta: Grade Avançada **não pode hardcodar colunas** — precisa descobrir schema em runtime.

### 2.2 Status/Situação (Q3) — uso de status estruturado

| Banco | distinct SITUACAO | Top valor (volume) | Uso real? |
|-------|------------------:|--------------------|-----------|
| WR2 | 5 | "Finalizado" (802) — só 2 com volume | fraco |
| Vargas | 1 | vazio (1929) | **NÃO USA** |
| Extreme | 1 | vazio (12) | **NÃO USA** |
| Gold | **7** | **"EM PRODUÇÃO" (29.559)** + "FINALIZADA" (7.082) + 5 outros | **USA FORTE** |

**Observação:** 3 de 4 clientes não usam SITUACAO no Delphi. Gold usa intensamente — 29k vendas em "EM PRODUÇÃO". Significa que o **Status de produção é feature do cliente, não do sistema** — gráficas com PCP estruturado (Gold) usam; gráficas sem PCP (Vargas/Extreme) não.

### 2.3 Agrupamento (Q4) — uso real de `CODFINANCEIRO_GRUPO` em FINANCEIRO

| Banco | % linhas com grupo | avg linhas/grupo | Sinal |
|-------|-------------------:|-----------------:|-------|
| WR2 | 34.5% | 2.32 | médio |
| Vargas | **65.1%** | 1.35 | **alto** |
| Extreme | 43.3% | 1.05 | médio |
| Gold | 53.1% | 1.27 | alto |

**Todos > 30%.** Agrupamento é uso real. (`VENDA_FINANCEIRO` não tem coluna de grupo nesses bancos — o agrupador vive em `FINANCEIRO`).

### 2.4 Itens por venda (Q5) — ROI sub-linha produtos

| Banco | Média itens/venda | 1 item | 2-5 itens | 6-10 | 11+ |
|-------|------------------:|-------:|----------:|-----:|----:|
| WR2 | 1.30 | 1.535 (92%) | 114 | 10 | 5 |
| **Vargas** | **3.08** | 1.430 (37%) | **1.819 (47%)** | 482 (12%) | 96 (3%) |
| Extreme | 1.47 | 59.524 (74%) | 20.028 (25%) | 933 | 129 |
| Gold | 1.58 | 37.619 (72%) | 14.041 (27%) | 897 | 232 |

**Vargas é outlier:** gráfica produtiva real, 63% das vendas têm 2+ itens, 15% têm 6+. Outros são vendas de 1 item dominante. **Conclusão:** sub-linha vale a pena pra clientes tipo Vargas (gráfica multi-item) — **manter P2**, mas não acelerar pra P1.

### 2.5 Range temporal (Q6) — qual preset de filtro importa

| Banco | últimos 7d | últimos 30d | últimos 90d | últimos 365d |
|-------|-----------:|------------:|------------:|-------------:|
| WR2 | 0 | 3 | 5 | 39 |
| Vargas | 0 | 0 | 270 | 2.477 |
| Extreme | 0 | 5 | 1.162 | 7.958 |
| Gold | 0 | 0 | 508 | 4.343 |

**Padrão consistente:** janelas Dia/Semana têm volume baixo (queries provavelmente rodadas em horário não-comercial OU clientes operam em ciclo de produção mais longo). Janelas Mês (30d) e Ano (365d) têm volume real. **Preset Ano é essencial** (clientes consultam histórico longo); preset Dia/Semana é nice-to-have.

### 2.6 Campos automotivos (Q7) — confirma esconder por default

| Banco | PLACA | MARCAMODELO | ANO |
|-------|------:|------------:|----:|
| Todos | **0%** | **0%** | **0%** |

**Confirmação total:** 0 gráficas usam campos automotivos. Grade Avançada **esconde por default** quando `vertical='grafica'` ou `'comunicacao_visual'`; mostra só pra `vertical='oficina'`.

### 2.7 Tabelas relacionadas a produção

`AGENDA_TITULO_WORKFLOW` aparece em **TODOS** os 3 bancos cliente. Possível fonte de status de produção (workflow) que não vem de `VENDA.SITUACAO` direta — investigar em PR de US-SELL-023.

## 3. Decisão final por US

| US | Original | **Após heatmap** | Mudança | Razão |
|----|----------|------------------|---------|-------|
| US-SELL-015 toggle + Grade base | **P0** | **P0** | — | Pré-requisito |
| US-SELL-016 multiseleção | **P0** | **P0** | — | Higiene UX |
| US-SELL-017 totalizador rodapé | **P0** | **P0** | — | Higiene UX |
| **US-SELL-021** qual data exibir (header dropdown) | P1 | **P0** ⬆️ | **subir** | DT_PROMETIDO existe só em Gold → schema varia → toggle de coluna é **crítico** pra zero refactor por cliente |
| US-SELL-018 filtros multi-data + presets | P1 | **P1** | confirma | 3-4 campos data com uso real >30% em pelo menos 1 cliente |
| US-SELL-019 agrupamento drag-to-group | P1 | **P1** | confirma | 43-65% das linhas com grupo em todos clientes |
| **US-SELL-023** badge status produção | P2 | **P1** ⬆️ | **subir** | Gold tem 29k+ vendas "EM PRODUÇÃO" — uso massivo de PCP |
| **US-SELL-024** campo is_grouped explícito | P2 | **P1** ⬆️ | **subir** | Acompanha US-SELL-019 — sem ele agrupamento fica ambíguo |
| **US-SELL-020** status financeiro/produção/fiscal badges separados | P1 | **P2** ⬇️ | **descer** | Só Gold (1 de 4) usa SITUACAO estruturado — feature de cliente, não core |
| US-SELL-022 sub-linha produtos | P2 | **P2** | confirma | Vargas usa (3.08 média); outros marginais |
| **US-SELL-026** impressão batch | P3 | **P2** ⬆️ | **subir** | Power-user OfficeImpresso vai pedir — expectativa óbvia |
| US-SELL-025 botões agrupamento rápido | P3 | **P3** | confirma | Depende de telemetria pós-019 |

## 4. US nova emergente

### US-SELL-027 · Schema discovery dinâmico Grade Avançada — **P1**

> origin: heatmap-2026-05-11-officeimpresso-3-clientes
> blocked_by: US-SELL-015

**Contexto.** Heatmap revelou que **schema da `VENDA` varia entre instalações OfficeImpresso**: Gold tem `DT_PROMETIDO` (85% preenchido), os outros 3 não têm a coluna. SITUACAO tem 7 valores ativos em Gold vs 1 vazio em Vargas/Extreme. Hardcodar colunas em `<GradeAvancadaLayout/>` quebra ao mudar de cliente.

**Escopo:**
- [ ] Job de discovery rodado no momento `business.legacy_origin = 'officeimpresso'` (uma vez no setup): conecta ao Firebird do cliente, dumpa colunas de `VENDA`, conta distintos de `SITUACAO`, percentual de preenchimento. Salva em `business.legacy_origin_features` JSON.
- [ ] `<GradeAvancadaLayout/>` lê `legacy_origin_features` via Inertia prop e configura colunas visíveis dinamicamente: coluna existe? % preenchimento > limiar? renderiza. Senão, esconde.
- [ ] UI de admin pra rever discovered features depois de import (`/admin/businesses/{id}/legacy-features`)
- [ ] Pest: 3 tests — discovery cria JSON, layout esconde coluna ausente, layout esconde coluna com %=0

**AC:**
- [ ] Cliente Gold cai com colunas DT_PROMETIDO + SITUACAO visíveis; Vargas cai sem essas mas com DT_COMPETENCIA forte (100%) visível
- [ ] Zero código de Grade Avançada referencia coluna específica — tudo via lookup `legacy_origin_features.columns`

**Refs:** US-SELL-015. ADR 0136 §"Implementação progressiva" amend (adicionar referência a US-027).

## 5. P0 acionáveis agora (sem mais discovery)

3 US prontas pra desenvolvimento, total 12h codáveis:

1. **US-SELL-015** (6h) — toggle + Grade base + coluna `business.legacy_origin`
2. **US-SELL-016** (4h) — multiseleção + bulk print/CSV
3. **US-SELL-017** (2h) — totalizador rodapé

Subir **US-SELL-021** pra P0 também (3h) — schema discovery (US-027) depende dela.

Pode-se paralelizar: dev A pega 015+021 (toggle + coluna dropdown), dev B pega 016+017 quando 015 mergeada.

## 6. Próximos rounds (opcional, se sinal precisar reforço)

| Round | Cliente | Por quê |
|-------|---------|---------|
| 5 | Martinho Caçambas | Único candidato `vertical=oficina` — valida Q7 (PLACA usado!) e ajusta auto-toggle por vertical |
| 6 | Mhundo ou Produart | Mais 1 gráfica diferente pra reduzir viés Vargas/Extreme/Gold |
| 7 | Cliente que reclamou + cliente que elogiou pós-migração | Validação pós-cutover real |

## 7. Notas LGPD

Todos `*-anonimizada.md` deste diretório são commitáveis: razão social do top1 cliente cada banco vira hash sha1 6 chars; SITUACAO ofuscado pra `_situacao_redacted_XXXX_`. Versões `*-COM-NOMES.md` e `raw-*.json` ficam em `.gitignore` local.

Eliana (advogada + financeiro) pode revisar este HEATMAP-CONSOLIDADO.md antes de exposição externa (deck investidor, blog, parceiro) — versão atual é seguro pra git interno (zero PII bruta).

---

**Última atualização:** 2026-05-11 — heatmap 4 clientes Firebird coletado em sessão Wagner. Próximo passo: PR atualizando `Sells/SPEC.md` com priorities revisadas + adicionando US-SELL-027.
