// Balancete de Verificação Gerencial — tab "balancete" da tela /financeiro/dre
//
// Fase 4 deprecação legacy (2026-05-21): absorve `/account/trial-balance` legacy.
// Versão GERENCIAL (não contábil-fiscal CFC-compliant). Banner obrigatório.
//
// Estrutura: lista hierárquica do fin_planos_conta com SUM acumulado por código.
// Agrega de filhos pra pais (totaliza nível 1, 2, 3 a partir das folhas nivel 4).
// Skip contas com saldo 0.

import { Card } from '@/Components/ui/card';

export interface BalanceteLinha {
  codigo: string;
  nome: string;
  nivel: number; // 1=raiz, 4=folha tipicamente
  natureza: 'debito' | 'credito';
  tipo: string; // 'ativo'|'passivo'|'patrimonio'|'receita'|'custo'|'despesa'
  saldo: number;
  tipo_saldo: 'D' | 'C';
  indent: number;
  is_folha: boolean;
}

export interface BalanceteData {
  periodo: {
    tipo: string; // 'mes'|'trimestre'|'ano'|'12m'
    label: string;
    inicio_mes: string;
    fim_mes: string;
  };
  linhas: BalanceteLinha[];
  totais: {
    debito: number;
    credito: number;
  };
  meta: {
    business_id: number;
    business_name: string;
  };
}

const brl = (v: number): string =>
  new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  }).format(v ?? 0);

const brlNoSign = (v: number): string => brl(v).replace('R$', '').trim();

export function BalanceteView({ balancete }: { balancete: BalanceteData | null | undefined }) {
  if (!balancete) {
    return (
      <Card className="mx-6 mt-4 mb-4 p-8 text-center text-[13px] text-stone-500">
        Carregando Balancete de Verificação gerencial…
      </Card>
    );
  }

  const { periodo, linhas, totais, meta } = balancete;
  const totalGeral = totais.debito + totais.credito;

  return (
    <>
      {/* Banner aviso versão gerencial — Wagner 2026-05-21 obrigatório */}
      <div
        style={{
          background: 'oklch(0.96 0.04 70)',
          border: '1px solid oklch(0.85 0.10 70)',
          borderRadius: 8,
          padding: '12px 16px',
          margin: '16px 24px',
          fontSize: 13,
          color: 'oklch(0.40 0.13 70)',
        }}
      >
        ⓘ <strong>Versão gerencial</strong> · saldos por plano de contas no
        regime de competência. Para contabilidade fiscal (CFC-compliant)
        consulte balancete do contador externo.
      </div>

      {/* KPI grid — Total D / Total C / período */}
      <div className="fin-stats">
        <div className="fin-stat fin-stat-hero">
          <small>PERÍODO</small>
          <b className="text-[18px]">{periodo.label}</b>
          <span className="fin-stat-hint">{linhas.length} contas com movimento</span>
        </div>
        <div className="fin-stat">
          <small>TOTAL DÉBITO (D)</small>
          <b className="fin-num-pos">{brl(totais.debito)}</b>
          <span className="fin-stat-hint">ativo + custo + despesa</span>
        </div>
        <div className="fin-stat">
          <small>TOTAL CRÉDITO (C)</small>
          <b className="fin-num-pos">{brl(totais.credito)}</b>
          <span className="fin-stat-hint">passivo + receita + PL</span>
        </div>
        <div className="fin-stat">
          <small>TOTAL GERAL</small>
          <b>{brl(totalGeral)}</b>
          <span className="fin-stat-hint">D + C consolidado</span>
        </div>
      </div>

      {/* Card principal: lista hierárquica */}
      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">
              Balancete de Verificação Gerencial
            </div>
            <div className="text-[16px] font-semibold mt-0.5 whitespace-nowrap">
              {periodo.label}
            </div>
          </div>
          <div className="ml-auto text-[11.5px] text-stone-500 whitespace-nowrap shrink-0">
            {meta.business_name}
          </div>
        </div>

        {linhas.length === 0 ? (
          <div className="px-6 py-12 text-center text-[12.5px] text-stone-500">
            Nenhuma conta com movimento no período {periodo.label}.
          </div>
        ) : (
          <table className="w-full text-[12.5px] tabular-nums">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                <th className="pl-6 pr-2 py-2 text-left font-medium w-[110px]">Código</th>
                <th className="px-2 py-2 text-left font-medium">Conta</th>
                <th className="px-2 py-2 text-center font-medium w-[60px]">D/C</th>
                <th className="px-2 py-2 text-right font-medium w-[150px]">Saldo</th>
                <th className="pl-2 pr-6 py-2 text-left font-medium w-[100px]">Tipo</th>
              </tr>
            </thead>
            <tbody>
              {linhas.map((l) => {
                const isRoot = l.nivel <= 2;
                return (
                  <tr
                    key={l.codigo}
                    className={`border-b border-stone-100 ${
                      isRoot ? 'bg-stone-50/60 font-semibold' : 'row-hover'
                    }`}
                  >
                    <td className="pl-6 pr-2 py-1.5 text-stone-500 text-[11.5px] font-mono">
                      {l.codigo}
                    </td>
                    <td
                      className={`px-2 py-1.5 ${isRoot ? 'text-stone-900' : 'text-stone-700'}`}
                      style={{ paddingLeft: 8 + l.indent * 16 }}
                    >
                      {l.nome}
                    </td>
                    <td
                      className={`px-2 py-1.5 text-center text-[11px] font-bold ${
                        l.tipo_saldo === 'D' ? 'text-blue-700' : 'text-amber-700'
                      }`}
                    >
                      {l.tipo_saldo}
                    </td>
                    <td
                      className={`px-2 py-1.5 text-right ${
                        isRoot ? 'font-bold' : ''
                      } ${l.saldo >= 0 ? 'text-stone-900' : 'text-destructive'}`}
                    >
                      {brlNoSign(l.saldo)}
                    </td>
                    <td className="pl-2 pr-6 py-1.5 text-stone-500 text-[11px] uppercase tracking-wider">
                      {l.tipo}
                    </td>
                  </tr>
                );
              })}
              {/* Totalizador */}
              <tr className="border-t-2 border-t-stone-300 bg-stone-50 font-bold text-[13px]">
                <td className="pl-6 pr-2 py-2.5"></td>
                <td className="px-2 py-2.5 text-stone-900">Total geral</td>
                <td className="px-2 py-2.5 text-center text-stone-500 text-[11px]">D + C</td>
                <td className="px-2 py-2.5 text-right text-stone-900">
                  {brlNoSign(totalGeral)}
                </td>
                <td className="pl-2 pr-6 py-2.5 text-stone-500 text-[11px]">
                  D: {brlNoSign(totais.debito)} · C: {brlNoSign(totais.credito)}
                </td>
              </tr>
            </tbody>
          </table>
        )}
      </Card>
    </>
  );
}
