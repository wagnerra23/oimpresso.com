---
distilled_at: "2026-07-17"
distilled_by: "manual [CC] — redistilação por releitura (rotas + comandos + seeder FSM + charter + baseline). Substitui o carimbo de 2026-07-09, que herdou gaps já entregues do CAPTERRA-FICHA e um 'meta 2026-Q3' 2 meses errado"
module: OficinaAuto
status: piloto
updated_at: "2026-07-17"
related_adrs:
  - "0171-oficinaauto-ativacao-piloto-martinho-faseada"
  - "0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"
  - "0265-oficina-reparo-erradica-locacao"
  - "0264-governanca-executavel-trio-dominio-e2e"
---

# BRIEFING — OficinaAuto (verdade destilada)

## Estado atual

Módulo vertical de **oficina de reparo de veículos pesados** (CNAE 4520-0/01) — **piloto LIVE em produção** para Martinho (biz=164) **desde 2026-05-13** (ativação faseada formal [ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)). O domínio é **reparo, nunca locação** ([ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md)).

**Duas notas, escalas e donos diferentes — não confundir:**
- **Module-grade v3 = 80/100 (Bom)** — dono: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) (rubrica v3, [ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md); recomputar com `php artisan module:grade OficinaAuto`). Mede código/multi-tenant/LGPD/perf/obs.
- **Capterra scoped = 63/100 (meta ≥85)** — dono: [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md). Mede paridade competitiva + jornada na vertical. Não contradiz a 80 (medem coisas diferentes).

> ⛔ **Errata do destilado anterior — não re-alegar.** Ele dizia *"meta 2026-Q3"* pra virar piloto: **errado**, o piloto já é LIVE desde maio ([ADR 0171](../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)). O *"meta Q3"* foi copiado do CAPTERRA-FICHA (que também estava stale). E citava só a nota 63, nunca a canônica 80.

## Capacidades

Varridas em 2026-07-17 (`git ls-files` — arquivos, não testes verdes; rodar Pest é CT 100, [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)): **9 controllers · 5 comandos · 4 entities · 14 services · 20 migrations · 44 arquivos de teste · 30 `.tsx` (9 páginas roteadas + 21 componentes).**

- **OS via Quadro Kanban FSM** (`ServiceOrders/Board.tsx`): arrastar card **executa transição FSM real** via `ExecuteStageActionService` (nunca UPDATE direto) — processo `oficina_mecanica_os` (recepção→diagnóstico→aprovação→peças→execução→pronto).
- **DVI (vistoria digital)**: itens de inspeção + decisão do cliente + **foto por item** + item reprovado vira linha de orçamento (`DviInspectionController@toOrcamento`).
- **Fotos/laudo OS-level** (`ServiceOrderPhotoController`, entram no laudo A4) + **itens de OS** (peça/mão-de-obra/serviço) + **print A4** (`ServiceOrderController@printInvoice`).
- **Aprovação pública WhatsApp + PIN** (token HMAC + PIN 4 díg. + lockout) — `Public/AprovacaoOsController` + `AprovacaoPublica.tsx` + `EnviarLinkAprovacaoWhatsappJob`.
- **CRUD de Veículo** + **lookup de placa** pluggable (stub/http via `.env`) — `Services/PlacaLookup/*`.
- **Importer Firebird Martinho** + tooling de migração legado (cleanup/report/sanity CLI).

## Gaps

Cruzados com SPEC + código (2026-07-17):

| Gap | Estado real | Âncora |
|---|---|---|
| **`ServiceOrder` sem trait `GuardsFsmTransitions`** — UPDATE direto em `current_stage_id` fica **desguardado**, contra o guard-rail FSM canon. Gap Tier-0 de robustez que o destilado anterior não citava | ❌ **ABERTO** | US-OFICINA-006 `_parcial_` (SPEC:373) |
| Catálogo de peças OEM (rota `/oficina-auto/pecas` não existe) | ❌ **ABERTO** | SPEC:752 `_pendente_` |
| Apontamento multi-mecânico (OS tem 1 `assigned_user_id`; tabela de atribuições não existe) | ❌ **ABERTO** | SPEC:707 |
| Dívida F3 do domínio — keys FSM `cacamba_locacao` (`disponivel/locada`) no seeder + status de veículo, **preservados de propósito** aguardando ADR própria; UI "Locações ativas" a remover | 🟡 dívida catalogada | US-OFICINA-046 `todo` · charter v4 |

