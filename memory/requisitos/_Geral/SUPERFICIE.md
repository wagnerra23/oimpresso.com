---
name: "SUPERFÍCIE — _Geral"
description: "Índice GERADO dos artefatos do módulo _Geral reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: _Geral
---

# 🗺️ Superfície de código — _Geral

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs _Geral --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** a porta geral para componentes, layouts e templates herdáveis por mais de um módulo. A lista é derivada das raízes compartilhadas declaradas em `module-surface.mjs::RAIZES_GERAIS`. **O que NÃO é:** autorização para importar qualquer item sem verificar contrato, status e consumidores; para decidir reuso, consulte também `node scripts/reuse-index.mjs "<símbolo ou intenção>"` e o registry do Design System.

**Total mapeado:** 127 arquivos em 5 papéis.

## Componentes compartilhados (React) — 87

- [CommandPalette.tsx](../../../resources/js/Components/CommandPalette.tsx)
- [Icon.tsx](../../../resources/js/Components/Icon.tsx)
- [MentionInput.tsx](../../../resources/js/Components/MentionInput.tsx)
- [FiscalStatusBadge.tsx](../../../resources/js/Components/NfeBrasil/FiscalStatusBadge.tsx)
- [NfceStatusBadge.tsx](../../../resources/js/Components/NfeBrasil/NfceStatusBadge.tsx)
- [fiscalStatus.ts](../../../resources/js/Components/NfeBrasil/fiscalStatus.ts)
- [PageHeader.tsx](../../../resources/js/Components/PageHeader/PageHeader.tsx)
- [PageHeaderPrimary.tsx](../../../resources/js/Components/PageHeader/PageHeaderPrimary.tsx)
- [index.ts](../../../resources/js/Components/PageHeader/index.ts)
- [DashboardMockup.tsx](../../../resources/js/Components/Site/DashboardMockup.tsx)
- [Faqs.tsx](../../../resources/js/Components/Site/Faqs.tsx)
- [FeatureGrid.tsx](../../../resources/js/Components/Site/FeatureGrid.tsx)
- [GoogleIcon.tsx](../../../resources/js/Components/Site/GoogleIcon.tsx)
- [Hero.tsx](../../../resources/js/Components/Site/Hero.tsx)
- [MicrosoftIcon.tsx](../../../resources/js/Components/Site/MicrosoftIcon.tsx)
- [PricingFaq.tsx](../../../resources/js/Components/Site/PricingFaq.tsx)
- [PricingTiers.tsx](../../../resources/js/Components/Site/PricingTiers.tsx)
- [SiteFooter.tsx](../../../resources/js/Components/Site/SiteFooter.tsx)
- [SiteHeader.tsx](../../../resources/js/Components/Site/SiteHeader.tsx)
- [SocialProof.tsx](../../../resources/js/Components/Site/SocialProof.tsx)
- [Testimonials.tsx](../../../resources/js/Components/Site/Testimonials.tsx)
- [ThemeToggle.tsx](../../../resources/js/Components/ThemeToggle.tsx)
- [BoardColumn.tsx](../../../resources/js/Components/board/BoardColumn.tsx)
- [TaskCard.tsx](../../../resources/js/Components/board/TaskCard.tsx)
- [badges.ts](../../../resources/js/Components/board/badges.ts)
- [LinkedApps.tsx](../../../resources/js/Components/cockpit/LinkedApps.tsx)
- [NfeCertBadge.tsx](../../../resources/js/Components/cockpit/NfeCertBadge.tsx)
- [Sidebar.tsx](../../../resources/js/Components/cockpit/Sidebar.tsx)
- [Thread.tsx](../../../resources/js/Components/cockpit/Thread.tsx)
- [TweaksPanel.tsx](../../../resources/js/Components/cockpit/TweaksPanel.tsx)
- [shared.ts](../../../resources/js/Components/cockpit/shared.ts)
- [box.tsx](../../../resources/js/Components/layout/box.tsx)
- [container.tsx](../../../resources/js/Components/layout/container.tsx)
- [grid.tsx](../../../resources/js/Components/layout/grid.tsx)
- [index.ts](../../../resources/js/Components/layout/index.ts)
- [inline.tsx](../../../resources/js/Components/layout/inline.tsx)
- [stack.tsx](../../../resources/js/Components/layout/stack.tsx)
- [text.tsx](../../../resources/js/Components/layout/text.tsx)
- [BulkActionBar.tsx](../../../resources/js/Components/shared/BulkActionBar.tsx)
- [ConsentBanner.tsx](../../../resources/js/Components/shared/ConsentBanner.tsx)
- [DataTable.tsx](../../../resources/js/Components/shared/DataTable.tsx)
- [EmptyState.tsx](../../../resources/js/Components/shared/EmptyState.tsx)
- [KpiCard.tsx](../../../resources/js/Components/shared/KpiCard.tsx)
- [KpiGrid.tsx](../../../resources/js/Components/shared/KpiGrid.tsx)
- [MercosulPlate.tsx](../../../resources/js/Components/shared/MercosulPlate.tsx)
- [PageFilters.tsx](../../../resources/js/Components/shared/PageFilters.tsx)
- [PageHeader.tsx](../../../resources/js/Components/shared/PageHeader.tsx)
- [PageHeaderActions.tsx](../../../resources/js/Components/shared/PageHeaderActions.tsx)
- [PageHeaderModuleNav.tsx](../../../resources/js/Components/shared/PageHeaderModuleNav.tsx)
- [PageHeaderTabs.tsx](../../../resources/js/Components/shared/PageHeaderTabs.tsx)
- [PwaInstallBanner.tsx](../../../resources/js/Components/shared/PwaInstallBanner.tsx)
- [SimpleMarkdown.tsx](../../../resources/js/Components/shared/SimpleMarkdown.tsx)
- [StatusBadge.tsx](../../../resources/js/Components/shared/StatusBadge.tsx)
- [SubNav.tsx](../../../resources/js/Components/shared/SubNav.tsx)
- [VendaDerivadaCard.tsx](../../../resources/js/Components/shared/VendaDerivadaCard.tsx)
- [SafeSelectItem.tsx](../../../resources/js/Components/ui/SafeSelectItem.tsx)
- [alert-dialog.tsx](../../../resources/js/Components/ui/alert-dialog.tsx)
- [alert.tsx](../../../resources/js/Components/ui/alert.tsx)
- [avatar.tsx](../../../resources/js/Components/ui/avatar.tsx)
- [badge.tsx](../../../resources/js/Components/ui/badge.tsx)
- [button.tsx](../../../resources/js/Components/ui/button.tsx)
- [card.tsx](../../../resources/js/Components/ui/card.tsx)
- [checkbox.tsx](../../../resources/js/Components/ui/checkbox.tsx)
- [command.tsx](../../../resources/js/Components/ui/command.tsx)
- [dialog.tsx](../../../resources/js/Components/ui/dialog.tsx)
- [document-input.tsx](../../../resources/js/Components/ui/document-input.tsx)
- [dropdown-menu.tsx](../../../resources/js/Components/ui/dropdown-menu.tsx)
- [field-state.tsx](../../../resources/js/Components/ui/field-state.tsx)
- [form-section.tsx](../../../resources/js/Components/ui/form-section.tsx)
- [icon-registry.ts](../../../resources/js/Components/ui/icon-registry.ts)
- [input-group.tsx](../../../resources/js/Components/ui/input-group.tsx)
- [input.tsx](../../../resources/js/Components/ui/input.tsx)
- [label.tsx](../../../resources/js/Components/ui/label.tsx)
- [numeric-input-ptbr.tsx](../../../resources/js/Components/ui/numeric-input-ptbr.tsx)
- [phone-input.tsx](../../../resources/js/Components/ui/phone-input.tsx)
- [popover.tsx](../../../resources/js/Components/ui/popover.tsx)
- [radio-group.tsx](../../../resources/js/Components/ui/radio-group.tsx)
- [resizable.tsx](../../../resources/js/Components/ui/resizable.tsx)
- [scroll-area.tsx](../../../resources/js/Components/ui/scroll-area.tsx)
- [segmented.tsx](../../../resources/js/Components/ui/segmented.tsx)
- [select.tsx](../../../resources/js/Components/ui/select.tsx)
- [separator.tsx](../../../resources/js/Components/ui/separator.tsx)
- [sheet.tsx](../../../resources/js/Components/ui/sheet.tsx)
- [skeleton.tsx](../../../resources/js/Components/ui/skeleton.tsx)
- [switch.tsx](../../../resources/js/Components/ui/switch.tsx)
- [textarea.tsx](../../../resources/js/Components/ui/textarea.tsx)
- [tooltip.tsx](../../../resources/js/Components/ui/tooltip.tsx)

