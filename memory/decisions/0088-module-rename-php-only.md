---
slug: 0088-module-rename-php-only
number: 0088
title: "Module rename PHP-only — fachada legacy mantida durante transição"
type: adr
status: superseded
superseded_by_section:
  db: "0092-tabela-rename-copiloto-para-jana"
authority: canonical
lifecycle: substituido
decided_by: [W]
decided_at: 2026-05-06
module: null
quarter: 2026-Q2
tags: [governance, refactor, rename, module-charter, migration-pattern, php-only]
supersedes: []
supersedes_partially: []
superseded_by: ['0092-tabela-rename-copiloto-para-jana']
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0087-drift-resolution-sem-mover-url
pii: false
review_triggers:
  - "Wagner abre `/copiloto/chat` em prod e nome `Jana` aparece em algum lugar inconsistente — UX requer migration ASAP"
  - "Composer dump-autoload pós-deploy quebrar ou log channel `copiloto-ai` parar de gravar — bug latente"
  - "ADR 0088 vigente >6 meses sem PR-3 movendo URLs/permissions — sinal de débito permanente, abrir ADR explicitando 'fachada legacy é definitiva' OU agendar PR-3 com prazo"
---

# ADR 0088 — Module rename PHP-only — fachada legacy mantida durante transição

## Contexto

MODULE-DRIFT-MIGRATION-PLAN v1.1.0 §4 previu renames de módulo na Fase 3.7:

| De | Pra | URLs |
|---|---|---|
| `Modules/Copiloto/` | `Modules/Jana/` | `/copiloto/*` → `/jana/*` (301) |
| `Modules/PontoWr2/` | `Modules/Ponto/` | `/ponto-wr2/*` → `/ponto/*` (301) |
| `Modules/MemCofre/` | `Modules/SRS/` | `/memcofre/*` → `/srs/*` (301) |

