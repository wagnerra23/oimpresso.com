// Fila do gestor do REP-P — marcações do celular que o geofence sinalizou (thread 06 · [W]
// 2026-09-29: "seção nova em Aprovações"). Forma: ValidacaoMobile de ponto-mobile.jsx.
//
// Validar = registro na trilha, a marcação não muda. Recusar = marcação NOVA de anulação
// (ORIGEM_ANULACAO, D3) — a original fica. Precisão do GPS e nome do local NÃO são gravados
// na marcação: a coluna GPS mostra "—" e o local, as coordenadas (não se inventa dado).
import { Deferred, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Info } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import EmptyState from '@/Components/shared/EmptyState';

export interface MarcacaoMobile {
  id: string; nsr: number; quando: string | null; tipo: string; colaborador: string;
  dispositivo: string; lat: string; lng: string; hash_trunc: string;
  estado: 'PENDENTE' | 'VALIDADA' | 'RECUSADA';
}

const TIPO: Record<string, string> = {
  ENTRADA: 'Entrada', ALMOCO_INICIO: 'Saída almoço', ALMOCO_FIM: 'Retorno almoço', SAIDA: 'Saída',
};
const ESTADO = {
  PENDENTE: { label: 'A validar', variant: 'warning' },
  VALIDADA: { label: 'Validada', variant: 'success' },
  RECUSADA: { label: 'Recusada', variant: 'danger' },
} as const;

function Tabela({ itens, podeRecusar }: { itens?: MarcacaoMobile[]; podeRecusar: boolean }) {
  const [recusar, setRecusar] = useState<MarcacaoMobile | null>(null);
  const [enviando, setEnviando] = useState(false);
  const lista = itens ?? [];
  const pendentes = lista.filter((m) => m.estado === 'PENDENTE').length;

  const decidir = (m: MarcacaoMobile, acao: 'validar' | 'recusar') => {
    setEnviando(true);
    router.post(`/ponto/aprovacoes/mobile/${m.id}/${acao}`, {}, {
      preserveScroll: true,
      only: ['mobile'],
      onSuccess: () => toast.success(acao === 'validar'
        ? `Marcação NSR ${m.nsr} validada.`
        : `Marcação NSR ${m.nsr} recusada — gravada a anulação; a original não muda.`),
      onError: () => toast.error('Não foi possível decidir esta marcação — ela pode já ter sido decidida.'),
      onFinish: () => { setEnviando(false); setRecusar(null); },
    });
  };

  return (
    <Card data-contract="repp-fila-validacao">
      <CardContent className="flex flex-col gap-3 p-4">
        <div>
          <h2 className="font-semibold">Marcações mobile a validar</h2>
          <p className="text-xs text-muted-foreground">({pendentes} pendentes · últimos 7 dias)</p>
        </div>
        <Alert role="note" className="border-info/25 bg-info/5">
          <Info aria-hidden />
          <AlertTitle>Por que uma fila</AlertTitle>
          <AlertDescription>
            Fora do geofence a marcação <b>não é recusada</b> — ela entra e fica sinalizada para revisão humana.
            Recusar grava uma marcação de anulação apontando a original; nada é apagado.
          </AlertDescription>
        </Alert>

        {lista.length === 0 ? (
          <EmptyState icon="check" title="Nenhuma marcação mobile aguardando validação." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                <tr>
                  {['Quando', 'Colaborador', 'Tipo', 'Local', 'GPS', 'Hash da marcação', 'Estado'].map((h) => (
                    <th key={h} className="p-3 text-left font-medium">{h}</th>
                  ))}
                  <th className="p-3 text-right font-medium">Ação</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {lista.map((m) => (
                  <tr key={m.id}>
                    <td className="p-3 font-mono">{m.quando}<small className="block text-muted-foreground">NSR {m.nsr}</small></td>
                    <td className="p-3"><b>{m.colaborador}</b><small className="block text-muted-foreground">{m.dispositivo}</small></td>
                    <td className="p-3">{TIPO[m.tipo] ?? m.tipo}</td>
                    <td className="p-3 font-mono text-xs">{m.lat}, {m.lng}</td>
                    <td className="p-3 text-muted-foreground">—</td>
                    <td className="p-3 font-mono text-xs">{m.hash_trunc}…</td>
                    <td className="p-3"><Badge variant={ESTADO[m.estado].variant}>{ESTADO[m.estado].label}</Badge></td>
                    <td className="p-3 text-right">
                      {m.estado === 'PENDENTE' ? (
                        <span className="inline-flex gap-2">
                          <Button size="sm" disabled={enviando} onClick={() => decidir(m, 'validar')}>Validar</Button>
                          {podeRecusar && (
                            <Button size="sm" variant="destructive" disabled={enviando} onClick={() => setRecusar(m)}>Recusar</Button>
                          )}
                        </span>
                      ) : <span className="text-muted-foreground">—</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>

      <AlertDialog open={recusar !== null} onOpenChange={(o) => { if (!o) setRecusar(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Recusar a marcação NSR {recusar?.nsr}?</AlertDialogTitle>
            <AlertDialogDescription>
              Grava uma marcação de anulação apontando a original (Portaria MTP 671/2021). Não dá para desfazer.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={() => recusar && decidir(recusar, 'recusar')}>Recusar</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}

export default function FilaMobile({ itens, podeRecusar }: { itens?: MarcacaoMobile[]; podeRecusar: boolean }) {
  return (
    <Deferred data="mobile" fallback={<Skeleton className="h-40 w-full" />}>
      <Tabela itens={itens} podeRecusar={podeRecusar} />
    </Deferred>
  );
}
