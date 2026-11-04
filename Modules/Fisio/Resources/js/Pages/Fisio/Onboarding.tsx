// @memcofre
//   tela: /fisio/onboarding
//   module: Fisio
//   stories: US-FISIO-050 (UC-FOB-01..06)
//   permissao: fisio.access (ver) · fisio.editar (gravar)
//
// Primeira configuração da clínica (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/Onboarding.dc.html).
// Cinco passos; cada "Continuar" grava só o passo atual no servidor. Nada aqui é obrigatório para usar o módulo.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/Lib/utils';
import { AVISO_QR } from './_components/avisoQr';

type Canal = 'proprio' | 'oficial' | 'qr';

interface Props {
  perfil: { nome_exibicao: string; crefito: string };
  atendimento: 'pf' | 'pj' | null;
  canal: Canal | null;
  aceite_qr: boolean;
  protocolos: { id: number; nome: string }[];
  favoritos: number[];
  concluido: boolean;
  pode_editar: boolean;
}

const PASSOS = [
  { k: 'perfil', t: 'Seu perfil profissional', s: 'É o que o paciente vê no link e no lembrete.' },
  { k: 'atendimento', t: 'Como você atende?', s: 'Decide o documento fiscal de cada pagamento.' },
  { k: 'canal', t: 'Como o link chega ao paciente', s: 'Dá para começar hoje e trocar depois.' },
  { k: 'protocolos', t: 'Seus protocolos', s: 'Escolha os que você mais usa.' },
  { k: 'concluir', t: 'Seu primeiro paciente', s: 'O jeito mais rápido de ver o Fisio funcionando.' },
] as const;

const FORMAS = [
  { k: 'pf', t: 'Autônomo (CPF)', d: 'Recibo de pagamento como pessoa física.' },
  { k: 'pj', t: 'Clínica (CNPJ)', d: 'Nota fiscal de serviço (NFS-e) da clínica.' },
] as const;

const CANAIS: { k: Canal; t: string; d: string; tag: string; tom: 'neutral' | 'success' | 'warning' }[] = [
  { k: 'proprio', t: 'Pelo meu WhatsApp', d: 'O Fisio monta a mensagem e abre o seu WhatsApp. Sem lembrete automático. Funciona hoje.', tag: 'grátis', tom: 'neutral' },
  { k: 'oficial', t: 'WhatsApp oficial da clínica (API da Meta)', d: 'Lembrete automático todo dia. Sem risco de bloqueio. A verificação da Meta leva alguns dias.', tag: 'recomendado', tom: 'success' },
  { k: 'qr', t: 'Número da clínica conectado por QR', d: 'Lembrete automático pelo número que a clínica já usa, sem verificação da Meta. Conexão não oficial.', tag: 'risco de bloqueio', tom: 'warning' },
];

function Escolha({ on, onClick, children, disabled }: { on: boolean; onClick: () => void; children: ReactNode; disabled: boolean }) {
  return (
    <button type="button" role="radio" aria-checked={on} onClick={onClick} disabled={disabled}
      className={cn('flex flex-col gap-1 rounded-lg border bg-card p-3 text-left', on ? 'border-2 border-primary bg-primary/5' : 'border-border')}>
      {children}
    </button>
  );
}

