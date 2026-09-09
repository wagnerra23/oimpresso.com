/**
 * KpiFilterCard — ALIAS: é o KpiCard em variant="filter"
 * (fusão 2026-08, ver NOTAS_INTERNAS.md).
 */
export function KpiFilterCard(props) {
  const NS = (typeof window !== 'undefined' && window.OfficeImpressoPontoWR2DesignSystem_019dd0) || {};
  const Kpi = NS.KpiCard;
  if (!Kpi) return null;
  return React.createElement(Kpi, Object.assign({ variant: 'filter' }, props));
}
