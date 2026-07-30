---
id: requisitos-auditoria-deprecation-plan
---

# DEPRECATION-PLAN — Modules/Auditoria

> **Status:** 📋 Planejado · **Owner:** [W] · **Decisão:** [W] 2026-07-30 (*"todos esses eu vou deletar"*)
> **Ordem no conjunto:** **1º de 6** — [proposal da ordem topológica](../../decisions/proposals/2026-07-30-deprecar-6-modulos-governanca-ordem-topologica.md)
> **É o delete mais barato do conjunto.** Um acoplador, e ele é um teste.

## ⚠️ ERRATA 2026-07-30 — o veredito CONFIRMA, mas *"nada no produto depende"* é forte demais

> Origem: revisão adversarial pedida por [W]. **Dois agentes de mandato oposto concordaram que este
> módulo deve morrer, e é o único dos 6 com essa unanimidade.** A errata é só de precisão.

**Confirmado:** 1 acoplador de namespace e é um teste (`tests/Feature/Auditoria/RevertServiceTest.php`)
· **0** cron (medido pelo oráculo de runtime, não por parse) · **0** tool MCP · **0** required ·
1 tabela própria que **nunca migrou** pra produção.

**A ressalva:** existe acoplamento que o grep de namespace **não vê** — `Route::has('auditoria.index')`
em `app/Http/Middleware/AdminSidebarMenu.php`. Ele degrada com graça (cai no Blade legado, que segue
vivo em `routes/web.php`), então não bloqueia. Mas *"nada no produto depende"* é impreciso: o padrão
`route()` por nome é um vetor de acoplamento que **nenhum dos 6 planos mediu**.

**A perda é declarável, não invisível:** o `RevertService` (undo de operações, [ADR 0127](../../decisions/0127-modules-auditoria-undo-activity-log.md), `status: aceito · lifecycle: ativo`) some com o módulo — com suas
categorias *unrevertible* (Portaria MTP 671/2021, NFe SEFAZ, Asaas pago). Declarar item a item, como
a [ADR 0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md) fez com o Admin.

## Fase 1 — Inventário

**Não repetido aqui.** O inventário é **gerado**: [`SUPERFICIE.md`](SUPERFICIE.md) — **36 arquivos em 12 papéis**, por `scripts/governance/module-surface.mjs Auditoria --write`. Frescor conferido em 2026-07-30: `--check` **exit 0**.

Regenerar antes de cada fase: `node scripts/governance/module-surface.mjs Auditoria --check`.

Fora da superfície do módulo: **2 telas** `.tsx` (dono do número: `npm run screen-coverage:report`) · **2 arquivos** em `Routes/` · **0** tools MCP · **0** entradas de cron em `Kernel.php`.

## Fase 2 — Estado em produção (medido ANTES de planejar)

**Sistema medido:** `APP_ENV=live` · `u906587222_oimpresso` · 385 tabelas · **2026-07-30**. Existência por `information_schema.tables`.
**Controle positivo:** `business=82` · `users=124` · `transactions=75.255`.

| Tabela | Estado |
|---|---|
| `auditoria_audit_notes` | ⚠️ **NÃO EXISTE no Hostinger** |

**Consequência:** a migration do módulo **nunca chegou em produção**. Não há linha, não há PII, não há dado cross-tenant, não há índice a reconstruir. **Todos os riscos Tier 0 que pressupõem volume evaporam** — exatamente o que a reconciliação do [plano do SRS](../SRS/DEPRECATION-PLAN.md) descobriu tarde e este descobre antes.

⚠️ **Ressalva que NÃO caducou:** "ausente no Hostinger" ≠ "não existe". A tabela pode viver no **CT 100** ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)). **Medir lá é pré-requisito da Fase 4** — `tailscale ssh root@ct100-mcp`. Não medido aqui.

## Fase 3 — Acoplamento externo

`git grep -lF 'Modules\Auditoria\'` fora da própria pasta, sem `memory/` nem `.claude/` → **1 arquivo**:

- `tests/Feature/Auditoria/RevertServiceTest.php`

