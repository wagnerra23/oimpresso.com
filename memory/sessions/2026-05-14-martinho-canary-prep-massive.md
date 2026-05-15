# Sessão 2026-05-14 — Martinho canary prep maratona

> **Owner:** Wagner + Claude (worktree `naughty-euclid-2ab744`)
> **Duração:** ~10h (manhã pós-reunião 10h → noite pós-2ª visita Felipe)
> **Cliente piloto:** MARTINHO CAÇAMBAS LTDA (biz=164 prod) — vertical OficinaAuto
> **Marcos comerciais:** (1) reunião 10h Wagner+Martinho — topou testar oimpresso; (2) tarde Felipe foi presencial — Jair (dono majoritário) endossou + Kamila pausou avaliação Highsoft (concorrente)

## Resultado executivo

**Vitória comercial dupla:**
- Martinho topou de manhã (reunião 10h)
- Jair (dono) endossou de tarde · pausa Highsoft

**Vitória técnica:**
- 115k+ rows migrados Firebird→MySQL prod biz=164
- 2 telas críticas migradas Blade→React (Contacts + Sidebar)
- 3 importers fortalecidos cinto-suspensório pós-incidente ROTA LIVRE
- Recovery surgical 5 VLDs ROTA LIVRE corrompidas (zero perda Larissa)
- 1 ADR proposal arquitetural dual-sync escrita
- 2 agents background rodando ao fim do dia (daemon Fase 1 + MWART /products)

## Cronologia (densa)

### Manhã

- **10h:** reunião Wagner + Martinho confirma piloto. P0 decididos (pricing R$ 830 paridade + canary 7d tela por tela + filha+Dani champions duplos)
- **~12h:** dry-runs Wave A — contacts 11.561 · financeiro 98.533 · vendas 5.079 (12m filter)
- **~13h:** dispara prod biz=164 — vehicles 91 ✅, contacts 18.845 ✅, transactions 44k ✅, fin_titulos parcial 5.546 (Firebird connection shutdown no meio)
- **~14h:** sub-wave A2 produtos+estoque prod — descoberto **bug cross-business** corrompeu 5 VLDs ROTA LIVRE biz=4 (CARDIGAN + JAQUETA + BLUSA variations)

### Tarde

- **~14:30:** **incidente ROTA LIVRE detectado** — Wagner cortou progressão
- **~15:00:** recovery via backup Hostinger `~/.cagefs/tmp/oimpresso-dump-20260513-195514.sql.gz` (12h pre-bug) — extraídos valores originais via grep regex + 3 UPDATEs aplicados — Larissa não viu nada
- **~15:30:** cleanup write-off — 748 títulos receber vencidos >3 anos R$ 844.660 flagados `metadata.is_write_off_candidate=true`
- **~15:51 WhatsApp Kamila:** *"continuar usando o sistema antigo e colocar o sistema novo para a lara e a dani usar... ele consegue puchar as informações em tempo real do sistema antigo"* — **insight estratégico de ouro**
- **~16:00:** ADR proposal `dual-system-delphi-oimpresso-sync-realtime.md` (11 seções) escrita baseado insight Kamila
- **~16:30:** Felipe foi presencial Martinho — segunda reunião
- **~16:45:** Felipe reporta a Wagner: Jair (dono majoritário) entrou na sala + endossou oimpresso + Kamila pausou Highsoft + co-design presencial Martinho 20km viável

### Noite

- **~17:00:** Wagner "bora trabalhar"
- **~17:15:** Spawn agente Daemon Fase 1 MVP dual-sync background (`a13a132de0c4217f1`)
- **~17:30:** Spawn agente MWART /products + /stock-history background (`ad2f9e74103193c6a`)
- **~17:35:** Fix `/sells/create` dual-render — hotfix biz=164 ao whitelist Inertia em SellController (preserva guard ROTA LIVRE biz=4)
- **~17:45:** Sidebar Martinho ajustada — grupo `estoque` removido do hidden list (Lara cuida estoque) + items específicos `Transferências`/`Ativos` adicionados
- **~18:00:** Session log + handoff (este doc)

## Decisões tomadas (rastreáveis)

### P0 da reunião Martinho

| # | Decisão | Status |
|---|---|---|
| Pricing | R$ 830/m paridade Delphi + upsell módulos novos (WhatsApp R$ 200-300/m, Jana IA, NFSe, PWA) | ✅ Wagner |
| Escopo Fase 1 | Cadastro cliente + Financeiro AR+AP + Produtos + Compra + MDe + Estoque + OS | ✅ Wagner |
| Prazo cutover | Canary 7d tela por tela com filha+Dani validando | ✅ Wagner |
| Champions | LARA (filha · estoque) + DANI (financeiro) — KAMILA continua Delphi operação | ✅ Wagner |
| Rename `vehicles` → `oa_vehicles` | NÃO — deixar como está, evita churn | ✅ Wagner |
| /contacts Blade legacy | Migrar MWART semana 1 paralelo canary | ✅ Wagner |

