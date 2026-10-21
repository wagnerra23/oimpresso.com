// @memcofre
//   tela: /fisio/pacientes
//   module: Fisio
//   stories: US-FISIO-005 (UC-FPI-01..07)
//   permissao: fisio.access · cadastrar exige fisio.editar
//
// Lista de pacientes (PT-01). O paciente é um contato da empresa (ADR 0437 §4): o cadastro
// busca um contato existente no servidor, ou cria um contato novo (UC-FPI-06/07), e acrescenta
// só o perfil clínico. Nome e telefone vêm do contato.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Plus, Search } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Inline } from '@/Components/layout';
import DataTable from '@/Components/shared/DataTable';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import PerfilClinicoCampos, { type PerfilClinico } from '../_components/PerfilClinicoCampos';
import OrigemContato, { type NovoContato, type Origem } from './_components/OrigemContato';
import { limparObjetivos, rotuloCondicao } from '../_components/condicoes';
import FiltroResponsavel from '../_components/FiltroResponsavel';
import type { Profissional } from '../_components/ResponsavelPaciente';

interface Paciente {
  id: number;
  nome: string | null;
  telefone: string | null;
  main_complaint: string | null;
  sessions_per_week: number;
  condition_flags: string[];
  responsavel: { id: number; nome: string } | null;
}
interface Props {
  filtros: { q: string; responsavel: string };
  condicoes: string[];
  pode_editar: boolean;
  pacientes?: Paciente[];
  profissionais?: Profissional[];
}

// Slot 5 do PT-01 (DataTable). A linha inteira abre a ficha; o DataTable dá a ela papel de botão
// e Enter/Espaço, para o teclado chegar onde o mouse chega.
const COLUNAS: ColumnDef<Paciente, unknown>[] = [
  {
    id: 'paciente',
    header: 'Paciente',
    accessorFn: (p) => p.nome ?? '',
    cell: ({ row: { original: p } }) => (
      <div className="min-w-0">
        <span className="block text-sm font-medium">{p.nome ?? 'Contato sem nome'}</span>
        <span className="block truncate text-xs text-muted-foreground">{p.main_complaint || 'Sem queixa registrada'}</span>
      </div>
    ),
  },
  {
    id: 'condicoes',
    header: 'Condições',
    accessorFn: (p) => p.condition_flags.join(', '),
    cell: ({ row: { original: p } }) => (
      <Inline wrap gap={1}>
        {p.condition_flags.map((c) => <Badge key={c} variant="outline">{rotuloCondicao(c)}</Badge>)}
      </Inline>
    ),
  },
  {
    id: 'responsavel',
    header: 'Responsável',
    accessorFn: (p) => p.responsavel?.nome ?? 'sem responsável',
    cell: ({ row: { original: p } }) => (
      <span className="text-xs text-muted-foreground" data-contract="fisio-paciente-responsavel">
        {p.responsavel?.nome ?? 'sem responsável'}
      </span>
    ),
  },
  {
    id: 'sessoes',
    header: 'Sessões',
    accessorFn: (p) => p.sessions_per_week,
    cell: ({ row: { original: p } }) => <span className="text-xs text-muted-foreground">{p.sessions_per_week}× por semana</span>,
  },
];

function Lista({ pacientes, q }: { pacientes: Paciente[]; q: string }) {
  if (pacientes.length === 0) {
    return (
      <p className="py-8 text-center text-sm text-muted-foreground">
        {q ? `Nenhum paciente ativo com "${q}" no nome.` : 'Nenhum paciente cadastrado ainda.'}
      </p>
    );
  }
  return (
    <div data-contract="fisio-pacientes-lista">
      <DataTable<Paciente>
        columns={COLUNAS}
        data={pacientes}
        caption="Pacientes"
        showSearch={false}
        rowKey={(p) => p.id}
        onRowClick={(p) => router.visit(`/fisio/pacientes/${p.id}`)}
      />
    </div>
  );
}

