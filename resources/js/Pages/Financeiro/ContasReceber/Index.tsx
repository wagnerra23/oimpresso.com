// @memcofre tela=/financeiro/contas-receber module=Financeiro

import AppShellV2 from '@/Layouts/AppShellV2';
import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Receipt, AlertTriangle, CheckCircle2, Circle, Hourglass, BellRing } from 'lucide-react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { toast } from 'sonner';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';

interface BoletoInfo {
  id: number;
  status: string;
  linha_digitavel: string | null;
  nosso_numero: string | null;
}

interface Titulo {
  id: number;
  numero: string;
  cliente_descricao: string | null;
  cliente_id: number | null;
  valor_total: string;
  valor_aberto: string;
  vencimento: string | null;
  status: 'aberto' | 'parcial' | 'quitado' | 'cancelado';
  origem: string;
  origem_id: number | null;
  boleto: BoletoInfo | null;
}

interface Lembrete {
  id: number;
  titulo_id: number;
  numero: string;
  cliente: string | null;
  vencimento: string | null;
  etapa_dias: number;
  canal: string;
  status: 'enviado' | 'nao_enviado';
  motivo: string | null;
  tarefa: boolean;
  quando: string;
}

interface Lembretes {
  regua: { ativo: boolean; dias: number[] };
  itens: Lembrete[];
}

interface Props {
  titulos: Titulo[];
  filtros: { status: string | null; vence_em: string | null };
  /** Inertia::optional — só chega quando o painel abre (reload com only: ['lembretes']). */
  lembretes?: Lembretes;
}

// Motivos gravados pelo ReguaCobrancaJob quando o lembrete não sai.
const MOTIVOS: Record<string, string> = {
  opt_out_email: 'Cliente recusou e-mail',
  sem_email: 'Cliente sem e-mail',
  sem_contato: 'Título sem cliente',
  falha_envio: 'Falha no envio',
  pendente: 'Em processamento',
};

const STATUS_LABELS: Record<string, { label: string; color: string; icon: typeof Circle }> = {
  aberto: { label: 'Aberto', color: 'bg-blue-100 text-blue-900 dark:bg-blue-900/30 dark:text-blue-200', icon: Circle },
  parcial: { label: 'Parcial', color: 'bg-warning-soft text-warning-fg', icon: Hourglass },
  quitado: { label: 'Quitado', color: 'bg-success-soft text-success-fg', icon: CheckCircle2 },
  cancelado: { label: 'Cancelado', color: 'bg-muted text-muted-foreground', icon: AlertTriangle },
};

