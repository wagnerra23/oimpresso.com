// Naturezas de operação — formato e listas da tela (RUNBOOK-tributacao §Naturezas · onda 3b).
// Fonte: prototipo-ui/cowork/Felipe/Configuracao de Tributos.html (vazia(), listas de códigos).
// A conferência NÃO mora aqui: a tela pergunta ao servidor (POST /naturezas/conferir), para a regra
// existir num lugar só.

export type Opcao = readonly [valor: string, rotulo: string];

export interface Natureza {
  id?: number;
  nome: string;
  tipo: 'S' | 'E';
  fin: '1' | '2' | '3' | '4';
  ativo: boolean;
  ref: boolean;
  cfop: { in: string; out: string; ext: string };
  icms: {
    cst: string; csosn: string; origem: 'uf' | 'fixa' | 'origem'; p: number; modbc: string; red: number;
    pDif: number; pCredSN: number; bFrete: boolean; bIPI: boolean; bDesc: boolean;
    st: { modbcst: string; mvaOrig: 'produto' | 'fixa'; mva: number; redB: number };
    difal: boolean; fcp: boolean;
  };
  ipi: { on: boolean; cst: string; cEnq: string; origem: 'produto' | 'fixa' | 'origem'; p: number };
  pis: { cst: string; cstSN: string; origem: 'regime' | 'fixa' | 'origem'; p: number; exclICMS: boolean };
  cofins: { cst: string; cstSN: string; origem: 'regime' | 'fixa' | 'origem'; p: number; exclICMS: boolean };
  ibs: { on: boolean; cst: string; cClass: string; origem: 'padrao' | 'fixa'; pUF: number; pMun: number; pCBS: number; pRed: number };
}

export interface Alerta {
  nivel: 'erro' | 'aviso' | 'info';
  secao: 'nat' | 'cfop' | 'icms' | 'ipi' | 'pc' | 'ibs';
  mensagem: string;
}

export interface LinhaNatureza {
  id: number;
  editavel: boolean;
  padrao: boolean;
  natureza: Partial<Natureza> & Pick<Natureza, 'nome' | 'tipo' | 'fin' | 'ativo' | 'ref' | 'cfop'>;
  pendencias: number | null;
}

export type Regime = 'LP' | 'LR' | 'SN';

export function vazia(): Natureza {
  return {
    nome: '', tipo: 'S', fin: '1', ativo: true, ref: false,
    cfop: { in: '', out: '', ext: '' },
    icms: {
      cst: '00', csosn: '102', origem: 'uf', p: 0, modbc: '3', red: 0, pDif: 0, pCredSN: 0,
      bFrete: true, bIPI: false, bDesc: true,
      st: { modbcst: '4', mvaOrig: 'produto', mva: 0, redB: 0 }, difal: true, fcp: true,
    },
    ipi: { on: false, cst: '53', cEnq: '999', origem: 'produto', p: 0 },
    pis: { cst: '01', cstSN: '49', origem: 'regime', p: 0, exclICMS: true },
    cofins: { cst: '01', cstSN: '49', origem: 'regime', p: 0, exclICMS: true },
    ibs: { on: true, cst: '000', cClass: '000001', origem: 'padrao', pUF: 0.1, pMun: 0, pCBS: 0.9, pRed: 0 },
  };
}

/** Uma linha da lista vira natureza completa: o que faltar vem da vazia (Venda e CSV não têm tributos da tela). */
export function completar(parcial: LinhaNatureza['natureza']): Natureza {
  const base = vazia();
  return {
    ...base, ...parcial,
    cfop: { ...base.cfop, ...parcial.cfop },
    icms: { ...base.icms, ...(parcial.icms ?? {}), st: { ...base.icms.st, ...(parcial.icms?.st ?? {}) } },
    ipi: { ...base.ipi, ...(parcial.ipi ?? {}) },
    pis: { ...base.pis, ...(parcial.pis ?? {}) },
    cofins: { ...base.cofins, ...(parcial.cofins ?? {}) },
    ibs: { ...base.ibs, ...(parcial.ibs ?? {}) },
  };
}

