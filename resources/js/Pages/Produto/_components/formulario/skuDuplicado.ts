// UC-PCAD-34: conferência do SKU digitado, a mesma que o Blade faz antes de enviar (`product.js` chama
// `/products/check_product_sku`). A rota responde o texto `true` quando o SKU está livre e `false`
// quando já é SKU ou sub-SKU de outro produto da empresa. No Editar vai o id do produto, para ele não
// contar como repetido.

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** `true` em uso, `false` livre, `null` quando não deu para conferir (o servidor confere no salvar). */
export async function skuEmUso(sku: string, produtoId?: number): Promise<boolean | null> {
  try {
    const corpo = new URLSearchParams({ sku });
    if (produtoId) corpo.set('product_id', String(produtoId));
    const r = await fetch('/products/check_product_sku', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: corpo,
    });
    if (!r.ok) return null;
    const texto = (await r.text()).trim();
    return texto === 'false' ? true : texto === 'true' ? false : null;
  } catch {
    return null;
  }
}
