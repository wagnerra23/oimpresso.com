// @memcofre
//   tela: /fisio/pacientes/{id}/pacote
//   module: Fisio
//   stories: US-FISIO-021 (UC-FPK-01..06) · US-FISIO-020
//   permissao: fisio.access · abrir pacote, registrar e devolver sessão exigem fisio.editar
//
// Aba "Pacote e cobrança" da ficha (fonte: prototipo-ui/cowork/Wagner/prototipos/fisio/Financeiro.dc.html).
// Sessões são do Fisio. Títulos e nota são LEITURA do Financeiro e do NFSe pela venda do pacote;
// esta tela não grava valor. Vender/renovar pela venda do núcleo é a US-FISIO-022.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';

interface Sessao { id: number; n: number; data: string | null; nota: string | null; estornada: boolean }
interface PacoteAtual { id: number; title: string; sessions_total: number; saldo: number; validade: string | null; tem_venda: boolean; sessoes: Sessao[] }
interface Titulo { id: number; numero: string; status: string; valor: string; vencimento: string | null; forma: string | null }
interface Nota { numero: string | null; status: string; erro: string | null }
interface Venda { id: number; numero: string | null; data: string; valor: string }
interface Props {
  paciente: { id: number; nome: string | null };
  pode_editar: boolean;
  pacote?: PacoteAtual | null;
  cobranca?: Titulo[];
  nota?: Nota | null;
  vendas?: Venda[];
}

