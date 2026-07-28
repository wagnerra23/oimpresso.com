---
id: requisitos-recurring-billing-adr-arq-0007-conta-bancaria-vs-gateway-duas-estrategias
---

# ADR ARQ-0007 (RecurringBilling) · Conta bancária vs gateway — duas estratégias de cobrança

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

O sistema tem duas formas distintas de emitir boleto:

**Estratégia A — Banco direto (Inter, C6):**
- Tenant tem conta bancária real no banco
- Dados do banco já ficam em `fin_contas_bancarias` (agência, conta, carteira, beneficiário, endereço)
- O banco oferece API (Inter) ou CNAB local (C6) para emitir boleto
- Credenciais de API são específicas dessa conta

**Estratégia B — Gateway de pagamento (Asaas):**
- Tenant não tem conta no "banco Asaas" — é um intermediário
- Asaas recebe em nome do tenant e repassa (MoR parcial)
- Dados do beneficiário vêm do Asaas (cadastro no painel Asaas)
- Credencial é só `api_key` — não vincula a uma conta bancária do tenant

**Problema descoberto (2026-05-06):**
`rb_boleto_credentials` foi criado como tabela standalone. Para bancos reais (Inter, C6), isso duplica dados que já existem em `fin_contas_bancarias` (agência, conta, beneficiário).

**Princípio do Wagner:** "o cliente pode ter banco mas nem sempre vai ter cobrança vinculada ao banco" — uma conta pode existir para movimentação sem estar configurada para emitir boleto.

## Decisão

**Duas tabelas com papéis distintos:**

```
fin_contas_bancarias
  ├── Conta bancária real (Inter, C6, BB, Itaú, etc.)
  ├── Dados bancários: agência, conta, carteira, convenio
  ├── Beneficiário: CNPJ, razão social, endereço
  ├── ativo_para_boleto: BOOLEAN (banco tem ou não cobrança ativa)
  └── FK opcional → rb_boleto_credentials (nullable, só se tem API configurada)

rb_boleto_credentials (renomear semanticamente para "gateway_credentials")
  ├── Credenciais de API por estratégia
  ├── Para banco direto: client_id, client_secret, certificados
  ├── Para gateway puro: api_key, ambiente
  └── FK opcional → fin_conta_bancaria_id (nullable — Asaas não tem conta bancária)
```

**Relacionamento bidirecional opcional (1-1 nullable):**
```
fin_contas_bancarias.rb_gateway_credential_id → rb_boleto_credentials (nullable)
rb_boleto_credentials.conta_bancaria_id       → fin_contas_bancarias  (nullable)
```

**Regra:**
- Inter, C6 → `fin_contas_bancarias` com `ativo_para_boleto=true` + FK para `rb_boleto_credentials` (credenciais de API)
- Asaas → só `rb_boleto_credentials` (sem FK para conta bancária)
- Conta bancária sem cobrança → `fin_contas_bancarias` com `ativo_para_boleto=false`, sem FK

## Tela de configuração

**Fluxo UltimatePOS (estado da arte):**
```
1. Admin > Contas (UPos)          → cria/edita conta genérica (nome, número, tipo)
2. Financeiro > Contas Bancárias  → complementa dados bancários (agência, carteira, beneficiário)
3. [toggle] Ativar cobrança       → expande formulário de credenciais de API (Inter/C6/Asaas)
4. Salvar → rb_boleto_credentials criado e vinculado
```

Não criar tela separada de "credenciais de boleto" — estender a tela de Conta Bancária do Financeiro com seção colapsável "Configurar cobrança".

## Consequências

**Positivas:**
- Tenant com conta Inter configura tudo num lugar só (banco + cobrança)
- `fin_contas_bancarias` continua sendo a source-of-truth de dados bancários
- Asaas como gateway puro funciona sem conta bancária cadastrada
- UI simples: toggle "Ativar cobrança nesta conta"

**Negativas:**
- `rb_boleto_credentials` criada hoje precisa de migration para adicionar FK `conta_bancaria_id` nullable
- `fin_contas_bancarias` precisa de FK `rb_gateway_credential_id` nullable

**Alternativas rejeitadas:**
- Tudo em `fin_contas_bancarias.metadata` JSON — sem tipagem, sem FK, sem validação
- Tudo em `rb_boleto_credentials` standalone — duplica beneficiário/agência/conta do Financeiro
