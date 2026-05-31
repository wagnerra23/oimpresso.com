// @memcofre tela=/financeiro/extrato/{contaId} module=Financeiro

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { ArrowDownCircle, ArrowUpCircle, RefreshCw, ArrowLeft } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { piiMask } from '@/Pages/Financeiro/Cobranca/_lib/cobranca-shared';

interface Lancamento {
  id: number;
  data: string;
  valor: number;
  tipo: 'C' | 'D';
  descricao: string;
  contraparte_documento: string | null;
  contraparte_nome: string | null;
}

interface Props {
  conta: {
    id: number;
    nome: string;
    banco_nome: string;
    numero_conta: string | null;
    saldo_cached: number | null;
    saldo_atualizado_em: string | null;
  };
  lancamentos: Lancamento[];
  filtros: { from: string; to: string };
  totais: { creditos: number; debitos: number; count: number };
}

const fmtBrl = (v: number | null) =>
  v === null ? '—' : v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const fmtDate = (iso: string) => {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};

function Index({ conta, lancamentos, filtros, totais }: Props) {
  const [from, setFrom] = useState(filtros.from);
  const [to, setTo] = useState(filtros.to);

  const aplicarFiltro = () => {
    router.get(
      route('financeiro.extrato.index', conta.id),
      { from, to },
      { preserveScroll: true, preserveState: true }
    );
  };

  return (
    // Wave 4 (2026-05-31) — header canon via <PageHeader> + FinanceiroSubNav (substitui os-page-h inline)
    <div className="fin-curadoria vendas-aplus p-6 space-y-6">
      <PageHeader
        icon="landmark"
        title={`Extrato · ${conta.banco_nome}`}
        description={`${conta.nome}${conta.numero_conta ? ` · Conta ${conta.numero_conta}` : ''}`}
        action={
          <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
            <FinanceiroSubNav active="extrato" hidePrimary />
            <Button variant="outline" size="sm" onClick={() => router.visit(route('financeiro.contas-bancarias.index'))}>
              <ArrowLeft className="h-4 w-4 mr-1.5" /> Voltar pra contas
            </Button>
          </div>
        }
      />

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Saldo atual</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{fmtBrl(conta.saldo_cached)}</div>
            <p className="text-xs text-muted-foreground">
              {conta.saldo_atualizado_em
                ? `Sincronizado em ${new Date(conta.saldo_atualizado_em).toLocaleString('pt-BR')}`
                : 'Sem sync'}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <ArrowDownCircle className="h-4 w-4 text-emerald-600" /> Créditos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-600">{fmtBrl(totais.creditos)}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <ArrowUpCircle className="h-4 w-4 text-destructive" /> Débitos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-destructive">{fmtBrl(totais.debitos)}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Lançamentos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{totais.count}</div>
            <p className="text-xs text-muted-foreground">no período</p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardContent className="pt-6 flex flex-wrap items-end gap-3">
          <div className="space-y-1">
            <label className="text-xs text-muted-foreground">De</label>
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
          </div>
          <div className="space-y-1">
            <label className="text-xs text-muted-foreground">Até</label>
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
          </div>
          <Button onClick={aplicarFiltro} size="sm">
            <RefreshCw className="h-4 w-4 mr-2" /> Aplicar filtro
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="pt-6">
          {lancamentos.length === 0 ? (
            <div className="text-center py-12 text-muted-foreground">
              <p>Nenhum lançamento no período {fmtDate(filtros.from)} a {fmtDate(filtros.to)}.</p>
              <p className="text-xs mt-2">
                Sync diário roda às 07:00 BRT. Se a conta foi conectada agora, aguarde o próximo ciclo.
              </p>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead className="text-left text-xs text-muted-foreground border-b">
                <tr>
                  <th className="py-2 pr-4 font-medium">Data</th>
                  <th className="py-2 pr-4 font-medium">Descrição</th>
                  <th className="py-2 pr-4 font-medium">Contraparte</th>
                  <th className="py-2 pr-4 font-medium text-right">Valor</th>
                </tr>
              </thead>
              <tbody>
                {lancamentos.map((l) => (
                  <tr key={l.id} className="border-b hover:bg-muted/30">
                    <td className="py-2 pr-4 whitespace-nowrap">{fmtDate(l.data)}</td>
                    <td className="py-2 pr-4">{l.descricao}</td>
                    <td className="py-2 pr-4 text-muted-foreground">
                      {l.contraparte_nome ?? '—'}
                      {l.contraparte_documento ? (
                        <span className="font-mono"> · {piiMask(l.contraparte_documento)}</span>
                      ) : ''}
                    </td>
                    <td
                      className={`py-2 pr-4 text-right font-mono whitespace-nowrap ${
                        l.tipo === 'C' ? 'text-emerald-600' : 'text-destructive'
                      }`}
                    >
                      {l.tipo === 'C' ? '+' : '-'} {fmtBrl(l.valor)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default Index;
