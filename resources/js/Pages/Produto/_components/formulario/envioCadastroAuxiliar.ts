// Pedido e envio do cadastro rápido de unidade e de marca (UC-PCAD-28 · UC-PEDIT-28). O diálogo fica
// em CadastroAuxiliar.tsx; aqui só o que não é componente.

export type TipoAuxiliar = 'unidade' | 'marca';

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** O pedido de cada tipo, no formato que UnitController@store e BrandController@store leem. */
export function pedidoDoAuxiliar(
  tipo: TipoAuxiliar,
  campos: { nome: string; abreviacao: string; decimal: boolean; descricao: string },
): { url: string; corpo: Record<string, unknown> } {
  return tipo === 'unidade'
    ? {
        url: '/units',
        corpo: { actual_name: campos.nome.trim(), short_name: campos.abreviacao.trim(), allow_decimal: campos.decimal ? 1 : 0 },
      }
    : { url: '/brands', corpo: { name: campos.nome.trim(), description: campos.descricao.trim() } };
}

export async function enviarAuxiliar(url: string, corpo: Record<string, unknown>): Promise<{ id: number } | { erro: string }> {
  const falha = 'Não foi possível gravar.';
  try {
    const r = await fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(corpo),
    });
    const j = (await r.json().catch(() => null)) as { success?: boolean; msg?: string; data?: { id?: number } } | null;
    if (r.ok && j?.success === true && typeof j.data?.id === 'number') return { id: j.data.id };
    return { erro: j?.msg ?? falha };
  } catch {
    return { erro: falha };
  }
}
