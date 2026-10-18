// @memcofre
//   tela: /fisio/pacientes/{id}
//   module: Fisio
//   stories: US-FISIO-005 (UC-FPS-01..05, 07)
//   permissao: fisio.access (ler) · fisio.editar (registrar, editar perfil, bloquear)
//
// Ficha do paciente — Cockpit + Apps Vinculados (UI-0008). Abas: Evolução, Programa,
// Avaliação, Prontuário, Questionários. Nesta US funcionam Avaliação (anamnese da Res.
// COFFITO 414/2012), Prontuário (linha do tempo + evolução + adendo) e o perfil clínico.
// Programa (US-FISIO-006), gráfico da Evolução (US-FISIO-010) e Questionários (US-FISIO-011)
// aparecem como "em breve", sem dado inventado. Registro assinado não se edita: corrigir
// é acrescentar um adendo.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { ArrowLeft, FilePen, Lock, Pencil, ShieldCheck, User } from 'lucide-react';
import { LBlock, LinkedKv } from '@/Components/cockpit/LinkedApps';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import SubNav from '@/Components/shared/SubNav';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import PerfilClinico, { type Opcao } from './_components/PerfilClinico';
import Prontuario, { type Registro } from './_components/Prontuario';

interface Paciente {
  id: number; contact_id: number; nome: string; telefone: string; email: string | null;
  main_complaint: string | null; goals: string[]; sessions_per_week: number; condition_flags: string[];
  status: string; bloqueado: boolean; blocked_at: string | null;
}
interface Consentimento { purpose: string; version: string; granted_at: string | null; revoked_at: string | null; channel: string | null }
interface Props {
  paciente: Paciente;
  pode_editar: boolean;
  condition_flags: Opcao[];
  red_flags: Opcao[];
  prontuario?: Registro[];
  serie_dor?: { date: string; pain: number }[];
  consentimentos?: Consentimento[];
}

type Aba = 'evolucao' | 'programa' | 'avaliacao' | 'prontuario' | 'questionarios';

const FINALIDADE: Record<string, string> = {
  teleatendimento: 'Teleatendimento', whatsapp: 'WhatsApp', imagem: 'Imagem', marketing: 'Marketing', pesquisa_ia: 'Pesquisa/IA',
};

