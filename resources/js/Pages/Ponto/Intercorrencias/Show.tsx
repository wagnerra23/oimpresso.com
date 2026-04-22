import AppShell from '@/Layouts/AppShell';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, Send, X, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

interface I {
  id: number | string;
  codigo: string;
  tipo: string;
  estado: string;
  prioridade: string;
  data: string | null;
  dia_todo: boolean;
  intervalo_inicio: string | null;
  intervalo_fim: string | null;
  justificativa: string;
  impacta_apuracao: boolean;
  descontar_banco_horas: boolean;
  motivo_rejeicao: string | null;
  created_at: string | null;
  updated_at: string | null;
  colaborador: { id: number | null; matricula: string | null; nome: string };
  solicitante: { nome: string };
  aprovador: { nome: string | null };
}

interface Props { intercorrencia: I; }

const estadoVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  RASCUNHO: 'outline', PENDENTE: 'default', APROVADA: 'default',
  REJEITADA: 'destructive', APLICADA: 'secondary', CANCELADA: 'outline',
};

export default function IntercorrenciasShow({ intercorrencia: i }: Props) {
  const submeter = () => {
    if (!confirm('Submeter esta intercorrência para aprovação?')) return;
    router.post(`/ponto/intercorrencias/${i.id}/submeter`, {}, {
      onSuccess: () => toast.success('Submetida para aprovação.'),
      onError: () => toast.error('Falha ao submeter.'),
    });
  };

  const cancelar = () => {
    if (!confirm('Cancelar esta intercorrência? Ação não reversível.')) return;
    router.post(`/ponto/intercorrencias/${i.id}/cancelar`, {}, {
      onSuccess: () => toast.success('Cancelada.'),
      onError: () => toast.error('Falha ao cancelar.'),
    });
  };

  const canEdit    = i.estado === 'RASCUNHO';
  const canSubmit  = i.estado === 'RASCUNHO';
  const canCancel  = ['RASCUNHO', 'PENDENTE'].includes(i.estado);

  return (
    <AppShell
      title={`Intercorrência ${i.codigo}`}
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Intercorrências', href: '/ponto/intercorrencias' },
        { label: i.codigo },
      ]}
    >
      <div className="mx-auto max-w-4xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <AlertTriangle size={22} /> {i.codigo}
            </h1>
            <p className="text-sm text-muted-foreground mt-1 flex items-center gap-2">
              <Badge variant={estadoVariant[i.estado]} className="text-[10px]">{i.estado}</Badge>
              {i.prioridade === 'URGENTE' && <Badge variant="destructive" className="text-[10px]">Urgente</Badge>}
              <span>Criada em {i.created_at}</span>
            </p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href="/ponto/intercorrencias"><ArrowLeft size={14} className="mr-1.5" /> Voltar</Link>
          </Button>
        </header>

        {/* Estado REJEITADA: mostra motivo */}
        {i.estado === 'REJEITADA' && i.motivo_rejeicao && (
          <Alert variant="destructive">
            <XCircle size={14} />
            <AlertTitle>Rejeitada por {i.aprovador.nome ?? 'aprovador'}</AlertTitle>
            <AlertDescription>{i.motivo_rejeicao}</AlertDescription>
          </Alert>
        )}

        {i.estado === 'APROVADA' && i.aprovador.nome && (
          <Alert className="border-emerald-500/40">
            <Check size={14} className="text-emerald-600" />
            <AlertTitle>Aprovada por {i.aprovador.nome}</AlertTitle>
            <AlertDescription>Aprovada em {i.updated_at}</AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Dados</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <Row label="Colaborador">
              <strong>{i.colaborador.nome}</strong>
              {i.colaborador.matricula && <span className="ml-2 text-xs text-muted-foreground">mat. {i.colaborador.matricula}</span>}
            </Row>
            <Row label="Tipo">{i.tipo.replace(/_/g, ' ')}</Row>
            <Row label="Data">
              {i.data ?? '—'}
              {i.dia_todo && <span className="ml-2 text-xs text-muted-foreground">(dia todo)</span>}
              {!i.dia_todo && i.intervalo_inicio && (
                <span className="ml-2 text-xs text-muted-foreground">
                  {i.intervalo_inicio} – {i.intervalo_fim}
                </span>
              )}
            </Row>
            <Row label="Justificativa">
              <span className="whitespace-pre-wrap">{i.justificativa}</span>
            </Row>
            <Row label="Impacta apuração">{i.impacta_apuracao ? 'Sim' : 'Não'}</Row>
            <Row label="Desconta BH">{i.descontar_banco_horas ? 'Sim' : 'Não'}</Row>
            <Row label="Solicitante">{i.solicitante.nome}</Row>
          </CardContent>
        </Card>

        {/* Ações por estado */}
        <div className="flex flex-wrap gap-2 justify-end">
          {canEdit && (
            <Button variant="outline" asChild>
              <Link href={`/ponto/intercorrencias/${i.id}/edit`}>Editar</Link>
            </Button>
          )}
          {canSubmit && (
            <Button onClick={submeter} className="gap-1.5">
              <Send size={14} /> Submeter para aprovação
            </Button>
          )}
          {canCancel && (
            <Button variant="outline" onClick={cancelar} className="gap-1.5 text-destructive">
              <X size={14} /> Cancelar
            </Button>
          )}
        </div>
      </div>
    </AppShell>
  );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-2 py-1.5 border-b border-border last:border-0">
      <span className="text-xs text-muted-foreground">{label}</span>
      <span className="md:col-span-2">{children}</span>
    </div>
  );
}
