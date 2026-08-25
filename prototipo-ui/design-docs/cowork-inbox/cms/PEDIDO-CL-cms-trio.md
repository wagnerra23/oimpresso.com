# PEDIDO [CL] — módulo CMS: trio de prontidão + tradução do painel

> [CC] 2026-08-19 · destino [CL] (F3) · aprovação [W]
> Este diretório é **entrega pronta pra copiar**: charters, casos de uso, contratos de tela e o teste-âncora do painel. O F1 correspondente está em `prototipo-ui/cowork/cms/`.
> **Lido no `main` NESTE turno** (tree `60a9b423ac4a`, 2026-08-19T19:44Z): tree de `Modules/Cms/**`, `Modules/Cms/Resources/js/Pages/Site/Page.charter.md`, `package.json`, `.github/workflows/{contrato-de-tela,governance-script-tests,mv-metabolismo,design-memory-gate}.yml`, `resources/js/Pages/Site/`, `prototipo-ui/contrato/`. O detalhe do domínio (controllers, blades, requests) veio do espelho local — **duas divergências do espelho já corrigidas abaixo**.
> Contexto e ondas: `cowork-inbox/CMS-F1-2026-08-19.md`.

---

## 0. Mudou desde a primeira versão deste pedido (leitura do `main`, 19/08 19:44Z)

Se você já leu a versão anterior, **estes seis pontos mudaram** — o resto está igual:

| # | O que mudou | Impacto pra você |
|---|---|---|
| 1 | 🔴 **Achado novo §3.b:** `type` validado como `in:page,post,banner` contra o domínio `page\|blog\|testimonial` — blog e depoimento **não salvam** hoje | Entra na PR 3 e há UC + teste (UC-CMS-19) |
| 2 | **Destino dos charters trocou:** `Modules/Cms/Resources/js/Pages/Admin/**` (o módulo é dono das próprias páginas), não `resources/js/Pages/Cms/**` | Tabela do §1 |
| 3 | **Convenção de charter alinhada:** campo `related_prototype` (o gate `pt_declarado` casa `/PT-0[1-5]/`) + seções "(faz)/(NÃO faz)" + "Pendências antes de `status: live`" | Os dois charters já vêm assim |
| 4 | **Comandos:** os `npm run` reais (`contrato:check -- <arquivo>`, `casos:check`, `casos:results`, `pt:conformance:check`, `dominio:check`) e a flag `--contract` (não `--tela`) | §3 |
| 5 | **R14/R15 novas:** `meta_description` aceita 500 no servidor (160 é regra de SEO da UI) e `feature_image` até 5 MB | Charter de Content |
| 6 | **Retenção corrigida:** `leads_days 730` · `contacts_days 1095` · `activity_log_days 2555`, e **sem executor de purge** | Decisão 2 do §4 |

## 1. Copiar (caminho de destino no repo)

| Arquivo aqui | Destino no `main` |
|---|---|
| `Index.charter.md` | `Modules/Cms/Resources/js/Pages/Admin/Content/Index.charter.md` |
| `Index.casos.md` | `Modules/Cms/Resources/js/Pages/Admin/Content/Index.casos.md` |
| `SiteDetails.charter.md` | `Modules/Cms/Resources/js/Pages/Admin/SiteDetails/Index.charter.md` |
| `SiteDetails.casos.md` | `Modules/Cms/Resources/js/Pages/Admin/SiteDetails/Index.casos.md` |
| `cms-content.contract.json` | `prototipo-ui/contrato/cms-content.contract.json` |
| `cms-site-details.contract.json` | `prototipo-ui/contrato/cms-site-details.contract.json` |
| `CmsPainelAdminTest.php` | `Modules/Cms/Tests/Feature/CmsPainelAdminTest.php` |
| (do F1) `prototipo-ui/cowork/cms/cms-page.jsx` · `cms-extras.jsx` · `cms-page.css` | já é o caminho final do build F1 |

> **Correção de destino (leitura do `main`):** o módulo Cms é **dono das próprias páginas Inertia** — as públicas moram em `Modules/Cms/Resources/js/Pages/Site/{Home,Page,Blogs,BlogPost}.tsx` **com charter ao lado**; `resources/js/Pages/Site/` no `main` só tem `Login`/`Register`. O espelho local está desatualizado nesse ponto (ainda mostra Home/Page/Pricing na raiz). Logo o painel vai em `Modules/Cms/Resources/js/Pages/Admin/**`, não em `resources/js/Pages/Cms/**`.

