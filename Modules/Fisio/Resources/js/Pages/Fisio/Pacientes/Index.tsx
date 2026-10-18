// @memcofre
//   tela: /fisio/pacientes
//   module: Fisio
//   stories: US-FISIO-005 (UC-FPI-01..04)
//   permissao: fisio.access (ler) · fisio.editar (cadastrar)
//
// PT-01 Lista de pacientes. O paciente É um contact (ADR 0437 §3): nome e telefone vêm do
// contact; aqui só o perfil clínico. Busca pelo nome/telefone do contact (servidor, ?q=),
// filtro ativos/bloqueados, J/K percorre, Enter abre, N novo, / busca.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus, Search } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import SubNav from '@/Components/shared/SubNav';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import PerfilClinico from './_components/PerfilClinico';

import type { Opcao } from './_components/PerfilClinico';
interface PacienteLinha {
  id: number; nome: string; telefone: string; queixa: string | null; sessions_per_week: number;
  condition_flags: string[]; status: string; bloqueado: boolean; ultimo_registro: string | null;
}
interface Props {
  filtros: { q: string; situacao: 'ativos' | 'bloqueados' };
  pode_editar: boolean;
  condition_flags: Opcao[];
  contagem?: { ativos: number; bloqueados: number };
  pacientes?: PacienteLinha[];
}

const dataBR = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

function digitando(e: KeyboardEvent) {
  const t = e.target as HTMLElement | null;
  return !!t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName));
}

export default function PacientesIndex({ filtros, pode_editar, condition_flags, contagem, pacientes }: Props) {
  const [novo, setNovo] = useState(false);
  const [busca, setBusca] = useState(filtros.q);
  const buscaRef = useRef<HTMLInputElement>(null);
  const rotulo = useMemo(() => Object.fromEntries(condition_flags.map((o) => [o.value, o.label])), [condition_flags]);

  const filtrar = useCallback((mudar: Partial<Props['filtros']>) => {
    router.get('/fisio/pacientes', { ...filtros, ...mudar, q: (mudar.q ?? busca) || undefined }, { preserveState: true, replace: true, only: ['filtros', 'pacientes', 'contagem'] });
  }, [filtros, busca]);

  useEffect(() => {
    if (busca === filtros.q) return;
    const t = setTimeout(() => filtrar({ q: busca }), 300);
    return () => clearTimeout(t);
  }, [busca, filtros.q, filtrar]);

  const colunas: ColumnDef<PacienteLinha>[] = useMemo(() => [
    { accessorKey: 'nome', header: 'Paciente', cell: ({ row }) => (
      <div className="min-w-0"><b className="block truncate text-sm">{row.original.nome}</b>
        <span className="text-xs text-muted-foreground tabular-nums">{row.original.telefone}</span></div>
    ) },
    { accessorKey: 'queixa', header: 'Queixa principal', cell: ({ row }) => <span className="line-clamp-2 text-sm">{row.original.queixa || '—'}</span> },
    { accessorKey: 'condition_flags', header: 'Marcadores', cell: ({ row }) => (
      <div className="flex flex-wrap gap-1">{row.original.condition_flags.length === 0 ? <span className="text-xs text-muted-foreground">—</span>
        : row.original.condition_flags.map((f) => <Badge key={f} variant="warning">{rotulo[f] ?? f}</Badge>)}</div>
    ) },
    { accessorKey: 'sessions_per_week', header: 'Sessões/sem.', meta: { align: 'right', mono: true, width: 110 } },
    { accessorKey: 'ultimo_registro', header: 'Último registro', meta: { mono: true, width: 130 }, cell: ({ row }) => dataBR(row.original.ultimo_registro) },
    { id: 'situacao', header: 'Situação', meta: { width: 120 }, cell: ({ row }) => (row.original.bloqueado
      ? <Badge variant="neutral" dot>Bloqueado</Badge>
      : <Badge variant={row.original.status === 'alta' ? 'info' : 'success'} dot>{row.original.status === 'alta' ? 'Alta' : 'Ativo'}</Badge>) },
  ], [rotulo]);

  return (
    <div className="flex flex-col gap-4 p-4" data-screen-label="Fisio · Pacientes">
      <Head title="Pacientes · Fisio" />
      <PageHeader
        title="Pacientes"
        subtitle={contagem ? <span className="tabular-nums">{contagem.ativos} ativos · {contagem.bloqueados} bloqueados</span> : undefined}
        actions={pode_editar ? <Button onClick={() => setNovo(true)}><Plus className="size-4" aria-hidden="true" /> Novo paciente <kbd className="ml-1 text-xs opacity-70">N</kbd></Button> : undefined}
      />

      <div className="flex flex-wrap items-center gap-3">
        <SubNav variant="segmented" ariaLabel="Situação do paciente" value={filtros.situacao}
          onChange={(v) => filtrar({ situacao: v as Props['filtros']['situacao'] })}
          items={[{ value: 'ativos', label: 'Ativos', badge: contagem?.ativos }, { value: 'bloqueados', label: 'Bloqueados', badge: contagem?.bloqueados }]} />
        <div className="relative min-w-64 flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input ref={buscaRef} className="pl-9" aria-label="Buscar paciente por nome ou telefone" placeholder="Buscar por nome ou telefone (/)"
            value={busca} onChange={(e) => setBusca(e.target.value)} />
        </div>
      </div>

      <Deferred data={['pacientes', 'contagem']} fallback={<Skeleton className="h-72 w-full" />}>
        <Lista pacientes={pacientes ?? []} colunas={colunas} filtros={filtros} onNovo={() => setNovo(true)} podeEditar={pode_editar} buscaRef={buscaRef} />
      </Deferred>

      {novo && <NovoPaciente marcadores={condition_flags} onClose={() => setNovo(false)} />}
    </div>
  );
}