export default function PacienteShow(props: Props) {
  const { paciente, pode_editar, condition_flags } = props;
  const [aba, setAba] = useState<Aba>('prontuario');
  const [editando, setEditando] = useState(false);
  const [bloqueando, setBloqueando] = useState(false);
  const rotulo = useMemo(() => Object.fromEntries(condition_flags.map((o) => [o.value, o.label])), [condition_flags]);
  const podeRegistrar = pode_editar;

  return (
    <div className="flex flex-col gap-4 p-4" data-screen-label="Fisio · Ficha do paciente">
      <Head title={`${paciente.nome} · Fisio`} />
      <PageHeader
        leading={<Button asChild size="icon" variant="ghost" aria-label="Voltar para pacientes"><Link href="/fisio/pacientes"><ArrowLeft className="size-4" aria-hidden="true" /></Link></Button>}
        title={paciente.nome}
        subtitle={<span className="tabular-nums">{paciente.telefone} · {paciente.sessions_per_week} sessões/semana</span>}
        actions={pode_editar && !paciente.bloqueado ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setEditando(true)}><Pencil className="size-4" aria-hidden="true" /> Editar perfil</Button>
            <Button variant="ghost" onClick={() => setBloqueando(true)}><Lock className="size-4" aria-hidden="true" /> Bloquear</Button>
          </div>
        ) : undefined}
      />

      {paciente.bloqueado && (
        <Card><CardContent className="flex items-center gap-2 p-3 text-sm">
          <Lock className="size-4 text-muted-foreground" aria-hidden="true" />
          Paciente bloqueado em {paciente.blocked_at ? new Date(paciente.blocked_at).toLocaleDateString('pt-BR') : '—'}. O prontuário segue guardado por 20 anos (Lei 13.787/2018) e aceita só adendo de correção.
        </CardContent></Card>
      )}

      <div className="grid gap-4 lg:grid-cols-12">
        <section className="flex min-w-0 flex-col gap-4 lg:col-span-8" aria-label="Conteúdo da ficha">
          <SubNav variant="underline" ariaLabel="Seções da ficha" value={aba} onChange={(v) => setAba(v as Aba)} items={[
            { value: 'evolucao', label: 'Evolução', icon: 'activity' },
            { value: 'programa', label: 'Programa', icon: 'list-checks' },
            { value: 'avaliacao', label: 'Avaliação', icon: 'clipboard-list' },
            { value: 'prontuario', label: 'Prontuário', icon: 'file-text', badge: props.prontuario?.length },
            { value: 'questionarios', label: 'Questionários', icon: 'clipboard-check' },
          ]} />

          {aba === 'evolucao' && (
            <Deferred data="serie_dor" fallback={<Skeleton className="h-40 w-full" />}>
              <SerieDor serie={props.serie_dor ?? []} />
            </Deferred>
          )}
          {aba === 'programa' && (
            <EmptyState icon="list-checks" title="Programa de exercícios — em breve"
              description="O editor do programa chega com a US-FISIO-006 (biblioteca, dose e aviso de contraindicação)." />
          )}
          {aba === 'questionarios' && (
            <EmptyState icon="clipboard-check" title="Questionários — em breve"
              description="EVA, RMDQ, PSFS, LEFS e SPADI chegam com a US-FISIO-011." />
          )}
          {(aba === 'avaliacao' || aba === 'prontuario') && (
            <Deferred data="prontuario" fallback={<Skeleton className="h-72 w-full" />}>
              <Prontuario modo={aba} pacienteId={paciente.id} registros={props.prontuario ?? []} podeRegistrar={podeRegistrar}
                bloqueado={paciente.bloqueado} marcadores={condition_flags} sinaisDeAlerta={props.red_flags} />
            </Deferred>
          )}
        </section>

        <aside className="flex flex-col gap-3 lg:col-span-4" aria-label="Apps vinculados">
          <LBlock title="Contato" blockKey="fisio-contato" icon={User}>
            <LinkedKv label="Nome" value={paciente.nome} />
            <LinkedKv label="Telefone" value={paciente.telefone || '—'} mono />
            <LinkedKv label="E-mail" value={paciente.email || '—'} />
            <p className="mt-1 text-xs text-muted-foreground">Nome e telefone vêm do contato da empresa.</p>
          </LBlock>
          <LBlock title="Perfil clínico" blockKey="fisio-perfil" icon={FilePen}>
            <dl className="flex flex-col gap-2 text-sm">
              <div><dt className="text-xs text-muted-foreground">Queixa principal</dt><dd>{paciente.main_complaint || '—'}</dd></div>
              <div><dt className="text-xs text-muted-foreground">Meta semanal</dt><dd className="tabular-nums">{paciente.sessions_per_week}×/semana</dd></div>
              <div><dt className="text-xs text-muted-foreground">Objetivos</dt>
                <dd>{paciente.goals.length === 0 ? '—' : <ul className="list-disc pl-4">{paciente.goals.map((g) => <li key={g}>{g}</li>)}</ul>}</dd></div>
            </dl>
            <div className="flex flex-wrap gap-1 py-1">
              {paciente.condition_flags.length === 0 ? <span className="text-xs text-muted-foreground">Sem marcadores de condição</span>
                : paciente.condition_flags.map((f) => <Badge key={f} variant="warning">{rotulo[f] ?? f}</Badge>)}
            </div>
          </LBlock>
          <LBlock title="Consentimentos" blockKey="fisio-consentimentos" icon={ShieldCheck}>
            <Deferred data="consentimentos" fallback={<Skeleton className="h-16 w-full" />}>
              <Consentimentos itens={props.consentimentos ?? []} />
            </Deferred>
          </LBlock>
        </aside>
      </div>

      {editando && <EditarPerfil paciente={paciente} marcadores={condition_flags} onClose={() => setEditando(false)} />}

      <AlertDialog open={bloqueando} onOpenChange={setBloqueando}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Bloquear {paciente.nome}?</AlertDialogTitle>
            <AlertDialogDescription>
              O paciente sai da lista de ativos. Nada é apagado: o prontuário, os programas e os registros ficam guardados por 20 anos (Lei 13.787/2018) e a ficha continua legível.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={() => router.delete(`/fisio/pacientes/${paciente.id}`)}>Bloquear paciente</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function SerieDor({ serie }: { serie: { date: string; pain: number }[] }) {
  if (serie.length === 0) {
    return <EmptyState icon="activity" title="Sem registro de dor ainda"
      description="A dor informada nos check-ins do portal aparece aqui (só para o profissional). O gráfico chega com a US-FISIO-010." />;
  }
  return (
    <Card><CardContent className="p-4">
      <h3 className="mb-2 text-sm font-semibold">Dor informada nos registros (0–10)</h3>
      <ul className="flex flex-wrap gap-2">
        {serie.map((s) => (
          <li key={s.date} className="rounded-lg border border-border px-2 py-1 text-sm tabular-nums">
            {new Date(`${s.date}T12:00:00`).toLocaleDateString('pt-BR')} · <b>{s.pain}</b>
          </li>
        ))}
      </ul>
    </CardContent></Card>
  );
}