> ⛔ **Errata — gaps que o destilado anterior listava como abertos já foram entregues:** **aprovação PIN/token** (dizia "top gap") = US-OFICINA-014 `done`; **checklist visual com fotos** = em grande parte entregue (DVI + fotos por item + laudo).

**Domínio "locação" ([ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md)):** caminhos de **produção** limpos — enum `order_type ∈ {manutencao, mecanica}`, importer normaliza, KPI/filtro `locacao_ativa` removidos, gate `dominio:check` ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4) trava CI se reaparecer. O resíduo é **schema preservado como dívida F3** (não regressão) — detalhe em [RUNBOOK-erradicacao-locacao.md](RUNBOOK-erradicacao-locacao.md) (P1-P3 já feitos, parcialmente stale).

## Última mudança

Recibo: `git log --since=2026-07-09 -- Modules/OficinaAuto memory/requisitos/OficinaAuto resources/js/Pages/OficinaAuto`, rodado 2026-07-17 → **6 commits, todos higiene, zero código de módulo** (`git log --since=2026-07-09 -- Modules/OficinaAuto` = **0**): dark-mode do Board ([#4367](https://github.com/wagnerra23/oimpresso.com/pull/4367)/[#4373](https://github.com/wagnerra23/oimpresso.com/pull/4373)), baseline visreg ([#4388](https://github.com/wagnerra23/oimpresso.com/pull/4388)), backfill de frontmatter ([#4274](https://github.com/wagnerra23/oimpresso.com/pull/4274)), Padrão de Tela em charters ([#4109](https://github.com/wagnerra23/oimpresso.com/pull/4109)), e o arquivamento do charter-ghost `Os/Create` (lápide L-22, [#4037](https://github.com/wagnerra23/oimpresso.com/pull/4037)).

> Nota honesta: **o próprio motivo desta redistilação foi ruído** — o doc que empurrou a porta pra 7d (`RUNBOOK-board.md`, 07-16) mudou por um **rename** (`git mv`, dentro de um fix de CSS), não por evento de negócio. Nenhuma capacidade nem gap mudou desde 07-09.

## Estado do piloto (biz=164)

Números **medidos em 2026-05-13 via SSH** ([discovery-martinho.md](demo-martinho-2026-05-13/discovery-martinho.md) + charter v3) — **não remedidos** (query de banco é CT 100, fora do CI): **91 veículos · 91 OS · ~44k vendas · ~103k títulos** importados do Firebird legado. Não há sinal de novo import ou saúde do piloto pós-julho. O "42k+" atemporal do destilado anterior era arredondamento das ~44k vendas — o número correto vem com data e fonte, nunca solto.

## Proveniência (destilado de)

Releitura direta em 2026-07-17 (o destilado anterior citava 8 fontes, a mais nova de 2026-06-30):

- código: `Modules/OficinaAuto/Http/Controllers/` (9) · `Routes/web.php` · `Console/Commands/` (5) · `Database/Seeders/OficinaAutoFsmSeeder.php` · `Services/PlacaLookup/`
- contrato: [SPEC.md](SPEC.md) (US-OFICINA-001..046) · [OficinaAuto.charter.md](OficinaAuto.charter.md) · [RUNBOOK-erradicacao-locacao.md](RUNBOOK-erradicacao-locacao.md)
- números: [`governance/module-grades-baseline.json`](../../../governance/module-grades-baseline.json) (80) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (63) · [discovery-martinho.md](demo-martinho-2026-05-13/discovery-martinho.md) (piloto, medido 2026-05-13)
- janela: `git log --since=2026-07-09 …` (6 commits)
