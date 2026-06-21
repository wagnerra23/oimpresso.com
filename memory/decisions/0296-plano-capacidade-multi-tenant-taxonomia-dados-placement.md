---
slug: 0296-plano-capacidade-multi-tenant-taxonomia-dados-placement
number: 296
title: "Plano de capacidade à prova de falhas — taxonomia canônica de dados + placement multi-DB (Hostinger 6GB → ops no CT 100 → tenant DB off-shared)"
type: adr
status: proposed
authority: canonical
lifecycle: ativo
kind: infra
decided_by: [W]
decided_at: ""
module: infra
tags: [capacidade, multi-tenant, business_id, hostinger, ct100, mysql, bloat, retencao, lgpd, audit, hash-chain, monitoramento, tier-0]
supersedes: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0280-postura-multi-tenant-tabelas-mcp-governanca
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0084-mcp-audit-log-append-only-triggers
  - 0294-mcp-audit-log-hash-chain-tamper-evident
  - 0053-conhecimento-canonico-git-fonte-verdade
  - 0058-reverb-substituido-por-centrifugo-frankenphp
---

# ADR 0296 — Plano de capacidade à prova de falhas: taxonomia de dados + placement multi-DB

> **Status: proposed.** Aguarda aprovação Wagner nos GATES marcados 🔒 abaixo. As fases P0 mecânicas/seguras (marcadas ✅ MECÂNICO) podem começar antes da aceitação formal; tudo que muda placement de host, conexão ou que toca dado de negócio (C1) e a cadeia hash-chain (C6) exige **GATE Wagner explícito**.
>
> ⚠️ **Honestidade sobre a rodada adversarial:** este ADR foi consolidado a partir da FUNDAÇÃO (taxonomia desenhada + ancorada em ADRs reais). **As PARTES de adversários chegaram VAZIAS (`[]`)** — nenhum sketch nem veredito de adversário foi registrado para esta rodada. O Risk Register abaixo é derivado dos failure modes que a própria FUNDAÇÃO expõe + análise do incidente, **não** de veredictos adversariais verificados. **GATE-ADV (🔒):** rodar a rodada adversarial (mín. 1 skeptic por classe C1–C7 + 1 por fase P0/P1/P2) ANTES de promover este ADR de `proposed` → `aceito`. Não promover sem isso.
>
> ✅ **ATUALIZAÇÃO 2026-06-21 — GATE-ADV PARCIALMENTE EXECUTADO** (ver **§RODADA ADVERSARIAL** no fim do doc). Veredicto: **prova-de-falhas-COM-fixes**. Cobertura efetiva: **C2/C5/C6** (lente migração) + crítico de completude; o resto (C1/C3/C4/C7/P0/P1/P2 + transversais) ficou **bloqueado por overload sustentado da API** — pendente. **R-ADV permanece 🟠** até cobertura completa.

## Contexto

### O incidente (madrugada 2026-06-21)

O ERP oimpresso (multi-tenant UltimatePOS por `business_id`) roda no **Hostinger SHARED hosting**, banco MySQL `u906587222_oimpresso` **limitado a 6 GB**. O banco bateu os 6 GB e a Hostinger **auto-revogou** `INSERT/UPDATE/CREATE` do único usuário de DB, deixando só `SELECT/DELETE/DROP` (o "grant torto" que a gente viu). Efeito cascata:

1. `mcp:tasks:sync` e o **ADS logger** pararam de escrever → **erro MySQL 1142** (`command denied`).
2. Cache stale → health-check `spec_id_drift` disparou 646 falhas.
3. Causa-raiz imediata: a tabela interna **`mcp_memory_documents_history`** sozinha tinha **~5 GB** (histórico de versões de docs de memória, **cresce sem limite**).
4. Resolvido **truncando** `_history` → DB 6180 MB → 816 MB → escrita voltou.
5. **MAS já reenche:** 0 → 72 MB em ~30 min. Truncate manual não é solução — é tampão.

### A restrição estrutural (o defeito de fundo)

`config/database.php` (confirmado nesta sessão) tem **UMA** conexão `mysql` default (o Hostinger 6 GB) + `redis`. **Não existe conexão separada para ops/governança.** Logo **as 7 classes de dado compartilham o mesmo banco de 6 GB e o mesmo grant único**. Quando estoura, a Hostinger revoga `INSERT/UPDATE` de **TUDO** — dado de cliente inclusive. Isto é: ops interno (mcp_*, logs, cache, audit ≈ 390 MB+ e crescendo **sem teto**) compete por bytes com o dado de negócio do cliente (C1 ≈ 500 MB) no mesmo grant. **Esse é o defeito que o Wagner intuiu:** *"vários clientes no mesmo banco vai encher rápido."* Não é só "vão encher" — é que **o ruído operacional sem teto derruba o sinal de negócio** quando a quota estoura.

### Inventário u906587222_oimpresso (2026-06-21 pós-truncate)

385 tabelas · 888.9 MB · 241 com `business_id`. Top ofensores: `fin_titulos` 158.9 MB · `transactions` 110.4 MB · `messages` 84.2 MB · `mcp_memory_documents_history` 72.5 MB (REENCHENDO) · `mcp_dual_brain_decisions` 67.2 MB · `transaction_sell_lines` 60.7 MB · `activity_log` 51.1 MB · `fin_titulo_baixas` 47.2 MB · `jobs` 36.1 MB · `contacts` 34.2 MB · `mcp_memory_documents` 22.4 MB · `licenca_log` 18.8 MB · `oauth_refresh_tokens` 12.1 MB · `oauth_access_tokens` 8.1 MB · `mcp_cc_messages` 7.2 MB · `mcp_audit_log` 2.9 MB · `_bkp_*` (vários descartáveis). Dado de negócio real ≈ 500 MB; logs/ops internos ≈ o resto.

