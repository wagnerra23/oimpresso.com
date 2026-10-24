// Chamada JSON aos endpoints da Produção que não são Inertia (alocação e aproveitamento).
// Mesmo padrão de Cliente/Grupos: cookie XSRF + cabeçalhos de AJAX. Erro 422 traz a mensagem
// do servidor (`message` no alocador, `mensagem` no aproveitamento) — devolvida como Error, com o
// `codigo` e o `contexto` da recusa quando o endpoint manda (a Linha do tempo pergunta quem faz por eles).
export class Recusa extends Error {
  constructor(mensagem: string, public codigo?: string, public contexto?: Record<string, unknown>) { super(mensagem); }
}
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
    throw new Recusa(dados.mensagem ?? dados.message ?? campo ?? (r.status === 403 ? 'Você não tem permissão para isto.' : 'Não foi possível concluir.'),
      dados.codigo, dados.contexto);
  }
  return dados as T;
}
