---
date: 2026-06-20
topic: "como-integrar — T5 mcp_audit_log tamper-evident (hash-chain SHA-256)"
type: session
---

# como-integrar — T5: mcp_audit_log tamper-evident (hash-chain SHA-256)

> Agente introspectivo. Só leu memory/ + código. NÃO executou nada.
> Data: 2026-06-20 · Tier-0 (audit log forense) · Veredito: **AUSENTE — cria do zero, mas com padrão 100% provado em Ponto**

---

## Fase 1 — INVENTÁRIO

| O que procurei | Onde achei | Status |
|---|---|---|
| Padrão hash-chain SHA-256 provado | `Modules/Ponto/Services/MarcacaoService.php` (`payloadCanonico`, `registrar`, `verificarIntegridade`) | **completo** — referência canônica a copiar |
| Schema hash/hash_anterior | `Modules/Ponto/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php:38-39` (`char('hash_anterior',64)` + `char('hash',64)`) | completo |
| Hash-chain JÁ em `mcp_audit_log` | — | **ausente** — tabela só tem trigger imutabilidade, sem encadeamento |
| Migration que cria `mcp_audit_log` | `Modules/Jana/Database/Migrations/2026_04_29_100005_create_mcp_audit_log_table.php` | sem colunas hash |
| Trigger append-only `mcp_audit_log` | `Modules/Jana/Database/Migrations/2026_05_05_230001_add_immutability_triggers_to_mcp_audit_log.php` (ADR 0084) | completo — **NÃO QUEBRAR** |
| Writer canônico (factory) | `Modules/Jana/Entities/Mcp/McpAuditLog.php::registrar()` | completo — **único ponto de escrita** |
| Call sites de `registrar()` | 5: `McpAuthMiddleware.php` (3×: sucesso/quota/denied), `KbAiController.php` (3×), `RetrievalTelemetryDecorator.php`, `RewrapCredentialsCommand.php`, (test `McpSchemaTest`) | gravam SEM hash |
| ADR sobre imutabilidade audit | `memory/decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md` | aceito — base, mas só fala trigger, não hash-chain |
| ADR/SPEC sobre hash-chain no audit | grep `tamper`/`hash-chain` em memory/ → só Ponto/NfeBrasil/Arquivos | **ausente pra mcp_audit_log** — precisa ADR nova |
| Teste multi-tenant que toca McpAuditLog | `Modules/Jana/Tests/Feature/MultiTenantIsolation{,Comprehensive}Test.php` | completo — não pode regredir |
| Schema sintético de teste | `tests/Feature/Modules/Copiloto/Mcp/McpSchemaTest.php:86-110` (replica `mcp_audit_log` à mão p/ SQLite) | **precisa espelhar colunas hash** |

**Conclusão Fase 1:** feature AUSENTE na tabela alvo, mas o algoritmo está 100% provado e testado em Ponto. Trabalho = transplantar o padrão MarcacaoService → audit log, sem reinventar. NÃO PARAR: não há duplicação a evitar, há um padrão a reusar.

---

## Fase 2 — PEGADINHAS APLICÁVEIS (filtradas)

| # | Pegadinha | Por que se aplica aqui |
|---|---|---|
| 1 | **Append-only / imutabilidade (ADR 0084)** | Triggers `trg_mcp_audit_log_no_update/no_delete` lançam `SIGNAL 45000`. Hash-chain é **append-only-friendly** (só lê N-1 e insere N), MAS o cálculo do hash precisa do hash da linha anterior do MESMO escopo de cadeia — e isso é um SELECT, nunca UPDATE. Qualquer tentação de "backfill por UPDATE" das linhas antigas vai **bater no trigger e falhar**. Backfill, se necessário, é só leitura (verificarIntegridade), não escrita. |
| 2 | **Multi-tenant Tier 0 (ADR 0093)** | `McpAuditLog` usa `HasBusinessScope`. **Decisão de design crítica:** a cadeia é por-`business_id` ou global? `business_id` é **nullable** (CLI/denied gravam `user_id=0`, biz=null). Se a cadeia for por-biz, linhas com biz=null formam cadeia própria. Recomendo **cadeia global única** (igual ordem de `id`) porque audit forense precisa detectar exclusão de QUALQUER linha, inclusive cross-tenant — mas o SELECT do "hash anterior" deve usar `withoutGlobalScopes()` COM comentário SUPERADMIN, senão o global scope filtra e quebra a cadeia entre tenants. |
| 3 | **Writer único = `registrar()` factory** | TODOS os 5 call sites passam por `McpAuditLog::registrar()`. Plugar o cálculo de hash AÍ cobre 100% das escritas de uma vez. NÃO espalhar lógica de hash nos call sites. |
| 4 | **Concorrência / corrida no hash anterior** | `MarcacaoService` usa `DB::transaction` + lock pessimista (NsrService) pra serializar. O audit log NÃO tem lock hoje e é gravado em request quente (best-effort try/catch). Sem serialização, 2 inserts concorrentes podem ler o mesmo `hash_anterior` → cadeia bifurca. Mitigar com `DB::transaction` + `lockForUpdate()` na última linha, OU aceitar cadeia por-`id` (auto-increment garante ordem, mas hash_anterior pode referenciar id N-2 se N-1 ainda não commitou). **Este é o risco técnico nº1.** |
| 5 | **best-effort try/catch silencioso** | `registrar()` é chamado dentro de `try/catch` que engole exceção (`McpAuthMiddleware:128`, `:172`, `:199`). Se o cálculo de hash lançar, o audit some silenciosamente. Hash calc deve ser à prova de exceção (campos faltando → string vazia, igual `payloadCanonico` do Ponto). |
| 6 | **Schema sintético do teste (SQLite)** | `McpSchemaTest.php:86` recria `mcp_audit_log` à mão. Adicionar colunas `hash`/`hash_anterior` na migration SEM espelhar no teste → teste passa com schema desatualizado e não cobre o campo novo. Atualizar os dois. **E** o teste hoje faz `markTestSkipped` em não-SQLite (linha 24-26) — tamper-test precisa rodar onde realmente importa. |
| 7 | **Migration idempotente + down()** | Convenção `.claude/rules/migrations.md`: `ALTER TABLE ADD COLUMN` precisa guard (`if (!Schema::hasColumn(...))`) p/ re-run em worktrees/staging, e `down()` que remove as colunas. |
| 8 | **Runtime Hostinger vs CT 100 (ADR 0062)** | Migration roda no Hostinger (DB prod). Sem daemon, sem octane. PHP `hash('sha256', ...)` é nativo — OK no Hostinger. Sem dependência de extensão exótica. |