### Restrições duras (Tier 0 — não negociáveis)

- **[ADR 0093](0093-multi-tenant-isolation-tier-0.md)** — isolamento por `business_id` é Tier 0 **IRREVOGÁVEL**. 241/385 tabelas têm `business_id` com global scope. Vazar dado entre tenants é o pior bug possível. **Nenhuma migração de host pode relaxar o global scope.**
- **[ADR 0062](0062-separacao-runtime-hostinger-ct100.md)** — app PHP-FPM **NÃO sai** do Hostinger; CT 100 (Proxmox, casa: Xeon/128 GB/2 TB, UPS+4G, **não é datacenter**) é o runtime de governança/MCP e já roda MariaDB (`oimpresso-staging-db`), Meilisearch, Langfuse/Jaeger. **IRREVOGÁVEL.**
- **[ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) / [0053](0053-conhecimento-canonico-git-fonte-verdade.md)** — conhecimento canônico vive em **GIT**; `mcp_memory_documents*` é **CACHE derivado** do git → git tem **todas** as versões → o `_history` no DB é **redundante e descartável**.
- **LGPD + [ADR 0084](0084-mcp-audit-log-append-only-triggers.md) + [0294](0294-mcp-audit-log-hash-chain-tamper-evident.md)** — `mcp_audit_log` é append-only (triggers MySQL) + hash-chain SHA-256 tamper-evident. Retenção legal → **NÃO se deleta por bloat**.
- **[ADR 0280](0280-postura-multi-tenant-tabelas-mcp-governanca.md)** — tabelas `mcp_*` dividem-se em **Grupo A** (governança, SEM `business_id` by-design — não é vazamento) e **Grupo B** (dado de cliente, COM `business_id`). Esta distinção separa C2 de C3 abaixo.
- **Hostinger shared:** 6 GB/banco. Sem MySQL root. GRANT só via hPanel. Quota estourada → `INSERT/UPDATE` auto-revogados.

---

## DECISÃO

### Parte 1 — Taxonomia canônica de dados (7 classes)

Todo dado do banco `u906587222_oimpresso` (e qualquer DB futuro do oimpresso) é classificado em **exatamente uma** das 7 classes. A classe determina: **onde vive** (placement), **quanto tempo vive** (retenção) e **se pode ser podado por bloat**. Esta tabela é o contrato canônico; tabela nova nasce com classe atribuída ou o gate de migração reprova.

| Classe | Definição (1 linha) | `business_id`? | Placement-ALVO | Retenção | Podável por bloat? |
|---|---|---|---|---|---|
| **C1 — Negócio-tenant** | Dado real do cliente do ERP (financeiro, vendas, estoque, contatos, PII) | **SIM** (0093 Grupo B) | **MySQL gerenciado/VPS off-shared, servido pelo PHP-FPM do Hostinger via Remote MySQL** (app fica no Hostinger → 0062 OK) | Longa/permanente (legal+comercial); pós-churn anonimizar | ❌ **NUNCA** |
| **C2 — Ops governança plataforma** | Estado vivo do dev de 1 org (backlog, tasks, leases) | **NÃO** (0280 Grupo A — by-design) | **ops-DB no CT 100** (MariaDB) | Média; arquivar US done | ✅ podável (não-legal) |
| **C3 — Ops por-tenant (telemetria IA)** | Telemetria/decisões da camada IA com sinal de tenant | **SIM** (0280 Grupo B) | **ops-DB no CT 100** (mantendo `business_id`+FK+scope) | Curta-média, janela rolante (ex.: 90d) → sumariza | ✅ podável |
| **C4 — Log efêmero / fila / token** | Fila, jobs, activity_log, tokens OAuth, sessões | misto | **Redis (fila/cache) + observabilidade CT 100 (Langfuse/Jaeger) para logs**; OAuth com pruning onde o app autentica | Efêmera (TTL curto) | ✅ truncável sem dó |
| **C5 — Cache derivado-do-git** | `mcp_memory_documents*` — cache + history de docs de memória | C5 cache: Grupo B; history: — | **git (verdade) + cache no ops-DB CT 100**; `_history` = ring buffer ou eliminado | Cache: regenerável; `_history`: **ZERO retenção longa** | ✅ **deve** ser podado (AUTOMÁTICO) |
| **C6 — Audit append-only / hash-chain** | `mcp_audit_log` — forense imortal, LGPD | nullable (cadeia global proposital) | **ops-DB CT 100 (append-only) + object-storage WORM frio por janela** | **Legal/imortal — NUNCA deletar** | ❌ **só particionar+arquivar frio** |
| **C7 — Backup descartável / scratch** | `_bkp_*`, `_bad_*` — lixo de migração/incidente | — | **DROP** (após dump comprimido p/ object-storage se houver dúvida) | Nenhuma | ✅ primeira poda |