function Consentimentos({ itens }: { itens: Consentimento[] }) {
  if (itens.length === 0) return <p className="text-sm text-muted-foreground">Nenhum consentimento registrado.</p>;
  return (
    <ul className="flex flex-col gap-1">
      {itens.map((c, i) => (
        <li key={`${c.purpose}-${i}`} className="flex items-center justify-between gap-2 text-sm">
          <span>{FINALIDADE[c.purpose] ?? c.purpose} <span className="text-xs text-muted-foreground">{c.version}</span></span>
          {c.revoked_at ? <Badge variant="neutral" dot>Revogado</Badge> : <Badge variant="success" dot>Ativo</Badge>}
        </li>
      ))}
    </ul>
  );
}

function EditarPerfil({ paciente, marcadores, onClose }: { paciente: Paciente; marcadores: Opcao[]; onClose: () => void }) {
  const form = useForm({
    main_complaint: paciente.main_complaint ?? '',
    goals: paciente.goals.length ? paciente.goals : [''],
    sessions_per_week: paciente.sessions_per_week,
    condition_flags: paciente.condition_flags,
  });
  const erro = (k: string) => (form.errors as Record<string, string>)[k];
  const marcar = (v: string, on: boolean) => form.setData('condition_flags', on ? [...form.data.condition_flags, v] : form.data.condition_flags.filter((x) => x !== v));
  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({ ...d, goals: d.goals.filter((g) => g.trim() !== '') }));
    form.put(`/fisio/pacientes/${paciente.id}`, { preserveScroll: true, onSuccess: onClose });
  };
  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-[760px]">
        <form onSubmit={enviar} className="flex h-full flex-col">
          <SheetHeader>
            <SheetTitle>Perfil clínico</SheetTitle>
            <SheetDescription>Nome e telefone se editam no contato. Aqui ficam queixa, objetivos, meta e marcadores.</SheetDescription>
          </SheetHeader>
          <div className="flex flex-col gap-5 px-4"><PerfilClinico form={form} marcadores={marcadores} marcar={marcar} erro={erro} /></div>
          <SheetFooter className="flex-row justify-end">
            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={form.processing}>Salvar</Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}

PacienteShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
