/**
 * Seleciona as rotas do smoke pós-deploy e mantém explícito o que não foi medido.
 *
 * Antes, qualquer Page fora de routes.json caía silenciosamente nas três rotas
 * nav_critical. O job podia ficar verde tendo aberto dashboard/vendas, sem visitar a
 * tela que disparou o workflow. `unmatched` é o canário desse falso verde.
 */
export function selectSmokeRoutes(routes, screens) {
  const value = String(screens || '__NAV__');
  const changed = value.split(',').map((item) => item.trim()).filter(Boolean);
  const manual = value === '__MANUAL__';
  const navOnly = value === '__NAV__';

  if (manual) return { routes: [...routes], unmatched: [] };
  if (navOnly) return { routes: routes.filter((route) => route.nav_critical), unmatched: [] };

  const declaredSources = new Set(routes.map((route) => route.source).filter(Boolean));
  const unmatched = changed.filter((source) => !declaredSources.has(source));
  const selected = routes.filter((route) => route.nav_critical || (route.source && changed.includes(route.source)));
  return { routes: selected, unmatched };
}