> **Mapeamento de tabelas → classe** (canônico; é o que o monitor e o gate de migração leem):
> - **C1:** `fin_titulos`, `transactions`, `transaction_sell_lines`, `fin_titulo_baixas`, `transaction_payments`, `messages`, `contacts`, `products`, `variations`, `purchase_lines`, `account_transactions`, `sale_stage_history`, `rb_invoices`
> - **C2:** `mcp_tasks`, `mcp_task_events`, `mcp_work_leases`, `mcp_task_comments`, `mcp_jira_projects`, `mcp_epics`, `mcp_cycles`, `mcp_cycle_goals`, `mcp_components`, `mcp_workflows`, `mcp_views`, `mcp_inbox_notifications`, `mcp_task_dependencies`, `mcp_automation_runs`, `mcp_skill_versions`, `mcp_tokens`, `mcp_quotas`
> - **C3:** `mcp_dual_brain_decisions`, `mcp_cc_messages`, `mcp_cc_sessions`, `mcp_usage_diaria`, `mcp_alertas`, `mcp_alertas_eventos`, `mcp_scorecard_ai_suggestions`, `mcp_handoff_summaries`, `mcp_handoff_diffs`, `mcp_weekly_digests`, `mcp_doc_summaries`, `licenca_log`
> - **C4:** `jobs`, `failed_jobs`, `activity_log`, `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_auth_codes`, `sessions`, `cache`, `cache_locks`
> - **C5:** `mcp_memory_documents`, `mcp_memory_documents_history`
> - **C6:** `mcp_audit_log`
> - **C7:** `_bkp_bad_compras_20260602` + todos `_bkp_*` / `_bad_*`

### Parte 2 — Política de placement (onde cada classe vive + por quê)

**Princípio reitor:** o banco que serve o cliente (C1) **nunca mais** compete por bytes com ruído operacional sem teto. Logo, **separar os DBs por classe** é a correção estrutural — não só podar.

1. **C1 (negócio) → MySQL off-shared acessível ao Hostinger.** O app continua no Hostinger (0062 respeitado); só o *storage* do DB sai do shared 6 GB, via Remote MySQL apontando para MySQL gerenciado ou VPS dedicado. **NUNCA** colocar C1 como primário no CT 100 (casa, sem datacenter — indisponibilidade derruba o ERP do cliente).
2. **C2 + C3 + C5(cache) + C6 → ops-DB no CT 100** (MariaDB, ao lado do MCP server que já roda lá). Governança/telemetria/cache/audit **sempre foram** responsabilidade do CT 100 (0062) → migrar não fere 0062. C3/C6 mantêm `business_id`+FK+scope e a hash-chain **intactos**.
3. **C4 (log/fila) → Redis (fila/cache, já em `config/database.php`) + observabilidade CT 100** (Langfuse/Jaeger/loki-style já existentes) para `activity_log`. OAuth fica onde o app autentica (Hostinger), com **pruning agressivo** agendado.
4. **C5 `_history` → git é a verdade.** A tabela `mcp_memory_documents_history` **não deve existir como tabela ilimitada em banco nenhum.** Vira ring buffer (últimas K versões / N dias) OU é eliminada e o recall histórico aponta para `git log`.
5. **C7 → DROP.** Primeira poda, imediata.

**Por que CT 100 e não "tudo gerenciado":** custo. O CT 100 já está pago e rodando MariaDB+observabilidade; ops/governança/cache/audit **degradam graciosamente** se o CT 100 cair (governança/IA ficam indisponíveis, mas o **ERP do cliente continua de pé** porque C1 está noutro DB). Só C1, que é crítico e não tolera "casa", paga por storage gerenciado.

---

## ROADMAP FASEADO (com gates Wagner)

### P0 — AGORA (parar o sangramento, sem migrar host)

Recupera quota e **fecha as torneiras sem teto** dentro do próprio Hostinger. Quase tudo mecânico/seguro.

| Item | O quê | Tipo |
|---|---|---|
| P0.1 | **DROP C7** — `_bkp_*` / `_bad_*` após confirmar fix estável (dump→object-storage se dúvida). | ✅ MECÂNICO |
| P0.2 | **Teto mecânico + cron em C5** `mcp_memory_documents_history` — ring buffer (últimas K versões OU N dias). Git é a verdade (0061). | ✅ MECÂNICO |
| P0.3 | **Pruning agendado C4** — `jobs/failed_jobs` (pós-processado/N dias), `oauth:prune` (expirados), `activity_log` TTL 30–90d (export p/ observabilidade antes de truncar). | ✅ MECÂNICO |
| P0.4 | **Particionar C6** `mcp_audit_log` por período — **SEM deletar**, sem tocar trigger 0084 nem a cadeia 0294. | 🔒 GATE (toca audit) |
| P0.5 | **Monitoramento cota + grant-torto** no `jana:health-check` (ver INVARIANTE-MON abaixo). | ✅ MECÂNICO |
| P0.6 | **Fix do loop ADS logger** — o logger que parou no erro 1142 precisa de fallback (buffer/retry/degradação) e não pode reentrar em loop quando o grant está torto. | ✅ MECÂNICO |
| P0.7 | **Fix do backup do deploy** — o backup que rodava no deploy contribuía para o bloat / não validava espaço; alinhar com a receita de recuperação de classmap stale (incidente 2026-06-18). | ✅ MECÂNICO |

