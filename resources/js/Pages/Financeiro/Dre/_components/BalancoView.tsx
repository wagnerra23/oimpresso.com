// Balanço Patrimonial Gerencial — tab "balanco" da tela /financeiro/dre
//
// Fase 4 deprecação legacy (2026-05-21): absorve `/account/balance-sheet` legacy.
// Versão GERENCIAL (não contábil-fiscal CFC-compliant). Banner obrigatório.
//
// Decisão arquitetural Wagner 2026-05-21: usar dados disponíveis em
// fin_titulos.valor_aberto + fin_contas_bancarias.saldo_cached (não tentar
// derivar PL via plano de contas — F1 simplificado).

import { Card } from '@/Components/ui/card';

interface AtivoCirculante {
  saldo_bancos: number;
  contas_a_receber: number;
  total: number;
}

interface PassivoCirculante {
  contas_a_pagar: number;
  total: number;
}

export interface BalancoData {
  data_referencia: string; // 'YYYY-MM-DD'
  ativo_circulante: AtivoCirculante;
  passivo_circulante: PassivoCirculante;
  ativo_total: number;
  passivo_total: number;
  patrimonio_liquido: number;
  equacao_ok: boolean;
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

function formatDataPtBr(yyyymmdd: string): string {
  // 'YYYY-MM-DD' → 'DD/MM/YYYY' (display PT-BR)
  const parts = yyyymmdd.split('-');
  if (parts.length !== 3) return yyyymmdd;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

export function BalancoView({ balanco }: { balanco: BalancoData | null | undefined }) {
  if (!balanco) {
    return (
      <Card className="mx-6 mt-4 mb-4 p-8 text-center text-[13px] text-stone-500">
        Carregando Balanço Patrimonial gerencial…
      </Card>
    );
  }

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
        ⓘ <strong>Versão gerencial</strong> · usa dados disponíveis em contas a
        pagar/receber + saldos sincronizados. Para contabilidade fiscal
        (CFC-compliant) consulte balancete do contador externo.
      </div>

      {/* KPI grid — 4 cards: Ativo / Passivo / PL / Equação */}
      <div className="fin-stats">
        <div className="fin-stat fin-stat-hero">
          <small>ATIVO TOTAL</small>
          <b className="fin-num-pos">{brl(balanco.ativo_total)}</b>
          <span className="fin-stat-hint">circulante</span>
        </div>
        <div className="fin-stat">
          <small>PASSIVO TOTAL</small>
          <b className="fin-num-neg">{brl(balanco.passivo_total)}</b>
          <span className="fin-stat-hint">circulante</span>
        </div>
        <div className="fin-stat">
          <small>PATRIMÔNIO LÍQUIDO</small>
          <b className={balanco.patrimonio_liquido >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>
            {brl(balanco.patrimonio_liquido)}
          </b>
          <span className="fin-stat-hint">ativo − passivo</span>
        </div>
        <div className="fin-stat">
          <small>EQUAÇÃO PATRIMONIAL</small>
          <b className={balanco.equacao_ok ? 'fin-num-pos' : 'fin-num-neg'}>
            {balanco.equacao_ok ? 'OK' : 'ERRO'}
          </b>
          <span className="fin-stat-hint">A = P + PL</span>
        </div>
      </div>

      {/* Card principal: Balanço em 2 colunas (Ativo | Passivo+PL) */}
      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">
              Balanço Patrimonial Gerencial
            </div>
            <div className="text-[16px] font-semibold mt-0.5 whitespace-nowrap">
              Posição em {formatDataPtBr(balanco.data_referencia)}
            </div>
          </div>
          <div className="ml-auto text-[11.5px] text-stone-500 whitespace-nowrap shrink-0">
            {balanco.meta.business_name}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-0">
          {/* ATIVO */}
          <div className="border-r border-stone-200">
            <div className="px-6 py-3 bg-stone-50/60 border-b border-stone-200">
              <div className="text-[11px] uppercase tracking-widest font-semibold text-stone-700">
                Ativo
              </div>
            </div>
            <table className="w-full text-[12.5px] tabular-nums">
              <tbody>
                <tr className="border-b border-stone-100">
                  <td className="pl-6 pr-2 py-2 font-medium text-stone-900">
                    Ativo Circulante
                  </td>
                  <td className="pr-6 py-2 text-right font-semibold">
                    {brl(balanco.ativo_circulante.total)}
                  </td>
                </tr>
                <tr className="border-b border-stone-100 row-hover">
                  <td className="pl-12 pr-2 py-1.5 text-stone-600">
                    Saldo em Contas Bancárias
                  </td>
                  <td className="pr-6 py-1.5 text-right text-stone-700">
                    {brl(balanco.ativo_circulante.saldo_bancos)}
                  </td>
                </tr>
                <tr className="border-b border-stone-100 row-hover">
                  <td className="pl-12 pr-2 py-1.5 text-stone-600">
                    Contas a Receber
                  </td>
                  <td className="pr-6 py-1.5 text-right text-stone-700">
                    {brl(balanco.ativo_circulante.contas_a_receber)}
                  </td>
                </tr>
                <tr className="border-y-2 border-stone-200 bg-stone-50">
                  <td className="pl-6 pr-2 py-2.5 font-semibold">
                    Total do Ativo
                  </td>
                  <td className="pr-6 py-2.5 text-right font-bold text-[14px] text-success">
                    {brl(balanco.ativo_total)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          {/* PASSIVO + PL */}
          <div>
            <div className="px-6 py-3 bg-stone-50/60 border-b border-stone-200">
              <div className="text-[11px] uppercase tracking-widest font-semibold text-stone-700">
                Passivo + Patrimônio Líquido
              </div>
            </div>
            <table className="w-full text-[12.5px] tabular-nums">
              <tbody>
                <tr className="border-b border-stone-100">
                  <td className="pl-6 pr-2 py-2 font-medium text-stone-900">
                    Passivo Circulante
                  </td>
                  <td className="pr-6 py-2 text-right font-semibold">
                    {brl(balanco.passivo_circulante.total)}
                  </td>
                </tr>
                <tr className="border-b border-stone-100 row-hover">
                  <td className="pl-12 pr-2 py-1.5 text-stone-600">
                    Contas a Pagar
                  </td>
                  <td className="pr-6 py-1.5 text-right text-stone-700">
                    {brl(balanco.passivo_circulante.contas_a_pagar)}
                  </td>
                </tr>
                <tr className="border-b border-stone-100">
                  <td className="pl-6 pr-2 py-2 font-medium text-stone-900">
                    Patrimônio Líquido
                  </td>
                  <td
                    className={`pr-6 py-2 text-right font-semibold ${
                      balanco.patrimonio_liquido >= 0 ? 'text-success' : 'text-destructive'
                    }`}
                  >
                    {brl(balanco.patrimonio_liquido)}
                  </td>
                </tr>
                <tr className="border-b border-stone-100 row-hover">
                  <td className="pl-12 pr-2 py-1.5 text-stone-600">
                    Derivado (Ativo − Passivo)
                  </td>
                  <td className="pr-6 py-1.5 text-right text-stone-500 text-[11.5px]">
                    F1 simplificado
                  </td>
                </tr>
                <tr className="border-y-2 border-stone-200 bg-stone-50">
                  <td className="pl-6 pr-2 py-2.5 font-semibold">
                    Total Passivo + PL
                  </td>
                  <td
                    className={`pr-6 py-2.5 text-right font-bold text-[14px] ${
                      balanco.passivo_total + balanco.patrimonio_liquido >= 0
                        ? 'text-success'
                        : 'text-destructive'
                    }`}
                  >
                    {brl(balanco.passivo_total + balanco.patrimonio_liquido)}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        {/* Footer com validação da equação */}
        <div className="px-6 py-3 border-t border-stone-200 bg-stone-50/40 flex items-center gap-3 text-[12px]">
          <span
            className={`inline-flex items-center gap-1.5 font-medium ${
              balanco.equacao_ok ? 'text-success' : 'text-destructive'
            }`}
          >
            <span className="w-2 h-2 rounded-full bg-current" />
            {balanco.equacao_ok ? 'Equação patrimonial OK' : 'Equação patrimonial inconsistente'}
          </span>
          <span className="text-stone-500">
            Ativo (R$ {balanco.ativo_total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}) = Passivo
            (R$ {balanco.passivo_total.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}) + PL (R${' '}
            {balanco.patrimonio_liquido.toLocaleString('pt-BR', { minimumFractionDigits: 2 })})
          </span>
        </div>
      </Card>
    </>
  );
}
