---
title: "RUNBOOK — Data readiness da Uniafibro (empresa, migrations, dados iniciais, Tier 0)"
module: Associacoes
owner: W
status: rascunho
last_validated: "2026-11-14"
preconditions:
  - "ADR 0451 ratificada (merge do PR da onda 1)"
  - "Dados da empresa respondidos por [W] (seção 1)"
  - "Backup do banco feito e conferido (seção 3) ANTES do merge da onda 2"
related_adrs:
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — Data readiness da Uniafibro

> **Quem executa o quê.** Criar a empresa, ligar o pacote e dar permissão é **[W] pela tela do
> superadmin**. O agente prepara, simula e confere; não cria empresa em produção nem liga módulo.
> Status `rascunho` até a primeira execução real ser conferida.

## 0. Fatos medidos (2026-11-14, `origin/main` `532ba51fbb`)

- **O deploy roda `php artisan migrate --force` sozinho** a cada push em `main` que toque código
  (`.github/workflows/deploy.yml`, passo "Artisan migrate"). Merge da onda 2 = migration em produção.
- **O deploy NÃO faz dump do banco.** O passo "Snapshot pré-deploy" é só de arquivos (o próprio
  workflow avisa). O banco só tem o `spatie/laravel-backup` noturno (01:30, zip em
  `public/uploads/UltimatePOS/`). O `backup:run` sai com exit 1 toda noite por causa do e-mail de
  aviso (SMTP 535), com o zip pronto — por isso o backup se confere pelo **arquivo**, não pelo exit.
- **CLI não tem sessão:** `App\Scopes\ScopeByBusiness` não filtra nada sem usuário logado. Em
  `tinker`, comando ou seed, a empresa tem que vir **explícita** na consulta (ADR 0093).
- Superadmin cria empresa **sem CNPJ**: `tax_number_1` é opcional em `StoreBusinessRequest`.
- Triggers MySQL já rodam em produção (Fisio, Forja, Marketplaces): a trigger da onda 2 não é novidade de privilégio.

## 1. Dados que [W] precisa fornecer antes de produção

