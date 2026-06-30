# Gap — Compras/Index.tsx (vivo) × compras-page.jsx (mockup Cowork)

> Fase 1 (READ-ONLY) da skill `aplicar-prototipo`. Mapeia o que o mockup propõe que a tela viva NÃO tem, e onde o vivo já passou na frente (stale).
> **Arquivos comparados:**
> - VIVO: `resources/js/Pages/Compras/Index.tsx` (849 linhas, Inertia+TS, deferred, backend real)
> - MOCKUP: `_cowork-handoff-staging/.../project/compras-page.jsx` (640 linhas, IIFE+React global, dados MOCK)
> Data: 2026-06-30 · PT-BR · multi-tenant `business_id` Tier 0 intocável.

## Contexto-chave

O vivo é a Index "Wave 1+2+3+4 F1 pin literal do protótipo" — já implementa filtros server-side reais, paginação real, KPIs reais via `<Deferred>`, drawer real (`./components/Drawer`), permissões C1 (`purchase.create/update/delete`), export bridge p/ Blade legacy. O comentário-cabeçalho do vivo declara explicitamente: *"Drawer 5 tabs / Importar XML / Nova compra ficam pra Waves 6+"*.

O mockup é o protótipo Cowork de referência (mesmo `compras-page.jsx` que o vivo "pinou"), porém **mais completo no DRAWER e em detalhes de header/tabs/colunas** — porque é um mock estático sem custo de backend. Vários "extras" do mockup já estavam previstos para Waves futuras no próprio vivo.

---

## Por parte

### 1. Header
- **Gap real (mockup→vivo):** mockup usa `header.os-page-h` (estrutura PageHeader canon `os-page-h-l`/`os-page-h-r`); o vivo usa `header.hd` legado com crumbs + `<h1>` + count inline. O mockup está mais alinhado ao PageHeader canon. **Mas:** o vivo tem breadcrumb (`crumbs`) + contador "X de Y" que o mockup não traz no mesmo formato.
- **Vivo-à-frente (stale no mockup):** vivo tem gate de permissão `permissions.create &&` no botão "+ Nova compra" (mockup mostra sempre); vivo tem `title` explicando Wave 6 no Importar XML (desabilitado, honesto); vivo liga a busca a `router.visit` real (mockup é input morto).
- **POR QUÊ:** o mockup propõe migrar o header pro padrão `os-page-h`; o vivo intencionalmente manteve `hd` por ser "pin literal" de uma versão anterior do protótipo.
- **Esforço/risco:** **P** (troca de markup/classe de header) · risco baixo — visual apenas, **não toca valor**. Requer conferir se `cowork-compras-bundle.css` tem as classes `os-page-h*`.

### 2. KPIs (4 cards)
- **Gap real:** mesmos 4 KPIs (A pagar / Em trânsito / Volume do mês / Fornecedores ativos). O mockup tem **sub-linhas (`.ln`) mais ricas**: "próx. venc. 09/05", "+12,4% vs. abr/26 · meta R$45.000", "3 com compra recorrente · 1 novo". O vivo usa sub-linhas técnicas/neutras ("compras em aberto (due + partial)", "distinct contact_id").
- **Vivo-à-frente:** vivo busca KPIs reais via `<Deferred data="kpis">` + skeleton; mockup calcula de array MOCK. Vivo é a versão produção-real.
- **POR QUÊ:** sub-linhas ricas do mockup dependem de dados que o backend talvez não exponha (variação MoM %, meta, próximo vencimento, recorrência). **_pendente_** confirmar se `kpis` payload tem esses campos.
- **Esforço/risco:** **M** se adotar sub-linhas ricas (precisa estender backend `kpis`) · ⚠️ **toca valor** (KPI "A pagar"/"Volume do mês" são somatórios monetários) — só copiar o texto/label visual; qualquer novo número exibido (% MoM, meta) exige Regra Mestre (dupla confirmação) ANTES de exibir. Marcar "⚠️ toca valor".

### 3. Filtros / Tabs
- **Gap real:** mockup tem **tab "Cancelados"** a mais (vivo não tem); mockup mostra **contadores em todas as tabs** (`A pagar 2`, `Rascunhos 1`, `Em trânsito 1`) — vivo só conta na "Todas". Mockup mostra **pills de filtro nomeados** "Fornecedor · Local · Período · Pagamento" com tag "em breve"; vivo mostra um único pill genérico "Em breve".
- **Vivo-à-frente:** as tabs do vivo são filtros client-side reais sobre `rows.data` (`useMemo`); mockup idem mas sobre MOCK.
- **POR QUÊ:** mockup antecipa visualmente os filtros avançados da Wave 7; vivo deixou placeholder mínimo honesto. Tab "Cancelados" no vivo existe como `Stage='cancelada'` no type mas sem tab.
- **Esforço/risco:** **P** (adicionar tab Cancelados + contadores por tab + pills nomeados, tudo client-side/visual) · risco baixo, **não toca valor**. Contadores por tab são `.length` de array já em memória.

### 4. Tabela / Lista
- **Gap real:** mockup tem **2 colunas a mais** — `Itens` (qtd de itens, numérica) e `NF-e` (badge "✓ XML" / "—"). O vivo tem 7 colunas (sem Itens, sem NF-e). Mockup também ordena por essas colunas extras.
- **Vivo-à-frente:** vivo tem `AcoesDropdown` real (component próprio), sort server-side via `navigateWithFilters` (mockup é sort client-side em array). Vivo tem `payment_status` como coluna sortável "A pagar".
- **POR QUÊ:** coluna `Itens` precisa de `count` de linhas da compra; coluna `NF-e` precisa do flag de XML vinculado. **_pendente_** confirmar se `Row` payload do backend traz `items_count` e algum `has_xml`/`nfe_chave`. Hoje o `Row` interface não tem esses campos.
- **Esforço/risco:** **M** (estender backend `Row` + frontend colunas/visibilidade) · risco baixo–médio · **não toca valor** (Itens é contagem, NF-e é booleano). Adicionar aos `COLUMNS`/`DEFAULT_COL_VISIBILITY`.