- **Artefatos concretos:** cron de poda C5/C4 (`schedule` em `app/Console/Kernel.php`); migration idempotente de particionamento C6; check novo em `jana:health-check`; ajuste no ADS logger (Modules/ADS/) com fallback; script `.claude/run/` de DROP de C7 (segue padrão dos `bridge-*.sql` já presentes).
- **Guardrails:** P0.2/P0.3 truncam só dado podável (C4/C5); **nenhum toque em C1/C6 deletando**. P0.4 só particiona. Todo DROP precede de `COUNT(*)` + dump.
- **Rollback:** C5 `_history` → regenerável do git (rollback é re-pull). C4 → efêmero (sem rollback necessário). C6 particionamento → reversível (merge de partição). C7 → dump comprimido guardado antes do DROP.
- **Critério de sucesso:** DB < 4.5 GB sustentado por 7 dias; `_history` estabilizado sob o teto (não reenche acima de K/N); health-check de cota **verde** e capaz de **alertar antes** de 75%; `INSERT/UPDATE` nunca mais revogados.

### P1 — Mover ops/logs pro CT 100 (degradação graciosa) + segunda conexão

| Item | O quê | Tipo |
|---|---|---|
| P1.1 | **Adicionar 2ª conexão** em `config/database.php` (`ops` / `governanca`) apontando para MariaDB do CT 100. | 🔒 GATE (estrutural) |
| P1.2 | **Migrar C2 + C3 + C5(cache)** para o ops-DB CT 100 — Grupo B (C3/C5) mantém `business_id`+FK+global scope (0093/0280). | 🔒 GATE |
| P1.3 | **Migrar C6 audit** para ops-DB CT 100 via **export append-only verificado** — NUNCA backfill (não disparar trigger 0084); validar hash-chain (0294) ponta-a-ponta pós-migração. | 🔒 GATE (audit/hash-chain) |
| P1.4 | **C4 logs → observabilidade CT 100** (`activity_log` → Langfuse/Jaeger). Fila → Redis confirmado. | ✅ MECÂNICO |
| P1.5 | **Degradação graciosa:** app no Hostinger trata ops-DB indisponível (CT 100 caiu) **sem quebrar o ERP** — escrita ops em fila/buffer, leitura com fallback. | 🔒 GATE (invariante crítica) |

- **Artefatos concretos:** nova conexão nomeada em `config/database.php`; migrations de movimentação por classe; rotina de export append-only verificado para C6; health-check `ops_db_reachable` (não-duro: ops-DB down **não** derruba cron do ERP); circuit-breaker no MCP/ADS para ops-DB.
- **Guardrails:** Tier 0 preservado na migração (teste de isolamento `business_id` antes/depois — `php artisan jana:health-check` check `multi_tenant_isolation`); hash-chain verificada; **zero perda** (linhas origem == destino + checksum).
- **Rollback:** cada migração de classe é reversível (a conexão antiga continua no Hostinger até o cutover ser validado; cutover por classe, não big-bang). Se ops-DB CT 100 falhar validação, app volta a apontar ops para Hostinger.
- **Critério de sucesso:** Hostinger contém **só C1 + C4-OAuth**; CT 100 ops-DB recebe C2/C3/C5/C6; derrubar o CT 100 em teste **não derruba** o ERP (smoke do cliente passa); isolamento `business_id` idêntico antes/depois.

### P2 — ESTRATÉGICO: tenant DB (C1) off-shared

| Item | O quê | Tipo |
|---|---|---|
| P2.1 | **Mover C1** do shared 6 GB para **MySQL gerenciado/VPS dedicado**, servido ao PHP-FPM do Hostinger via Remote MySQL. App fica no Hostinger (0062 OK). | 🔒 GATE Wagner (decisão de plataforma + custo) |

- **GATILHO recomendado (quando disparar P2):** quando, **após P0+P1**, **só C1** ocupar o Hostinger e a **projeção de crescimento de C1** (taxa medida pelo monitor) cruzar **3 GB (50% de 6 GB) com tendência de bater 4.5 GB em < 6 meses** — OU quando entrar o cliente N que, sozinho, empurra a projeção pra cima. Não migrar antes: enquanto C1 < 3 GB e estável, P0+P1 já resolvem; migração de plataforma é custo+risco que só se paga quando o **crescimento de negócio** (não o ruído) é o limitante.
- **OPÇÃO RECOMENDADA:** **MySQL gerenciado** (DBaaS) como primário de C1 — não VPS auto-gerenciado e **não** CT 100. Razão: C1 é crítico, precisa de SLA/datacenter/backup gerenciado; CT 100 é casa (energia/link, mitigado por UPS+4G mas sem garantia de datacenter — registrado no Risk Register R-CASA). VPS dedicado é aceitável como fallback de custo, mas transfere o ônus de HA/backup pro time.
- **Artefatos concretos:** conexão `mysql` default repontada para o DBaaS; runbook de cutover C1 (snapshot→sync→switch→verify) com janela; teste de Remote MySQL latência Hostinger↔DBaaS; backup gerenciado validado.
- **Guardrails:** Tier 0 isolamento revalidado no novo host; zero downtime alvo (réplica + cutover); LGPD (dados em região compatível).
- **Rollback:** manter o DB Hostinger como réplica read-only durante janela de validação; reverter DNS/conexão se latência ou isolamento falharem.
- **Critério de sucesso:** C1 fora do shared, sem limite de 6 GB, backup gerenciado, isolamento `business_id` intacto, latência app↔DB aceitável, ERP estável.

---

## RISK REGISTER (consolidado)

