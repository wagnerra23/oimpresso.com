// "Como fica no lançamento" — o que a natureza põe nos itens da nota, por destino do cliente.
// Fonte: prototipo-ui/cowork/Felipe/Configuracao de Tributos.html, `lancamento(N, d)`.
// Só códigos e de onde vem cada alíquota — nenhum valor: base, imposto e totais são do lançamento.
// As alíquotas citadas são as que o motor usa (MotorTributarioService::PIS_COFINS_DO_REGIME e
// `nfebrasil.ibs_cbs_por_ano`, que chega pela faixa de resumo); se o motor mudar, isto muda junto.
import { CST_COM_ST, FINS, MODBC, MODBCST, PC_PADRAO, REGIME_NOME, rotulo, type EmpresaResumo, type Natureza, type Regime } from './natureza';

export type Destino = 'in' | 'out' | 'ext';

export const DESTINOS: { value: Destino; label: string }[] = [
  { value: 'in', label: 'Dentro do estado' },
  { value: 'out', label: 'Outro estado' },
  { value: 'ext', label: 'Exterior' },
];

export interface LinhaLancamento {
  cor: string;
  rotulo: string;
  /** Texto principal; `codigo` vai destacado em monoespaçada antes dele. */
  codigo?: string;
  texto: string;
  sub?: string;
}

const CFOP_NOME: Record<string, string> = {
  '5101': 'Venda de produção do estabelecimento', '6101': 'Venda de produção do estabelecimento', '7101': 'Venda de produção para o exterior',
  '5102': 'Venda de mercadoria de terceiros', '6102': 'Venda de mercadoria de terceiros', '7102': 'Venda de mercadoria para o exterior',
  '5401': 'Venda de produção com ST (substituto)', '6401': 'Venda de produção com ST (substituto)',
  '5403': 'Venda de mercadoria de terceiros com ST (substituto)', '6403': 'Venda de mercadoria de terceiros com ST (substituto)',
  '5405': 'Venda de mercadoria com ST já retido', '6404': 'Venda de mercadoria com ST já retido',
  '5201': 'Devolução de compra para industrialização', '6201': 'Devolução de compra para industrialização',
  '5202': 'Devolução de compra para comercialização', '6202': 'Devolução de compra para comercialização',
  '5411': 'Devolução de compra com ST', '6411': 'Devolução de compra com ST',
  '1201': 'Devolução de venda de produção', '2201': 'Devolução de venda de produção',
  '1202': 'Devolução de venda de mercadoria de terceiros', '2202': 'Devolução de venda de mercadoria de terceiros',
  '1411': 'Devolução de venda com ST', '2411': 'Devolução de venda com ST',
  '5910': 'Remessa em bonificação, doação ou brinde', '6910': 'Remessa em bonificação, doação ou brinde',
  '5915': 'Remessa para conserto ou reparo', '6915': 'Remessa para conserto ou reparo',
  '5916': 'Retorno de mercadoria recebida para conserto', '6916': 'Retorno de mercadoria recebida para conserto',
  '5949': 'Outra saída não especificada', '6949': 'Outra saída não especificada',
  '1949': 'Outra entrada não especificada', '2949': 'Outra entrada não especificada',
};

const SEM_ICMS_PROPRIO = ['30', '40', '41', '50', '60', '102', '103', '202', '203', '300', '400', '500'];

const COR = {
  natureza: 'var(--accent)',
  icms: 'var(--origin-CRM-fg)',
  ipi: 'var(--origin-MFG-fg)',
  pis: 'var(--origin-FIN-fg)',
  cofins: 'var(--stage-blue)',
  ibs: 'var(--stage-violet)',
} as const;

function pct(v: number): string {
  return `${(v || 0).toLocaleString('pt-BR', { maximumFractionDigits: 4 })}%`;
}

