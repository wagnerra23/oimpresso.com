---
number: 172
title: "Deprecar Modules/Accounting e consolidar contabilidade operacional no Modules/Financeiro"
status: aceito
decided_at: "2026-05-20"
accepted_at: 2026-05-20
decided_by: [W]
authors: [wagner]
supersedes:
  - memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md
  - memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md
derived_adrs:
  - memory/decisions/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md
ref_plano: memory/requisitos/Accounting/DEPRECATION-PLAN.md
ref_inspecao: memory/requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md
ondas: 7 (Onda 0 + E1-E6)
estimate_corrido: ~26 semanas (dominado por 60d+90d waits)
estimate_trabalho_ativo: ~18d úteis distribuídos
slug: 0172-deprecar-modulo-accounting-fundir-financeiro
type: adr
authority: canonical
lifecycle: ativo
---

# ADR 0172 — Deprecar Modules/Accounting e consolidar contabilidade operacional no Modules/Financeiro

## Status

**accepted** (Wagner aprovou 2026-05-20 após inspeção forense + confirmação ROTA LIVRE Simples Nacional. Onda 0 audit DB já parcialmente executada na mesma sessão — SQLs em prod confirmaram 36 títulos `origem=venda` biz=4 via Observer + zero cross-imports de Accounting em outros módulos.)

## Contexto

Em **2026-04-24** a ADR [`Financeiro/arq/0005-financeiro-vs-accounting-paralelo.md`](../../requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) foi aceita decidindo **MANTER paralelos** Modules/Financeiro (operacional) e Modules/Accounting (contábil formal). A irmã ADR [`Accounting/arq/0001-contabilidade-isolada-do-financeiro-transacional.md`](../../requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md) (aceita 2026-04-22) chumbou o mesmo princípio: Accounting é "espinha dorsal contábil isolada do transacional".

Em **2026-05-20** (27d depois), fatos mudaram a estimativa de risco/benefício do paralelo:

### O que mudou de 2026-04-24 → 2026-05-20

1. **Financeiro evoluiu 9 ondas (Onda 22→31)** com features que cobrem operacionalmente 60-70% das capacidades do Accounting:
   - Onda 23 OCR boleto (US-FIN-029, killer feature vs Conta Azul)
   - Onda 24-28 governance Wave 11-28 (LGPD PiiRedactor + audit logger + multi-tenant tagging)
   - Onda 19 Conciliação OFX (`ConciliacaoController` — superior operacional ao `ReconcileController` Accounting)
   - Onda 31 **Portal Advisor** (US-FIN-037, entregue 2026-05-20) — guard `web-advisor` isolado, contador externo loga grant-based em `advisor_business_access`, faz SPED **fora do sistema** no software dele (Domínio/Sage/Alterdata)
   - 9 cobranças + 20 migrations + 14 entities + 46 pages .tsx Cowork-aprovadas

2. **Accounting estagnou** — última feature 2022-07-25 (4 anos atrás); 2026 trouxe APENAS governance (Wave J thin services, Wave 11 LGPD audit logger, Wave 13 multi-tenant tagging, Wave 23 D9.c HealthCommand) **sem zero feature de negócio**.

3. **Inspeção forense [`INSPECAO-FORENSE-2026-05-20.md`](../../requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md)** (505 linhas, 24 capacidades mapeadas) **refutou 3 hipóteses centrais** que sustentavam ARQ-0005:
   - **Hipótese 1 (BRIEFING linha 25):** "JournalEntry gerado automaticamente em vendas/compras pagas via observer/listener". **REFUTADA**: pastas `Listeners/`, `Observers/`, `Subscribers/`, `Jobs/` **NÃO EXISTEM** em Modules/Accounting. Criação só manual via UI (`/accounting/journal_entry/store` ou `/accounting/transactions/map_to_chart_of_account`).
   - **Hipótese 2 (BRIEFING linha 21):** "Espinha dorsal pra Vestuario, Financeiro, NfeBrasil, RecurringBilling". **REFUTADA**: grep `Modules\Accounting` retorna **0 arquivos fora do módulo** importando o namespace; ZERO referência cross-módulo.
   - **Hipótese 3 (ARQ-0005 + ARQ-0001):** tabelas com prefixo `accounting_*`. **REFUTADA**: código real usa nomes nus (`chart_of_accounts`, `journal_entries`, `accounts`, `transfers`, `branch_capital`, `budgets`, `payment_details`, `account_subtypes`, `account_detail_types`). Drift catalogado mas nunca corrigido por errata (corrige-se na ADR 0173 derivada).

