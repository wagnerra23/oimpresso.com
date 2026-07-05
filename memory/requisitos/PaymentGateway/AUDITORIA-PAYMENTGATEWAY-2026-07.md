# AUDITORIA — PaymentGateway (Onda 2 · plano de aprofundamento das avaliações)

> **Por que este doc existe:** o `Check X` do `memory-health` (cobertura de auditoria) flagava PaymentGateway como o **único** módulo Tier-0 sem nenhum `AUDIT*.md` no dir de requisitos. Este doc é a lente que fecha o gap — diagnóstico consolidado, **sem implementação** (ADR 0170 status `proposto`, fase "later" = docs only até Wagner ativar).
> **Gerado:** 2026-07-05 · sessão executora da [Onda 2 do PLANO-APROFUNDAMENTO-AVALIACOES](../_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md) · fonte de nota: `module:grade PaymentGateway --detail --evolve` rodado no CT100 (`oimpresso-staging`, código = `origin/main`).
> **Não duplica** (T6): capacidade vs mercado vive na [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (67/100, 2026-07-03); buckets + batch de tasks vivem no [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md); execução da Onda 5 vive no [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md). Aqui: **nota interna D1-D9 + leitura de risco REGRA MESTRE + o que a nota esconde**.

## 1 · Snapshot module:grade — 2026-07-05

**Nota: 64/100 · bucket Bom** (baseline 2026-05-27: 60 · webhook 2026-07-05: 63 — trajetória subindo).

| Dimensão | Score | Leitura |
|---|---|---|
| D1 Multi-tenant Tier 0 | 28/30 | 5/5 Entities com BusinessScope · 21 test files cross-tenant · D1.c 2/3 (ver §3) |
| D2 Pest cobertura | 10/20 | 47 tests / 2 controllers (ratio 23.5, saturado) · **D2.b 0/8 é artefato de NOME** (ver §3) |
| D3 Documentação | 10/15 | SPEC+BRIEFING frescos · D3.c 0/3 (0 tsx — backend-only) · D3.d sem ADR `module:` frontmatter |
| D4 Arquitetura | 14/20 | FSM N/A justificado (module.json ADR 0159) · D4.d 0/4 (ver §3) |
| D5 Cliente real | 0/15 | **decisão, não gap** — ADR 0170 "later" fase 2; módulo não está em prod |
| D6 Performance | 9/10 | 2/2 controllers com `Inertia::defer` · sem paginate (neutro) |
| D7 LGPD | 10/10 | PiiRedactor + LogsActivity 5/5 models + retention_days 1825 — **teto** |
| D8 Segurança | 2/8 | D8.a throttle ausente nas rotas · D8.c 0 FormRequests / 12 controllers |
| D9 Observabilidade | 3/7 | **D9.a 0/23 Services com OTel — gap real nº 1** |

## 2 · Leitura de risco — REGRA MESTRE (valor/estoque)

O módulo É a camada que toca dinheiro (proibicoes.md §CÁLCULO DE VALOR ou ESTOQUE). Caminhos de valor e estado das defesas:

| Caminho de valor | Onde | Defesa hoje | Risco residual |
|---|---|---|---|
| Emissão boleto/Pix (valor da cobrança) | 5 drivers `emitirBoleto()` · Pix cob/cobv | 21 test files cross-tenant; contrato driver único | Sem OTel span → falha silenciosa de driver só aparece no laravel.log |
| **Baixa por retorno CNAB** (marca cobrança paga/cancelada) | `Jobs/CnabRetornoProcessor` | Match `nossoNumero + business_id` explícito, idempotência `paga_em`, audit `GatewayWebhookEvent`, OTel span próprio | Job é o ponto de maior blast-radius (baixa em lote); D1.c flagou constructor sem `$businessId` — ver §3 |
| **Webhook marca paga** (fraude = liberar sem pagar) | 6 webhook controllers | HMAC validado fail-secure (US-PG-002 `verificado@98cae0a`) + encrypted cast credenciais (US-PG-001 idem) | US-PG-003 (throttle 120/min + timestamp window + nonce) especificada e ABERTA — G1 da FICHA |
| Reconciliação push+pull | `ReconciliarCobrancaService` | single-source, job+polling | Sem OTel; divergência push vs pull não emite métrica |
| Refund/devolução | drivers (parcial) | flag `ASAAS_REFUND_ENABLED` default false | Contrato refund não-uniforme nos 6 drivers (G2 da FICHA) |

**Regra executora:** qualquer fix futuro nesses caminhos exige REGRA MESTRE — dupla confirmação por 2 caminhos independentes + tabela antes→depois + OK Wagner explícito. Nada disso foi implementado nesta onda (ADR 0170 later).

## 3 · O que a nota esconde (falso-negativos vs gaps reais)

**Artefatos de detecção (não são gap de código):**
- **D2.b 0/8** — a rubrica procura arquivos com NOME `MultiTenant`/`Smoke`/`Scaffold`. O módulo tem 47 tests (21 com pattern cross-tenant) mas nenhum arquivo com esses nomes. Fix barato quando o módulo ativar: criar os 3 canônicos (ou renomear equivalentes) — +8 raw.
- **D1.c 2/3** — `CnabRetornoProcessor` foi flagado (constructor sem `$businessId`), mas o job declara e implementa filtro explícito `nossoNumero + business_id` com `withoutGlobalScopes` + where (padrão worker ADR 0093). Confirmar constructor no PR de ativação; se o padrão for Job-por-ID legítimo, declarar `na_justified_v3` no SPEC (precedente: Financeiro D1.c).
- **D5 0/15** — não é gap: é o estado decidido (ADR 0170 fase 2 "later"; ADR 0105 cliente como sinal).

**Gaps reais (fila pra QUANDO Wagner ativar a fase 2):**
1. **D9.a — 0/23 Services com OTel** (−4 raw + trava D6.b): é observabilidade de DINHEIRO — span em `emitirBoleto`/`ReconciliarCobrancaService`/webhooks primeiro. Maior ROI da lista.
2. **US-PG-003 webhook hardening** (G1 da FICHA — throttle + timestamp + nonce): P0 de fraude/DoS, ~4h, já especificada no SPEC.
3. **D8.a/D8.c** — throttle nas rotas de webhook (pareia com o item 2) + FormRequests nos 12 controllers.
4. **D4.d/D3.d** — telemetry no caminho controller/service + ADR mãe com `module: PaymentGateway` no frontmatter (a 0170 não declara).

## 4 · Encaminhamento

- **Batch de tasks:** já proposto no [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (2026-07-03) — **aguarda aprovação Wagner** (nenhuma task auto-criada, regra do plano). A ordem G1→G4→G2→G3/G12 da FICHA §6 continua válida.
- **Check X:** zera com este doc (PaymentGateway era o único flagado). O check continua vigiando os demais Tier-0 (Compras/Financeiro/Fiscal/NfeBrasil/RecurringBilling) a cada PR.
- **Re-grade:** próxima medição relevante só após Wagner ativar a fase 2 (implementações mudam D2.b/D8/D9); até lá a nota 64 é o floor honesto.

---

**Trilha:** criado 2026-07-05 (Onda 2 do plano de aprofundamento — sessão executora [CC]). Doc é lente de diagnóstico; evoluir ESTE arquivo em re-auditorias futuras, nunca abrir paralelo (T6).