> ⚠️ Derivado dos failure modes da FUNDAÇÃO + análise do incidente. **NÃO** há veredictos adversariais registrados (PARTES vazias) — ver GATE-ADV. Severidade: 🔴 critical · 🟠 high · 🟡 medium.

| # | Risco | Sev | Mitigação (já no plano) |
|---|---|---|---|
| R-1 | **Reenchimento de C5 `_history`** (0→72MB/30min) volta a estourar antes do teto entrar. | 🔴 | P0.2 teto mecânico + cron; monitor de **taxa de crescimento por tabela** (R-MON) alerta em rampa, não só em tamanho. |
| R-2 | **Grant-torto silencioso** — Hostinger revoga `INSERT/UPDATE` e ninguém vê até o cliente quebrar. | 🔴 | P0.5 health-check que **testa o grant** (write-probe) + alerta `mcp_alertas` ANTES de 75%/90%. Foi exatamente a causa de não ter (a)+(b). |
| R-3 | **Vazamento Tier 0 na migração** C3/C5/C6 (Grupo B perde `business_id`/scope ao mudar de host). | 🔴 | P1.2/P1.3 preservam `business_id`+FK+global scope; check `multi_tenant_isolation` antes/depois é gate de cutover. |
| R-4 | **Quebra da hash-chain** do C6 ao migrar (backfill dispara trigger 0084 / cadeia inconsistente). | 🔴 | P1.3 **export append-only verificado**, nunca backfill; verificação hash-chain ponta-a-ponta pós-migração. |
| R-5 | **CT 100 cai (casa)** e derruba ops-DB → se app não degradar, ERP cai junto. | 🟠 | P1.5 degradação graciosa (circuit-breaker + buffer/fila); **C1 nunca no CT 100** (P2 → DBaaS). |
| R-6 | **Perda de dado na poda** C4/C5 (truncar algo que não era podável). | 🟠 | Classe é contrato; só C4/C5/C7 podam; C1/C6 nunca deletam; `COUNT`+dump antes de DROP. |
| R-7 | **`mcp_audit_log` particionado perde verificabilidade** legal/LGPD. | 🟠 | P0.4 só particiona (não deleta); arquivo frio WORM preserva cadeia verificável (0294). |
| R-8 | **Loop do ADS logger** reentra no erro 1142 e amplifica carga quando o grant está torto. | 🟠 | P0.6 fallback + backoff; logger não tenta escrever quando write-probe falha. |
| R-9 | **Big-bang de migração** introduz inconsistência difícil de reverter. | 🟡 | Cutover **por classe**, conexão antiga viva até validar; rollback por classe. |
| R-10 | **Latência Remote MySQL** (Hostinger↔DBaaS em P2) degrada UX do ERP. | 🟡 | P2 teste de latência antes do cutover; réplica + janela de validação; rollback de conexão. |
| R-11 | **`reviewed_at` / placement-classe não atribuído** a tabela nova → bloat volta a furar. | 🟡 | Gate de migração: tabela nova nasce com classe ou reprova (ver INVARIANTE-CLASSE). |
| R-CASA | **CT 100 não é datacenter** (energia/link). | 🟠 | Aceito como risco residual **só para ops/governança** (C2/C3/C5/C6), cuja indisponibilidade degrada governança/IA mas **não** o ERP. C1 vai pra DBaaS. |
| R-ADV | **Rodada adversarial PARCIAL** (2026-06-21) — C2/C5/C6 + completude cobertos; C1/C3/C4/C7/P0/P1/P2/transversais pendentes (overload da API). Furos podem ter passado nas partes não-cobertas. | 🟠 | GATE-ADV: completar adversários das partes pendentes antes de `aceito` — ver §RODADA ADVERSARIAL. |

## REQUIRED FIXES aplicados no design

> A FUNDAÇÃO não trouxe `required_fixes` de adversários (PARTES vazias). Os fixes abaixo são os **derivados obrigatórios** do incidente + das restrições Tier 0, já embutidos no roadmap — não são opcionais:

- **F-1 (incorporado em P0.5/INVARIANTE-MON):** monitor mede **(a) tamanho total vs threshold**, **(b) taxa de crescimento por tabela**, **(c) `_bkp_*` vivos >30d**, **(d) growth de C4/C5 sem TTL**, **(e) write-probe do grant** — a ausência de (a)+(b) **causou** o incidente.
- **F-2 (P0.2):** `_history` **não pode** ser append-only ilimitado — teto mecânico AUTOMÁTICO, não truncate manual pós-incidente.
- **F-3 (P1.3):** migração de C6 por export append-only verificado, hash-chain revalidada — **nunca** backfill.
- **F-4 (P1.5):** app no Hostinger **degrada gracioso** se ops-DB (CT 100) cair — invariante crítica, não "nice to have".
- **F-5 (P0.6):** ADS logger com fallback/backoff — não reentra no 1142.
- **F-6 (Parte 1):** toda tabela tem **classe atribuída**; gate de migração reprova tabela sem classe.

## INVARIANTES À PROVA DE FALHAS (não negociáveis)

