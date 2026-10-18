// @memcofre
//   tela: /fisio/pacientes/{id}
//   module: Fisio
//   stories: US-FISIO-005 (UC-FPS-02..05, UC-FPS-07)
//   permissao: fisio.access · editar, assinar e bloquear exigem fisio.editar
//
// Ficha do paciente: perfil clínico e prontuário (Res. COFFITO 414/2012). O prontuário só
// cresce: registro assinado não se edita; corrigir é acrescentar um adendo ao original.
// Programa e questionários chegam nas US seguintes (US-FISIO-006 e T-11).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';
import PerfilClinicoCampos, { type PerfilClinico } from '../_components/PerfilClinicoCampos';
import { limparObjetivos, rotuloCondicao } from '../_components/condicoes';

interface Paciente {
  id: number;
  nome: string | null;
  telefone: string | null;
  main_complaint: string | null;
  goals: string[];
  sessions_per_week: number;
  condition_flags: string[];
  bloqueado: boolean;
}
interface Registro {
  id: number;
  kind: 'avaliacao' | 'evolucao' | 'telemonitoramento' | 'adendo';
  body: Record<string, unknown>;
  signed_at: string | null;
  signed_crefito: string | null;
  assinado_por: string;
  amends_id: number | null;
}
interface Props {
  paciente: Paciente;
  condicoes: string[];
  pode_editar: boolean;
  prontuario?: Registro[];
}

const TIPO: Record<Registro['kind'], string> = {
  avaliacao: 'Avaliação', evolucao: 'Evolução', telemonitoramento: 'Telemonitoramento', adendo: 'Adendo',
};
const CAMPOS_AVALIACAO: [string, string][] = [
  ['queixa_principal', 'Queixa principal'], ['historia_doenca', 'História da doença'], ['exame_fisico', 'Exame físico'],
  ['diagnostico_cinesiologico', 'Diagnóstico cinesiológico-funcional'], ['objetivos', 'Objetivos'], ['plano_tratamento', 'Plano de tratamento'],
];
const CAMPOS_EVOLUCAO: [string, string][] = [['conduta', 'Conduta'], ['resposta_paciente', 'Resposta do paciente']];
const ROTULO_CAMPO: Record<string, string> = Object.fromEntries([...CAMPOS_AVALIACAO, ...CAMPOS_EVOLUCAO, ['texto', 'Correção']]);

const dataHora = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
const texto = (v: unknown) => (Array.isArray(v) ? v.join('; ') : String(v ?? ''));

function Perfil({ paciente, condicoes, podeEditar }: { paciente: Paciente; condicoes: string[]; podeEditar: boolean }) {
  const form = useForm<PerfilClinico>({
    main_complaint: paciente.main_complaint ?? '',
    goals: paciente.goals,
    sessions_per_week: paciente.sessions_per_week,
    condition_flags: paciente.condition_flags,
  });
  const [confirmando, setConfirmando] = useState(false);
  const salvar = (e: FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({ ...d, goals: limparObjetivos(d.goals) }));
    form.put(`/fisio/pacientes/${paciente.id}`, { preserveScroll: true });
  };

  if (!podeEditar) {
    return (
      <dl className="flex flex-col gap-3 text-sm">
        <div><dt className="text-muted-foreground">Queixa principal</dt><dd>{paciente.main_complaint || '—'}</dd></div>
        <div><dt className="text-muted-foreground">Objetivos</dt><dd>{paciente.goals.join('; ') || '—'}</dd></div>
        <div><dt className="text-muted-foreground">Dias por semana</dt><dd>{paciente.sessions_per_week}</dd></div>
        <div className="flex flex-wrap gap-1">{paciente.condition_flags.map((c) => <Badge key={c} variant="outline">{rotuloCondicao(c)}</Badge>)}</div>
      </dl>
    );
  }

  return (
    <form onSubmit={salvar} className="flex flex-col gap-4">
      <PerfilClinicoCampos
        dados={form.data}
        condicoes={condicoes}
        erros={form.errors as Record<string, string>}
        onChange={(campo, valor) => form.setData(campo, valor as never)}
      />
      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={form.processing}>Salvar perfil</Button>
        {!paciente.bloqueado && (confirmando ? (
          <>
            <Button type="button" variant="destructive" onClick={() => router.delete(`/fisio/pacientes/${paciente.id}`)}>Confirmar bloqueio</Button>
            <Button type="button" variant="ghost" onClick={() => setConfirmando(false)}>Cancelar</Button>
          </>
        ) : (
          <Button type="button" variant="outline" onClick={() => setConfirmando(true)}>Bloquear paciente</Button>
        ))}
      </div>
      {confirmando && (
        <p className="text-xs text-muted-foreground">Bloquear tira o paciente da lista de ativos. O prontuário fica guardado por 20 anos e continua legível.</p>
      )}
    </form>
  );
}

