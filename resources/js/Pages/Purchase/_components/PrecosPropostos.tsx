// Preços propostos pelo recebimento da compra (US-COM-028 · UC-FLX-17).
// Quando a regra da empresa está em "Propõe novo preço", o recebimento grava o custo novo e deixa
// o preço proposto aqui. Aprovar troca o preço de venda do produto; recusar mantém o preço.
// O cálculo é do servidor (Modules/Compras/Services/PropostasDePreco) — a tela só mostra e envia.

import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { AlertTriangle, Check, X } from 'lucide-react';

export interface PrecoProposto {
  id: number;
  status: 'pendente' | 'aprovada' | 'recusada';
  produto: string;
  variacao: string | null;
  custo_antes: number;
  custo_depois: number;
  preco_antes: number;
  preco_proposto: number;
  preco_atual: number;
  preco_mudou: boolean;
  decided_at: string | null;
}

export interface PrecosPropostosPayload {
  aprovacao: string | null;
  aviso: string | null;
  itens: PrecoProposto[];
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(v ?? 0);

const STATUS_LABEL: Record<PrecoProposto['status'], string> = { pendente: 'Pendente', aprovada: 'Aprovado', recusada: 'Recusado' };

export default function PrecosPropostos({ dados, podeAprovar }: { dados: PrecosPropostosPayload | null; podeAprovar: boolean }) {
  const { flash } = usePage<{ flash?: { success?: unknown; error?: unknown } }>().props;
  const [enviando, setEnviando] = useState<number | null>(null);

  if (!dados || (dados.itens.length === 0 && !dados.aviso)) return null;

  const decidir = (id: number, acao: 'aprovar' | 'recusar') => {
    setEnviando(id);
    router.post(`/compras/propostas-preco/${id}/${acao}`, {}, { preserveScroll: true, onFinish: () => setEnviando(null) });
  };

  const mensagem = [flash?.error, flash?.success].find((m): m is string => typeof m === 'string' && m !== '');

  return (
    <Card className="mt-4" data-testid="precos-propostos">
      <CardHeader className="pb-2 pt-3 px-4">
        <h3 className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">Preços propostos</h3>
      </CardHeader>
      <CardContent className="px-4 pb-4 text-[13px] space-y-3">
        {dados.aviso && (
          <div role="note" className="flex gap-2 rounded border border-warning/20 bg-warning-soft px-3 py-2 text-[12px] text-warning-fg">
            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
            <span>{dados.aviso}</span>
          </div>
        )}
        {mensagem && (
          <p role="status" className={flash?.error ? 'text-[12px] text-destructive' : 'text-[12px] text-success-fg'}>{mensagem}</p>
        )}
        {dados.itens.length > 0 && (
          <table className="w-full text-[12px]">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-muted-foreground border-b border-border">
                <th className="py-1.5 text-left font-medium">Produto</th>
                <th className="py-1.5 text-right font-medium">Custo</th>
                <th className="py-1.5 text-right font-medium">Preço de venda</th>
                <th className="py-1.5 text-right font-medium">Situação</th>
              </tr>
            </thead>
            <tbody>
              {dados.itens.map((p) => (
                <tr key={p.id} className="border-b border-border align-top">
                  <td className="py-2">
                    <div className="font-medium text-foreground">{p.produto}</div>
                    {p.variacao && <div className="text-[11px] text-muted-foreground">{p.variacao}</div>}
                  </td>
                  <td className="py-2 text-right tabular-nums whitespace-nowrap">
                    {brl(p.custo_antes)} → {brl(p.custo_depois)}
                  </td>
                  <td className="py-2 text-right tabular-nums whitespace-nowrap">
                    {brl(p.preco_antes)} → <span className="font-medium">{brl(p.preco_proposto)}</span>
                    {p.preco_mudou && (
                      <div className="text-[11px] text-destructive">Preço mudou para {brl(p.preco_atual)} depois da proposta</div>
                    )}
                  </td>
                  <td className="py-2 text-right">
                    {p.status === 'pendente' && podeAprovar ? (
                      <div className="flex justify-end gap-1.5">
                        <Button size="sm" variant="outline" disabled={enviando !== null || p.preco_mudou} onClick={() => decidir(p.id, 'aprovar')}>
                          <Check className="h-3.5 w-3.5 mr-1" /> Aprovar
                        </Button>
                        <Button size="sm" variant="outline" disabled={enviando !== null} onClick={() => decidir(p.id, 'recusar')}>
                          <X className="h-3.5 w-3.5 mr-1" /> Recusar
                        </Button>
                      </div>
                    ) : (
                      <span className="text-muted-foreground">{STATUS_LABEL[p.status]}</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </CardContent>
    </Card>
  );
}
