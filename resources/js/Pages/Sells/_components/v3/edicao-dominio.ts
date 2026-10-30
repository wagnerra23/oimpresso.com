// Adaptador do formulário deferido de SellController@edit. Integração da Page é separada.
// Reutiliza a edição canônica: preço antes do desconto, IDs existentes e total no servidor.
import { linhaDoBanco, linhaParaEnvio, numeroParaEnvio, type LinhaEdicao, type LinhaGravada } from '../edicaoVenda';

type Registro = Record<string, unknown>;
export type TravaEdicao = {
  nota: boolean; pagamento: boolean; itens_valores: boolean; cliente: boolean;
  motivo: string | null; campos_livres: string[];
};
export type FonteEdicaoV3 = {
  saleId: number;
  headline: { type: string };
  permissions: { update: boolean; editPrice: boolean; editDiscount: boolean; payments?: boolean };
  trava: TravaEdicao;
  urls: { submit: string };
  form?: {
    transaction: Registro;
    edicaoV3: Registro;
    sellDetails: (LinhaGravada & Registro)[];
    paymentLines: Registro[];
    reparo?: unknown;
  };
};
export type EdicaoV3 = {
  fonte: FonteEdicaoV3 & { form: NonNullable<FonteEdicaoV3['form']> };
  itens: LinhaEdicao[];
  pagamentos: Registro[];
  impedimento: string | null;
};

/** undefined = aguardar form deferido. Cada visita usa os dados do servidor, sem draft local. */
export function carregarEdicaoV3(fonte: FonteEdicaoV3): EdicaoV3 | undefined {
  if (!fonte.form) return undefined;
  // Snapshot independente: editar state não pode alterar a referência usada para conferir travas.
  const copia = structuredClone(fonte) as EdicaoV3['fonte'];
  const { form } = copia;
  const complexa = form.sellDetails.some((l) => l.sub_unit_id || Number(l.secondary_unit_quantity ?? 0) !== 0 || l.product_type === 'combo' ||
    (Array.isArray(l.modifiers) && l.modifiers.length > 0));
  return {
    fonte: copia,
    itens: form.sellDetails.map(linhaDoBanco),
    // Mantém método, conta, datas, troco e IDs; não converte em atalhos Dinheiro/Cartão.
    pagamentos: structuredClone(form.paymentLines),
    impedimento: !form.edicaoV3 ? 'Recarregue a venda para obter os dados completos da edição.'
      : fonte.headline.type !== 'sell' ? 'Este tipo de documento ainda usa o editor atual.'
      : form.reparo ? 'A integração da edição de reparo está pendente.'
      : complexa ? 'Subunidades, combos e modificadores ainda usam o editor atual.' : null,
  };
}

// O que a primeira integração permite alterar. Demais dados voltam na base preservada.
const CAMPOS = ['contact_id', 'discount_type', 'discount_amount', 'tax_rate_id', 'shipping_charges',
  'pay_term_number', 'pay_term_type', 'sale_note', 'staff_note', 'shipping_details',
  'shipping_address', 'delivered_to', 'customer_secondary_address', 'commission_agent'] as const;
type Campo = typeof CAMPOS[number];
type Mudancas = { itens?: LinhaEdicao[]; campos?: Partial<Record<Campo, unknown>> };
type Pedido = { ok: true; url: string; method: 'put'; payload: Registro } | { ok: false; motivo: string };

