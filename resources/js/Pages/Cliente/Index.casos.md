---
id: resources-js-pages-cliente-index-casos
casos: Lista de clientes + drawer 760 · /cliente
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — os KPIs reais, paginação/ordenação server-side e a aba "Outros" não mudam no refactor.
owner: wagner
last_run: "2026-10-14"
---

> ℹ️ **`last_run` 2026-09-06 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **2 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- resources/js/Pages/Cliente/Index.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

# Casos de Uso & Aceite — Lista de clientes

> Fase 2 (lanes do Cliente). Tela principal do módulo. UCs ancorados em `ClienteTypeOtherRouteTest` (render HTTP real), `ClientePaginacaoServerSideTest`, `ClienteSortServerSideTest`, `ClienteKpisServerSideTest`. Onde o teste é guard estrutural do query (a semântica É a corretude), o **número real** vira ✅ só com smoke — por isso os status são 🧪.
>
> ⚖️ **Onde estes UC rodam, e com que força** (medido 2026-07-27): lane `PHP / Pest (Cliente · MySQL)` — [`.github/workflows/cliente-pest.yml`](../../../../.github/workflows/cliente-pest.yml), criada 2026-07-27, **advisory** (não está em [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json): reprova visível, **não bloqueia merge**). **Antes dela** estes testes rodavam só no full-suite nightly do CT 100 e em **nenhuma** lane de PR — o `anchor-lint` do SPEC acusava `15 US com teste-que-cobre fora das lanes de JUnit (verde impossível)`. Onde as linhas abaixo dizem "passa no CI", leia-se **passava no nightly**; agora passa a valer também no PR. Ver [SDD Cliente §8](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado / prova de render = smoke) · ⬜ não verificado · ❌ quebrou.
>
> **Revalidação 2026-07-14 (consolidação DS):** a barra de abas migrou da versão inline hand-rolled pro componente canônico `PageHeaderTabs` em faixa própria (posição do protótipo + contadores). Os UCs abaixo são de **rota/query** (`?type=X`, paginação, ordenação, KPIs) — os `href` das abas são **idênticos**, então a navegação testada não muda. Status mantidos (🧪).

> ℹ️ **`last_run` 2026-07-15 → 2026-08-25 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` foi **comentário de ponteiro de design**: a linha citava um
> caminho de protótipo que não existe mais no repo, e passou a citar o vivo (ou a declarar a
> ausência com data). Zero JSX, zero handler, zero prop, zero copy alterada — `git diff --numstat`
> do PR não tem uma linha sequer fora de comentário. **Nenhum UC desta tela foi reexecutado nem
> revalidado**; o bump é o que o campo significa na prática (*trio reconciliado com a tela nesta
> data*), não afirmação de re-run.
---

> ℹ️ **`last_run` 2026-08-27 → 2026-09-06 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` (+ `_components/KpiStripClickable.tsx`) foi o atributo **`data-contract="<id>"`** no elemento raiz de
> região (header-identidade · kpis · filtros-busca · tabela-linhas · drawer-de-detalhe · perspectivas-fornecedor-funcionario-representante) — a âncora estável que o `<tela>.map.json` declara em `vivo.ancora`
> (`scripts/governance/design-code-map-check.mjs`). Atributo inerte: nenhum CSS o seleciona
> nestas telas (`grep -rn 'data-contract' resources/css/` → só `.arq-page [data-contract=abas|cabecalho]`)
> e nenhum JS de runtime o lê. Zero JSX estrutural, zero handler, zero prop, zero copy alterada.
> **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo significa na
> prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run.
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

