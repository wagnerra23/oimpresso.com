// @memcofre tela=/financeiro/centros-custo module=Financeiro
// US-FIN-075 — cadastro de centros de custo. Fonte de design:
// prototipo-ui/cowork/Wagner/financeiro-centro-custo-orcado.html (aba "Centros de custo").

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Input } from '@/Components/ui/input';
import { toast } from 'sonner';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import { CentroCustoSheet, type CentroCusto } from './_components/CentroCustoSheet';

interface Centro extends CentroCusto {
  ativo: boolean;
  titulos_mes: number;
  rateado_mes: number;
}

interface Props {
  mes: string;
  centros: Centro[];
}

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function Index({ mes, centros }: Props) {
  const [busca, setBusca] = useState('');
  const [editando, setEditando] = useState<Centro | null>(null);
  const [criando, setCriando] = useState(false);

  const visiveis = useMemo(() => {
    const q = busca.trim().toLowerCase();
    return q ? centros.filter((c) => `${c.codigo ?? ''} ${c.nome}`.toLowerCase().includes(q)) : centros;
  }, [busca, centros]);

  const alternar = (c: Centro) => {
    router.post(`/financeiro/centros-custo/${c.id}/toggle`, {}, {
      preserveScroll: true,
      only: ['centros', 'flash'],
      onSuccess: () => toast.success(c.ativo ? 'Centro inativado' : 'Centro ativado'),
      onError: () => toast.error('Não foi possível mudar a situação'),
    });
  };

  const [ano, m] = mes.split('-');

  return (
    <>
      <div className="fin-curadoria vendas-aplus p-6 max-w-5xl mx-auto space-y-6">
        <div data-contract="cabecalho">
        <PageHeader
          title="Centros de custo"
          subtitle={<>Onde o dinheiro é gasto ou ganho: rateie o título entre centros e filtre DRE, fluxo e orçamento</>}
        >
          <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
            <FinanceiroSubNav active="centros-custo" hidePrimary />
            <PageHeaderPrimary label="Novo centro" onClick={() => setCriando(true)} />
          </div>
        </PageHeader>
        </div>

        <div className="rounded-md border bg-card">
          <div data-contract="filtros" className="flex flex-wrap items-center gap-3 px-4 py-3 border-b">
            <span className="text-sm text-muted-foreground">
              {centros.length} {centros.length === 1 ? 'centro' : 'centros'} · uso em {m}/{ano}
            </span>
            <Input
              id="busca-centro"
              aria-label="Buscar centro de custo"
              placeholder="Buscar centro…"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              className="ml-auto h-8 w-[200px]"
            />
          </div>
          <div data-contract="lista" className="overflow-x-auto">
            <table className="w-full text-sm tabular-nums">
              <thead className="bg-muted/50">
                <tr className="text-left">
                  <th className="px-4 py-2 font-medium w-[100px]">Código</th>
                  <th className="px-4 py-2 font-medium">Nome</th>
                  <th className="px-4 py-2 font-medium w-[110px]">Situação</th>
                  <th className="px-4 py-2 font-medium text-right w-[130px]">Títulos no mês</th>
                  <th className="px-4 py-2 font-medium text-right w-[150px]">Rateado no mês</th>
                  <th className="px-4 py-2 w-[140px]" aria-label="Ações"></th>
                </tr>
              </thead>
              <tbody>
                {visiveis.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                      {centros.length === 0
                        ? 'Nenhum centro de custo ainda. Crie o primeiro para começar a ratear títulos.'
                        : 'Nenhum centro encontrado com essa busca.'}
                    </td>
                  </tr>
                )}
                {visiveis.map((c) => (
                  <tr key={c.id} className="border-t hover:bg-muted/30">
                    <td className="px-4 py-2.5 font-mono text-xs text-muted-foreground">{c.codigo ?? '—'}</td>
                    <td className="px-4 py-2.5 font-medium">{c.nome}</td>
                    <td className="px-4 py-2.5">
                      <span
                        className={`inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full ${
                          c.ativo ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'
                        }`}
                      >
                        <span className="w-1.5 h-1.5 rounded-full bg-current" aria-hidden />
                        {c.ativo ? 'Ativo' : 'Inativo'}
                      </span>
                    </td>
                    <td className="px-4 py-2.5 text-right">
                      {c.titulos_mes > 0 ? c.titulos_mes : <span className="text-muted-foreground">—</span>}
                    </td>
                    <td className="px-4 py-2.5 text-right">
                      {c.rateado_mes > 0 ? brl(c.rateado_mes) : <span className="text-muted-foreground">—</span>}
                    </td>
                    <td className="px-4 py-2.5 text-right text-xs whitespace-nowrap">
                      <button type="button" className="text-muted-foreground hover:text-foreground underline underline-offset-2" onClick={() => setEditando(c)}>
                        editar
                      </button>
                      <span className="text-muted-foreground"> · </span>
                      <button type="button" className="text-muted-foreground hover:text-foreground underline underline-offset-2" onClick={() => alternar(c)}>
                        {c.ativo ? 'inativar' : 'ativar'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="px-4 py-3 border-t text-xs text-muted-foreground">
            Centro com título rateado não pode ser excluído, só inativado. Inativo some da escolha no título,
            mas o histórico continua nos relatórios.
          </p>
        </div>
      </div>

      {(editando || criando) && (
        <CentroCustoSheet
          centro={editando}
          onClose={() => {
            setEditando(null);
            setCriando(false);
          }}
        />
      )}
    </>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Centros de custo · Financeiro" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Centros de custo' }]}>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);
export default Index;
