// DELETE no contrato ajax `{success, msg}` dos `destroy` do Patrimônio.
//
// Os `destroy` do módulo só respondem dentro de `if (request()->ajax())` e devolvem JSON
// (`AssetController::destroy`). É o mesmo contrato que as telas irmãs tratam por `fetch`
// (`Pages/Configuracoes/Impostos/Index.tsx`, `enviar()`). Mora fora do `.tsx` do diálogo para o
// arquivo de componente exportar só componente (fast refresh).

export interface ResultadoExclusao {
  success: boolean;
  msg?: string;
}

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** DELETE no contrato ajax `{success, msg}` do módulo. Nunca lança: falha vira `success: false`. */
export async function excluirRegistro(url: string): Promise<ResultadoExclusao> {
  try {
    const r = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      body: new URLSearchParams({ _method: 'DELETE' }),
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
    });
    const corpo = await r.json().catch(() => null);
    if (r.ok && corpo && typeof corpo.success === 'boolean') return corpo;
    return { success: false, msg: `O servidor não concluiu a exclusão (HTTP ${r.status}).` };
  } catch {
    return { success: false, msg: 'Sem conexão com o servidor.' };
  }
}
