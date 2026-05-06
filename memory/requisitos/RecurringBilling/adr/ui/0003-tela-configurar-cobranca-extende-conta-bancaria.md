# ADR UI-0003 (RecurringBilling) · Tela "Configurar cobrança" estende Conta Bancária do Financeiro

- **Status**: accepted
- **Data**: 2026-05-06
- **Decisores**: Wagner
- **Categoria**: ui

## Contexto

Precisamos de uma tela para o tenant configurar suas credenciais de boleto (Inter, C6, Asaas). Opções avaliadas:

1. Tela nova em `/recurring-billing/credentials` — totalmente separada
2. Sub-seção dentro da tela de Conta Bancária Financeiro — extensão in-place
3. Wizard multi-step dedicado

## Estado da arte (Asaas, Vindi, Omie)

| ERP | Abordagem |
|---|---|
| **Asaas** | Uma tela só: cadastra empresa → ativa cobrança na mesma tela |
| **Vindi** | Aba "Métodos de pagamento" dentro da tela de "Empresa" |
| **Omie** | "Configurações" > "Cobranças" — tela separada por banco |
| **Bling** | "Contas" > seleciona conta > aba "Boleto" — in-place |

**Padrão mais comum:** configuração de boleto fica **dentro** da tela da conta bancária. O usuário não pensa "preciso configurar credenciais de API" — ele pensa "quero ativar boleto nesta conta".

## Decisão

**Estender a tela de Conta Bancária do Financeiro** com uma seção colapsável no final:

```
Conta Bancária — Inter PJ
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Dados bancários (já existem)
  Banco: 077 - Banco Inter   Agência: 0001
  Conta: 123456789-0         Carteira: 112

Beneficiário (já existe)
  CNPJ: 00.000.000/0001-00
  Razão Social: OIMPRESSO SISTEMAS LTDA

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[ ✓ ] Usar esta conta para emissão de boleto
      ▼ (seção expande ao ativar toggle)

  Estratégia de emissão:
  ● API Banco Inter (boleto registrado, sem taxa PJ)
  ○ CNAB local (C6, bancos tradicionais)
  ○ Gateway Asaas (sandbox disponível para testes)

  — se Inter selecionado —
  Client ID:     [_________________________]
  Client Secret: [_________________________]
  Certificado .crt: [upload]  Certificado .key: [upload]
  Ambiente: ● Produção  ○ Sandbox

  — se Asaas selecionado —
  API Key:   [_________________________]
  Ambiente:  ● Produção  ○ Sandbox

  [Testar conexão]    [Salvar configuração]
```

**Tela de Conta Bancária nova (sem conta UPos existente):**
- Botão "Nova Conta Bancária" abre modal/page com 3 passos:
  1. Dados bancários (banco, agência, conta)
  2. Dados do beneficiário (CNPJ, endereço)
  3. [Opcional] Configurar cobrança (toggle + credenciais)
- Passo 3 é pulável — tenant pode cadastrar banco sem ativar cobrança

## Componentes React

```
Pages/Financeiro/ContaBancaria/
├── Show.tsx         — detalhe com seção "Cobrança" colapsável
├── Edit.tsx         — edição com toggle + formulário credenciais
└── components/
    ├── BoletoConfigSection.tsx  — seção completa (toggle + form por estratégia)
    ├── InterCredentialsForm.tsx — upload .crt/.key + client_id/secret
    ├── AsaasCredentialsForm.tsx — api_key + ambiente
    └── TestConnectionButton.tsx — chama endpoint de smoke test
```

## Consequências

- Uma URL só: `/financeiro/contas-bancarias/{id}/edit` já cobre tudo
- UX familiar: usuário navega onde já conhece (Financeiro > Contas)
- Sem tela separada de "credenciais de boleto" — menor carga cognitiva
- `Pages/RecurringBilling/` só precisa de telas de faturas/contratos — não de configuração de banco
