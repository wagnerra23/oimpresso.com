---
id: modules-cms-resources-js-pages-admin-content-index-charter
page: /cms/cms-page
component: Modules/Cms/Resources/js/Pages/Admin/Content/Index.tsx
related_prototype: PT-01 (índice) + PT-02 (drawer de detalhe)
owner: wagner
status: draft
last_validated: "2026-08-19"
parent_module: Cms
related_adrs: [93, 94, 149, 180, 190, 286, 300]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/cms/cms-page.jsx"
  blueprint_screenshot_approval: "pendente [W2]"
  derived_screens: [Index, Editor(drawer PT-02)]
  divergence_from_blueprint: "nenhuma — PT-01 lista + PT-02 drawer"
related_us: [US-CMS-001, US-CMS-002]
---

# Page Charter — /cms/cms-page (DRAFT)

> **Status:** draft — o F1 existe (`prototipo-ui/cowork/cms/cms-page.jsx`), a tela viva **não**: hoje `/cms/cms-page` ainda é Blade/AdminLTE (`cms::page.index|create|edit`) com TinyMCE, `fileinput`, `swal`, `toastr` e `$.ajax` no delete. Vira `live` quando [W2] aprovar o screenshot da tela Inertia em produção.
> Backend canon: `Modules\Cms\Http\Controllers\CmsPageController` (`index/create/store/edit/update/destroy`).
> Middleware da rota: `web · SetSessionData · auth · language · timezone · AdminSidebarMenu · superadmin · throttle:60,1`.

## Mission

Uma tela para **todo o conteúdo do site**: páginas, publicações do blog e depoimentos. Quem edita o site precisa ver o que está no ar, o que é rascunho, o que não tem descrição de busca, e mudar isso sem abrir o banco nem chamar o suporte.

## Goals — Features (faz)

- Índice único com abas por `type` (`page` · `blog` · `testimonial`) e contadores.
- Detalhe/edição em **drawer lateral (PT-02)**, com prévia do conteúdo em computador e celular.
- `meta_description` com medidor de 160 caracteres e o motivo escrito quando está vazia.
- Ordem por **arrasto** (grava `priority`), bloqueada com filtro/busca ativos e com o motivo à vista.
- Seleção múltipla → publicar / tirar do ar (BulkBar do DS).
- Blocos `feature` e `industry` editáveis item a item, **só** em `layout=home`.
- Sanitização **declarada**: a tela lista o que a publicação vai remover (script, iframe, atributo de evento).
- Aviso de link quebrado quando o título muda (o endereço público sai do título).

## Non-Goals — Features (NÃO faz)

- ❌ Editor rico (TinyMCE) — o corpo é HTML sanitizado; editor visual só com decisão de [W].
- ❌ Versionamento/rascunho paralelo do conteúdo (o histórico é leitura do activitylog, não *rollback*).
- ❌ Agendamento de publicação (`is_enabled` é booleano; data de publicação não existe no schema).
- ❌ Mexer no **layout** de página de sistema (`home`, `contact`) — layout é tema, não conteúdo.
- ❌ Multi-idioma / tradução de página.
- ❌ Escopo por negócio: `cms_pages` é global (sem `business_id`) — ADR 0093 §superadmin.

## UX targets

- Cabe em 1280px sem scroll horizontal com a sidebar de 256px aberta.
- Abrir o drawer < 150 ms (dado já em memória, sem nova requisição).
- Densidade de ERP: linha da tabela ≤ 44 px no modo confortável.

## Anti-hooks (NÃO faz automaticamente)

- ❌ Não grava em GET.
- ❌ Não publica conteúdo sozinho (nenhum `is_enabled=1` automático).
- ❌ Não dispara e-mail/notificação ao publicar.
- ❌ Não expõe enum cru (`page/blog/testimonial`, `is_enabled`) na interface.
- ❌ Não exclui página de sistema por rota alternativa (a recusa é do servidor, não só do botão).

## Regras de domínio (15)

| # | Regra | Onde vive |
|---|---|---|
| R1 | Um CRUD, três tipos, via `?type=` — **pretendido**. Vigente no `main`: `Store/UpdateCmsPageRequest` validam `in:page,post,banner`, então blog e depoimento são recusados na escrita (achado §3.b do pedido) | `CmsPageController::index/create/edit` + `Http/Requests/*` |
| R2 | Em `testimonial` o rótulo muda: título = nome de quem depõe, conteúdo = depoimento, imagem = foto | `page/create.blade.php` `@php` |
| R3 | `layout` preenchido = página de sistema: sem excluir | `page/index.blade.php` |
| R4 | Blocos `feature`/`industry` só em `layout=home` | `page/partials/*` + `CmsPageMeta` |
| R5 | Em `home`/`contact` o rótulo do corpo é **Descrição** | `page/edit.blade.php` |
| R6 | `priority` ordena asc; vazio no fim | `index()` |
| R7 | `meta_description` vazia recebe os 160 primeiros caracteres do conteúdo em texto puro | JS do create/edit |
| R8 | O conteúdo público é sanitizado no render (`dangerouslySetInnerHTML`) | `SiteContentService::sanitizeHtml` |
| R9 | Endereço sai do título; inexistente ⇒ 404 antes do render | `showPage()` |
| R10 | Trocar imagem de destaque apaga o arquivo anterior | `update()` `unlink()` |
| R11 | Create/update/delete logam com PII redactada; site-details tem activitylog | controller + `PiiRedactor` |
| R12 | Modo demo bloqueia escrita | `notAllowedInDemo()` |
| R14 | `meta_description` aceita até **500** caracteres no servidor; os 160 são regra de SEO da interface, não do banco | `StoreCmsPageRequest::rules()` |
| R15 | Imagem de destaque: arquivo de imagem, até **5 MB** | `StoreCmsPageRequest::rules()` |
| R13 | O painel exige `superadmin` + `throttle:60,1` | `Routes/web.php` |

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks (A1–A6)
- [ ] [W2] aprova screenshot 1280/1440 da tela Inertia em produção
- [ ] Confirmar que `destroy` recusa `layout` preenchido no servidor (UC-CMS-09)
- [ ] Confirmar a numeração PT do drawer com `prototipo-ui/PROTOCOL.md`
- [ ] **[W] decide o whitelist de `type`** — hoje `in:page,post,banner` contra o domínio `page|blog|testimonial` (pedido §3.b)

## Refs

- Backend: `Modules/Cms/Http/Controllers/CmsPageController.php`
- Blade que morre: `Modules/Cms/Resources/views/page/{index,create,edit}.blade.php`
- F1: `prototipo-ui/cowork/cms/cms-page.jsx` (+ `cms-extras.jsx`, `cms-page.css`)
- Precedente de onde a página mora: `Modules/Cms/Resources/js/Pages/Site/Page.tsx` + `Page.charter.md` (o módulo é dono das próprias páginas Inertia)
- Contrato: `prototipo-ui/contrato/cms-content.contract.json` (ADR 0286)
- Casos: `Index.casos.md`