## UC-CIDX-05 · A coluna "Última OS" mostra a venda mais recente do cliente, só da empresa dela
- **Persona:** Larissa — bate o olho na lista e quer saber qual foi o último pedido do cliente e abri-lo direto, sem entrar no drawer.
- **Aceite:** Dado um cliente com vendas · Quando a lista carrega · Então a coluna "Última OS" mostra o nº (`invoice_no`) da venda `type=sell` de **maior `transaction_date`** (desempate por id), com link para `/sells/{id}`; sem venda mostra "—". Venda do mesmo `contact_id` em **outra empresa** nunca entra (Tier 0).
- **Teste:** `tests/Feature/Cliente/ClienteUltimaOsTest.php` — maior data ≠ maior id · venda mais nova em outra empresa ignorada · sem venda = `null`.
- **Fonte:** protótipo `clientes-page.jsx` (coluna "Última OS", `.cli-lastos-link`) + decisão [W] 2026-10-14 (thread cliente/02). Não há CU no SDD para esta coluna.
- **Status: 🧪** — teste cita o UC; ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Clicar num KPI aplica o filtro correspondente (toggle 2x desativa)** — exige spec e2e do `KpiStripClickable`.
- **[BACKLOG] Abrir o drawer 760 ao clicar na linha + autosave on blur** — spec Playwright (o cadastro em si é coberto por Edit.casos UC-CEDI-04).
- **[BACKLOG] 6 dropdowns de filtro (Tipo/Status/UF/Tags/Sem compra/Saldo) filtram** — e2e.
- **[BACKLOG] A LISTAGEM não devolve cliente de outro tenant** — hoje o cross-tenant do módulo é coberto no `edit` (UC-CEDI-03), no mapa (UC-CMAP-02), no saldo (UC-CLED-04) e nos pagamentos (UC-CSHW-03), mas **não** em `GET /cliente`. O `ClienteKpisServerSideTest` cobre os KPIs por *source-grep*, não a lista. É a lacuna Tier 0 que sobra, e pesa mais aqui porque **`App\Contact` não tem global scope** (0 ocorrências de `addGlobalScope` em `app/Contact.php` — SDD §5.4.2): o isolamento é `where('business_id')` manual. É exatamente o que **US-CRM-080** pede; vira UC quando a US for atendida.

**Casos pedidos em 2026-10-23 — saldo a favor e limite de crédito**

