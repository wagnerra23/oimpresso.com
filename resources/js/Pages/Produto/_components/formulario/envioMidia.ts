// Envio da imagem e do folheto do produto (UC-PCAD-29 · UC-PEDIT-29). O campo fica em CampoMidia.tsx;
// aqui só o que não é componente.

/** O que o Blade aceita no folheto (config/constants.php `document_upload_mimes_types`). */
export const ACEITA_FOLHETO = 'application/pdf,text/csv,application/zip,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/jpg,image/png';
/** 5 MB, o `document_size_limit` do servidor. */
export const LIMITE_BYTES = 5_000_000;

export interface Folheto {
  nome: string;
  url: string;
}

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export async function enviarMidia(
  produtoId: number,
  campo: 'image' | 'product_brochure',
  arquivo: File,
): Promise<{ imagem: string | null; folheto: Folheto | null } | { erro: string }> {
  const falha = 'Não foi possível gravar o arquivo.';
  const corpo = new FormData();
  corpo.append(campo, arquivo, arquivo.name);
  try {
    const r = await fetch(`/products/${produtoId}/midia`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: corpo,
    });
    const j = (await r.json().catch(() => null)) as
      | { success?: boolean; msg?: string; imagem?: string | null; folheto?: Folheto | null; errors?: Record<string, string[]> }
      | null;
    if (r.ok && j?.success === true) return { imagem: j.imagem ?? null, folheto: j.folheto ?? null };
    const primeiro = Object.values(j?.errors ?? {})[0]?.[0];
    return { erro: primeiro ?? j?.msg ?? falha };
  } catch {
    return { erro: falha };
  }
}
