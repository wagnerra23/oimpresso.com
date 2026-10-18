// @memcofre
//   tela: /fisio/biblioteca
//   module: Fisio
//   stories: US-FISIO-004 (UC-FBI-01..07)
//   permissao: fisio.access · cadastrar/editar exercício próprio exige fisio.editar
//
// Biblioteca de exercícios (grade). Mostra a biblioteca global do oimpresso (só leitura) e os
// exercícios próprios da clínica. Filtro por região e busca por nome e sinônimos (decisão nº 3 da
// ADR 0437 §14, aceita por [W] em 2026-10-18). A tela não sugere exercício para paciente nenhum:
// quem escolhe é o profissional (ADR 0437 §5).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Pencil, Plus, Search } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';
import { MidiaCampo, MidiaPreview, type MidiaResumo } from '../_components/MidiaExercicio';

interface Exercicio {
  id: number;
  name: string;
  region: string;
  type: string;
  pose: string | null;
  steps: string[];
  synonyms: string[];
  default_sets: number | null;
  default_reps: number | null;
  default_hold_s: number | null;
  times_per_day: number;
  safety_notes: string[];
  needs_support: boolean;
  global: boolean;
  midia: MidiaResumo | null;
}
interface Props {
  filtros: { region: string; q: string };
  pode_editar: boolean;
  exercicios?: Exercicio[];
  regioes?: string[];
}

/** Sentinela do "todas as regiões" — nunca value="" (Radix derruba a tela). */
const TODAS = '__todas__';

function dose(e: Exercicio): string {
  const partes: string[] = [];
  if (e.default_sets) partes.push(`${e.default_sets} séries`);
  if (e.default_reps) partes.push(`${e.default_reps} repetições`);
  if (e.default_hold_s) partes.push(`${e.default_hold_s} s de sustentação`);
  partes.push(`${e.times_per_day}× ao dia`);
  return partes.join(' · ');
}

function Grade({ exercicios, filtros, podeEditar, onEditar }: {
  exercicios: Exercicio[];
  filtros: Props['filtros'];
  podeEditar: boolean;
  onEditar: (e: Exercicio) => void;
}) {
  if (exercicios.length === 0) {
    return (
      <p className="py-8 text-center text-sm text-muted-foreground">
        {filtros.q || filtros.region ? 'Nenhum exercício com esse filtro.' : 'A biblioteca ainda não tem exercícios.'}
      </p>
    );
  }
  return (
    <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" data-contract="fisio-biblioteca-grade">
      {exercicios.map((e) => (
        <li key={e.id}>
          <Card className="h-full">
            <CardHeader className="flex flex-row items-start justify-between gap-2 p-4 pb-2">
              <div className="min-w-0">
                <CardTitle className="text-sm">{e.name}</CardTitle>
                <div className="mt-1 flex flex-wrap gap-1">
                  <Badge variant="outline">{e.region}</Badge>
                  <Badge variant="outline">{e.type}</Badge>
                  {e.global ? <Badge variant="secondary">Biblioteca oimpresso</Badge> : <Badge>Da clínica</Badge>}
                </div>
              </div>
              {podeEditar && !e.global && (
                <Button variant="ghost" size="icon" aria-label={`Editar ${e.name}`} onClick={() => onEditar(e)}>
                  <Pencil className="size-4" aria-hidden="true" />
                </Button>
              )}
            </CardHeader>
            <CardContent className="flex flex-col gap-2 p-4 pt-0 text-sm">
              {e.midia && <MidiaPreview midia={e.midia} nome={e.name} className="aspect-video" />}
              <ol className="list-decimal pl-5 text-muted-foreground">
                {e.steps.map((passo, i) => <li key={i}>{passo}</li>)}
              </ol>
              <p className="text-xs text-muted-foreground">{dose(e)}</p>
              {e.safety_notes.length > 0 && (
                <ul className="text-xs text-muted-foreground">
                  {e.safety_notes.map((aviso, i) => <li key={i}>Atenção: {aviso}</li>)}
                </ul>
              )}
            </CardContent>
          </Card>
        </li>
      ))}
    </ul>
  );
}

interface FormExercicio {
  name: string;
  region: string;
  type: string;
  pose: string;
  steps: string;
  synonyms: string;
  default_sets: string;
  default_reps: string;
  default_hold_s: string;
  times_per_day: string;
}

const linhas = (s: string) => s.split('\n').map((l) => l.trim()).filter(Boolean);
const virgulas = (s: string) => s.split(',').map((l) => l.trim()).filter(Boolean);
const inteiro = (s: string) => (s.trim() === '' ? null : Number(s));