`CmsSiteDetailsPainelTest.php` está **citado nos casos e não escrito** — depende dos helpers de sessão do repo; escrever junto da Onda 2.

## 2. Ordem de aplicação (uma PR por passo)

1. **PR 1 — trio, sem código de tela.** Copia charters + casos + contratos. `Index.charter.md` entra como `status: draft` (é o que é: a tela viva ainda é Blade). Roda `scripts/qa/prototipo-readiness.mjs` e o `contrato-de-tela.yml` em **advisory**.
2. **PR 2 — teste-âncora vermelho.** Copia `CmsPainelAdminTest.php`. Ele **reprova de propósito** em UC-CMS-01 (a rota devolve Blade), 05 (derivação está no JS da página), 09 (a rota aceita excluir página de sistema) e 08 (não há `page_meta` na prop). Esse vermelho é a lista de trabalho — não silencie com `markTestSkipped`.
3. **PR 3 — Onda 1: `Cms/Content`.** `index/create/edit` viram `Inertia::render`; `store/update` recebem a derivação da `meta_description` (sai do JS, vira servidor); `destroy` passa a recusar `layout` preenchido. Verde: UC-01, 04, 05, 07, 08, 09, 10.
4. **PR 4 — Onda 2: `Cms/SiteDetails`.** Um POST, 8 seções, chaves verbatim. Escrever `CmsSiteDetailsPainelTest`.
5. **PR 5 — Onda 3/4: Leads e Módulo.** Só depois da decisão de [W] no §4.
6. **PR 6 — promover.** Charters para `status: live` com screenshot aprovado por [W2]; contrato de tela vira **required**.

## 3. Comandos

```bash
# trio / prontidão (nomes conferidos no package.json e nos workflows do main)
node scripts/qa/prototipo-readiness.mjs --json     # mv-metabolismo.yml:107
npm run casos:check                                # guarda de cobertura de casos
npm run casos:results                              # 🧪 vira ✅ no manifesto

# contrato de tela (advisory até PR 6) — flag --contract, via npm script
npm run contrato:check -- prototipo-ui/contrato/cms-content.contract.json
npm run contrato:check -- prototipo-ui/contrato/cms-site-details.contract.json
npm run contrato:preflight
npm run contrato:selftest

# conformidade de idioma/domínio (a copy do painel é PT-BR)
npm run pt:conformance:check
npm run dominio:check

# teste do painel (CT100 — nunca local/Hostinger)
docker exec oimpresso-staging php artisan test --filter=CmsPainelAdminTest
docker exec oimpresso-staging php artisan test Modules/Cms/Tests/Feature

# saúde e importação do módulo
docker exec oimpresso-staging php artisan cms:health --detail
docker exec oimpresso-staging php artisan cms:import-wp-officeimpresso --dry-run --limit=5

# guarda de export do Cowork (design-memory-gate.yml:141)
node scripts/governance/cowork-ssot-guard.mjs
```

Conferido no `main`: `scripts/contrato-de-tela.mjs` (flag `--contract`, **não** `--tela`) é chamado por `contrato-de-tela.yml:99-140`; `scripts/qa/prototipo-readiness.mjs` por `mv-metabolismo.yml:107`; `scripts/governance/cowork-ssot-guard.mjs` por `design-memory-gate.yml:141`. Os `npm run` acima estão no `package.json` do `main`. Não crie script novo.

> Nota de método: `github_get_tree` não lista `.mjs` (filtro de "importable"), então a ausência dos scripts na árvore **não** é ausência no repo — confirmei por `github_search_code` nos workflows.

## 3.b 🔴 ACHADO BLOQUEANTE no `main` — a validação recusa blog e depoimento

Lido neste turno: `Modules/Cms/Http/Requests/StoreCmsPageRequest.php:31` e `UpdateCmsPageRequest.php:37` validam

```php
'type' => ['nullable', 'string', 'in:page,post,banner'],
```

mas o domínio real do módulo é **`page | blog | testimonial`**:

| Onde | Valor usado |
|---|---|
| `Resources/views/layouts/nav.blade.php` | `page` · `blog` · `testimonial` |
| `CmsController` (home, blogs, post) | `'testimonial'` (linhas 87/112) e `'blog'` (203/213/241/261) |
| `CmsPageRepository:42` | docblock `page|post|banner|blog|testimonial` (admite os cinco) |
| `StoreBlogPostRequest:53` | força `'blog'` |
| `CmsServiceProvider:28` | `getPagesCount('blog')` no contador do menu |
| migração de dados padrão | `page` e `testimonial` |