function Lista({ pacientes, colunas, filtros, onNovo, podeEditar, buscaRef }: {
  pacientes: PacienteLinha[]; colunas: ColumnDef<PacienteLinha>[]; filtros: Props['filtros'];
  onNovo: () => void; podeEditar: boolean; buscaRef: React.RefObject<HTMLInputElement | null>;
}) {
  const [foco, setFoco] = useState(0);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.metaKey || e.ctrlKey || e.altKey || digitando(e)) return;
      if (e.key === 'j') setFoco((i) => Math.min(i + 1, pacientes.length - 1));
      else if (e.key === 'k') setFoco((i) => Math.max(i - 1, 0));
      else if (e.key === 'Enter' && pacientes[foco]) router.visit(`/fisio/pacientes/${pacientes[foco].id}`);
      else if ((e.key === 'n' || e.key === 'N') && podeEditar) { e.preventDefault(); onNovo(); }
      else if (e.key === '/') { e.preventDefault(); buscaRef.current?.focus(); }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [pacientes, foco, onNovo, podeEditar, buscaRef]);

  if (pacientes.length === 0) {
    return filtros.q
      ? <EmptyState variant="search" icon="search-x" title={`Nada para "${filtros.q}"`} description="Tente outro nome ou telefone." />
      : <EmptyState icon="users" title={filtros.situacao === 'bloqueados' ? 'Nenhum paciente bloqueado' : 'Nenhum paciente ainda'}
          description="O paciente nasce de um contato da empresa: escolha um contato existente ou cadastre um novo."
          action={podeEditar && filtros.situacao === 'ativos' ? <Button onClick={onNovo}><Plus className="size-4" aria-hidden="true" /> Cadastrar o primeiro paciente</Button> : undefined} />;
  }

  return (
    <DataTable caption="Pacientes da clínica" columns={colunas} data={pacientes} rowKey={(p: PacienteLinha) => p.id}
      rowState={(p: PacienteLinha) => (pacientes[foco]?.id === p.id ? 'selected' : p.bloqueado ? 'archived' : undefined)}
      onRowClick={(p: PacienteLinha) => router.visit(`/fisio/pacientes/${p.id}`)} />
  );
}

interface ContatoOpcao { id: number; nome: string; telefone: string }

