// @memcofre
//   tela: /financeiro/boletos
//   module: Financeiro
//   status: live
//   stories: US-BOL-XXX (refator visual Cockpit V2)
//   adrs: ui/0114 (cockpit-v2), 0093 (multi-tenant Tier 0)
//
// Origem: prototipo Cowork "Boleto e Contas Inter" aprovado [W] 2026-05-09.
// Decisões Q1-Q5 aprovadas [W] 2026-05-14
// (memory/requisitos/Financeiro/boletos-visual-comparison.md).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Sheet, SheetContent } from '@/Components/ui/sheet';
import {
  Receipt, X, Copy, AlertTriangle, CheckCircle2, Hourglass, FileText,
  Bell, Megaphone, ShieldAlert, ChevronRight,
} from 'lucide-react';
import { toast } from 'sonner';
import { useState, type ReactNode } from 'react';

interface Remessa {
  id: number;
  titulo_id: number;
  titulo_numero: string | null;
  cliente: string | null;
  nosso_numero: string;
  linha_digitavel: string;
  codigo_barras: string;
  valor_total: string;
  vencimento: string | null;
  status: string;
  strategy: string;
  enviado_em: string | null;
  pago_em: string | null;
  created_at: string;
  conta_id: number | null;
  conta_nome: string | null;
  banco_codigo: string | null;
  banco_short: string | null;
  dias_atraso: number;
}

interface ContaOpt {
  id: number;
  name: string;
  banco_codigo: string | null;
  banco_short: string | null;
}

interface Kpi { qtd: number; valor: number }
interface FunilStep { qtd: number; valor?: number; desc?: string }

interface Props {
  remessas: Remessa[];
  kpis: { pago_mes: Kpi; vencido: Kpi; aberto: Kpi };
  funil: {
    aberto: FunilStep;
    lembrete: FunilStep;
    cobranca: FunilStep;
    vencido_5d: FunilStep;
    protesto: FunilStep;
  };
  contas: ContaOpt[];
  filtros: { status: string | null; conta_id: number | null };
}

const STATUS_TABS: Array<{ id: string | null; label: string }> = [
  { id: null, label: 'Todos' },
  { id: 'registrado', label: 'Em aberto' },
  { id: 'pago', label: 'Pagos' },
  { id: 'vencido', label: 'Vencidos' },
  { id: 'cancelado', label: 'Cancelados' },
];

const STATUS_BADGE: Record<string, { label: string; cls: string; icon: typeof CheckCircle2 }> = {
  gerado_mock:   { label: 'Mock',       cls: 'bg-purple-50 text-purple-700 border-purple-200',     icon: FileText },
  gerado:        { label: 'Gerado',     cls: 'bg-stone-50 text-stone-700 border-stone-200',         icon: FileText },
  enviado:       { label: 'Enviado',    cls: 'bg-sky-50 text-sky-700 border-sky-200',               icon: Hourglass },
  registrado:    { label: 'Registrado', cls: 'bg-sky-50 text-sky-700 border-sky-200',               icon: Hourglass },
  pago:          { label: 'Pago',       cls: 'bg-emerald-50 text-emerald-700 border-emerald-200',   icon: CheckCircle2 },
  vencido:       { label: 'Vencido',    cls: 'bg-rose-50 text-rose-700 border-rose-200',            icon: AlertTriangle },
  cancelado:     { label: 'Cancelado',  cls: 'bg-stone-50 text-stone-500 border-stone-200',         icon: X },
};

const BANCO_COR: Record<string, string> = {
  '001': 'bg-yellow-400',
  '033': 'bg-red-600',
  '077': 'bg-orange-500',
  '104': 'bg-sky-600',
  '237': 'bg-red-700',
  '274': 'bg-blue-600',
  '336': 'bg-amber-600',
  '341': 'bg-orange-700',
  '748': 'bg-emerald-600',
  '756': 'bg-green-700',
};