**Observação (NÃO é pegadinha catalogada):** não há ADR que defina a SEMÂNTICA da cadeia (por-biz vs global, ordenação por `id` vs `ts`, o que entra no payload canônico). Tier-0 exige ADR nova ANTES de codar — é decisão de design custosa e forense. Atenção: sem ADR, qualquer reviewer trava o PR.

**NÃO há UI.** Sem MWART, sem charter, sem `.tsx`. (Eventual painel "verificar integridade" seria feature separada — fora do escopo T5.)

---

## Fase 3 — PONTO DE PLUGUE (onde tocar)

| Peça | Arquivo + linha | Ação |
|---|---|---|
| Migration colunas hash | `Modules/Jana/Database/Migrations/2026_06_20_xxxxxx_add_hash_chain_to_mcp_audit_log.php` ⚠️ **criar** | `ALTER TABLE`: `char('hash_anterior',64)->nullable()` + `char('hash',64)->nullable()` (nullable p/ backfill seguro de linhas legadas). Idempotente + `down()` que dropa. NÃO mexer na migration original 100005 (append-only de histórico de migrations). |
| Serviço de hash | `Modules/Jana/Services/AuditChainService.php` ⚠️ **criar** | Espelha `MarcacaoService`: `payloadCanonico(array)`, `proximoHash($hashAnterior, $payload)`, `verificarIntegridade()`. Algoritmo `config('...hash_algoritmo','sha256')`. |
| Writer (plugue central) | `Modules/Jana/Entities/Mcp/McpAuditLog.php::registrar()` (linha 55-68) | DENTRO da factory, antes do `static::create()`: buscar hash da última linha da cadeia (`orderByDesc('id')->first()->hash`, com `withoutGlobalScopes` comentado SUPERADMIN se cadeia global), montar payload canônico, calcular hash, injetar `hash_anterior`+`hash` nos atributos. Envolver em `DB::transaction`+`lockForUpdate` se optar por serialização. |
| Config algoritmo | `Modules/Jana/Config/config.php` | adicionar chave `audit.hash_algoritmo => 'sha256'` (espelha `pontowr2.marcacao.hash_algoritmo`). |
| Schema sintético do teste | `tests/Feature/Modules/Copiloto/Mcp/McpSchemaTest.php:86-110` | adicionar `char('hash_anterior',64)->nullable()` + `char('hash',64)->nullable()` no `Schema::create('mcp_audit_log')`. |
| Teste tamper-evidence | `Modules/Jana/Tests/Feature/Mcp/AuditChainTamperTest.php` ⚠️ **criar** | cadeia cresce; hash_anterior[N]==hash[N-1]; `verificarIntegridade()` detecta linha mutada/removida; multi-tenant não vaza. |
| ADR semântica da cadeia | `memory/decisions/00xx-mcp-audit-hash-chain-tamper-evidence.md` ⚠️ **criar** | define: cadeia global vs por-biz, ordenação, payload canônico, relação com ADR 0084. `related: [0084, 0093]`. |
| RUNBOOK (opcional) | `memory/requisitos/Jana/RUNBOOK.md` ou nota | procedimento "como verificar integridade do audit log" + o que fazer se quebrar. |

