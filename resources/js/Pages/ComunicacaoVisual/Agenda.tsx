/**
 * Pages/ComunicacaoVisual/Agenda — agenda de instalação em semana × equipe (US-COMVIS-007 · UC-FLX-21).
 *
 * Âncora de design: o widget "Agenda de instalação" de prototipo-ui/cowork/Wagner/producao-cv-campo.jsx
 * (item = hora · OS · equipe · veículo · selo de estado). Aqui o mesmo item vira bloco numa grade: uma
 * linha por pessoa da equipe, uma coluna por dia da semana.
 *
 * A tela não decide conflito: agenda pelo POST da API (InstalacaoController@agendar), e quando a API
 * recusa por falta de janela (422 "Sem janela: …") a mensagem dela — com quem e quando — aparece no
 * alerta "Sem janela — não agendado".
 *
 * Persona: coordenador de instalação da gráfica, monitor 1280px. Tokens DS v4, sem cor crua.
 *
 * @see resources/js/Pages/ComunicacaoVisual/Agenda.charter.md
 * @see resources/js/Pages/ComunicacaoVisual/Agenda.casos.md
 * @see Modules/ComunicacaoVisual/Http/Controllers/AgendaController.php
 */
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { Deferred, Head, router } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, Truck } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Checkbox } from '@/Components/ui/checkbox';
import { Textarea } from '@/Components/ui/textarea';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Grid, Inline, Stack } from '@/Components/layout';

interface Pessoa { id: number; nome: string }
interface OsOpcao { id: number; numero: string | null; etapa: string }
export interface InstalacaoAgenda {
  id: number;
  os_id: number | null;
  os_numero: string | null;
  inicio: string; // ISO com o offset do servidor
  fim: string;
  equipe: { id: number; nome: string | null }[];
  veiculo: string | null;
  status: string;
}

interface Props {
  bizName?: string;
  semana: string; // segunda-feira, AAAA-MM-DD
  equipe: Pessoa[];
  os: OsOpcao[];
  instalacoes?: InstalacaoAgenda[];
  podeAgendar: boolean;
}

const DIAS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const ESTADO: Record<string, [string, 'info' | 'warning' | 'success' | 'neutral']> = {
  agendada: ['Agendada', 'info'],
  em_execucao: ['Em execução', 'warning'],
  aguardando_reagendamento: ['A reagendar', 'warning'],
  concluida: ['Concluída', 'success'],
};