const brl = (v: string | number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
    .format(typeof v === 'string' ? parseFloat(v) : (v ?? 0));

const brlNoSign = (v: string | number) =>
  brl(typeof v === 'string' ? parseFloat(v) : v).replace('R$', '').trim();

const fmtDate = (iso: string | null) => {
  if (!iso) return '—';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};

function FinanceiroBoletos({ remessas, kpis, funil, contas, filtros }: Props) {
  const cancelForm = useForm({});
  const [drawer, setDrawer] = useState<Remessa | null>(null);

  const filtrar = (status: string | null) => {
    router.get('/financeiro/boletos', { status, conta_id: filtros.conta_id }, {
      preserveScroll: true, preserveState: true,
    });
  };

  const filtrarConta = (conta_id: string) => {
    router.get('/financeiro/boletos', { status: filtros.status, conta_id: conta_id || null }, {
      preserveScroll: true, preserveState: true,
    });
  };

  const copiar = async (txt: string, label: string) => {
    try {
      await navigator.clipboard.writeText(txt);
      toast.success(`${label} copiado`);
    } catch {
      toast.error('Não foi possível copiar');
    }
  };

  const cancelar = (r: Remessa) => {
    if (!confirm(`Cancelar boleto ${r.nosso_numero}?`)) return;
    cancelForm.post(`/financeiro/boletos/${r.id}/cancelar`, {
      preserveScroll: true,
      onSuccess: () => { toast.success('Boleto cancelado'); setDrawer(null); },
      onError: () => toast.error('Erro ao cancelar'),
    });
  };

  return (
    <>
      <div className="p-6 max-w-7xl mx-auto space-y-5">
        <div className="flex items-baseline justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Boletos</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Cobrança · {kpis.aberto.qtd} em aberto · {kpis.vencido.qtd} vencidos
            </p>
          </div>
        </div>

        <Card className="p-4">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-3">
            Funil de cobrança · mês corrente
          </div>
          <div className="grid grid-cols-5 gap-2">
            <FunilStepCard
              icon={Receipt} label="Em aberto" qtd={funil.aberto.qtd}
              sub={funil.aberto.valor ? brl(funil.aberto.valor) : '—'} tone="default" active
            />
            <FunilStepCard icon={Bell} label="Lembrete" qtd={funil.lembrete.qtd} sub={funil.lembrete.desc} tone="default" />
            <FunilStepCard icon={Megaphone} label="Cobrança ativa" qtd={funil.cobranca.qtd} sub={funil.cobranca.desc} tone="default" />
            <FunilStepCard
              icon={AlertTriangle} label="Vencidos +5d" qtd={funil.vencido_5d.qtd}
              sub={funil.vencido_5d.valor ? brl(funil.vencido_5d.valor) : '—'}
              tone={funil.vencido_5d.qtd > 0 ? 'amber' : 'default'}
            />
            <FunilStepCard icon={ShieldAlert} label="Protesto" qtd={funil.protesto.qtd} sub={funil.protesto.desc} tone="default" />
          </div>
        </Card>

        <div className="grid grid-cols-3 gap-3">
          <KpiCard
            label="Pago no mês"
            value={brl(kpis.pago_mes.valor)}
            caption={`${kpis.pago_mes.qtd} liquidações${kpis.pago_mes.qtd > 0 ? ` · ticket médio ${brl(kpis.pago_mes.valor / kpis.pago_mes.qtd)}` : ''}`}
            tone="emerald"
          />
          <KpiCard
            label="Vencido"
            value={brl(kpis.vencido.valor)}
            caption={kpis.vencido.qtd > 0 ? `${kpis.vencido.qtd} boleto(s)` : 'sem boletos vencidos'}
            tone={kpis.vencido.qtd > 0 ? 'rose' : 'default'}
          />
          <KpiCard
            label="Em aberto"
            value={brl(kpis.aberto.valor)}
            caption={`${kpis.aberto.qtd} registrado(s)/enviado(s)`}
            tone="default"
          />
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          <div className="inline-flex bg-stone-100 rounded-md p-0.5 border border-stone-200">
            {STATUS_TABS.map((t) => (
              <button key={`s-${t.id ?? 'all'}`}
                onClick={() => filtrar(t.id)}
                className={`h-7 px-3 rounded text-[12px] font-medium transition ${
                  filtros.status === t.id
                    ? 'bg-white shadow-sm text-stone-900'
                    : 'text-stone-600 hover:text-stone-900'
                }`}>
                {t.label}
              </button>
            ))}
          </div>
          {contas.length > 1 && (
            <select
              value={filtros.conta_id ?? ''}
              onChange={(e) => filtrarConta(e.target.value)}
              className="h-7 text-[12px] bg-white border border-stone-300 rounded-md px-2 text-stone-700"
            >
              <option value="">todas as contas</option>
              {contas.filter((c) => c.banco_codigo).map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
        </div>

        <div className="bg-white border border-stone-200 rounded-md overflow-hidden">
          <table className="w-full text-[12.5px] tabular-nums">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/60">
                <th className="pl-5 pr-2 py-2 text-left font-medium w-[110px]">Vencimento</th>
                <th className="px-2 py-2 text-left font-medium">Cliente / Título</th>
                <th className="px-2 py-2 text-left font-medium w-[160px]">Nosso número</th>
                <th className="px-2 py-2 text-left font-medium w-[100px]">Conta</th>
                <th className="px-2 py-2 text-right font-medium w-[110px]">Valor</th>
                <th className="px-2 py-2 text-left font-medium w-[110px]">Status</th>
                <th className="pl-2 pr-5 py-2 text-right font-medium w-[110px]"></th>
              </tr>
            </thead>
            <tbody>
              {remessas.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-12 text-center text-stone-400">
                    Nenhum boleto encontrado com os filtros atuais.
                  </td>
                </tr>
              )}
              {remessas.map((r) => {
                const cfg = STATUS_BADGE[r.status] ?? STATUS_BADGE.gerado;
                const Icon = cfg.icon;
                const overdue = r.dias_atraso > 0 && ['registrado', 'enviado'].includes(r.status);
                const bancoCor = r.banco_codigo ? BANCO_COR[r.banco_codigo] ?? 'bg-stone-300' : 'bg-stone-300';
                return (
                  <tr key={r.id}
                    className="border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer"
                    onClick={() => setDrawer(r)}>
                    <td className="pl-5 pr-2 py-2.5 text-stone-700">
                      <div className="font-medium">{fmtDate(r.vencimento)}</div>
                      {overdue && <div className="text-[10.5px] text-rose-600">{r.dias_atraso}d atraso</div>}
                    </td>
                    <td className="px-2 py-2.5">
                      <div className="font-medium text-stone-900 truncate max-w-[280px]">{r.cliente ?? '—'}</div>
                      <div className="text-[10.5px] text-stone-400 font-mono">
                        {r.titulo_numero ? `#${r.titulo_numero}` : '—'}
                      </div>
                    </td>
                    <td className="px-2 py-2.5 font-mono text-[11.5px] text-stone-600">{r.nosso_numero}</td>
                    <td className="px-2 py-2.5">
                      <span className="inline-flex items-center gap-1.5">
                        <span className={`w-2 h-2 rounded-sm ${bancoCor}`} />
                        <span className="text-[11.5px] text-stone-600">{r.banco_short ?? '—'}</span>
                      </span>
                    </td>
                    <td className="px-2 py-2.5 text-right font-semibold">
                      {brlNoSign(r.valor_total)}
                    </td>
                    <td className="px-2 py-2.5">
                      <span className={`inline-flex items-center gap-1 text-[10.5px] font-medium px-1.5 py-0.5 rounded border ${cfg.cls}`}>
                        <Icon className="h-2.5 w-2.5" />
                        {cfg.label}
                      </span>
                    </td>
                    <td className="pl-2 pr-5 py-2.5 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                      <Button size="sm" variant="ghost"
                        onClick={() => copiar(r.linha_digitavel, 'Linha digitável')}
                        title="Copiar linha digitável">
                        <Copy className="h-3.5 w-3.5" />
                      </Button>
                      <Button size="sm" variant="ghost"
                        onClick={() => setDrawer(r)}
                        title="Detalhes">
                        <ChevronRight className="h-3.5 w-3.5" />
                      </Button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <Card className="p-4 text-xs text-muted-foreground">
          Mostrando até 100 boletos. Cancelar é irreversível — boleto fica no histórico com status &quot;cancelado&quot;.
          Funil de cobrança UI-only F1; jobs automáticos (lembrete/cobrança/protesto) entram em Onda 2.
        </Card>
      </div>

      <Sheet open={drawer !== null} onOpenChange={(open) => !open && setDrawer(null)}>
        <SheetContent side="right" className="w-[520px] sm:max-w-[520px] overflow-y-auto">
          {drawer && (
            <div className="space-y-5 pr-2">
              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">
                  Boleto {drawer.titulo_numero ? `#${drawer.titulo_numero}` : drawer.nosso_numero}
                </div>
                <div className="text-lg font-semibold mt-0.5">{drawer.cliente ?? '—'}</div>
              </div>

              <div className="grid grid-cols-2 gap-x-5 gap-y-3 text-[12.5px] tabular-nums">
                <Field label="Vencimento" value={fmtDate(drawer.vencimento)} />
                <Field label="Valor" value={brl(drawer.valor_total)} bold />
                <Field label="Conta emissora" value={drawer.conta_nome ?? '—'} sub={drawer.banco_short} />
                <Field label="Estratégia" value={drawer.strategy} mono />
                <Field label="Status" value={STATUS_BADGE[drawer.status]?.label ?? drawer.status} colSpan={2} />
              </div>

              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Nosso número</div>
                <div className="font-mono text-sm">{drawer.nosso_numero}</div>
              </div>

              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Linha digitável</div>
                <div className="flex items-center gap-2 bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
                  <div className="font-mono text-[11.5px] flex-1 break-all">{drawer.linha_digitavel}</div>
                  <Button size="sm" variant="outline" onClick={() => copiar(drawer.linha_digitavel, 'Linha digitável')}>
                    <Copy className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>

              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Código de barras</div>
                <div className="font-mono text-[10.5px] text-stone-500 break-all">{drawer.codigo_barras}</div>
              </div>

              <div className="flex flex-wrap gap-2 pt-2">
                <Button variant="outline" size="sm" onClick={() => copiar(drawer.linha_digitavel, 'Linha digitável')}>
                  <Copy className="h-3.5 w-3.5 mr-1" /> Copiar linha digitável
                </Button>
                {!['pago', 'cancelado'].includes(drawer.status) && (
                  <Button variant="destructive" size="sm" onClick={() => cancelar(drawer)}
                    disabled={cancelForm.processing}>
                    <X className="h-3.5 w-3.5 mr-1" /> Cancelar boleto
                  </Button>
                )}
              </div>
              <p className="text-[10.5px] text-stone-400">
                Timeline cronológica completa (criação → envio → pagamento) entra em F2 com activity_log.
              </p>
            </div>
          )}
        </SheetContent>
      </Sheet>
    </>
  );
}

function FunilStepCard({
  icon: Icon, label, qtd, sub, tone, active,
}: { icon: typeof Receipt; label: string; qtd: number; sub?: string; tone: 'default' | 'amber'; active?: boolean }) {
  const toneCls = tone === 'amber'
    ? 'bg-amber-50 border-amber-200 text-amber-900'
    : active
      ? 'bg-stone-900 text-white border-stone-900'
      : 'bg-white border-stone-200 text-stone-700';
  return (
    <div className={`rounded-md border p-3 flex flex-col gap-1 ${toneCls}`}>
      <div className="flex items-center gap-1.5">
        <Icon className="h-3 w-3" />
        <div className="text-[10px] uppercase tracking-widest font-medium">{label}</div>
      </div>
      <div className="text-xl font-semibold tabular-nums">{qtd}</div>
      {sub && <div className={`text-[10.5px] ${active ? 'text-stone-300' : 'text-stone-500'}`}>{sub}</div>}
    </div>
  );
}

function KpiCard({ label, value, caption, tone }: { label: string; value: string; caption?: string; tone: 'default' | 'emerald' | 'rose' }) {
  const toneCls = tone === 'emerald'
    ? 'border-emerald-200 bg-emerald-50/50'
    : tone === 'rose'
      ? 'border-rose-200 bg-rose-50/50'
      : 'border-stone-200 bg-white';
  return (
    <div className={`rounded-md border p-4 ${toneCls}`}>
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">{label}</div>
      <div className="text-2xl font-semibold tabular-nums tracking-tight mt-1">{value}</div>
      {caption && <div className="text-[11.5px] text-stone-500 mt-1">{caption}</div>}
    </div>
  );
}

function Field({ label, value, sub, mono, bold, colSpan }: {
  label: string; value: string; sub?: string | null; mono?: boolean; bold?: boolean; colSpan?: number
}) {
  return (
    <div className={colSpan === 2 ? 'col-span-2' : ''}>
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">{label}</div>
      <div className={`mt-0.5 ${bold ? 'font-semibold' : 'font-medium'} ${mono ? 'font-mono text-[11.5px]' : ''}`}>
        {value}
      </div>
      {sub && <div className="text-[10.5px] text-stone-400 mt-0.5">{sub}</div>}
    </div>
  );
}

FinanceiroBoletos.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Boletos"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Boletos' }]}
  >
    {page}
  </AppShellV2>
);

export default FinanceiroBoletos;
