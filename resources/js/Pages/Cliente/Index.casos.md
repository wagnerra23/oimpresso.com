---
casos: Lista de clientes + drawer 760 · /cliente
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — os KPIs reais, paginação/ordenação server-side e a aba "Outros" não mudam no refactor.
owner: wagner
last_run: "2026-07-15"
---

# Casos de Uso & Aceite — Lista de clientes

> Fase 2 (lanes do Cliente). Tela principal do módulo. UCs ancorados em testes da lane ativa (Pest, CT100): `ClienteTypeOtherRouteTest` (render HTTP real), `ClientePaginacaoServerSideTest`, `ClienteSortServerSideTest`, `ClienteKpisServerSideTest`. Onde o teste é guard estrutural do query (a semântica É a corretude), o **número real** vira ✅ só com smoke — por isso os status são 🧪.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado / prova de render = smoke) · ⬜ não verificado · ❌ quebrou.
>
> **Revalidação 2026-07-14 (consolidação DS):** a barra de abas migrou da versão inline hand-rolled pro componente canônico `PageHeaderTabs` em faixa própria (posição do protótipo + contadores). Os UCs abaixo são de **rota/query** (`?type=X`, paginação, ordenação, KPIs) — os `href` das abas são **idênticos**, então a navegação testada não muda. Status mantidos (🧪).

---

## UC-CIDX-01 · A aba "Outros" abre sem cair em Clientes
- **Persona:** Larissa — clica na aba **Outros** (contatos que não são cliente/fornecedor/equipe/repr., ADR 0246) e vê a lista certa.
- **Aceite:** Dado a listagem · Quando faço `GET /cliente?type=other` · Então renderiza Inertia com `activeType=other` (não `customer`) — as 3 camadas (rota whitelist + `$types` + `$inertiaTypes`) aceitam `other`.
- **Teste:** `tests/Feature/Cliente/ClienteTypeOtherRouteTest.php` — `rota /cliente aceita type=other na whitelist` + `GET /cliente?type=other renderiza Inertia com activeType=other`.
- **Regressão que defende:** a aba "Outros" caía em Clientes por falta do `other` na whitelist (incidente #2297).
- **Status: 🧪** — render HTTP Inertia passa no CI; ✅ com o manifesto regravado.

---

## UC-CIDX-02 · Trocar de página busca no servidor (setas vivas)
- **Persona:** Larissa — com milhares de clientes, mudar a página / o tamanho tem que re-buscar de verdade, não travar na página 1.
- **Aceite:** Dado a lista paginada · Quando mudo a página · Então o front manda `page` + `per_page` no reload e o backend (`buildClienteIndexCustomers` via `paginate()`) devolve a fatia certa; mudar `per_page` reseta pra página 1.
- **Teste:** `tests/Feature/Cliente/ClientePaginacaoServerSideTest.php` — `onPageChange manda page + per_page pro servidor` + `onPerPageChange reseta page=1`.
- **Regressão que defende:** bug 2026-06-12 "paginação não funciona" — setas mortas (só state local, sem reload).
- **Status: 🧪** — teste de contrato passa; ✅ com o manifesto regravado.

---

## UC-CIDX-03 · Ordenar por coluna re-busca com whitelist (default: recentes)
- **Persona:** Larissa — quer os clientes recentes/relevantes no topo, não lixo alfabético (".COM", "@") enterrando os que importam.
- **Aceite:** Dado a lista · Quando clico num cabeçalho de coluna · Então o sort é server-side com **whitelist** (anti-injeção), default = recentes (`id desc`), colunas agregadas via `leftJoinSub` com NULLs por último.
- **Teste:** `tests/Feature/Cliente/ClienteSortServerSideTest.php` — `sort lido com whitelist + default RECENTES (id desc)` + `handleSort re-busca server-side`.
- **Status: 🧪** — guard de query passa; ordem real = smoke; ✅ com o manifesto regravado.

---

## UC-CIDX-04 · Os KPIs do topo vêm de query real, isolados por tenant
- **Persona:** Larissa — os cards (VIPs / sem compra 90d / novos do mês) têm que ser números de verdade do negócio dela, não estimativa da página.
- **Aceite:** Dado a lista carregada · Quando os KPIs são computados · Então `buildClienteIndexKpis` devolve os counts reais (VIPs = `vip=1`; novos = `created_at >= início do mês`; sem compra 90d = já comprou mas nada nos últimos 90d), **scoped por `business_id`** (Tier 0).
- **Teste:** `tests/Feature/Cliente/ClienteKpisServerSideTest.php` — `buildClienteIndexKpis devolve os 3 counts reais` + `Tier 0: subqueries do sem_compra_90d scoped por business_id`.
- **Regressão que defende:** KPIs estimados client-side sobre as 50 rows da página ("número sem prova") + risco de vazamento cross-tenant no count.
- **Status: 🧪** — guard de query + Tier 0 passam; número real = smoke; ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Clicar num KPI aplica o filtro correspondente (toggle 2x desativa)** — exige spec e2e do `KpiStripClickable`.
- **[BACKLOG] Abrir o drawer 760 ao clicar na linha + autosave on blur** — spec Playwright (o cadastro em si é coberto por Edit.casos UC-CEDI-04).
- **[BACKLOG] 6 dropdowns de filtro (Tipo/Status/UF/Tags/Sem compra/Saldo) filtram** — e2e.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter="ClienteTypeOtherRouteTest|ClientePaginacaoServerSideTest|ClienteSortServerSideTest|ClienteKpisServerSideTest"` no CT100.
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `Index.tsx` ou nos builders do `ContactController` (kpis/customers/sort).

## Trilha do tempo
- 2026-07-15 · [CC] revalidação (mudança cosmética DS) — troca de contorno/divisórias hardcoded (`oklch(0.93/0.9 0.004 90)`) por `var(--border)` dark-aware no header + KPI cards. Puramente visual (token de borda); NÃO altera rota/query/KPI backend — os 4 UCs (type=other, paginação, sort, KPIs) seguem válidos, status 🧪 mantidos. `last_run` bumpado (G-6). PR #4285.
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente), tela principal. 4 UCs ancorados em `ClienteTypeOtherRouteTest` / `ClientePaginacaoServerSideTest` / `ClienteSortServerSideTest` / `ClienteKpisServerSideTest`. Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · [ADR 0246](../../../../memory/decisions/0246-tipo-outros-default-migracoes-legacy.md) · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
