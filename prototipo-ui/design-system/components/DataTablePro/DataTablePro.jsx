/**
 * DataTablePro — ALIAS de compatibilidade. A implementação única é DataGrid
 * com resizable + header fixo (fusão 2026-08, ver NOTAS_INTERNAS.md).
 */
export function DataTablePro(props) {
  const NS = (typeof window !== 'undefined' && window.OfficeImpressoPontoWR2DesignSystem_019dd0) || {};
  const Grid = NS.DataGrid;
  if (!Grid) return null;
  const { height, density, ...rest } = props;
  return React.createElement(Grid, Object.assign({}, rest, {
    pagination: false,
    resizable: true,
    zebra: false,
    density: density === 'compact' ? 'compact' : 'comfortable',
    maxHeight: height != null ? height : 440,
  }));
}
