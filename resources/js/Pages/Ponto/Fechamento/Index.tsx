// Ponto/Fechamento — fechamento da competência (ADR 0413 · thread 04 do playbook Ponto).
// Âncora de design: prototipo-ui/cowork/Wagner/ponto-fechamento.jsx (`Fechamento`).
// Diferenças conscientes contra o protótipo: memory/requisitos/Ponto/RUNBOOK-fechamento.md §4.

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
import { Deferred, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Download, Lock } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Grid, Inline } from '@/Components/layout';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

type BloqueioId = 'divergencia' | 'intercorrencia' | 'clt' | 'sem_pis' | 'importacao';

interface Bloqueio { id: BloqueioId; grave: boolean; n: number }

interface Fechada { fechada_em: string; fechada_por: string; bloqueios_aceitos: number }

interface Props {
  competencia: string; // AAAA-MM
  fechada: Fechada | null;
  pode_fechar: boolean;
  bloqueios?: Bloqueio[]; // Inertia::defer
}

// Rótulo e destino de cada bloqueio. A CONTAGEM vem do backend (FechamentoService::preChecagem).
const ROTULO: Record<BloqueioId, { um: string; varios: string; sub: string; acao: string; href: (c: string) => string }> = {
  divergencia: { um: 'dia em DIVERGENCIA', varios: 'dias em DIVERGENCIA', sub: 'apuração não consolida com dia divergente', acao: 'Ver espelhos', href: (c) => `/ponto/espelho?mes=${c}` },
  intercorrencia: { um: 'intercorrência em aberto', varios: 'intercorrências em aberto', sub: 'rascunho ou pendente — decidir antes de fechar, senão a correção fica fora do mês', acao: 'Abrir fila', href: () => '/ponto/aprovacoes' },
  clt: { um: 'dia com violação de regra dura da CLT', varios: 'dias com violação de regra dura da CLT', sub: 'interjornada (Art. 66) ou intrajornada (Art. 71) já apuradas', acao: 'Ver espelhos', href: (c) => `/ponto/espelho?mes=${c}` },
  sem_pis: { um: 'colaborador sem PIS', varios: 'colaboradores sem PIS', sub: 'o AFD rejeita a linha e o eSocial S-2230 não sobe', acao: 'Configurar', href: () => '/ponto/colaboradores' },
  importacao: { um: 'importação em andamento', varios: 'importações em andamento', sub: 'esperar o worker terminar para não fechar com marcação faltando', acao: 'Ver importações', href: () => '/ponto/importacoes' },
};

const PASSOS = ['Pré-checagem', 'Fechar competência', 'AFD / AEJ — em Relatórios'];

