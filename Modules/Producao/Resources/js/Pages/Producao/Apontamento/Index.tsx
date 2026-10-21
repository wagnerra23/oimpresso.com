// Producao/Apontamento — celular do operador (PWA), thread 04 + E04 do playbook PCP.
// Forma: prototipo-ui/cowork/Wagner/producao-cv-campo.jsx (Apontamento) + alvo
// governance/design/targets/producao--apontamento--index-390. A moldura de celular e o texto
// explicativo do protótipo são andaime da demonstração: aqui o aparelho É o do operador.
// A regra mora no ApontamentoOperadorService; a tela só mostra, lê o QR e envia com chave.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/Components/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import { agoraIso, enviar, esvaziar, novaChave, pendentes } from './_lib/fila';

interface Etapa { id: number; os_tipo: string; os_id: number; posto: string | null; tipo_custo: string | null; inicio_previsto: string; status: string }
interface Aberto { id: number; posto_id: number; programacao_id: number | null; os_tipo: string | null; os_id: number | null; posto: string | null; tipo_custo: string; desde: string }
interface Rodando { pessoa: Aberto | null; maquinas: Aberto[]; ponto: { entrada: string | null; saida: string | null } | null }
interface Props { operador: { id: number; nome: string }; etapas?: Etapa[]; rodando?: Rodando; motivos?: Array<{ id: number; posto_id: number; nome: string }> }

const hms = (s: number) => [Math.floor(s / 3600), Math.floor(s / 60) % 60, s % 60].map((n) => String(n).padStart(2, '0')).join(':');

function Cronometro({ desde }: { desde: string }) {
  const [seg, setSeg] = useState(0);
  useEffect(() => {
    const calc = () => setSeg(Math.max(0, Math.floor((Date.now() - new Date(desde).getTime()) / 1000)));
    calc();
    const t = setInterval(calc, 1000);
    return () => clearInterval(t);
  }, [desde]);
  return <div role="timer" aria-live="off" aria-label={`Tempo apontado ${hms(seg)}`} className="pcv-crono is-on">{hms(seg)}</div>;
}