4. **ROTA LIVRE biz=4 (único cliente piloto pago)** confirmado **Simples Nacional** por Wagner 2026-05-20:
   > "ROTA LIVRE Simples Nacional. Pode fazer tudo isso. Não achei nada que proíba extinguir. Deveria construir tudo no Financeiro."
   
   LC 123/2006: Simples Nacional NÃO obriga ECD nem ECF. Apenas DAS mensal + DEFIS anual (sem balanço). Portanto **deprecar Accounting NÃO PIORA SPED Larissa** — situação atual é "Accounting não entrega SPED, e Larissa não precisa de SPED".

5. **VERDADE DE CAMPO Larissa** confirmada por código real (DEPRECATION-PLAN.md seção 3):
   - Tela canon Inertia [`Sells/Index.tsx`](../../../resources/js/Pages/Sells/Index.tsx) já mostra `payment_status` + `total_paid`
   - Observers `TransactionObserver` + `TransactionPaymentObserver` registrados em `FinanceiroServiceProvider` (linhas 60-61) sincronizam automaticamente `transactions` → `fin_titulos` + `fin_titulo_baixas` + `fin_caixa_movimentos`
   - Tela [`Financeiro/Unificado/Index.tsx`](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx) (Cockpit V2, US-FIN-013+020) consolida visualmente
   - Pergunta literal Wagner ("até isso vai ter que ir pro Financeiro?") JÁ ESTÁ no Financeiro hoje em produção

6. **Portal Advisor (US-FIN-037)** entregue 2026-05-20 oferece mitigação conceitualmente superior à export batch: contador EXTERNO **loga no sistema** com guard `web-advisor`, vê dados do(s) businesses que ganhou acesso, faz SPED no software dele. Modelo subverte premissa do paralelo "precisamos de SPED interno".

### Sinais auxiliares de zumbi (inspeção forense seções 2.4, 5, 6)

- Última migration Accounting: **2022-07-25** (~4 anos)
- Zero commit feature de negócio Accounting em 2026
- Zero cross-import: `grep -rn "use Modules\\Accounting"` fora do módulo = **0 files**
- Zero evento disparado por Accounting: pasta `Events/` não existe + `event(new...)` = 0 hits
- Zero schedule artisan: `grep "accounting" app/Console/Kernel.php` = 0 hits
- Coluna `transactions.journal_entry_id` adicionada 2022-02-23 — só preenchida via UI manual (zero uso em Sells/Compras/NfeBrasil/Vestuario/RecurringBilling)

## Decisão

**Deprecar Modules/Accounting em 7 ondas (Onda 0 audit + E1-E6) consolidando contabilidade operacional no Modules/Financeiro**, com SPED Contábil ECD/ECF outsourced via Portal Advisor (US-FIN-037 — contador externo loga e exporta no software dele).

Plano operacional completo em [`memory/requisitos/Accounting/DEPRECATION-PLAN.md`](../../requisitos/Accounting/DEPRECATION-PLAN.md). Resumo das ondas:

| Onda | O que faz | Gate Wagner |
|---|---|---|
| **Onda 0** | Audit produção (3 SQLs + smoke biz=4) | Aprova dados retornados |
| **E1** | ADRs governance (0172 + 0173 promoção) | Promove `proposals/` → `accepted` |
| **E2** | `@deprecated` PHPDoc em 12 Controllers + 10 Services + 8 Entities | Review code |
| **E3** | UI freeze (sidebar + 94 routes 301 + canary biz=4 24h + Larissa avisada 7d antes) | curl -sv URLs + smoke |
| **E4** | Archive mysqldump 5 tabelas (AES-256 S3 + LGPD PII redact) + view bridge 60d | Cross-tenant Pest + arquivo validado |
| **E5** | `git rm Modules/Accounting/` + cleanup permissions/provider/module.json | 60d wait + zero log error |
| **E6** | DROP tabelas DB (`journal_entries`, `chart_of_accounts`, `account_subtypes`, `account_detail_types`, `payment_details`, `transfers`, `branch_capital`) + SCOPE/BRIEFING/handoff/proibicoes update | 90d wait + 2ª mysqldump |