function FormularioExercicio({ editando, onClose }: { editando: Exercicio | null; onClose: () => void }) {
  const form = useForm<FormExercicio>({
    name: editando?.name ?? '',
    region: editando?.region ?? '',
    type: editando?.type ?? '',
    pose: editando?.pose ?? '',
    steps: (editando?.steps ?? []).join('\n'),
    synonyms: (editando?.synonyms ?? []).join(', '),
    default_sets: editando?.default_sets != null ? String(editando.default_sets) : '',
    default_reps: editando?.default_reps != null ? String(editando.default_reps) : '',
    default_hold_s: editando?.default_hold_s != null ? String(editando.default_hold_s) : '',
    times_per_day: String(editando?.times_per_day ?? 1),
  });
  const erros = form.errors as Record<string, string | undefined>;

  const enviar = (ev: FormEvent) => {
    ev.preventDefault();
    form.transform((d) => ({
      name: d.name,
      region: d.region,
      type: d.type,
      pose: d.pose || null,
      steps: linhas(d.steps),
      synonyms: virgulas(d.synonyms),
      default_sets: inteiro(d.default_sets),
      default_reps: inteiro(d.default_reps),
      default_hold_s: inteiro(d.default_hold_s),
      times_per_day: Number(d.times_per_day) || 1,
    }));
    const opcoes = { preserveScroll: true, onSuccess: onClose };
    if (editando) {
      form.put(`/fisio/biblioteca/exercicios/${editando.id}`, opcoes);
    } else {
      form.post('/fisio/biblioteca/exercicios', opcoes);
    }
  };

  const campo = (id: keyof FormExercicio, rotulo: string, extra?: { type?: string; placeholder?: string }) => (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={`fx-${id}`}>{rotulo}</Label>
      <Input
        id={`fx-${id}`}
        type={extra?.type ?? 'text'}
        placeholder={extra?.placeholder}
        value={form.data[id]}
        onChange={(e) => form.setData(id, e.target.value)}
      />
      {erros[id] && <p className="text-xs text-destructive">{erros[id]}</p>}
    </div>
  );

  return (
    <Sheet open onOpenChange={(aberto) => !aberto && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <form onSubmit={enviar} className="flex flex-col gap-4">
          <SheetHeader>
            <SheetTitle>{editando ? 'Editar exercício da clínica' : 'Novo exercício da clínica'}</SheetTitle>
            <SheetDescription>Fica visível só para a sua clínica. A biblioteca do oimpresso não muda.</SheetDescription>
          </SheetHeader>
          <div className="flex flex-col gap-4 px-4">
            {campo('name', 'Nome')}
            <div className="grid grid-cols-2 gap-3">
              {campo('region', 'Região', { placeholder: 'ex.: lombar' })}
              {campo('type', 'Objetivo', { placeholder: 'ex.: fortalecimento' })}
            </div>
            {campo('pose', 'Posição', { placeholder: 'ex.: deitado' })}
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="fx-steps">Passos (um por linha)</Label>
              <Textarea id="fx-steps" rows={4} value={form.data.steps} onChange={(e) => form.setData('steps', e.target.value)} />
              {erros.steps && <p className="text-xs text-destructive">{erros.steps}</p>}
            </div>
            {campo('synonyms', 'Outros nomes (separados por vírgula)', { placeholder: 'ex.: elevação pélvica' })}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              {campo('default_sets', 'Séries', { type: 'number' })}
              {campo('default_reps', 'Repetições', { type: 'number' })}
              {campo('default_hold_s', 'Sustentação (s)', { type: 'number' })}
              {campo('times_per_day', 'Vezes ao dia', { type: 'number' })}
            </div>
            {editando && <MidiaCampo exercicioId={editando.id} nome={editando.name} midia={editando.midia} />}
          </div>
          <SheetFooter>
            <Button type="submit" disabled={form.processing}>{editando ? 'Salvar' : 'Cadastrar exercício'}</Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}

export default function BibliotecaIndex({ filtros, pode_editar, exercicios, regioes }: Props) {
  const [q, setQ] = useState(filtros.q);
  const [formulario, setFormulario] = useState<{ editando: Exercicio | null } | null>(null);

  const visitar = (params: { region?: string; q?: string }) => {
    const limpo = Object.fromEntries(Object.entries(params).filter(([, v]) => v));
    router.get('/fisio/biblioteca', limpo, { preserveState: true, replace: true });
  };
  const buscar = (e: FormEvent) => {
    e.preventDefault();
    visitar({ region: filtros.region, q });
  };

  return (
    <div data-screen-label="Fisio · Biblioteca">
      <PageHeader
        title="Biblioteca de exercícios"
        actions={pode_editar ? (
          <Button onClick={() => setFormulario({ editando: null })}><Plus className="size-4" aria-hidden="true" /> Novo exercício</Button>
        ) : undefined}
      />
      <div className="flex flex-col gap-4 p-4">
        <div className="flex flex-wrap items-end gap-3">
          <form onSubmit={buscar} className="flex min-w-0 max-w-md flex-1 gap-2" role="search">
            <Input aria-label="Buscar exercício pelo nome" placeholder="Buscar pelo nome ou outro nome" value={q} onChange={(e) => setQ(e.target.value)} />
            <Button type="submit" variant="outline"><Search className="size-4" aria-hidden="true" /> Buscar</Button>
          </form>
          <Deferred data="regioes" fallback={<Skeleton className="h-9 w-48" />}>
            <Select value={filtros.region || TODAS} onValueChange={(v) => visitar({ region: v === TODAS ? '' : v, q: filtros.q })}>
              <SelectTrigger className="w-48" aria-label="Filtrar por região"><SelectValue placeholder="Região" /></SelectTrigger>
              <SelectContent>
                <SafeSelectItem value={TODAS}>Todas as regiões</SafeSelectItem>
                {(regioes ?? []).map((r) => <SafeSelectItem key={r} value={r}>{r}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </Deferred>
        </div>
        <Deferred data="exercicios" fallback={<Skeleton className="h-64 w-full" />}>
          <Grade
            exercicios={exercicios ?? []}
            filtros={filtros}
            podeEditar={pode_editar}
            onEditar={(e) => setFormulario({ editando: e })}
          />
        </Deferred>
      </div>
      {formulario && <FormularioExercicio editando={formulario.editando} onClose={() => setFormulario(null)} />}
    </div>
  );
}

BibliotecaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
