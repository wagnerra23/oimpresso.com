---
id: modules-cms-resources-js-pages-admin-sitedetails-index-charter
page: /cms/site-details
component: Modules/Cms/Resources/js/Pages/Admin/SiteDetails/Index.tsx
related_prototype: PT-03 (formulário) — conferir a numeração canônica em prototipo-ui/PROTOCOL.md antes de aplicar
owner: wagner
status: draft
last_validated: "2026-08-19"
parent_module: Cms
related_adrs: [93, 94, 190, 286]
tier: B
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/cms/cms-page.jsx (aba Detalhes do site)"
  blueprint_screenshot_approval: "pendente [W2]"
  derived_screens: [Index]
  divergence_from_blueprint: "rail de seções + um único POST, divergente do PT-01 lista (página de configuração)"
related_us: [US-CMS-003]
---

# Page Charter — /cms/site-details (DRAFT)

> **Status:** draft — hoje é `cms::settings.index` (Blade AdminLTE com `pos-tab-container` e 8 `partials`). Vira `live` quando [W2] aprovar o screenshot.
> Backend canon: `Modules\Cms\Http\Controllers\SettingsController` + `CmsSiteDetail::createOrUpdateSiteDetails()`.

## Mission

Um lugar para os dados que o site público exibe e usa: marca, contatos, redes, números, perguntas frequentes, chat, medição e os textos dos botões do tema. Sem tocar em nada do sistema por dentro.

## Goals — Features (faz)

- Rail de 8 seções (Aplicação · Contato · Redes sociais · Estatísticas · Perguntas frequentes · Atendimento por chat · Integrações · Botões) com **um único** POST.
- Chaves preservadas exatamente como o Model espera: `logo`, `notifiable_email`, `contact_us[]`, `mail_us[]`, `follow_us{}`, `statistics{}`, `faqs[]`, `chat{}`, `chat_widget`, `google_analytics`, `fb_pixel`, `custom_js`, `custom_css`, `meta_tags`, `btns{}`.
- Barra de salvar fixa, dizendo que a mudança vale pro site público.
- Campo vazio = seção escondida no site (dito na interface, não adivinhado).

## Non-Goals — Features (NÃO faz)

- ❌ Editar o **layout**/tema do site.
- ❌ Configuração por negócio: `cms_site_details` é global hoje (US-CMS-003 é quem traz `business_id`).
- ❌ Validar se o código de medição existe de verdade no Google/Meta.
- ❌ Executar `custom_js`/`custom_css` dentro do painel (só publica no site).

## UX targets

- Cabe em 1280px sem scroll horizontal; em ≤980px o rail vira uma fileira de uma linha com rolagem lateral (sem sobrepor o painel).
- Trocar de seção não perde o que foi digitado nas outras (um formulário, um POST).

## Anti-hooks (NÃO faz automaticamente)

- ❌ Não grava em GET.
- ❌ Não envia e-mail de teste sozinho.
- ❌ Não injeta script de terceiro no **painel** — só no site.

## Regras de domínio (6)

| # | Regra | Onde vive |
|---|---|---|
| S1 | Cada chave é uma linha em `cms_site_details` com `site_value` em JSON | `createOrUpdateSiteDetails()` |
| S2 | `logo_url`/`logo_path` são atributos derivados de `uploads/cms/` | `CmsSiteDetail::getLogoUrlAttribute()` |
| S3 | Alterações são auditadas (activitylog, `logOnlyDirty`) — são settings com PII de admin | `LogsActivity` |
| S4 | `notifiable_email` é o destinatário dos leads do formulário público | `CmsLeadService::capturar()` |
| S5 | `contact_us[].num` valida 10 dígitos no legado — revisar pro padrão BR (10–11 + máscara) | `settings/partials/contact_us` |
| S6 | Painel exige `superadmin` + `throttle:60,1` | `Routes/web.php` |

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks
- [ ] [W2] aprova screenshot 1280/1440
- [ ] Confirmar a validação de telefone BR (S5) e a numeração PT

## Refs

- Backend: `Modules/Cms/Http/Controllers/SettingsController.php`, `Modules/Cms/Entities/CmsSiteDetail.php`
- Blade que morre: `Modules/Cms/Resources/views/settings/index.blade.php` + `partials/*`
- Contrato: `prototipo-ui/contrato/cms-site-details.contract.json`
- Casos: `Index.casos.md` (seção Detalhes do site)