**Consequência:** salvar uma publicação de blog ou um depoimento pelo painel cai em erro de validação com a mensagem *"Tipo inválido. Use page, post ou banner."* — e `post`/`banner` não são lidos por nenhuma consulta do módulo. O caminho que funciona hoje é o `StoreBlogPostRequest` (que ignora o input e força `blog`), ou seja: **o CRUD do painel só está confiável para `page`**.

**Correção proposta (entra na PR 3):** `'type' => ['nullable', 'string', 'in:page,blog,testimonial']` nas duas requests, mensagem `type.in` reescrita ("Tipo inválido. Use página, blog ou depoimento."), e um teste que trava os três valores — está em `CmsPainelAdminTest::test_store_aceita_os_tres_tipos_do_dominio` (UC-CMS-19). Se `post`/`banner` forem reserva de futuro, então o whitelist deve ser a **união** — mas aí é decisão de [W], não conserto.

**Efeito colateral do achado:** minha regra R1 do charter ("um CRUD, três tipos") descreve o comportamento **pretendido**, não o vigente. O charter está anotado.

## 4. Decisões que precisam de [W] antes da Onda 3

1. **Persistência de lead.** Hoje `CmsLeadService` só notifica e loga — o lead não é gravado em lugar nenhum. Tabela `cms_leads` no módulo Cms, ou lead direto no `Modules/Crm` (uma origem a mais no funil)? **Minha recomendação: no Crm**, com `origin=site` — evita duas caixas de entrada de lead.
2. **Retenção do lead.** Corrigido para o canon do repo: `Modules/Cms/Config/retention.php` declara `leads_days = 730` (~24 meses, `CMS_RETENTION_LEADS_DAYS`), `contacts_days = 1095` e `activity_log_days = 2555`. A tela já diz 24 meses. Falta decidir **quem aplica o purge** — o arquivo é política declarada, sem comando agendado (o RUNBOOK previsto não existe).
3. **Editor rico.** O F1 aposentou o TinyMCE (HTML + prévia). Se quem edita o site não for técnico, isso volta — decisão sua.
4. **`post` e `banner` existem?** Só aparecem no whitelist das requests e no docblock do Repository — nenhuma consulta os lê. Remover do whitelist (minha recomendação) ou são reserva declarada?
5. **Endereço público derivado do título.** É a regra atual (R9) e é frágil: renomear quebra link. Vale um campo `slug` estável? Se sim, é migração + redirect 301 — vira ADR.

## 5. Pedidos de DS (senão a Onda 1 improvisa)

1. `StatusBadge kind="cms"` — `publicada · rascunho · pagina-sistema · sem-descricao`.
2. `Input`/`Textarea` com `ref` + acesso à seleção (inserir tag no cursor) — pedido já aberto por `notificacoes-page.jsx`.
3. Reordenação por arrasto (`SortableList` ou `DataTablePro reorderable`) — `priority` aparece em Cms, produtos e FAQ.
4. Prévia de dispositivo (a do editor é bespoke: `.cms-ed-prev.mob`).

## 6. O que eu NÃO fiz (e não vou dizer que fiz)

- Não commitei, não abri branch, não abri PR — as tools de git aqui são leitura.
- Não rodei nenhum dos comandos acima: o teste-âncora foi **escrito, não executado**. Os helpers de sessão (`actingAsSuperadmin`) estão marcados com `TODO [CL]` para apontar pro helper canônico do repo.
- Li o `main` neste turno **para caminhos, convenção de charter, nomes de gate e agora o domínio da escrita** (tree `60a9b423ac4a`): `CmsPageController`, `SettingsController`, `StoreCmsPageRequest`, `UpdateCmsPageRequest` + busca por `testimonial`/`blog` em `Modules/Cms` (85 ocorrências) — foi assim que o achado §3.b apareceu. **Não** reli no `main`: as blades de `page/*` e `settings/*`, `SubmitContactFormRequest`, `SiteContentService` e `retention.php` — as regras R2, R5, R7, R8, R13 e os UC 06/15/16/17 seguem vindo do espelho local. Se o `main` divergir num ponto de domínio, o `main` manda.
- Convenção de charter alinhada ao `main` nesta rodada: campo `related_prototype` (o gate `pt_declarado` casa `/PT-0[1-5]/`), seções "Goals — Features (faz)" / "Non-Goals — Features (NÃO faz)" / "UX targets" / "Anti-hooks (NÃO faz automaticamente)" e bloco "Pendências antes de `status: live`" — como em `Modules/Cms/Resources/js/Pages/Site/Page.charter.md`.