function NovoPaciente({ marcadores, onClose }: { marcadores: Opcao[]; onClose: () => void }) {
  const [origem, setOrigem] = useState<'existente' | 'novo'>('existente');
  const [termo, setTermo] = useState('');
  const [contatos, setContatos] = useState<ContatoOpcao[]>([]);
  const form = useForm({
    contact_id: null as number | null,
    novo_contato: { nome: '', telefone: '' },
    main_complaint: '',
    goals: [''] as string[],
    sessions_per_week: 3,
    condition_flags: [] as string[],
  });

  useEffect(() => {
    if (origem !== 'existente') return;
    const ctl = new AbortController();
    const t = setTimeout(() => {
      fetch(`/fisio/pacientes/contatos?q=${encodeURIComponent(termo)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: ctl.signal })
        .then((r) => (r.ok ? r.json() : { contatos: [] })).then((d) => setContatos(d.contatos ?? [])).catch(() => undefined);
    }, 250);
    return () => { clearTimeout(t); ctl.abort(); };
  }, [termo, origem]);

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({
      ...d,
      contact_id: origem === 'existente' ? d.contact_id : null,
      novo_contato: origem === 'novo' ? d.novo_contato : null,
      goals: d.goals.filter((g) => g.trim() !== ''),
    }));
    form.post('/fisio/pacientes');
  };

  const erro = (k: string) => (form.errors as Record<string, string>)[k];
  const marcar = (v: string, on: boolean) => form.setData('condition_flags', on ? [...form.data.condition_flags, v] : form.data.condition_flags.filter((x) => x !== v));

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-[760px]">
        <form onSubmit={enviar} className="flex h-full flex-col">
          <SheetHeader>
            <SheetTitle>Novo paciente</SheetTitle>
            <SheetDescription>O paciente é um contato da empresa. Nome e telefone ficam no contato; aqui vai o perfil clínico.</SheetDescription>
          </SheetHeader>
          <div className="flex flex-col gap-5 px-4">
            <Segmented value={origem} onValueChange={(v) => v && setOrigem(v as typeof origem)}
              options={[{ value: 'existente', label: 'Contato existente' }, { value: 'novo', label: 'Contato novo' }]} />

            {origem === 'existente' ? (
              <div className="flex flex-col gap-2">
                <Label>Contato</Label>
                <Command shouldFilter={false} className="rounded-lg border border-border">
                  <CommandInput placeholder="Buscar contato por nome ou telefone" value={termo} onValueChange={setTermo} />
                  <CommandList>
                    <CommandEmpty>Nenhum contato disponível.</CommandEmpty>
                    <CommandGroup>
                      {contatos.map((c) => (
                        <CommandItem key={c.id} value={String(c.id)} onSelect={() => form.setData('contact_id', c.id)}
                          data-checked={form.data.contact_id === c.id} className="justify-between">
                          <span>{c.nome}</span>
                          <span className="tabular-nums text-muted-foreground">{form.data.contact_id === c.id ? 'selecionado · ' : ''}{c.telefone}</span>
                        </CommandItem>
                      ))}
                    </CommandGroup>
                  </CommandList>
                </Command>
                {erro('contact_id') && <p className="text-xs text-destructive">{erro('contact_id')}</p>}
              </div>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="flex flex-col gap-1"><Label htmlFor="nc-nome">Nome</Label>
                  <Input id="nc-nome" value={form.data.novo_contato.nome} onChange={(e) => form.setData('novo_contato', { ...form.data.novo_contato, nome: e.target.value })} />
                  {erro('novo_contato.nome') && <p className="text-xs text-destructive">{erro('novo_contato.nome')}</p>}</div>
                <div className="flex flex-col gap-1"><Label htmlFor="nc-tel">Telefone (WhatsApp)</Label>
                  <Input id="nc-tel" inputMode="tel" value={form.data.novo_contato.telefone} onChange={(e) => form.setData('novo_contato', { ...form.data.novo_contato, telefone: e.target.value })} />
                  {erro('novo_contato.telefone') && <p className="text-xs text-destructive">{erro('novo_contato.telefone')}</p>}</div>
              </div>
            )}

            <PerfilClinico form={form} marcadores={marcadores} marcar={marcar} erro={erro} />
          </div>
          <SheetFooter className="flex-row justify-end">
            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={form.processing}>Cadastrar paciente</Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}

PacientesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
