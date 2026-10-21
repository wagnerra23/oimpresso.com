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
import { quantidadeBr as br, quantidadeParaEnvio } from './_lib/quantidade';

interface Etapa { id: number; os_tipo: string; os_id: number; posto: string | null; tipo_custo: string | null; inicio_previsto: string; status: string }
interface Aberto { id: number; posto_id: number; programacao_id: number | null; os_tipo: string | null; os_id: number | null; posto: string | null; tipo_custo: string; desde: string }
interface Rodando { pessoa: Aberto | null; maquinas: Aberto[]; ponto: { entrada: string | null; saida: string | null } | null }
interface Lote { id: number; lote: string | null; saldo: string; recebido_em: string }
interface Material { variation_id: number; nome: string; unidade: string | null; previsto: string | null; apontado: string; lotes: Lote[] }
interface Props { operador: { id: number; nome: string }; etapas?: Etapa[]; rodando?: Rodando; motivos?: Array<{ id: number; posto_id: number; nome: string }>; materiais?: Record<number, Material[]> }

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

export default function ApontamentoIndex({ operador, motivos = [], materiais = {} }: Props) {
  // `motivos` é deferido junto com `rodando`; antes de chegar, a pausa fica desabilitada.
  const [codigo, setCodigo] = useState('');
  const [aviso, setAviso] = useState<string | null>(null);
  const [naFila, setNaFila] = useState(() => pendentes(operador.id));
  const recarregar = useCallback(() => router.reload({ only: ['etapas', 'rodando', 'materiais'] }), []);

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
              <RodandoAgora renderLinha={(a) => <Linha key={a.id} a={a} motivos={motivos} materiais={a.programacao_id ? materiais[a.programacao_id] ?? [] : []} apontar={apontar} />} />
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

function Linha({ a, motivos: todos, materiais, apontar }: { a: Aberto; motivos: Array<{ id: number; posto_id: number; nome: string }>; materiais: Material[]; apontar: (url: string, corpo?: Record<string, unknown>) => void }) {
  const [motivo, setMotivo] = useState('');
  const [abrirMaterial, setAbrirMaterial] = useState(false);
  const motivos = todos.filter((m) => m.posto_id === a.posto_id); // E04 A4: só os do posto
  const maquina = a.tipo_custo === 'maquina';
  return (
    <div className="pcv-ap">
      <div><span className="pcv-eyebrow">{maquina ? 'Máquina' : 'Você'} · OS {a.os_id}</span><b className="pcv-ap-t">{a.posto ?? 'Etapa'}</b></div>
      <Cronometro desde={a.desde} />
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
        <Button className="min-h-11" onClick={() => apontar(`/producao/apontamento/${a.id}/concluir`)}>Concluir etapa</Button>
        {a.programacao_id && <Button className="min-h-11" variant="outline" aria-expanded={abrirMaterial} onClick={() => setAbrirMaterial((v) => !v)}>Material</Button>}
      </div>
      {abrirMaterial && a.programacao_id && (
        <FormMaterial materiais={materiais} onEnviar={(corpo) => { apontar('/producao/apontamento/material', { programacao_id: a.programacao_id, ...corpo }); setAbrirMaterial(false); }} />
      )}
    </div>
  );
}

// US-PCP-021 · UC-FLX-18: material consumido na etapa, com o rolo (lote) de onde saiu. Sem rolo, a baixa
// segue o método da empresa. Quem confere material, rolo e saldo é o servidor; a tela só oferece o que ele aceitaria.
function FormMaterial({ materiais, onEnviar }: { materiais: Material[]; onEnviar: (corpo: Record<string, unknown>) => void }) {
  const [vid, setVid] = useState('');
  const [qtd, setQtd] = useState('');
  const [lote, setLote] = useState('auto');
  const material = materiais.find((m) => String(m.variation_id) === vid);
  const quantidade = quantidadeParaEnvio(qtd);
  if (!materiais.length) return <p className="pcv-help">Nenhum material previsto para esta OS.</p>;
  return (
    <form className="pcv-bar" aria-label="Apontar material"
      onSubmit={(e) => { e.preventDefault(); if (!material || !quantidade) return; onEnviar({ variation_id: material.variation_id, quantidade, ...(lote !== 'auto' ? { lote_id: Number(lote) } : {}) }); }}>
      <Select value={vid || undefined} onValueChange={(v) => { setVid(v); setLote('auto'); }}>
        <SelectTrigger aria-label="Material" className="min-h-11 min-w-48"><SelectValue placeholder="Material" /></SelectTrigger>
        <SelectContent>
          {materiais.filter((m) => m.variation_id).map((m) => (
            <SelectItem key={m.variation_id} value={String(m.variation_id)}>{m.nome}{m.previsto ? ` · previsto ${br(m.previsto)}` : ''}</SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Input aria-label="Quantidade" inputMode="decimal" placeholder={`Quantidade${material?.unidade ? ` (${material.unidade})` : ''}`} value={qtd}
        onChange={(e) => setQtd(e.target.value)} className="min-h-11 w-32" />
      <Select value={lote} onValueChange={setLote} disabled={!material}>
        <SelectTrigger aria-label="Rolo" className="min-h-11 min-w-48"><SelectValue placeholder="Rolo" /></SelectTrigger>
        <SelectContent>
          <SelectItem value="auto">Rolo mais antigo (automático)</SelectItem>
          {(material?.lotes ?? []).filter((l) => l.id).map((l) => (
            <SelectItem key={l.id} value={String(l.id)}>{l.lote ?? `Rolo ${l.id}`} · saldo {br(l.saldo)} · {l.recebido_em.split('-').reverse().join('/')}</SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Button type="submit" className="min-h-11" disabled={!material || !quantidade}>{quantidade && material ? `Baixar ${br(quantidade)}${material.unidade ? ` ${material.unidade}` : ''}` : 'Baixar'}</Button>
    </form>
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