export default function ApontamentoIndex({ operador, motivos = [] }: Props) {
  // `motivos` é deferido junto com `rodando`; antes de chegar, a pausa fica desabilitada.
  const [codigo, setCodigo] = useState('');
  const [aviso, setAviso] = useState<string | null>(null);
  const [naFila, setNaFila] = useState(() => pendentes(operador.id));
  const recarregar = useCallback(() => router.reload({ only: ['etapas', 'rodando'] }), []);

  const sincronizar = useCallback(async () => {
    const n = await esvaziar(operador.id, (j) => toast.error(String(j.mensagem ?? 'Um apontamento guardado foi recusado.')));
    setNaFila(pendentes(operador.id));
    if (n > 0) recarregar();
  }, [operador.id, recarregar]);

  useEffect(() => {
    sincronizar();
    window.addEventListener('online', sincronizar);
    return () => window.removeEventListener('online', sincronizar);
  }, [sincronizar]);

  async function apontar(url: string, corpo: Record<string, unknown> = {}) {
    const r = await enviar(operador.id, { url, body: { ...corpo, chave: novaChave(), momento: agoraIso() } });
    if (r.ok === 'fila') { setNaFila(pendentes(operador.id)); toast('Sem internet: guardado no celular, envia quando voltar.'); return; }
    if (!r.ok) { toast.error(String(r.json.mensagem ?? 'Não foi possível apontar.')); return; }
    recarregar();
  }

  async function lerQr(texto: string) {
    // Formato do QR é da thread 05 (etiqueta): os dois últimos segmentos são o tipo e o id da OS.
    const partes = texto.trim().split(':');
    const [osTipo, osId] = partes.length >= 2 ? partes.slice(-2) : ['comvis_os', partes[0]];
    const r = await enviar(operador.id, { url: '/producao/apontamento/qr', body: { os_tipo: osTipo, os_id: Number(osId) } });
    if (r.ok === 'fila') { setAviso('Sem internet: leia o QR de novo quando a rede voltar.'); return; }
    if (!r.ok) { setAviso(String(r.json.mensagem ?? 'QR não reconhecido.')); return; }
    const etapa = r.json.etapa as Etapa;
    if (!r.json.pode_apontar) {
      // E04 A2: etapa de outra pessoa — só informa, não grava nada.
      setAviso(`Está em ${etapa.posto ?? 'outra etapa'}, com ${String(r.json.responsavel ?? 'outro responsável')}.`);
      return;
    }
    setAviso(null);
    apontar('/producao/apontamento/iniciar', { programacao_id: etapa.id });
  }

  async function lerCamera() {
    const Detector = (window as unknown as { BarcodeDetector?: new (o: object) => { detect: (v: HTMLVideoElement) => Promise<Array<{ rawValue: string }>> } }).BarcodeDetector;
    if (!Detector || !navigator.mediaDevices) { setAviso('Este navegador não lê QR pela câmera. Digite o código da etiqueta.'); return; }
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    const video = document.createElement('video');
    video.srcObject = stream; await video.play();
    const detector = new Detector({ formats: ['qr_code'] });
    for (let i = 0; i < 40; i++) {
      const achados = await detector.detect(video).catch(() => []);
      if (achados[0]) { stream.getTracks().forEach((t) => t.stop()); lerQr(achados[0].rawValue); return; }
      await new Promise((ok) => setTimeout(ok, 250));
    }
    stream.getTracks().forEach((t) => t.stop());
    setAviso('Não encontrei o QR. Tente de novo ou digite o código.');
  }

  return (
    <AppShellV2 title="Apontamento · Produção" breadcrumbItems={[{ label: 'Produção' }, { label: 'Apontamento' }]}>
      <div className="pcv-root">
        <div className="pcv-ph"><PageHeader title="Apontamento" subtitle={operador.nome} /></div>
        <div className="pcv-body">
          {naFila > 0 && <Alert><AlertTitle>{naFila} apontamento(s) guardado(s) no celular</AlertTitle><AlertDescription>Envia sozinho quando a internet voltar.</AlertDescription></Alert>}

          <section aria-label="Rodando agora" className="pcv-ap">
            <div><span className="pcv-eyebrow">{operador.nome}</span><b className="pcv-ap-t">Rodando agora</b></div>
            <Deferred data="rodando" fallback={<Skeleton className="h-24 w-full" />}>
              <RodandoAgora renderLinha={(a) => <Linha key={a.id} a={a} motivos={motivos} apontar={apontar} />} />
            </Deferred>
          </section>

          <section aria-label="Ler QR" className="pcv-scan">
            <Button className="min-h-11" onClick={lerCamera}>Ler QR da OS</Button>
            <form className="pcv-bar" onSubmit={(e) => { e.preventDefault(); if (codigo) lerQr(codigo); }}>
              <Input aria-label="Código da etiqueta" placeholder="ou digite o código" value={codigo} onChange={(e) => setCodigo(e.target.value)} className="min-h-11" />
              <Button type="submit" variant="outline" className="min-h-11">Abrir</Button>
            </form>
            {aviso && <p className="pcv-help" role="status">{aviso}</p>}
          </section>

          <section aria-label="Minhas etapas de hoje" className="pcv-ap">
            <div><b className="pcv-ap-t">Minhas etapas de hoje</b><small>na ordem da programação</small></div>
            <Deferred data="etapas" fallback={<Skeleton className="h-32 w-full" />}>
              <MinhasEtapas onIniciar={(id) => apontar('/producao/apontamento/iniciar', { programacao_id: id })} />
            </Deferred>
          </section>
        </div>
      </div>
    </AppShellV2>
  );
}

// US-COMVIS-004: tinta gasta por canal, em ml. Rótulo curto no celular; o nome inteiro vai no aria-label.
const CANAIS = [['c', 'C', 'ciano'], ['m', 'M', 'magenta'], ['y', 'Y', 'amarelo'], ['k', 'K', 'preto']] as const;
const decimal = (v: string) => v.trim().replace(',', '.'); // o serviço só aceita ponto