### Pós-Felipe presencial (decisões emergentes)

| # | Decisão | Status |
|---|---|---|
| Highsoft (concorrente) | **PAUSADO** — Kamila escolheu oimpresso | ✅ Jair+Kamila |
| Dual-system Delphi master + oimpresso viewer | Approach principal (ADR proposal) | ✅ Implícito Jair |
| Co-design presencial Martinho 20km | Viável — visitas regulares Wagner sem hospedagem | ✅ Jair endossou tempo |
| Backup ROTA LIVRE Larissa enquanto Wagner viaja | Não-bloqueante (não some, só visitas) | ✅ Auto |
| Migrar estoque Martinho (Lara) | Sidebar mostra grupo estoque · MWART /products amadurece | ✅ Wagner |
| Daemon dual-sync Fase 1 MVP | Spawn agente noite 14/maio | ✅ Wagner |

## Incidente catalogado: ROTA LIVRE biz=4 VLDs corrompidas

### Causa raiz

3 importers (estoque + produtos + compras) tinham SELECT/UPDATE em `variation_location_details` SEM JOIN+WHERE business_id explícito. Embora lookup `(business_id, officeimpresso_codigo)` retornasse product_id Martinho biz=164, o SELECT VLD posterior usava `WHERE product_id=X AND variation_id=Y AND location_id=Z` — abriu janela teórica pra match cross-business.

### Dano

5 VLDs ROTA LIVRE atualizadas (CARDIGAN M/G qty preservada por coincidência; JAQUETA P/M −1; BLUSA P −1; BLUSA G −1 · total 3 unidades).

### Recovery

Backup `~/.cagefs/tmp/oimpresso-dump-20260513-195514.sql.gz` (13/maio 19:55 BRT · 12h pre-bug) → grep regex no INSERT VLD da tabela específica → extraídos valores originais → 3 UPDATEs single-shot prod.

### Mitigação aplicada (14/maio 17h)

3 importers fortalecidos com cinto-suspensório:
- `import-estoque.py` linhas 359-388 — SELECT/UPDATE com `INNER JOIN products + WHERE business_id` + `rowcount==0 → skip + log`
- `import-produtos.py` linhas 560-571 — SELECT defensivo
- `import-compras.py` linhas 624-632 — UPDATE transactions com `AND business_id=%s`

Stat tracking `skipped_cross_business_guard` adicionado.

## Estado final prod biz=164

| Tabela | Rows biz=164 | Esperado | % |
|---|---:|---:|---:|
| contacts | 18.845 | 18.845 | 100% |
| transactions (sell) | 43.995 | ~44k | 100% |
| vehicles | 91 | 91 | 100% |
| service_orders | 91 | 91 | 100% |
| products | 1.838 | 4.378 | 42% (re-rodar c/ fix) |
| variation_location_details (qty≠0) | 4.279 | 4.581 | 93% |
| fin_titulos | 5.546 | 98.533 | 6% (Firebird connection caiu) |
| fin_titulos flagged write-off | 748 (R$ 844.660) | — | — |
| purchase_lines | 1 | 16k | 0% (esperando fornecedores importer) |

**Peso DB total Hostinger:** 285 MB (Hostinger Business plan 100GB comporta tranquilo).

## Arquivos entregues neste dia

### Branch `claude/doc-armadilha-tz-multitenant` (Wagner consolida em PRs)

**MWART /contacts (agent `a8fd0a5de4b0c1559`):**
- `resources/js/Pages/Crm/Contacts/Index.tsx`
- `resources/js/Pages/Crm/Contacts/Create.tsx`
- `resources/js/Pages/Crm/Contacts/Edit.tsx`
- `resources/js/Pages/Crm/Contacts/Show.tsx`
- `resources/js/Pages/Crm/Contacts/Index.charter.md`
- `resources/js/Pages/Crm/Contacts/Create.charter.md`
- `memory/requisitos/Crm/RUNBOOK-contacts.md`
- `tests/Feature/Crm/ContactsInertiaTest.php` (21 Pest passed)
- `app/Http/Controllers/ContactController.php` (dual-mode)
- `routes/web.php` (+ `/contacts/list-json`)

**Sidebar customizada (agent `a27bb3b8a6bda7306`):**
- `database/migrations/2026_05_14_120000_add_sidebar_hidden_groups_to_business.php`
- `database/seeders/BusinessSidebarConfigSeeder.php`
- `tests/Feature/Sidebar/SidebarPerBusinessTest.php` (26 Pest passed)
- `memory/requisitos/_DesignSystem/RUNBOOK-sidebar-per-business.md`
- `app/Services/LegacyMenuAdapter.php` (filter+cache)
- `database/seeders/DatabaseSeeder.php` (registrar seeder)

