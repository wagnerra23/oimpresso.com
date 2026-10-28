// Rota PÚBLICA sem login — cliente paga um título a receber por Pix ou boleto (US-FIN-081)
// Charter: PagamentoPublico.charter.md · Casos: PagamentoPublico.casos.md
// Tier 0: a empresa vem do token (só o sha256 no banco), nunca da URL (ADR 0093)

import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Barcode, CheckCircle2, Copy, QrCode, Receipt } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Grid, Inline } from '@/Components/layout';
import { fmtDataBr } from '@/Lib/datetime-br';

interface Meio {
  linha_digitavel: string | null;
  pix_copia_cola: string | null;
  boleto_url: string | null;
}

interface Titulo {
  empresa: string;
  numero: string;
  vencimento: string | null;
  valor_aberto: number;
  situacao: 'aberto' | 'pago' | 'vencido' | 'indisponivel';
  meio: Meio | null;
}

interface Props {
  token: string;
  titulo: Titulo;
  gerado: Meio | null;
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

const AVISO: Record<Exclude<Titulo['situacao'], 'aberto'>, string> = {
  pago: 'Este título já está pago. Obrigado!',
  vencido: 'Este link venceu. Peça um novo à empresa.',
  indisponivel: 'Este título não está mais disponível.',
};

function Codigo({ rotulo, valor }: { rotulo: string; valor: string }) {
  const [copiado, setCopiado] = useState(false);
  return (
    <div className="space-y-1.5">
      <p className="text-sm font-medium">{rotulo}</p>
      <p className="text-xs break-all bg-muted rounded p-2 font-mono">{valor}</p>
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={() => navigator.clipboard?.writeText(valor).then(() => setCopiado(true))}
      >
        <Copy className="size-4 mr-1.5" aria-hidden="true" />
        {copiado ? 'Copiado' : 'Copiar'}
      </Button>
    </div>
  );
}

export default function PagamentoPublico({ token, titulo, gerado }: Props) {
  const { errors } = usePage<{ errors: Record<string, string> }>().props;
  const form = useForm({});
  const meio = gerado ?? titulo.meio;
  const aberto = titulo.situacao === 'aberto';

  return (
    <Inline align="stretch" justify="center" gap={0} className="min-h-screen bg-muted/30 px-4 py-8">
      <Head title={`Pagamento ${titulo.numero} · ${titulo.empresa}`} />

      <main className="max-w-md w-full space-y-4" data-contract="pagamento-publico">
        <header className="text-center">
          <div className="inline-flex items-center justify-center size-12 rounded-full bg-primary/10 text-primary mb-3">
            <Receipt className="size-6" aria-hidden="true" />
          </div>
          <p className="text-sm text-muted-foreground">{titulo.empresa}</p>
          <h1 className="text-xl font-bold">Título {titulo.numero}</h1>
          {titulo.vencimento && (
            <p className="text-sm text-muted-foreground mt-1">Vencimento {fmtDataBr(titulo.vencimento)}</p>
          )}
        </header>

        <section className="bg-card rounded-lg border shadow-sm p-5 text-center" data-contract="valor">
          <p className="text-sm text-muted-foreground">Valor a pagar</p>
          <p className="text-3xl font-bold tabular-nums">{brl(titulo.valor_aberto)}</p>
        </section>

        {!aberto && (
          <Alert variant={titulo.situacao === 'pago' ? 'default' : 'destructive'} data-contract="indisponivel">
            {titulo.situacao === 'pago' ? <CheckCircle2 /> : <AlertTriangle />}
            <AlertTitle>{titulo.situacao === 'pago' ? 'Pago' : 'Não dá para pagar por este link'}</AlertTitle>
            <AlertDescription>{AVISO[titulo.situacao as Exclude<Titulo['situacao'], 'aberto'>]}</AlertDescription>
          </Alert>
        )}
        {errors.pagamento && (
          <Alert variant="destructive">
            <AlertTriangle />
            <AlertDescription>{errors.pagamento}</AlertDescription>
          </Alert>
        )}

        {aberto && meio && (
          <section className="bg-card rounded-lg border shadow-sm p-5 space-y-4" data-contract="meio">
            {meio.pix_copia_cola && <Codigo rotulo="Pix copia e cola" valor={meio.pix_copia_cola} />}
            {meio.linha_digitavel && <Codigo rotulo="Linha digitável do boleto" valor={meio.linha_digitavel} />}
            {meio.boleto_url && (
              <a className="text-sm text-primary underline" href={meio.boleto_url} target="_blank" rel="noreferrer noopener">
                Abrir o boleto em PDF
              </a>
            )}
          </section>
        )}

        {aberto && !meio && (
          <Grid cols={2} gap={2} data-contract="acoes">
            <Button type="button" disabled={form.processing} onClick={() => form.post(`/pagar/${token}/pix`, { preserveScroll: true })}>
              <QrCode className="size-4 mr-1.5" aria-hidden="true" />
              Pagar com Pix
            </Button>
            <Button
              type="button"
              variant="outline"
              disabled={form.processing}
              onClick={() => form.post(`/pagar/${token}/boleto`, { preserveScroll: true })}
            >
              <Barcode className="size-4 mr-1.5" aria-hidden="true" />
              Gerar boleto
            </Button>
          </Grid>
        )}

        <p className="text-xs text-center text-muted-foreground">Pagamento seguro · oimpresso.com</p>
      </main>
    </Inline>
  );
}
