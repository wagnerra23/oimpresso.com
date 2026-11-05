// @memcofre
//   tela: /fisio/protocolos
//   module: Fisio
//   stories: US-FISIO-006 (UC-FPR-01..05)
//   permissao: fisio.access · copiar e editar exigem fisio.editar
//
// Protocolos-modelo (fonte: prototipo-ui/cowork/Wagner/prototipos/fisio/Protocolos.dc.html).
// Lista à esquerda, protocolo aberto à direita. O modelo do produto é só leitura: a clínica
// copia e edita a cópia. Aplicar a um paciente acontece no editor de programa da ficha.
// A tela não escolhe protocolo para ninguém (ADR 0437 §5, P58).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';

interface Item {
  exercicio: string;
  sets: number | null;
  reps: number | null;
  hold_s: number | null;
  times_per_day: number | null;
  notes: string | null;
}
interface Protocolo {
  id: number;
  nome: string;
  regiao: string | null;
  modelo_global: boolean;
  copia_de: number | null;
  notes: string | null;
  itens: Item[];
}
interface Props {
  pode_editar: boolean;
  protocolos?: Protocolo[];
}

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

async function chamar(metodo: 'POST' | 'PUT', url: string, corpo?: unknown) {
  const res = await fetch(url, {
    method: metodo,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    body: corpo === undefined ? undefined : JSON.stringify(corpo),
  });
  return { status: res.status, json: (await res.json().catch(() => ({}))) as { id?: number; message?: string; errors?: Record<string, string[]> } };
}

function dose(i: Item): string {
  const partes = [i.sets ? `${i.sets} séries` : null, i.reps ? `${i.reps} repetições` : null, i.hold_s ? `${i.hold_s} s` : null, i.times_per_day ? `${i.times_per_day}× ao dia` : null];
  return partes.filter(Boolean).join(' · ') || 'dose no programa';
}

function Edicao({ protocolo, onFim }: { protocolo: Protocolo; onFim: (msg: string) => void }) {
  const [dados, setDados] = useState({ name: protocolo.nome, region: protocolo.regiao ?? '', notes: protocolo.notes ?? '' });
  const [erro, setErro] = useState('');
  const salvar = async (e: FormEvent) => {
    e.preventDefault();
    const r = await chamar('PUT', `/fisio/protocolos/${protocolo.id}`, { ...dados, region: dados.region || null, notes: dados.notes || null });
    if (r.status === 200) onFim('Protocolo salvo.');
    else setErro(Object.values(r.json.errors ?? {})[0]?.[0] ?? r.json.message ?? 'Não foi possível salvar.');
  };
  return (
    <form onSubmit={salvar} className="flex flex-col gap-3" data-contract="fisio-protocolo-edicao">
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="pr-nome">Nome</Label>
          <Input id="pr-nome" value={dados.name} onChange={(e) => setDados({ ...dados, name: e.target.value })} />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="pr-regiao">Região</Label>
          <Input id="pr-regiao" value={dados.region} onChange={(e) => setDados({ ...dados, region: e.target.value })} />
        </div>
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="pr-notas">Observações</Label>
        <Textarea id="pr-notas" rows={3} value={dados.notes} onChange={(e) => setDados({ ...dados, notes: e.target.value })} />
      </div>
      {erro && <p className="text-xs text-destructive">{erro}</p>}
      <div className="flex gap-2">
        <Button type="submit">Salvar</Button>
        <Button type="button" variant="ghost" onClick={() => onFim('')}>Cancelar</Button>
      </div>
    </form>
  );
}