export function montarEdicaoV3(edicao: EdicaoV3, mudancas: Mudancas = {}): Pedido {
  const { fonte } = edicao;
  const { permissions, trava, form } = fonte;
  const recusar = (motivo: string): Pedido => ({ ok: false, motivo });
  if (!permissions.update) return recusar('Sem permissão para alterar a venda.');
  if (edicao.impedimento) return recusar(edicao.impedimento);
  if (fonte.urls.submit !== `/pos/${fonte.saleId}`) return recusar('Destino de edição inválido.');
  const campos = mudancas.campos ?? {};
  if (Object.keys(campos).some((k) => !(CAMPOS as readonly string[]).includes(k))) {
    return recusar('Campo fora do contrato de edição.');
  }
  const originais = form.sellDetails.map(linhaDoBanco);
  const itens = mudancas.itens ?? edicao.itens;
  if (!itens.length) return recusar('A venda não tem item.');
  if (itens.some((l) => !Number.isFinite(l.quantity) || l.quantity <= 0 ||
    !Number.isFinite(l.unit_price) || l.unit_price < 0 || !Number.isFinite(l.discount) || l.discount < 0)) {
    return recusar('Quantidade, preço ou desconto inválido.');
  }
  const ids = new Set<number>();
  for (const item of itens) {
    const original = originais.find((l) => l.sell_line_id === item.sell_line_id);
    if (!item.sell_line_id || ids.has(item.sell_line_id) || !original ||
      original.product_id !== item.product_id || original.variation_id !== item.variation_id) {
      return recusar('Linha sem identidade válida nesta venda.');
    }
    ids.add(item.sell_line_id);
    if (!permissions.editPrice && item.unit_price !== original.unit_price) return recusar('Sem permissão para alterar preço.');
    if (!permissions.editDiscount && (item.discount !== original.discount || item.discount_type !== original.discount_type)) {
      return recusar('Sem permissão para alterar desconto.');
    }
  }
  const mudou = (k: string) => {
    if (!(k in campos)) return false;
    if (['discount_amount', 'shipping_charges', 'tax_rate_id', 'contact_id'].includes(k)) {
      const anterior = Number(String(form.edicaoV3[k] ?? 0).replace(',', '.'));
      return Number(campos[k as Campo] ?? 0) !== anterior;
    }
    return String(campos[k as Campo] ?? '') !== String(form.edicaoV3[k] ?? '');
  };
  if (!permissions.editDiscount && (mudou('discount_type') || mudou('discount_amount'))) {
    return recusar('Sem permissão para alterar desconto.');
  }
  const produtos = itens.map(linhaParaEnvio);
  const mudouItens = JSON.stringify(produtos) !== JSON.stringify(originais.map(linhaParaEnvio));
  if (trava.itens_valores && (mudouItens || ['discount_type', 'discount_amount', 'tax_rate_id', 'shipping_charges'].some(mudou))) {
    return recusar(trava.motivo ?? 'Itens e valores estão travados.');
  }
  if (trava.cliente && mudou('contact_id')) return recusar(trava.motivo ?? 'Cliente está travado.');

  if (trava.nota) {
    if (Object.keys(campos).some((k) => mudou(k) && !trava.campos_livres.includes(k === 'sale_note' ? 'additional_notes' : k))) {
      return recusar(trava.motivo ?? 'Só os campos livres podem ser alterados com nota.');
    }
    // O update da nota aceita SOMENTE os campos livres e sai antes de regravar linhas/valores.
    const livres = Object.fromEntries(Object.entries(campos).filter(([k]) =>
      trava.campos_livres.includes(k === 'sale_note' ? 'additional_notes' : k)));
    return { ok: true, url: fonte.urls.submit, method: 'put', payload: { status: form.edicaoV3.status, ...livres } };
  }
  const payload: Registro = { ...structuredClone(form.edicaoV3), ...campos };
  for (const k of ['discount_amount', 'shipping_charges'] as const) {
    if (k in campos) {
      // Os controles entregam número cru. Texto pt-BR já é responsabilidade do domínio de inputs.
      const valor = campos[k];
      if (typeof valor !== 'number' || !Number.isFinite(valor) || valor < 0) return recusar('Valor inválido.');
      payload[k] = numeroParaEnvio(valor);
    }
  }
  payload.products = produtos.map((p, i) => {
    const original = form.sellDetails.find((l) => Number(l.transaction_sell_lines_id ?? l.id) === itens[i].sell_line_id)!;
    return { ...p, enable_stock: original.enable_stock, product_type: original.product_type,
      product_unit_id: original.unit_id, sell_line_note: original.sell_line_note,
      lot_no_line_id: original.lot_no_line_id, so_line_id: original.so_line_id,
      res_service_staff_id: original.res_service_staff_id,
      secondary_unit_quantity: original.secondary_unit_quantity,
      warranty_id: Array.isArray(original.warranties) ? (original.warranties[0] as Registro | undefined)?.id : undefined };
  });
  // Não reapresenta pagamentos como novos nem apaga os antigos. Alterar/estornar recebimentos
  // usa o fluxo dedicado com sell.payments; nesta edição eles são um snapshot somente leitura.
  payload.is_credit_sale = 1;
  payload.calcular_total_no_servidor = 1;
  return { ok: true, url: fonte.urls.submit, method: 'put', payload };
}
