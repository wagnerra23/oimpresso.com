// Cadastro de bem — o que o drawer POSTA pro `AssetController::store()`.
//
// Separado do componente de propósito: é a peça que mexe em VALOR e QUANTIDADE (REGRA
// MESTRE Tier 0 — `memory/proibicoes.md`), então tem de ser testável sozinha, sem render.
// O teste `tests/js/patrimonio-cadastro-bem.test.tsx` fixa as strings exatas que saem daqui,
// e o Pest `BensContratoTest` (UC-BENS-05) posta AS MESMAS strings e lê o que o banco gravou.
// São os dois caminhos independentes da dupla confirmação.
//
// ── Por que número sai como "1234,56" e nunca como 1234.56 ──────────────────────
// O backend passa `unit_price`, `quantity`, `depreciation` e `additional_cost` por
// `Util::num_uf`, que é pt-BR canônico: vírgula = decimal, ponto = milhar (com a tolerância
// "1 ponto + ≤2 dígitos = decimal en-US"). O incidente de 2026-06-05 (ROTA LIVRE) foi
// exatamente um float com ponto e muitas casas (`204.99605`) lido como milhar → valor ×100k.
// Então: arredonda nas casas da coluna, usa VÍRGULA como decimal e NUNCA põe separador de
// milhar. "1234,56" só tem uma leitura possível no `num_uf`.
//
// ── Por que data sai no formato do negócio ─────────────────────────────────────
// `purchase_date` e o início da garantia passam por `Util::uf_date`, que faz
// `Carbon::createFromFormat(session('business.date_format'), …)`. ISO cru lança exceção, e o
// `store()` a engole num flash genérico "algo deu errado".

export interface FormCadastroBem {
  nome: string;
  categoriaId: string;
  localId: string;
  modelo: string;
  serie: string;
  /** ISO `YYYY-MM-DD`, o que `<input type="date">` devolve. */
  compraEm: string;
  tipoCompra: string;
  valorUnitario: number;
  quantidade: number;
  /** `null` = não informado (o campo é opcional no backend). */
  depreciacao: number | null;
  alocavel: boolean;
  /** Vazio = sem garantia registrada. */
  garantiaMeses: string;
  /** ISO. */
  garantiaInicio: string;
  garantiaNota: string;
  descricao: string;
  imagem: File | null;
}

/**
 * Casas enviadas = casas EXIBIDAS no drawer (2). As colunas guardam 4, mas o
 * `NumericInputPtBR` arredonda só a EXIBIÇÃO no blur e emite o número cru — se o envio usasse
 * mais casas que a tela, o usuário veria "1,50" e o banco gravaria 1,4999. Mesma precisão
 * nos dois lados: o que a tela mostra é o que o banco grava.
 */
const CASAS_VALOR = 2;
const CASAS_QUANTIDADE = 2;

/**
 * Número → string que `Util::num_uf` lê sem ambiguidade: vírgula decimal, sem milhar,
 * zeros à direita da parte decimal removidos (`2` → "2", `1.5` → "1,5", `1234.56` → "1234,56").
 */
export function paraNumUf(valor: number, casas: number): string {
  if (!Number.isFinite(valor)) return '0';
  const fixo = valor.toFixed(casas); // "1234.5600" — toFixed nunca põe separador de milhar
  const [inteira = '0', decimal = ''] = fixo.split('.');
  const dec = decimal.replace(/0+$/, '');
  return dec ? `${inteira},${dec}` : inteira;
}

/** ISO (`2026-09-10`) → formato do negócio (`d/m/Y` → `10/09/2026`). */
export function paraFormatoDoNegocio(iso: string, formato: string): string {
  const [ano, mes, dia] = iso.split('-');
  if (!ano || !mes || !dia) return iso;
  return formato.replace('d', dia).replace('m', mes).replace('Y', ano);
}

/** O que o form precisa ter pra ser enviado. Espelha as regras do protótipo (`BemForm.salvar`). */
export function validarCadastroBem(f: FormCadastroBem): Record<string, string> {
  const e: Record<string, string> = {};
  if (!f.nome.trim()) e.nome = 'O nome do recurso é obrigatório.';
  if (!f.categoriaId) e.categoriaId = 'Escolha a categoria.';
  if (!f.localId) e.localId = 'Escolha o local.';
  if (!f.compraEm) e.compraEm = 'Informe a data da compra.';
  if (!(f.valorUnitario > 0)) e.valorUnitario = 'Valor unitário precisa ser maior que zero.';
  if (!(f.quantidade >= 1)) e.quantidade = 'Quantidade mínima é 1.';
  if (f.garantiaMeses.trim()) {
    const meses = Number(f.garantiaMeses);
    if (!Number.isInteger(meses) || meses < 1) e.garantiaMeses = 'Período em meses inteiros, a partir de 1.';
    if (!f.garantiaInicio) e.garantiaInicio = 'Com período de garantia, informe o início.';
  }
  return e;
}

/**
 * O payload exato do POST. Chaves e forma são as do `StoreAssetRequest` + `AssetService::criar`
 * (`start_dates[]`/`months[]`/`additional_cost[]`/`additional_note[]` são arrays PARALELOS).
 *
 * - `asset_code` vai vazio: o serviço gera pelo prefixo do módulo (`setAndGetReferenceCount`).
 * - `additional_cost` vai "0" quando há garantia: o `montarGarantias()` lê
 *   `additional_cost[$key]` sem checar existência — omitir a chave derrubaria o `store()`.
 *   "0" é o que o Blade gravava com o campo em branco (`num_uf('')` = 0).
 * - `is_allocatable` só vai quando marcado: o serviço testa `! empty(...)`, e o checkbox do
 *   Blade também só era enviado marcado.
 */
export function montarPayloadCadastro(f: FormCadastroBem, formatoData: string): Record<string, unknown> {
  const payload: Record<string, unknown> = {
    asset_code: '',
    name: f.nome.trim(),
    category_id: f.categoriaId,
    location_id: f.localId,
    model: f.modelo.trim(),
    serial_no: f.serie.trim(),
    purchase_date: paraFormatoDoNegocio(f.compraEm, formatoData),
    purchase_type: f.tipoCompra,
    unit_price: paraNumUf(f.valorUnitario, CASAS_VALOR),
    quantity: paraNumUf(f.quantidade, CASAS_QUANTIDADE),
    depreciation: f.depreciacao === null ? '' : paraNumUf(f.depreciacao, CASAS_VALOR),
    description: f.descricao,
  };

  if (f.alocavel) payload.is_allocatable = '1';

  if (f.garantiaMeses.trim()) {
    payload.start_dates = [paraFormatoDoNegocio(f.garantiaInicio, formatoData)];
    payload.months = [String(Number(f.garantiaMeses))];
    payload.additional_cost = ['0'];
    payload.additional_note = [f.garantiaNota.trim()];
  }

  if (f.imagem) payload.image = f.imagem;

  return payload;
}
