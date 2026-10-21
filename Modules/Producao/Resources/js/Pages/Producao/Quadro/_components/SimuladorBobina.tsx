// Simulador de aproveitamento da bobina (UC-FLX-13 · US-PCP-024), em lote a partir do quadro.
// O arranjo é do servidor (`POST /producao/aproveitamento/simular`): aqui só se escolhe a mídia,
// cadastra a largura útil quando falta (`PUT /producao/bobinas/{material}`) e desenha as faixas.
// Aceitar (`POST /producao/aproveitamento`) recalcula no servidor e gera o romaneio de cada OS na
// etapa atual do cartão; só aparece para quem tem o papel de liberar (o mesmo do romaneio).
import { useLayoutEffect, useMemo, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Switch } from '@/Components/ui/switch';
import { json } from '../_lib/json';
import type { Bobina, Cartao, Midia } from '../_lib/tipos';

interface Peca { os_id: number; descricao: string; largura_m: string; altura_m: string; girada: boolean }
interface Simulacao {
  bobina: Bobina; faixas: { altura_m: string; pecas: Peca[] }[]; pecas: number;
  metros_lineares: string; perda_pct: string; area_util_m2: string;
  uma_a_uma: { metros_lineares: string; perda_pct: string };
  por_os: { os_id: number; metros_previstos: string; romaneio?: string }[];
}

const n = (v: string) => Number(v);
const br = (v: string, casas = 2) => n(v).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });

function Desenho({ sim, cartoes }: { sim: Simulacao; cartoes: Cartao[] }) {
  const larg = n(sim.bobina.largura_util_m); const margem = n(sim.bobina.margem_m);
  const alt = sim.faixas.reduce((s, f) => s + n(f.altura_m), 0) + margem * Math.max(0, sim.faixas.length - 1);
  const tom = (osId: number) => 30 + ((cartoes.findIndex((c) => c.os_id === osId) * 23) % 60);
  let y = 0;
  return (
    <svg viewBox={`0 0 ${larg} ${alt}`} className="w-full rounded border border-border" style={{ maxHeight: 420 }} role="img"
      aria-label={`Arranjo de ${sim.pecas} peças em ${sim.faixas.length} faixas, ${br(sim.metros_lineares)} m de bobina`} preserveAspectRatio="xMidYMin meet">
      <rect x={0} y={0} width={larg} height={alt} style={{ fill: 'var(--bg-2, var(--bg))' }} />
      {sim.faixas.map((f, i) => {
        const y0 = y; let x = 0; y += n(f.altura_m) + margem;
        return (
          <g key={i}>{f.pecas.map((p, j) => {
            const x0 = x; x += n(p.largura_m) + margem;
            return (
              <rect key={j} x={x0} y={y0} width={n(p.largura_m)} height={n(p.altura_m)} vectorEffect="non-scaling-stroke"
                style={{ fill: `color-mix(in oklch, var(--accent) ${tom(p.os_id)}%, var(--surface))`, stroke: 'var(--border)' }}>
                <title>{`${cartoes.find((c) => c.os_id === p.os_id)?.numero ?? p.os_id} · ${p.descricao} · ${br(p.largura_m, 3)} × ${br(p.altura_m, 3)} m${p.girada ? ' (girada)' : ''}`}</title>
              </rect>
            );
          })}</g>
        );
      })}
    </svg>
  );
}

