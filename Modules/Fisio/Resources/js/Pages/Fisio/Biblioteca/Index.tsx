// @memcofre
//   tela: /fisio/biblioteca
//   module: Fisio
//   stories: US-FISIO-004 (UC-FBI-01..06)
//   permissao: fisio.access · criar/editar/remover o próprio: fisio.editar
//
// Biblioteca de exercícios em grade. Globais do oimpresso (revisados pela responsável técnica,
// ninguém edita) + exercícios próprios da clínica. Filtro por região e por equipamento caseiro.
// Busca por texto NÃO entra na F1 (ADR 0437 §14 nº 3 não aceita por [W]).
// Charter: Index.charter.md · casos: Index.casos.md.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Pencil, Plus, ShieldAlert, Trash2 } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Grid, Inline, Stack, Text } from '@/Components/layout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import EditorExercicio from './_components/EditorExercicio';
import { dose, rotuloContraindicacao, type Exercicio, type Facetas, type Filtros } from './_lib/biblioteca';

interface Props {
  filtros: Filtros;
  facetas: Facetas;
  pode_editar: boolean;
  exercicios?: Exercicio[];
  aguardando_revisao?: number;
}

const TODOS = '__todos__';

function filtrar(filtros: Filtros, chave: keyof Filtros, valor: string) {
  const prox = { ...filtros, [chave]: valor === TODOS ? null : valor };
  const query = Object.fromEntries(Object.entries(prox).filter(([, v]) => v));
  router.get('/fisio/biblioteca', query, { preserveState: true, preserveScroll: true, replace: true });
}

