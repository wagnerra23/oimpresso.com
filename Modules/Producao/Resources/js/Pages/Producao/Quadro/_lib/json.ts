// Chamada JSON aos endpoints da Produção que não são Inertia (alocação e aproveitamento).
// Mesmo padrão de Cliente/Grupos: cookie XSRF + cabeçalhos de AJAX. Erro 422 traz a mensagem
// do servidor (`message` no alocador, `mensagem` no aproveitamento) — devolvida como Error.
function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

export async function json<T>(metodo: 'POST' | 'PUT', url: string, corpo: unknown): Promise<T> {
  const r = await fetch(url, {
    method: metodo, credentials: 'same-origin', body: JSON.stringify(corpo),
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const dados = await r.json().catch(() => ({}));
  if (!r.ok) {
    const campo = dados.errors ? Object.values(dados.errors as Record<string, string[]>)[0]?.[0] : undefined;
    throw new Error(dados.mensagem ?? dados.message ?? campo ?? (r.status === 403 ? 'Você não tem permissão para isto.' : 'Não foi possível concluir.'));
  }
  return dados as T;
}