Wagner contextualizou (2026-05-06): *"a Copiloto realmente estava com muita funcoes, por isso passou para Jana"* — após PR-1 (drift resolution, ADR 0087) extrair MemoriaController, FontesController e Mcp/* do Copiloto, o "resíduo" virou chat IA puro = Jana. Rename é semanticamente conseqüência da limpeza.

Análise do blast radius de rename completo (URLs + permissions + Pages + tudo):

- **5993 clientes ROTA LIVRE** com permissions Spatie `copiloto.*` no DB. Backfill `copiloto.* → jana.*` em produção é migration de risco.
- **30+ `Inertia::render('Copiloto/...')`** em ~controllers (KB/Jana/etc). Mover Pages dir = git mv ~30 arquivos TSX + update todos os render calls.
- **Watchers Claude Code** de cada dev (Wagner/Felipe/Maíra/Luiz/Eliana) apontam pra `/api/cc/ingest`. Mover URL = re-config 5 watchers + janela de inconsistência.
- **Webhook GitHub** aponta pra `/api/mcp/sync-memory`. Mover URL = Wagner update settings GitHub + janela de drop pushs.
- **Bookmarks de Wagner** + links em emails internos.
- **Log channel `copiloto-ai`** com 6 meses de logs históricos. Mover = grep retroativo precisa olhar 2 channels.
- **Config keys `copiloto.*`** em arquivos PHP. `.env Hostinger` com `OPENAI_API_KEY` + `COPILOTO_*` envs.
- **30 `route('copiloto.*.')`** em Pages React.
- **Tabelas DB `copiloto_*`, `ponto_*`, `docs_*`** já legacy (plano §4 explicita não renomear).

Total: 7 dimensões de mudança superficial além do PHP. Cada uma com 1-3h trabalho + janela de risco.

Custo de fazer tudo num PR: ~24h trabalho + 7 janelas humanas de coordenação. Custo de não fazer (manter legacy): zero usuário final notou — `Jana` é nome interno enquanto fachada usa `copiloto`.

## Decisão

**Module rename = PHP-only. Fachada legacy mantida.**

### O que renomeia (PHP code)

1. **Pasta**: `git mv Modules/Copiloto Modules/Jana` (idem PontoWr2→Ponto, MemCofre→SRS)
2. **Namespace PSR-4**: `Modules\Copiloto\` → `Modules\Jana\` em todos os arquivos PHP/JSON do módulo
3. **ServiceProvider class**: `CopilotoServiceProvider` → `JanaServiceProvider`
4. **module.json**: `name`, `alias`, `providers`, descrição
5. **composer.json**: `name`, `providers` (FQCN), `autoload.psr-4`
6. **SCOPE.md**: campo `module:`, texto, histórico

### O que NÃO renomeia (fachada legacy)

| Item | Antes | Mantido legacy |
|---|---|---|
| URLs | `/copiloto/*` | `/copiloto/*` ✓ |
| Permissions Spatie | `copiloto.*` | `copiloto.*` ✓ |
| Config keys + env vars | `copiloto.*` / `COPILOTO_*` | `copiloto.*` / `COPILOTO_*` ✓ |
| Log channel | `copiloto-ai` | `copiloto-ai` ✓ |
| Pages React dir | `Pages/Copiloto/` | `Pages/Copiloto/` ✓ |
| Lang namespace | `copiloto::` | `copiloto::` ✓ |
| Tabelas DB | `copiloto_*` | `copiloto_*` ✓ |
| Route names | `copiloto.memoria.*` | `copiloto.memoria.*` ✓ |

Resultado arquitetural: módulo PHP `Modules\Jana\` operando sob fachada `copiloto.*`. **Backend renomeado, fachada user-visible preservada.**

### O que externos veem após merge

- Wagner abre `/copiloto/chat` → mesma tela de antes, sem 301
- Logs continuam em `copiloto-ai` channel
- Permissions Spatie `copiloto.chat.use` continuam válidas
- `php artisan tinker` `Modules\Jana\Services\ApuracaoService` (novo namespace funciona)
- IDE busca por `CopilotoServiceProvider` → 0 results (renomeado pra `JanaServiceProvider`)

## Justificativa

**Por que rename PHP-only.** Blast radius de rename completo é alto demais pra entregar num PR. Cada uma das 7 dimensões da fachada tem coordenação externa (DB backfill, watcher reconfig, webhook update, etc.) que multiplicam pelo número de envs. Fragmentar em PR-3+ permite cada dimensão ser decidida e aplicada isoladamente.

**Por que ainda renomear PHP.** ROI alto e risco baixo. IDE refactoring + autoloading do composer + GUARDA bin/check-scope reconhecendo `Jana`/`Ponto`/`SRS` corretamente. Custo: `composer dump-autoload` pós-deploy. Risco: zero (mecânico, validado em GUARDA).

**Por que fachada legacy não é débito permanente.** É opt-in evolution. Cada dimensão da fachada pode ser movida em PR isolado quando Wagner decidir vale o investimento. Nenhuma dimensão tem deadline.

**Por que não criar redirect 301 já.** 301 redirect requer URL nova existir. Pra existir URL nova, precisa Routes/web.php do destino + Pages React refeitos + permission slugs migrados (senão acesso quebra). Fragmentar implica criar a fachada nova ANTES do redirect — gera doubling de URLs durante transição (`/copiloto/X` E `/jana/X` ambos válidos). Aumenta surface de bugs. Melhor: manter só legacy enquanto não há motivo concreto pra mover.

**Por que `Jana`, não `Copiloto2` ou outro.** Wagner declarou: chat IA do business tem nome humano (`Jana`). Está em uso em comentários, sidebar (placeholder pré-rename), comparativos. ADR 0079 §10.4 já antecipava rename Jana.

**Por que `Ponto`, não manter `PontoWr2`.** WR2 é cliente, não nome do módulo. Ponto é nome canônico do domínio (CLT Portaria 671). PontoWr2 era artefato histórico de quando módulo nasceu da WR2.

**Por que `SRS`, não manter `MemCofre`.** MemCofre era cofre de evidências; repurpose pra System Rules Spec mudou função. Nome novo reflete função nova.

**Quando reabrir.** Reabrir 0088 quando Wagner decidir mover qualquer dimensão da fachada. Cada movimento vira ADR sub-decisão (e.g. "0090 — URL `/copiloto/*` → `/jana/*` migration").

## Cascade Review (cumprindo §10.4)

| Camada | Auditada | Resultado | Ação |
|---|---|---|---|
| L5 Module Charter | ✅ sim | 3 SCOPE.md atualizados (`module:` field renomeado, histórico bumped, `will_rename_*` removido) | OK |
| L7 Audit | ✅ sim | git mv preservou history em 369 arquivos (96-99% similarity); GUARDA reconhece Jana/Ponto/SRS | OK |
| Plano canônico | ✅ sim | MODULE-DRIFT-MIGRATION-PLAN bumped v1.1→1.2 com erratum §4 | OK |
| Composer autoload | ✅ sim | PSR-4 atualizado nos 3 composer.json; pós-deploy precisa `composer dump-autoload` | OK |
| Tests Pest | ✅ sim | imports atualizados via bulk replace (`Modules\Copiloto\` → `Modules\Jana\` etc.) | OK |
| Pages React | ✅ sim | NÃO tocadas (Pages/Copiloto/ permanece; `Inertia::render('Copiloto/X')` continua válido) | OK |
| Permissions Spatie / DB | ✅ sim | NÃO tocadas (ROTA LIVRE 5993 clientes não notam) | OK |
| Webhook GitHub / Watchers | ✅ sim | URLs preservadas — config externa intocada | OK |
| Sidebar SIDEBAR_GROUPS | ⚠️ parcial | já tem placeholders "Jana" + "Copiloto" lado a lado (ADR 0086); pós-merge revisar se ambos ainda fazem sentido OU consolidar | follow-up posterior |

## Consequências

**Positivas:**

- **Backend reflete arquitetura atualizada.** IDE, autoloading, GUARDA reconhecem nomes corretos.
- **Fachada user-visible inalterada.** Zero break em prod.
- **Rollback isolado por dimensão.** PR-3 movendo URL pode ser revertido sem mexer no PHP rename.
- **Padrão reusável.** Futuras renomeações (e.g. ProjectMgmt → Project Fase 3.9) seguem mesma estratégia.

**Negativas / Trade-offs:**

- **Cognitive dissonance temporária.** Dev novo lê `Modules\Jana\Services\X` e procura `/jana/api` — não existe. Mitigação: SCOPE.md + comments dos ServiceProviders explicam.
- **Naming inconsistente.** `Jana` (PHP) opera sob `copiloto.*` (URL/perm/config/log). Vai parecer descuidado pra auditor externo.
- **Risco de débito permanente.** Sem deadline pra PR-3+, fachada legacy pode virar "definitivo de facto". Mitigação: review_triggers desta ADR (>6 meses sem ação = sinal).
- **`composer dump-autoload` pós-deploy obrigatório.** Esquecer = autoloading quebra. Mitigação: incluir no checklist de deploy.

**Riscos mitigados:**

- 5993 clientes ROTA LIVRE perdendo permissions (DB intocado).
- Watchers Claude Code dropando ingestão (URL `/api/cc/ingest` mantida).
- Webhook GitHub dropando pushs (URL `/api/mcp/sync-memory` mantida).
- 30 `Inertia::render('Copiloto/X')` quebrando (Pages dir não tocada).
- Logs históricos perdidos por troca de channel name.
- `.env` Hostinger reescrito perdendo secrets (env vars `COPILOTO_*` mantidas).

## Implementação

✅ **FEITO em PR-2 da Fase 3.7 (commit `8f7a5138`):**

1. **3 git mv pasta**: Copiloto→Jana, PontoWr2→Ponto, MemCofre→SRS
2. **3 git mv ServiceProvider**: CopilotoServiceProvider→JanaServiceProvider, etc.
3. **314 arquivos com namespace bulk-replaced** via PowerShell (320 substituições distintas)
4. **3 ServiceProvider class names atualizadas** + docblock + middleware array key (Ponto)
5. **3 module.json** atualizadas (name, alias, providers, descrição)
6. **3 composer.json** atualizados (name, providers FQCN, autoload PSR-4)
7. **3 SCOPE.md** atualizados (campo `module:`, texto, histórico v1.x.0)
8. **Plano canônico v1.1.0 → v1.2.0** com erratum §4 (rename PHP-only)
9. **GUARDA `bin/check-scope.php`**: 0 drift / 29 módulos (Jana/Ponto/SRS reconhecidos)

⏸️ **Pendente (próximas sessões — opcional, item-a-item):**

- PR-3 (URL move) — `/copiloto/*` → `/jana/*` com 301 redirect + update Pages React route names + watchers reconfig + webhook GitHub update
- PR-4 (permissions) — backfill `copiloto.* → jana.*` em mcp_user_permissions com migration safe (preserva atual + duplica nova)
- PR-5 (Pages React) — `git mv resources/js/Pages/Copiloto resources/js/Pages/Jana` + sed em ~30 `Inertia::render`
- PR-6 (config + env) — `copiloto.php` → `jana.php` config + `.env` Hostinger update
- PR-7 (log channel) — `copiloto-ai` → `jana-ai` + grep histórico double-channel
- PR-8 (lang) — `copiloto::` → `jana::` namespace + dir
- PR-9 (DB) — opcional, alta dor: rename tabelas `copiloto_*` → `jana_*` (plano §4 default = manter legacy)

Cada PR-3..9 tem ADR sub-decisão registrando se vale executar OU se fica permanente legacy.

## Referências

- [MODULE-DRIFT-MIGRATION-PLAN v1.2.0](../governance/MODULE-DRIFT-MIGRATION-PLAN.md) §4 + erratum §4
- [ADR 0079 — Constituição](0079-constituicao-oimpresso-7-camadas-governanca.md) §10.4 cascade review
- [ADR 0080 — Trust Tiers + audit findings](0080-trust-tiers-operacional-audit-findings.md)
- [ADR 0086 — Fase 5 MVP Governance](0086-fase-5-mvp-governance-actiongate-warn.md) (sidebar GOVERNANÇA preparou Jana/Ponto/SRS)
- [ADR 0087 — Drift resolution sem mover URL](0087-drift-resolution-sem-mover-url.md) (decisão pareada — mesma filosofia)
- [Session log 2026-05-06 PR-1](../sessions/2026-05-06-fase-3-7-pr1-drift-controllers.md)
- PR [oimpresso.com#97](https://github.com/wagnerra23/oimpresso.com/pull/97) commit `8f7a5138`