- **INVARIANTE-TIER0:** `business_id` + global scope preservados em **toda** migração de host (0093/0280). Check `multi_tenant_isolation` é gate de cutover. Relaxar = aborta.
- **INVARIANTE-ZEROLOSS:** nenhuma poda toca C1/C6 deletando; linhas origem == destino + checksum em toda migração; DROP só após dump.
- **INVARIANTE-AUDIT:** C6 append-only + hash-chain (0084/0294) íntegra antes, durante e depois; nunca deletar, só particionar + arquivar frio.
- **INVARIANTE-REVERSIBILIDADE:** cutover por classe, conexão antiga viva até validar, rollback por classe documentado.
- **INVARIANTE-MON (avisar ANTES de estourar):** `jana:health-check` (padrão duro 0270 D5) alarma em **4.5 GB/75%** e **5.4 GB/90%**, detecta rampa de crescimento (tipo `_history` 72MB/30min), `_bkp_*` vivos >30d, e roda **write-probe** do grant — alerta via `mcp_alertas` antes do grant-torto.
- **INVARIANTE-ERP-FIRST:** o ERP no Hostinger **nunca quebra** se o ops-DB (CT 100) cair. C1 nunca depende do CT 100. Indisponibilidade de ops degrada governança/IA, não o cliente.
- **INVARIANTE-CLASSE:** toda tabela nova nasce com classe C1–C7 atribuída; gate de migração reprova tabela sem classe e sem placement declarado.

## Consequências

**Positivas:** o ruído operacional sem teto deixa de poder derrubar o sinal de negócio; cota monitorada com antecedência; crescimento por-cliente vira o **único** limitante real de C1 (que é o sinal saudável); CT 100 absorve ops sem custo novo; C1 fica num storage à altura da sua criticidade.

**Negativas / trade-offs honestos:** multi-DB aumenta complexidade operacional (2–3 conexões, cutovers, circuit-breakers); CT 100 como host de ops adiciona dependência de casa (mitigada por degradação graciosa + por C1 nunca morar lá); P2 (DBaaS) adiciona custo recorrente e latência Remote MySQL a validar; particionamento de C6 exige cuidado com triggers/hash-chain.

**O que precisa de APROVAÇÃO Wagner (🔒):** GATE-ADV (rodar adversários antes de aceitar), P0.4 (particionar audit), P1.1/P1.2/P1.3/P1.5 (2ª conexão + migração de ops/audit + degradação graciosa), P2.1 (mover C1 off-shared — decisão de plataforma + custo). **O que é mecânico/seguro (✅):** P0.1 (DROP C7), P0.2 (teto C5), P0.3 (pruning C4), P0.5 (monitor), P0.6 (fix ADS logger), P0.7 (fix backup deploy), P1.4 (logs→observabilidade).

---

## RODADA ADVERSARIAL (2026-06-21) — fecha PARCIALMENTE o GATE-ADV

> **Método:** workflow multi-agente adversarial (adversário hostil por parte × lente diversa → cético-do-cético refuta cada achado → síntese → crítico de completude). 3 runs.
>
> ⚠️ **Cobertura limitada por força maior:** a API da Anthropic entrou em **overload sustentado server-side** (`"Server is temporarily limiting requests — not your usage limit"`) e derrubou **44 de 45 adversários em CADA run paralela**; a run sequencial (1-por-vez) travou no 1º. Os sobreviventes (1 por run) + o crítico de completude + a síntese ainda assim entregaram achados **verificados-no-código** de alto valor. **Cobertura efetiva:** classes **C2/C5/C6** sob a lente CORRETUDE-DE-MIGRAÇÃO + crítico de completude (superfícies transversais). **PENDENTE — não por desenho, por overload:** adversários dedicados de **C1/C3/C4/C7/P0/P1/P2 + placement/invariantes/monitor** sob suas lentes. Rodar quando a API normalizar (scripts versionados em `.claude/workflows/scripts/adr-0296-adversarial-*.js`).

### Veredicto: **prova-de-falhas-COM-FIXES**

Direção sólida e honesta (o ADR já admitia PARTES vazias). **MAS ainda não é à prova de falhas como escrito**, por dois temas-raiz:

1. As invariantes de migração (ZEROLOSS/REVERSIBILIDADE/AUDIT) são **cegas à realidade do schema** — FK cross-host, triggers append-only fora do C6, caches que nascem linhas após o snapshot.
2. O plano resolve isolamento **lógico** (Tier 0) de forma exaustiva, mas é **silencioso sobre rede e confidencialidade do fio** — mover PII (C1) pra Remote MySQL pode trocar um problema de *capacidade* por um **MUITO pior de exposição (vazamento LGPD reportável)**.

**Nenhum achado bloqueia P0** (parar o sangramento). Vários bloqueiam o **cutover P1/P2** e a promoção `proposed→aceito`.

### Achados confirmados (verificados no código)

