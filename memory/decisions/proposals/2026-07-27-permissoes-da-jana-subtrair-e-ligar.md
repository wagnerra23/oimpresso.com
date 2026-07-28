---
title: Permissões da Jana — subtrair e ligar, não somar
status: proposto
date: '2026-07-27'
owners:
  - '[W]'
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0216-governance-drift-checkers-alertas
---

# Permissões da Jana — subtrair e ligar, não somar

> **Estado:** proposta. A decisão de produto (quais níveis existem) é [W].
> Esta proposta registra **o que foi medido** e **o desenho que a medição sustenta**.

## O pedido

[W] 2026-07-27: *"isso deve ser feito por permissões de acesso do usuário, controlado
pela spatie de permissões do nivel que o usuário tem"* — dito logo depois de decidir
que **num ERP não se apaga PII** (ver §5 de `proibicoes.md`, mesma data). Ou seja: o
controle do dado sensível migra de *retenção* para *acesso*.

## O que foi medido (não lido)

| Fato | Evidência |
|---|---|
| As 5 permissões da Jana estavam **declaradas e não aplicadas** | `Modules/Jana/Http/routes.php` tinha **1** `can:` em todo o arquivo (linha 167), e o grupo `/ia` **nenhum**. `'can' => 'jana.chat'` no `topnav.php` só esconde item de menu |
| **Menu escondido ≠ rota protegida** | quem soubesse a URL `/ia` entrava sem a permissão |
| O ERP **não tem hierarquia de usuário** | zero `supervisor_id`/`manager_id`/`reports_to`. `department_id` existe só em `Modules/Essentials` (folha), preso a `employees` — não é escopo de permissão |
| **Fadiga de permissão é real** | 188 permissões só nos `DataController` de módulo (Essentials 24 · Financeiro 16 · Crm 13 · Repair 12 …), mais as do núcleo UltimatePOS |
| O padrão `self/team/all` **já existe neste repo** | `jana.cc.read.self` · `.team` · `.all`, com `risk` (low/medium/high) e grafo `requires` no `Resources/permissions.php` |
| **`Gate::before` anula tudo para o dono** | `app/Providers/AuthServiceProvider.php:34-47` devolve `true` em **qualquer** ability pra quem tem `Admin#{business_id}` (exceto `backup`/`superadmin`/`manage_modules`, que vão por lista de username) |

## As duas consequências que mudam o desenho

**1. Permissão de IA só morde funcionário.** Larissa é admin da biz=4 → passa em
qualquer permissão, hoje e no futuro. Então o que se está desenhando não é *"o que a
Larissa vê"*, é *"o que a funcionária dela vê"*. Qualquer proposta que prometa
restringir o dono é falsa por construção.

**2. Não existe nível "time".** Sem hierarquia no modelo de dados, um `.team` não
teria o que significar num negócio-cliente. O desenho colapsa em **próprio × todos do
negócio** — e a tripla `self/team/all` do `cc.read.*` funciona lá porque "time" é o
conjunto plano de usuários internos do MCP, não uma estrutura de subordinação.

## O desenho: 3 permissões, todas já existentes, zero novas

| Permissão | Libera | Risco |
|---|---|---|
| `jana.access` | usar a Jana (chat, brief, painel) | low |
| `jana.metas.manage` | definir metas e alertas | medium |
| `jana.admin.custos.view` | ver custo de IA | high |

**Sai `jana.chat`** — não há uso legítimo de *"entra no módulo mas não conversa"*;
funde em `jana.access`. **`jana.superadmin` e as 22 chaves `mcp.*`/`cc.*` saem do
grupo visual do cliente** — são superfície interna do time, não do negócio.

O trabalho é **subtrair e ligar**, não somar. Num universo de 188+ permissões, propor
12-15 chaves novas de IA não é completude: é garantir que ninguém configure e todo
mundo vire admin.

### Por que não "a IA herda as permissões do dado"

O padrão de mercado (Copilot, Slack AI, Agentforce) separa **permissão de ferramenta**
(grossa, 1-3 chaves) de **alcance do dado** (herdado, nunca declarado) — e nenhum líder
cria permissão de IA por tipo de dado. O **princípio** transplanta; o **mecanismo** não:
o Copilot herda de graça porque o M365 tem ACL **por objeto** num gateway único (Graph).
Aqui o ACL é **por feature** (Spatie) + filtro `created_by` escrito à mão dentro de cada
Controller. Herdar exigiria trabalho manual em cada porta de dado.

*(Tradução de premissa obrigatória — §5 2026-07-16: importar solução sem checar se o
problema existe no nosso.)*

## O gap que sobra, e que este PR NÃO fecha

`Modules/Jana/Services/ContextSnapshotService.php` scopa **só por `business_id`** —
6 pontos de query, zero `created_by`/`view_own_*`. Uma vendedora com `view_own_sell_only`
na tela de Vendas recebe, pela Jana, o faturamento da loja inteira. **Não é vazamento
entre empresas** (o Tier 0 segura) — é entre usuários da mesma empresa. Fechar isso é
trabalho separado e maior: exige decidir, por porta de dado, o que "alcance do usuário"
significa.

## Rollout

`jana.access` nasce `default => false`. A ordem segura é:

1. **[W] marca a permissão nos papéis** (UI `/roles/{id}/edit`) — antes do merge;
2. **merge do PR** liga o gate;
3. smoke com usuária não-admin.

Invertido, há janela em que funcionária perde a Jana. Como merge é ato de [W], o
passo 2 já é naturalmente o dele.

## O que esta proposta NÃO autoriza

- Criar permissão nova de IA por tipo de dado (relatório, faturamento, cliente…) — é o
  anti-padrão que a pesquisa isolou e que a fadiga de 188 permissões torna concreto.
- Prometer que permissão restringe o **dono** — `Gate::before` impede, e dizer o
  contrário é alegação falsa sobre enforcement (classe LC-10).
- Introduzir nível `team` sem antes existir hierarquia no modelo de dados.
