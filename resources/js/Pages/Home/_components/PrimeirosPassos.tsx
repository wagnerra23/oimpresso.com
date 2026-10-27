// Onboarding guiado (US-SUPER-012) — card "Primeiros passos" da Visão geral.
// Âncora de design: prototipo-ui/cowork/Wagner/primeiros-passos.html (ADR 0438).
// O status de cada passo vem do servidor, derivado do dado real da empresa: clicar no
// passo só leva à tela que já existe. Pular/dispensar gravam a escolha da empresa.

import { Button } from '@/Components/ui/button';
import { Icon } from '@/Components/Icon';
import { Inline, Stack } from '@/Components/layout';
import { Link, router } from '@inertiajs/react';

export interface PassoOnboarding {
  chave: string;
  titulo: string;
  sub: string;
  acao: string;
  href: string;
  status: 'feito' | 'pulado' | 'aberto';
}

export interface EstadoPrimeirosPassos {
  visivel: boolean;
  dispensado: boolean;
  feitos: number;
  pulados: number;
  total: number;
  passos: PassoOnboarding[];
}

const CARTAO = 'rounded-lg border border-border bg-card p-[14px] shadow-sm';

function registrar(acao: 'pular' | 'voltar' | 'dispensar' | 'retomar', passo?: string) {
  router.post('/home/primeiros-passos', passo ? { acao, passo } : { acao }, {
    preserveScroll: true,
    only: ['primeiros_passos'],
  });
}

export default function PrimeirosPassos({ estado }: { estado?: EstadoPrimeirosPassos | null }) {
  if (!estado) return null;

  if (!estado.visivel) {
    // Dispensado com passo ainda aberto: o caminho de volta é discreto, sem ocupar a tela.
    if (!estado.dispensado || estado.feitos === estado.total) return null;
    return (
      <Inline justify="end">
        <Button variant="link" size="sm" onClick={() => registrar('retomar')}>
          Retomar primeiros passos
        </Button>
      </Inline>
    );
  }

  const atual = estado.passos.find((p) => p.status === 'aberto')?.chave;
  const pct = Math.round((estado.feitos / estado.total) * 100);

  return (
    <section aria-label="Primeiros passos" data-contract="primeiros-passos" className={CARTAO}>
      <Inline justify="between" align="center" wrap gap={3}>
        <Stack gap={1}>
          <h2 className="text-[13.5px] font-semibold text-foreground">Primeiros passos</h2>
          <span className="font-mono text-[10.5px] text-muted-foreground">
            {estado.feitos} de {estado.total} feitos
            {estado.pulados > 0 ? ` · ${estado.pulados} ${estado.pulados === 1 ? 'pulado' : 'pulados'}` : ''}
          </span>
        </Stack>
        <Button variant="ghost" size="sm" onClick={() => registrar('dispensar')}>
          Dispensar
        </Button>
      </Inline>

      <div
        role="progressbar"
        aria-label="Progresso dos primeiros passos"
        aria-valuemin={0}
        aria-valuemax={estado.total}
        aria-valuenow={estado.feitos}
        className="mt-[10px] mb-1 h-[6px] overflow-hidden rounded-full bg-muted"
      >
        <div className="h-full rounded-full bg-primary" style={{ width: `${pct}%` }} />
      </div>

      <ol>
        {estado.passos.map((p, i) => (
          <li
            key={p.chave}
            className="grid grid-cols-[22px_minmax(0,1fr)_auto] items-center gap-[10px] border-b border-border py-[9px] last:border-b-0"
          >
            <span
              aria-hidden="true"
              className={
                'grid size-5 place-items-center rounded-full border text-[11px] ' +
                (p.status === 'feito'
                  ? 'border-success bg-success/10 text-success'
                  : p.chave === atual
                    ? 'border-primary text-primary'
                    : 'border-border text-muted-foreground')
              }
            >
              {p.status === 'feito' ? <Icon name="check" size={12} /> : p.status === 'pulado' ? '–' : i + 1}
            </span>
            <Stack gap={0} className="min-w-0">
              <span
                className={
                  'text-[13px] font-medium ' +
                  (p.status === 'aberto' ? 'text-foreground' : 'text-muted-foreground')
                }
              >
                {p.titulo}
              </span>
              <span className="text-[11.5px] text-muted-foreground">{p.sub}</span>
            </Stack>
            <Inline gap={2} align="center" className="justify-end">
              {p.status === 'feito' && <span className="font-mono text-[10.5px] text-success">feito</span>}
              {p.status === 'pulado' && (
                <>
                  <span className="font-mono text-[10.5px] text-muted-foreground">pulado</span>
                  <Button variant="link" size="sm" onClick={() => registrar('voltar', p.chave)}>
                    Voltar a este passo
                  </Button>
                </>
              )}
              {p.status === 'aberto' && (
                <>
                  <Button variant="ghost" size="sm" onClick={() => registrar('pular', p.chave)}>
                    Pular
                  </Button>
                  <Button asChild size="sm" variant={p.chave === atual ? 'default' : 'outline'}>
                    <Link href={p.href}>{p.acao}</Link>
                  </Button>
                </>
              )}
            </Inline>
          </li>
        ))}
      </ol>
    </section>
  );
}