| # | Sev | Parte | Achado | Bloqueia |
|---|---|---|---|---|
| **A-1** | 🟠 high | C2+C6 | **ZEROLOSS é cega a constraints.** `mcp_tokens`(FK user_id→users CASCADE), `mcp_quotas`(FK user_id→users), `mcp_audit_log`(3 FKs: users/business/mcp_tokens) vão pro CT 100, mas `users`/`business` ficam no Hostinger e **não estão em nenhuma das 7 classes**. MariaDB não faz FK cross-server → no cutover o CASCADE **morre calado**. ZEROLOSS valida só COUNT+checksum. *Mitigado:* a brecha de auth já é coberta por `McpAuthMiddleware`(users::find→401) + soft-delete. Evidência: `2026_04_29_100003:40`, `_100004:37`, `_100005:68-70`. | P1.2/P1.3 |
| **A-2** | 🟠 high | C2 | **Append-only fora do C6.** `mcp_task_events` (rotulado C2 "podável") tem triggers `SIGNAL 45000` BEFORE UPDATE/DELETE idênticos ao C6 (`2026_06_15_160000`, "espelho 1:1 do mcp_audit_log"). O regime export-verificado é reservado só ao C6 → migrar/reverter viola a imutabilidade. | P1.2 (tabela) |
| **A-3** | 🟡 med | C2+C5 | **COUNT==COUNT mente p/ cache git-synced.** `mcp_tasks`/C5 nascem linhas via webhook GitHub **depois** do snapshot → split-brain transitório (US some pro time até o próximo push). Fix: drenar webhook + **reconstruir via reparse do git** no destino (não copiar linhas) — `mcp:tasks:sync` já é idempotente. | — |
| **A-4** | 🟡 low | C2+C6 | **Grafo de FK entre classes não declarado.** `mcp_audit_log.mcp_token_id→mcp_tokens` força ordem (P1.2 antes de P1.3) que o plano não especifica; exige co-residência C2+C6 no mesmo ops-DB. | — |
| **A-5** | 🟡 low | C5 | **Premissa "git == _history, descartável" é FALSA.** `IndexarMemoryGitParaDb` aplica `redactarPii()`(l.584-597) + `sanitizarUtf8()` `iconv //IGNORE`(l.603-611) **ANTES** de gravar `content_md` → `_history` guarda a **variante SERVIDA (redactada)**, não o original do git. Regenerável (git + código de redação) e o autoritativo do que a IA serviu é `mcp_audit_log`(C6). | aceitação (trivial) |
| **A-6** | 🟡 low | C5 | **Docs git-deleted.** `mcp:sync-memory` faz **soft-delete** de docs sumidos do git (l.84) → a tabela-mãe soft-deletada é a única materialização. "Re-pull regenera C5" (l.122) é falso p/ esses; ring buffer P0.2 não pode tocar a tabela-mãe. | aceitação (trivial) |

### Superfícies NÃO cobertas (crítico de completude) — o que o ADR ainda não resolve

1. **Segurança de rede do Remote MySQL** — IP-allowlist **impossível** em shared de IP rotativo/compartilhado; porta 3306 alcançável.
2. **TLS em trânsito** (`require_secure_transport`) Hostinger↔DBaaS/CT100 — PII (C1) e a hash-chain (C6) em claro no fio.
3. **Encryption-at-rest** — C1/C3/C6 com PII saem pro disco do CT 100 (**casa**, fisicamente acessível) e do DBaaS.
4. **Backup/restore DRILL nunca testado** + RPO/RTO não declarados (restaurar C6 e **re-verificar a hash-chain**; restaurar C1 e revalidar `business_id` scope).
5. **Quem PAGA e quem OPERA** o ops-DB CT100 + o DBaaS (patch/upgrade/rotação/monitoria 24/7 de banco agora crítico).
6. **Plano de SAÍDA se a Hostinger mudar regra de novo** — o incidente-mãe FOI isso; não há gatilho "se Hostinger mudar X, migra o app pra Y".
7. **Egress / latência por-query WAN** — N+1 sobre `fin_titulos`(158MB)/`transactions`(110MB) cruzando WAN degrada cada page-load.
8. **Região LGPD + DPA/sub-processador** — DBaaS e CT100 viram **novos sub-processadores** de dado pessoal (exigem DPA, base legal, registro de operações).
9. **Clientes legacy** (perfex/wr2/crm/Firebird) no mesmo grant `u906587222` — tabelas não-UltimatePOS **não-mapeadas** furam o INVARIANTE-CLASSE na prática.
10. **Rotação de credenciais pós-migração** — novas conexões = novas credenciais no `.env` do shared (com histórico de vazamento), agora dando acesso a **dois** bancos remotos.
11. **Drift de escrita-dupla** durante o cutover (ex.: C3 telemetria de alta frequência) — "COUNT==checksum" só vale p/ dado estático.
12. **O monitor que monitora a si mesmo** — o write-probe (P0.5) escreve no **mesmo banco** que pode estar read-only; `mcp_alertas` idem. Quem monitora o monitor e por onde sai o alerta quando o DB está read-only?

> **Pergunta mais perigosa que ninguém fez:** quando C1 (PII) sair pro DBaaS via "Remote MySQL", **quem** pode abrir um socket TCP na porta do banco, **por qual caminho criptografado**? O ADR resolve o isolamento **lógico** (Tier 0) exaustivamente, mas é silencioso sobre isolamento de **rede** e confidencialidade do **fio** — sem IP-allowlist (impossível no shared de IP rotativo), sem TLS obrigatório e sem encryption-at-rest, a "correção estrutural" troca um problema de capacidade por um de **superfície de exposição** (vazamento LGPD reportável). É exatamente a classe de risco que o incidente-mãe deveria ter ensinado a antecipar.

### Invariantes emendadas / novos fixes obrigatórios

