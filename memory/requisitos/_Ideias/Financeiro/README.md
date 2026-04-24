---
status: researching
priority: alta
problem: "oimpresso não tem módulo financeiro completo (Contas a Pagar, Contas a Receber, fluxo de caixa, conciliação bancária). Hoje só tem 'pagamentos de transações' direto na venda. Falta gestão financeira separada do POS."
persona: "Larissa-financeiro (RH/contas) controla títulos a pagar/receber + Contador (terceiro) reconcilia bancário"
estimated_effort: "14 etapas (estimativa não detalhada — provavelmente 4-6 semanas)"
references:
  - https://claude.ai/chat/9e05ee7a-315a-4162-b5e4-adb7f0ae55c6
  - eduardokum/laravel-boleto (lib boleto BR)
  - reference_ultimatepos_integracao.md
related_modules:
  - NfeBrasil (DRE precisa de NFe emitidas)
---

# Ideia: Financeiro — contas a pagar/receber + DRE BR

## Problema

UltimatePOS tem `transaction_payments` (pagamento atrelado à venda) mas **não tem módulo financeiro real**: Contas a Pagar / Receber, fluxo de caixa projetado, DRE, conciliação bancária via OFX/CNAB, juros/multa por atraso, plano de contas brasileiro. Empresa que usa o oimpresso como ERP precisa replicar tudo em planilha externa.

## Persona

| Persona | Job |
|---|---|
| **Larissa-financeiro** (cargo único na ROTA LIVRE) | Lança contas a pagar (fornecedor manda boleto) + recebíveis (vendas a prazo) + dá baixa quando paga/recebe |
| **Contador (terceiro)** | Concilia bancário (OFX), exporta DRE/Razão pra ECF, calcula impostos sobre faturamento |
| **Gestor** | Vê fluxo de caixa projetado (entradas+saídas próximos 30 dias), inadimplência por aging |

## Status

`researching` — Claude (mobile) gerou artifact completo "Módulo Financeiro Brasileiro para UltimatePOS - Guia Completo" via 3 pesquisas web. Não foi promovido a spec ainda.

## Arquitetura proposta na conversa

**Módulo nwidart** (`Modules/Financeiro/`) seguindo padrão UltimatePOS já existente.

### Modelo de dados (núcleo)

Tabela `titulos` com:
- `tipo`: `pagar` | `receber`
- `status`: `aberto` | `parcial` | `quitado`

Combinações que viram telas/filtros:

| `tipo` | `status` | Tela visível |
|---|---|---|
| pagar | aberto/parcial | **A Pagar** |
| pagar | quitado | **Pagas** |
| receber | aberto/parcial | **A Receber** |
| receber | quitado | **Recebidas** |

### Services principais (lógica de negócio)

- **`TituloService`** — CRUD de título, recálculo de juros/multa por atraso, geração de parcelas, vinculação com transação UltimatePOS
- **`BaixaService`** — registra pagamento parcial/total, gera movimentação no caixa/conta bancária, recalcula saldo do título pai, dispara observers

### Integração nativa com core UltimatePOS

- **Observer no model `Transaction`** — venda finalizada com `payment_status=due` → cria automaticamente título `tipo=receber` em aberto
- **Compras** — purchase com `payment_status=due` → cria título `tipo=pagar`
- **Menu** plugado via `DataController::modifyAdminMenu()` (padrão UltimatePOS — ver `reference_ultimatepos_integracao.md`)

### Recursos brasileiros específicos

- **Plano de contas padrão BR** (modelo Receita Federal pra DRE)
- **CNAB 240/400** (remessa/retorno bancário)
- **Boleto bancário** via `eduardokum/laravel-boleto` (composer)
- **PIX** (cobrança imediata + cobrança com vencimento)
- **Regime competência vs caixa** (mesmo fato gera lançamentos diferentes)
- **Multi-empresa** via `business_id` (segue padrão tenant do UltimatePOS)
- **Juros de mora padrão BR**: 0,33% ao dia (a.d.) + multa 2%

### Relatórios

- **Fluxo de caixa realizado** (já entrou/saiu)
- **Fluxo de caixa projetado** (próximos 30/60/90 dias com base nos vencimentos)
- **DRE** (Demonstração de Resultado do Exercício) — periódica
- **Inadimplência por aging** (vencido há <30/30-60/60-90/>90 dias)
- **Razão bancário** (movimentação por conta)

## Decisões iniciais (pré-spec)

- **Lib boleto:** `eduardokum/laravel-boleto`
- **PIX:** API banco direta (não decidido qual — provavelmente Banco do Brasil ou Sicoob, depende do banco do cliente)
- **OFX:** parser próprio ou lib externa (não decidido)
- **Multi-empresa:** sempre via `business_id` (escopo padrão UltimatePOS)

## Estimativa

Conversa menciona **"passo-a-passo de 14 etapas para implementar do zero"**. Sem detalhamento das etapas extraído da conversa visível — o conteúdo fica no artifact da conversa que precisaria ser aberto explicitamente.

Estimativa rough: 4-6 semanas dedicadas, mas depende do escopo (boleto + PIX + CNAB + DRE = pesado).

## Próximos passos

1. **Abrir o artifact da conversa Claude** pra capturar as 14 etapas detalhadas
2. **Decidir escopo MVP:** começar só com Contas a Receber a partir de vendas existentes? Ou já contas a pagar manuais também?
3. **Validar com ROTA LIVRE:** ela emite boleto hoje? Recebe via PIX direto? Concilia banco?
4. **Promover** quando spec leve estiver completa: `_Ideias/Financeiro/` → `requisitos/Financeiro/`
5. **Scaffold:** `memcofre:new-module Financeiro`

## Riscos

- **Compliance fiscal:** DRE precisa bater com SPED Contribuições. Integração com `NfeBrasil` é obrigatória.
- **Bancos brasileiros são chatos:** cada banco tem padrão CNAB ligeiramente diferente. Lib `eduardokum/laravel-boleto` cobre maioria mas pode ter casos.
- **PIX:** APIs ainda em maturação (2026). Banco escolhido limita features.
- **Fluxo de caixa projetado** depende de qualidade dos lançamentos — lixo entra, lixo sai.

## Conexões

- **NfeBrasil** — DRE consome dados de NFes emitidas. Sincronia via observer.
- **Officeimpresso** — possível receber comissão/licença (se o módulo gerar revenue futuro).
- **Design System** — telas A Pagar / A Receber seguem ADR UI-0006 (PageHeader + KpiGrid + DataTable + EmptyState).
- **PontoWr2** — folha de pagamento futura conectaria aqui (passa a ser título `tipo=pagar` por colaborador).
