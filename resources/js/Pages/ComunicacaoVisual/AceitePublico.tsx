// Rota PÚBLICA sem login — cliente aceita o orçamento da gráfica ou pede ajuste (US-COMVIS-023)
// Charter: AceitePublico.charter.md · Casos: AceitePublico.casos.md
// Tier 0: a empresa vem do token (só o sha256 no banco), nunca da URL (ADR 0093)

import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, CheckCircle2, FileText, MessageSquare } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { fmtDataBr } from '@/Lib/datetime-br';

interface Item {
  descricao: string;
  largura_m: number;
  altura_m: number;
  quantidade: number;
  subtotal: number;
}

interface Orcamento {
  empresa: string;
  numero: string;
  data_emissao: string | null;
  data_validade: string | null;
  itens: Item[];
  subtotal: number;
  desconto: number;
  extras: number;
  custo_instalacao: number;
  custo_entrega: number;
  total: number;
  situacao: 'aberto' | 'decidido' | 'vencido' | 'fora_da_validade' | 'indisponivel';
  decisao: 'aceito' | 'ajuste' | null;
}

interface Props {
  token: string;
  orcamento: Orcamento;
  feito: string | null;
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
const medida = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 3 });

const AVISO: Record<Exclude<Orcamento['situacao'], 'aberto'>, string> = {
  decidido: 'Este orçamento já foi respondido por este link.',
  vencido: 'Este link venceu. Peça um novo à gráfica.',
  fora_da_validade: 'A validade deste orçamento passou. Peça um novo à gráfica.',
  indisponivel: 'Este orçamento não está mais disponível.',
};

export default function AceitePublico({ token, orcamento, feito }: Props) {
  const { errors } = usePage<{ errors: Record<string, string> }>().props;
  const [pedindoAjuste, setPedindoAjuste] = useState(false);
  const aceite = useForm({});
  const ajuste = useForm({ comentario: '' });
  const ocupado = aceite.processing || ajuste.processing;
  const aberto = orcamento.situacao === 'aberto' && !feito;

  const linhas: Array<[string, number]> = [
    ['Subtotal', orcamento.subtotal],
    ['Desconto', -orcamento.desconto],
    ['Extras', orcamento.extras],
    ['Instalação', orcamento.custo_instalacao],
    ['Entrega', orcamento.custo_entrega],
  ];

  return (
    <div className="min-h-screen bg-muted/30 flex justify-center px-4 py-8">
      <Head title={`Orçamento ${orcamento.numero} · ${orcamento.empresa}`} />

      <main className="max-w-xl w-full space-y-4" data-contract="aceite-publico">
        <header className="text-center">
          <div className="inline-flex items-center justify-center size-12 rounded-full bg-primary/10 text-primary mb-3">
            <FileText className="size-6" aria-hidden="true" />
          </div>
          <p className="text-sm text-muted-foreground">{orcamento.empresa}</p>
          <h1 className="text-xl font-bold">Orçamento {orcamento.numero}</h1>
          {orcamento.data_validade && (
            <p className="text-sm text-muted-foreground mt-1">Válido até {fmtDataBr(orcamento.data_validade)}</p>
          )}
        </header>

        {feito && (
          <Alert data-contract="feito">
            <CheckCircle2 />
            <AlertTitle>Resposta enviada</AlertTitle>
            <AlertDescription>{feito}</AlertDescription>
          </Alert>
        )}
        {!feito && orcamento.situacao !== 'aberto' && (
          <Alert variant="destructive" data-contract="indisponivel">
            <AlertTriangle />
            <AlertTitle>Não dá para responder por este link</AlertTitle>
            <AlertDescription>{AVISO[orcamento.situacao]}</AlertDescription>
          </Alert>
        )}
        {errors.decisao && (
          <Alert variant="destructive">
            <AlertTriangle />
            <AlertDescription>{errors.decisao}</AlertDescription>
          </Alert>
        )}

        <section className="bg-card rounded-lg border shadow-sm p-5" aria-labelledby="itens-titulo" data-contract="itens">
          <h2 id="itens-titulo" className="font-semibold text-sm uppercase text-muted-foreground tracking-wide mb-3">
            Itens
          </h2>
          <ul className="divide-y">
            {orcamento.itens.map((item, i) => (
              <li key={i} className="py-2 flex justify-between gap-4 text-sm">
                <div className="min-w-0">
                  <p className="font-medium break-words">{item.descricao}</p>
                  <p className="text-muted-foreground tabular-nums">
                    {medida(item.largura_m)} × {medida(item.altura_m)} m · {item.quantidade} un.
                  </p>
                </div>
                <span className="tabular-nums shrink-0">{brl(item.subtotal)}</span>
              </li>
            ))}
          </ul>

          <dl className="mt-3 pt-3 border-t space-y-1 text-sm">
            {linhas.filter(([, v]) => v !== 0).map(([rotulo, v]) => (
              <div key={rotulo} className="flex justify-between gap-4">
                <dt className="text-muted-foreground">{rotulo}</dt>
                <dd className="tabular-nums">{brl(v)}</dd>
              </div>
            ))}
            <div className="flex justify-between gap-4 pt-2 border-t">
              <dt className="font-medium">Total</dt>
              <dd className="font-bold tabular-nums">{brl(orcamento.total)}</dd>
            </div>
          </dl>
        </section>

        {aberto && !pedindoAjuste && (
          <div className="grid grid-cols-2 gap-2" data-contract="acoes">
            <Button type="button" variant="outline" disabled={ocupado} onClick={() => setPedindoAjuste(true)}>
              <MessageSquare className="size-4 mr-1.5" aria-hidden="true" />
              Pedir ajuste
            </Button>
            <Button type="button" disabled={ocupado} onClick={() => aceite.post(`/orcamento-cv/${token}/aceitar`, { preserveScroll: true })}>
              <Check className="size-4 mr-1.5" aria-hidden="true" />
              {aceite.processing ? 'Enviando…' : 'Aceitar orçamento'}
            </Button>
          </div>
        )}

        {aberto && pedindoAjuste && (
          <form
            className="bg-card rounded-lg border shadow-sm p-5 space-y-3"
            data-contract="ajuste"
            onSubmit={(e) => {
              e.preventDefault();
              ajuste.post(`/orcamento-cv/${token}/ajuste`, { preserveScroll: true });
            }}
          >
            <Label htmlFor="comentario">O que precisa mudar?</Label>
            <Textarea
              id="comentario"
              rows={4}
              maxLength={2000}
              value={ajuste.data.comentario}
              onChange={(e) => ajuste.setData('comentario', e.target.value)}
              aria-invalid={errors.comentario ? true : undefined}
              aria-describedby={errors.comentario ? 'comentario-erro' : undefined}
            />
            {errors.comentario && (
              <p id="comentario-erro" className="text-sm text-destructive">{errors.comentario}</p>
            )}
            <div className="grid grid-cols-2 gap-2">
              <Button type="button" variant="outline" disabled={ocupado} onClick={() => setPedindoAjuste(false)}>
                Voltar
              </Button>
              <Button type="submit" disabled={ocupado || ajuste.data.comentario.trim() === ''}>
                {ajuste.processing ? 'Enviando…' : 'Enviar pedido'}
              </Button>
            </div>
          </form>
        )}

        <p className="text-xs text-center text-muted-foreground">Link válido por 7 dias · oimpresso.com</p>
      </main>
    </div>
  );
}
