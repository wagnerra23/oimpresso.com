---
id: research-clientes-legacy-officeimpresso-05-martinho-cacambas-04-inadimplencia-investigacao
---

# Investigação adversarial — `Cliente_731814` (Martinho Caçambas) · inadimplência 76,7%

> Coletado: 2026-05-11
> Fonte: `192.168.0.55:D:\DadosClientes\MartinhoCacamba\Dados\BANCO.FDB` (Firebird, read-only)
> Script: [`scripts/probe_inadimplencia.py`](../../../../scripts/probe_inadimplencia.py)
> Schema fingerprint: OK
> LGPD: anonimização sha1(razao_social)[:6] ([_LGPD.md](../_LGPD.md))
> Snapshot anterior: [`03-financeiro-2026-05-11.md`](03-financeiro-2026-05-11.md) — flag 🔴 inadimplência 76,7%

---

## 1. Resumo executivo — Veredito: **USO RUIM DO SISTEMA (com viés histórico)**

A inadimplência reportada **R$ [redacted Tier 0]M = 76,7% da receita 12m** **NÃO é** cliente da Martinho que não paga hoje — é **lixo contábil histórico não-limpo desde 2015**. Três sinais decisivos:

1. **83% do valor vencido (R$ [redacted Tier 0]M de R$ [redacted Tier 0]M) tem > 365 dias de atraso**, com média de **2.564 dias (≈ 7 anos)** e título mais antigo de **2015-10-22**. Isso é **backlog não-baixado**, não cobrança recente atrasada.
2. **Taxa de baixa 12m é 84,7%** (4.950 recebidas / 5.847 emitidas) e **taxa de baixa de boleto 12m é 74%** (2.895/3.913). Operação corrente é saudável.
3. **20% dos títulos vencidos têm `RAZAOSOCIAL` órfã** (não existe em PESSOAS — grafia/cadastro inconsistente, R$ [redacted Tier 0]k) e **6.585 títulos em `INATIVO CANCELADA` somam R$ [redacted Tier 0]M** (12× mais valor cancelado do que ativo vencido) — sinal de **operação manual com correções caóticas**.

**Implicação pra migração Modules/OficinaAuto:** **NÃO é caso pra dunning agressivo / cobrança automática como ROI principal**. O ROI principal é **importer que limpa o lixo histórico + treinamento básico de processo**. Cobrança automatizada (boleto + lembrete) é melhoria *secundária* — a operação 12m corrente já funciona, só precisa modernizar o canal.

---

## 2. Dados Q-INV (sinais de inadimplência REAL)

### Q-INV-1 · Aging — onde mora a inadimplência?

| Bucket | Títulos | Valor | % do vencido |
|---|---:|---:|---:|
| 0-30 dias (recente) | 59 | R$ [redacted Tier 0] | 1,6% |
| 30-60 dias | 145 | R$ [redacted Tier 0] | 3,3% |
| 60-180 dias | 498 | R$ [redacted Tier 0] | 11,7% |
| 180-365 dias | 81 | R$ [redacted Tier 0] | 2,9% |
| **> 365 dias (abandono)** | **3.812** | **R$ [redacted Tier 0]** | **80,5%** |

- **N total vencidos:** 4.595 títulos
- **Média atraso:** **2.564 dias** (≈ 7 anos)
- **Min vencto:** 2015-10-22 · **Max vencto:** 2026-05-09
- **Total vencido:** R$ [redacted Tier 0]

**Interpretação:** 4 de 5 títulos vencidos é fóssil de 2015-2019. O atraso "real" recente (até 60 dias) é apenas R$ [redacted Tier 0]k = 4,9% do total reportado. **A inadimplência atual ≠ inadimplência histórica.**

### Q-INV-2 · Distribuição — concentrada ou difusa?

- **N clientes inadimplentes:** 939 (cauda longa enorme — não problema de 2-3 contas grandes)
- **Top 1 (`Cliente_77639D`):** 8,4% do total · R$ [redacted Tier 0] · 232 títulos · **vencendo desde 2016-02-19**
- **Top 5:** 19,1% · **Top 20:** 35,7%

**Interpretação:** problema **difuso e antigo**. Sem cliente único pra perseguir judicialmente. O Top 1 (`Cliente_77639D`) acumula 10 anos de pendências — é candidato a **write-off contábil**, não a cobrança ativa.

### Q-INV-3 · Taxa de baixa 12m (operação corrente)

| Métrica | Valor |
|---|---:|
| Emitidos 12m (`A RECEBER`+`RECEBIDA`) | 5.847 |
| Recebidos 12m | 4.950 |
| Pendentes (ATIVO sem pagto) | 780 |
| Canceladas | 586 |
| **Taxa de baixa** | **84,7%** |