### Pré-condições já atendidas

- [x] Pré-cond #2: Regime tributário Larissa = **Simples Nacional** (Wagner 2026-05-20)
- [x] Pré-cond #4: Errata drift `accounting_*` resolvida via ADR 0173 derivada
- [x] Pré-cond #7 (nova): Verdade de campo AR Larissa = JÁ no Financeiro (DEPRECATION-PLAN seção 3)
- [ ] Pré-cond #1, #3, #5, #6: pendentes Onda 0 (3 SQLs + smoke biz=4) — NÃO bloqueiam aprovação desta ADR

### Mapping destino tabelas

- **PRESERVE in-place** (não touch): `payment_types`, `countries`, `accounts`, `account_transactions`, `accounts_legacy_map` (todas UltimatePOS core ou Financeiro)
- **ARCHIVE** (mysqldump AES-256 S3, retention LGPD 5y): `chart_of_accounts`, `journal_entries`, `account_subtypes`, `account_detail_types`, `payment_details`
- **DROP**: `transfers`, `branch_capital`, coluna `transactions.journal_entry_id`
- **PRESERVE archive futuro**: `budgets` (vira US-FIN-NNN Onda 35+ — Financeiro NÃO TEM orçamento hoje)

## Alternativas consideradas

### Alt A — Manter status quo (paralelo)

**Rejeitada porque:**
- ARQ-0005 estava certa em 2026-04-24, errada em 2026-05-20 (Portal Advisor + Onda 22-31 mudaram o terreno)
- Drift entre BRIEFING e código real (3 falsidades catalogadas) deteriora governance Tier 0
- Manter Accounting custa esforço de manutenção zero-benefício (Wave J/W11-W28 só governance, zero feature)
- Time MCP entrante (Felipe/Maiara/Eliana/Luiz) precisa de superfície menor pra onboardar

### Alt B — Deprecar imediato (drop em 1 PR)

**Rejeitada porque:**
- Viola Tier 0 multi-tenant ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)) — drop sem audit cross-tenant + sem snapshot mysqldump
- Risco bookmarks 82+12 URLs sem redirect 301
- Risco LGPD Art. 16 retention 5y (PII em `journal_entries.notes/reference` sem archive criptografado)
- Sem 60d/90d wait, sem janela pra detectar regressão silenciosa em cliente desconhecido

### Alt C — Refatorar Accounting em Inertia (MWART) em vez de deprecar