export const TIPOS_NOTA: readonly Opcao[] = [['S', 'Saída'], ['E', 'Entrada']];
export const FINS: readonly Opcao[] = [['1', '1 · Normal'], ['2', '2 · Complementar'], ['3', '3 · Ajuste'], ['4', '4 · Devolução']];
export const CST_ICMS_NATUREZA: readonly Opcao[] = [['00', '00 · Tributada integralmente'], ['10', '10 · Tributada e com ICMS-ST'], ['20', '20 · Com redução de base'], ['30', '30 · Isenta ou não tributada, com ST'], ['40', '40 · Isenta'], ['41', '41 · Não tributada'], ['50', '50 · Suspensão'], ['51', '51 · Diferimento'], ['60', '60 · ICMS cobrado antes por ST'], ['70', '70 · Redução de base e ST'], ['90', '90 · Outras']];
export const CSOSN: readonly Opcao[] = [['101', '101 · Com permissão de crédito'], ['102', '102 · Sem permissão de crédito'], ['103', '103 · Isenção por faixa de receita'], ['201', '201 · Com crédito e com ST'], ['202', '202 · Sem crédito e com ST'], ['203', '203 · Isenção por faixa e com ST'], ['300', '300 · Imune'], ['400', '400 · Não tributada'], ['500', '500 · ICMS cobrado antes por ST'], ['900', '900 · Outros']];
export const MODBC: readonly Opcao[] = [['3', '3 · Valor da operação'], ['0', '0 · Margem de valor agregado'], ['1', '1 · Pauta'], ['2', '2 · Preço tabelado máximo']];
export const MODBCST: readonly Opcao[] = [['4', '4 · MVA (margem)'], ['0', '0 · Preço tabelado ou máximo sugerido'], ['1', '1 · Lista negativa'], ['2', '2 · Lista positiva'], ['3', '3 · Lista neutra'], ['5', '5 · Pauta'], ['6', '6 · Valor da operação']];
export const CST_IPI_S: readonly Opcao[] = [['50', '50 · Saída tributada'], ['51', '51 · Tributada com alíquota zero'], ['52', '52 · Isenta'], ['53', '53 · Não tributada'], ['54', '54 · Imune'], ['55', '55 · Com suspensão'], ['99', '99 · Outras saídas']];
export const CST_IPI_E: readonly Opcao[] = [['00', '00 · Entrada com crédito'], ['01', '01 · Entrada com alíquota zero'], ['02', '02 · Entrada isenta'], ['03', '03 · Entrada não tributada'], ['04', '04 · Entrada imune'], ['05', '05 · Entrada com suspensão'], ['49', '49 · Outras entradas']];
export const CST_PC_S: readonly Opcao[] = [['01', '01 · Alíquota básica'], ['02', '02 · Alíquota diferenciada'], ['04', '04 · Monofásica, revenda a alíquota zero'], ['06', '06 · Alíquota zero'], ['07', '07 · Isenta'], ['08', '08 · Sem incidência'], ['09', '09 · Com suspensão'], ['49', '49 · Outras saídas']];
export const CST_PC_E: readonly Opcao[] = [['50', '50 · Com direito a crédito'], ['70', '70 · Aquisição sem direito a crédito'], ['98', '98 · Outras entradas'], ['99', '99 · Outras operações']];
export const CST_IBS: readonly Opcao[] = [['000', '000 · Tributação integral'], ['200', '200 · Alíquota reduzida'], ['410', '410 · Imunidade e não incidência']];
export const ORIG_ICMS: readonly Opcao[] = [['uf', 'Pela UF (interna ou interestadual)'], ['fixa', 'Fixa nesta natureza'], ['origem', 'Da nota de origem']];
export const ORIG_MVA: readonly Opcao[] = [['produto', 'Da regra do NCM (Tributação › Regras NCM)'], ['fixa', 'Fixa nesta natureza']];
export const ORIG_IPI: readonly Opcao[] = [['produto', 'Da regra do NCM (Tributação › Regras NCM)'], ['fixa', 'Fixa nesta natureza'], ['origem', 'Da nota de origem']];
export const ORIG_PC: readonly Opcao[] = [['regime', 'Padrão do regime da empresa'], ['fixa', 'Fixa nesta natureza'], ['origem', 'Da nota de origem']];
export const ORIG_IBS: readonly Opcao[] = [['padrao', 'Padrão do ano (teste em 2026)'], ['fixa', 'Fixa nesta natureza']];

export const CST_COM_ST = ['10', '30', '70', '201', '202', '203'];

export function rotulo(opcoes: readonly Opcao[], valor: string): string {
  return opcoes.find((o) => o[0] === valor)?.[1] ?? valor;
}

const URL = '/nfe-brasil/tributacao/naturezas';

function cabecalhos(): HeadersInit {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
  };
}

/** Faixa de resumo do topo (protótipo `.dados`): quem emite, de onde saem as notas e o IBS/CBS do ano. */
export interface EmpresaResumo {
  nome: string | null;
  cnpj: string | null;
  municipio: string | null;
  uf: string;
  ano: number;
  /** Fração (0.001 = 0,1%). null = o ano ainda não tem alíquota cadastrada. */
  ibs: number | null;
  cbs: number | null;
}

export const REGIME_NOME: Record<Regime, string> = { LP: 'Lucro Presumido', LR: 'Lucro Real', SN: 'Simples Nacional' };

export async function listar(): Promise<{ regime: Regime; empresa?: EmpresaResumo; naturezas: LinhaNatureza[] }> {
  const res = await fetch(URL, { headers: cabecalhos(), credentials: 'same-origin' });
  if (!res.ok) throw new Error(String(res.status));
  return res.json();
}

export async function conferir(n: Natureza): Promise<Alerta[]> {
  const res = await fetch(`${URL}/conferir`, {
    method: 'POST', headers: cabecalhos(), credentials: 'same-origin', body: JSON.stringify(n),
  });
  if (!res.ok) throw new Error(String(res.status));
  return ((await res.json()) as { alertas: Alerta[] }).alertas;
}

/** Salva: 201/200 → ok; 422 → alertas da conferência; 409 → natureza que a tela não edita. */
export async function salvar(n: Natureza): Promise<{ ok: true } | { ok: false; alertas?: Alerta[]; mensagem?: string }> {
  const res = await fetch(n.id ? `${URL}/${n.id}` : URL, {
    method: n.id ? 'PUT' : 'POST', headers: cabecalhos(), credentials: 'same-origin', body: JSON.stringify(n),
  });
  if (res.ok) return { ok: true };
  const corpo = await res.json().catch(() => ({}));
  return { ok: false, alertas: corpo.alertas, mensagem: corpo.mensagem };
}
