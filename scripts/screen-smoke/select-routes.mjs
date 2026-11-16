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

  // __CLIENTES__ → só as rotas que um cliente vivo usa (campo `clientes` em routes.json).
  // Roda em TODO deploy, mexa ou não em tela: a pergunta é "o que a Larissa e a WR2 abrem
  // amanhã ainda abre?", não "a tela deste diff abre?". Nada fica unmatched aqui porque o
  // conjunto não depende do diff; o NÃO MEDIDO do diff segue com o job `smoke`.
  if (value === '__CLIENTES__') {
    return { routes: routes.filter((route) => clientesDa(route).length > 0), unmatched: [] };
  }
  if (manual) return { routes: [...routes], unmatched: [] };
  if (navOnly) return { routes: routes.filter((route) => route.nav_critical), unmatched: [] };

  const declaredSources = new Set(routes.map((route) => route.source).filter(Boolean));
  const unmatched = changed.filter((source) => !declaredSources.has(source));
  const selected = routes.filter((route) => route.nav_critical || (route.source && changed.includes(route.source)));
  return { routes: selected, unmatched };
}

/** Clientes vivos que usam a rota (`clientes: ["rota-livre"]`). Ausente ou inválido = nenhum. */
export function clientesDa(route) {
  return Array.isArray(route?.clientes) ? route.clientes.filter((c) => typeof c === 'string' && c) : [];
}

/** Linhas `ALERT smoke-clientes cliente=<x> ...` para as rotas quebradas ou não exercidas. */
export function alertasPorCliente(results, runId = 'local') {
  const porCliente = new Map();
  for (const r of results) {
    const ruim = r.skipped ? 'NAO-EXERCIDA' : (r.verdict === 'QUEBRADO' ? 'QUEBRADO' : null);
    if (!ruim) continue;
    for (const c of clientesDa(r)) {
      if (!porCliente.has(c)) porCliente.set(c, []);
      porCliente.get(c).push(`${r.label}=${ruim}`);
    }
  }
  return [...porCliente].map(([c, itens]) => `[smoke] ::error::ALERT smoke-clientes cliente=${c} run=${runId} ${itens.join(' ')}`);
}