## Layouts herdados (React) — 2

- [AppShellV2.tsx](../../../resources/js/Layouts/AppShellV2.tsx)
- [SiteLayout.tsx](../../../resources/js/Layouts/SiteLayout.tsx)

## Componentes compartilhados (Blade) — 5

- [avatar.blade.php](../../../resources/views/components/avatar.blade.php)
- [document_help_text.blade.php](../../../resources/views/components/document_help_text.blade.php)
- [filters.blade.php](../../../resources/views/components/filters.blade.php)
- [static.blade.php](../../../resources/views/components/static.blade.php)
- [widget.blade.php](../../../resources/views/components/widget.blade.php)

## Layouts herdados (Blade) — 31

- [app.blade.php](../../../resources/views/layouts/app.blade.php)
- [auth.blade.php](../../../resources/views/layouts/auth.blade.php)
- [auth2.blade.php](../../../resources/views/layouts/auth2.blade.php)
- [guest.blade.php](../../../resources/views/layouts/guest.blade.php)
- [home.blade.php](../../../resources/views/layouts/home.blade.php)
- [inertia.blade.php](../../../resources/views/layouts/inertia.blade.php)
- [install.blade.php](../../../resources/views/layouts/install.blade.php)
- [calculator.blade.php](../../../resources/views/layouts/partials/calculator.blade.php)
- [clarity.blade.php](../../../resources/views/layouts/partials/clarity.blade.php)
- [consent-banner.blade.php](../../../resources/views/layouts/partials/consent-banner.blade.php)
- [css.blade.php](../../../resources/views/layouts/partials/css.blade.php)
- [error.blade.php](../../../resources/views/layouts/partials/error.blade.php)
- [extracss.blade.php](../../../resources/views/layouts/partials/extracss.blade.php)
- [extracss_auth.blade.php](../../../resources/views/layouts/partials/extracss_auth.blade.php)
- [footer-restaurant.blade.php](../../../resources/views/layouts/partials/footer-restaurant.blade.php)
- [footer.blade.php](../../../resources/views/layouts/partials/footer.blade.php)
- [footer_pos.blade.php](../../../resources/views/layouts/partials/footer_pos.blade.php)
- [header-auth.blade.php](../../../resources/views/layouts/partials/header-auth.blade.php)
- [header-notifications.blade.php](../../../resources/views/layouts/partials/header-notifications.blade.php)
- [header-pos.blade.php](../../../resources/views/layouts/partials/header-pos.blade.php)
- [header-restaurant.blade.php](../../../resources/views/layouts/partials/header-restaurant.blade.php)
- [header.blade.php](../../../resources/views/layouts/partials/header.blade.php)
- [home_header.blade.php](../../../resources/views/layouts/partials/home_header.blade.php)
- [javascripts.blade.php](../../../resources/views/layouts/partials/javascripts.blade.php)
- [language_btn.blade.php](../../../resources/views/layouts/partials/language_btn.blade.php)
- [logo.blade.php](../../../resources/views/layouts/partials/logo.blade.php)
- [module_form_part.blade.php](../../../resources/views/layouts/partials/module_form_part.blade.php)
- [notification_list.blade.php](../../../resources/views/layouts/partials/notification_list.blade.php)
- [search_settings.blade.php](../../../resources/views/layouts/partials/search_settings.blade.php)
- [sidebar.blade.php](../../../resources/views/layouts/partials/sidebar.blade.php)
- [restaurant.blade.php](../../../resources/views/layouts/restaurant.blade.php)

## Templates de construção (Design System) — 2

- [PageHeader-LEARNINGS.md](../../../memory/requisitos/_DesignSystem/templates/PageHeader-LEARNINGS.md)
- [PageHeader-canon-v3-1.md](../../../memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md)
