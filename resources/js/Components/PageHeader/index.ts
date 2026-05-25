/**
 * PageHeader canon components — ADR 0189 v3.2 + ADR 0190 (primary universal).
 *
 * Componentes aqui implementam o template oficial documentado em
 * `memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md`.
 *
 * Próximos componentes a adicionar (Wave 3):
 *   <PageHeaderSubNav>   — Zona C subnav tabs com counter (refatorar nav inline existente)
 *   <PageHeaderOverflow> — Zona R overflow ⋮ com 3 seções (Filtros/Dados/Configuração)
 *   <KpiStripCanon>      — BLOCO 2 KPI strip 4 cards branco frio
 */
export { PageHeader, type PageHeaderProps } from './PageHeader';
export { PageHeaderPrimary } from './PageHeaderPrimary';