export default function SimuladorBobina({ cartoes, midias, podeAceitar, onClose, onAceito }:
  { cartoes: Cartao[]; midias: Midia[]; podeAceitar: boolean; onClose: () => void; onAceito: (msg: string) => void }) {
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  // Mídias das OS escolhidas; cada simulação junta só as OS que usam a mídia escolhida.
  const comuns = useMemo(() => midias.filter((m) => cartoes.some((c) => c.midias.includes(m.id))), [midias, cartoes]);
  const [midiaId, setMidiaId] = useState(comuns[0] ? String(comuns[0].id) : '');
  const midia = comuns.find((m) => String(m.id) === midiaId) ?? null;
  const [bobina, setBobina] = useState<Bobina | null>(midia?.bobina ?? null);
  const [form, setForm] = useState({ largura: '', margem: '0', girar: true });
  const [sim, setSim] = useState<Simulacao | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [ocupado, setOcupado] = useState(false);
  const osDaMidia = cartoes.filter((c) => midia && c.midias.includes(midia.id));

  const trocarMidia = (id: string) => { setMidiaId(id); setBobina(comuns.find((m) => String(m.id) === id)?.bobina ?? null); setSim(null); setErro(null); };
  const rodar = async (fazer: () => Promise<void>) => {
    setOcupado(true); setErro(null);
    try { await fazer(); } catch (e) { setErro((e as Error).message); } finally { setOcupado(false); }
  };
  const salvarBobina = () => rodar(async () => {
    const b = await json<Bobina>('PUT', `/producao/bobinas/${midiaId}`, {
      largura_util_m: form.largura.replace(',', '.'), margem_m: form.margem.replace(',', '.') || 0, permite_girar: form.girar });
    setBobina(b);
  });
  const simular = () => rodar(async () => setSim(await json<Simulacao>('POST', '/producao/aproveitamento/simular', { material_id: Number(midiaId), os_ids: osDaMidia.map((c) => c.os_id) })));
  const aceitar = () => rodar(async () => {
    const r = await json<Simulacao>('POST', '/producao/aproveitamento', { material_id: Number(midiaId), os: osDaMidia.map((c) => ({ os_id: c.os_id, programacao_id: c.etapa.id })) });
    onAceito(`Arranjo aceito: ${br(r.metros_lineares)} m de ${midia?.nome}. Romaneios ${r.por_os.map((o) => o.romaneio).filter(Boolean).join(', ')}.`);
  });

  return (
    <Sheet open onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent side="right" container={cockpit} className="w-full overflow-y-auto sm:max-w-[760px]" data-contract="producao-simulador-bobina">
        <SheetHeader>
          <SheetTitle>Simular bobina · {cartoes.length} OS</SheetTitle>
          <SheetDescription>Junta as peças das OS escolhidas na largura da bobina, em faixas. Simular não grava nada.</SheetDescription>
        </SheetHeader>
        <div className="flex flex-col gap-4 px-4">
          {comuns.length === 0 ? <p className="pcv-help">Nenhuma das OS escolhidas tem peça medida no orçamento.</p> : (
            <div><Label>Mídia</Label>
              <Select value={midiaId} onValueChange={trocarMidia}>
                <SelectTrigger aria-label="Mídia"><SelectValue /></SelectTrigger>
                <SelectContent>{comuns.map((m) => <SelectItem key={m.id} value={String(m.id)}>{m.nome}</SelectItem>)}</SelectContent>
              </Select>
              <p className="pcv-help">{osDaMidia.length} de {cartoes.length} OS usam esta mídia: {osDaMidia.map((c) => c.numero).join(', ')}.</p></div>
          )}
          {midia && !bobina && (
            <div className="flex flex-col gap-2 rounded-lg border border-border p-3" data-contract="producao-bobina-cadastro">
              <p className="text-sm">Falta a largura útil da bobina desta mídia.</p>
              <div className="grid grid-cols-2 gap-3">
                <div><Label htmlFor="bob-larg">Largura útil (m)</Label><Input id="bob-larg" inputMode="decimal" value={form.largura} onChange={(e) => setForm({ ...form, largura: e.target.value })} /></div>
                <div><Label htmlFor="bob-marg">Margem entre peças (m)</Label><Input id="bob-marg" inputMode="decimal" value={form.margem} onChange={(e) => setForm({ ...form, margem: e.target.value })} /></div>
              </div>
              <label className="flex items-center gap-2 text-sm"><Switch checked={form.girar} onCheckedChange={(v) => setForm({ ...form, girar: v })} /> Pode girar a peça</label>
              <div><Button size="sm" variant="outline" onClick={salvarBobina} disabled={ocupado || form.largura === ''}>Salvar bobina</Button></div>
            </div>
          )}
          {midia && bobina && <p className="pcv-help">Bobina com {br(bobina.largura_util_m, 3)} m úteis, margem de {br(bobina.margem_m, 3)} m{bobina.permite_girar ? ', peças podem girar' : ''}.</p>}
          {erro && <p className="text-sm text-destructive" role="alert">{erro}</p>}
          {sim && (
            <div className="flex flex-col gap-3" data-contract="producao-simulacao">
              <Desenho sim={sim} cartoes={cartoes} />
              <dl className="grid grid-cols-3 gap-3 text-sm">
                <div><dt className="pcv-help">Arranjo</dt><dd className="mono">{br(sim.metros_lineares)} m · perda {br(sim.perda_pct)}%</dd></div>
                <div><dt className="pcv-help">Uma a uma</dt><dd className="mono">{br(sim.uma_a_uma.metros_lineares)} m · perda {br(sim.uma_a_uma.perda_pct)}%</dd></div>
                <div><dt className="pcv-help">Economia</dt><dd className="mono">{br(String(n(sim.uma_a_uma.metros_lineares) - n(sim.metros_lineares)))} m</dd></div>
              </dl>
            </div>
          )}
        </div>
        <SheetFooter>
          {midia && bobina && <Button onClick={simular} disabled={ocupado || osDaMidia.length === 0}>Simular</Button>}
          {sim && podeAceitar && <Button variant="outline" onClick={aceitar} disabled={ocupado}>Aceitar e gerar romaneios</Button>}
          <Button variant="ghost" onClick={onClose}>Fechar</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
