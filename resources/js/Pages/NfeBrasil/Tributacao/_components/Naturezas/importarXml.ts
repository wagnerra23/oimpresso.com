// "Importar nota de referência" — lê o XML de uma NF-e/NFC-e já emitida e preenche a natureza com os códigos do
// 1º item (UC-NFTR-73..76). Porta de `lerXml()` do protótipo `prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`.
//
// Roda no navegador, como no protótipo: o XML traz nome e documento do destinatário e não precisa ir ao servidor. A
// natureza preenchida passa pela mesma conferência do servidor (POST /naturezas/conferir) antes de poder ser salva.
// Guarda só códigos e a origem de cada alíquota — nenhum valor da nota (vProd, vBC, vICMS, totais).
import type { Passo } from './EditorCampos';
import { DESTINOS, type Destino } from './lancamento';
import { FINS, MODBC, MODBCST, PC_PADRAO, REGIME_NOME, rotulo, type EmpresaResumo, type Natureza, type Regime } from './natureza';

export type Situacao = 'ok' | 'ajuste' | 'nao';

export interface LinhaImportacao {
  passo: Exclude<Passo, 'rev'>;
  campo: string;
  tag: string;
  valor: string;
  situacao: Situacao;
  obs: string;
}

export interface Importacao {
  natureza: Natureza;
  info: { natOp: string; nNF: string; emitente: string; itens: number };
  linhas: LinhaImportacao[];
  /** Passos que o XML preencheu (a barra mostra "do XML, revisar" até a pessoa abrir). */
  passos: Exclude<Passo, 'rev'>[];
}

function pct(v: number | null): string {
  return `${(v ?? 0).toLocaleString('pt-BR', { maximumFractionDigits: 4 })}%`;
}

/** Conversão do CFOP da operação original para o da devolução (últimos 3 dígitos), como no protótipo. */
const DEVOLUCAO: Record<string, string> = { '101': '201', '102': '202', '401': '411', '403': '411', '405': '411' };
const DESTINO_DO_DIGITO: Record<string, Destino> = { '1': 'in', '5': 'in', '2': 'out', '6': 'out', '3': 'ext', '7': 'ext' };
const OUTRO_ESTADO: Record<string, string> = { '5': '6', '6': '5', '1': '2', '2': '1' };

const nomeDestino = (d: Destino) => DESTINOS.find((x) => x.value === d)!.label;

