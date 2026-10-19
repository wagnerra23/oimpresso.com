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
import { Deferred, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Plus, Search } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import PerfilClinicoCampos, { type PerfilClinico } from '../_components/PerfilClinicoCampos';
import OrigemContato, { type NovoContato, type Origem } from './_components/OrigemContato';
import { limparObjetivos, rotuloCondicao } from '../_components/condicoes';

interface Paciente {
  id: number;
  nome: string | null;
  telefone: string | null;
  main_complaint: string | null;
  sessions_per_week: number;
  condition_flags: string[];
}
interface Props {
  filtros: { q: string };
  condicoes: string[];
  pode_editar: boolean;
  pacientes?: Paciente[];
}

function Lista({ pacientes, q }: { pacientes: Paciente[]; q: string }) {
  if (pacientes.length === 0) {
    return (
      <p className="py-8 text-center text-sm text-muted-foreground">
        {q ? `Nenhum paciente ativo com "${q}" no nome.` : 'Nenhum paciente cadastrado ainda.'}
      </p>
    );
  }
  return (
    <ul className="divide-y divide-border" data-contract="fisio-pacientes-lista">
      {pacientes.map((p) => (
        <li key={p.id}>
          <Link href={`/fisio/pacientes/${p.id}`} className="flex flex-wrap items-center gap-3 px-1 py-3 hover:bg-muted/50">
            <div className="min-w-0 flex-1">
              <span className="block text-sm font-medium">{p.nome ?? 'Contato sem nome'}</span>
              <span className="block truncate text-xs text-muted-foreground">{p.main_complaint || 'Sem queixa registrada'}</span>
            </div>
            {p.condition_flags.map((c) => <Badge key={c} variant="outline">{rotuloCondicao(c)}</Badge>)}
            <span className="text-xs text-muted-foreground">{p.sessions_per_week}× por semana</span>
          </Link>
        </li>
      ))}
    </ul>
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

export default function PacientesIndex({ filtros, condicoes, pode_editar, pacientes }: Props) {
  const [q, setQ] = useState(filtros.q);
  const [criando, setCriando] = useState(false);
  const buscar = (e: FormEvent) => {
    e.preventDefault();
    router.get('/fisio/pacientes', q ? { q } : {}, { preserveState: true, replace: true });
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