function NovoRegistro({ pacienteId, corrigindo, onPronto }: { pacienteId: number; corrigindo: Registro | null; onPronto: () => void }) {
  const [tipo, setTipo] = useState<'avaliacao' | 'evolucao'>('evolucao');
  const kind = corrigindo ? 'adendo' : tipo;
  const campos = corrigindo ? [['texto', 'Correção'] as [string, string]] : tipo === 'avaliacao' ? CAMPOS_AVALIACAO : CAMPOS_EVOLUCAO;
  const form = useForm<{ crefito: string; body: Record<string, string> }>({ crefito: '', body: {} });

  const assinar = (e: FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({ ...d, kind, ...(corrigindo ? { amends_id: corrigindo.id } : {}) }));
    form.post(`/fisio/pacientes/${pacienteId}/registros`, {
      preserveScroll: true,
      onSuccess: () => { form.reset('body'); onPronto(); },
    });
  };
  const erros = form.errors as Record<string, string>;

  return (
    <form onSubmit={assinar} className="flex flex-col gap-3 rounded-lg border border-border p-4">
      {corrigindo ? (
        <p className="text-sm">Adendo ao registro de {dataHora(corrigindo.signed_at)}. O original não muda.</p>
      ) : (
        <div className="flex gap-2" role="group" aria-label="Tipo de registro">
          {(['evolucao', 'avaliacao'] as const).map((t) => (
            <Button key={t} type="button" size="sm" variant={tipo === t ? 'default' : 'outline'} onClick={() => setTipo(t)}>{TIPO[t]}</Button>
          ))}
        </div>
      )}
      {campos.map(([chave, rotulo]) => (
        <div key={chave} className="flex flex-col gap-1.5">
          <Label htmlFor={`reg-${chave}`}>{rotulo}</Label>
          <Textarea id={`reg-${chave}`} rows={2} value={form.data.body[chave] ?? ''} onChange={(e) => form.setData('body', { ...form.data.body, [chave]: e.target.value })} />
          {erros[`body.${chave}`] && <p className="text-xs text-destructive">{erros[`body.${chave}`]}</p>}
        </div>
      ))}
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="reg-crefito">CREFITO de quem assina</Label>
        <Input id="reg-crefito" className="max-w-xs" placeholder="CREFITO-10/000000-F" value={form.data.crefito} onChange={(e) => form.setData('crefito', e.target.value)} />
        {erros.crefito && <p className="text-xs text-destructive">{erros.crefito}</p>}
        {erros.amends_id && <p className="text-xs text-destructive">{erros.amends_id}</p>}
      </div>
      <p className="text-xs text-muted-foreground">Ao assinar, data, hora e seu usuário são gravados pelo sistema. Registro assinado não se edita.</p>
      <div className="flex gap-2">
        <Button type="submit" disabled={form.processing}>Assinar registro</Button>
        {corrigindo && <Button type="button" variant="ghost" onClick={onPronto}>Cancelar</Button>}
      </div>
    </form>
  );
}

function LinhaDoTempo({ registros, podeEditar, onCorrigir }: { registros: Registro[]; podeEditar: boolean; onCorrigir: (r: Registro) => void }) {
  if (registros.length === 0) return <p className="text-sm text-muted-foreground">Nenhum registro no prontuário.</p>;
  return (
    <ol className="flex flex-col gap-3" data-contract="fisio-prontuario">
      {registros.map((r) => (
        <li key={r.id} className="rounded-lg border border-border p-3">
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <Badge variant={r.kind === 'adendo' ? 'outline' : 'secondary'}>{TIPO[r.kind]}</Badge>
            <span className="text-muted-foreground">{dataHora(r.signed_at)} · {r.assinado_por || 'Usuário'} · {r.signed_crefito}</span>
            {r.amends_id && <span className="text-xs text-muted-foreground">corrige o registro nº {r.amends_id}</span>}
            {podeEditar && r.kind !== 'adendo' && (
              <Button size="sm" variant="ghost" className="ml-auto" onClick={() => onCorrigir(r)}>Corrigir com adendo</Button>
            )}
          </div>
          <dl className="mt-2 flex flex-col gap-1 text-sm">
            {Object.entries(r.body).map(([k, v]) => (
              <div key={k}><dt className="inline text-muted-foreground">{ROTULO_CAMPO[k] ?? k}: </dt><dd className="inline">{texto(v)}</dd></div>
            ))}
          </dl>
        </li>
      ))}
    </ol>
  );
}

export default function PacienteShow({ paciente, condicoes, pode_editar, prontuario }: Props) {
  const [corrigindo, setCorrigindo] = useState<Registro | null>(null);

  return (
    <div data-screen-label="Fisio · Ficha do paciente">
      <PageHeader
        title={paciente.nome ?? 'Paciente'}
        actions={<Button variant="ghost" asChild><Link href="/fisio/pacientes"><ArrowLeft className="size-4" aria-hidden="true" /> Pacientes</Link></Button>}
      />
      <div className="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <Card>
          <CardContent className="flex flex-col gap-3 p-4">
            <h2 className="text-base font-semibold">Perfil clínico</h2>
            {paciente.bloqueado && <Badge variant="outline">Bloqueado — prontuário guardado</Badge>}
            {paciente.telefone && <p className="text-sm text-muted-foreground">{paciente.telefone}</p>}
            <Perfil paciente={paciente} condicoes={condicoes} podeEditar={pode_editar} />
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex flex-col gap-4 p-4">
            <h2 className="text-base font-semibold">Prontuário</h2>
            {pode_editar && !paciente.bloqueado && (
              <NovoRegistro key={corrigindo?.id ?? 'novo'} pacienteId={paciente.id} corrigindo={corrigindo} onPronto={() => setCorrigindo(null)} />
            )}
            <Deferred data="prontuario" fallback={<Skeleton className="h-40 w-full" />}>
              <LinhaDoTempo registros={prontuario ?? []} podeEditar={pode_editar && !paciente.bloqueado} onCorrigir={setCorrigindo} />
            </Deferred>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

PacienteShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