**Rejeitada porque:**
- Lições F3 Financeiro rejeitado 2026-05-09 ([`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)) — 6 meta-antipadrões + 15 técnicos mostram que MWART custa caro
- 91 Blade views × MWART 5 fases = ~50 ondas; ROI negativo (zero cliente pagante usa Accounting)
- Financeiro já cobre 60-70%; gap 30% é SPED (mitigado Portal Advisor)

## Consequências

### Positivas

- **1 módulo a menos pra manter** (Modules/Accounting deletado em E5)
- **Sidebar limpa** (entry Accounting desaparece em E3)
- **Fim do drift ARQ-0005** (BRIEFING vs código real)
- **Redução superfície Tier 0** (menos `business_id` global scope pra auditar)
- **Time MCP entrante** onboarda mais rápido (menos contexto pra absorver)
- **`module-grade-v3` bucket `functional_horizontal`** menos um item (ADR 0160)
- **94 routes a menos** no `route:list` (82 `/accounting/*` + 12 `/report/accounting/*`)
- **11 permissions Spatie órfãs limpas** (`accounting.chart_of_accounts.*`, `accounting.journal_entries.*`, `accounting.reports.*`)

### Negativas

- **Perda das 9 capacidades AUSENTES** no Financeiro (Trial Balance, Balance Sheet formal, LALUR, Fechamento, Multi-currency, Centro de Custo contábil, Rateio, Encerramento exercício, Reclassificação). **Aceito** porque:
  - Nenhum cliente prod usa (inspeção forense seção 5)
  - Portal Advisor + Simples Nacional Larissa cobrem necessidade real
  - Cliente como sinal ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)): sem cliente pagante reportando, sem feature

- **Perda do `Budget`** (capacidade 15) — sentimento de regressão. **Mitigado** archive snapshot `budgets` table + criar US-FIN-NNN Onda 35+ no Financeiro quando cliente pedir.

- **94 URLs antigas redirecionam** (não 404). Bookmark admins precisa atualizar — esforço Wagner manual.

- **30+90+60 = 180d corridos** dominados por waits — pra evitar regressão silenciosa.

### Reversibilidade

- **E3 (UI freeze)** reversível com 1 PR revert
- **E4 (archive)** reversível restore mysqldump (testado em staging primeiro)
- **E5 (drop código)** reversível `git revert HEAD` (mas tabelas DB ainda existem)
- **E6 (drop tabelas)** **IRREVERSÍVEL pós-merge** — por isso 90d wait + 2ª mysqldump pre-drop irreversível

## Compliance check

| Princípio | Atendido? | Como |
|---|---|---|
| **Append-only ADR** ([ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) p.7) | ✅ | Esta ADR NÃO edita ARQ-0005 nem ARQ-0001; supersedes via frontmatter |
| **Multi-tenant Tier 0** ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)) | ✅ | Todas migrations E4/E6 preservam `business_id`; cross-tenant Pest biz=1 vs biz=99 antes E depois |
| **LGPD Art. 7º+16** | ✅ | PII em `journal_entries.notes/reference` passa por `PiiRedactor` no mysqldump archive E4; AES-256 + chave Vaultwarden |
| **Publication-policy** | ✅ | Wagner aprova ADR proposta; tasks MCP só criadas pós-aprovação batch |
| **Cliente como sinal** ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) | ✅ | Larissa Simples Nacional confirmou; SPED outsource via Portal Advisor já pago/entregue |
| **Loop fechado por métrica** | ✅ | Seção 12 DEPRECATION-PLAN.md lista 9 métricas mensuráveis |
| **Confiabilidade com fallback** | ✅ | View bridge E4 (60d) + 90d wait E6 = duas janelas pra detectar regressão |
| **Estimate fator 10x IA-pair** ([ADR 0106](../0106-recalibracao-velocidade-fator-10x-ia-pair.md)) | ✅ | ~18d úteis trabalho ativo distribuído; 26 semanas corridas dominado por waits (relógio real) |
| **Skill mwart-process** ([ADR 0104](../0104-processo-mwart-canonico-unico-caminho.md)) | N/A | Não é migração Blade→Inertia, é deprecação |
| **Lições F3 Financeiro** ([`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)) | ✅ | Citada na Alt C — não Inertia-rizar Accounting, deprecar |

## Próximas ADRs derivadas

- [`0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md`](./0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md) — errata drift `accounting_*` (proposed simultânea)
- **(Futuro Onda 35+)** US-FIN-NNN — Orçamento no Financeiro (substitui `Budget` archive)
- **(Futuro)** US-FIN-NNN — Export TXT SPED para Domínio/Sage no Portal Advisor (Fase 2)
- **(Futuro condicional)** ADR formal "Centro de Custo Contábil no Financeiro" se cliente pagante reportar necessidade ([ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md))

## Refs

- [memory/requisitos/Accounting/DEPRECATION-PLAN.md](../../requisitos/Accounting/DEPRECATION-PLAN.md) — plano operacional 7 ondas
- [memory/requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md](../../requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md) — inspeção 505 linhas
- [memory/decisions/proposals/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md](./0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md) — errata derivada
- [memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md](../../requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) — superseded
- [memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md](../../requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md) — superseded
- [ADR 0093 multi-tenant Tier 0](../0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição v2](../0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0104 MWART canônico](../0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0105 Cliente como sinal](../0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0106 Recalibração fator 10x IA-pair](../0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0160 governance-v4 buckets](../0160-governance-v4-scoped-scorecards-buckets.md)
- [ADR 0167 Append-only handoff](../0167-errata-0130-indice-handoff-historico-longo.md)
- [ADR 0170 PaymentGateway Cobranca](../0170-paymentgateway-extracao-camada-cobranca.md)
- [memory/reference/cliente-rotalivre.md](../../reference/cliente-rotalivre.md) — Larissa biz=4 Simples Nacional
- [prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)