function formatBRL(v: string | number) {
  const n = typeof v === 'string' ? parseFloat(v) : v;
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatDate(v: string | null) {
  if (!v) return '—';
  const [y, m, d] = v.split('-');
  return `${d}/${m}/${y}`;
}

function StatusPill({ status }: { status: Titulo['status'] }) {
  const cfg = STATUS_LABELS[status];
  const Icon = cfg.icon;
  return (
    <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded ${cfg.color}`}>
      <Icon className="h-3 w-3" /> {cfg.label}
    </span>
  );
}

function formatDateTime(v: string) {
  const [data, hora = ''] = v.split(' ');
  return `${formatDate(data)} ${hora.slice(0, 5)}`.trim();
}

/** Lembretes da régua de cobrança (US-FIN-071): o que saiu, o que não saiu e por quê. */
function LembretesPainel({ aberto, onFechar, lembretes }: { aberto: boolean; onFechar: () => void; lembretes?: Lembretes }) {
  const regua = lembretes?.regua;
  return (
    <Sheet open={aberto} onOpenChange={(v) => !v && onFechar()}>
      <SheetContent side="right" className="w-full sm:max-w-lg overflow-y-auto" data-contract="lembretes-painel">
        <SheetHeader>
          <SheetTitle>Lembretes de cobrança</SheetTitle>
          <SheetDescription>
            {!regua
              ? 'Carregando…'
              : regua.ativo
                ? `Régua ligada: e-mail ${regua.dias.map((d) => `D+${d}`).join(', ')} após o vencimento.`
                : 'Régua desligada: nenhum lembrete sai até ligar.'}{' '}
            {regua && (
              <a href="/business/settings" className="underline">Configurar</a>
            )}
          </SheetDescription>
        </SheetHeader>
        <div className="px-4 pb-4">
          {lembretes && lembretes.itens.length === 0 && (
            <p className="text-sm text-muted-foreground py-6 text-center">Nenhum lembrete registrado.</p>
          )}
          <ul className="divide-y">
            {lembretes?.itens.map((l) => (
              <li key={l.id} className="py-3 text-sm" data-contract="lembrete">
                <div className="flex items-baseline justify-between gap-2">
                  <span className="font-medium">{l.cliente ?? '(sem cliente)'}</span>
                  <span className="text-xs text-muted-foreground whitespace-nowrap">{formatDateTime(l.quando)}</span>
                </div>
                <div className="text-xs text-muted-foreground">
                  Título <span className="font-mono">{l.numero}</span> · venceu {formatDate(l.vencimento)} · D+{l.etapa_dias} · {l.canal === 'email' ? 'e-mail' : l.canal}
                </div>
                <div className="text-xs mt-1">
                  {l.status === 'enviado'
                    ? <span className="text-success-fg">Enviado</span>
                    : <span className="text-warning-fg">Não enviado: {MOTIVOS[l.motivo ?? ''] ?? l.motivo ?? '—'}</span>}
                  {l.tarefa && <span className="text-muted-foreground"> · tarefa criada para o vendedor</span>}
                </div>
              </li>
            ))}
          </ul>
          {lembretes && lembretes.itens.length >= 100 && (
            <p className="text-xs text-muted-foreground pt-2">Mostrando os 100 mais recentes.</p>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}

function Index({ titulos, filtros, lembretes }: Props) {
  const emitirForm = useForm({});
  const [lembretesAberto, setLembretesAberto] = useState(false);

  const abrirLembretes = () => {
    setLembretesAberto(true);
    router.reload({ only: ['lembretes'] });
  };

  const filtrar = (key: string, value: string | null) => {
    // D-14: partial reload — só re-busca o que muda com filtro
    router.get(
      '/financeiro/contas-receber',
      { ...filtros, [key]: value },
      { preserveScroll: true, preserveState: true, only: ['titulos', 'filtros'] }
    );
  };

  const emitirBoleto = (titulo: Titulo) => {
    if (titulo.boleto) {
      toast.info(`Já tem boleto: ${titulo.boleto.nosso_numero}`);
      return;
    }
    emitirForm.post(`/financeiro/contas-receber/${titulo.id}/boleto`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Boleto emitido'),
      onError: (errs) => toast.error(typeof errs === 'string' ? errs : 'Erro ao emitir boleto'),
    });
  };

  return (
    <>
      {/* Onda 12.8 (2026-05-19) — header canon paridade Unificado */}
      <div className="fin-curadoria vendas-aplus p-6 max-w-6xl mx-auto space-y-6">
        {/* Wave 4 (2026-05-25): migrado pra <PageHeader> canon v3.8 (PR #1496) */}
        <PageHeader
          title="Contas a Receber"
          suffix=" · Títulos"
          subtitle={<>Gerados de vendas (auto) ou cadastrados manualmente</>}
        >
          <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
            <FinanceiroSubNav active="contas-receber" hidePrimary />
            <Button size="sm" variant="outline" onClick={abrirLembretes} data-contract="abrir-lembretes">
              <BellRing className="h-4 w-4 mr-1" />
              Lembretes
            </Button>
            <PageHeaderPrimary
              label="Novo recebimento"
              onClick={() => router.visit('/financeiro/unificado/novo?kind=receivable')}
            />
          </div>
        </PageHeader>

        {/* Filtros simples */}
        <div className="flex flex-wrap gap-2">
          <span className="text-xs text-muted-foreground self-center mr-2">Filtros:</span>
          {[
            { k: 'status', v: null, label: 'Todos' },
            { k: 'status', v: 'aberto', label: 'Abertos' },
            { k: 'status', v: 'parcial', label: 'Parciais' },
            { k: 'status', v: 'quitado', label: 'Quitados' },
          ].map((f) => (
            <Button
              key={`${f.k}-${f.v}`}
              size="sm"
              variant={filtros.status === f.v ? 'default' : 'outline'}
              onClick={() => filtrar('status', f.v)}
            >
              {f.label}
            </Button>
          ))}
          <span className="self-center mx-2 text-muted-foreground">·</span>
          {[
            { v: null, label: 'Qualquer venc.' },
            { v: 'hoje', label: 'Hoje' },
            { v: 'semana', label: 'Próx. 7 dias' },
            { v: 'atrasado', label: 'Atrasados' },
          ].map((f) => (
            <Button
              key={`venc-${f.v}`}
              size="sm"
              variant={filtros.vence_em === f.v ? 'default' : 'outline'}
              onClick={() => filtrar('vence_em', f.v)}
            >
              {f.label}
            </Button>
          ))}
        </div>

        <div className="rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">Nº</th>
                <th className="px-4 py-2 font-medium">Cliente</th>
                <th className="px-4 py-2 font-medium">Vencimento</th>
                <th className="px-4 py-2 font-medium text-right">Valor aberto</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Boleto</th>
                <th className="px-4 py-2 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              {titulos.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                    Nenhum título encontrado com esses filtros.
                  </td>
                </tr>
              )}
              {titulos.map((t) => (
                <tr key={t.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-3 font-mono text-xs">{t.numero}</td>
                  <td className="px-4 py-3">
                    <div className="font-medium">{t.cliente_descricao ?? '(sem cliente)'}</div>
                    <div className="text-xs text-muted-foreground">
                      {t.origem === 'venda' && t.origem_id ? `Venda #${t.origem_id}` : t.origem}
                    </div>
                  </td>
                  <td className="px-4 py-3">{formatDate(t.vencimento)}</td>
                  <td className="px-4 py-3 text-right font-medium">{formatBRL(t.valor_aberto)}</td>
                  <td className="px-4 py-3">
                    <StatusPill status={t.status} />
                  </td>
                  <td className="px-4 py-3">
                    {t.boleto ? (
                      <span className="text-xs">
                        <span className="font-mono">{t.boleto.nosso_numero}</span>
                        <div className="text-muted-foreground">{t.boleto.status}</div>
                      </span>
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {t.status === 'aberto' && !t.boleto && (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => emitirBoleto(t)}
                        disabled={emitirForm.processing}
                      >
                        <Receipt className="h-4 w-4 mr-1" />
                        Emitir boleto
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <LembretesPainel aberto={lembretesAberto} onFechar={() => setLembretesAberto(false)} lembretes={lembretes} />

        <Card>
          <CardContent className="pt-6 text-xs text-muted-foreground">
            Mostrando até 100 títulos. Use os filtros acima pra refinar. Para emitir boleto, configure a conta bancária em{' '}
            <a href="/financeiro/contas-bancarias" className="underline">/financeiro/contas-bancarias</a>.
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Contas a Receber" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Contas a Receber' }]}>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);
export default Index;