- **[BACKLOG] Saldo a favor do cliente no drawer** `[V0]` — o cliente pode ter dinheiro a favor, vindo
  de devolução, troca, pagamento a mais ou adiantamento. Aceite: Dado um cliente com 120,00 a favor
  (80,00 de uma devolução e 40,00 de pagamento a mais), Quando abro o drawer, Então o resumo financeiro
  mostra "Crédito a favor 120,00" e a lista de onde veio cada parte, com a data. Cliente sem saldo a
  favor não mostra o bloco. Fonte: protótipo `cliente-drawer760.jsx` ("Créditos a favor — abatidos na
  próxima venda; o vendedor pode recusar"); o adiantamento lançado no cliente já existe.
  - Pedido: P-015
  - Âncora: Protótipo `cliente-drawer760.jsx`
  - Fazer: Somar o saldo a favor só da empresa e listar a origem de cada parte.
  - Hoje: part | Em parte | O protótipo já desenha; a tela não mostra
  - Comunicação visual: gray | Pouco | veio do vestuário
  - Clientes: NewPrint
  - Etiquetas: valor, part
- **[BACKLOG] Limite de crédito: quanto o cliente ainda pode gastar** `[V0]` — o limite é o quanto o
  cliente pode comprar a prazo. Aceite: Dado um cliente com limite de 2.000,00 e 1.500,00 em aberto,
  Quando abro o drawer, Então a aba Comercial mostra "Limite 2.000,00 · em aberto 1.500,00 · disponível
  500,00"; com o em aberto acima do limite, o disponível fica 0,00 e aparece "acima do limite". Cliente
  sem limite mostra "sem limite". Fonte: US-CRM-081; o campo já existe na aba Comercial
  (`limite_credito`) e o limite padrão em Configurações › Empresa.
  - Pedido: P-016
  - Âncora: US-CRM-081
  - Fazer: Calcular o em aberto e o disponível; mostrar ao lado do campo de limite.
  - Hoje: part | Em parte | O campo do limite existe na aba Comercial
  - Comunicação visual: part | Cliente empresa | venda a prazo
  - Clientes: Madereira Spilere
  - Etiquetas: valor, part, cv
- **[BACKLOG] Condição de pagamento do cliente: sinal e prazo** `[V0]` — Aceite: Dado a empresa com
  sinal mínimo de 30%, Quando defino para o cliente sinal de 50% e prazo de 28 dias na aba Comercial,
  Então as vendas dele vêm com essa condição, e o vendedor pode trocar na venda; cliente sem condição
  própria usa a da empresa. Fonte: DecoNetwork (condições por cliente e por empresa cliente);
  UC-FLX-28.
  - Pedido: P-025
  - Âncora: `UC-FLX-28` · DecoNetwork
  - Fazer: Campos de sinal e prazo na aba Comercial, com a condição da empresa como padrão.
  - Hoje: part | Em parte | O sinal mínimo é um só para a empresa
  - Comunicação visual: ok | Sim | cliente fixo com sinal diferente
  - Clientes: NewPrint, Sabor Brasil, ArtLaser
  - Etiquetas: valor, part, cv, decisao
- **[BACKLOG] "Faltam dados para nota" no cliente** — Aceite: Dado um cliente sem CPF ou CNPJ, sem
  endereço com município ou sem a indicação de contribuinte do ICMS, Então a lista e o drawer mostram
  "Faltam dados para nota" e quais; com os dados completos, o aviso some. Fonte: grupo do destinatário
  da NF-e.
  - Pedido: P-049
  - Âncora: Grupo do destinatário da nf-e
  - Fazer: Selo na lista e no drawer do cliente.
  - Hoje: miss | Não tem | 
  - Comunicação visual: part | Cliente empresa | nota para empresa
  - Clientes: Techpress, PraMoto
  - Etiquetas: miss, cv
- **[BACKLOG] Ficha do cliente com OS, devoluções, vendas e orçamentos, cada um com o total**
  (pedido de 2026-10-23) — Aceite: Dado um cliente com 4 vendas, 2 orçamentos, 1 OS e 1 devolução,
  Quando abro a ficha dele, Então vejo uma lista por tipo, cada uma com a quantidade e o valor total
  (vendas, orçamentos, OS e devoluções separados), e cada linha abre o documento. Tipo sem registro
  mostra zero. Hoje a aba Operações tem Extrato, Vendas, Pagamentos, Documentos, Pessoas,
  Assinaturas, Pontos e Auditoria; não há OS, orçamentos nem devoluções separados, nem total por tipo.
  O protótipo `cliente-drawer760.jsx` traz os totais "OS no total" e "Valor total".
  - Pedido: P-064
  - Âncora: Aba Operações (`OssTab`) · protótipo `cliente-drawer760.jsx`
  - Fazer: Separar OS, orçamentos e devoluções na aba Operações e somar cada tipo.
  - Hoje: part | Em parte | Operações tem Extrato, Vendas e Pagamentos; sem OS, orçamentos, devoluções nem total por tipo
  - Comunicação visual: ok | Sim | histórico do cliente recorrente
  - Etiquetas: part, cv, decisao
- **[BACKLOG] Campos obrigatórios no cadastro para a cobrança** (pedido de 2026-10-23) — Aceite: Dado a
  empresa que define quais dados do cliente são obrigatórios para cobrar (por exemplo CPF ou CNPJ,
  endereço completo e e-mail de cobrança), Quando gero um boleto ou uma cobrança para um cliente sem um
  deles, Então a cobrança não sai e a tela lista o que falta, com link para o cadastro; ao salvar o
  cadastro, a tela avisa os campos de cobrança vazios. Hoje o cadastro não tem essa regra; o caso
  "Faltam dados para nota" acima trata só da nota fiscal.
  - Pedido: P-085
  - Âncora: `Cliente/Index.casos.md`
  - Fazer: Lista de campos obrigatórios para cobrança por empresa, conferida ao gerar a cobrança e ao salvar o cliente.
  - Hoje: miss | Não tem | O cadastro não tem essa regra
  - Comunicação visual: part | Cliente empresa | cobrança por boleto
  - Clientes: Zoom
  - Etiquetas: miss, cv
- **[BACKLOG] Desconto para boleto pago antes do vencimento** (pedido de 2026-10-23) `[V0]` — Aceite:
  Dado um cliente com 2% de desconto para pagamento até 5 dias antes do vencimento, Quando o boleto dele
  é gerado, Então o boleto já sai com o desconto e a data limite, e o título recebe o desconto só se o
  pagamento chegar dentro do prazo; cliente sem desconto não muda. Hoje o cadastro não tem o campo e os
  boletos saem sem desconto. Mexe em valor: Regra Mestre.
  - Pedido: P-086
  - Âncora: `Cliente/Index.casos.md`
  - Fazer: Campo de desconto e prazo no cadastro do cliente, levado ao boleto e à baixa do título.
  - Hoje: miss | Não tem | A coluna `contacts.boleto_desconto_pontualidade_pct` existe no banco (migration de 2026-05-27), mas nenhuma tela mostra e nenhum boleto usa (medido em 2026-10-29); no Delphi o Sabor Brasil dá 3%, 5% ou 7% até o próprio vencimento
  - Comunicação visual: part | Cliente empresa | cliente a prazo com boleto
  - Clientes: Sabor Brasil
  - Etiquetas: valor, miss, cv, decisao

## Rastreabilidade (UC → CU do SDD → US do SPEC)

| UC | CU (SDD §6) | US (SPEC) |
|---|---|---|
| UC-CIDX-01 | CU-CLI-05 | — |
| UC-CIDX-02 | CU-CLI-06 | — |
| UC-CIDX-03 | CU-CLI-06 | — |
| UC-CIDX-04 | CU-CLI-07 · CU-CLI-11 | — |
| UC-CIDX-05 | — (protótipo + [W] 2026-10-14) | — |

> Coluna US vazia de propósito: a US-CRM-071 (KB-9.75 Slice A no Index) declara `ClienteIndexDrawer760CharterTest` como `**Testado em:**`, não estes 4. Vínculo por tema não conta.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter="ClienteTypeOtherRouteTest|ClientePaginacaoServerSideTest|ClienteSortServerSideTest|ClienteKpisServerSideTest"` no CT100.
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `Index.tsx` ou nos builders do `ContactController` (kpis/customers/sort).

## Trilha do tempo
- 2026-10-23 · [M+C] Backlog de saldo a favor e limite de crédito no drawer, sem id.
- 2026-10-23 · [M+C] Backlog: condição de pagamento do cliente (sinal e prazo), da comparação com concorrentes (DecoNetwork), sem id.
- 2026-10-23 · [M+C] Backlog: aviso de dados faltando para a nota, sem id.
- 2026-10-14 · [CL] thread cliente/02 — a tabela segue o protótipo: a foto entra na célula Cliente (sai a coluna própria) e nasce a coluna "Última OS" (UC-CIDX-05). Desta vez **é comportamento**: o `last_run` sobe por mudança real da tela. Os UCs 01–04 (rota/query) não mudam.
- 2026-07-27 · [CC] chip S-Cliente do passo 5 (agent `sdd-from-source`). **Nenhum UC reescrito** — os 4 seguem como estavam (o módulo já tinha contrato). Duas correções factuais no cabeçalho: (a) "lane ativa" era falso (não havia lane de PR pro Cliente; `ci-sqlite-pest.list` = 0 entradas), (b) a força do veredito agora está declarada (**advisory**, lida do `required-checks-baseline.json`, não deduzida). **+1 `[BACKLOG]`**: cross-tenant da listagem, a lacuna Tier 0 que sobra (US-CRM-080). Refs: [SDD Cliente](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md) §5.4.2/§8 · [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md).
- 2026-07-15 · [CC] revalidação (mudança cosmética DS) — troca de contorno/divisórias hardcoded (`oklch(0.93/0.9 0.004 90)`) por `var(--border)` dark-aware no header + KPI cards. Puramente visual (token de borda); NÃO altera rota/query/KPI backend — os 4 UCs (type=other, paginação, sort, KPIs) seguem válidos, status 🧪 mantidos. `last_run` bumpado (G-6). PR #4285.
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente), tela principal. 4 UCs ancorados em `ClienteTypeOtherRouteTest` / `ClientePaginacaoServerSideTest` / `ClienteSortServerSideTest` / `ClienteKpisServerSideTest`. Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · [ADR 0246](../../../../memory/decisions/0246-tipo-outros-default-migracoes-legacy.md) · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