/** Soma dias a uma data AAAA-MM-DD sem passar por fuso (meio-dia UTC não escorrega de dia). */
function somaDias(iso: string, n: number): string {
  const d = new Date(`${iso}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}
const ddmm = (iso: string) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}`;
// Data e hora saem do texto ISO do servidor, sem conversão para o fuso do navegador.
const diaDe = (isoHora: string) => isoHora.slice(0, 10);
const horaDe = (isoHora: string) => isoHora.slice(11, 16);

export default function Agenda({ bizName = 'oimpresso', semana, equipe, os, instalacoes, podeAgendar }: Props) {
  const dias = useMemo(() => DIAS.map((rotulo, i) => ({ rotulo, iso: somaDias(semana, i) })), [semana]);
  const domingo = somaDias(semana, 6);
  const hoje = new Date().toLocaleDateString('en-CA');

  const irPara = (novaSemana: string) =>
    router.get('/comunicacao-visual/agenda', { semana: novaSemana }, { preserveScroll: true });

  return (
    <>
      <Head title="Comunicação Visual — Agenda de instalação" />

      <div data-contract="cabecalho">
        <PageHeader
          leading={<CalendarDays className="h-5 w-5 text-primary" aria-hidden="true" />}
          title="Agenda de instalação"
          subtitle={`Equipe e veículo de campo de ${bizName}, semana a semana.`}
        />
      </div>

      <div className="space-y-4 p-6">
        <Inline wrap gap={2} data-contract="semana">
          <Button type="button" variant="outline" size="sm" onClick={() => irPara(somaDias(semana, -7))}>
            <ChevronLeft className="mr-1 h-4 w-4" aria-hidden="true" /> Semana anterior
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={() => irPara(hoje)}>
            Hoje
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={() => irPara(somaDias(semana, 7))}>
            Próxima semana <ChevronRight className="ml-1 h-4 w-4" aria-hidden="true" />
          </Button>
          <span className="ml-2 text-sm font-medium">
            {ddmm(semana)} a {ddmm(domingo)}/{domingo.slice(0, 4)}
          </span>
        </Inline>

        <Grid cols={1} gap={6} className="items-start xl:grid-cols-[minmax(0,1fr)_390px]">
          <Card data-contract="agenda">
            <CardHeader>
              <CardTitle>Semana × equipe</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto p-0">
              <Deferred data="instalacoes" fallback={<Skeleton className="m-4 h-48" />}>
                <GradeSemana dias={dias} equipe={equipe} instalacoes={instalacoes ?? []} hoje={hoje} />
              </Deferred>
            </CardContent>
          </Card>

          {podeAgendar ? (
            <FormAgendar equipe={equipe} os={os} diaPadrao={semana} />
          ) : (
            <Card data-contract="agendar">
              <CardContent className="pt-6 text-sm text-muted-foreground">
                Você vê a agenda, mas agendar instalação pede a permissão de atualizar a OS.
              </CardContent>
            </Card>
          )}
        </Grid>
      </div>
    </>
  );
}

export function GradeSemana({ dias, equipe, instalacoes, hoje }: {
  dias: { rotulo: string; iso: string }[];
  equipe: Pessoa[];
  instalacoes: InstalacaoAgenda[];
  hoje: string;
}) {
  if (equipe.length === 0) {
    return <p className="p-4 text-sm text-muted-foreground">Nenhuma pessoa cadastrada nesta empresa para montar a equipe.</p>;
  }

  const daPessoaNoDia = (pessoa: number, dia: string) =>
    instalacoes.filter((i) => diaDe(i.inicio) === dia && i.equipe.some((p) => p.id === pessoa));

  return (
    <table className="w-full min-w-[880px] border-collapse text-sm" aria-label="Agenda de instalação da semana por pessoa da equipe">
      <thead>
        <tr>
          <th scope="col" className="w-40 border-b border-border px-3 py-2 text-left text-xs font-semibold text-muted-foreground">
            Equipe
          </th>
          {dias.map((d) => (
            <th
              key={d.iso}
              scope="col"
              aria-current={d.iso === hoje ? 'date' : undefined}
              className="border-b border-l border-border px-2 py-2 text-left text-xs font-semibold text-muted-foreground aria-[current=date]:text-primary"
            >
              {d.rotulo} <span className="font-mono">{ddmm(d.iso)}</span>
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {equipe.map((pessoa) => (
          <tr key={pessoa.id}>
            <th scope="row" className="border-b border-border px-3 py-2 text-left align-top font-medium">
              {pessoa.nome || `Pessoa ${pessoa.id}`}
            </th>
            {dias.map((d) => (
              <td key={d.iso} className="border-b border-l border-border p-1.5 align-top" data-celula={`${pessoa.id}:${d.iso}`}>
                <Stack gap={1}>
                  {daPessoaNoDia(pessoa.id, d.iso).map((i) => (
                    <BlocoInstalacao key={i.id} instalacao={i} />
                  ))}
                </Stack>
              </td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function BlocoInstalacao({ instalacao: i }: { instalacao: InstalacaoAgenda }) {
  const [rotulo, tom] = ESTADO[i.status] ?? [i.status, 'neutral'];
  return (
    <Stack gap={0} className="rounded-md border border-border bg-muted/40 px-2 py-1.5" data-instalacao={i.id}>
      <span className="font-mono text-xs font-semibold">{horaDe(i.inicio)}–{horaDe(i.fim)}</span>
      <span className="text-xs">{i.os_numero ? `OS ${i.os_numero}` : `Instalação #${i.id}`}</span>
      {i.veiculo && (
        <Inline gap={1} className="text-xs text-muted-foreground">
          <Truck className="h-3 w-3" aria-hidden="true" /> {i.veiculo}
        </Inline>
      )}
      <Badge variant={tom} className="w-fit">{rotulo}</Badge>
    </Stack>
  );
}

export function FormAgendar({ equipe, os, diaPadrao, osInicial = '' }: {
  equipe: Pessoa[];
  os: OsOpcao[];
  diaPadrao: string;
  osInicial?: string;
}) {
  const [osId, setOsId] = useState(osInicial);
  const [dia, setDia] = useState(diaPadrao);
  const [inicio, setInicio] = useState('08:00');
  const [fim, setFim] = useState('10:00');
  const [escolhidos, setEscolhidos] = useState<number[]>([]);
  const [veiculo, setVeiculo] = useState('');
  const [observacoes, setObservacoes] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [semJanela, setSemJanela] = useState<string | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const alternar = (id: number, marcado: boolean) =>
    setEscolhidos((atual) => (marcado ? [...atual, id] : atual.filter((x) => x !== id)));

  async function agendar(e: FormEvent) {
    e.preventDefault();
    setSemJanela(null);
    setErro(null);
    setOk(null);
    if (!osId) return setErro('Escolha a OS.');
    if (escolhidos.length === 0) return setErro('Marque ao menos uma pessoa da equipe.');

    setEnviando(true);
    try {
      const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const res = await fetch(`/comunicacao-visual/api/os/${osId}/instalacao`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          inicio: `${dia} ${inicio}`,
          fim: `${dia} ${fim}`,
          equipe: escolhidos,
          veiculo: veiculo.trim() || null,
          observacoes: observacoes.trim() || null,
        }),
      });
      const data = await res.json().catch(() => null);
      if (res.status === 201) {
        setOk('Instalação agendada.');
        setEscolhidos([]);
        router.reload({ only: ['instalacoes'] });
        return;
      }
      const msg = (data && typeof data.message === 'string' && data.message) || `Não consegui agendar (erro ${res.status}).`;
      // A API devolve 422 também para dado inválido; o conflito de agenda é o que começa com "Sem janela".
      if (res.status === 422 && msg.startsWith('Sem janela')) setSemJanela(msg);
      else setErro(msg);
    } catch {
      setErro('Falha de conexão ao agendar. Tente de novo.');
    } finally {
      setEnviando(false);
    }
  }

  return (
    <Card data-contract="agendar">
      <CardHeader>
        <CardTitle>Agendar instalação</CardTitle>
      </CardHeader>
      <CardContent>
        <Stack asChild gap={3}>
        <form onSubmit={agendar}>
          {semJanela && (
            <Alert variant="destructive" role="alert" data-contract="bloqueio">
              <AlertTitle>Sem janela — não agendado</AlertTitle>
              <AlertDescription>{semJanela.replace(/^Sem janela:\s*/, '')}</AlertDescription>
            </Alert>
          )}
          {erro && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{erro}</AlertDescription>
            </Alert>
          )}
          {ok && (
            <Alert role="status">
              <AlertDescription>{ok}</AlertDescription>
            </Alert>
          )}

          <Campo id="ag-os" rotulo="OS">
            <Select value={osId} onValueChange={setOsId}>
              <SelectTrigger id="ag-os" className="w-full">
                <SelectValue placeholder={os.length ? 'Escolha a OS' : 'Nenhuma OS aberta'} />
              </SelectTrigger>
              <SelectContent>
                {os.map((o) => (
                  <SelectItem key={o.id} value={String(o.id)}>
                    {o.numero ? `OS ${o.numero}` : `OS #${o.id}`} · {o.etapa}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Campo>

          <Campo id="ag-dia" rotulo="Dia">
            <Input id="ag-dia" type="date" value={dia} onChange={(e) => setDia(e.target.value)} required />
          </Campo>
          <Grid cols={2} gap={2}>
            <Campo id="ag-inicio" rotulo="Início">
              <Input id="ag-inicio" type="time" value={inicio} onChange={(e) => setInicio(e.target.value)} required />
            </Campo>
            <Campo id="ag-fim" rotulo="Fim">
              <Input id="ag-fim" type="time" value={fim} onChange={(e) => setFim(e.target.value)} required />
            </Campo>
          </Grid>

          <Stack asChild gap={1}>
            <fieldset>
              <legend className="mb-1 text-sm font-medium">Equipe</legend>
              {equipe.map((p) => (
                <Inline asChild key={p.id} gap={2} className="min-h-9 text-sm">
                  <label>
                    <Checkbox checked={escolhidos.includes(p.id)} onCheckedChange={(v) => alternar(p.id, v === true)} />
                    {p.nome || `Pessoa ${p.id}`}
                  </label>
                </Inline>
              ))}
            </fieldset>
          </Stack>

          <Campo id="ag-veiculo" rotulo="Veículo">
            <Input id="ag-veiculo" value={veiculo} maxLength={60} placeholder="Placa ou apelido" onChange={(e) => setVeiculo(e.target.value)} />
          </Campo>
          <Campo id="ag-obs" rotulo="Observações">
            <Textarea id="ag-obs" value={observacoes} maxLength={1000} onChange={(e) => setObservacoes(e.target.value)} />
          </Campo>

          <p className="text-xs text-muted-foreground">
            A agenda recusa quando alguém da equipe ou o veículo já está em outra instalação no mesmo horário.
          </p>
          <Button type="submit" disabled={enviando}>
            {enviando ? 'Agendando…' : 'Agendar'}
          </Button>
        </form>
        </Stack>
      </CardContent>
    </Card>
  );
}

/** Rótulo em cima do controle, como nos outros formulários do DS. */
function Campo({ id, rotulo, children }: { id: string; rotulo: string; children: ReactNode }) {
  return (
    <Stack gap={1}>
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
    </Stack>
  );
}

Agenda.layout = (page: ReactNode) => (
  <AppShellV2
    title="Comunicação Visual"
    breadcrumbItems={[{ label: 'Comunicação Visual', href: '/comunicacao-visual' }, { label: 'Agenda de instalação' }]}
  >
    {page}
  </AppShellV2>
);