function NovoPaciente({ condicoes, onClose }: { condicoes: string[]; onClose: () => void }) {
  const [origem, setOrigem] = useState<Origem>('existente');
  const form = useForm<PerfilClinico & { contact_id: number | null; novo_contato: NovoContato }>({
    contact_id: null, novo_contato: { nome: '', telefone: '' },
    main_complaint: '', goals: [], sessions_per_week: 3, condition_flags: [],
  });
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({
      ...d,
      contact_id: origem === 'existente' ? d.contact_id : null,
      novo_contato: origem === 'novo' ? d.novo_contato : null,
      goals: limparObjetivos(d.goals),
    }));
    form.post('/fisio/pacientes', { onSuccess: onClose });
  };
  const pronto = origem === 'existente'
    ? form.data.contact_id !== null
    : form.data.novo_contato.nome.trim() !== '' && form.data.novo_contato.telefone !== '';

  return (
    <Sheet open onOpenChange={(aberto) => !aberto && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <form onSubmit={enviar} className="flex flex-col gap-4">
          <SheetHeader>
            <SheetTitle>Novo paciente</SheetTitle>
            <SheetDescription>Escolha o contato ou cadastre um novo. Nome e telefone ficam no cadastro do contato.</SheetDescription>
          </SheetHeader>
          <div className="px-4">
            <OrigemContato
              origem={origem}
              onOrigem={setOrigem}
              contatoId={form.data.contact_id}
              onContato={(c) => form.setData('contact_id', c?.id ?? null)}
              novo={form.data.novo_contato}
              onNovo={(n) => form.setData('novo_contato', n)}
              erros={form.errors as Record<string, string>}
            />
          </div>
          <div className="px-4">
            <PerfilClinicoCampos
              dados={form.data}
              condicoes={condicoes}
              erros={form.errors as Record<string, string>}
              onChange={(campo, valor) => form.setData(campo, valor as never)}
            />
          </div>
          <SheetFooter>
            <Button type="submit" disabled={form.processing || !pronto}>Cadastrar paciente</Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}

export default function PacientesIndex({ filtros, condicoes, pode_editar, pacientes, profissionais }: Props) {
  const [q, setQ] = useState(filtros.q);
  const [criando, setCriando] = useState(false);
  const visitar = (busca: string, responsavel: string) =>
    router.get('/fisio/pacientes', { ...(busca ? { q: busca } : {}), ...(responsavel ? { responsavel } : {}) }, { preserveState: true, replace: true });
  const buscar = (e: FormEvent) => {
    e.preventDefault();
    visitar(q, filtros.responsavel);
  };

  return (
    <div data-screen-label="Fisio · Pacientes">
      <PageHeader
        title="Pacientes"
        actions={pode_editar ? <Button onClick={() => setCriando(true)}><Plus className="size-4" aria-hidden="true" /> Novo paciente</Button> : undefined}
      />
      <div className="flex flex-col gap-4 p-4">
        <form onSubmit={buscar} className="flex max-w-md gap-2" role="search">
          <Input aria-label="Buscar paciente pelo nome" placeholder="Buscar pelo nome" value={q} onChange={(e) => setQ(e.target.value)} />
          <Button type="submit" variant="outline"><Search className="size-4" aria-hidden="true" /> Buscar</Button>
        </form>
        <Deferred data="profissionais" fallback={null}>
          <FiltroResponsavel valor={filtros.responsavel} profissionais={profissionais ?? []} onChange={(v) => visitar(filtros.q, v)} />
        </Deferred>
        <Card>
          <CardContent className="p-4">
            <Deferred data="pacientes" fallback={<Skeleton className="h-40 w-full" />}>
              <Lista pacientes={pacientes ?? []} q={filtros.q} />
            </Deferred>
          </CardContent>
        </Card>
      </div>
      {criando && <NovoPaciente condicoes={condicoes} onClose={() => setCriando(false)} />}
    </div>
  );
}

PacientesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
