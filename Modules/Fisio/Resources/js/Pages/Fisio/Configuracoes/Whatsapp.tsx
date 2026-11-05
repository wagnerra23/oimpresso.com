// @memcofre
//   tela: /fisio/configuracoes/whatsapp
//   module: Fisio
//   stories: US-FISIO-009 (UC-FCW-01..06)
//   permissao: fisio.access (ver) · fisio.editar (salvar)
//
// Configurações › WhatsApp (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/ConfigWhatsApp.dc.html).
// A clínica escolhe POR ONDE o Fisio manda link, programa e lembrete. Número, driver e conexão por QR
// são do módulo Whatsapp: aqui só aparece o estado do número e o link para lá.
import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { cn } from '@/Lib/utils';
import { AVISO_QR } from '../_components/avisoQr';

type Canal = 'proprio' | 'oficial' | 'qr';

interface Props {
  canal: Canal | null;
  aceite_qr: boolean;
  teto_por_hora: number;
  silencio: { inicio: string; fim: string };
  lembrete_horario: string;
  numero: { final: string; oficial: boolean; saude: string } | null;
  ativo_agora: boolean;
  url_canais: string | null;
  pode_editar: boolean;
  limites: { teto_min: number; teto_max: number };
}

const OPCOES: { k: Canal; t: string; d: string; tag: string; tom: 'pos' | 'warn' | 'neutro'; fatos: string[] }[] = [
  { k: 'oficial', t: 'WhatsApp oficial (API da Meta)', d: 'Número verificado pela Meta, com modelos aprovados.', tag: 'recomendado', tom: 'pos', fatos: ['Lembrete automático', 'Sem risco de bloqueio', 'Custo por mensagem'] },
  { k: 'qr', t: 'Número da clínica por QR', d: 'Usa o número que a clínica já tem, conectado como aparelho.', tag: 'risco de bloqueio', tom: 'warn', fatos: ['Lembrete automático', 'Sem custo por mensagem', 'Conexão não oficial'] },
  { k: 'proprio', t: 'Pelo meu WhatsApp', d: 'Abre o seu WhatsApp com a mensagem pronta.', tag: 'grátis', tom: 'neutro', fatos: ['Envio manual', 'Sem lembrete automático'] },
];

const TOM = { pos: 'success', warn: 'warning', neutro: 'neutral' } as const;

function EstadoNumero({ numero, canal, url }: { numero: Props['numero']; canal: Canal; url: string | null }) {
  if (canal === 'proprio') {
    return <p className="text-sm text-muted-foreground">Ao enviar o link ou o programa, o Fisio abre o seu WhatsApp com a mensagem pronta. Você toca em enviar. Sem lembrete diário automático.</p>;
  }
  const certo = numero !== null && numero.oficial === (canal === 'oficial');
  return (
    <div className="flex flex-col gap-2 text-sm" data-contract="fisio-config-wa-numero">
      {numero === null ? (
        <p>Nenhum número conectado no WhatsApp do sistema. Enquanto isso, o link sai pelo seu WhatsApp.</p>
      ) : certo ? (
        <p>Número final <span className="font-mono">{numero.final || '—'}</span> · {numero.saude === 'healthy' ? 'conectado' : numero.saude}</p>
      ) : (
        <p>O número conectado no WhatsApp do sistema (final <span className="font-mono">{numero.final || '—'}</span>) é {numero.oficial ? 'oficial' : 'conectado por QR'}, não {canal === 'oficial' ? 'oficial' : 'por QR'}. Enquanto não bater, o link sai pelo seu WhatsApp.</p>
      )}
      {url && <a href={url} className="w-fit text-primary hover:underline">Conectar ou trocar o número no WhatsApp do sistema</a>}
    </div>
  );
}

