---
id: sessions-2026-11-14-2239-data-readiness-uniafibro
date: "2026-11-14"
hour: "22:39"
topic: "Data readiness da Uniafibro: mapa das migrations das 6 ondas Associacoes, seed versionado das associações-membro, auditoria Tier 0 e perguntas para [W]"
authors: ["C"]
outcomes:
  - "RUNBOOK-data-readiness-uniafibro.md em rascunho: dados a fornecer, ordem das migrations, backup, sequência de produção, auditoria Tier 0, rollback"
  - "Só 2 migrations nas 6 ondas, ambas CREATE de tabelas novas assoc_* (onda 2 e onda 6)"
  - "Comando associacoes:semear-rede (simulação por padrão, --business obrigatório, idempotente) + 5 testes Pest, empilhado na onda 2"
  - "Achado: deploy aplica migration sozinho no merge e não faz dump do banco — backup manual antes do merge da onda 2"
  - "Nada executado em ambiente algum: aguardando respostas e aprovação de [W]"
related_adrs:
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - 0093-multi-tenant-isolation-tier-0
---

# Data readiness da Uniafibro

## TL;DR

RUNBOOK de entrada da Uniafibro em produção em rascunho, seed versionado das associações-membro
(`associacoes:semear-rede`, simulação por padrão) e auditoria Tier 0 em SQL. Só 2 migrations nas 6
ondas, ambas criando tabelas novas. Risco: o deploy migra sozinho no merge e não faz dump do banco,
então o backup manual vem antes. Nada executado em ambiente algum.

## Pedido

Preparar a entrada da Uniafibro em produção: dados da empresa, migrations das ondas Associacoes,
dados iniciais, consistência Tier 0 e backup. Regra do pedido: o agente não cria empresa em
produção nem liga módulo; documenta cada comando que [W] executa.

## O que medi (só leitura)

- `brief-fetch` ok. `decisions-fetch 0451` não acha: a ADR está só na branch
  `claude/associacoes-onda1-adr` (status `proposto`), lida por `git show`.
- 6 branches, 5 PRs abertos (#10878 onda 2, #10883 onda 3, #10882 onda 4, #10881 onda 5,
  #10880 onda 6). Migrations: `2026_11_13_000001_create_assoc_tables` (onda 2) e
  `2026_11_13_000060_create_assoc_portal_tokens_table` (onda 6). Ondas 3–5 sem migration.
  Ondas 3–6 carregam os commits da onda 2.
- Os 6 models usam `HasBusinessScope`. `ScopeByBusiness` não filtra sem usuário logado → em CLI a
  empresa tem que ser explícita (vale para o seed e para a auditoria).
- `deploy.yml`: `migrate --force` em todo push em main; o snapshot pré-deploy é só de arquivos;
  o artisan extra roda **depois** do migrate. Backup de banco = spatie noturno, exit 1 falso por SMTP.
- `StoreBusinessRequest`: `tax_number_1` opcional → empresa sem CNPJ é possível.
- Trigger MySQL já existe em produção em outros módulos (Fisio, Forja, Marketplaces).
- A FK não impede filho de uma empresa apontar para pai de outra → a auditoria do RUNBOOK §7b
  compara `business_id` entre tabelas (associação, contato, usuário, associada, consentimento, token).

## O que produzi

- `memory/requisitos/Infra/RUNBOOK-data-readiness-uniafibro.md` (este PR).
- Branch `claude/associacoes-seed-rede-inicial` sobre a onda 2: `Modules/Associacoes/Console/SemearRedeCommand.php`,
  registro no ServiceProvider, `SemearRedeCommandTest.php` (5 casos, lane `verticais-pest`).
  `php` não existe nesta máquina: sintaxe e testes ficam para o CI/CT 100.

## Plano proposto (aguarda aprovação de [W])

1. [W] responde a tabela do RUNBOOK §1 (pacote, owner, contato, diretoria, representantes, nomes do estatuto).
2. [W] ratifica a ADR 0451 (merge da onda 1).
3. Agente: `migrate --pretend` no CT 100 na branch da onda 2 e da onda 6; anexa a saída aqui.
4. Agente confere a lane `verticais-pest` verde nos PRs das ondas 2–6 e no PR do seed.
5. [W] dispara o backup só-banco pelo Actions (RUNBOOK §3); agente confere o zip.
6. [W] mergeia onda 2 (migration 1 sobe sozinha); agente roda a auditoria §7.
7. [W] instala o módulo, ajusta o pacote e cria a empresa no superadmin; informa o `business_id`.
8. Seed: simulação e depois `--aplicar` (RUNBOOK §6).
9. [W] cria os papéis diretoria/representante na empresa.
10. Ondas 3, 4, 5, 6 em ordem; auditoria de novo após a 6.
11. Smoke com usuário da Uniafibro e com usuário de outra empresa (403).
12. RUNBOOK passa de `rascunho` para `ativo` com a data da execução.

## Perguntas abertas para [W]

1. Qual pacote/plano? Existe um com `associacoes_module` ou cria um novo (gratuito?)?
2. Quem é o owner da conta (nome, usuário, e-mail)? A senha você digita na tela.
3. Telefone, e-mail e endereço da sede da rede.
4. Quem da diretoria entra com `associacoes.rede`?
5. Já há representantes de AGFESC e AFIBROMAR para virar usuário?
6. Nome completo do estatuto de cada associação (o seed grava a sigla).
7. Qual ambiente é o "staging" desta entrega: CT 100 (testes) basta, ou quer uma cópia de produção?
