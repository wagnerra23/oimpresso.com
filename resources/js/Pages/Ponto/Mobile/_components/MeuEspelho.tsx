// Meu espelho — sub-tela do REP-P (ponto-mobile.jsx · MeuEspelho). Lê os MESMOS builders do
// Espelho/Show (totais + linhas do mês corrente), entregues deferidos pelo controller.
import { Deferred } from '@inertiajs/react';
import { Skeleton } from '@/Components/ui/skeleton';
import { cn, formatMinutes } from '@/Lib/utils';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface TotaisEspelho { trabalhado: number; atraso: number; falta: number; he_diurna: number; he_noturna: number }
export interface LinhaEspelho {
  data: string; dia: number; dow: string; trabalhado: number; divergencia: boolean; estado: string;
  marcacoes: Array<{ hora: string; tipo: string }>;
}

function mesExtenso(mes: string): string {
  const [a, m] = mes.split('-').map(Number);
  const s = new Date(a ?? 2000, (m ?? 1) - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
  return s.charAt(0).toUpperCase() + s.slice(1);
}

function Conteudo({ totais, linhas, mes, hoje }: { totais?: TotaisEspelho | null; linhas?: LinhaEspelho[]; mes: string; hoje: string }) {
  if (!totais || !linhas) return null;
  // Dias até hoje que tiveram algo a mostrar (marcação ou apuração) — o futuro do mês fica fora.
  const dias = linhas.filter((l) => l.data <= hoje && (l.marcacoes.length > 0 || l.estado !== '')).slice().reverse();
  const cards: Array<[string, number]> = [
    ['Trabalhado', totais.trabalhado],
    ['Hora extra', totais.he_diurna + totais.he_noturna],
    ['Faltas', totais.falta],
    ['Atrasos', totais.atraso],
  ];

  return (
    <Stack gap={4}>
      <Grid data-contract="repp-espelho-totais" cols={2} gap={2}>
        {cards.map(([rotulo, min]) => (
          <div key={rotulo} className="rounded-md border p-3">
            <small className="text-xs text-muted-foreground">{rotulo}</small>
            <b className="block font-mono tabular-nums">{formatMinutes(min)}</b>
          </div>
        ))}
      </Grid>

      <section data-contract="repp-espelho-dias" aria-label="Dia a dia">
        <h3 className="mb-2 text-sm font-medium">{mesExtenso(mes)} · dia a dia</h3>
        {dias.length === 0 && <p className="text-sm text-muted-foreground">Nenhum dia apurado neste mês ainda.</p>}
        <ul className="divide-y">
          {dias.map((d) => (
            <Inline asChild key={d.data} gap={3} className={cn('py-2 text-sm', d.divergencia && 'bg-warning/5')}>
            <li>
              <span className="w-10 text-center"><b className="block tabular-nums">{String(d.dia).padStart(2, '0')}</b><small className="text-xs text-muted-foreground">{d.dow}</small></span>
              <Inline asChild wrap gap={1} className="flex-1 font-mono text-xs">
              <span>
                {d.marcacoes.length ? d.marcacoes.map((m, i) => <i key={i} className="not-italic">{m.hora}</i>) : <i className="text-muted-foreground">sem marcação</i>}
              </span>
              </Inline>
              <span className="text-right font-mono tabular-nums">
                {formatMinutes(d.trabalhado)}
                {d.divergencia && <small className="block text-xs text-warning">conferir</small>}
              </span>
            </li>
            </Inline>
          ))}
        </ul>
      </section>
      <p className="text-xs text-muted-foreground">Espelho do mês corrente. O oficial sai no fechamento da competência.</p>
    </Stack>
  );
}

export default function MeuEspelho(props: { totais?: TotaisEspelho | null; linhas?: LinhaEspelho[]; mes: string; hoje: string }) {
  return (
    <Deferred data={['totais', 'linhas']} fallback={<Skeleton className="h-48 w-full" />}>
      <Conteudo {...props} />
    </Deferred>
  );
}
