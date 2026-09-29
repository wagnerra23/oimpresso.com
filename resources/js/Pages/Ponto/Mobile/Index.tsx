// Ponto/Mobile — REP-P (celular): o colaborador bate o ponto pelo próprio aparelho.
// Forma: prototipo-ui/cowork/Wagner/ponto-mobile.jsx (BaterPonto). A moldura de Android e o
// "Simular condição de campo" do protótipo são andaime da demonstração — aqui o aparelho É o
// celular do colaborador, e o GPS é o real (navigator.geolocation). RUNBOOK-mobile.md §4.
//
// SEM BIOMETRIA (ADR 0383): nenhuma câmera, nenhuma imagem. Anti-fraude no servidor
// (MobileMarcacaoService): GPS acima do limite RECUSA, relógio fora do limite RECUSA, geofence
// SINALIZA. A tela nunca gera NSR — ele vem na resposta.
import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import { Info } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { FormGrid, FormSection } from '@/Components/ui/form-section';
import { Segmented } from '@/Components/ui/segmented';
import EmptyState from '@/Components/shared/EmptyState';
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
import MeuEspelho, { type LinhaEspelho, type TotaisEspelho } from './_components/MeuEspelho';
import Justificar from './_components/Justificar';

interface MarcacaoHoje { id: string; nsr: number; tipo: string; hora: string | null; revisar: boolean }

interface Props {
  colaborador: { nome: string; matricula: string | null } | null;
  marcacoes_hoje: MarcacaoHoje[];
  hoje: string;
  mes: string;
  totais?: TotaisEspelho | null; // Inertia::defer
  linhas?: LinhaEspelho[]; // Inertia::defer
  tipos: Array<{ value: string; label: string }>;
  limites: { accuracy_max: number; drift_max: number };
}

const TELAS = [
  { value: 'bater', label: 'Bater ponto', titulo: 'Ponto' },
  { value: 'espelho', label: 'Meu espelho', titulo: 'Meu espelho' },
  { value: 'justificar', label: 'Justificar', titulo: 'Justificar' },
] as const;

const TIPOS = [
  { id: 'ENTRADA', label: 'Entrada', hint: 'início da jornada' },
  { id: 'ALMOCO_INICIO', label: 'Saída almoço', hint: 'intervalo' },
  { id: 'ALMOCO_FIM', label: 'Retorno almoço', hint: 'volta do intervalo' },
  { id: 'SAIDA', label: 'Saída', hint: 'fim da jornada' },
] as const;

type Gps =
  | { estado: 'buscando' }
  | { estado: 'indisponivel'; motivo: string }
  | { estado: 'ok'; lat: number; lng: number; accuracy: number };

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

/** Identifica o APARELHO (não a pessoa): uuid local → `dispositivo_id = mobile:{uuid}` no servidor. */
function deviceUuid(): string {
  try {
    const k = 'ponto.device_uuid';
    const v = window.localStorage.getItem(k) ?? crypto.randomUUID();
    window.localStorage.setItem(k, v);
    return v;
  } catch {
    return crypto.randomUUID();
  }
}

const rotulo = (tipo: string) => TIPOS.find((t) => t.id === tipo)?.label ?? tipo;
const tipoSeguinte = (n: number): string => (TIPOS[Math.min(n, 3)] ?? TIPOS[0]).id;

