# Post-mortem v4 go-live — 14 erros + 7 anti-patterns (2026-05-16/17)

> **Por que existe:** A Governance v4 entrou em vigor com Wave 25 LIVE em 2026-05-16 ([ADR 0163](../decisions/0163-governance-v4-metas-alcancadas-ondas-19-28.md)). Nas ~36h seguintes (2026-05-17), foram catalogados **14 erros** distribuídos em **7 anti-patterns recorrentes**. Doc unifica origem, custo e defesa atual/proposta — pra time MCP (Felipe/Maiara/Eliana/Luiz) não repetir.
>
> **Fontes:** `git log --since=2026-05-13`, `memory/handoffs/2026-05-1[6-7]*.md`, ADRs 0156/0159/0163/0166/0167, skill `smoke-prod-evidence`, rules path-scoped `.claude/rules/*.md`.

## TL;DR

| Métrica | Valor |
|---|---|
| Janela do post-mortem | 2026-05-16 ~21:00 → 2026-05-17 ~22:00 (~36h pós-v4 LIVE) |
| PRs corretivos abertos | 14 (`#984` recall + `#999-#1031`) |
| ADRs errata novas | 2 ([0166](../decisions/0166-errata-0162-otel-require-dev-hostinger.md) OTel require-dev · [0167](../decisions/0167-errata-0130-indice-handoff-historico-longo.md) handoff index longo) |
| Skills novas Tier B | 1 (`smoke-prod-evidence` — anti-cascata `/public/`) |
| Rules path-scoped expandidas | 1 (`.claude/rules/modules.md` — SCOPE-first) |
| Cost mais alto | 3 PRs cascata `/public/` (#1024 → #1026 → #1028) — 3 deploys consecutivos por declaração precoce |
| Tipo dominante | Drift entre canon escrito e prática real (5/14 erros) |

## Inventário cronológico — 14 erros

| # | Erro | PR(s) | Anti-pattern | Defesa registrada |
|---|---|---|---|---|
| 1 | **PowerShell 5.1 `Set-Content -Encoding utf8` grava UTF-8 COM BOM** (`EF BB BF` prefix) → PHP crasha `Namespace declaration statement has to be the very first statement` | #984 | A (encoding silencioso) | `memory/proibicoes.md` §Ambiente |
| 2 | **Conflito merge `SrsMemoryReader.php` foi pra prod** (parse error `<<<<<<<`) | #1000 | A (encoding silencioso) | — só hotfix |
| 3 | **Git markers em PHPDoc cross-projeto** | #1001 | A (encoding silencioso) | — só housekeeping |
| 4 | **OTel SDK em `require`** (não `require-dev`) → quebrou deploy Hostinger (sem `ext-opentelemetry`) | #1018, #1025 | C (require vs require-dev errado) | [ADR 0166](../decisions/0166-errata-0162-otel-require-dev-hostinger.md) errata + comentário deploy.yml |
| 5 | **CI vermelho pré-existente** (linter ADR + storage dirs + .env fallback) | #1020 | F (drift canon vs prática — falhas mascaradas pré-v4) | — fix técnico |
| 6 | **Pest vermelho pré-existente** (Repair + NfeBrasil + ComVis) | #1022 | F (drift canon vs prática) | — fix técnico |
| 7 | **`IsWagner` middleware não aceita role `superadmin#{biz}`** (Spatie suffix multi-tenant) | #1006 | D (middleware multi-tenant esquecido) | proibicoes.md §FSM já citava regra |
| 8 | **`AdminSidebarMenu` middleware ausente em `/admin/*`** → sidebar vazia | #1008 | D (middleware multi-tenant esquecido) | — fix técnico |
| 9 | **`ADMIN_BYPASS_TAILSCALE` flag não suportada real** | #1005 | F (config vs código) | — hotfix |
| 10 | **`KpiGrid` sem `cols=5`** → Governance v4 layout quebrado | #1007 | E (componente shared sem cap natural) | — fix técnico |
| 11 | **Cascata `/public/` URL exposure** — 3 PRs declarando "funcionando" sem `curl -sv` real em prod | #1024 → #1026 → #1028 | B (declaração precoce sem evidência) | Skill [`smoke-prod-evidence`](../../.claude/skills/smoke-prod-evidence/SKILL.md) Tier B (#1029) |
| 12 | **Índice `08-handoff.md` truncar 5** (ADR 0130 §2) vs prática histórico longo | — (só ADR) | F (drift canon vs prática) | [ADR 0167](../decisions/0167-errata-0130-indice-handoff-historico-longo.md) errata |
| 13 | **Checklist pós-merge disperso em 8 fontes** | #1027 | F (canon não-unificado) | [checklist-pos-merge.md](checklist-pos-merge.md) canon |
| 14 | **SCOPE.md ignorado em pesquisa de módulo** (esta sessão) → 4 Glob + 2 Grep antes de ler SCOPE | #1031 | G (protocolo pesquisa incompleto) | [`.claude/rules/modules.md`](../../.claude/rules/modules.md) + fix Governance/SCOPE.md |

## 7 anti-patterns meta (com defesa proposta)

### A. Encoding/syntax silencioso vai pra prod

**Manifestações:** UTF-8 BOM em PHP (#984), git merge markers em arquivos PHP (#1000 + #1001), routes string legacy `'Controller@method'` (#843 pré-v4).

**Custo:** prod CRASHA (#984 quebrou `oimpresso.com` inteiro até hotfix). Detectado tarde — usuário/cliente vê 500 antes do dev.

**Defesa hoje:** proibicoes.md §Ambiente cataloga regra ("usar `WriteAllText` UTF8 sem BOM" / "validar com `file <path>`"), MAS sem hook automático.

**Defesa proposta:** 2 hooks novos pre-commit:
- `block-bom-encoding.ps1` — detecta byte sequence `0xEF 0xBB 0xBF` no início de `*.php` `*.js` `*.ts` `*.tsx` `*.css`. Bloqueia Write/Edit que reintroduza BOM.
- `block-merge-markers.ps1` — detecta `<<<<<<<`, `=======`, `>>>>>>>` em qualquer Write/Edit. Bloqueia commit acidental de conflito não-resolvido.

### B. Declarar "funcionando" sem evidência inequívoca

**Manifestações:** cascata 3 PRs `/public/` (#1024 → #1026 → #1028) — cada PR declarou "funcionando" baseado em consequência observável compatível (status code ≠ 500, sem 404), não em `curl -sv` real do hop específico.

**Custo:** 3 deploys consecutivos pra mesmo bug raiz, dia perdido.

**Defesa atual:** Skill [`smoke-prod-evidence`](../../.claude/skills/smoke-prod-evidence/SKILL.md) Tier B criada #1029 — exige `curl -sv` literal de cada hop antes de declarar OK. Bane declarações guarda-chuva "✅ funcionando" sem evidência citada.

**Trigger:** sessão começa, PR de redirect/middleware/routing/cache merger; Wagner pergunta "tá funcionando?".

### C. `composer require` vs `require-dev` errado por runtime

**Manifestações:**
- OTel em `require` (#1025) — quebrou Hostinger porque `ext-opentelemetry` não existe lá
- Faker em `require-dev` quebrou prod (incidente 2026-04-25 — `--no-dev` removeu Faker que era usado em queries demo)

**Custo:** deploy bloqueado horas até diagnóstico + errata ADR.

**Defesa atual:** [ADR 0166](../decisions/0166-errata-0162-otel-require-dev-hostinger.md) catalogou matriz Hostinger vs CT 100 vs CI vs Local + comentário em `deploy.yml` explicando `--ignore-platform-req=ext-opentelemetry`.

**Defesa proposta:** entrada nova em proibicoes.md §Ambiente — "antes de adicionar pacote em `composer.json` require, checar matriz `Hostinger | CT 100 | CI | Local` — se Hostinger não suporta, vai pra `require-dev` com gate no deploy.yml".

### D. Middleware/role multi-tenant esquecido

**Manifestações:** IsWagner sem suffix `#{biz}` (#1006), AdminSidebarMenu ausente em `/admin/*` (#1008).

**Custo:** acesso negado falsamente ou UI quebrada (sidebar vazia).

**Defesa atual:** proibicoes.md §FSM já cataloga *"Roles Spatie sem suffix `#{biz}` em UltimatePOS — tabela `roles.business_id` é NOT NULL com FK"*. Skill `multi-tenant-patterns` Tier A always-on.

**Defesa proposta:** Pest test cross-route `admin/*` que asserte stack middleware completa (AdminSidebarMenu + SetSessionData + auth + tailscale-only). Backlog — não prioritário.

### E. Componente shared sem cap natural

**Manifestações:** `KpiGrid` sem suporte `cols=5` (#1007) — Governance v4 precisava de 5 colunas, layout quebrou.

**Custo:** menor (hotfix simples), mas indica componente shared sem flex inicial.

**Defesa atual:** nenhuma específica.

**Defesa proposta:** quando criar componente shared (`resources/js/Components/shared/*.tsx`), prop variant numérica deve aceitar `1-12` (Tailwind grid spectrum), não enumerar `1-4`. Backlog rule path-scoped.

### F. Drift entre doc canônico e prática real

**Manifestações:**
- ADR 0130 §2 "truncar 5" vs prática histórico longo (#1027 / ADR 0167)
- SCOPE.md Governance com `pertence_a: Modules/Copiloto/...` 11 dias após rename Copiloto→Jana (este PR #1031)
- CI vermelho pré-existente mascarado pré-v4 (#1020 + #1022)
- `ADMIN_BYPASS_TAILSCALE` documentado mas não suportado real (#1005)

**Custo:** ofusca debugging — dev lê doc, doc mente, fix é trial-and-error.

**Defesa atual:** errata como ADR nova (append-only). Workflow lento mas correto.

**Defesa proposta:** revisão trimestral de canon high-rotation (SCOPE.md de cada módulo, proibicoes.md, how-trabalhar.md). Backlog — sem urgência.

### G. Pesquisa de módulo sem ler `SCOPE.md` primeiro

**Manifestações:** esta sessão (2026-05-17) — Wagner pediu "reunir rotas governança", eu fiz 4 Glob + 2 Grep antes de Read `Modules/Governance/SCOPE.md`. Wagner corrigiu.

**Custo:** tokens queimados reconstruindo info que tava em 1 arquivo.

**Defesa atual:** [`.claude/rules/modules.md`](../../.claude/rules/modules.md) atualizada — `SCOPE.md` virou FONTE PRIMÁRIA no PRE-FLIGHT do workflow 3 fases. Path-scoped: carrega só quando Claude toca `Modules/**/*.php`.

**Custo zero pra outras áreas** — rule não polui contexto fora de Modules.

## 3 hooks novos propostos (PR separado pós este post-mortem)

| Hook | Defende | Matcher | Onde mora |
|---|---|---|---|
| `block-bom-encoding.ps1` | Anti-pattern A — UTF-8 BOM em arquivos PHP/JS/TS/CSS | `Write\|Edit\|MultiEdit` em `*.php` `*.js` `*.ts` `*.tsx` `*.css` | `.claude/hooks/` registrado em `.claude/settings.json` |
| `block-merge-markers.ps1` | Anti-pattern A — git markers `<<<<<<<` / `=======` / `>>>>>>>` em arquivos commitáveis | `Write\|Edit\|MultiEdit` em qualquer arquivo de código (≠ `.md`) | idem |
| `block-routes-string-legacy.ps1` | Anti-pattern A — strings `'Controller@method'` em routes que quebram `route:cache` | `Write\|Edit\|MultiEdit` em `routes/*.php`, `Modules/*/Routes/*.php`, `Modules/*/Http/routes.php` | idem |

**Padrão:** segue arquitetura dos 11 hooks existentes (`block-*.ps1` em PowerShell 5.1 compatível, lê JSON via stdin, emite `decision='deny'` JSON ou `exit 0`). Modo `warn` default 4 semanas (calibração), depois `strict`.

**Override emergencial Wagner Tier 0:** env var `OIMPRESSO_<HOOK>_OVERRIDE=1` (padrão `block-memory-drift.ps1`).

## Refs canon

- [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (§3 Charter > Spec + append-only)
- [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) + [ADR 0167](../decisions/0167-errata-0130-indice-handoff-historico-longo.md) — Handoff append-only + errata índice longo
- [ADR 0162](../decisions/0162-otel-collector-prod-observability.md) + [ADR 0166](../decisions/0166-errata-0162-otel-require-dev-hostinger.md) — OTel collector + errata require-dev
- [ADR 0160](../decisions/0160-governance-v4-scoped-scorecards-buckets.md) + [ADR 0163](../decisions/0163-governance-v4-metas-alcancadas-ondas-19-28.md) — v4 bucket-aware + metas atingidas
- [checklist-pos-merge.md](checklist-pos-merge.md) — checklist pós-merge canônico unificado
- [`.claude/rules/modules.md`](../../.claude/rules/modules.md) — pre-flight SCOPE-first
- [`.claude/rules/routes.md`](../../.claude/rules/routes.md) — FQCN obrigatório em routes
- [`.claude/skills/smoke-prod-evidence/SKILL.md`](../../.claude/skills/smoke-prod-evidence/SKILL.md) — Tier B anti-cascata
- [memory/proibicoes.md](../proibicoes.md) §Ambiente — UTF-8 sem BOM + Hostinger ≠ CT 100
- Handoff [2026-05-17 07:00 Governance v4 final](../handoffs/2026-05-17-0700-governance-v4-final-ondas-19-28.md)

**Origem:** sessão `frosty-greider-83ab2f` 2026-05-17 — Wagner pediu revisão consolidada dos erros pós-v4 + regras a unificar. Doc nasce pra time MCP entrante (Felipe/Maiara/Eliana/Luiz) não repetir os 14 erros.