export function lerNotaReferencia(
  xml: string, base: Natureza, regime: Regime, empresa?: EmpresaResumo | null,
): { erro: string } | Importacao {
  const doc = new DOMParser().parseFromString(xml, 'application/xml');
  if (doc.getElementsByTagName('parsererror').length) {
    return { erro: 'O arquivo não é um XML válido. Confira se é o XML da nota (não o PDF do DANFE).' };
  }
  const q = (ctx: Document | Element | null, tag: string): Element | null => {
    if (!ctx) return null;
    const l = ctx.getElementsByTagNameNS('*', tag);
    return (l.length ? l : ctx.getElementsByTagName(tag))[0] ?? null;
  };
  const v = (ctx: Document | Element | null, tag: string) => q(ctx, tag)?.textContent?.trim() ?? '';
  const num = (s: string): number | null => {
    const n = parseFloat(s);
    return Number.isNaN(n) ? null : Math.round(n * 10000) / 10000;
  };

  const ide = q(doc, 'ide');
  const dets = Array.from(doc.getElementsByTagNameNS('*', 'det'));
  if (!ide || !dets.length) return { erro: 'Não encontrei as tags ide e det. O arquivo precisa ser o XML de uma NF-e ou NFC-e.' };

  const N: Natureza = structuredClone(base);
  const det = dets[0]!;
  const linhas: LinhaImportacao[] = [];
  const passos = new Set<Exclude<Passo, 'rev'>>();
  const add = (passo: LinhaImportacao['passo'], campo: string, tag: string, valor: string, situacao: Situacao, obs = '') => {
    linhas.push({ passo, campo, tag, valor, situacao, obs });
    if (situacao !== 'nao') passos.add(passo);
  };
  const sn = regime === 'SN';
  const info = { natOp: v(ide, 'natOp'), nNF: v(ide, 'nNF'), emitente: v(q(doc, 'emit'), 'xNome'), itens: dets.length };

  // Natureza — a finalidade e o tipo escolhidos no passo 1 ganham quando a pessoa já disse que é devolução.
  const devolucaoEscolhida = base.fin === '4';
  if (info.natOp) {
    if (N.nome.trim()) add('nat', 'Nome', 'natOp', info.natOp, 'ajuste', 'Mantido o nome digitado no passo 1.');
    else { N.nome = info.natOp.slice(0, 80); add('nat', 'Nome', 'natOp', info.natOp, 'ok'); }
  }
  const tp = v(ide, 'tpNF');
  if (tp) {
    const lido = `${tp} · ${tp === '0' ? 'entrada' : 'saída'}`;
    if (devolucaoEscolhida) add('nat', 'Tipo da nota', 'tpNF', lido, 'ajuste', 'Mantido o tipo escolhido no passo 1; a nota importada é a original.');
    else { N.tipo = tp === '0' ? 'E' : 'S'; add('nat', 'Tipo da nota', 'tpNF', lido, 'ok'); }
  }
  const fin = v(ide, 'finNFe');
  if (fin) {
    if (devolucaoEscolhida && fin !== '4') add('nat', 'Finalidade', 'finNFe', fin, 'ajuste', 'Mantida a devolução escolhida no passo 1; a nota importada é a original.');
    else if (['1', '2', '3', '4'].includes(fin)) { N.fin = fin as Natureza['fin']; add('nat', 'Finalidade', 'finNFe', rotulo(FINS, fin), 'ok'); }
  }
  if (q(ide, 'refNFe')) { N.ref = true; add('nat', 'Nota referenciada', 'NFref', 'presente', 'ok'); }
  if (N.fin === '4') N.ref = true;
  const devolucaoDaOriginal = N.fin === '4' && fin !== '4';

  // CFOP do 1º item, no destino que o 1º dígito indica.
  const lido = v(det, 'CFOP');
  if (/^\d{4}$/.test(lido)) {
    let cf = lido;
    const fimDevolucao = DEVOLUCAO[cf.slice(1)];
    if (devolucaoDaOriginal && fimDevolucao) cf = cf.slice(0, 1) + fimDevolucao;
    // Entrada usa 1/2/3 e saída 5/6/7. A conversão da entrada é a do protótipo; a da saída (devolução de compra
    // importando a compra original, 1102 → 5202) o protótipo não fazia e deixava um CFOP de entrada na saída.
    if (N.tipo === 'E' && '567'.includes(cf[0]!)) cf = String(Number(cf[0]) - 4) + cf.slice(1);
    if (devolucaoDaOriginal && N.tipo === 'S' && '123'.includes(cf[0]!)) cf = String(Number(cf[0]) + 4) + cf.slice(1);
    const dest = DESTINO_DO_DIGITO[cf[0]!] ?? 'in';
    N.cfop[dest] = cf;
    add('cfop', nomeDestino(dest), 'CFOP', lido, cf === lido ? 'ok' : 'ajuste', cf === lido ? '' : `Convertido para ${cf} (devolução da operação lida).`);
    const outro: Destino = dest === 'in' ? 'out' : 'in';
    const dOut = OUTRO_ESTADO[cf[0]!];
    if (dOut && !N.cfop[outro]) {
      N.cfop[outro] = dOut + cf.slice(1);
      add('cfop', nomeDestino(outro), '—', N.cfop[outro], 'ajuste', 'Deduzido do CFOP lido; confira.');
    }
    const outros = [...new Set(dets.slice(1).map((d) => v(d, 'CFOP')).filter((c) => c && c !== lido))];
    if (outros.length) add('cfop', 'Outros itens', 'CFOP', outros.join(', '), 'ajuste', 'Itens com CFOP diferente do primeiro; usei o do item 1.');
  } else add('cfop', 'CFOP', 'CFOP', '', 'nao', 'Tag não encontrada.');

  // ICMS — o grupo (ICMS00, ICMS20, ICMSSN102…) é o 1º filho de <ICMS>.
  const g = q(det, 'ICMS')?.firstElementChild ?? null;
  if (g) {
    const cst = v(g, 'CST');
    const csosn = v(g, 'CSOSN');
    if (cst) { N.icms.cst = cst; add('icms', 'CST do ICMS', `${g.localName}/CST`, cst, sn ? 'ajuste' : 'ok', sn ? 'A empresa está no Simples; guardado no CST, preencha o CSOSN.' : ''); }
    if (csosn) { N.icms.csosn = csosn; add('icms', 'CSOSN', `${g.localName}/CSOSN`, csosn, sn ? 'ok' : 'ajuste', sn ? '' : 'Nota de empresa do Simples; guardado no CSOSN, preencha o CST.'); }
    const mbc = v(g, 'modBC');
    if (mbc) { N.icms.modbc = mbc; add('icms', 'Base de cálculo', 'modBC', rotulo(MODBC, mbc), 'ok'); }
    const pI = num(v(g, 'pICMS'));
    if (pI !== null) {
      N.icms.origem = N.fin === '4' ? 'origem' : 'uf';
      add('icms', 'Alíquota do ICMS', 'pICMS', pct(pI), 'ajuste', N.fin === '4'
        ? 'Devolução: guardado como “da nota de origem”.' : 'Guardado como “pela UF”: a alíquota muda com o destino do cliente.');
    }
    const red = num(v(g, 'pRedBC'));
    if (red) { N.icms.red = red; add('icms', 'Redução da base', 'pRedBC', pct(red), 'ok'); }
    const dif = num(v(g, 'pDif'));
    if (dif) { N.icms.pDif = dif; add('icms', 'Diferimento', 'pDif', pct(dif), 'ok'); }
    const cr = num(v(g, 'pCredSN'));
    if (cr) { N.icms.pCredSN = cr; add('icms', 'Crédito do Simples', 'pCredSN', pct(cr), 'ok'); }
    const mst = v(g, 'modBCST');
    if (mst) { N.icms.st.modbcst = mst; add('icms', 'Base do ST', 'modBCST', rotulo(MODBCST, mst), 'ok'); }
    const mva = num(v(g, 'pMVAST'));
    if (mva) {
      N.icms.st.mvaOrig = 'fixa'; N.icms.st.mva = mva;
      add('icms', 'MVA', 'pMVAST', pct(mva), 'ok', 'Guardada como fixa; se varia por produto, troque para “da regra do NCM”.');
    }
    const rst = num(v(g, 'pRedBCST'));
    if (rst) { N.icms.st.redB = rst; add('icms', 'Redução da base do ST', 'pRedBCST', pct(rst), 'ok'); }
  } else add('icms', 'ICMS', 'ICMS', '', 'nao', 'Grupo não encontrado.');

  // IPI
  const ipi = q(det, 'IPI');
  if (ipi) {
    N.ipi.on = true;
    const ce = v(ipi, 'cEnq');
    if (ce) N.ipi.cEnq = ce;
    const ic = v(ipi, 'CST');
    if (ic) N.ipi.cst = ic;
    add('ipi', 'IPI', 'IPI/CST', ic + (ce ? ` · cEnq ${ce}` : ''), 'ok');
    const pIp = num(v(ipi, 'pIPI'));
    if (pIp !== null) {
      N.ipi.origem = N.fin === '4' ? 'origem' : 'produto';
      add('ipi', 'Alíquota do IPI', 'pIPI', pct(pIp), 'ajuste', N.fin === '4'
        ? 'Guardado como “da nota de origem”.' : 'Guardado como “da regra do NCM”: muda com o NCM do produto.');
    }
  } else { N.ipi.on = false; add('ipi', 'IPI', 'IPI', '', 'nao', 'A nota não tem IPI; o passo fica desligado.'); }

  // PIS e COFINS
  (['PIS', 'COFINS'] as const).forEach((T) => {
    const k = T === 'PIS' ? 'pis' : 'cofins';
    const el = q(det, T);
    if (!el) { add('pc', T, T, '', 'nao', 'Grupo não encontrado.'); return; }
    const c = v(el, 'CST');
    if (c) { if (sn) N[k].cstSN = c; else N[k].cst = c; }
    const p = num(v(el, `p${T}`));
    const padrao = sn ? null : PC_PADRAO[regime as 'LP' | 'LR'][k];
    const tag = `${T}/CST · p${T}`;
    if (N.fin === '4' && p) { N[k].origem = 'origem'; add('pc', T, tag, `${c} · ${pct(p)}`, 'ajuste', 'Devolução: guardado como “da nota de origem”.'); }
    else if (p && p === padrao) { N[k].origem = 'regime'; add('pc', T, tag, `${c} · ${pct(p)}`, 'ok', `Igual ao padrão do ${REGIME_NOME[regime]}.`); }
    else if (p) { N[k].origem = 'fixa'; N[k].p = p; add('pc', T, tag, `${c} · ${pct(p)}`, 'ok', 'Guardado como alíquota fixa.'); }
    else { N[k].origem = 'fixa'; N[k].p = 0; add('pc', T, `${T}/CST`, c, 'ok', 'Sem alíquota na nota.'); }
  });

  // IBS e CBS — "padrão do ano" quando as alíquotas da nota batem com as do ano na configuração da empresa (a mesma
  // fonte que o motor usa, `nfebrasil.ibs_cbs_por_ano`, que chega pela faixa de resumo). O protótipo fixava 0,1%/0,9%.
  const ib = q(det, 'IBSCBS');
  if (ib) {
    N.ibs.on = true;
    N.ibs.cst = v(ib, 'CST') || N.ibs.cst;
    N.ibs.cClass = v(ib, 'cClassTrib');
    add('ibs', 'CST e cClassTrib', 'IBSCBS/CST · cClassTrib', `${N.ibs.cst} · ${N.ibs.cClass || '—'}`, 'ok');
    const u = num(v(ib, 'pIBSUF'));
    const mu = num(v(ib, 'pIBSMun'));
    const cb = num(v(ib, 'pCBS'));
    const anoIbs = empresa?.ibs != null ? Math.round(empresa.ibs * 1000000) / 10000 : null;
    const anoCbs = empresa?.cbs != null ? Math.round(empresa.cbs * 1000000) / 10000 : null;
    const tag = 'pIBSUF · pIBSMun · pCBS';
    if (anoIbs !== null && anoCbs !== null && u === anoIbs && (mu ?? 0) === 0 && cb === anoCbs) {
      N.ibs.origem = 'padrao';
      add('ibs', 'Alíquotas', tag, `${pct(u)} · ${pct(0)} · ${pct(cb)}`, 'ok', empresa?.ano === 2026 ? 'Iguais ao padrão do ano de teste.' : `Iguais ao padrão de ${empresa?.ano}.`);
    } else if (u !== null || cb !== null) {
      N.ibs.origem = 'fixa'; N.ibs.pUF = u ?? 0; N.ibs.pMun = mu ?? 0; N.ibs.pCBS = cb ?? 0;
      add('ibs', 'Alíquotas', tag, `${pct(u)} · ${pct(mu)} · ${pct(cb)}`, 'ok', 'Guardadas como fixas.');
    }
  } else { N.ibs.on = false; add('ibs', 'IBS e CBS', 'IBSCBS', '', 'nao', 'A nota não tem os grupos da reforma; o passo fica desligado.'); }

  const csts = [...new Set(dets.map((d) => {
    const gg = q(d, 'ICMS')?.firstElementChild ?? null;
    return gg ? v(gg, 'CST') || v(gg, 'CSOSN') : '';
  }).filter(Boolean))];
  if (csts.length > 1) add('icms', 'Outros itens', 'CST', csts.join(', '), 'ajuste', 'A nota tem itens com CST diferente; usei o do item 1.');

  return { natureza: N, info, linhas, passos: [...passos] };
}
