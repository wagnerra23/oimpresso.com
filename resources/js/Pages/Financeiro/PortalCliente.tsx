// Rota PÚBLICA sem login — portal do cliente: títulos em aberto, segunda via, histórico e pagar (US-FIN-082)
// Charter: PortalCliente.charter.md · Casos: PortalCliente.casos.md
// Tier 0: empresa e cliente vêm do token (só o sha256 no banco), nunca da URL (ADR 0093)

import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Barcode, Copy, QrCode, Wallet } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';
import { fmtDataBr } from '@/Lib/datetime-br';

interface Meio {
  linha_digitavel: string | null;
  pix_copia_cola: string | null;
  boleto_url: string | null;
}

interface Aberto {
  id: number;
  numero: string;
  vencimento: string | null;
  valor_aberto: number;
  meio: Meio | null;
}

interface Pago {
  numero: string;
  vencimento: string | null;
  valor_total: number;
}

interface Portal {
  empresa: string;
  cliente: string;
  situacao: 'aberto' | 'vencido';
  total_aberto: number;
  abertos: Aberto[];
  pagos: Pago[];
}

interface Props {
  token: string;
  portal: Portal;
  pode_pagar: boolean;
  gerado: { titulo_id: number; meio: Meio } | null;
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

function Codigo({ rotulo, valor }: { rotulo: string; valor: string }) {
  const [copiado, setCopiado] = useState(false);
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium">{rotulo}</p>
      <p className="text-xs break-all bg-muted rounded p-2 font-mono">{valor}</p>
      <Button type="button" variant="outline" size="sm" onClick={() => navigator.clipboard?.writeText(valor).then(() => setCopiado(true))}>
        <Copy className="size-4 mr-1.5" aria-hidden="true" />
        {copiado ? 'Copiado' : 'Copiar'}
      </Button>
    </div>
  );
}

export default function PortalCliente({ token, portal, pode_pagar, gerado }: Props) {
  const { errors } = usePage<{ errors: Record<string, string> }>().props;
  const [ocupado, setOcupado] = useState(false);

  const pagar = (id: number, metodo: 'pix' | 'boleto') =>
    router.post(`/portal-cliente/${token}/${id}/${metodo}`, {}, {
      preserveScroll: true,
      onStart: () => setOcupado(true),
      onFinish: () => setOcupado(false),
    });

  return (
    <Inline align="stretch" justify="center" gap={0} className="min-h-screen bg-muted/30 px-4 py-8">
      <Head title={`Suas contas · ${portal.empresa}`} />

      <main className="max-w-xl w-full space-y-4" data-contract="portal-cliente">
        <header className="text-center">
          <div className="inline-flex items-center justify-center size-12 rounded-full bg-primary/10 text-primary mb-3">
            <Wallet className="size-6" aria-hidden="true" />
          </div>
          <p className="text-sm text-muted-foreground">{portal.empresa}</p>
          <h1 className="text-xl font-bold">Olá, {portal.cliente}</h1>
        </header>

        {portal.situacao === 'vencido' && (
          <Alert variant="destructive" data-contract="vencido">
            <AlertTriangle />
            <AlertTitle>Este link venceu</AlertTitle>
            <AlertDescription>Peça um novo à empresa.</AlertDescription>
          </Alert>
        )}
        {errors.pagamento && (
          <Alert variant="destructive">
            <AlertTriangle />
            <AlertDescription>{errors.pagamento}</AlertDescription>
          </Alert>
        )}

        {portal.situacao === 'aberto' && (
          <section className="bg-card rounded-lg border shadow-sm p-5" aria-labelledby="abertos-titulo" data-contract="abertos">
            <Inline align="center" justify="between" gap={4} className="mb-3">
              <h2 id="abertos-titulo" className="font-semibold text-sm uppercase text-muted-foreground tracking-wide">
                Em aberto
              </h2>
              <span className="font-bold tabular-nums">{brl(portal.total_aberto)}</span>
            </Inline>
            {portal.abertos.length === 0 && <p className="text-sm text-muted-foreground">Nada em aberto. Obrigado!</p>}
            <ul className="divide-y">
              {portal.abertos.map((t) => {
                const meio = gerado?.titulo_id === t.id ? gerado.meio : t.meio;
                return (
                  <li key={t.id} className="py-3 space-y-2">
                    <Inline align="stretch" justify="between" gap={4} className="text-sm">
                      <div>
                        <p className="font-medium">Título {t.numero}</p>
                        {t.vencimento && <p className="text-muted-foreground">Vence {fmtDataBr(t.vencimento)}</p>}
                      </div>
                      <span className="tabular-nums font-medium shrink-0">{brl(t.valor_aberto)}</span>
                    </Inline>
                    {meio && (
                      <div className="space-y-2" data-contract="segunda-via">
                        {meio.pix_copia_cola && <Codigo rotulo="Pix copia e cola" valor={meio.pix_copia_cola} />}
                        {meio.linha_digitavel && <Codigo rotulo="Linha digitável" valor={meio.linha_digitavel} />}
                        {meio.boleto_url && (
                          <a className="text-sm text-primary underline" href={meio.boleto_url} target="_blank" rel="noreferrer noopener">
                            Abrir o boleto em PDF
                          </a>
                        )}
                      </div>
                    )}
                    {!meio && pode_pagar && (
                      <Inline align="center" gap={2}>
                        <Button type="button" size="sm" disabled={ocupado} onClick={() => pagar(t.id, 'pix')}>
                          <QrCode className="size-4 mr-1.5" aria-hidden="true" />
                          Pix
                        </Button>
                        <Button type="button" size="sm" variant="outline" disabled={ocupado} onClick={() => pagar(t.id, 'boleto')}>
                          <Barcode className="size-4 mr-1.5" aria-hidden="true" />
                          Boleto
                        </Button>
                      </Inline>
                    )}
                  </li>
                );
              })}
            </ul>
          </section>
        )}

        {portal.situacao === 'aberto' && portal.pagos.length > 0 && (
          <section className="bg-card rounded-lg border shadow-sm p-5" aria-labelledby="pagos-titulo" data-contract="historico">
            <h2 id="pagos-titulo" className="font-semibold text-sm uppercase text-muted-foreground tracking-wide mb-3">
              Pagos
            </h2>
            <ul className="divide-y">
              {portal.pagos.map((t) => (
                <Inline asChild key={t.numero} align="stretch" justify="between" gap={4} className="py-2 text-sm">
                  <li>
                    <span>Título {t.numero}{t.vencimento ? ` · ${fmtDataBr(t.vencimento)}` : ''}</span>
                    <span className="tabular-nums">{brl(t.valor_total)}</span>
                  </li>
                </Inline>
              ))}
            </ul>
          </section>
        )}

        <p className="text-xs text-center text-muted-foreground">Pagamento seguro · oimpresso.com</p>
      </main>
    </Inline>
  );
}
