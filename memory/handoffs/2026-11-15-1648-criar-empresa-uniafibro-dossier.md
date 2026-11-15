---
date: "2026-11-15"
time: "16:48"
slug: criar-empresa-uniafibro-dossier
tldr: "Dossier para [W] criar a empresa Uniafibro no superadmin com dados provisórios da AGFESC. AGFESC NÃO foi encontrada em banco (local desligado, CT 100 offline); no repo só existe o nome oficial dela, no site. Endereço, CNPJ-referência e contatos dependem de [W]. Nada criado em produção."
cycle: null
related_adrs:
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - 0093-multi-tenant-isolation-tier-0
next_steps:
  - "[W] informa o endereço da AGFESC (sede provisória); dono da conta = [W] (decidido), com e-mail e usuário novos"
  - "Pacote: criar \"Contrato Uniafibro Semestral\" copiando o #4 da ROTA LIVRE + Modulo Associacoes (seção 4) — [W] decide o preço"
  - "[W] roda as consultas da seção 3 (só leitura) e confirma que não existe empresa duplicada"
  - "[W] cria a empresa pela tela (seção 5) depois do merge da onda 2"
---

# Dossier — criar a empresa Uniafibro no superadmin

> Direção [W]: *"criar a empresa uniafibro com os dados da agefesc"* e, para contatos,
> *"preenche depois"*. Leitura adotada: endereço e responsável da **AGFESC** como **provisórios**,
> até a Uniafibro ter sede própria. **O agente não cria a empresa** — [W] cria na tela.
> Sequência completa (backup, migrations, auditoria): [RUNBOOK-data-readiness-uniafibro](../requisitos/Infra/RUNBOOK-data-readiness-uniafibro.md).

## 1. A AGFESC existe no banco? — **não confirmado**

| Fonte | Resultado |
|---|---|
| MySQL local (`laravel-boost`, conexão `mysql`) | desligado — conexão recusada em 127.0.0.1:3306 |
| Produção via CT 100 (`tailscale ssh ct100-mcp`, receita do `financeiro-bridge-auditor`) | `ct100-mcp` **offline há 18 dias** no Tailscale |
| Repositório (`git grep` em `origin/main` e nas branches Associacoes/Captação) | só o site `resources/uniafibro/home.html`: nome oficial e região. **Sem CNPJ, endereço ou contato** — o próprio arquivo proíbe pôr esses dados sem confirmação da associação |

O que o repo garante sobre a AGFESC (público, do site):

- **Nome:** Associação Girassóis Fibromiálgicos do Extremo Sul Catarinense (AGFESC)
- **Cidade:** Criciúma; atua em Criciúma e região (Amrec, Amesc e Amurel)
- **CNPJ:** existe (obtido em março de 2024, segundo o site), mas o número **não está no repo**
- Utilidade pública municipal em Criciúma (Lei 8.786/2025)

Provável: a AGFESC **não** é empresa no oimpresso (ADR 0451 a trata como associação-membro, que
vira linha dentro da empresa Uniafibro, não empresa própria). A seção 3 confirma isso.

## 2. Valores para a tela

O formulário "Novo negócio" do superadmin (`StoreBusinessRequest` + `BusinessController@store`)
grava empresa, **um** local (endereço) e o usuário dono. Não tem campo de nome fantasia.

