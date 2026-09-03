---
title: "MCP sai da Jana e vai pra Forja — ondas 2..7 do item 4 da ADR 0366 (mesa de decisão [W])"
status: proposta
date: "2026-09-02"
owners: [W]
proposed_by: Claude Code (a pedido de [W])
parent_module: Forja
numero_candidato: 389
related_adrs: [53, 62, 70, 87, 93, 121, 224, 256, 334, 344, 351, 358, 366]
related_specs:
  - memory/requisitos/Jana/BRIEFING.md (entrada 2026-08-13 — as duas consequências da onda 1)
  - memory/requisitos/Jana/SCOPE.md (purpose declara a fronteira em execução)
  - memory/requisitos/Forja/SCOPE.md (contains registra cada recebimento com data)
related_charters: []
---

# MCP sai da Jana e vai pra Forja — as ondas que faltam do item 4

> **Status: `proposta`.** ADR é Tier 0 — **só [W] cunha**, e ratificar é ato de merge (R10).
> A [ADR 0366](../0366-fronteira-jana-forja-governance-kb.md) §D-C item 4 diz que mover as `Mcp*`
> *"exige ADR própria + janela"*; a onda 1 saiu em 2026-08-13 sob autorização verbal de [W]
> (*"mcp foi para forja, isso já decidi"*), e **essa ADR nunca foi escrita**. Este documento é o
> rascunho dela. Número candidato **0389** (`node scripts/governance/next-id.mjs adr`, 2026-09-02).
> Ao virar ADR, o frontmatter troca para o schema canônico (`status: proposto` → `aceito` no merge —
> o enum de [`adr.schema.json`](../../../scripts/memory-schemas/adr.schema.json) não tem `proposta`).

## 1 · Contexto

### 1.1 — O que a onda 1 fez, e o que ela deixou aberto