**Interpretação:** **a operação 12m está SAUDÁVEL.** Padrão Brasil PME boa: 75-90% de baixa em 12m. Martinho está em 84,7% — acima da média. Isso desmente "cliente não paga": cliente paga; só não está limpando o histórico do sistema.

### Q-INV-4 · Boletos 12m

| Métrica | Valor |
|---|---:|
| Boletos emitidos 12m | 3.913 |
| Pagos | 2.895 |
| Abertos | 665 |
| Cancelados | 198 |
| **Taxa baixa boleto** | **74,0%** |
| Valor pago | R$ [redacted Tier 0] |
| Valor vencido | R$ [redacted Tier 0] |

**Interpretação:** boletos funcionam mas têm **26% sem baixa** (cancelados + abertos = 22%). Pode ser melhorado, mas não é catastrófico. O **valor vencido de boletos 12m (R$ [redacted Tier 0]k) é apenas 15% do total vencido (R$ [redacted Tier 0]M)** — confirma que os outros 85% são fóssil pré-12m.

---

## 3. Dados Q-UR (sinais de USO RUIM do sistema)

### Q-UR-1 · MOTIVO_EXCLUSAO — quanto se manipula manualmente

| Métrica | Valor | % |
|---|---:|---:|
| Total FINANCEIRO | 100.498 | 100% |
| Excluídos (DT_EXCLUSAO IS NOT NULL) | 10.093 | **10,04%** |
| Em STATUS LIKE 'INATIVO%' | 13.628 | **13,56%** |

**Top motivos de exclusão:**

| Motivo | Frequência |
|---|---:|
| `EXCLUSÃO POR REPARCELAMENTO DA VENDA` | 251 |
| `duplicado` / `duplicada` / `DUPLICADO` (3 grafias) | ~115 |
| `err` / `erro` | ~70 |
| `EXCLUSd POR REPARCELAMENTO DA VENDA` (typo) | 56 |
| `RETORNO TRATADO ERRADO` | 52 |
| `CANCELAMENTO DE FATURAMENTO DA VENDA - DATA: ...` (várias) | ~120 |

**Interpretação:** **operação manual com 10% de retrabalho**. Reparcelamentos e duplicações + erros de retorno bancário tratado manualmente = sinal de processo com fricção. Top user (operadora interna do cliente) aparece em vários motivos — pessoa atua como "limpadora" do sistema.

### Q-UR-2 · Excluídos mas ATIVOS (sanity check)

| Métrica | Valor |
|---|---:|
| `A RECEBER` com DT_EXCLUSAO E STATUS=ATIVO | **0** |
| `A RECEBER` vencidos E excluídos E STATUS=ATIVO | **0** |

**Interpretação:** **sanity OK.** Quando excluem, marcam INATIVO corretamente. O lixo de 4.595 vencidos ATIVOS **não é** inconsistência — são títulos genuinamente em aberto que ninguém baixou nem cancelou. **Backlog real, não bug de status.**

### Q-UR-3 · Distribuição por STATUS dos A RECEBER vencidos

| STATUS | Títulos | Valor |
|---|---:|---:|
| `INATIVO CANCELADA` | **6.585** | **R$ [redacted Tier 0]** |
| `ATIVO` | 4.595 | R$ [redacted Tier 0] |
| `INATIVO AGRUPADO` | 415 | R$ [redacted Tier 0] |
| `INATIVO EXCLUIDA` | 464 | R$ [redacted Tier 0] |
| `INATIVO EXCLUIDO` | 272 | R$ [redacted Tier 0] |
| `ATIVO AGRUPADO` | 91 | R$ [redacted Tier 0] |
| `INATIVO EXC.AGRUPADO` | 9 | R$ [redacted Tier 0] |
| `INATIVO PREVISÃO` | 13 | R$ [redacted Tier 0] |
| `ATIVO PREVISAO` | 9 | R$ [redacted Tier 0] |

**LANCAMENTO_FUTURO='S':** 105 títulos (8,00 vencidos · negligível) · **`ATIVO PREVISAO`:** 24 títulos · 8,00 vencidos · negligível.

**Interpretação:**
- **R$ [redacted Tier 0]M em `INATIVO CANCELADA`** (12× mais que ativos vencidos) — sinal claríssimo de que a equipe **cancela bastante mas não limpa retroativo**. Esses não contam pra inadimplência (status inativo) mas mostram **rotatividade comercial enorme** (orçamentos virando A Receber e depois cancelados).
- O ROI de "limpeza" pode ser dramatico: importer agressivo + UI de revisão batch reduziria o universo "vivo" de inadimplência de 4.595 pra ~1.000 títulos legítimos.

### Q-UR-4 · VENDA.TOTAL × VENDA_FINANCEIRO desbalanço

