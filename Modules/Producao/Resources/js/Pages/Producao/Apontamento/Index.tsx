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
import EmptyState from '@/Components/shared/EmptyState';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack } from '@/Components/layout';
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
  return <span role="timer" aria-live="off" aria-label={`Tempo apontado ${hms(seg)}`} className="font-mono text-2xl tabular-nums">{hms(seg)}</span>;
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
      <PageHeader title="Apontamento" subtitle={operador.nome} />
      <Stack gap={4} className="mx-auto w-full max-w-md p-4">
        {naFila > 0 && <Alert><AlertTitle>{naFila} apontamento(s) guardado(s) no celular</AlertTitle><AlertDescription>Envia sozinho quando a internet voltar.</AlertDescription></Alert>}

        <section aria-label="Rodando agora" className="space-y-2">
          <h2 className="text-sm font-semibold">Rodando agora</h2>
          <Deferred data="rodando" fallback={<Skeleton className="h-24 w-full" />}>
            <RodandoAgora renderLinha={(a) => <Linha key={a.id} a={a} motivos={motivos} apontar={apontar} />} />
          </Deferred>
        </section>

        <section aria-label="Ler QR" className="space-y-2">
          <Button className="min-h-11 w-full" onClick={lerCamera}>Ler QR da OS</Button>
          <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); if (codigo) lerQr(codigo); }}>
            <Input aria-label="Código da etiqueta" placeholder="ou digite o código" value={codigo} onChange={(e) => setCodigo(e.target.value)} className="min-h-11" />
            <Button type="submit" variant="outline" className="min-h-11">Abrir</Button>
          </form>
          {aviso && <Alert><AlertDescription>{aviso}</AlertDescription></Alert>}
        </section>

        <section aria-label="Minhas etapas de hoje" className="space-y-2">
          <h2 className="text-sm font-semibold">Minhas etapas de hoje <span className="font-normal text-muted-foreground">· na ordem da programação</span></h2>
          <Deferred data="etapas" fallback={<Skeleton className="h-32 w-full" />}>
            <MinhasEtapas onIniciar={(id) => apontar('/producao/apontamento/iniciar', { programacao_id: id })} />
          </Deferred>
        </section>
      </Stack>
    </AppShellV2>
  );
}

function Linha({ a, motivos: todos, apontar }: { a: Aberto; motivos: Array<{ id: number; posto_id: number; nome: string }>; apontar: (url: string, corpo?: Record<string, unknown>) => void }) {
  const [motivo, setMotivo] = useState('');
  const motivos = todos.filter((m) => m.posto_id === a.posto_id); // E04 A4: só os do posto
  const maquina = a.tipo_custo === 'maquina';
  return (
    <Card><CardContent className="space-y-3 p-4">
      <div className="flex items-baseline justify-between gap-2">
        <div><b>{a.posto ?? 'Etapa'}</b><div className="text-xs text-muted-foreground">{maquina ? 'máquina' : 'você'} · OS {a.os_id}</div></div>
        <Cronometro desde={a.desde} />
      </div>
      <div className="flex flex-wrap gap-2">
        <select aria-label="Motivo da pausa" value={motivo} onChange={(e) => setMotivo(e.target.value)} className="min-h-11 rounded-md border border-input bg-background px-3 text-sm">
          <option value="">{motivos.length ? 'Motivo da pausa' : 'Sem motivos cadastrados no posto'}</option>
          {motivos.filter((m) => m.id).map((m) => <option key={m.id} value={m.id}>{m.nome}</option>)}
        </select>
        <Button className="min-h-11" variant="outline" disabled={!motivo} onClick={() => apontar(`/producao/apontamento/${a.id}/pausar`, { motivo_id: Number(motivo) })}>Pausar</Button>
        <Button className="min-h-11" onClick={() => apontar(`/producao/apontamento/${a.id}/concluir`)}>Concluir etapa</Button>
      </div>
    </CardContent></Card>
  );
}

function RodandoAgora({ renderLinha }: { renderLinha: (a: Aberto) => React.ReactNode }) {
  const r = (usePage().props as unknown as Props).rodando;
  const abertos = r ? [r.pessoa, ...r.maquinas].filter((a): a is Aberto => !!a) : [];
  return (
    <Stack gap={2}>
      {r?.ponto && <p className="text-xs text-muted-foreground">Ponto: entrada {r.ponto.entrada ?? '—'}{r.ponto.saida ? ` · saída ${r.ponto.saida}` : ' · presente'}</p>}
      {abertos.length ? abertos.map(renderLinha) : <EmptyState title="Nada rodando" description="Leia o QR da OS ou escolha uma etapa abaixo." />}
    </Stack>
  );
}

function MinhasEtapas({ onIniciar }: { onIniciar: (id: number) => void }) {
  const etapas = (usePage().props as unknown as Props).etapas ?? [];
  if (!etapas.length) return <EmptyState title="Nenhuma etapa sua hoje" description="Quando o PCP programar, ela aparece aqui." />;
  return (
    <ul className="space-y-2">
      {etapas.map((e) => (
        <li key={e.id}>
          <button type="button" onClick={() => onIniciar(e.id)} className="flex min-h-11 w-full items-center justify-between rounded-md border border-border bg-card px-3 py-2 text-left">
            <span><b className="font-mono">OS {e.os_id}</b><span className="block text-xs text-muted-foreground">{e.posto ?? '—'} · {e.inicio_previsto}</span></span>
            <span className="text-sm">{e.status === 'iniciada' ? 'Retomar' : 'Iniciar'}</span>
          </button>
        </li>
      ))}
    </ul>
  );
}
