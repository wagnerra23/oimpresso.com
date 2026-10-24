// Editor do roteiro em drawer (PT-02, 760px). Monta o mesmo formato que o servidor valida em
// SalvarRoteiroRequest (UC-PCV-13): etapas em sequência e, no máximo, blocos de 2 trilhas que se
// juntam na etapa seguinte. O editor não oferece 3ª trilha nem paralelo dentro de trilha; a regra
// continua sendo do servidor, que recusa qualquer payload fora dela.
import { useForm } from '@inertiajs/react';
import { GitMerge, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';

export interface Posto { id: number; nome: string; setor: string | null }
export interface Etapa { nome: string; posto_id?: number | null; horas?: string | number | null; opcional_por?: string | null }
export type Item = Etapa | { paralelo: Etapa[][]; junta: string };
export interface Roteiro { id: number; familia: string; nome: string; etapas: Item[]; produtos?: { id: number; nome: string; sku: string | null }[] }

const NENHUM = '__nenhum';
const vazia = (): Etapa => ({ nome: '', posto_id: null, horas: null, opcional_por: null });
const ehBloco = (i: Item): i is { paralelo: Etapa[][]; junta: string } => 'paralelo' in i;

function CamposEtapa({ e, postos, onChange, onRemover, erro }: {
  e: Etapa; postos: Posto[]; onChange: (e: Etapa) => void; onRemover: () => void; erro?: string;
}) {
  return (
    <div className="grid grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_80px_minmax(0,1.2fr)_auto] items-end gap-2">
      <div><Label className="text-xs">Etapa</Label>
        <Input value={e.nome} onChange={(v) => onChange({ ...e, nome: v.target.value })} aria-invalid={!!erro} placeholder="Ex.: Impressão UV" /></div>
      <div><Label className="text-xs">Posto</Label>
        <Select value={e.posto_id ? String(e.posto_id) : NENHUM} onValueChange={(v) => onChange({ ...e, posto_id: v === NENHUM ? null : Number(v) })}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value={NENHUM}>Sem posto</SelectItem>
            {postos.map((p) => <SafeSelectItem key={p.id} value={String(p.id)}>{p.nome}</SafeSelectItem>)}
          </SelectContent>
        </Select></div>
      <div><Label className="text-xs">Horas</Label>
        <Input inputMode="decimal" value={e.horas ?? ''} onChange={(v) => onChange({ ...e, horas: v.target.value.replace(',', '.') || null })} /></div>
      <div><Label className="text-xs">Só com (opção do orçamento)</Label>
        <Input value={e.opcional_por ?? ''} onChange={(v) => onChange({ ...e, opcional_por: v.target.value || null })} placeholder="com laminação" /></div>
      <Button type="button" variant="ghost" size="icon" onClick={onRemover} aria-label={`Remover etapa ${e.nome || ''}`.trim()}>
        <Trash2 className="size-4" aria-hidden="true" /></Button>
      {erro && <p className="col-span-5 text-xs text-destructive">{erro}</p>}
    </div>
  );
}

export default function EditorRoteiro({ roteiro, postos, onClose }: { roteiro: Roteiro | null; postos: Posto[]; onClose: () => void }) {
  const form = useForm({ familia: roteiro?.familia ?? '', nome: roteiro?.nome ?? '', etapas: roteiro?.etapas ?? [vazia()] as Item[] });
  const { data, setData, errors, processing } = form;
  const erro = (k: string) => (errors as Record<string, string>)[k];
  const trocar = (i: number, item: Item) => setData('etapas', data.etapas.map((x, k) => (k === i ? item : x)));
  const remover = (i: number) => setData('etapas', data.etapas.filter((_, k) => k !== i));

  // A junção é a etapa logo depois do bloco: o nome digitado no bloco renomeia essa etapa.
  const mudarJunta = (i: number, junta: string) => {
    const etapas = data.etapas.map((x, k) => {
      if (k === i && ehBloco(x)) return { ...x, junta };
      if (k === i + 1 && !ehBloco(x)) return { ...x, nome: junta };
      return x;
    });
    setData('etapas', etapas);
  };
  const addBloco = () => setData('etapas', [...data.etapas, { paralelo: [[vazia()], [vazia()]], junta: '' }, vazia()]);

  const salvar = () => {
    const opts = { preserveScroll: true, onSuccess: onClose };
    if (roteiro) form.put(`/producao/roteiros/${roteiro.id}`, opts);
    else form.post('/producao/roteiros', opts);
  };

  return (
    <Sheet open onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-[760px]" data-contract="producao-roteiro-editor">
        <SheetHeader>
          <SheetTitle>{roteiro ? 'Ajustar roteiro' : 'Novo roteiro'}</SheetTitle>
          <SheetDescription>Até 2 trilhas em paralelo, que sempre se juntam numa etapa nomeada.</SheetDescription>
        </SheetHeader>
        <div className="flex flex-col gap-4 px-4">
          <div className="grid grid-cols-2 gap-3">
            <div><Label>Família</Label><Input value={data.familia} onChange={(e) => setData('familia', e.target.value)} placeholder="Placa rígida" />
              {erro('familia') && <p className="text-xs text-destructive">{erro('familia')}</p>}</div>
            <div><Label>Nome</Label><Input value={data.nome} onChange={(e) => setData('nome', e.target.value)} placeholder="Placa em ACM com UV" />
              {erro('nome') && <p className="text-xs text-destructive">{erro('nome')}</p>}</div>
          </div>
          {data.etapas.map((item, i) => ehBloco(item) ? (
            <fieldset key={i} className="flex flex-col gap-3 rounded-lg border border-dashed border-border p-3">
              <legend className="px-1 text-xs text-muted-foreground">Em paralelo</legend>
              {item.paralelo.map((trilha, t) => (
                <div key={t} className="flex flex-col gap-2">
                  <span className="text-xs font-semibold">Trilha {t + 1}</span>
                  {trilha.map((e, q) => (
                    <CamposEtapa key={q} e={e} postos={postos}
                      onChange={(n) => trocar(i, { ...item, paralelo: item.paralelo.map((tr, tt) => (tt === t ? tr.map((x, qq) => (qq === q ? n : x)) : tr)) })}
                      onRemover={() => trocar(i, { ...item, paralelo: item.paralelo.map((tr, tt) => (tt === t ? tr.filter((_, qq) => qq !== q) : tr)) })} />
                  ))}
                  <Button type="button" variant="ghost" size="sm" className="self-start"
                    onClick={() => trocar(i, { ...item, paralelo: item.paralelo.map((tr, tt) => (tt === t ? [...tr, vazia()] : tr)) })}>
                    <Plus className="size-3.5" aria-hidden="true" /> Etapa na trilha {t + 1}</Button>
                </div>
              ))}
              <div><Label className="text-xs">As trilhas se juntam em</Label>
                <Input value={item.junta} onChange={(e) => mudarJunta(i, e.target.value)} aria-invalid={!!erro(`etapas.${i}.junta`)} placeholder="Montagem na fábrica" />
                {[erro(`etapas.${i}.junta`), erro(`etapas.${i}.paralelo`)].filter(Boolean).map((m) => <p key={m} className="text-xs text-destructive">{m}</p>)}</div>
              <Button type="button" variant="ghost" size="sm" className="self-start" onClick={() => remover(i)}>
                <Trash2 className="size-3.5" aria-hidden="true" /> Remover bloco</Button>
            </fieldset>
          ) : (
            <CamposEtapa key={i} e={item} postos={postos} erro={erro(`etapas.${i}.nome`)} onChange={(n) => trocar(i, n)} onRemover={() => remover(i)} />
          ))}
          {erro('etapas') && <p className="text-xs text-destructive">{erro('etapas')}</p>}
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => setData('etapas', [...data.etapas, vazia()])}>
              <Plus className="size-3.5" aria-hidden="true" /> Etapa</Button>
            <Button type="button" variant="outline" size="sm" onClick={addBloco}>
              <GitMerge className="size-3.5" aria-hidden="true" /> Bloco em paralelo</Button>
          </div>
        </div>
        <SheetFooter>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button onClick={salvar} disabled={processing}>Salvar roteiro</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