⚠️ **Plugues a CRIAR (não existem):** migration de ALTER, `AuditChainService`, teste tamper, ADR. Reuso direto: `McpAuditLog::registrar()` (writer único), padrão `MarcacaoService` (algoritmo), schema Ponto (colunas).

---

## Fase 4 — CHECKLIST PRÉ-CÓDIGO

```markdown
## Pré-código checklist — mcp_audit_log tamper-evident (hash-chain SHA-256)

### Antes de Edit/Write
- [ ] ADR nova necessária? SIM — semântica da cadeia (global vs por-biz, ordenação,
      payload canônico) é decisão Tier-0 forense. Wagner aprova ANTES de codar.
- [ ] Schema migration necessária? SIM — ALTER TABLE add hash/hash_anterior char(64)
      nullable, idempotente + down(). NÃO editar migration 100005.
- [ ] Feature flag necessária? NÃO — audit é infra, não tem toggle de UI.
- [ ] RUNBOOK existente? ausente p/ Jana audit — criar nota de verificarIntegridade.
- [ ] DECISÃO DE DESIGN a travar no ADR: cadeia é GLOBAL (recomendado p/ forense
      cross-tenant) ou por-business_id? Define se SELECT do hash anterior usa
      withoutGlobalScopes (comentado SUPERADMIN).

### Pegadinhas a respeitar (filtradas)
- [ ] Não quebrar trigger append-only ADR 0084 — hash-chain é SELECT N-1 + INSERT N,
      NUNCA UPDATE. Backfill de legados = leitura, não escrita.
- [ ] Multi-tenant Tier 0 — se cadeia global, withoutGlobalScopes COM comentário
      SUPERADMIN no SELECT do hash anterior; senão global scope quebra a cadeia.
- [ ] Plugar SÓ em McpAuditLog::registrar() — cobre os 5 call sites de uma vez.
- [ ] Serializar contra corrida no hash_anterior (DB::transaction + lockForUpdate)
      OU justificar no ADR por que ordem-por-id basta.
- [ ] Hash calc à prova de exceção (registrar() roda em try/catch que engole erro).
- [ ] Espelhar colunas hash no schema sintético McpSchemaTest.php:86.

### Pontos de plugue (em ordem)
- [ ] ADR: memory/decisions/00xx-mcp-audit-hash-chain.md — semântica + related 0084,0093
- [ ] Config: Modules/Jana/Config/config.php — audit.hash_algoritmo='sha256'
- [ ] Service: Modules/Jana/Services/AuditChainService.php — payloadCanonico/proximoHash/verificarIntegridade
- [ ] Migration: Modules/Jana/Database/Migrations/2026_06_20_*_add_hash_chain_to_mcp_audit_log.php
- [ ] Writer: McpAuditLog::registrar() — injeta hash_anterior+hash antes do create()
- [ ] Test: Modules/Jana/Tests/Feature/Mcp/AuditChainTamperTest.php — cadeia + detecção + multi-tenant
- [ ] Test schema: tests/.../McpSchemaTest.php:86 — add colunas hash

### Smoke / CI
- [ ] ATENÇÃO: "PHP / Pest (Jana)" NÃO existe como job próprio. modules-pest.yml
      matrix = [Arquivos, ComunicacaoVisual, Fiscal, NfeBrasil, Repair, Vestuario]
      — Jana FORA. ci.yml (Pest Unit) roda allowlist explícita e NÃO inclui
      McpSchemaTest nem o novo teste. => o novo AuditChainTamperTest precisa ser
      ANCORADO numa allowlist de workflow pra rodar no CI, OU adicionar 'Jana'
      ao matrix de modules-pest.yml. Sem isso, teste verde local mas invisível no CI.
- [ ] biz=1 (Pest, ADR 0101) — nunca biz=4 em teste.
- [ ] Migration roda no Hostinger (DB prod). hash('sha256') nativo PHP — OK Hostinger.
- [ ] Pós-deploy: SELECT manual conferindo hash_anterior encadeia nas últimas N linhas.

### Estimativa (IA-pair, ADR 0106)
- ADR + design da cadeia: ~30-45 min (decisão Tier-0, precisa Wagner)
- Migration + Service + plugue writer: ~30 min (padrão copiado de Ponto)
- Testes + schema sintético: ~30 min
- Anexar no CI (allowlist/matrix): ~15 min
- Total IA-pair: ~2h (sem contar review humano Tier-0)
```

---

## Veredito ao parent

- **Status:** AUSENTE na tabela `mcp_audit_log` — **cria do zero**, mas transplantando padrão 100% provado de `Modules/Ponto/Services/MarcacaoService.php` (zero invenção de algoritmo).
- **Maior risco/pegadinha:** decisão da SEMÂNTICA da cadeia sob multi-tenant Tier-0 (global vs por-`business_id`, com `business_id` nullable) + corrida no `hash_anterior` sem lock — define se quebra ADR 0093 ou bifurca a cadeia. Exige ADR antes de codar.
- **Postura:** precisa-review-wagner (Tier-0 audit forense + ADR de semântica).
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
