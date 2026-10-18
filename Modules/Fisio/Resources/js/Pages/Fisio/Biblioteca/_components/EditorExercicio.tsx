// Editor do exercício PRÓPRIO da clínica (US-FISIO-004 · UC-FBI-02/03), em drawer lateral.
// O global do oimpresso nunca abre aqui: o servidor recusa com 403 e a grade nem oferece editar.
// Passos e avisos são um por linha. Contraindicação é aviso ao prescrever, nunca bloqueio
// (ADR 0437 §13.2) — e o paciente não vê nada disso.
import { useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import { rotuloContraindicacao, type Exercicio, type Facetas } from '../_lib/biblioteca';

const linhas = (t: string) => t.split('\n').map((x) => x.trim()).filter(Boolean);
const numero = (t: string) => (t.trim() === '' ? null : Number(t));

function Campo({ rotulo, id, erro, children }: { rotulo: string; id?: string; erro?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro && <p className="text-xs text-destructive">{erro}</p>}
    </div>
  );
}

function Escolha({ id, valor, opcoes, onChange }: { id: string; valor: string; opcoes: Record<string, string>; onChange: (v: string) => void }) {
  return (
    <Select value={valor} onValueChange={onChange}>
      <SelectTrigger id={id}><SelectValue placeholder="Escolha" /></SelectTrigger>
      <SelectContent>
        {Object.entries(opcoes).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
      </SelectContent>
    </Select>
  );
}

function Marcas({ prefixo, opcoes, marcadas, onChange }: { prefixo: string; opcoes: [string, string][]; marcadas: string[]; onChange: (v: string[]) => void }) {
  const alternar = (k: string, on: boolean) => onChange(on ? [...marcadas, k] : marcadas.filter((x) => x !== k));
  return (
    <div className="grid grid-cols-2 gap-2">
      {opcoes.map(([k, v]) => (
        <div key={k} className="flex items-center gap-2 text-sm">
          <Checkbox id={`${prefixo}-${k}`} checked={marcadas.includes(k)} onCheckedChange={(c) => alternar(k, c === true)} />
          <Label htmlFor={`${prefixo}-${k}`} className="font-normal">{v}</Label>
        </div>
      ))}
    </div>
  );
}

export default function EditorExercicio({ exercicio, facetas, onClose }: { exercicio: Exercicio | null; facetas: Facetas; onClose: () => void }) {
  const form = useForm({
    name: exercicio?.name ?? '',
    region: exercicio?.region ?? '',
    type: exercicio?.type ?? '',
    pose: exercicio?.pose ?? '',
    equipment: exercicio?.equipment ?? [],
    steps: (exercicio?.steps ?? []).join('\n'),
    default_sets: exercicio?.default_sets?.toString() ?? '',
    default_reps: exercicio?.default_reps?.toString() ?? '',
    default_hold_s: exercicio?.default_hold_s?.toString() ?? '',
    times_per_day: (exercicio?.times_per_day ?? 1).toString(),
    safety_notes: (exercicio?.safety_notes ?? []).join('\n'),
    contraindication_flags: exercicio?.contraindication_flags ?? [],
    needs_support: exercicio?.needs_support ?? false,
  });
  const { data, setData, errors, processing } = form;
  const erroPassos = errors.steps ?? (errors as Record<string, string | undefined>)['steps.0'];

  const salvar = () => {
    form.transform((d) => ({
      ...d,
      pose: d.pose || null,
      steps: linhas(d.steps),
      safety_notes: linhas(d.safety_notes),
      default_sets: numero(d.default_sets),
      default_reps: numero(d.default_reps),
      default_hold_s: numero(d.default_hold_s),
      times_per_day: Number(d.times_per_day || 1),
    }));
    const opts = { preserveScroll: true, onSuccess: onClose };
    if (exercicio) form.put(`/fisio/biblioteca/exercicios/${exercicio.id}`, opts);
    else form.post('/fisio/biblioteca/exercicios', opts);
  };

  return (
    <Sheet open onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-[760px]" data-contract="fisio-exercicio-editor">
        <SheetHeader>
          <SheetTitle>{exercicio ? `Editar ${exercicio.name}` : 'Novo exercício da clínica'}</SheetTitle>
          <SheetDescription>Fica só na sua clínica. A biblioteca do oimpresso não muda.</SheetDescription>
        </SheetHeader>
        <div className="grid grid-cols-2 gap-3 px-4">
          <div className="col-span-2">
            <Campo rotulo="Nome" id="fisio-ex-nome" erro={errors.name}>
              <Input id="fisio-ex-nome" value={data.name} onChange={(e) => setData('name', e.target.value)} />
            </Campo>
          </div>
          <Campo rotulo="Região" id="fisio-ex-regiao" erro={errors.region}>
            <Escolha id="fisio-ex-regiao" valor={data.region} opcoes={facetas.regioes} onChange={(v) => setData('region', v)} />
          </Campo>
          <Campo rotulo="Tipo" id="fisio-ex-tipo" erro={errors.type}>
            <Escolha id="fisio-ex-tipo" valor={data.type} opcoes={facetas.tipos} onChange={(v) => setData('type', v)} />
          </Campo>
          <Campo rotulo="Posição" id="fisio-ex-posicao" erro={errors.pose}>
            <Escolha id="fisio-ex-posicao" valor={data.pose} opcoes={facetas.posicoes} onChange={(v) => setData('pose', v)} />
          </Campo>
          <Campo rotulo="Vezes por dia" id="fisio-ex-vezes" erro={errors.times_per_day}>
            <Input id="fisio-ex-vezes" inputMode="numeric" value={data.times_per_day} onChange={(e) => setData('times_per_day', e.target.value)} />
          </Campo>
          <div className="col-span-2 grid grid-cols-3 gap-3">
            <Campo rotulo="Séries" id="fisio-ex-series" erro={errors.default_sets}>
              <Input id="fisio-ex-series" inputMode="numeric" value={data.default_sets} onChange={(e) => setData('default_sets', e.target.value)} />
            </Campo>
            <Campo rotulo="Repetições" id="fisio-ex-reps" erro={errors.default_reps}>
              <Input id="fisio-ex-reps" inputMode="numeric" value={data.default_reps} onChange={(e) => setData('default_reps', e.target.value)} />
            </Campo>
            <Campo rotulo="Sustentar (s)" id="fisio-ex-sust" erro={errors.default_hold_s}>
              <Input id="fisio-ex-sust" inputMode="numeric" value={data.default_hold_s} onChange={(e) => setData('default_hold_s', e.target.value)} />
            </Campo>
          </div>
          <div className="col-span-2">
            <Campo rotulo="Passos (um por linha)" id="fisio-ex-passos" erro={erroPassos}>
              <Textarea id="fisio-ex-passos" rows={5} value={data.steps} onChange={(e) => setData('steps', e.target.value)} />
            </Campo>
          </div>
          <div className="col-span-2">
            <Campo rotulo="Avisos ao paciente (um por linha)" id="fisio-ex-avisos" erro={errors.safety_notes}>
              <Textarea id="fisio-ex-avisos" rows={3} value={data.safety_notes} onChange={(e) => setData('safety_notes', e.target.value)} placeholder="Não prenda a respiração" />
            </Campo>
          </div>
          <div className="col-span-2">
            <Campo rotulo="Equipamento caseiro" erro={errors.equipment}>
              <Marcas prefixo="fisio-ex-eq" opcoes={Object.entries(facetas.equipamentos)} marcadas={data.equipment} onChange={(v) => setData('equipment', v)} />
            </Campo>
          </div>
          <div className="col-span-2">
            <Campo rotulo="Avisar ao prescrever para paciente com" erro={errors.contraindication_flags}>
              <Marcas prefixo="fisio-ex-ci" opcoes={facetas.contraindicacoes.map((c) => [c, rotuloContraindicacao(c)])} marcadas={data.contraindication_flags} onChange={(v) => setData('contraindication_flags', v)} />
            </Campo>
          </div>
          <div className="col-span-2 flex items-center gap-2 text-sm">
            <Switch id="fisio-ex-apoio" checked={data.needs_support} onCheckedChange={(v) => setData('needs_support', v)} />
            <Label htmlFor="fisio-ex-apoio">Fazer perto de um apoio firme</Label>
          </div>
        </div>
        <SheetFooter>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button onClick={salvar} disabled={processing}>Salvar</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
