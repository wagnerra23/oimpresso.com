// Editor de posto, máquina ou veículo em drawer (PT-02, 760px). Valores de custo são só
// cadastro (nenhuma conta aqui): o custo real é do CustoRealService (thread 02).
// Motivos: lista fechada por posto (UC-PCV-33), um por linha. Posto novo nasce com o padrão
// sugerido pelo servidor (Motivo::PADRAO); a lista se ajusta depois de salvo.
import { router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

export interface Posto {
  id: number; codigo: string; nome: string; setor: string | null; tipo_custo: 'maquina' | 'pessoa';
  veiculo: boolean; placa: string | null; custo_hora: string; custo_km: string | null;
  capacidade_por_hora: string | null; unidade_capacidade: string | null; ativo: boolean;
  motivos: Record<'pausa' | 'refugo', string[]>;
}

const linhas = (t: string) => t.split('\n').map((x) => x.trim()).filter(Boolean);

function Campo({ rotulo, erro, children }: { rotulo: string; erro?: string; children: ReactNode }) {
  return <div><Label>{rotulo}</Label>{children}{erro && <p className="text-xs text-destructive">{erro}</p>}</div>;
}

export default function EditorPosto({ posto, veiculo, onClose }: { posto: Posto | null; veiculo: boolean; onClose: () => void }) {
  const form = useForm({
    codigo: posto?.codigo ?? '', nome: posto?.nome ?? '', setor: posto?.setor ?? '',
    tipo_custo: posto?.tipo_custo ?? 'maquina', veiculo, placa: posto?.placa ?? '',
    custo_hora: posto?.custo_hora ?? '', custo_km: posto?.custo_km ?? '',
    capacidade_por_hora: posto?.capacidade_por_hora ?? '', unidade_capacidade: posto?.unidade_capacidade ?? '', ativo: posto?.ativo ?? true,
  });
  const [pausa, setPausa] = useState((posto?.motivos.pausa ?? []).join('\n'));
  const [refugo, setRefugo] = useState((posto?.motivos.refugo ?? []).join('\n'));
  const { data, setData, errors, processing } = form;
  const editaMotivos = posto !== null && !veiculo;

  const salvarMotivos = () => {
    if (!posto) return onClose();
    router.put(`/producao/postos/${posto.id}/motivos`, { tipo: 'pausa', nomes: linhas(pausa) }, {
      preserveScroll: true,
      onSuccess: () => router.put(`/producao/postos/${posto.id}/motivos`, { tipo: 'refugo', nomes: linhas(refugo) }, { preserveScroll: true, onSuccess: onClose }),
    });
  };
  const salvar = () => {
    form.transform((d) => ({ ...d, custo_hora: String(d.custo_hora).replace(',', '.'), custo_km: String(d.custo_km).replace(',', '.') || null }));
    const opts = { preserveScroll: true, onSuccess: () => (editaMotivos ? salvarMotivos() : onClose()) };
    if (posto) form.put(`/producao/postos/${posto.id}`, opts);
    else form.post('/producao/postos', opts);
  };

  return (
    <Sheet open onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-[760px]" data-contract="producao-posto-editor">
        <SheetHeader>
          <SheetTitle>{posto ? `Editar ${posto.nome}` : veiculo ? 'Novo veículo' : 'Novo posto'}</SheetTitle>
          <SheetDescription>{veiculo ? 'Veículo custa pela hora e pelo km.' : 'Capacidade e custo-hora são da sua empresa.'}</SheetDescription>
        </SheetHeader>
        <div className="grid grid-cols-2 gap-3 px-4">
          <Campo rotulo="Código" erro={errors.codigo}><Input value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} /></Campo>
          <Campo rotulo="Nome" erro={errors.nome}><Input value={data.nome} onChange={(e) => setData('nome', e.target.value)} /></Campo>
          {veiculo ? (
            <Campo rotulo="Placa" erro={errors.placa}><Input value={data.placa} onChange={(e) => setData('placa', e.target.value.toUpperCase())} /></Campo>
          ) : (
            <Campo rotulo="Setor" erro={errors.setor}><Input value={data.setor} onChange={(e) => setData('setor', e.target.value)} placeholder="Impressão" /></Campo>
          )}
          {!veiculo && (
            <Campo rotulo="Custo por" erro={errors.tipo_custo}>
              <Select value={data.tipo_custo} onValueChange={(v) => setData('tipo_custo', v as 'maquina' | 'pessoa')}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent><SelectItem value="maquina">Hora da máquina</SelectItem><SelectItem value="pessoa">Hora da pessoa</SelectItem></SelectContent>
              </Select>
            </Campo>
          )}
          <Campo rotulo="Custo-hora (R$)" erro={errors.custo_hora}><Input inputMode="decimal" value={data.custo_hora} onChange={(e) => setData('custo_hora', e.target.value)} /></Campo>
          {veiculo ? (
            <Campo rotulo="Custo por km (R$)" erro={errors.custo_km}><Input inputMode="decimal" value={data.custo_km} onChange={(e) => setData('custo_km', e.target.value)} /></Campo>
          ) : (
            <Campo rotulo="Capacidade por hora" erro={errors.capacidade_por_hora}>
              <div className="flex gap-2"><Input inputMode="decimal" value={data.capacidade_por_hora} onChange={(e) => setData('capacidade_por_hora', e.target.value.replace(',', '.'))} />
                <div className="w-20 shrink-0"><Input aria-label="Unidade" placeholder="m²" value={data.unidade_capacidade} onChange={(e) => setData('unidade_capacidade', e.target.value)} /></div></div>
            </Campo>
          )}
          <div className="col-span-2 flex items-center gap-2 text-sm"><Switch id="pcp-posto-ativo" checked={data.ativo} onCheckedChange={(v) => setData('ativo', v)} /><Label htmlFor="pcp-posto-ativo">Ativo</Label></div>
          {editaMotivos && (
            <>
              <Campo rotulo="Motivos de pausa (um por linha)"><Textarea rows={6} value={pausa} onChange={(e) => setPausa(e.target.value)} /></Campo>
              <Campo rotulo="Motivos de refugo (um por linha)"><Textarea rows={6} value={refugo} onChange={(e) => setRefugo(e.target.value)} /></Campo>
            </>
          )}
          {!posto && !veiculo && <p className="pcv-help col-span-2">O posto novo já nasce com os motivos sugeridos (troca de bobina, setup, falta de material…). Ajuste depois de salvar.</p>}
        </div>
        <SheetFooter>
          <Button variant="outline" onClick={onClose}>Cancelar</Button>
          <Button onClick={salvar} disabled={processing}>Salvar</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
