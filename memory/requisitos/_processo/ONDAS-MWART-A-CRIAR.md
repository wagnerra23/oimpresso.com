---
id: requisitos-processo-ondas-mwart-a-criar
title: "Ondas MWART — os 23 mockups A_CRIAR do bundle 5023b274 (planejamento, não execução)"
owner: W
status: proposto
created: "2026-09-06"
related_adrs: ["0104-processo-mwart-canonico-unico-caminho", "0277-rota-migracao-blade-ondas-completude", "0121-oimpresso-modular-especializado-por-vertical", "0264-governanca-executavel-trio-dominio-e2e"]
---

# Ondas MWART — os 23 mockups `A_CRIAR` (bundle 5023b274)

> **Dono do tema:** [ROADMAP-ONDAS-BLADE-ADVERSARIOS.md](../Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md) ([ADR 0277](../../decisions/0277-rota-migracao-blade-ondas-completude.md) — 10 ondas do backbone Blade, `US-MWART-004…013`). Este doc **não abre rota paralela**: é o **delta por mockup** do bundle Cowork `5023b274` (retrato `scripts/design-sync/state/application-report.json`, `lifecycleState: to-create` = 23), e cada linha aponta pra onda da 0277 (ou pro módulo nWidart, que a 0277 não cobre). O *como* migrar segue [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (F1→F5) e a tela nasce por `node scripts/governance/criar-tela.mjs <Mod/Tela> <PT-0X>` (trio charter+casos+e2e por construção).
>
> **Como foi medido (2026-09-06, `origin/main` 80bc4ef8b9):** `A_CRIAR` de [`prototipo-ui/detectar-telas.mjs`](../../../prototipo-ui/detectar-telas.mjs) cruzado com `git grep` em `routes/*.php` + `Modules/*/Routes/*.php`, `Inertia::render|return view(` em cada controller alvo, `git ls-files` das views Blade, `node prototipo-ui/ancora.mjs` nos gêmeos vivos, PEDIDO-PARA-CODE em `prototipo-ui/design-docs/cowork-inbox/`, SPECs por módulo e o topo de [`08-handoff.md`](../../08-handoff.md). Não repete o comentário do `A_CRIAR`; onde ele diverge do medido, a linha diz.
>
> **Regra de leitura:** `⛔ [W]` = decisão do dono (Non-Goal de charter, fronteira, tela que NÃO deve nascer). Tamanho P/M/G = views Blade + rotas da família (fator [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)). Sem valores monetários aqui (Tier 0).

## Status vivo
**status:** proposto
**owner:** W
**criado:** 2026-09-06 · **reviewed_at:** 2026-09-06 · **próxima-revisão:** 2026-10-06
**cycle:** — · **execução:** parent_plan=ondas-mwart-a-criar
**gate-de-saída (DoD):** as 4 ondas viraram tasks MCP aprovadas por [W] (ou foram recusadas com motivo) e os 7 gêmeos vivos saíram do `A_CRIAR` (ALIAS/charter ou aposentadoria do mockup).
**kill-condition:** [W] decidir que o bundle 5023b274 não é fonte de design (lápide §5 2026-08-28: hub que se declara porte reverso não é design aprovado).

## §1 · O que o `A_CRIAR` chama de "a-criar" e não é — 7 gêmeos vivos

O guard estrutural do `detectar-telas.mjs` só cruza o *stem* do mockup com o nome da pasta em `Pages/`; estes 7 escapam porque a tela viva mora noutra pasta. **Nenhum vira onda.** Cada um é uma decisão `⛔ [W]` de âncora, não de construção.

| Mockup | Tela VIVA (medida) | Rota hoje | Decisão ⛔ [W] |
|---|---|---|---|
| `boletos-page.jsx` | `resources/js/Pages/Financeiro/Cobranca/Index.tsx` (charter `live`) | `GET /boletos` → **301** `/financeiro/cobranca` (`Modules/Financeiro/Routes/web.php:170`) | mockup é material F1 anterior ("Boleto + Contas/Caixa Inter"); mover pro `ALIAS` → `Financeiro/Cobranca/Index` ou aposentar do bundle |
| `pg-cobranca-page.jsx` | idem — cabeçalho diz "Substitui /financeiro/boletos" | idem | é ancestral do `related_prototype` já declarado (`prototipos/payment-gateway-ui/cobranca-page.jsx`); mesma decisão acima |
| `perfil-page.jsx` | `resources/js/Pages/User/Perfil.tsx` (`/perfil`, charter `draft`, `related_prototype: n/a`) | `/user/profile` Blade **intacto** (`UserController@getProfile`, paridade em [User/perfil-parity.md](../User/perfil-parity.md)) | promover o mockup a `related_prototype` do charter (é redesign fiel do Blade) ou manter `n/a`; cutover de `/user/profile` = Onda 7/9 da 0277 |
| `orc-page.jsx` | `resources/js/Pages/Sells/Index.tsx` (vista *quotations*) | `/sells/quotations` já é **dual** (`SellController@getQuotations` responde Inertia sob `X-Inertia`) | Onda 1 da 0277 ([ONDA-1-VENDAS-PDV-CAIXA-PLANO](../Mwart/ONDA-1-VENDAS-PDV-CAIXA-PLANO.md), em execução) — mockup vira fonte de design da vista, não tela nova |
| `os-page.jsx` | `resources/js/Pages/Repair/JobSheet/{Index,Show}.tsx` (`bundle_source: repair-page.jsx`) | `job-sheet` dual (Inertia + Blade); `US-REPA-004` `_parcial_` **em voo** (brief: "Listar e filtrar as OS abertas") | o mockup é OS de **gráfica** (hub "OS" ≠ "Assistência técnica" no shell Cowork). Repair é infra compartilhada ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)): 1 OS com vocabulário por vertical, ou tela ComVis própria? Sem esta decisão a tela não nasce |
| `producao-page.jsx` | `resources/js/Pages/Repair/ProducaoOficina/Index.tsx` (PT-05, `bundle_source: repair-page.jsx`) | `/repair/producao-oficina` Inertia | mesma decisão do `os-page`; equipamentos de gráfica (impressoras/plotter) são `US-COMVIS-003` / `US-PCP-004` (workstations), não Kanban novo |
| `equipe-page.jsx` | `resources/js/Pages/Essentials/Messages/Index.tsx` (mural, `bundle_source: essenciais-page.jsx`) | `POST essentials/messages` (mural único) | mockup propõe canais + DMs — **não existe backend de canal**. Evoluir o mural ou abrir tela nova é decisão de produto ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |

## §2 · Em voo com dono — 3 (não duplicar)

| Mockup | Dono vivo | Estado medido |
|---|---|---|
| `connector-page.jsx` | `US-CONN-014` **[EPIC] Migrar a tela Conector (API) de Blade para Inertia** + `US-CONN-015…020` (F5 cutover) — task MCP `[w] @ Connector` | Blade `connector::clients.index` (2 views, 10 rotas). Fica no EPIC |
| `documentacao-page.jsx` | `US-DOC-001` `doing` (F1 feita, F2/F3 não) — task MCP `wagner @ Documentacao` | `DocumentacaoController` 100% Blade (5 views, 11 rotas). Fica na US |
| `programa-doc-page.jsx` | `US-DOC-002` `todo` — mesma US-mãe | `/documentacao/programa` **já tem rota** (`web.php:1121`, view `documentacao.programa`) — o `A_CRIAR` diz "sem tela"; está desatualizado. Vai junto da `US-DOC-001` |

## §3 · As 13 que são a-criar de verdade — linha por mockup

| Mockup | Tela-alvo proposta | Blade/rota que substitui (medido) | PT | Tam. | Dependências | Em voo? |
|---|---|---|---|---|---|---|
| `funcoes-page.jsx` | `resources/js/Pages/Roles/{Index,Form}.tsx` | `resource('roles')` → `RoleController` (`role.index/create/edit`, 4 views) | PT-01 + editor tela cheia (PT-02) | M | backend no `main` (#5962 Tier 0, #5964 catálogo fechado de permissões, #5971 guarda de exclusão); F3 estava bloqueada por transporte (handoff 2026-08-20 21:20), **destravada** pelo bundle 5023b274 (`transportComplete: true`) | PEDIDO acessos PR-6 (dono) |
| `usuarios-page.jsx` | `resources/js/Pages/Users/Index.tsx` (drawer PT-02 + convite) | `resource('users')` → `ManageUserController` (`manage_user.*`, 4 views) + `/sign-in-as-user/{id}` | PT-01 | M | permissões `user.view/create`; sign-in-as-user é ação sensível (⛔ [W] entra no drawer ou fica Blade?) | Onda 7 (K) da 0277; PEDIDO acessos **não** cobre `/users` |
| `prefs-page.jsx` | `resources/js/Pages/User/Preferencias.tsx` | **não existe tela**: só `POST /user/preferences/{theme,sidebar}` (`web.php:1095-1100`) | PT-02 | P | mockup se declara "proposta, não espelho". Abas Identidade/Fiscal/Numeração **duplicam** `business/settings` ⛔ [W] fronteira prefs (usuário) × configurações (empresa) | PEDIDO acessos PR-8 ("/prefs + os três relógios") |
| `voz-do-cliente-page.jsx` | `resources/js/Pages/VozDoCliente/Index.tsx` | `/voz-do-cliente` → `SinalController@index` (`vozdocliente::caixa`, 1 view, 7 rotas) | PT-01 | P | SPEC sem US (`charter-us-lint` exige US antes do charter); triagem MANUAL é Non-Goal ⛔ [W] | não |
| `catalogo-qr-page.jsx` | `resources/js/Pages/ProductCatalogue/Qr.tsx` | `product-catalogue/catalogue-qr` → `ProductCatalogueController@generateQr` (Blade, 8 views) | PT-02 | P | **dois donos** da mesma rota: `Modules/Officeimpresso/Routes/web.php:31` tem `catalogue-qr` duplicado ⛔ [W] qual sobrevive; ghost do hub Vendas (ADR 0180), DataController no-op | não |
| `planilhas-page.jsx` | `resources/js/Pages/Spreadsheet/Index.tsx` (+ editor) | `spreadsheet/sheets` (`SpreadsheetController`, 7 views, 10 rotas) | PT-01 (árvore pastas) + editor bespoke | M | Luckysheet/LuckyExcel só no Blade — trazer pro Vite é dependência nova (ADR); "Download" não implementado no repo | SPEC `US-SHEET-001…005` |
| `patrimonio-page.jsx` | `resources/js/Pages/AssetManagement/{Index,Dashboard}.tsx` + drawer | `/asset/{assets,allocation,revocation,asset-maintenance,settings,dashboard}` (12 rotas, 17 views) | PT-01 + PT-04 | G | backend `US-ASSET-001…008` done; módulo por pacote (Camada 1); valor bruto/residual é **leitura**, não cálculo de venda | não |
| `notificacoes-page.jsx` | `resources/js/Pages/NotificationTemplates/Index.tsx` | `notification-templates` (`only index,store`) + `notification-templates/test` (3 views) | PT-02 (rail + editor) | M | editor HTML (TinyMCE no Blade) + validação de tags + GSM-7; `{business_logo}` só e-mail | Onda 7 (K) |
| `configuracoes-page.jsx` | `resources/js/Pages/Business/Settings.tsx` (16 seções) | `/business/settings` → `BusinessController@getBusinessSettings` (`business.settings` + 19 partials = 40 views) + família `business-location`/`invoice-*`/`tax-rates`/`printers` (10 rotas) | PT-02 multi-seção com busca | G | permissão `business_settings.access`; **Tier 0 valor**: moeda, casas decimais, impostos padrão alimentam `TransactionUtil` → regra mestre (dupla prova + antes→depois) | Onda 7 (K) — `US-MWART-010` |
| `cms-page.jsx` | `resources/js/Pages/Cms/Pages/{Index,Create,Edit}.tsx` + `SiteDetails.tsx` (path já declarado na `US-CMS-004`) | `/cms/cms-page` (`CmsPageController`) + `/cms/site-details` (`SettingsController`) — middleware **superadmin**, 45 views | PT-01 + PT-02 (8 abas) | G | só superadmin; sanitização de HTML colado; `Pages/Site/*` é o site público (outra tela) | `US-CMS-004` `_pendente_` |
| `comissionados-page.jsx` | `resources/js/Pages/CommissionAgents/Index.tsx` + drawer | `resource('sales-commission-agents')` → `SalesCommissionAgentController` (3 views) | PT-01 + PT-02 | P | backend #5970 (guarda de exclusão) no `main`; **Tier 0 valor**: `cmmsn_percent` passa por `num_uf` (classe do incidente 2026-06-05) | PEDIDO acessos PR-7 |
| `comissoes-page.jsx` | `resources/js/Pages/CommissionAgents/Apuracao.tsx` | **não existe backend** — Comissao SPEC `US-COMM-001…014` todas `_pendente_` (feature-wish ADR 0151, pasta ausente); só o relatório legado `reports/sales-representative-total-commission` | PT-01 + fechamento | G | **Tier 0 valor**: apuração gera título a pagar no Financeiro (`US-COMM-007`); aprovado por [W] 2026-08-19 no cabeçalho do mockup — ⛔ [W] confirma escopo mínimo (faixas/metas ficam fora?) | PEDIDO acessos PR-7 ("apuração") |
| `relatorios-page.jsx` | `resources/js/Pages/Reports/{Index,Relatorio}.tsx` (hub + tela) | `/reports/*` → `ReportController` (44 métodos, 42 rotas, 50 views) | PT-01 (hub) + PT-01/PT-04 por relatório | G | **represa** (0277 Onda 8, `US-MWART-011` `blocked_by` 004…010): relatório que lê domínio não migrado mente; grupo "Gráfica" do mockup é tela NOVA sem backend ⛔ [W] | Onda 8 |

## §4 · Ondas propostas (3–5 telas · afinidade + risco · valor por último)

Cada onda = **1 task MCP proposta** (não criada — batch pra [W] aprovar no PR). Dentro da onda, cada tela roda F1→F5 da 0104 em PRs ≤300 linhas; a onda só fecha pelo contrato da 0277 (route Blade morto ou 302).

| Onda | Telas | Por que juntas | Risco / gate |
|---|---|---|---|
| **A · Acessos & conta** | `funcoes` · `usuarios` · `prefs` ⛔ | mesmo PEDIDO (grupo Usuários), backend já no `main`, transporte destravado | baixo (permissão, não valor). Gate: catálogo fechado #5964 respeitado; `prefs` só entra depois da fronteira ⛔ [W] |
| **B · Satélites só-Blade** | `voz-do-cliente` · `catalogo-qr` · `planilhas` · `patrimonio` | módulos nWidart pequenos, sem valor de venda, fora da 0277 | baixo/médio. Gate: `voz` precisa US no SPEC; `catalogo-qr` precisa a decisão dos 2 donos; `planilhas` pode exigir ADR de dependência JS |
| **C · Configuração & site** | `notificacoes` · `configuracoes` · `cms` | Onda 7 (K) da 0277 + admin superadmin; raramente visitadas, alto impacto | **médio/alto**: `configuracoes` toca moeda/imposto (regra mestre valor); `cms` é superadmin-only |
| **D · Valor (Tier 0)** | `comissionados` · `comissoes` · `relatorios` | tudo que calcula ou exibe valor apurado; `comissoes` precisa do módulo nascer; `relatorios` é represa | **alto**: dupla prova + antes→depois em cada PR; `relatorios` só depois de A–C e das ondas 1–6 da 0277 |

**Fora de onda:** os 7 gêmeos vivos (§1 — decisão de âncora) e os 3 em voo (§2 — já têm US/EPIC).

## §5 · Decisões do dono antes de qualquer task (⛔ [W])

1. `A_CRIAR` está **errado em 7 entradas** (§1): aposentar `boletos`/`pg-cobranca` (ALIAS → Cobranca), `perfil` (âncora do charter), `orc` (vista quotations), `os`/`producao` (1 OS compartilhada vs ComVis), `equipe` (backend de canal não existe). Só depois disso o `status.mjs --check-mapping` deixa de mentir "a-criar".
2. **Fronteira `prefs` × `configuracoes`** — o mockup de prefs traz seções de empresa. Sem essa linha, nascem duas telas pro mesmo campo.
3. **Comissão**: confirmar que o escopo mínimo é cadastro + apuração + título a pagar (`US-COMM-001/002/007`), sem faixas/metas (`US-COMM-005/006`).
4. **Catálogo QR**: `ProductCatalogue` ou `Officeimpresso` — um dos dois roteadores morre.
5. **Relatórios "Gráfica"** (m², bobina, lucro por OS, retrabalho): telas novas sem backend — entram ou ficam como feature-wish ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))?
6. Non-Goals dos charters (A–D) — só [W] preenche ([how-trabalhar §Pedido de tela](../../how-trabalhar.md)); nenhum foi inferido aqui.

## §6 · O que este doc NÃO faz

Não cria task, charter, casos nem `.tsx`; não regrava `A_CRIAR`; não promove nada a required. Tudo isso é execução, e execução nasce de aprovação [W] no PR que carrega este doc.

---
*[CC] 2026-09-06 · medido em `origin/main` 80bc4ef8b9 · dono do tema: ROADMAP-ONDAS-BLADE-ADVERSARIOS (ADR 0277).*