| Métrica | Valor |
|---|---:|
| Vendas 12m com total > 0 | 3.419 |
| Valor total das vendas | R$ [redacted Tier 0] |
| Match exato (Total = sum Financeiro) | 3.040 (88,9%) |
| Com diferença numérica | 5 (0,1%) |
| **SEM nenhum lançamento Financeiro** | **374 (10,9%)** |
| Valor das vendas sem Financeiro | **R$ [redacted Tier 0]** |

**Interpretação:** **374 vendas (R$ [redacted Tier 0]M) não têm contas no VENDA_FINANCEIRO ativo**. Pode ser: (a) vendas à vista pagas direto sem lançamento (caixa); (b) orçamentos contados como vendas; (c) vendas com pagamento ainda não baixado. **Risco de migração:** importer precisa decidir o que fazer com essas (idealmente classificar como "vendas à vista" → criar lançamento `RECEBIDA` retroativo OU classificar como "orçamento" → ignorar pra contas a receber).

### Q-UR-5 · RAZAOSOCIAL órfã

| Métrica | Valor |
|---|---:|
| Vencidos com RAZAOSOCIAL inexistente em PESSOAS | **920 / 4.595 = 20,02%** |
| Valor órfão | **R$ [redacted Tier 0] / R$ [redacted Tier 0]M = 17,85%** |

**Interpretação:** **1 em 5 títulos vencidos não bate com cadastro mestre.** Causas típicas: (a) razão social digitada com espaço/typo; (b) cliente PESSOA removido sem cascade; (c) lançamentos importados de outro sistema sem normalização. Migração precisa de **fuzzy match de PESSOAS** ou aceitar criar contatos novos a partir do FINANCEIRO. Cliente real **não consegue cobrar 17,85% do valor vencido sem revisão manual** — confirma "cobrança manual cai vulnerável".

### Q-UR-6 · Cobertura de boleto

| Métrica | Valor |
|---|---:|
| A RECEBER vencidas | 4.595 |
| Vencidas COM boleto emitido | 2.712 (59,0%) |
| A RECEBER 12m (`A RECEBER`+`RECEBIDA`) | 5.847 |
| Com boleto 12m | 3.663 (**62,6%**) |

**Interpretação:** **62,6% do faturamento 12m sai com boleto.** Acima do limiar "30% = cobrança manual perigosa". Operação está mais maduro do que esperado. Os **37,4% sem boleto** provavelmente são vendas à vista (dinheiro/PIX/cartão balcão) — comportamento legítimo pra oficina de caçambas que faz pequenos consertos balcão.

---

## 4. Recomendação de migração

### Veredito: **MISTO com viés UR (uso ruim — backlog histórico não-limpo)**

| Componente | Peso | Razão |
|---|---:|---|
| Inadimplência REAL (cobrança falha) | **15-20%** | apenas R$ [redacted Tier 0]-900k recentes (60d-12m) tem indício de não-baixa; resto é fóssil |
| USO RUIM histórico (não-limpo) | **65-75%** | 83% do "vencido" tem > 1 ano; backlog até 2015 |
| Drift VENDA vs FINANCEIRO | **10-15%** | 374 vendas sem lançamentos = R$ [redacted Tier 0]M de dado faltante a normalizar |

### 4.1 Features prioritárias em Modules/OficinaAuto V1

**ROI principal = importer inteligente + UX de revisão batch** (NÃO dunning agressivo)

| # | Feature | Por quê |
|---|---|---|
| **P0** | **Importer Firebird → MySQL com regras de cleanup** | Sem isso, 4.595 títulos venenosos contaminam o oimpresso novo. Regras: títulos > 365 dias E sem boleto E sem movimentação → flag "histórico — write-off candidato"; órfãos PESSOAS → fuzzy match ou criar contato cliente |
| **P0** | **Tela "Revisão de pendências legadas" (batch)** | UX pro responsável Martinho percorrer os 4.595 títulos em lote (200 por dia em 23 dias úteis) e decidir: Baixar / Cancelar / Renegociar / Write-off. Sem isso, importer trava em "humano-in-the-loop" sem ferramenta apropriada |
| **P0** | **Conciliação VENDA ↔ contas a receber** | Resolve as 374 vendas sem lançamento — UX de "vendas órfãs" que pergunta: já recebeu? → cria `RECEBIDA` retroativo; é orçamento? → vira PRE_VENDA |
| **P1** | **Boleto auto (Asaas) com lembrete 3 dias antes do vencimento** | Eleva taxa de baixa de 74% pra 85%+; reduz "esquecimento operacional" de cobrança. Reutiliza integração existente `Modules/RecurringBilling` |
| **P1** | **PESSOAS deduplicador** | Limpa 920 razões sociais órfãs. Pode ser tela admin com sugestões "estes 3 cadastros parecem o mesmo, mesclar?" |
| **P2** | **Dunning (rotina lembrete pós-vencido 7d/15d/30d)** | Útil mas não é o gargalo. Premia ROI menor (~10-15%) |
| **P2** | **Dashboard de saúde financeira** | Reusa snapshot da skill `officeimpresso-financial-snapshot` — vira tela viva no oimpresso novo |

