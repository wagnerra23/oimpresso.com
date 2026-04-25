---
name: Cliente ROTA LIVRE — perfil, histórico e sensibilidades
description: Único cliente ativo com volume real (17k+ vendas, 99% do sistema). Dono operacional Larissa. Endereço Gravatal/SC, mas timezone SP. Histórico de incidentes, comportamento operacional e o que NÃO mexer. Consultar sempre que aparecer pedido do ROTA LIVRE.
type: cliente
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
# ROTA LIVRE (business_id = 4)

**Razão social:** LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME
**CNPJ:** 73.306.573/0001-11
**Localização única:** BL0001 "ROTA LIVRE" — Termas do Gravatal, Gravatal/SC, CEP 88735-0
**Telefone:** (48) 3626-4806
**Timezone cadastrado:** `America/Sao_Paulo` (confirmado operacionalmente em 2026-04-24)
**Cadastro original:** 2021-02-01
**Volume:** 17.251+ vendas (~99% do total do sistema)
**Primeira venda:** 2021-05-13 · **Venda ativa diariamente** (operação real, não demo)

## Users ativos

| id | username | nome | role | obs |
|---|---|---|---|---|
| 9 | `wr2.rotalivre` | WR2 Sistemas (admin externo) | `Admin#4` | conta do Wagner pra suporte |
| 10 | `larissa-04` | **Larissa Fernandes** | `Admin#4` | **dona/operadora principal** |
| 11 | `rota.vendas-04` | Vendas | `Vendas#4` | operador de balcão (caixa) |
| 72 | `caixa-04` | Caixa | `Caixa#4` | operador de caixa secundário |

**Todos ativos, todos `permitted_locations='all'` ou location.4 explícita** (confirmado 2026-04-24).

## Sensibilidades operacionais — NÃO MEXER SEM AVISAR

1. **Horários das vendas:** Larissa **decorou** horários com o shift +3h do bug histórico de `format_date`. Qualquer correção visual de datetime = regressão percebida. Ver `feedback_carbon_timezone_bug.md`. Não reaplicar `Carbon::parse` no `format_date` sem comunicar antes.

2. **Transaction_date retroativo é normal:** Larissa frequentemente digita `transaction_date` com hora passada (até 17h de diferença do `created_at`), especialmente pra vendas de balcão registradas em lote no final do dia. Isso NÃO é bug — é fluxo dela. Diff `transaction_date` < `created_at` esperado. Não tentar "corrigir" por algoritmo.

3. **Monitor pequeno:** opera em monitor ~1280px. Tela `/sells` com 21 colunas era inutilizável. Solução 2026-04-24: `columnDefs: { targets: [11,12,21,22,23], visible: false }` + locale pt-BR DataTables. Não adicionar colunas default sem pensar no espaço.

4. **Fluxo depende de location selecionada:** `/sells/create` só libera busca de produto depois de `default_location` preenchido. Role `Vendas#4` já tem `location.4` (corrigido em 2026-04-24); se alguém criar role custom sem isso, trava novamente.

## Histórico de incidentes

### 2026-04-24 — Bateria de bugs em `/sells`
Wagner reportou queixas acumuladas da Larissa. Origem múltipla:

1. **Labels em inglês/espanhol/português com typos** → fix lang + DataTables locale pt-BR (commit `dcefd087`)
2. **21 colunas estourando tela 1280px** → columnDefs escondendo 5 colunas (mesmo commit)
3. **Timezone frontend não aplicando (`moment.tz.setDefault('')`)** → session('business_timezone') chave dedicada + middleware (commit `47c9e594`)
4. **`format_date` empurrando +3h** → corrigido (commit `10634ad2`), **revertido mesmo dia** (commit `e5c8c90d`) porque Larissa reportou "vendas antigas mudaram 3h". Ela decorou os horários errados.
5. **Campo de busca de produto disabled em `/sells/create`** → dois bugs independentes:
   - role `Vendas#4` sem `location.4` (fix de dados, SSH direto)
   - shim `Form::` rendering `disabled=false` como ativo (commit `7fbfbdc7`)

Todas as session notes em `D:/oimpresso.com/memory/sessions/2026-04-24-*.md`.

## Padrões de uso (análise dos dados)

- **Horário de pico:** 14h-17h SP (base nos `created_at` recentes)
- **Ticket médio observado:** R$ 100-500 (balcão de moda)
- **Cliente mais frequente:** "Cliente Balcão" (genérico) + nomes PF (Jociane, Edna, Guilherme, Eli, Lucio, etc.)
- **Esquema de fatura:** NFs com `2026/NNNN` e/ou `17NNN` convivem — dois schemes ativos

## Como retomar contexto rapidamente

Ao receber qualquer solicitação mencionando ROTA LIVRE / Larissa / Gravatal / biz=4:

1. Ler esta memória primeiro
2. Checar session notes recentes em `memory/sessions/` com grep `rotalivre`
3. Se for bug de produção, **antes de qualquer fix sistêmico** considerar impacto visual (a operadora decorou estado anterior)
4. Se for análise de dados, usar receita em `reference_hostinger_analise.md` (SSH + mysql CLI)
5. Comunicar o Wagner antes de mudanças — ele é o canal com o cliente

## Dados úteis pra query

```sql
-- Vendas do ROTA LIVRE num período
SELECT id, invoice_no, transaction_date, created_at, final_total
FROM transactions WHERE business_id=4 AND type='sell'
  AND DATE(created_at) BETWEEN :start AND :end;

-- Users + roles
SELECT u.id, u.username, u.first_name, r.name as role
FROM users u
JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type='App\\User'
JOIN roles r ON r.id = mhr.role_id
WHERE u.business_id=4;

-- Permissões do role Vendas#4 (pra verificar se location ainda existe)
SELECT p.name FROM permissions p
JOIN model_has_permissions mhp ON mhp.permission_id=p.id
JOIN roles r ON r.id=mhp.model_id AND mhp.model_type='Spatie\\Permission\\Models\\Role'
WHERE r.name='Vendas#4';
```