- **INVARIANTE-ZEROLOSS (emendada):** validação **não** é só `COUNT origem==destino` + checksum. Adicionar (a) **FK-INVENTORY** + decisão explícita por FK cross-host (substituída por guard app-layer documentado) e (b) p/ classes-cache (`mcp_tasks`/C2, `mcp_memory_documents`/C5), trocar COUNT por **"re-parse do git bate com o DB destino"**.
- **INVARIANTE-AUDIT (generalizada):** "toda tabela append-only **com triggers de imutabilidade** (`mcp_audit_log` **E** `mcp_task_events` e qualquer futura) segue o regime export-verificado — criação dos triggers no destino **SOMENTE APÓS** validação, nunca antes." Não nomear só C6.
- **INVARIANTE-CLASSE (estendida):** o gate de migração **detecta triggers `BEFORE UPDATE/DELETE`** e força o regime export-verificado independente da classe nominal.
- **INVARIANTE-REVERSIBILIDADE (apertada):** "cutover por classe **RESPEITANDO o grafo de FK declarado**" + co-residência **"C2 e C6 co-residem no ops-DB CT 100"**. Ordem: folha primeiro (`mcp_tokens`/P1.2 antes da FK de audit/P1.3).
- **Novo check `referential_orphans`** no `jana:health-check`: COUNT de `mcp_tokens`/`mcp_quotas`/`mcp_audit_log` com `user_id`/`business_id` sem match nos `users`/`business` do Hostinger — ANTES e DEPOIS do cutover (sentinela, não gate duro).
- **F-C5-1:** corrigir a premissa C5 (linhas ~58/77/97/113): "redundante e descartável" → "**CACHE derivado-do-git; `_history` guarda a variante redactada+sanitizada SERVIDA, REGENERÁVEL de git + `redactarPii()`/`sanitizarUtf8()`; autoritativo do que a IA serviu = `mcp_audit_log`(C6)**".
- **F-C5-2:** ring buffer/poda P0.2 restrito a `mcp_memory_documents_history`; **PROIBIDO** `forceDelete` das linhas **soft-deletadas** da tabela-mãe sem prova de re-pull do slug.
- **F-C5-3:** corrigir o rollback (l.122): "re-pull regenera C5" só vale p/ docs **vivos** no git.
- **F-C5-4:** monitor de C5 mede **cobertura de proveniência** (linhas trashed contadas), não só tamanho.
- **Honestidade (linhas ~95/130/163):** "FK intactos / business_id+FK+scope intactos através do split" → a verdade: **`business_id` COLUNA + global scope app-layer (0093) sobrevivem; a FK `business_id→business` NÃO** (vira guard app-layer + `referential_orphans`).
- **NÃO** adicionar object-storage/arquivo-frio caro p/ C5 (desproporcional sem mandato LGPD documentado).

### Emendas concretas ao plano

- **P1.2** → desmembrar em **P1.2a** (C2 operacional puro: `mcp_work_leases`/`mcp_cycles`/`mcp_views`… mecânico) e **P1.2b** (`mcp_task_events`, sob regime export-verificado de P1.3). `mcp_tokens` migra antes de tudo que tenha FK pra ele.
- **P1.2/P1.3** → prefixar com **FK-INVENTORY** (`grep '->foreign(' Modules/Jana/Database/Migrations`); o output vira tabela de decisão por FK. Sem isso, não passa o gate.
- **P1.2 (mcp_tasks) + C5-cache** → trocar "migrar linhas" por "drenar webhook → reparse do git HEAD no destino → reabrir após push de teste".
- **Mapa tabela→classe (l.81-88)** → flag **"append-only (triggers)"** p/ `mcp_task_events` e `mcp_audit_log`; corrigir a célula "Podável?" de `mcp_task_events` (imortal, não podável).
- **Risk Register** → adicionar **R-FK**, **R-CACHE-RACE**, **R-REDE** (Remote MySQL sem allowlist/TLS/at-rest), **R-LGPD-SUBPROC** (DBaaS/CT100 = sub-processadores sem DPA), **R-EXIT** (sem plano de saída), **R-LEGACY** (tabelas legacy não-classificadas), **R-MONITOR-SELF**.

### Perguntas que dependem do Wagner

1. **`business_id` FK vs coluna+scope:** aceita **perder a FK DB-level** `business_id→business` no split, mantendo só a coluna + global scope app-layer (0093) + `referential_orphans`?
2. **Co-residência C2+C6** no ops-DB CT 100 como **invariante dura**?
3. **Janela de cutover dos caches:** OK **pausar o webhook do GitHub + 503 no `/api/mcp/sync-memory`** durante o snapshot?
4. **Mandato LGPD/forense:** existe requisito de **reproduzir a versão exata que a IA serviu** numa data? Se SIM, `mcp_audit_log` cobre ou precisa guardar o `content_md` servido. Se NÃO, C5 `_history` poda livre.
5. **Escopo do GATE-ADV:** promover com adversários só em C2/C5/C6 + completude, OU exigir a rodada completa (P0, C1/P2 rede+LGPD, C4) antes do aceite?
6. **Rede do C1 (a mais perigosa):** mover C1/PII pra Remote MySQL aceita o risco de exposição (allowlist impossível no shared), ou repensar (túnel/VPN, ou manter C1 no Hostinger e mover só ops)?

### Status do GATE-ADV

**PARCIAL.** Coberto: **C2/C5/C6 (lente CORRETUDE-DE-MIGRAÇÃO)** + **crítico de completude** (12 superfícies). **Pendente** p/ `proposed→aceito`: adversários de **C1, C3, C4, C7, P0, P1, P2, placement, invariantes, monitor** (bloqueados pelo overload — rodar quando normalizar). **R-ADV permanece 🟠.** **F-C5-1/2/3 + correções de honestidade (l.95/130/163)** são pré-condição trivial de aceitação.
