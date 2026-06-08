---
module: Financeiro
alias: financeiro
status: ativo
migration_target: react
migration_priority: alta
risk: medio
problem: "UltimatePOS tem `transaction_payments` por venda mas não tem Contas a Pagar/Receber, fluxo de caixa projetado, conciliação OFX/CNAB, DRE BR. Tenant replica tudo em planilha externa, perde dado, paga juros por esquecimento, contador cobra hora extra."
persona: "Larissa-financeiro (operadora única) + Contador terceiro + Gestor"
positioning: "O caixa do seu negócio em ordem em 5 minutos por dia. De planilha-que-ninguém-entende a fluxo de caixa projetado, baixa automática e DRE pronto pro contador — direto do ERP que já roda o caixa."
estimated_effort: "5-6 semanas dev sênior (4 ondas)"
revenue_tier: 1A
revenue_pricing:
  free: "Contas a Pagar manual + Contas a Receber automático das vendas (limite 50 títulos/mês)"
  pro: "R$ 199/mês — boleto + PIX + OFX + DRE + sem limite + multi-conta bancária"
  enterprise: "R$ 599/mês — CNAB direto + multi-empresa consolidado + API + SLA"
revenue_take_rate: "0,5% sobre boleto/PIX emitido (capped R$ 9,90 por título) quando usa nosso adapter"
references:
  - https://claude.ai/chat/9e05ee7a-315a-4162-b5e4-adb7f0ae55c6
  - eduardokum/laravel-boleto
  - reference_ultimatepos_integracao.md
related_modules:
  - NfeBrasil
  - RecurringBilling
  - PontoWr2
last_generated: 2026-04-24
last_updated: 2026-04-24
---

# Financeiro

> **Pitch para o tenant:** _O caixa do seu negócio em ordem em 5 minutos por dia._ De planilha-que-ninguém-entende a fluxo de caixa projetado, baixa automática e DRE pronto pro contador — direto do ERP que já roda o caixa.

## Propósito

Transformar UltimatePOS em ERP financeiro completo brasileiro:

- **Contas a Pagar / a Receber** com origem rastreável (venda, compra, despesa, contrato, manual)
- **Fluxo de caixa** realizado e projetado (próximos 30/60/90 dias)
- **Conciliação bancária** via OFX (lê extrato) ou CNAB 240/400 (banco-direto)
- **Boleto + PIX + Cartão** como meios de cobrança nativos (não só registro)
- **DRE / Razão / Aging** prontos para o contador (ou exportáveis pro SPED)
- **Plano de contas BR** padrão Receita Federal

Tudo respeitando padrão UltimatePOS: módulo `nwidart`, `business_id` em toda query, hooks `DataController` (menu, permissões, license), eventos para integrações cross-módulo.

## Posicionamento de mercado (revenue thesis)

Este é o módulo que **transforma o oimpresso de "POS" em "ERP"** aos olhos do cliente. Tenant que paga R$ 49/mês pelo POS aceita pagar R$ 199-599/mês quando o financeiro economiza 1 hora/dia da Larissa-financeiro e elimina a planilha externa.

| Plano | Preço/mês | Quem assina | Margem (estimada) |
|---|---|---|---|
| **Free** (limite 50 títulos/mês) | R$ 0 | Microempreendedor / teste | Negativa por usuário, positiva agregado por upgrade |
| **Pro** | R$ 199 | PME 1-10 funcionários | 70% (custo: gateway + storage) |
| **Enterprise** | R$ 599 | PME 10-50 funcionários | 80% |
| **Take rate boleto/PIX** | 0,5% (capped R$ 9,90) | Plano Pro+Ent que usa nosso adapter | 90% (sem custo variável significativo) |

**Modelo de revenue híbrido** (subscription + take rate) é o que paga a manutenção dos adapters bancários (que mudam toda semana) sem precisar segurar lock-in puro de SaaS.

## Índice

- **[SPEC.md](SPEC.md)** — user stories US-FIN-NNN + regras Gherkin R-FIN-NNN
- **[ARCHITECTURE.md](ARCHITECTURE.md)** — camadas, modelos, services, integração com core UltimatePOS
- **[GLOSSARY.md](GLOSSARY.md)** — vocabulário (título, baixa, aging, regime caixa/competência, CNAB)
- **[CHANGELOG.md](CHANGELOG.md)** — versão a versão
- **[adr/](adr/)** — decisões numeradas (`arq/`, `tech/`, `ui/`)
- **[RUNBOOK.md](RUNBOOK.md)** — operações (rotacionar cert OFX, regenerar CNAB, etc.) — _stub_