export function lancamento(n: Natureza, d: Destino, regime: Regime, empresa: EmpresaResumo | null): LinhaLancamento[] {
  const L: LinhaLancamento[] = [];
  const sn = regime === 'SN';
  const cst = sn ? n.icms.csosn : n.icms.cst;
  const cfop = n.cfop[d] || '';
  const uf = empresa?.uf ?? 'da empresa';
  const destino = DESTINOS.find((x) => x.value === d)!.label.toLowerCase();

  L.push({
    cor: COR.natureza, rotulo: 'Natureza e finalidade', texto: n.nome || 'Sem nome',
    sub: rotulo(FINS, n.fin).replace(/^\d · /, 'Finalidade ') + (n.ref ? ' · pede a chave da nota referenciada' : ''),
  });
  L.push(cfop
    ? { cor: COR.natureza, rotulo: 'CFOP', codigo: cfop, texto: CFOP_NOME[cfop] ?? '' }
    : { cor: COR.natureza, rotulo: 'CFOP', texto: `Sem CFOP para ${destino}`, sub: 'A natureza não pode ser usada para esse destino.' });

  let aliq: string;
  if (n.icms.origem === 'origem') aliq = 'Alíquota e base copiadas da nota de origem';
  else if (sn && !['101', '201', '900'].includes(cst)) aliq = 'Sem destaque de ICMS próprio; recolhido no DAS';
  else if (SEM_ICMS_PROPRIO.includes(cst)) aliq = 'Sem ICMS próprio destacado';
  else if (n.icms.origem === 'fixa') aliq = `Alíquota fixa de ${pct(n.icms.p)}`;
  else aliq = d === 'in' ? `Alíquota interna de ${uf}` : d === 'out' ? 'Alíquota interestadual pela UF do cliente (4%, 7% ou 12%)' : 'Exportação: ICMS não incide';
  if (n.icms.red && !sn) aliq += ` · base reduzida em ${pct(n.icms.red)}`;
  if (n.icms.pDif && !sn) aliq += ` · ${pct(n.icms.pDif)} diferido`;
  if (sn && (cst === '101' || cst === '201')) aliq += ` · crédito do Simples ${n.icms.pCredSN ? pct(n.icms.pCredSN) : 'do cadastro da empresa'}`;
  L.push({
    cor: COR.icms, rotulo: 'ICMS', codigo: `${sn ? 'CSOSN' : 'CST'} ${cst || '—'}`,
    texto: `· ${rotulo(MODBC, n.icms.modbc).replace(/^\d · /, 'base: ').toLowerCase()}`, sub: aliq,
  });

  if (CST_COM_ST.includes(cst)) {
    L.push({
      cor: COR.icms, rotulo: 'ICMS-ST', texto: rotulo(MODBCST, n.icms.st.modbcst),
      sub: `${n.icms.st.mvaOrig === 'fixa' ? `MVA fixa de ${pct(n.icms.st.mva)}` : 'MVA do cadastro do produto'} · alíquota interna da UF de destino`,
    });
  }
  if (n.tipo === 'S' && n.fin !== '4' && d === 'out' && (n.icms.difal || n.icms.fcp)) {
    L.push({
      cor: COR.icms, rotulo: 'DIFAL e FCP', texto: 'Calcula se o cliente for consumidor final não contribuinte',
      sub: [n.icms.difal ? 'DIFAL para a UF do cliente' : '', n.icms.fcp ? 'FCP quando a UF cobrar' : ''].filter(Boolean).join(' · '),
    });
  }

  L.push(n.ipi.on
    ? {
        cor: COR.ipi, rotulo: 'IPI', codigo: `CST ${n.ipi.cst}`, texto: `· enquadramento ${n.ipi.cEnq || '—'}`,
        sub: n.ipi.origem === 'produto' ? 'Alíquota da TIPI pelo NCM do produto' : n.ipi.origem === 'origem' ? 'Alíquota da nota de origem' : `Alíquota fixa de ${pct(n.ipi.p)}`,
      }
    : { cor: COR.ipi, rotulo: 'IPI', texto: 'Não destaca' });

  (['pis', 'cofins'] as const).forEach((k) => {
    const x = n[k];
    let t: string;
    if (x.origem === 'origem') t = 'Alíquota da nota de origem';
    else if (x.origem === 'fixa') t = x.p ? `Alíquota fixa de ${pct(x.p)}` : 'Sem valor (alíquota zero)';
    else if (sn) t = 'Recolhido no DAS, sem destaque';
    else t = `${pct(PC_PADRAO[regime as 'LP' | 'LR'][k])} do ${REGIME_NOME[regime]} (${PC_PADRAO[regime as 'LP' | 'LR'].tipo})`;
    if (!sn && x.exclICMS && x.origem !== 'fixa') t += ' · sem o ICMS na base';
    L.push({ cor: k === 'pis' ? COR.pis : COR.cofins, rotulo: k.toUpperCase(), codigo: `CST ${sn ? x.cstSN : x.cst}`, texto: '', sub: t });
  });

  if (n.ibs.on) {
    const padrao = empresa && empresa.ibs !== null && empresa.cbs !== null
      ? `IBS ${pct(empresa.ibs * 100)} e CBS ${pct(empresa.cbs * 100)} (${empresa.ano === 2026 ? 'ano de teste' : `padrão de ${empresa.ano}`})`
      : 'Padrão do ano, ainda sem alíquota da CBS cadastrada';
    L.push({
      cor: COR.ibs, rotulo: 'IBS e CBS', codigo: `CST ${n.ibs.cst} · cClassTrib ${n.ibs.cClass || '—'}`, texto: '',
      sub: n.ibs.origem === 'padrao' ? padrao : `IBS ${pct((n.ibs.pUF || 0) + (n.ibs.pMun || 0))} e CBS ${pct(n.ibs.pCBS)}`,
    });
  } else {
    L.push({ cor: COR.ibs, rotulo: 'IBS e CBS', texto: 'Não informa' });
  }

  return L;
}
