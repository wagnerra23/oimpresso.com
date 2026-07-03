---
date: '2026-07-03'
topic: "Capterra de capacidade do módulo Compras (Onda 2.1) — benchmark vs 13 concorrentes de compras/procurement, nota 30/100, leitura adversarial do que a nota 59 (module-grade) esconde"
authors: [C]
tipo: session-log
agente: capterra-senior
onda: 2.1
modulo: Compras
nota_capacidade: 30
module_grade_ref: 59
design_ficha_ref: 67
websearches: 6
concorrentes_cobertos: 13
artefato: memory/requisitos/Compras/CAPTERRA-FICHA.md
related_adrs:
  - 0089-capterra-driven-module-evolution
  - 0101-tests-business-id-1-nunca-cliente
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0093-multi-tenant-isolation-tier-0
prs: [3708]
---

# Session log — CAPTERRA capacidade Compras (Onda 2.1)

## TL;DR

Gerada a **ficha de capacidade** do módulo Compras (`CAPTERRA-FICHA.md`, nota **30/100**) vs 13 concorrentes de compras/procurement 2026 — complementa a `CAPTERRA-DESIGN-FICHA.md` (UX, 67) e o module-grade 59. Achado central (pedido da onda "o que a nota esconde"): o module-grade 59 mede **higiene/governança** (Tier 0, Pest, doc, sec), cega ao valor de compras; a capacidade real é 30 — gap **-42** vs topo BR (Omie/Hiper ~72). Os 3 P0 que *definem* compra BR — import XML DF-e (C01=1), matching XML→PO (C02=0), recebimento parcial (C03=1) — mais 3-way match (C05=0) estão ~vazios. **FSM é teatro** (`const STAGES` em `Drawer.tsx:12`, não persistida); **bridge XML DF-e não existe** (diferencial nº1 BR); **módulo não está em prod pra ninguém** (D5=0, feature theater); **hardening tests são source-grep tautológicos** (Tier 0 valor/estoque descoberto — entrada de compra mexe em estoque). Honestidade: a onda ESTABILIZAR landou de verdade (Tier 0 bem-feito, `MultiTenantTest` real). Read-only, docs-only (PR #3708). Próximo passo = `/comparativo Compras` (aguarda OK [W]).

## O que foi feito

Gerada a **CAPTERRA-FICHA.md** de **capacidade** do módulo Compras (o módulo de nota mais baixa do projeto, module-grade 59 pós-ESTABILIZAR, subiu de 38). Complementa a CAPTERRA-DESIGN-FICHA (nota 67 UX). Espelha o formato canônico validado na Onda 1.1 (Sells FICHA): 10 seções, cálculo ponderado explícito P0=4/P1=2/P2=1/P3=0.5, §8 adversarial forte com evidência arquivo/linha.

## Verificação do estado REAL (Passo 1)

Ler o audit de 25/mai NÃO bastava — o código evoluiu muito desde então. Confirmado no worktree fresco (`compras-capterra-onda21`, origin/main @854cd9da33):

- **ESTABILIZAR landou de verdade:** `ComprasController::index` agora usa `auth()->user()->business_id` (não session) + `abort_if($businessId<=0)` + cross-check drift session≠auth. `ComprasService` ganhou OTel spans (`OtelHelper::spanBiz`). `ListarComprasRequest` FormRequest existe. Throttle 60,1 no route group.
- **Pest multi-tenant é REAL:** `Modules/Compras/Tests/Feature/MultiTenantTest.php` cria Business+Transaction+bate `/compras` via Inertia partial-reload, assert JSON cross-tenant (4 cenários: list, show-404, KPIs scope, filtro ?q= JOIN contacts). `MultiTenantSqlGuardTest.php` testa `getListPurchases(1)->toSql()` (DB-agnostic). NÃO são source-grep. Hotfix R1 (scope `contacts.business_id`) confirmado.
- **MAS os hardening tests SÃO source-grep:** `GapsHardeningTest.php` + `GapsP1HardeningTest.php` são `file_get_contents`+`str_contains` no source — tautológicos (mesmo anti-padrão proibicoes §5 que mordeu o Sells).
- **FSM é UI-only:** `const STAGES` em `Drawer.tsx:12`, renderizada sobre `transactions.status` string legacy. Sem state machine, sem history, sem transição gateada. A tela pode dizer "Recebido" com banco em `pending`.
- **Bridge XML DF-e NÃO existe:** `grep ImportarDfeComoCompra|nfe_dfe_recebidos` em Services/ = zero (só comentário em ServiceProvider/InstallController). US-COM-003 nunca construída.
- **D5=0:** `config/governance/module_clients.yaml` sem entry `Compras`. Módulo não em prod/canary pra ninguém. US-COM-010 ainda `todo`.
- **PII raw:** `Drawer.tsx:266/275/281` renderiza `tax_number`(CNPJ/CPF)+`mobile`+`email` sem PiiRedactor.
- **GradeMatrixInput** vive em `Purchase/Create.tsx` (convergência C1), fora do `/compras`, aguarda smoke/canary.

## Pesquisa adversarial (Passo 2 — 6 WebSearches nesta sessão)

1. Bling — importar XML NF-e entrada + manifestação destinatário + vincular a PO + polling SEFAZ por CNPJ.
2. Omie — NF-e Agent (polling SEFAZ AN 24h) + recebimento + AP automático atrelado + matching.
3. Lightspeed/Shopify — partial receive (Received Total vs Ordered Total, delivery linkada expansível, autosave check-in; received-vs-not-received linha-a-linha).
4. 3-way match P2P — Coupa/Procurify/Precoro (auto-match PO↔receipt↔invoice, AI-OCR, e-invoicing; "essential 2026"; Precoro ~US$499/mês, Coupa/Ariba 6 dígitos).
5. Zoho/Cin7 — PO workflow (Zoho: Draft→Open→Partially Received→Received→Billed→Closed; Cin7: 6 stages drag Draft→Ordered→Receiving→Received→Costed→Invoiced).
6. Tiny/Conta Azul/Hiper — importar XML via campo `xPed` auto-vincula PO + matching por fornecedor/descrição→EAN.
7. Supplier scorecard KPIs — OTIF≥95%, PPM<500, lead-time, rolling 13-sem.
8. AI AP automation — US$2.36-3.00/invoice (vs US$12-30 manual), 70-90% touchless; OCR 95-99% accuracy.
9. Reforma Tributária/NFS-e 2026 BR — NFS-e nacional obrigatória jan/2026, IBS/CBS destaque em DF-e.

Total corpus: 6 desta sessão + 10 do AUDIT-SENIOR = 16 buscas, 13 concorrentes com fonte (6 ERP BR, 4 inventory/POS global, 3 P2P puro).

**Insight-chave:** o Brasil tem o DF-e SEFAZ pull NSU (`DistribuicaoDfeService` já pronto em NfeBrasil) como substituto NATIVO do AI-OCR que o mundo anglo paga US$3/invoice — dados estruturados, 100% accuracy. Vantagem ESTRUTURAL do oimpresso. MAS só vira diferencial quando a bridge G-01 existir; hoje é potencial não-realizado.

## Nota calculada + justificativa

**30/100.** Σ ponderado 124.5 / 420 × 100 = 29.6.

- P0 (56/240): C06 multi-tenant=9 é o único forte; C01 import XML=1, C02 matching=0, C03 partial=1, C04 cálculo-testado=3, C05 3-way=0.
- Referências: Omie/Hiper ~72, Bling/Tiny ~68, Cin7/Zoho ~66, Coupa/Ariba/Precoro ~85 (desqualificado over-engineering PME).
- Gap pro topo BR: -42 pts. Causa: os 3 P0 que DEFINEM compra BR estão ~0.

A nota é baixa e honesta (esperado 30-50, veio 30). Não inflei: o módulo ganha eixos de UI/higiene (isolamento, cockpit) e perde todos os eixos que SÃO compra.

## 5 achados adversariais mais fortes (§8)

1. module-grade 59 mede higiene (Tier 0/Pest/doc/sec), NÃO valor de compras — capacidade real (30) é ~metade.
2. FSM é teatro: const `STAGES` no Drawer.tsx:12, não persistida — tela pode mentir "Recebido" vs banco `pending`.
3. Diferencial nº1 BR (import XML DF-e) não existe — grep zero; todo concorrente BR tem.
4. Módulo não em prod/canary pra ninguém (D5=0, module_clients.yaml sem Compras) — toda nota é teórica (feature theater).
5. Sem teste de cálculo custo/total/estoque; hardening tests são source-grep tautológicos (proibicoes §5). Entrada de compra MEXE EM ESTOQUE = Tier 0 valor/estoque.
   (6º: GradeMatrixInput, razão de existir pra Larissa, vive em Purchase/Create.tsx, fora do /compras.)

## Top 3 gaps P0

1. G-01 — Bridge Importar XML DF-e → Transaction (US-COM-003, Wave 6). L.
2. G-03 — Teste E2E cálculo custo/total/estoque. M.
3. G-02 — Matching automático XML→produto (EAN+xProd), depende G-01. M.

## Ficha gerada

`memory/requisitos/Compras/CAPTERRA-FICHA.md` — nota 30/100, 19 capacidades P0-P3, 13 concorrentes, §8 adversarial com evidência arquivo/linha. Cabeçalho espelha o Sells FICHA (persona Larissa, alvo de código, ADRs 0089/0101/0105/0093, aviso complementar apontando design-ficha 67 + module-grade 59).

## Próximo passo (Onda 2.1 Passo 2)

`/comparativo Compras` (skill `comparativo`, ADR 0089) → gera CAPTERRA-INVENTARIO.md em 3 buckets (✅🟡❌) + batch de tasks priorizadas P0-P3 → **aguarda OK [W]** antes de `tasks-create` no MCP (publication-policy). Foco do batch: G-01/G-02/G-03 (import XML + matching + teste cálculo) como P0 executáveis.

Read-only research — nenhum código commitado, nenhuma task criada. 2 arquivos escritos (ficha + este log).
