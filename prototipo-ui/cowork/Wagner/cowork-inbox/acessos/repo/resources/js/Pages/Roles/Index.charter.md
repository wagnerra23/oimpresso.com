---
id: resources-js-pages-roles-index-charter
page-id: Roles/Index
status: proposta
tier: A
component: resources/js/Pages/Roles/Index.tsx
autor: CC
last_validated: 2026-08-19
---

# Charter — Funções e permissões (`/roles`)

## Missão

Deixar o dono do negócio dizer o que cada função pode fazer **sem ler 400 checkbox** — e sem conseguir
dar poder demais por acidente.

## Norte

O legado mostra 53 grupos de checkbox achatados, com pares "ver todos / ver próprio", nove permissões
escritas ao contrário ("Desativar desconto") e 27 chaves sem tradução. A tela nova não reorganiza: ela
**normaliza** em 5 formas de controle e mostra a consequência antes de salvar.

## Regras de domínio (lidas de `RoleController.php`)

| # | Regra |
|---|---|
| R1 | Papel é por negócio; o nome grava `Nome#<business_id>` |
| R2 | A UI nunca mostra o sufixo; `Admin`/`Cashier` vêm de `lang_v1` |
| R3 | `is_default` não é editável nem excluível — **exceto** `Cashier#<biz>` |
| R4 | Editar o `Cashier` **zera** `is_default` para sempre |
| R5 | `is_service_staff` é coluna do papel, não permissão |
| R6 | Grupo de preço de venda é **radio** (`radio_option`), um por papel |
| R7 | `Permission` é global (sem `business_id`); só o `Role` é por negócio |
| R8 | Salvar usa `syncPermissions()` — o que não veio no POST é revogado |
| R9 | Nome duplicado no negócio ⇒ erro, sem gravar |
| R10 | Exclusão não checa uso hoje (alvo da PR-5) |

## Regras de apresentação

| # | Regra |
|---|---|
| A1 | Par "ver todos / ver próprio" ⇒ **um** escopo (Todos · Só os próprios · Sem acesso) |
| A2 | Os nove `Desativar …` do PDV aparecem como **liberação**, com a nota "grava invertido" |
| A3 | Permissão destrutiva/financeira é marcada `risco` e contada no rodapé |
| A4 | O rodapé mostra `+N −M` desde o padrão e quantos usuários são afetados |
| A5 | Chave sem lang string aparece **crua**, com aviso — nunca rótulo inventado |
| A6 | Cabe em 1280px com a sidebar de 256px aberta, sem scroll horizontal |
| A7 | Densidade de ERP: linha ~58px, igual às telas irmãs do módulo |

## Non-goals

Não cria permissão nova (só marca o que está no catálogo) · não define pacote/plano do superadmin ·
não limita por local (isso é escopo de dados, campo separado) · não gerencia usuário (é a tela irmã).

## Anti-hooks

Não grava em GET · não aceita permissão fora do catálogo · não mostra enum cru · não usa modal
full-screen para detalhe (drawer PT-02) · não usa inglês na UI · sem emoji.

## Pendências para [W]

1. Permissão própria de comissionado (`commission_agent.*`) ou segue em `user.*`?
2. Editar `Cashier`: avisar na tela (recomendado) ou preservar `is_default` no backend?
3. As 27 chaves sem tradução: quem escreve as lang strings dos módulos `Fiscal`, `PaymentGateway` e `RecurringBilling`?