O commit `c07cb44c6a` ([#5722](https://github.com/wagnerra23/oimpresso.com/pull/5722), 2026-08-13)
moveu **61 migrations** de `Modules/Jana/Database/Migrations/` para `Modules/Forja/`, preservando o
nome de arquivo — logo a tabela `migrations` casa e nada re-roda em produção.

Recibo: `git show c07cb44c6a --name-status --format='' -M | awk '$1 ~ /^R/ && $2 ~ /Jana\/Database\/Migrations/ && $3 ~ /Forja\/Database\/Migrations/' | wc -l` → **61** (de 90 arquivos tocados).

Mudou o dono **derivado do schema**. O **código não seguiu**: as 30 entidades, o servidor JSON-RPC,
as 40 tools, os 5 services, os 10 comandos, o middleware e os 44 testes continuam na Jana.

### 1.2 — O gap medido (execução, não leitura)

**Pergunta:** instalada a Jana **por módulo** (`module:migrate Jana`, que é o que a rota `/ia/install`
dispara via [`BaseModuleInstallController:128`](../../../app/Http/Controllers/BaseModuleInstallController.php)),
quantas das tabelas que o código MCP **ainda na Jana** consome deixam de ser provisionadas?

| Medida | Valor | Como |
|---|---|---|
| Tabelas `mcp_*` consumidas por código que ficou na Jana | **42** | união de `protected $table` das 30 `Entities/Mcp/` + `DB::table('mcp_*')` no resto do módulo |
| Dessas, provisionadas **só** por migration da Forja | **41** | `comm -12` do conjunto acima com as 46 `Schema::create('mcp_*')` da Forja |
| Dessas, provisionadas pelo **core** (independe de módulo) | **1** | `mcp_briefs`, criada por SQL cru em `database/migrations/2026_05_06_170045_create_daily_brief_schema.php` |
| Tabelas `mcp_*` criadas por `module:migrate Jana` | **0** | execução, abaixo |

**Portanto o gap é `41 de 42`** — e as 41 são provisionadas pelas 61 migrations que hoje pertencem à
Forja. ⚠️ **61 e 41 não são o mesmo número e não devem ser conflacionados**: 61 conta *migrations*
(várias são `ALTER`/`add column`), 41 conta *tabelas*. Quem citar "N de 61" está somando unidades
diferentes.

**A execução** (banco SQLite vazio, `DB_CONNECTION`/`DB_DATABASE` por env var):

```bash
php artisan module:migrate Jana --force     # 10 migrations DONE, depois FAIL (fulltext, ver limite)
php artisan tinker --execute='...sqlite_master...'   # → 10 tabelas, TODAS copiloto_*, ZERO mcp_*
php artisan module:migrate Forja --force    # controle positivo → 7 tabelas mcp_* nascem
```

O controle positivo importa: prova que o mecanismo **cria** `mcp_*` quando o módulo dono é invocado,
logo o zero da Jana é ausência real, não sonda quebrada.

**Limite honesto da medição.** O CT 100 estava **fora do ar** nesta janela (`tailscale status` →
*"offline, last seen 1h ago"*; corroborado por [#6593](https://github.com/wagnerra23/oimpresso.com/pull/6593)
e [#6595](https://github.com/wagnerra23/oimpresso.com/pull/6595), abertos no mesmo dia sobre a queda),
então rodou em SQLite local. O driver não suporta índice FULLTEXT e a 11ª migration da Jana
(`copiloto_cache_semantico`) abortou a corrida — **10 das 21 executaram**. A perna restante foi
fechada por varredura contada **com controle positivo**: o mesmo padrão de criação de tabela acha
**46** ocorrências `mcp_*` nas migrations da Forja e **0** nas 21 da Jana (as 21 criam só
`copiloto_*`/`jana_*`). Refazer no CT 100 quando ele voltar **confirma**; não muda o veredito, porque
o insumo — o conjunto de migrations do módulo — é o mesmo (`git ls-tree` de `HEAD` × `origin/main`:
idênticas).

**Em produção o gap é mascarado** pelo `migrate --force` global do
[`deploy.yml`](../../../.github/workflows/deploy.yml), que roda todas as migrations sem olhar módulo.
Ele é real em qualquer instalação por módulo — e some quando o código seguir o schema.

### 1.3 — A superfície que as ondas movem (medido)

| Peça | Qtd | Onde |
|---|---|---|
| Entidades | **30** | `Modules/Jana/Entities/Mcp/` |
| Servidor JSON-RPC | **1** | `Modules/Jana/Mcp/OimpressoMcpServer.php` |
| Tools | **40** | `Modules/Jana/Mcp/Tools/` |
| Resources + Prompts | **3** | `Modules/Jana/Mcp/{Resources,Prompts}/` |
| Services | **5** | `Modules/Jana/Services/Mcp/` |
| Comandos artisan | **10** | `Modules/Jana/Console/` (nome casando `Mcp`) |
| Middleware | **1** | `Http/Middleware/McpAuthMiddleware.php`, alias `mcp.auth` em `JanaServiceProvider:48` |
| Testes | **44** | `Modules/Jana/Tests/` (de 175 do módulo) |

**Consumidores do namespace** `Modules\Jana\Entities\Mcp` — **220 arquivos**, dos quais **188** são
código/config e **32** são documentos em `memory/` (que a migração não quebra — são prosa datada):

| Área | Arquivos |
|---|---|
| `Modules/Jana` | 113 |
| `Modules/Forja` | **52** |
| `Modules/KB` | 8 |
| `Modules/Governance` | 4 · `tests/` 4 · `app/` 2 · `scripts/` 1 · `Modules/PaymentGateway` 1 |
| baselines/CI | `phpstan-baseline.neon` · `governance/multi-tenant-scope-baseline.json` · `.github/` |

> ⚠️ **Nota de método.** `git grep -F 'Modules\Jana\Entities\Mcp'` sai **rc=128 com zero linhas** —
> o `\E` de `\Entities` fecha o `\Q…\E` interno do git (é a lápide [§5 2026-07-31](../../proibicoes.md)).
> A contagem acima usa `git grep -lE 'Modules.Jana.Entities.Mcp'`, com controle positivo
> (`git grep -l 'McpTask'` → 160).

### 1.4 — Dois acoplamentos que decidem a ORDEM das ondas

1. **A Forja já tem uma árvore `Mcp/`** — 5 tools em `Modules/Forja/Mcp/Tools/` (Handoff + BriefFetch).
   Elas são **registradas pelo servidor que está na Jana**: o `$tools` do `OimpressoMcpServer` lista as
   5 por FQCN (`Modules\Forja\Mcp\Tools\...`). Ou seja, o registro já é cross-módulo hoje, e é uma
   **lista explícita num arquivo só** — mover tool é editar essa lista. **Colisão de nome entre as 40
   da Jana e as 5 da Forja: zero** (`comm -12` dos basenames).
2. **O `mcp.auth` é da Jana e o KB depende dele** — o alias sai do `JanaServiceProvider:48` e é
   consumido por [`Modules/KB/Routes/api.php:32`](../../../Modules/KB/Routes/api.php). Mover o
   middleware sem mover o registro derruba `/api/mcp/kb/*`.

O runtime confirma a superfície HTTP (oráculo, não grep): `php artisan route:list --path=mcp` →
**25 rotas**, com as `team-mcp/*` **já** servidas por `Modules\Forja\Http\Controllers\*`. A superfície
administrativa do MCP já é Forja; o que falta migrar é o núcleo. *(Este `route:list` rodou no checkout
principal, em `0543136e7f`; as 25 são o retrato daquela base, e refazer em `origin/main` é o passo (a)
do canary da D-4 — as contagens de arquivo acima, essas sim, são todas de `origin/main`.)*

### 1.5 — `pre-adr-introspect` (prior art)

**Interna.** O projeto já fez extração de módulo com este formato: [ADR 0170](../0170-paymentgateway-extracao-camada-cobranca.md)
extraiu `Modules/PaymentGateway` de `RecurringBilling` (entidades renomeadas + drivers movidos), e a
proposta [`strangler-spec-anchored-reconstrucao-sdd`](strangler-spec-anchored-reconstrucao-sdd.md) já
adotou o vocabulário *strangler* para reconstrução incremental. O `SCOPE.md` da Forja mostra o padrão
**vivo** de absorção: cada recebimento (Admin do MCP, identidade, handoff, ingest CC) entrou com data,
razão e **URL preservada**. Esta proposta **reusa esse padrão**, não inventa outro.

**Externa** (LC-21). A técnica tem nome: o movimento em ondas com compatibilidade é **Parallel Change
(expand–migrate–contract)**, dentro de uma estratégia **Strangler Fig**; o alias de namespace durante o
`expand` é `class_alias()` — hoje usado em **1** arquivo do repo (um teste), não como padrão. Ferramenta
madura na stack: **Rector** (`RenameClassRector`) faz rename de classe em massa, e **Deptrac**/**PHPat**
enforçam a fronteira depois. **Nenhuma das duas está no `composer.json`** — adotá-las é dependência
nova e exigiria ADR própria. **Recomendação: não adotar agora** — o eixo que mais dói aqui é
`DB::table('mcp_*')` (string, não tipo), que está **fora** do alcance do Deptrac, e o rename é
mecânico o bastante para `git mv` + substituição ancorada com teste de identidade.

## 2 · Decisão proposta

### D-1 — As ondas 2..7, uma por PR, cada uma revertível sozinha

Ordem escolhida por **acoplamento**, não por tamanho: primeiro o que ninguém importa, por último o que
todo mundo importa.

| Onda | Move | Por que nesta posição | Risco |
|---|---|---|---|
| **2** | 5 `Services/Mcp/` | superfície interna, sem rota nem registro | baixo |
| **3** | 10 comandos `Console/Commands/Mcp*` | registro é do ServiceProvider do módulo; `schedule` do Kernel cita a classe | baixo — **conferir `schedule:list` antes e depois** |
| **4** | 40 tools + 3 Resources/Prompts + `OimpressoMcpServer` | o servidor e as tools viajam **juntos**: separá-los deixaria a lista de registro apontando pra fora nos dois sentidos | médio |
| **5** | `McpAuthMiddleware` + alias `mcp.auth` | depende de 4 (o servidor já estar na Forja); o KB é consumidor externo | médio — **derruba `/api/mcp/kb/*` se o alias não for registrado no `ForjaServiceProvider` no MESMO PR** |
| **6** | 30 `Entities/Mcp/` | é o que **188 arquivos** importam — vai por último, quando os consumidores internos já mudaram de casa | **alto** |
| **7** | 44 testes + baselines (`phpstan-baseline.neon`, `multi-tenant-scope-baseline.json`, `.github/ci-sqlite-pest.list`) | fecha a conta | baixo |

**Cada onda é um PR** com pré-flight ([`proibicoes.md`](../../proibicoes.md) §Regra Primária FASE 1) e
smoke real (R1). Nenhuma onda mistura mover com refatorar.

### D-2 — Os invariantes (o que NÃO pode mudar em nenhuma onda)

1. **URL** — nenhuma rota muda de path ([ADR 0087](../0087-drift-resolution-sem-mover-url.md): o
   frontend chama por string literal). É o mesmo compromisso que a Forja já honrou nos recebimentos anteriores.
2. **Nome de tool MCP** — `brief-fetch`, `my-work`, `tasks-list`… são contrato com o cliente MCP do time
   ([ADR 0053](../0053-mcp-server-governanca-como-produto.md)). Muda o FQCN da classe, nunca o nome da tool.
3. **Permission** — `jana.*` **preservadas**; renomear revoga acesso em silêncio (ADR 0087).
4. **Nome de arquivo de migration** — já garantido na onda 1; nada re-roda.
5. **Nome de tabela** — as `mcp_*` seguem `mcp_*`. Esta migração **não** toca schema.
6. **Multi-tenant Tier 0** — `business_id` global scope inalterado; as exceções cross-business já
   declaradas (`mcp_actors`, `cowork_handoffs`) continuam declaradas ([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)).

### D-3 — A mitigação do gap (1) até o código seguir o schema

O gap `41 de 42` existe **enquanto** houver código MCP na Jana. Ele fecha sozinho ao fim da onda 6 —
antes disso, a mitigação proposta é **declarar, não remendar**:

- **Não** duplicar as migrations na Jana para "consertar" o `module:migrate`. Duplicata de migration é
  schema com dois donos — cria o problema que a onda 1 resolveu.
- **Declarar a dependência de módulo**: hoje `Modules/Jana/module.json` traz `"requires": []` e o
  `InstallController` da Jana tem **23 linhas** — só declara nome, chave e versão, **sem nenhuma
  verificação de tabela**. Então o install **completa sem erro**: ele provisiona corretamente o que é
  dele, e a ausência das 41 só aparece no primeiro uso de uma tool. A mitigação é `requires: ["Forja"]`
  + uma verificação explícita que falhe com mensagem nomeando o módulo que falta.
- **Registrar no `SCOPE.md` da Jana** (que já declara a fronteira em execução) a linha *"instalar por
  módulo não provisiona as 41 tabelas `mcp_*` — depende de `module:migrate Forja`"*, com a data.

⚠️ A alternativa "rodar sempre `migrate` global" **não é mitigação** — é o mascaramento que já existe
em produção e foi justamente o que escondeu a superfície por 20 dias.

### D-4 — Janela e canary

A ADR 0366 pede "janela". Proposta:

- **Janela:** ondas 2 e 3 em qualquer dia útil. Ondas **4, 5 e 6** só com o CT 100 **no ar** (a suíte
  MySQL real é o único lugar onde os 44 testes rodam — [`proibicoes.md`](../../proibicoes.md) §Ambiente)
  e **fora** de dia de fechamento fiscal.
- **Canary:** cada onda passa por (a) `php artisan route:list --path=mcp` antes/depois com **25 rotas**
  nos dois lados; (b) `php artisan schedule:list` antes/depois na onda 3; (c) uma chamada JSON-RPC real
  a `tools/list` no CT 100 conferindo que a **contagem de tools não caiu**; (d) suíte MySQL do CT 100
  lendo **assertions**, não `0 failed` (LC-13).
- **Sem canary de 7 dias** entre ondas: o consumidor é o time interno, não cliente pagante — o custo de
  esperar supera o risco. Se [W] preferir, a onda 6 (a de risco alto) pode ficar sozinha numa semana.

### D-5 — Rollback

Cada onda é um `git mv` + substituição ancorada, sem migration e sem mudança de schema — logo o
rollback é **`git revert` do PR da onda**, sem passo de banco. É a propriedade que torna o faseamento
barato, e é a razão de **nenhuma onda poder incluir mudança de schema ou de contrato**: se incluir,
perde o revert de um comando. O ponto de não-retorno é só o fim da onda 6, quando o `module:migrate Jana`
deixa de ter gap por não haver mais consumidor.

## 3 · Consequências

- **Positivas** — o gap `41 de 42` fecha; a Jana volta a ser mensurável como produto (remédio do alarme
  da [ADR 0334](../0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)); o registro
  cross-módulo de tools deixa de existir; e a dívida de acoplamento por tabela que a onda 1 inverteu
  volta a cair — o [BRIEFING da Jana](../../requisitos/Jana/BRIEFING.md) registrou em **2026-08-13** que
  `Jana>Forja` foi de 4 para 54 queries, *"transitório por construção"*. Esse número é **dele**, medido
  naquela data; recontá-lo é passo do canary, não desta proposta.
- **Negativas / custo** — 6 PRs; a onda 6 toca ~188 arquivos de uma vez; durante a transição há estado
  intermediário com entidades e tabelas em módulos diferentes — **legítimo desde que declarado**, que é
  o que o `SCOPE.md` dos dois módulos já faz.
- **Não muda** — contratos MCP (0053), tasks Jira-style ([0070](../0070-jira-style-task-management-current-md-removed.md)),
  multi-tenant Tier 0 (0093), separação Hostinger ≠ CT 100 ([0062](../0062-separacao-runtime-hostinger-ct100.md)),
  `MCP_TOOLS_EXPOSED=false` no Hostinger.

## 4 · Gate de reversão

Esta ADR é revertida (nova, com `supersedes`) se:

1. a onda 4 ou 5 quebrar consumidor **fora** dos 4 módulos que a matriz da 0366 enxergava — o risco que
   a própria 0366 nomeou no gate dela, e que esta proposta parcialmente fecha ao contar `app/` (2),
   `tests/` (4), `scripts/` (1) e `Modules/PaymentGateway` (1); **ou**
2. o rollback de qualquer onda exigir passo de banco — sinal de que a onda misturou mover com mudar, e
   o faseamento perdeu a propriedade que o justifica.

## 5 · O que esta proposta NÃO decide

- **Não move um arquivo.** Igual à 0366 §D-C, declara o plano; cada onda é PR próprio.
- **Não adota Rector nem Deptrac** — seriam dependência nova, exigem ADR própria, e o eixo que mais dói
  (`DB::table` por string) está fora do alcance delas.
- **Não muda o nome de nada** — tabela, tool, URL, permission e arquivo de migration ficam como estão.
- **Não ratifica a onda 1 retroativamente.** Ela foi feita sob autorização verbal de [W] em 2026-08-13 e
  está em produção; esta proposta a **registra** como fato datado e cobre daqui pra frente.

## Referências

- [ADR 0366](../0366-fronteira-jana-forja-governance-kb.md) §D-C item 4 — a pendência que este documento endereça
- [ADR 0053](../0053-mcp-server-governanca-como-produto.md) · [ADR 0070](../0070-jira-style-task-management-current-md-removed.md) — contratos que **não** mudam
- [ADR 0170](../0170-paymentgateway-extracao-camada-cobranca.md) — precedente interno de extração de módulo
- [ADR 0344](../0344-two-strikes-cobre-processo.md) — por que nenhuma máquina nova nasce nesta proposta
- [`BRIEFING.md` da Jana](../../requisitos/Jana/BRIEFING.md), entrada 2026-08-13 — onde as duas consequências estavam registradas só em prosa
- PR [#5722](https://github.com/wagnerra23/oimpresso.com/pull/5722) (`c07cb44c6a`) — a onda 1