Nenhum Controller, Service, Provider, rota ou cron de outro módulo referencia o namespace. **Nada no produto depende deste módulo.**

## Fase 4 — Decisão por tabela

| Tabela | Decisão | Por quê |
|---|---|---|
| `auditoria_audit_notes` | **DROP** (se existir no CT 100) · **no-op** no Hostinger | 0 linhas onde existe; arquivar zero linhas é cerimônia vazia (precedente: T1..T7 do SRS) |

**Ordem obrigatória (lição do SRS, E3):** o DROP vem **depois** do refactor de código, nunca antes. O plano do SRS dropava na E3 e *"isso derrubaria produção"*.

## Destino por função — realocação

> Medido 2026-07-30: `use Modules\Auditoria\` fora da pasta → **1 arquivo, e é um teste**. Nenhum consumidor sobrevivente. Isto é o que torna a realocação **opcional** aqui, ao contrário dos outros 5.

| Peça | Módulo dono correto | Base da decisão |
|---|---|---|
| `Services/AuditEntryService` | **ninguém — morre** | a trilha de auditoria canônica é `mcp_audit_log`, tabela do `Modules/Jana` ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)). Este service escreve numa tabela paralela que **nunca chegou em produção** — é duplicata de régua consolidada que não pegou. |
| `Services/RevertService` + `Services/RevertCheck` | **decisão [W]** — candidato `Modules/Jana` | é a única **capacidade** do módulo (reverter ação auditada). Se [W] quiser preservá-la, o receptor é quem tem o log: Jana. Se não, morre com o módulo. **Não há consumidor pressionando** — o único é o teste dele mesmo. |
| `Entities/AuditNote` + `auditoria_audit_notes` | **ninguém — morre** | 0 linhas onde a tabela existe; ausente no Hostinger |
| `Console/Commands/AuditoriaHealthCommand` | **ninguém — morre** | health de módulo que não existe mais |
| 2 telas `.tsx` | **decisão [W]** | dono do número é `npm run screen-coverage:report`; não avaliadas uma a uma |
| `tests/Feature/Auditoria/RevertServiceTest.php` | **sai junto** | único acoplador; morre com o service ou vai com ele pro receptor |

**Leitura honesta:** este é o **delete mais barato do conjunto** e o único onde "não realocar nada" é resposta defensável. A pergunta que sobra não é *"pra onde vai"*, é *"a capacidade de reverter ação auditada importa?"* — e essa é [W], não derivável.

## Fase 5 — Riscos Tier 0

Nenhum identificado. É o único dos 6 nessa situação, e é por isso que vai primeiro.

| Risco genérico | Estado aqui |
|---|---|
| PII / LGPD | **não existe** — 0 linhas |
| cross-tenant (`business_id`) | **não existe** — 0 linhas |
| gate required órfão | **não** — nenhum required cita Auditoria (`governance/required-checks-baseline.json`) |
| sucessor canônico apontado por ADR | **não** — nenhuma ADR nomeia Auditoria como receptor |

## Roadmap

| Etapa | O que | Gate [W] |
|---|---|---|
| **E1** | Medir CT 100 (a tabela existe lá?) | — |
| **E2** | Remover `tests/Feature/Auditoria/RevertServiceTest.php` ou re-apontar pro receptor | — |
| **E3** | Remover `Modules/Auditoria/` + 2 telas + rotas + permissions Spatie + entrada em `modules_statuses.json` | ✋ [W] aprova o PR |
| **E4** | Migration de DROP (só se E1 achar a tabela) | ✋ [W] aprova |
| **E5** | Smoke real em prod: rotas do módulo em 301/410 + tabela ausente | ✋ [W] confere |
| **E6** | Lápide no §5 de `proibicoes.md` + `BRIEFING` final | — |

## Resíduo honesto

- **CT 100 não medido** — é a única incógnita real deste plano.
- **Pest não rodado** (Tier 0 manda no CT 100). O acoplador único é leitura de código, não veredito de execução.
- **As 2 telas** não foram inventariadas aqui — o dono é `screen-coverage:report`.