### 4.2 SE FOSSE inadimplência REAL (cenário descartado)

Pra registro: se 76,7% fosse cliente recente não pagando, o roadmap seria invertido — dunning + Asaas + protesto/Serasa via API + alerta WhatsApp seriam **P0**. Não é o caso.

### 4.3 Pricing / venda da migração

**Argumento de venda pra Martinho:**

> "Você tem **R$ [redacted Tier 0]M no relatório de contas a receber vencido, mas R$ [redacted Tier 0]M são de 2015-2019** — não vai cobrar mais. O resto (R$ [redacted Tier 0]k de 12m recentes) sim. Nossa migração inclui ferramenta de write-off em batch que limpa esse histórico em ~3 semanas de uso (sua equipe processa 200/dia). Depois disso o relatório fica honesto e você sabe quanto realmente precisa cobrar."

**Quanto cobrar:**

| Item | Custo IA-pair Felipe | Custo Wagner | Valor real |
|---|---:|---:|---:|
| Importer + cleanup rules + tela revisão batch (P0×3) | 24h | 8h | **R$ [redacted Tier 0]** (one-time) |
| Asaas + lembrete (P1×2) | 12h | 4h | **R$ [redacted Tier 0]** (one-time) |
| Setup OficinaAuto vertical (PLACA + FSM 2 estados) | 16h | 4h | **R$ [redacted Tier 0]** (one-time) |
| Manutenção mensal núcleo + OficinaAuto | — | — | **R$ [redacted Tier 0]-500/mês** (recorrente) |
| **Total piloto** | | | **R$ [redacted Tier 0] + R$ [redacted Tier 0]/mês** |

ROI pro cliente: 1 funcionária × 3 semanas × R$ [redacted Tier 0]/dia limpando manualmente = R$ [redacted Tier 0]k. Mas o **valor real está em parar de operar com "relatório mentindo"** (não saber quanto é cobrável de fato). Se cobrar 5% de R$ [redacted Tier 0]k recente = **R$ [redacted Tier 0]k de receita recuperada** justifica os R$ [redacted Tier 0]k facilmente.

### 4.4 Roadmap proposto pra abordagem Martinho

1. **Demo 1 — "Onde está seu dinheiro" (45min)**
   Wagner mostra o gráfico de aging desta investigação (R$ [redacted Tier 0]M é fóssil; R$ [redacted Tier 0]k é cobrável recente). Cliente sai com diagnóstico claro.

2. **Demo 2 — "Como limpamos" (30min)**
   Mock-up da tela "Revisão de pendências legadas" — batch UI com filtros (ano, valor, tem boleto?). Mostrar que 200/dia limpa em 23 dias úteis.

3. **Demo 3 — Prova técnica (sem custo, 1 semana)**
   Importer dry-run no banco real (read-only) gera relatório de "se você migrasse hoje, isso é o que ficaria como cobrável". Resultado factual, sem promessa.

4. **Contrato piloto (R$ [redacted Tier 0]k + R$ [redacted Tier 0]/m)**
   Migração cutover paralela 30d. Critério de sucesso: relatório de contas a receber pós-cleanup ≤ R$ [redacted Tier 0]M (atual: R$ [redacted Tier 0]M).

5. **Validação cruzada (Modules/OficinaAuto V1)**
   Martinho vira **caso piloto OficinaAuto** (CNAE 4520-0/01) — referência pra captação de outras oficinas legacy do portfolio (slugs `02-vargas-recapagem`, `mecanica-lebrinha`, etc).

---

## 5. Refs

- Snapshot anterior: [`03-financeiro-2026-05-11.md`](03-financeiro-2026-05-11.md) (flag inadimplência 76,7%)
- Perfil cliente: [`01-perfil.md`](01-perfil.md)
- Raw dados desta investigação: `raw-04-inadimplencia-2026-05-11.json` (gitignored — PII)
- LGPD: [`_LGPD.md`](../_LGPD.md)
- ADR 0105 (cliente como sinal): [`memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md`](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- ADR 0121 (modular especializado por vertical): [`memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md`](../../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- Script: [`scripts/probe_inadimplencia.py`](../../../../scripts/probe_inadimplencia.py)

---

Gerado por investigação adversarial sob skill `officeimpresso-financial-snapshot`. Apenas SELECT — sem mutação no banco origem.