export default function FisioConfigWhatsapp(props: Props) {
  const { data, setData, put, processing, errors } = useForm({
    canal: (props.canal ?? 'proprio') as Canal,
    aceite_qr: props.aceite_qr,
    teto_por_hora: props.teto_por_hora,
    silencio: props.silencio,
  });
  const enviar = (e: FormEvent) => {
    e.preventDefault();
    put('/fisio/configuracoes/whatsapp', { preserveScroll: true });
  };

  return (
    <form onSubmit={enviar} data-screen-label="Fisio · Configurações · WhatsApp" className="mx-auto flex max-w-4xl flex-col gap-4 p-4">
      <PageHeader title="WhatsApp" subtitle="Escolha por onde saem o link e o lembrete diário. Dá para trocar a qualquer momento, sem perder pacientes." />

      <div role="radiogroup" aria-label="Canal" className="grid gap-3 md:grid-cols-3" data-contract="fisio-config-wa-canais">
        {OPCOES.map((o) => {
          const on = data.canal === o.k;
          return (
            <button key={o.k} type="button" role="radio" aria-checked={on} disabled={!props.pode_editar} onClick={() => setData('canal', o.k)}
              className={cn('flex flex-col gap-1 rounded-lg border bg-card p-4 text-left', on ? 'border-2 border-primary bg-primary/5' : 'border-border')}>
              <span className="flex flex-wrap items-center gap-2"><b>{o.t}</b><Badge variant={TOM[o.tom]}>{o.tag}</Badge></span>
              <span className="text-sm text-muted-foreground">{o.d}</span>
              <span className="text-xs text-muted-foreground">{o.fatos.map((f) => `· ${f}`).join('  ')}</span>
            </button>
          );
        })}
      </div>

      <Card>
        <CardContent className="flex flex-col gap-3 p-4">
          <EstadoNumero numero={props.numero} canal={data.canal} url={props.url_canais} />
          {data.canal === 'qr' && !props.aceite_qr && (
            <label className="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning-soft p-3 text-sm" data-contract="fisio-config-wa-aceite">
              <Checkbox className="mt-1" checked={data.aceite_qr} onCheckedChange={(v) => setData('aceite_qr', v === true)} />
              <span>{AVISO_QR}</span>
            </label>
          )}
          {data.canal === 'qr' && props.aceite_qr && <Badge variant="outline" className="w-fit">Risco do QR aceito</Badge>}
          {errors.aceite_qr && <p className="text-sm text-destructive" role="alert">{errors.aceite_qr}</p>}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="grid gap-3 p-4 md:grid-cols-3">
          <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground md:col-span-3">Vale para qualquer canal</h2>
          <div className="flex flex-col gap-1">
            <Label htmlFor="wa-teto">Máximo por hora</Label>
            <Input id="wa-teto" type="number" min={props.limites.teto_min} max={props.limites.teto_max} value={data.teto_por_hora}
              onChange={(e) => setData('teto_por_hora', Number(e.target.value))} disabled={!props.pode_editar} />
            {errors.teto_por_hora && <p className="text-xs text-destructive">{errors.teto_por_hora}</p>}
          </div>
          <div className="flex flex-col gap-1">
            <Label htmlFor="wa-sil-i">Não mandar mensagem entre</Label>
            <div className="flex items-center gap-2">
              <Input id="wa-sil-i" type="time" value={data.silencio.inicio} onChange={(e) => setData('silencio', { ...data.silencio, inicio: e.target.value })} disabled={!props.pode_editar} />
              <span>e</span>
              <Input aria-label="fim do silêncio" type="time" value={data.silencio.fim} onChange={(e) => setData('silencio', { ...data.silencio, fim: e.target.value })} disabled={!props.pode_editar} />
            </div>
          </div>
          <p className="text-sm text-muted-foreground">Lembrete diário às <span className="font-mono">{props.lembrete_horario}</span> (horário do sistema).</p>
          <p className="text-xs text-muted-foreground md:col-span-3">Só recebe mensagem quem autorizou lembretes no primeiro acesso.</p>
        </CardContent>
      </Card>

      {props.pode_editar && (
        <div className="flex justify-end">
          <Button type="submit" disabled={processing}>Salvar</Button>
        </div>
      )}
    </form>
  );
}

FisioConfigWhatsapp.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
