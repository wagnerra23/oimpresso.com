---
id: requisitos-jana-runbook-memoria
slug: jana-runbook-memoria
title: "Jana — Runbook da tela Memória (/ia/memoria)"
type: runbook
module: Jana
tela: Jana/Memoria
owner: W
status: ativo
date: "2026-08-07"
last_validated: "2026-08-07"
related_adrs:
  - 0031-memoriacontrato-mem0-default
  - 0036-replanejamento-meilisearch-first
  - 0052-memoria-jana-3-angulos-faturamento
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0131-tiering-memoria-canonico-local-segredo
preconditions:
  - "Usuário autenticado num business (o grupo /ia já garante auth)"
  - "Driver de memória resolvido via config('copiloto.memoria.driver') — Meilisearch em prod, null em CI"
  - "Pelo menos 1 fato em jana_memoria_facts pro par (business_id, user_id) da sessão"
steps:
  - "Abrir /ia/memoria autenticado e conferir a lista de fatos ativos"
  - "Editar um fato: o Salvar só habilita com texto E motivo preenchidos"
  - "Conferir o registro em activity_log (log_name jana_memoria_fato_editado) com autor + motivo"
  - "Esquecer um fato e conferir o soft delete + o registro de auditoria"
  - "Conferir isolamento Tier 0: fato de outro business nunca aparece"
---

# RUNBOOK — Memória da Jana (`/ia/memoria`)

> **Tipo:** runbook reproduzível
> **Irmãos:** [`Memoria.charter.md`](../../../resources/js/Pages/Jana/Memoria.charter.md) (lei) · [`Memoria.casos.md`](../../../resources/js/Pages/Jana/Memoria.casos.md) (contrato UC)
> **Validado:** **estático** contra `origin/main` em 2026-08-07 — rotas, controller, contrato do driver, entity e componente conferidos arquivo a arquivo, mais o protótipo lido no DesignSync (`prototipo-ui/cowork/jana-merge.jsx`, função `JmMemoria`).
> ⚠️ **Fluxo vivo (editar/esquecer contra prod) NÃO exercitado nesta data.** O smoke real com screenshot é o passo 6 abaixo e é a evidência que fecha a R1 — sem ele, este RUNBOOK descreve o desenho, não o comportamento observado.

Tela LGPD-first onde o dono/gestor **vê, corrige e apaga** os fatos que a Jana aprendeu sobre o negócio. Cumpre acesso + retificação + esquecimento (LGPD Art. 18). Persona: Larissa (ROTA LIVRE, biz=4, monitor 1280px) e Wagner. Sem essa tela a memória vira caixa-preta — quebra confiança e compliance.

Vive dentro do `AppShellV2`, sob o header de área `JanaAreaHeader` (aba **Memória** do vocabulário `Painel | Conversa | Memória` fechado na onda 2 da US-COPI-148).

## Superfície (medida em `origin/main`, 2026-08-07)

| Peça | Onde |
|---|---|
| Page Inertia | [`resources/js/Pages/Jana/Memoria.tsx`](../../../resources/js/Pages/Jana/Memoria.tsx) |
| Controller | [`Modules/KB/Http/Controllers/MemoriaController.php`](../../../Modules/KB/Http/Controllers/MemoriaController.php) — mora no KB, a US nasceu na Jana |
| Rotas | [`Modules/Jana/Http/routes.php:151-153`](../../../Modules/Jana/Http/routes.php) — `jana.memoria.index` (GET) · `jana.memoria.update` (PATCH) · `jana.memoria.destroy` (DELETE) |
| Contrato de dados | [`Modules/Jana/Contracts/MemoriaContrato.php`](../../../Modules/Jana/Contracts/MemoriaContrato.php) — `listar` / `atualizar` / `esquecer` |
| Drivers | `MeilisearchDriver` (prod) · `NullMemoriaDriver` (dev/CI) · `McpMemoriaDriver` (delega a fallback) · `RetrievalTelemetryDecorator` (decorator) |
| Entity | [`Modules/Jana/Entities/MemoriaFato.php`](../../../Modules/Jana/Entities/MemoriaFato.php) — tabela `jana_memoria_facts`, `HasBusinessScope` + `SoftDeletes` + `LogsActivity` |
| Auditoria | `activity_log` (spatie) — o Model já loga `valid_from`/`valid_until`/`deleted_at` sob `log_name = jana_memoria_fato`; a **edição** loga à parte sob `jana_memoria_fato_editado` (ver passo 3) |