## Áreas funcionais

| Área | Controller(s) principais | Por que existe |
|---|---|---|
| **ContasReceber** | `ContaReceberController` | Larissa lança/baixa título; vendas viram títulos automaticamente |
| **ContasPagar** | `ContaPagarController` | Larissa cadastra fornecedor + upload boleto OCR + agenda pagamento |
| **Caixa** | `CaixaController`, `CaixaMovimentoController` | Fluxo realizado (banco) + projetado (vencimentos) |
| **ContaBancaria** | `ContaBancariaController` | Cadastro de conta + saldo + integração OFX |
| **Boleto** | `BoletoController` | Geração + remessa CNAB + retorno automático |
| **Pix** | `PixController` | QR estático/dinâmico + webhook recebimento |
| **PlanoContas** | `PlanoContaController` | Estrutura DRE; preset Receita Federal |
| **Categoria** | `CategoriaController` | Etiquetas livres complementares (ex: "Aluguel Loja A") |
| **Conciliacao** | `ConciliacaoController` | Importa OFX, faz match, sugere lançamentos |
| **Relatorio** | `RelatorioController` | Fluxo, Aging, DRE, Razão |

## Quem ganha o que

| Persona | Job (concretos) | Tela atende |
|---|---|---|
| **Larissa-financeiro** | "Lançar conta de luz que chegou agora" | `/financeiro/contas-pagar/create` |
| | "Receber o que vence hoje sem clicar venda por venda" | `/financeiro/contas-receber?vence=hoje` (lote-baixa) |
| | "Reconciliar OFX do banco do mês" | `/financeiro/conciliacao` |
| **Contador (terceiro)** | "Exportar DRE Q1 sem ligar pra Larissa" | `/financeiro/relatorio/dre?periodo=...` (link compartilhável read-only) |
| **Gestor** | "Quanto vou ter em caixa em 30 dias?" | `/financeiro/caixa/projetado` |
| **Auditor SEFAZ** (eventual) | "Razão da conta corrente Banco X 2026" | `/financeiro/relatorio/razao?conta=...` |

## Status atual (2026-04-24)

- ✅ **Spec promovida** de `_Ideias/Financeiro/` (era `researching`) para `requisitos/Financeiro/` (`spec-ready`)
- ⏳ **Onda 1 (MVP):** schema + Contas a Receber automático (vendas com `payment_status=due`)
- ⏳ **Onda 2:** Contas a Pagar + Caixa projetado
- ⏳ **Onda 3:** Boleto + PIX (depende de homologação banco)
- ⏳ **Onda 4:** Conciliação OFX + DRE

## Onde se conecta

- **Core UltimatePOS** — observers em `Transaction` (sell/purchase/expense) criam títulos automaticamente. Pagamento de título cria `transaction_payment` retro-vinculado. ([ARCHITECTURE.md §5](ARCHITECTURE.md#5-integrações))
- **NfeBrasil** — DRE consome NF-e emitida (receita bruta + dedução de impostos). Sem NfeBrasil, DRE é "best-effort" sobre `transactions.final_total`.
- **RecurringBilling** — fatura recorrente paga vira `transactions` core + título `Financeiro` baixado. Evita 2 fontes de verdade.
- **PontoWr2** — folha de pagamento futura é título `tipo=pagar` por colaborador (origem `pontowr2:folha`).
- **Officeimpresso** — possível receber comissão sobre take rate (split com tenant). Decisão pendente.
- **MemCofre** — telas finais terão `// @memcofre US-FIN-NNN` declarando stories cobertas.

## Próximos passos imediatos

1. **Validar com ROTA LIVRE** (biz=4): Larissa hoje usa qual planilha? Quais colunas? — substitui pra MVP
2. **Decidir gateway boleto/PIX MVP**: Sicoob (banco da ROTA LIVRE) ou Asaas (multi-banco gateway)?
3. **Scaffold módulo** (`php artisan module:make Financeiro`) com hooks `DataController` plugados
4. **Migrations + seeders** (plano de contas BR padrão pré-seeded)
5. **Onda 1**: ContaReceber observer (auto-cria título de venda `due`) + tela `/financeiro/contas-receber` + 1 teste Pest cobrindo isolamento multi-tenant