| Campo | Proposta | Status |
|---|---|---|
| Nome da empresa | "Uniafibro — União das Associações de Fibromiálgicos de Santa Catarina" (nome do estatuto, PR #10865) | confirmar |
| CNPJ | vazio — em formação (ADR 0451). Nenhuma tela diz que já é formalizada | decidido |
| Tipo | associação sem fins lucrativos | confirmar |
| Pacote (plano) | qual pacote do superadmin? precisa ter `associacoes_module` marcado. Existe um ou cria? Preço/gratuito? | **perguntar** |
| Moeda / fuso | BRL · America/Sao_Paulo | confirmar |
| Dono da conta (owner) | nome, usuário e e-mail de quem da diretoria vira dono. **A senha [W] digita na tela** | **perguntar** |
| Telefone, e-mail, endereço da empresa | da sede da rede | **perguntar** |
| Diretoria da rede (usuários com `associacoes.rede`) | quem são, e-mail de cada | **perguntar** |
| Representantes por associação | 1 usuário por associação (AGFESC, AFIBROMAR) ou ainda não? | **perguntar** |
| Nome completo de cada associação-membro | estatuto de AGFESC e AFIBROMAR (o seed grava a sigla como nome) | **perguntar** |

## 2. Migrations — ordem de execução

Só **duas** migrations, ambas só `CREATE TABLE` de tabelas novas `assoc_*`. Nenhuma altera tabela existente.

| # | Arquivo | Onda / PR | Cria |
|---|---|---|---|
| 1 | `Modules/Associacoes/Database/Migrations/2026_11_13_000001_create_assoc_tables.php` | onda 2 · #10878 | `assoc_associacoes`, `assoc_representantes`, `assoc_documentos`, `assoc_associadas`, `assoc_consentimentos` + trigger `trg_assoc_consentimentos_no_delete` |
| 2 | `Modules/Associacoes/Database/Migrations/2026_11_13_000060_create_assoc_portal_tokens_table.php` | onda 6 · #10880 | `assoc_portal_tokens` |

Ondas 3 (#10883), 4 (#10882) e 5 (#10881) **não trazem migration**. Atenção: as ondas 3 a 6 nasceram
da onda 2 e carregam os commits dela — mergear qualquer uma antes leva a migration 1 junto.
**Ordem de merge recomendada: 2 → 3 → 4 → 5 → 6.**

Propriedades conferidas no código: `Schema::hasTable` em toda criação (rodar de novo não quebra);
`business_id` NOT NULL + índice + FK em toda tabela; `down()` remove trigger e tabelas na ordem
inversa das FKs. Mesmo com o merge, nada aparece para empresa alguma sem o pacote (`AssociacoesLiberado`).

⚠️ `modules_statuses.json` ganha `"Associacoes": true` na onda 2: as rotas existem em produção logo
após o merge, barradas por pacote + permissão. "Ligar para a Uniafibro" é o pacote (seção 5), não o merge.

## 3. Backup — antes do merge da onda 2 (obrigatório)

1. GitHub Actions → **Deploy to Hostinger** → *Run workflow* em `main`, com
   `skip_migrate = true` e artisan extra `backup:run --only-db --disable-notifications`.
2. Conferir o arquivo novo em `public/uploads/UltimatePOS/` (data/hora de agora, tamanho na faixa
   do noturno). **Sem o zip conferido, não mergear.** O exit do comando não serve de prova (seção 0).
3. Anotar nome e tamanho do zip no session log do dia.

## 4. Simulação antes de qualquer ambiente

No CT 100 (testes não rodam local), numa worktree da branch da onda:

```bash
php artisan migrate --pretend
```

Esperado: só `create table assoc_*` e a trigger; **nenhum** `alter table` em tabela existente.
Prova complementar: a lane `verticais-pest` (MySQL) aplica as migrations e roda os contratos de
esquema e isolamento (`AssociacoesEsquemaContratoTest`, `AssociacoesAcessoContratoTest`).

## 5. Sequência de produção ([W] executa os passos marcados)

1. [W] ratifica a ADR 0451 (merge da onda 1).
2. Backup da seção 3 conferido.
3. [W] mergeia a onda 2 → o deploy aplica a migration 1. Conferir no log do job a linha `create_assoc_tables ... DONE`.
4. Auditoria Tier 0 (seção 7) — tudo zero.
5. [W] **Superadmin → Módulos → Instalar "Associacoes"** (grava `associacoes_version`).
6. [W] **Superadmin → Pacotes**: criar/editar o pacote com `Modulo Associacoes` marcado.
7. [W] **Superadmin → Empresas → Nova**, com os dados da seção 1 e o pacote do passo 6.
8. Anotar o `business_id` gerado (aparece na lista de empresas do superadmin).
9. Seed das associações-membro (seção 6): primeiro simulação, depois `--aplicar`.
10. [W] na empresa nova: papel "Diretoria da rede" com `associacoes.access` + `associacoes.rede` + `associacoes.editar`; papel "Representante" só com `associacoes.access` (+ `associacoes.editar` se for cadastrar).
11. Ondas 3, 4, 5 em ordem; depois onda 6 (aplica a migration 2) e auditoria de novo.
12. Smoke: logar como diretoria → `/associacoes` mostra AGFESC e AFIBROMAR; logar com usuário de **outra** empresa → 403.

## 6. Dados iniciais (seed versionado)

Comando `associacoes:semear-rede` (`Modules/Associacoes/Console/SemearRedeCommand.php`, PR empilhado na onda 2):

- grava **AGFESC (Criciúma)** e **AFIBROMAR (Maravilha)** em `assoc_associacoes`, situação `ativa`;
- `--business` obrigatório; sem `--aplicar` só simula; idempotente (pula o que já existe, não sobrescreve);
- **não** grava associada (dado pessoal com consentimento, D5 — só pela tela).

Pelo Actions (Run workflow em `main`, `skip_migrate = true`), artisan extra:

```text
associacoes:semear-rede --business=<ID>
associacoes:semear-rede --business=<ID> --aplicar
```

**Finalidades de consentimento não são seed.** São lista fechada versionada em
`config('associacoes.consentimento_versoes')`: `associada` (obrigatória), `whatsapp`, `visivel_rede`,
todas versão `1`; os textos chegam com a onda 4. Nada a gravar no banco.

## 7. Auditoria Tier 0 (CT 100 ou tinker em produção — consultas só de leitura)

Toda consulta tem a empresa explícita ou compara empresas entre tabelas; o escopo do Model não vale em CLI.

```sql
-- a) linhas sem empresa (esperado 0 em todas; a coluna é NOT NULL, então >0 = schema errado)
SELECT 'assoc_associacoes' t, COUNT(*) n FROM assoc_associacoes WHERE business_id IS NULL
UNION ALL SELECT 'assoc_representantes', COUNT(*) FROM assoc_representantes WHERE business_id IS NULL
UNION ALL SELECT 'assoc_documentos', COUNT(*) FROM assoc_documentos WHERE business_id IS NULL
UNION ALL SELECT 'assoc_associadas', COUNT(*) FROM assoc_associadas WHERE business_id IS NULL
UNION ALL SELECT 'assoc_consentimentos', COUNT(*) FROM assoc_consentimentos WHERE business_id IS NULL;

-- b) filho apontando para pai de OUTRA empresa (FK não impede isso; esperado 0 em todas)
SELECT 'representante x associacao' t, COUNT(*) n FROM assoc_representantes r JOIN assoc_associacoes a ON a.id = r.associacao_id WHERE r.business_id <> a.business_id
UNION ALL SELECT 'representante x usuario', COUNT(*) FROM assoc_representantes r JOIN users u ON u.id = r.user_id WHERE r.business_id <> u.business_id
UNION ALL SELECT 'documento x associacao', COUNT(*) FROM assoc_documentos d JOIN assoc_associacoes a ON a.id = d.associacao_id WHERE d.business_id <> a.business_id
UNION ALL SELECT 'associada x associacao', COUNT(*) FROM assoc_associadas s JOIN assoc_associacoes a ON a.id = s.associacao_id WHERE s.business_id <> a.business_id
UNION ALL SELECT 'associada x contato', COUNT(*) FROM assoc_associadas s JOIN contacts c ON c.id = s.contact_id WHERE s.business_id <> c.business_id
UNION ALL SELECT 'consentimento x associada', COUNT(*) FROM assoc_consentimentos k JOIN assoc_associadas s ON s.id = k.associada_id WHERE k.business_id <> s.business_id
UNION ALL SELECT 'representante contato x associacao', COUNT(*) FROM assoc_associacoes a JOIN contacts c ON c.id = a.representante_contact_id WHERE a.business_id <> c.business_id;

-- c) depois da onda 6
SELECT COUNT(*) FROM assoc_portal_tokens p JOIN assoc_associadas s ON s.id = p.associada_id WHERE p.business_id <> s.business_id;

-- d) trigger LGPD presente
SHOW TRIGGERS LIKE 'assoc_consentimentos';

-- e) dados só na empresa da rede (esperado: uma única linha, com o business_id da Uniafibro)
SELECT business_id, COUNT(*) FROM assoc_associacoes GROUP BY business_id;
```

Qualquer resultado diferente do esperado: parar, não seguir para a próxima onda, abrir incidente (ADR 0093, Política de incidente).

## 8. Rollback

- **Antes de qualquer dado real** (só o seed): `php artisan migrate:rollback --path=Modules/Associacoes/Database/Migrations --pretend`, conferir, depois sem `--pretend`. Remove só as tabelas `assoc_*`.
- **Com associadas/consentimentos gravados:** **não** fazer rollback — a trigger existe para que a prova de consentimento não suma. Desligar o pacote da empresa (corta acesso no request seguinte) e restaurar do zip da seção 3 só por decisão [W].

## Referências

- [ADR 0451](../../decisions/0451-associacoes-rede-uniafibro-area-de-membros.md) (na branch da onda 1 até o merge) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [RUNBOOK-artisan-command-pattern](RUNBOOK-artisan-command-pattern.md)
- Session log: [2026-11-14-2239-data-readiness-uniafibro](../../sessions/2026-11-14-2239-data-readiness-uniafibro.md)
