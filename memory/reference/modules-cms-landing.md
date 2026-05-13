---
name: Modules/Cms é o landing/blog do oimpresso.com
description: A landing pública e o blog/CMS do oimpresso.com são servidos pelo Modules/Cms (não-existente no worktree atual, vive na produção). Rotas /, /c/page/{page}, /c/blogs, /c/blog/{slug}-{id}, /c/contact-us; backoffice em /cms/cms-page e /cms/site-details (auth+superadmin).
type: reference
---
**Onde mora a landing do oimpresso.com:** módulo Laravel `Modules/Cms` (Mini CMS para landing/blog/contato), ativo na produção mas **ausente neste worktree**. A landing genérica em inglês ("Automate your business management at very-Low cost") é renderizada pelo `CmsController@index` lendo `cms_pages`, NÃO pelo `resources/views/welcome.blade.php` (essa view só renderiza nome do app centralizado e está obsoleta).

**Rotas (Modules/Cms/Routes/web.php):**
- `/` → `CmsController@index` (homepage)
- `/c/page/{page}` → `CmsPageController@showPage` (páginas estáticas)
- `/c/blogs` → `CmsController@getBlogList`
- `/c/blog/{slug}-{id}` → `CmsController@viewBlog`
- `/c/contact-us` → `CmsController@contactUs`
- POST `/c/submit-contact-form` → contato
- Admin (auth+superadmin): `/cms/cms-page` (resource) + `/cms/site-details` (resource) + `/cms/install*`

**Views:** `Modules/Cms/Resources/views/{frontend/{layouts,pages,blogs}, page, layouts, components, settings}`.

**Tabelas:** `cms_pages`, `cms_page_metas`, `cms_site_details`.

**Como aplicar:**
- Para mudar copy/landing → editar via `/cms/cms-page` no superadmin OU mexer direto em `cms_pages` no DB.
- Para mudar tema/visual → `Modules/Cms/Resources/views/frontend/layouts/*.blade.php`.
- NÃO codar landing nova em `routes/web.php` ou `Modules/Site` — usar o que já existe.

**Drift worktree↔produção (CRÍTICO):** worktree local tem 18 módulos; produção tem 30+ (incluindo Cms, Accounting, Grow, IProduction, MemCofre, PontoWr2, Producao, Spreadsheet, Writebot, AssetManagement, AiAssistance). Antes de qualquer trabalho em landing/Cms, sincronizar worktree com `producao` (estado real do servidor) ou pull do `Modules/Cms` específico.