function mesExtenso(c: string): string {
  const [a = 1970, m = 1] = c.split('-').map(Number);
  return new Date(a, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
}

export default function FechamentoIndex({ competencia, fechada, pode_fechar, bloqueios }: Props) {
  const [confirmar, setConfirmar] = useState(false);
  const abertos = (bloqueios ?? []).filter((b) => b.n > 0);
  const graves = abertos.filter((b) => b.grave);
  const nGraves = graves.reduce((s, b) => s + b.n, 0);
  const passoAtual = fechada ? 2 : 0;

  const trocarMes = (c: string) => router.get('/ponto/fechamento', { competencia: c }, { preserveScroll: true });
  const fechar = (aceitar: boolean) =>
    router.post('/ponto/fechamento', { competencia, aceitar_bloqueios: aceitar }, { preserveScroll: true, onFinish: () => setConfirmar(false) });

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <h1>Fechamento <span className="text-muted-foreground font-normal">· Competência</span></h1>
          <p>Confira a pré-checagem e feche o mês. Fechar não altera marcação nem apuração.</p>
        </div>
        <div className="os-page-h-r"><PontoSubNav active="fechamento" hidePrimary /></div>
      </header>

      <Card data-contract="fechamento-acoes">
        <CardContent>
          <Inline gap={4} align="end" wrap>
          <div className="space-y-1.5">
            <Label htmlFor="competencia">Competência</Label>
            <Input id="competencia" type="month" value={competencia} onChange={(e) => e.target.value && trocarMes(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <span className="text-sm font-medium">Situação</span>
            <div aria-live="polite">
              <Badge variant={fechada ? 'success' : 'warning'}>{fechada ? 'Fechada' : 'Aberta'}</Badge>
            </div>
          </div>
          <Inline gap={2} wrap className="ml-auto">
            {!fechada && pode_fechar && graves.length > 0 && (
              <Button variant="outline" onClick={() => setConfirmar(true)}>Fechar aceitando os bloqueios</Button>
            )}
            {!fechada && (
              <Button disabled={!pode_fechar || !bloqueios || graves.length > 0} onClick={() => fechar(false)}
                title={pode_fechar ? undefined : 'Fechar competência exige a permissão ponto.fechar'}>
                <Lock className="size-4" aria-hidden />Fechar competência
              </Button>
            )}
            {fechada && (
              <Button asChild variant="outline">
                <Link href="/ponto/relatorios"><Download className="size-4" aria-hidden />Ir para Relatórios</Link>
              </Button>
            )}
          </Inline>
          </Inline>
        </CardContent>
      </Card>

      <Grid asChild fit="sm" gap={2} data-contract="fechamento-passos"><ol>
        {PASSOS.map((p, i) => (
          <li key={p} aria-current={i === passoAtual ? 'step' : undefined}
            className={'rounded-md border p-3 text-sm ' + (i === passoAtual ? 'border-primary' : 'border-border')}>
            <span className="font-mono text-muted-foreground mr-2">{i + 1}</span><b>{p}</b>
          </li>
        ))}
      </ol></Grid>

      {fechada && (
        <Card role="status">
          <CardContent>
            <b>Competência {mesExtenso(competencia)} fechada</b> por {fechada.fechada_por} em {fechada.fechada_em}
            {fechada.bloqueios_aceitos > 0 && ` — ${fechada.bloqueios_aceitos} bloqueio(s) aceito(s), sem assinatura digital`}.
            {' '}Correção agora é <b>anulação + nova marcação</b>, com registro de auditoria — nunca edição da original.
          </CardContent>
        </Card>
      )}

      <Card data-contract="fechamento-pre-checagem">
        <CardHeader>
          <CardTitle>Pré-checagem do fechamento</CardTitle>
          <CardDescription>{bloqueios ? (abertos.length === 0 ? 'nada bloqueia' : `${abertos.length} itens abertos`) : 'calculando…'}</CardDescription>
        </CardHeader>
        <CardContent>
          <Deferred data="bloqueios" fallback={<Skeleton className="h-24 w-full" />}>
            <table className="w-full text-sm">
              <thead><tr className="text-left text-muted-foreground">
                <th scope="col">Bloqueio</th><th scope="col">Grau</th><th scope="col" className="text-right">Ação</th>
              </tr></thead>
              <tbody>
                {abertos.length === 0 && (
                  <tr><td colSpan={3} className="py-3">Nada pendente — a competência pode fechar.</td></tr>
                )}
                {abertos.map((b) => {
                  const r = ROTULO[b.id];
                  return (
                    <tr key={b.id} className="border-t">
                      <td className="py-2"><b>{b.n} {b.n === 1 ? r.um : r.varios}</b><div className="text-muted-foreground">{r.sub}</div></td>
                      <td><Badge variant={b.grave ? 'danger' : 'warning'}>{b.grave ? 'bloqueia' : 'conferir'}</Badge></td>
                      <td className="text-right"><Button asChild variant="outline" size="sm"><Link href={r.href(competencia)}>{r.acao}</Link></Button></td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </Deferred>
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground" data-contract="fechamento-legal">
        Fechamento não apaga histórico: a competência fechada guarda a apuração como estava, e qualquer correção posterior entra como novo lançamento (Portaria MTP 671/2021).
      </p>

      <AlertDialog open={confirmar} onOpenChange={setConfirmar}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Fechar {mesExtenso(competencia)} aceitando {nGraves} bloqueio(s)?</AlertDialogTitle>
            <AlertDialogDescription>
              Eles ficam registrados no fechamento com o seu nome e a data. Não há assinatura digital, e a competência fechada não reabre.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={() => fechar(true)}>Fechar aceitando</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

FechamentoIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Fechamento · Ponto WR2" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Fechamento' }]}>
    {page}
  </AppShellV2>
);