| Campo da tela | Valor | Origem |
|---|---|---|
| Nome do negócio *(obrigatório)* | `Uniafibro — União das Associações de Fibromiálgicos de Santa Catarina` | estatuto (PR #10865) |
| Nome fantasia | não existe no formulário. Proposta: depois de criar, renomear o **local** para `Uniafibro-SC` em Configurações → Locais | [W] confirma |
| Moeda *(obrigatório)* | Real (BRL) | padrão |
| Fuso | `America/Sao_Paulo` | padrão |
| Rótulo / número fiscal 1 | rótulo `CNPJ`, número **vazio** | em formação (ADR 0451) |
| Data de início | a data do dia | — |
| País / Estado / Cidade do local | Brasil / Santa Catarina / Criciúma | AGFESC, **provisório** |
| CEP, endereço (landmark) | **[W] informa** — endereço da AGFESC | AGFESC, **provisório** |
| Celular / telefone do local | vazio | [W]: *"preenche depois"* |
| Site | `https://uniafibro.com.br` | site da rede |
| Dono: nome, sobrenome *(obrigatório)* | **[W]** — decidido por [W] em 2026-11-15 (*"Eu"*), provisório até a diretoria assumir | ver nota |
| Dono: usuário, e-mail *(obrigatórios, únicos)* | **[W] digita** | ver nota |
| Dono: senha *(obrigatória)* | **[W] digita** na tela | nunca em git/chat |
| Pacote | seção 4 | [W] escolhe |
| Pago via *(obrigatório se houver pacote)* | ex.: `offline` / cortesia | [W] escolhe |

**Nota sobre o dono.** Nome, e-mail e celular de pessoa são dado pessoal: ficam **fora** deste
arquivo, do commit e do PR (ADR 0451 D5) — [W] digita direto na tela. O e-mail do dono tem de ser
**único** em `users`, e o e-mail do próprio [W] já tem conta. Como [W] é o dono (decisão
2026-11-15), usar um e-mail novo: um alias do e-mail dele com `+` (o provedor entrega na mesma
caixa, e para o sistema é outro endereço) ou um endereço da rede. O usuário também precisa ser novo.
Quando a diretoria assumir, ela ganha usuário próprio com papel Admin e [W] sai ou fica como apoio.

**Marcar como provisório.** Depois de criar, anotar no session log do dia: "endereço do local =
AGFESC, provisório até a sede da Uniafibro". Nenhuma tela pública mostra o endereço do local.

## 3. Consultas de verificação (só leitura — [W] roda antes de criar)

```sql
-- a) já existe empresa da rede ou da AGFESC? (esperado: nenhuma linha)
SELECT id, name, tax_number_1, created_at
FROM business
WHERE name LIKE '%uniafibro%' OR name LIKE '%fibromi%' OR name LIKE '%agfesc%'
   OR name LIKE '%girass%' OR name LIKE '%afibromar%';

-- b) a AGFESC aparece como CONTATO de alguma empresa (cliente/prospect)? Mostra só empresa e tipo.
SELECT business_id, type, COUNT(*) n
FROM contacts
WHERE name LIKE '%agfesc%' OR name LIKE '%girass%fibro%' OR supplier_business_name LIKE '%agfesc%'
GROUP BY business_id, type;

-- c) pacotes disponíveis e quais já liberam o módulo (o nome da chave aparece em custom_permissions)
SELECT id, name, price, is_active, is_private,
       JSON_EXTRACT(custom_permissions, '$.associacoes_module') AS associacoes,
       JSON_EXTRACT(custom_permissions, '$.captacao_module')    AS captacao
FROM packages
WHERE deleted_at IS NULL
ORDER BY price, id;

-- d) e-mail / usuário do dono livres? (troque pelos valores — rode e apague do histórico)
SELECT COUNT(*) FROM users WHERE email = '<email-do-dono>' OR username = '<usuario-do-dono>';
```

Se (b) achar a AGFESC como contato numa empresa, **não** copie de lá automaticamente: aquela
linha pertence a outra empresa (ADR 0093). Leia o endereço e digite na tela.

## 4. Pacote

**Decisão [W] (2026-11-15):** *"Pacote seis meses acho igual a Rotalivre"* — mesmo formato do
contrato da ROTA LIVRE.

O pacote da ROTA LIVRE é o **#4 "Contrato Rota Livre Semestral"**, e ele é **exclusivo** dela
(`memory/reference/feedback-habilitar-modulo-por-business.md`, levantamento de 2026-05-18; em
produção o padrão é um pacote "Contrato <cliente>" por empresa). Por isso:

- **Não usar o #4 na Uniafibro.** Marcar `Modulo Associacoes` nele com "Atualizar inscrições
  existentes" ligaria o módulo para a ROTA LIVRE também.
- **Criar um pacote novo copiando o #4:** **"Contrato Uniafibro Semestral"**, privado, mesma
  duração (6 meses), mesmos limites de locais e usuários, e as mesmas permissões que fizerem
  sentido para uma associação, **mais** `Modulo Associacoes`. Preço: [W] decide (o do #4 ou cortesia).
- Para copiar, ler o #4 antes (consulta 3e) ou abrir `/superadmin/packages/4/edit` só para olhar, sem salvar.
- O checkbox `Modulo Associacoes` só aparece **depois** que a onda 2 estiver em produção e o
  módulo instalado. O de Captação, depois da onda 1 da Captação (`claude/captacao-onda1`, ADR 0452
  proposta): aí editar **este** pacote e marcar "Atualizar inscrições existentes".
- A assinatura guarda uma cópia das permissões do pacote (`subscriptions.package_details`).
  Mudou o pacote depois de criada a empresa? Só chega na Uniafibro com "Atualizar inscrições existentes".

```sql
-- e) formato do pacote da ROTA LIVRE, para copiar (só leitura)
SELECT id, name, `interval`, interval_count, trial_days, location_count, user_count, price,
       is_private, is_active, custom_permissions
FROM packages WHERE id = 4;
```
- Editar pacote existente com outras empresas nele liga o módulo para elas também: conferir
  quem usa antes (`SELECT business_id FROM subscriptions WHERE package_id = <id> AND status = 'approved'`).

## 5. Passo a passo para [W] na tela

Pré-condição: onda 2 do Associacoes mergeada, backup do banco conferido (RUNBOOK §3), auditoria
(RUNBOOK §7) zerada.

1. Rodar as consultas da seção 3.
2. **Superadmin → Módulos** → instalar **Associacoes** (grava `associacoes_version`).
3. **Superadmin → Pacotes** (`/superadmin/packages`) → criar ou editar o pacote da seção 4 com
   `Modulo Associacoes` marcado. Salvar.
4. **Superadmin → Negócios** (`/superadmin/business`) → **Novo negócio** (abre o drawer) → preencher
   com a tabela da seção 2 → escolher o pacote → salvar.
5. Anotar o `business_id` que aparece na lista.
6. Entrar na empresa nova → **Configurações → Locais** → renomear o local para `Uniafibro-SC` (se aprovado).
7. Seed das associações-membro: Actions → Deploy to Hostinger → Run workflow em `main`,
   `skip_migrate = true`, artisan extra `associacoes:semear-rede --business=<ID>` (simulação) e
   depois `... --aplicar` (PR #10891).
8. Criar os papéis Diretoria da rede e Representante (RUNBOOK §5, passo 10).

## 6. Divergências encontradas

- **A rede no site já tem 5 associações** (ACPFA Joinville, AFIBROMAR, AFICOR Concórdia,
  AGFESC, AREFI Indaial); a ADR 0451 fala de 2. **Resolvido** ([W] 2026-11-15, *"Sim"*): o seed
  (#10891) grava as 5, com nome oficial para AGFESC, AFICOR e AREFI e sigla para AFIBROMAR e ACPFA.

## Estado MCP no momento do fechamento

Sem nova consulta MCP nesta etapa; estado é o do handoff
[2026-11-14-2239-data-readiness-uniafibro](2026-11-14-2239-data-readiness-uniafibro.md)
(brief #864, sem cycle ativo, nenhuma task criada). Nenhuma empresa criada, nenhum banco consultado.