function Detalhe({ protocolo, podeEditar }: { protocolo: Protocolo; podeEditar: boolean }) {
  const [editando, setEditando] = useState(false);
  const [msg, setMsg] = useState('');
  const recarregar = () => router.reload({ only: ['protocolos'] });

  const copiar = async () => {
    const r = await chamar('POST', `/fisio/protocolos/${protocolo.id}/copiar`);
    setMsg(r.status === 201 ? 'Cópia criada na lista da clínica. Edite a cópia.' : (r.json.message ?? 'Não foi possível copiar.'));
    if (r.status === 201) recarregar();
  };

  return (
    <Card className="min-w-0 flex-[2_1_520px]" data-contract="fisio-protocolo-detalhe">
      <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
        <div className="flex flex-col gap-1">
          <span className="text-xs text-muted-foreground">{protocolo.modelo_global ? 'Modelo do produto' : 'Protocolo da clínica'}{protocolo.regiao ? ` · ${protocolo.regiao}` : ''}</span>
          <CardTitle className="text-xl">{protocolo.nome}</CardTitle>
        </div>
        <div className="flex flex-wrap gap-2">
          {podeEditar && protocolo.modelo_global && <Button variant="outline" onClick={copiar}>Copiar para a clínica</Button>}
          {podeEditar && !protocolo.modelo_global && !editando && <Button variant="outline" onClick={() => setEditando(true)}>Editar</Button>}
          <Button asChild><Link href="/fisio/pacientes">Aplicar a um paciente</Link></Button>
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <p className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">Modelo de referência. A decisão clínica é do fisioterapeuta.</p>
        {msg && <p role="status" className="text-sm">{msg}</p>}
        {editando ? (
          <Edicao protocolo={protocolo} onFim={(m) => { setEditando(false); setMsg(m); if (m) recarregar(); }} />
        ) : protocolo.notes && <p className="whitespace-pre-line text-sm">{protocolo.notes}</p>}
        <section className="flex flex-col gap-1" aria-labelledby="pr-itens">
          <h3 id="pr-itens" className="text-sm font-semibold">Exercícios ({protocolo.itens.length})</h3>
          {protocolo.itens.length === 0 ? (
            <p className="text-sm text-muted-foreground">Nenhum exercício deste protocolo está disponível na sua clínica.</p>
          ) : protocolo.itens.map((i, n) => (
            <div key={n} className="flex justify-between gap-3 border-b border-border py-1.5 text-sm last:border-0">
              <span>{i.exercicio}{i.notes && <span className="block text-xs text-muted-foreground">{i.notes}</span>}</span>
              <span className="shrink-0 text-right font-mono text-xs text-muted-foreground">{dose(i)}</span>
            </div>
          ))}
        </section>
        <p className="text-xs text-muted-foreground">Para usar, abra o paciente, crie o programa e aplique o protocolo no editor. A passagem de fase é sempre sua.</p>
      </CardContent>
    </Card>
  );
}

function Conteudo({ protocolos, podeEditar }: { protocolos: Protocolo[]; podeEditar: boolean }) {
  const [abertoId, setAbertoId] = useState<number | null>(protocolos[0]?.id ?? null);
  const aberto = protocolos.find((p) => p.id === abertoId) ?? protocolos[0];

  if (!aberto) {
    return (
      <EmptyState
        title="Nenhum protocolo ainda"
        description="Os modelos do oimpresso aguardam a revisão clínica. Na ficha do paciente, o editor de programa salva um programa como protocolo da clínica."
      />
    );
  }

  return (
    <div className="flex flex-wrap items-start gap-5">
      <Card className="min-w-0 max-w-sm flex-[1_1_300px] overflow-hidden" data-contract="fisio-protocolos-lista">
        <CardHeader className="border-b border-border py-3"><CardTitle className="text-sm">{protocolos.length} protocolos</CardTitle></CardHeader>
        <nav aria-label="Protocolos">
          {protocolos.map((p) => (
            <button
              key={p.id}
              type="button"
              aria-current={p.id === aberto.id ? 'true' : undefined}
              onClick={() => setAbertoId(p.id)}
              className={`flex min-h-11 w-full flex-col gap-0.5 border-b border-border px-4 py-2.5 text-left text-sm last:border-0 ${p.id === aberto.id ? 'bg-accent' : 'hover:bg-muted'}`}
            >
              <span className="font-medium">{p.nome}</span>
              <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <Badge variant={p.modelo_global ? 'secondary' : 'outline'}>{p.modelo_global ? 'modelo do produto' : 'da clínica'}</Badge>
                {p.regiao}
              </span>
            </button>
          ))}
        </nav>
      </Card>
      <Detalhe key={aberto.id} protocolo={aberto} podeEditar={podeEditar} />
    </div>
  );
}

export default function ProtocolosIndex({ pode_editar, protocolos }: Props) {
  return (
    <div data-screen-label="Fisio · Protocolos">
      <PageHeader title="Protocolos-modelo" subtitle="Modelos de referência com a lógica à mostra. A passagem de fase é sempre sua." />
      <div className="p-4">
        <Deferred data="protocolos" fallback={<Skeleton className="h-64 w-full" />}>
          <Conteudo protocolos={protocolos ?? []} podeEditar={pode_editar} />
        </Deferred>
      </div>
    </div>
  );
}

ProtocolosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