### 5. Ações por linha (dropdown)
- **Gap real:** mockup tem **dropdown "Ações" com 9 opções** (Ver, Impressão, Editar, Excluir, Rótulos, Ver pagamentos, Reembolso de compra, Atualizar status, Elementos pendentes de notificação) — paridade declarada com Blade `/purchases`. O vivo usa `AcoesDropdown` em component separado (`./components/AcoesDropdown`) cujo conteúdo **não foi lido aqui** — **_pendente_** confirmar quantas das 9 opções o vivo já implementa.
- **Vivo-à-frente:** vivo separou em component reutilizável + recebe `status`/`paymentStatus` pra condicionar (mockup esconde "Reembolso" só em rascunho).
- **POR QUÊ:** mockup é a referência das 9 ações; o vivo pode estar com subset.
- **Esforço/risco:** **_pendente_** (depende do conteúdo de `AcoesDropdown.tsx`). Se faltar ações → **M**. ⚠️ "Reembolso de compra" e "Ver pagamentos" **tocam valor** — qualquer fluxo novo aqui é Tier 0 (Regra Mestre + permissões).

### 6. Drawer / Sheet — **maior gap**
- **Gap real:** mockup tem **drawer 5 tabs completo** (Resumo · Itens · Documentos · Pagamentos · Histórico) com: trilho FSM visual (`fsm-track` com 6 estágios done/now), tab **Itens** (tabela produto/qtd/custo/total/venda/**margem**), tab **Documentos** (NF-e SEFAZ status 100, chave 44 díg, botões XML/DANFE/Manifestar destinatário, manifesto destinatário), tab **Pagamentos** (linhas PIX/Boleto + "Registrar pagamento"/"Agendar no financeiro"), tab **Histórico** (timeline), e **footer de ação por estágio** (Enviar pedido → / Marcar em trânsito → / Pagar agora → etc). O vivo delega tudo a `./components/Drawer` (conteúdo **não lido aqui**) com só 2 tabs declaradas no type (`'resumo' | 'pagamentos'`) — **_pendente_** confirmar se o `Drawer.tsx` real já tem as 5 tabs ou só 2.
- **Vivo-à-frente:** vivo carrega `compra_detalhe` real via `<Deferred>` + skeleton; mockup é MOCK.
- **POR QUÊ:** o próprio cabeçalho do vivo diz "Drawer 5 tabs ... ficam pra Waves 6+". Forte indício de que o drawer vivo é **subset** do mockup (2 tabs vs 5).
- **Esforço/risco:** **G** (3 tabs novas + FSM track + footer de ações por estágio) · ⚠️ **toca valor FORTE** — tab Itens mostra custo/total/margem; tab Pagamentos registra pagamento; footer "Pagar agora" muda estado FSM + pode disparar baixa financeira. **Tier 0**: qualquer escrita aqui passa por FSM `ExecuteStageActionService` + Regra Mestre + permissões. Nesta fase só descrever o visual; aplicação cara em sessão limpa por tab.

---

## Veredito: **ADOTAR-PARCIAL**

O mockup NÃO está stale — é a referência mais completa, e o vivo é deliberadamente um subset "pin literal" de Wave 1-4 com Waves 6+ ainda abertas. Vários gaps já estavam roteirizados no próprio vivo. Adotar de forma incremental, do barato/visual ao caro/valor:

**Adotar (ordem sugerida):**
1. **(P, sem valor)** Tab "Cancelados" + contadores por tab + pills de filtro nomeados "em breve" — parte 3.
2. **(P, sem valor)** Migrar header `hd` → `os-page-h` (PageHeader canon) preservando crumbs/count/permissão/gate Wave 6 — parte 1.
3. **(M, sem valor)** Colunas `Itens` + `NF-e` na tabela (depende backend expor `items_count`/flag XML) — parte 4.
4. **(M, ⚠️ valor — só label visual)** Sub-linhas ricas dos KPIs — parte 2, **sem** inventar números (% MoM/meta) sem backend + Regra Mestre.
5. **(_pendente_)** Paridade do `AcoesDropdown` com as 9 ações — parte 5, conferir component antes.
6. **(G, ⚠️ valor FORTE — Tier 0)** Drawer 5 tabs completo (Itens/Documentos/Histórico + FSM track + footer ações por estágio) — parte 6, sessão limpa por tab, gate FSM + Regra Mestre.

**Bloqueios/pendências a resolver antes de aplicar:**
- `_pendente_` ler `Compras/components/Drawer.tsx` e `AcoesDropdown.tsx` (não lidos nesta fase) — define se gaps 5 e 6 são reais ou já cobertos.
- `_pendente_` confirmar payload backend: `kpis` tem variação/meta? `Row` tem `items_count`/`has_xml`?
- Tudo que toca valor/estoque (KPIs numéricos, tab Itens margem, Pagamentos, footer "Pagar agora") = Regra Mestre Tier 0 (dupla confirmação + antes→depois) + FSM gateway.
- `business_id` Tier 0 intocável em qualquer extensão de backend.
