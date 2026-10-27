/**
 * US-FIN-078 — Boletos DDA: boletos emitidos contra o CNPJ da empresa, sugeridos como conta a
 * pagar. Nada vira título sem o usuário aceitar. Só aparece com FINANCEIRO_DDA_ENABLED
 * (a prop `dda` chega null quando desligado e o Index nem monta a entrada).
 */
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';

export interface DdaSugestao {
  id: number;
  beneficiario: string | null;
  fornecedor: string | null;
  valor: number;
  vencimento: string;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 }).format(v ?? 0);

const dataBR = (iso: string) => iso.split('-').reverse().join('/');

export function FinDdaSheet({ open, onClose, sugestoes }: { open: boolean; onClose: () => void; sugestoes: DdaSugestao[] }) {
  const [enviando, setEnviando] = useState<number | null>(null);

  const decidir = (id: number, acao: 'aceitar' | 'descartar') => {
    setEnviando(id);
    router.post(`/financeiro/dda/${id}/${acao}`, {}, {
      preserveScroll: true,
      only: ['dda', 'lancamentos', 'kpis', 'flash'],
      onFinish: () => setEnviando(null),
    });
  };

  return (
    <Sheet open={open} onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="right" className="w-full sm:max-w-lg overflow-y-auto" data-contract="dda-boletos">
        <SheetHeader>
          <SheetTitle className="text-xl font-semibold tracking-tight">Boletos DDA</SheetTitle>
          <p className="text-[13px] text-muted-foreground leading-relaxed">
            Boletos emitidos contra o CNPJ da empresa. Aceite para virar conta a pagar ou descarte se não for seu.
          </p>
        </SheetHeader>

        {sugestoes.length === 0 ? (
          <p className="mt-6 text-[13px] text-muted-foreground">Nenhum boleto DDA aguardando decisão.</p>
        ) : (
          <ul className="mt-6 divide-y divide-border">
            {sugestoes.map((s) => (
              <li key={s.id} className="py-3 flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-[13px] font-medium truncate">{s.fornecedor ?? s.beneficiario ?? 'Beneficiário sem nome'}</div>
                  <div className="text-[12px] text-muted-foreground">
                    {s.fornecedor ? 'Fornecedor cadastrado' : 'Fornecedor não cadastrado'} · vence {dataBR(s.vencimento)}
                  </div>
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0">
                  <span className="text-[13px] font-semibold tabular-nums">{brl(s.valor)}</span>
                  <div className="flex gap-1.5">
                    <Button size="sm" variant="ghost" className="h-7 px-2 text-[12px]" disabled={enviando === s.id}
                      onClick={() => decidir(s.id, 'descartar')}>Descartar</Button>
                    <Button size="sm" variant="outline" className="h-7 px-2 text-[12px]" disabled={enviando === s.id}
                      onClick={() => decidir(s.id, 'aceitar')}>Aceitar</Button>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </SheetContent>
    </Sheet>
  );
}