function BaterPonto({ marcacoesIniciais, limites }: { marcacoesIniciais: MarcacaoHoje[]; limites: Props['limites'] }) {
  const [marcacoes, setMarcacoes] = useState(marcacoesIniciais);
  const [agora, setAgora] = useState(() => new Date());
  const [gps, setGps] = useState<Gps>({ estado: 'buscando' });
  const [enviando, setEnviando] = useState(false);
  const form = useForm({ tipo: tipoSeguinte(marcacoesIniciais.length) });

  useEffect(() => {
    const t = setInterval(() => setAgora(new Date()), 1000);
    return () => clearInterval(t);
  }, []);

  const localizar = () => {
    if (!('geolocation' in navigator)) {
      setGps({ estado: 'indisponivel', motivo: 'Este aparelho não informa a localização.' });
      return;
    }
    setGps({ estado: 'buscando' });
    navigator.geolocation.getCurrentPosition(
      (p) => setGps({ estado: 'ok', lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) }),
      () => setGps({ estado: 'indisponivel', motivo: 'Localização negada — libere o GPS para este site e tente de novo.' }),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  };
  useEffect(localizar, []);

  // Espelha a regra do servidor para não deixar tentar — quem decide continua sendo o servidor (422).
  const bloqueio =
    gps.estado === 'buscando' ? 'Buscando sua localização…'
      : gps.estado === 'indisponivel' ? gps.motivo
      : gps.accuracy > limites.accuracy_max ? 'Sinal de GPS fraco — aproxime-se de área aberta'
      : null;

  async function bater(e: FormEvent) {
    e.preventDefault();
    if (gps.estado !== 'ok' || bloqueio || enviando) return;
    setEnviando(true);
    try {
      const r = await fetch('/ponto/mobile/marcar', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json', Accept: 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          tipo: form.data.tipo, lat: gps.lat, lng: gps.lng, accuracy: gps.accuracy,
          device_uuid: deviceUuid(), timestamp_device: new Date().toISOString(),
        }),
      });
      const j = await r.json().catch(() => ({}));
      if (r.status !== 201) {
        toast.error(j.mensagem ?? 'Não foi possível registrar a marcação.');
        return;
      }
      const m = j.marcacao;
      setMarcacoes((ms) => [...ms, { id: m.id, nsr: m.nsr, tipo: m.tipo, hora: new Date(m.momento).toTimeString().slice(0, 5), revisar: m.revisar }]);
      form.setData('tipo', tipoSeguinte(marcacoes.length + 1));
      toast.success(`Marcação registrada · NSR ${m.nsr} · hash ${String(m.hash_trunc).slice(0, 8)}${m.revisar ? ' · fora da área, foi para revisão' : ''}`);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <form onSubmit={bater} className="mx-auto flex w-full max-w-md flex-col gap-4">
      <div className="text-center" data-contract="repp-relogio">
        <p className="text-xs text-muted-foreground">agora</p>
        <p className="font-mono text-4xl font-semibold tabular-nums">{agora.toLocaleTimeString('pt-BR')}</p>
        <p className="text-xs text-muted-foreground">{agora.toLocaleDateString('pt-BR', { dateStyle: 'full' })}</p>
      </div>

      <div data-contract="repp-gps" className="flex items-center justify-between gap-3 rounded-md border p-3 text-sm">
        <span>{gps.estado === 'ok' ? `GPS ±${gps.accuracy}m` : gps.estado === 'buscando' ? 'Buscando GPS…' : 'GPS indisponível'}</span>
        <Button type="button" variant="ghost" size="sm" className="min-h-11" onClick={localizar}>Atualizar local</Button>
      </div>

      <FormSection title="Tipo de marcação">
        <FormGrid>
          <div data-contract="repp-tipos" className="grid grid-cols-2 gap-2">
            {TIPOS.map((t) => (
              <Button key={t.id} type="button" variant={form.data.tipo === t.id ? 'default' : 'outline'}
                className="h-auto min-h-11 flex-col items-start py-2" aria-pressed={form.data.tipo === t.id}
                onClick={() => form.setData('tipo', t.id)}>
                <span>{t.label}</span>
                <small className="font-normal opacity-80">{t.hint}</small>
              </Button>
            ))}
          </div>
        </FormGrid>
      </FormSection>

      <Button type="submit" className="min-h-11" disabled={!!bloqueio || enviando} title={bloqueio ?? ''}>
        Bater ponto — {rotulo(form.data.tipo)}
      </Button>
      {bloqueio && <p role="status" className="text-sm text-warning">{bloqueio}</p>}

      <section data-contract="repp-hoje" aria-label="Hoje">
        <h3 className="mb-2 text-sm font-medium">Hoje</h3>
        {marcacoes.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma marcação registrada hoje.</p>}
        <ul className="divide-y">
          {marcacoes.map((m) => (
            <li key={m.id} className="flex items-baseline gap-3 py-2 text-sm">
              <b className="font-mono tabular-nums">{m.hora}</b>
              <span>{rotulo(m.tipo)}</span>
              <small className="ml-auto text-muted-foreground">NSR {m.nsr}{m.revisar ? ' · fora da área' : ''}</small>
            </li>
          ))}
        </ul>
      </section>
      <p className="text-xs text-muted-foreground">Marcação imutável (Portaria MTP 671/2021). Correção só por intercorrência.</p>
    </form>
  );
}

export default function Mobile({ colaborador, marcacoes_hoje, hoje, mes, totais, linhas, tipos, limites }: Props) {
  const [tela, setTela] = useState<string>('bater');
  const titulo = TELAS.find((t) => t.value === tela)?.titulo ?? 'Ponto';

  return (
    <AppShellV2 title="REP-P (celular) · Ponto WR2" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'REP-P (celular)' }]}>
      <div className="flex flex-col gap-4">
        <PontoAreaHeader active="mobile" />

        <Alert role="note" className="border-info/25 bg-info/5" data-contract="repp-nota-regras">
          <Info aria-hidden />
          <AlertTitle>REP-P — o aparelho do colaborador</AlertTitle>
          <AlertDescription>
            Mesma regra do balcão: a marcação nasce imutável, com NSR e hash. O que muda é o contexto — <b>GPS</b> com
            precisão máxima de {limites.accuracy_max}m, <b>relógio</b> do aparelho conferido contra o servidor
            ({limites.drift_max}s) e <b>geofence</b> que sinaliza em vez de recusar.
          </AlertDescription>
        </Alert>

        {colaborador === null ? (
          <EmptyState icon="user-x" title="Seu usuário não tem cadastro de ponto neste empregador"
            description="O REP-P é do colaborador que controla ponto. Peça ao RH o cadastro em Colaboradores." />
        ) : (
          <Card>
            <CardContent className="flex flex-col gap-4 p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-semibold">{titulo}</p>
                  <p className="text-xs text-muted-foreground">
                    {colaborador.nome}{colaborador.matricula ? ` · matrícula ${colaborador.matricula}` : ''}
                  </p>
                </div>
                <Badge variant="secondary">REP-P</Badge>
              </div>
              <Segmented aria-label="Tela do app" value={tela} onValueChange={setTela}
                options={TELAS.map((t) => ({ value: t.value, label: t.label }))} />
              {/* As 3 ficam montadas: trocar de tela não pode perder a batida recém-feita nem o GPS. */}
              <div className={tela === 'bater' ? undefined : 'hidden'}><BaterPonto marcacoesIniciais={marcacoes_hoje} limites={limites} /></div>
              <div className={tela === 'espelho' ? undefined : 'hidden'}><MeuEspelho totais={totais} linhas={linhas} mes={mes} hoje={hoje} /></div>
              <div className={tela === 'justificar' ? undefined : 'hidden'}><Justificar tipos={tipos} hoje={hoje} /></div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppShellV2>
  );
}