> ⚠️ **`redirect` de rota:** `/ia/memorias` (plural) é **302** pra `/ia/memoria`. O ghost do menu já aponta pro destino real desde a onda 2 — não reintroduzir o plural em link novo.

## Passos

### 1. Abrir a tela

Login → `https://oimpresso.com/ia/memoria`. Esperado: header de área com a aba **Memória** acesa, título "O Copiloto lembra de você", a barra com busca + filtro de categoria, e a lista de fatos.

Cada fato mostra: texto, categoria, **origem** (chat / brief auto / inserção manual), "desde &lt;data&gt;" e relevância.

### 2. Editar um fato — o motivo é obrigatório

Clicar em **Editar**. O formulário abre inline com o texto e o campo **"Motivo da correção"** (placeholder `fica no log de auditoria`).

**Contrato duro:** o botão Salvar fica **desabilitado** enquanto texto **ou** motivo estiverem vazios, e o backend **rejeita** o PATCH sem motivo (`422`) mesmo que alguém contorne a UI. Os dois lados existem de propósito — a UI é conveniência, o servidor é a garantia.

### 3. Conferir o log de auditoria

```sql
SELECT log_name, description, causer_id, properties, created_at
FROM activity_log
WHERE log_name = 'jana_memoria_fato_editado'
ORDER BY id DESC LIMIT 5;
```

Esperado: uma linha por edição, com `causer_id` = quem editou e `properties.motivo` = o texto digitado.

> 🔒 **O que NÃO entra no log:** o texto do fato (antigo ou novo). É decisão deliberada da entity (`logOnly(['valid_from','valid_until','deleted_at'])`, comentário *"NÃO logga `fato`/`metadata` (PII livre)"*) e a edição respeita a mesma regra. O `motivo` é prosa digitada pelo usuário, então passa por `PiiRedactor` antes de ser gravado — motivo com CPF vira `[REDACTED:CPF]`.

### 4. Esquecer um fato

Clicar em **Apagar** → a confirmação aparece **na própria linha** ("Apagar é irreversível" · Apagar / Manter). Confirmar.

Esperado: o fato some da lista; `deleted_at` preenchido (soft delete — LGPD opt-out, nada é apagado fisicamente); registro em `activity_log`.

### 5. Isolamento Tier 0 (ADR 0093)

`business_id` e `user_id` vêm **sempre** de `session('user.business_id')` + `auth()->id()` — nunca de query string ou body. Conferir com sessão biz=1 que `?business_id=999` na URL não muda nada.

### 6. Smoke real pós-deploy (R1 — fecha o DoD)

Browser MCP em `https://oimpresso.com/ia/memoria`: screenshot 1280px (monitor da Larissa) + console sem `EXCEPTION`. Colar a screenshot no PR. **Sem isso a mudança não está pronta** — CI verde não prova render.

## Armadilhas catalogadas

- ⛔ **A lane não segue o arquivo.** `jana-pest.yml` dispara por `paths: Modules/Jana/**`, mas o controller desta tela mora em `Modules/KB/`. Teste novo aqui exige ligar **os dois pontos** — `paths:` e a lista de execução — senão o gate fica mudo com cara de cobertura (lápide §5 2026-08-02).
- ⛔ **`tests/Feature/Modules/Copiloto/MemoriaControllerTest.php` não roda.** Não está no `.github/ci-sqlite-pest.list` (149 alvos, conferido 2026-08-07). Ele exercita só o driver, nunca o Controller, e espera os route names `copiloto.memoria.*` — que **não existem** (os reais são `jana.memoria.*`). Está "verde" por não-execução (LC-13). Não usar como evidência de nada.
- ⛔ **`NullMemoriaDriver` ignora `business_id`.** O teste acima grava em `businessId: 1` e lê em `listar(4, ...)` esperando encontrar. Isolamento multi-tenant **não** pode ser provado nesse driver — use MySQL real (`jana-pest.yml`).
- ⛔ **Ids de US fantasma.** O charter e o cabeçalho do `.tsx` citam `US-COPI-MEM-005/008/012`; nenhuma existe no SPEC da Jana (0 hits, medido 2026-08-07). Mesmo padrão do `US-JANA-PAINEL-001` que a onda 1 da US-COPI-148 pegou. Não ancorar trabalho novo nesses ids.