function Filtro({ rotulo, id, valor, opcoes, extra, onChange }: { rotulo: string; id: string; valor: string | null; opcoes: Record<string, string>; extra?: [string, string]; onChange: (v: string) => void }) {
  return (
    <Stack gap={1}>
      <Label htmlFor={id} className="text-muted-foreground">{rotulo}</Label>
      <Select value={valor ?? TODOS} onValueChange={onChange}>
        <SelectTrigger id={id} className="w-56"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {extra && <SelectItem value={extra[0]}>{extra[1]}</SelectItem>}
          {Object.entries(opcoes).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
        </SelectContent>
      </Select>
    </Stack>
  );
}

function CartaoExercicio({ e, facetas, onEditar, onRemover }: { e: Exercicio; facetas: Facetas; onEditar: () => void; onRemover: () => void }) {
  return (
    <Card data-contract="fisio-exercicio">
      <CardContent className="flex h-full flex-col gap-3 p-4">
        <Inline gap={2} align="start" justify="between">
          <Stack gap={1} className="min-w-0">
            <Text as="h3" size="base" weight="semibold">{e.name}</Text>
            <Text size="sm" tone="muted">{[facetas.regioes[e.region] ?? e.region, facetas.tipos[e.type] ?? e.type].join(' · ')}</Text>
          </Stack>
          <Badge variant={e.global ? 'neutral' : 'info'}>{e.global ? 'Biblioteca' : 'Da clínica'}</Badge>
        </Inline>
        {dose(e) && <Text size="sm" family="mono" numeric="tabular">{dose(e)}</Text>}
        <ol className="list-decimal pl-5 text-sm text-foreground">
          {e.steps.map((p, i) => <li key={i}>{p}</li>)}
        </ol>
        {e.safety_notes.length > 0 && <Text size="sm" tone="muted">{e.safety_notes.join(' · ')}</Text>}
        <Inline gap={1} wrap>
          {e.equipment.length === 0
            ? <Badge variant="outline">Sem equipamento</Badge>
            : e.equipment.map((q) => <Badge key={q} variant="outline">{facetas.equipamentos[q] ?? q}</Badge>)}
          {e.needs_support && <Badge variant="outline">Com apoio</Badge>}
        </Inline>
        {e.contraindication_flags.length > 0 && (
          <Inline gap={1} wrap>
            <ShieldAlert className="size-3.5 text-warning" aria-hidden="true" />
            <Text size="xs" tone="muted">Avisa ao prescrever: {e.contraindication_flags.map(rotuloContraindicacao).join(', ')}</Text>
          </Inline>
        )}
        {e.editavel && (
          <Inline gap={1} justify="end" className="mt-auto">
            <Button size="sm" variant="ghost" onClick={onEditar} aria-label={`Editar ${e.name}`}><Pencil className="size-3.5" aria-hidden="true" /></Button>
            <Button size="sm" variant="ghost" onClick={onRemover} aria-label={`Remover ${e.name}`}><Trash2 className="size-3.5" aria-hidden="true" /></Button>
          </Inline>
        )}
      </CardContent>
    </Card>
  );
}

function Grade({ exercicios, facetas, onEditar }: { exercicios: Exercicio[]; facetas: Facetas; onEditar: (e: Exercicio) => void }) {
  if (exercicios.length === 0) {
    return <EmptyState icon="search-x" variant="search" title="Nenhum exercício com esse filtro" description="Troque a região ou o equipamento, ou crie um exercício da clínica." />;
  }
  const remover = (e: Exercicio) => {
    if (window.confirm(`Remover "${e.name}" da biblioteca da clínica?`)) {
      router.delete(`/fisio/biblioteca/exercicios/${e.id}`, { preserveScroll: true });
    }
  };
  return (
    <Grid min="md" gap={3} data-contract="fisio-biblioteca-grade">
      {exercicios.map((e) => <CartaoExercicio key={e.id} e={e} facetas={facetas} onEditar={() => onEditar(e)} onRemover={() => remover(e)} />)}
    </Grid>
  );
}

export default function BibliotecaIndex({ filtros, facetas, pode_editar, exercicios, aguardando_revisao }: Props) {
  const [editando, setEditando] = useState<Exercicio | 'novo' | null>(null);

  return (
    <div data-screen-label="Fisio · Biblioteca">
      <PageHeader
        title="Fisio"
        suffix=" · Biblioteca"
        subtitle="Exercícios da biblioteca do oimpresso e os da sua clínica."
        actions={pode_editar ? <Button onClick={() => setEditando('novo')}><Plus className="size-4" aria-hidden="true" /> Exercício da clínica</Button> : undefined}
      />
      <Stack gap={4} className="p-4">
        <Inline gap={3} wrap align="end" data-contract="fisio-biblioteca-filtros">
          <Filtro rotulo="Região" id="fisio-filtro-regiao" valor={filtros.region} opcoes={facetas.regioes} onChange={(v) => filtrar(filtros, 'region', v)} />
          <Filtro rotulo="Equipamento" id="fisio-filtro-equipamento" valor={filtros.equipamento} opcoes={facetas.equipamentos} extra={['nenhum', 'Sem equipamento']} onChange={(v) => filtrar(filtros, 'equipamento', v)} />
        </Inline>
        <Deferred data={['exercicios', 'aguardando_revisao']} fallback={<Grid min="md" gap={3}>{[0, 1, 2].map((i) => <Skeleton key={i} className="h-48 w-full" />)}</Grid>}>
          <Stack gap={4}>
            {(aguardando_revisao ?? 0) > 0 && (
              <Alert>
                <AlertTitle>{aguardando_revisao} exercício(s) da biblioteca em revisão clínica</AlertTitle>
                <AlertDescription>Eles aparecem aqui assim que a responsável técnica concluir a revisão.</AlertDescription>
              </Alert>
            )}
            <Grade exercicios={exercicios ?? []} facetas={facetas} onEditar={setEditando} />
          </Stack>
        </Deferred>
      </Stack>
      {editando && <EditorExercicio exercicio={editando === 'novo' ? null : editando} facetas={facetas} onClose={() => setEditando(null)} />}
    </div>
  );
}

BibliotecaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