const data = (d: string | null) => (d ? new Date(`${d}T12:00:00`).toLocaleDateString('pt-BR') : '—');
const moeda = (v: string) => Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function AbrirPacote({ pacienteId }: { pacienteId: number }) {
  const form = useForm({ title: '', sessions_total: 10, sessions_per_week: 2, valid_until: '' });
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/fisio/pacientes/${pacienteId}/pacotes`, { preserveScroll: true });
  };
  return (
    <form onSubmit={enviar} className="grid gap-3 sm:grid-cols-4">
      <div className="grid gap-1 sm:col-span-2"><Label htmlFor="pac-titulo">Nome do pacote</Label>
        <Input id="pac-titulo" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="10 sessões · 2× por semana" /></div>
      <div className="grid gap-1"><Label htmlFor="pac-sessoes">Sessões</Label>
        <Input id="pac-sessoes" type="number" min={1} max={200} value={form.data.sessions_total} onChange={(e) => form.setData('sessions_total', Number(e.target.value))} /></div>
      <div className="grid gap-1"><Label htmlFor="pac-validade">Validade</Label>
        <Input id="pac-validade" type="date" value={form.data.valid_until} onChange={(e) => form.setData('valid_until', e.target.value)} /></div>
      {Object.values(form.errors)[0] && <p className="text-sm text-destructive sm:col-span-4">{Object.values(form.errors)[0]}</p>}
      <div className="sm:col-span-4"><Button type="submit" disabled={form.processing}>Abrir pacote</Button></div>
    </form>
  );
}

function Sessoes({ pacote, podeEditar }: { pacote: PacoteAtual; podeEditar: boolean }) {
  const devolver = (id: number) => {
    const motivo = window.prompt('Por que devolver esta sessão ao pacote?');
    if (motivo) router.post(`/fisio/pacotes/lancamentos/${id}/estornar`, { motivo }, { preserveScroll: true });
  };
  return (
    <section className="grid gap-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pacote atual</h2>
          <p className="text-base font-medium">{pacote.title}</p>
        </div>
        <p className="font-mono text-2xl tabular-nums" aria-label="Sessões usadas de contratadas">
          {pacote.sessions_total - pacote.saldo} / {pacote.sessions_total}
        </p>
      </div>
      <p className="text-sm text-muted-foreground">Validade: {data(pacote.validade)} · saldo {pacote.saldo}</p>
      <ul className="divide-y rounded-md border">
        {pacote.sessoes.length === 0 && <li className="p-3 text-sm text-muted-foreground">Nenhuma sessão usada ainda.</li>}
        {pacote.sessoes.map((s) => (
          <li key={s.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
            <span className="font-mono tabular-nums text-muted-foreground">{s.n}</span>
            <span>{data(s.data)}</span>
            <span className="min-w-0 flex-1 truncate">{s.nota ?? 'Sessão'}</span>
            <Badge variant={s.estornada ? 'outline' : 'secondary'}>{s.estornada ? 'devolvida' : 'realizada'}</Badge>
            {podeEditar && !s.estornada && <Button size="sm" variant="ghost" onClick={() => devolver(s.id)}>Devolver</Button>}
          </li>
        ))}
      </ul>
      {podeEditar && (
        <div><Button variant="outline" disabled={pacote.saldo < 1}
          onClick={() => router.post(`/fisio/pacotes/${pacote.id}/consumir`, {}, { preserveScroll: true })}>Registrar sessão</Button></div>
      )}
    </section>
  );
}

function LigarVenda({ pacoteId, vendas }: { pacoteId: number; vendas: Venda[] }) {
  const form = useForm({ transaction_id: vendas[0]?.id ?? 0 });
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/fisio/pacotes/${pacoteId}/venda`, { preserveScroll: true });
  };
  return (
    <form onSubmit={enviar} className="flex flex-wrap items-end gap-2">
      {vendas.length > 0 && (
        <div className="grid gap-1">
          <Label htmlFor="pac-venda">Venda deste pacote</Label>
          <Select value={String(form.data.transaction_id)} onValueChange={(v) => form.setData('transaction_id', Number(v))}>
            <SelectTrigger id="pac-venda" className="w-72"><SelectValue placeholder="Escolha a venda" /></SelectTrigger>
            <SelectContent>
              {vendas.map((v) => <SelectItem key={v.id} value={String(v.id)}>{v.numero ?? `#${v.id}`} · {data(v.data)} · {moeda(v.valor)}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
      )}
      {vendas.length > 0 && <Button type="submit" size="sm" disabled={form.processing}>Ligar venda</Button>}
      <Button size="sm" variant="outline" asChild><a href="/sells/create">Nova venda</a></Button>
      {form.errors.transaction_id && <p className="w-full text-sm text-destructive">{form.errors.transaction_id}</p>}
    </form>
  );
}

export default function PacotePaciente({ paciente, pode_editar, pacote, cobranca, nota, vendas }: Props) {
  return (
    <>
      <PageHeader title="Pacote e cobrança" subtitle={paciente.nome ?? undefined}
        actions={<Button variant="ghost" asChild><Link href={`/fisio/pacientes/${paciente.id}`}><ArrowLeft className="size-4" aria-hidden="true" /> Ficha</Link></Button>} />
      <div className="mx-auto grid w-full max-w-4xl gap-4 p-4">
        <Card><CardContent className="grid gap-4 pt-6">
          <Deferred data="pacote" fallback={<Skeleton className="h-32 w-full" />}>
            {pacote ? <Sessoes pacote={pacote} podeEditar={pode_editar} />
              : pode_editar ? <AbrirPacote pacienteId={paciente.id} />
                : <p className="text-sm text-muted-foreground">Este paciente não tem pacote aberto.</p>}
          </Deferred>
        </CardContent></Card>

        <Card><CardContent className="grid gap-3 pt-6">
          <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cobrança</h2>
          <Deferred data="cobranca" fallback={<Skeleton className="h-16 w-full" />}>
            {(cobranca ?? []).length === 0
              ? <p className="text-sm text-muted-foreground">Sem venda ligada a este pacote. A cobrança nasce da venda do pacote, no Financeiro.</p>
              : <ul className="divide-y rounded-md border">{(cobranca ?? []).map((t) => (
                  <li key={t.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
                    <span className="font-mono">{t.numero}</span><span>vence {data(t.vencimento)}</span>
                    <span className="flex-1 font-mono tabular-nums">{moeda(t.valor)}</span><Badge variant="secondary">{t.status}</Badge>
                  </li>))}</ul>}
          </Deferred>
          {pacote && !pacote.tem_venda && pode_editar && (
            <Deferred data="vendas" fallback={<Skeleton className="h-8 w-full" />}>
              <LigarVenda pacoteId={pacote.id} vendas={vendas ?? []} />
            </Deferred>
          )}
          <p className="text-xs text-muted-foreground">Pix Automático só para clínica com CNPJ (regra do Banco Central). Para atendimento como pessoa física: cartão recorrente ou Pix cobrança.</p>
        </CardContent></Card>

        <Card><CardContent className="grid gap-2 pt-6">
          <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Fiscal</h2>
          <Deferred data="nota" fallback={<Skeleton className="h-8 w-full" />}>
            {nota ? <p className="text-sm">NFS-e {nota.numero ?? ''} · <Badge variant="secondary">{nota.status}</Badge>{nota.erro && <span className="block text-destructive">{nota.erro}</span>}</p>
              : <p className="text-sm text-muted-foreground">Nenhuma nota para a venda deste pacote.</p>}
          </Deferred>
        </CardContent></Card>
      </div>
    </>
  );
}

PacotePaciente.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