export default function FisioOnboarding(props: Props) {
  const [n, setN] = useState(0);
  const passo = PASSOS[n] ?? PASSOS[0];
  const { data, setData, put, processing, errors } = useForm({
    nome_exibicao: props.perfil.nome_exibicao,
    crefito: props.perfil.crefito,
    atendimento: props.atendimento ?? 'pf',
    canal: (props.canal ?? 'proprio') as Canal,
    aceite_qr: props.aceite_qr,
    favoritos: props.favoritos,
  });

  const continuar = (e: FormEvent) => {
    e.preventDefault();
    put(`/fisio/onboarding/${passo.k}`, { preserveScroll: true, preserveState: true, onSuccess: () => setN((x) => Math.min(PASSOS.length - 1, x + 1)) });
  };
  const ro = !props.pode_editar;

  return (
    <div data-screen-label="Fisio · Primeira configuração" className="mx-auto flex max-w-5xl flex-wrap items-start justify-center gap-6 p-4">
      <nav aria-label="Passos" className="flex w-full max-w-xs flex-col gap-1 rounded-lg border border-border bg-card p-4">
        <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Primeiro paciente no primeiro dia</span>
        <span className="text-sm text-muted-foreground">Leva uns 10 minutos.</span>
        {PASSOS.map((p, i) => (
          <button key={p.k} type="button" onClick={() => setN(i)} aria-current={i === n ? 'step' : undefined}
            className={cn('flex items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm', i === n && 'bg-primary/10 font-semibold')}>
            <span className="font-mono text-xs">{i < n ? '✓' : i + 1}</span>{p.t}
          </button>
        ))}
      </nav>

      <form onSubmit={continuar} className="w-full max-w-xl" data-contract="fisio-onboarding-passo">
        <Card>
          <CardContent className="flex flex-col gap-4 p-6">
            <span className="font-mono text-xs text-muted-foreground">passo {n + 1} de 5</span>
            <h1 className="text-xl font-semibold">{passo.t}</h1>
            <span className="text-sm text-muted-foreground">{passo.s}</span>

            {passo.k === 'perfil' && (
              <div className="grid gap-3">
                <Label htmlFor="ob-n">Nome como o paciente vê</Label>
                <Input id="ob-n" value={data.nome_exibicao} onChange={(e) => setData('nome_exibicao', e.target.value)} disabled={ro} />
                {errors.nome_exibicao && <p className="text-xs text-destructive">{errors.nome_exibicao}</p>}
                <Label htmlFor="ob-c">CREFITO</Label>
                <Input id="ob-c" value={data.crefito} onChange={(e) => setData('crefito', e.target.value)} disabled={ro} />
              </div>
            )}

            {passo.k === 'atendimento' && (
              <div role="radiogroup" aria-label="Forma de atendimento" className="grid gap-2 sm:grid-cols-2">
                {FORMAS.map((f) => (
                  <Escolha key={f.k} on={data.atendimento === f.k} onClick={() => setData('atendimento', f.k)} disabled={ro}>
                    <b>{f.t}</b><span className="text-sm text-muted-foreground">{f.d}</span>
                  </Escolha>
                ))}
              </div>
            )}

            {passo.k === 'canal' && (
              <div role="radiogroup" aria-label="Canal do WhatsApp" className="grid gap-2">
                {CANAIS.map((c) => (
                  <Escolha key={c.k} on={data.canal === c.k} onClick={() => setData('canal', c.k)} disabled={ro}>
                    <span className="flex flex-wrap items-center gap-2"><b>{c.t}</b><Badge variant={c.tom}>{c.tag}</Badge></span>
                    <span className="text-sm text-muted-foreground">{c.d}</span>
                  </Escolha>
                ))}
                {data.canal === 'qr' && !props.aceite_qr && (
                  <label className="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning-soft p-3 text-sm">
                    <Checkbox className="mt-1" checked={data.aceite_qr} onCheckedChange={(v) => setData('aceite_qr', v === true)} />
                    <span>{AVISO_QR}</span>
                  </label>
                )}
                {errors.aceite_qr && <p className="text-sm text-destructive" role="alert">{errors.aceite_qr}</p>}
                <span className="text-sm text-muted-foreground">Dá para trocar de canal depois em <Link href="/fisio/configuracoes/whatsapp" className="text-primary hover:underline">Configurações › WhatsApp</Link>, sem perder pacientes.</span>
              </div>
            )}

            {passo.k === 'protocolos' && (
              <div className="grid gap-2 sm:grid-cols-2">
                {props.protocolos.map((p) => (
                  <label key={p.id} className="flex items-center gap-2 text-sm">
                    <Checkbox checked={data.favoritos.includes(p.id)} disabled={ro}
                      onCheckedChange={(v) => setData('favoritos', v === true ? [...data.favoritos, p.id] : data.favoritos.filter((x) => x !== p.id))} />
                    {p.nome}
                  </label>
                ))}
                <span className="text-sm text-muted-foreground sm:col-span-2">Os {props.protocolos.length} protocolos continuam disponíveis. Os marcados ficam guardados como os seus.</span>
              </div>
            )}

            {passo.k === 'concluir' && (
              <div className="flex flex-col gap-3">
                <span>Cadastre alguém que você atende esta semana e mande o link agora.</span>
                {props.concluido && <Badge variant="success" className="w-fit">Configuração concluída</Badge>}
                <Button asChild variant="outline" className="w-fit"><Link href="/fisio/pacientes">Cadastrar meu primeiro paciente</Link></Button>
              </div>
            )}

            <div className="flex justify-between gap-2">
              <Button type="button" variant="outline" onClick={() => setN((x) => Math.max(0, x - 1))} disabled={n === 0}>Voltar</Button>
              {props.pode_editar && <Button type="submit" disabled={processing}>{n === PASSOS.length - 1 ? 'Concluir' : 'Continuar'}</Button>}
            </div>
          </CardContent>
        </Card>
      </form>
    </div>
  );
}

FisioOnboarding.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
