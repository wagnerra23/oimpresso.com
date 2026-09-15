/**
 * DataTable — ALIAS de compatibilidade. A implementação única é DataGrid
 * (fusão 2026-08, ver NOTAS_INTERNAS.md). Mantém a API antiga: seleção e
 * ordenação controladas por fora, sem paginação e sem header fixo.
 */
export function DataTable(props) {
  const NS = (typeof window !== 'undefined' && window.OfficeImpressoPontoWR2DesignSystem_019dd0) || {};
  const Grid = NS.DataGrid;
  if (!Grid) return null;
  return React.createElement(Grid, Object.assign({}, props, {
    pagination: false,
    zebra: false,
    density: 'comfortable',
    maxHeight: props.maxHeight != null ? props.maxHeight : 'none',
  }));
}