function Linha({ a, motivos: todos, apontar }: { a: Aberto; motivos: Array<{ id: number; posto_id: number; nome: string }>; apontar: (url: string, corpo?: Record<string, unknown>) => void }) {
  const [motivo, setMotivo] = useState('');
  const [m2, setM2] = useState('');
  const [tinta, setTinta] = useState<Record<string, string>>({});
  const motivos = todos.filter((m) => m.posto_id === a.posto_id); // E04 A4: só os do posto
  const maquina = a.tipo_custo === 'maquina';

  function concluir() {
    // Campos vazios não vão: no banco, NULL é "não informado", diferente de zero.
    const corpo: Record<string, string> = {};
    if (maquina && m2.trim()) corpo.m2_impresso = decimal(m2);
    if (maquina) CANAIS.forEach(([k]) => { if (tinta[k]?.trim()) corpo[`tinta_ml_${k}`] = decimal(tinta[k]); });
    apontar(`/producao/apontamento/${a.id}/concluir`, corpo);
  }

  return (
    <div className="pcv-ap">
      <div><span className="pcv-eyebrow">{maquina ? 'Máquina' : 'Você'} · OS {a.os_id}</span><b className="pcv-ap-t">{a.posto ?? 'Etapa'}</b></div>
      <Cronometro desde={a.desde} />
      {maquina && (
        <fieldset className="grid gap-2">
          <legend className="pcv-help">Produção da etapa (opcional, vai junto ao concluir)</legend>
          <label htmlFor={`ap-m2-${a.id}`} className="grid gap-1 text-sm">
            m² impresso
            <Input id={`ap-m2-${a.id}`} inputMode="decimal" placeholder="ex.: 12,5" value={m2} onChange={(e) => setM2(e.target.value)} className="min-h-11" />
          </label>
          <span className="text-sm">Tinta (ml)</span>
          <div className="grid grid-cols-4 gap-2">
            {CANAIS.map(([k, rotulo, nome]) => (
              <label key={k} className="grid gap-1 text-sm">
                {rotulo}
                <Input inputMode="decimal" aria-label={`Tinta ${nome} em ml`} value={tinta[k] ?? ''}
                  onChange={(e) => setTinta((t) => ({ ...t, [k]: e.target.value }))} className="min-h-11" />
              </label>
            ))}
          </div>
        </fieldset>
      )}
      <div className="pcv-bar">
        <Select value={motivo || undefined} onValueChange={setMotivo} disabled={!motivos.length}>
          <SelectTrigger aria-label="Motivo da pausa" className="min-h-11 min-w-48">
            <SelectValue placeholder={motivos.length ? 'Motivo da pausa' : 'Sem motivos cadastrados no posto'} />
          </SelectTrigger>
          <SelectContent>
            {/* Valor vazio quebra o Radix Select (§5 2026-06-29): só motivo com id. */}
            {motivos.filter((m) => m.id).map((m) => <SelectItem key={m.id} value={String(m.id)}>{m.nome}</SelectItem>)}
          </SelectContent>
        </Select>
        <Button className="min-h-11" variant="outline" disabled={!motivo} onClick={() => apontar(`/producao/apontamento/${a.id}/pausar`, { motivo_id: Number(motivo) })}>Pausar</Button>
        <Button className="min-h-11" onClick={concluir}>Concluir etapa</Button>
      </div>
    </div>
  );
}

function RodandoAgora({ renderLinha }: { renderLinha: (a: Aberto) => React.ReactNode }) {
  const r = (usePage().props as unknown as Props).rodando;
  const abertos = r ? [r.pessoa, ...r.maquinas].filter((a): a is Aberto => !!a) : [];
  return (
    <>
      {r?.ponto && <p className="pcv-help">Ponto: entrada {r.ponto.entrada ?? '—'}{r.ponto.saida ? ` · saída ${r.ponto.saida}` : ' · presente'}</p>}
      {abertos.length ? abertos.map(renderLinha) : <p className="pcv-vazio">Nada rodando. Leia o QR da OS ou escolha uma etapa abaixo.</p>}
    </>
  );
}

function MinhasEtapas({ onIniciar }: { onIniciar: (id: number) => void }) {
  const etapas = (usePage().props as unknown as Props).etapas ?? [];
  if (!etapas.length) return <p className="pcv-vazio">Nenhuma etapa sua hoje. Quando o PCP programar, ela aparece aqui.</p>;
  return (
    <ul className="pcv-minhas">
      {etapas.map((e) => (
        <li key={e.id}>
          <button type="button" onClick={() => onIniciar(e.id)} aria-label={`OS ${e.os_id} · ${e.posto ?? ''} · ${e.status === 'iniciada' ? 'retomar' : 'iniciar'}`}>
            <b className="mono">OS {e.os_id}</b><span>{e.posto ?? '—'}</span><small>{e.inicio_previsto} · {e.status === 'iniciada' ? 'em andamento' : 'a iniciar'}</small>
          </button>
        </li>
      ))}
    </ul>
  );
}