**Bug fixes importers (eu mesmo):**
- `scripts/legacy-migration/import-estoque.py` (cinto-suspensório SELECT/UPDATE)
- `scripts/legacy-migration/import-produtos.py` (cinto-suspensório SELECT VLD)
- `scripts/legacy-migration/import-compras.py` (cinto-suspensório UPDATE transactions)

**Hotfix /sells/create dual-render (eu mesmo):**
- `app/Http/Controllers/SellController.php` linhas 805-816 (whitelist canary biz=164)

**Sidebar config Martinho ajustado (eu mesmo):**
- `database/seeders/BusinessSidebarConfigSeeder.php` (estoque removido do hidden + Transferências/Ativos adicionados)

**Migrar Martinho wrapper:**
- `scripts/legacy-migration/migrar-martinho.py` (SSH tunnel + 3 importers)

**ADR proposal arquitetural:**
- `memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md` (11 seções + 4 lições incidente)

**Doc Martinho:**
- `memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md`

### Em curso (background ao fim do dia)

- Agent `a13a132de0c4217f1` — Daemon Fase 1 MVP dual-sync (~4-6h)
- Agent `ad2f9e74103193c6a` — MWART /products + /stock-history (~4-6h)

## Lições aprendidas

### 1. Backup Hostinger é literalmente salva-vidas

Sem `~/.cagefs/tmp/oimpresso-dump-DATE.sql.gz` no Hostinger, ROTA LIVRE seria reconciliação manual com Larissa (constrangedor). Manter cadência diária e validar.

### 2. Cinto-suspensório > confiança em variáveis Python

`WHERE business_id=X` no Python NÃO substitui `WHERE business_id=X` no SQL. Tier 0 obriga ambos. Pest cross-tenant biz=1 vs biz=99 vs biz=4 obrigatório por importer.

### 3. Firebird connection NÃO é confiável pra batch grande

Conexão Firebird LAN caiu durante query de 98k rows. Daemon precisa chunks paginados + retry exponencial + checkpoint per chunk. Sem isso, sync nunca completa em redes domésticas.

### 4. Importer NÃO pode sobrescrever metadata user-added

Write-off flags Dani · tags Lara · observações Wagner — devem ser preservados em re-runs idempotentes. Pattern correto: `JSON_MERGE_PATCH` + namespaces `metadata.import_*` (importer) vs `metadata.user_*` (preservar).

### 5. Cliente sugere arquitetura melhor que você pensou

Kamila propôs dual-system sem ter visto ADR proposal · cabia exatamente na nossa estrutura técnica. Escutar cliente real (ADR 0105 sinal qualificado) > inventar wish.

### 6. Reunião com dono majoritário muda jogo

Jair entrou na sala 30s · endossou · destravou tudo (pause Highsoft + tempo co-design). Wagner-Felipe presencial conseguiu o que Wagner sozinho remoto não conseguiria.

## Pendências pro Wagner consolidar (segunda 19/maio)

1. ⏳ **Consolidar git** — branch `claude/doc-armadilha-tz-multitenant` tem ~16 arquivos pra virar 4-5 PRs (Contacts MWART · Sidebar · Bug fixes · Sells hotfix · ADR proposal)
2. ⏳ **Migrate prod** — `php artisan migrate` (adiciona coluna `business.sidebar_hidden_groups`)
3. ⏳ **Seed prod** — `php artisan db:seed --class=BusinessSidebarConfigSeeder --force` (popula Martinho)
4. ⏳ **Aprovar ADR proposal** como ADR 0144 accepted
5. ⏳ **Dados Lara** — pegar nome+email+telefone com Martinho · criar user biz=164
6. ⏳ **Confirmar DANIELLI (id=297) = Dani financeiro**
7. ⏳ **GrowthBook UI** — criar rule `useV2SellsCreate` per business_id (remover hotfix hardcoded)
8. ⏳ Aguardar 2 agents background completarem (Daemon Fase 1 + MWART /products)

## Refs

- [ADR proposal dual-sync](../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md)
- [CHECKLIST-POS-REUNIAO](../requisitos/OficinaAuto/demo-martinho-2026-05-13/CHECKLIST-POS-REUNIAO.md)
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md) — Pest biz=1 nunca cliente
- [ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART canônico
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline LIVE prod

---

**Encerramento:** 2026-05-14 18:00 BRT
**Próxima sessão:** segunda 19/maio (Wagner consolida git · canary Lara+Dani inicia)
