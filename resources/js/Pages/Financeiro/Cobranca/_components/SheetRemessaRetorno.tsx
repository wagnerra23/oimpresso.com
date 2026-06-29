// SheetRemessaRetorno.tsx — sheet C6 CNAB 240 remessa+retorno (UI-only F3)
import { useEffect } from 'react';
import { X, Download, Upload, AlertCircle } from 'lucide-react';
import { Btn } from './atoms';
import { brl, fmtDate, cn } from '../_lib/cobranca-shared';

interface RemessaArquivo {
  id: string;
  tipo: 'remessa' | 'retorno';
  filename: string;
  ts: string;
  qtd: number;
  total: number;
  status: string;
}

interface Props {
  remessas?: RemessaArquivo[];
  onClose: () => void;
}

const REMESSAS_MOCK: RemessaArquivo[] = [
  { id: 'r-008', tipo: 'remessa', filename: 'REM_C6_20260519_001.REM', ts: '2026-05-19T18:00:00', qtd: 14, total: 18420.00, status: 'enviada' },
  { id: 'r-007', tipo: 'retorno', filename: 'RET_C6_20260518.RET',     ts: '2026-05-18T22:18:00', qtd: 8,  total:  9420.00, status: 'processado' },
  { id: 'r-006', tipo: 'remessa', filename: 'REM_C6_20260517_002.REM', ts: '2026-05-17T17:30:00', qtd: 6,  total:  4220.00, status: 'enviada' },
];

export default function SheetRemessaRetorno({ remessas = REMESSAS_MOCK, onClose }: Props) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose} role="dialog" aria-modal="true" aria-label="Remessa e retorno CNAB 240">
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[560px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Remessa &amp; Retorno · CNAB 240</div>
            <div className="text-[15px] font-semibold mt-0.5">C6 Bank · Operacional</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" aria-label="Fechar (Esc)">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>

        <div className="px-5 py-3 border-b border-stone-200 grid grid-cols-2 gap-2">
          <Btn variant="primary"><Download className="h-3 w-3" />Gerar remessa do dia</Btn>
          <Btn variant="outline"><Upload className="h-3 w-3" />Importar arquivo retorno</Btn>
        </div>

        <div className="flex-1 overflow-auto">
          {remessas.map(r => (
            <div key={r.id} className="px-5 py-3 border-b border-stone-100 hover:bg-stone-50/60 flex items-center gap-3">
              <span className={cn(
                'w-7 h-7 rounded inline-grid place-items-center',
                r.tipo === 'remessa' ? 'bg-blue-50 text-blue-700' : 'bg-success-soft text-success-fg',
              )}>
                {r.tipo === 'remessa' ? <Upload className="h-3.5 w-3.5" /> : <Download className="h-3.5 w-3.5" />}
              </span>
              <div className="flex-1 min-w-0">
                <div className="text-[12.5px] font-medium font-mono truncate">{r.filename}</div>
                <div className="text-[10.5px] text-stone-500 tabular-nums mt-0.5">
                  {fmtDate(r.ts.slice(0, 10))} {r.ts.slice(11, 16)} · {r.qtd} títulos · {brl(r.total)}
                </div>
              </div>
              <Btn variant="ghost" size="xs"><Download className="h-3 w-3" /></Btn>
            </div>
          ))}
        </div>

        <div className="border-t border-stone-200 px-5 py-3 bg-amber-50/60 text-[11px] text-amber-900">
          <div className="flex gap-2">
            <AlertCircle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
            <div><strong>C6:</strong> único driver via CNAB. Inter/Asaas/BCB usam API direta (webhook). Onda 5 ligará processamento automático.</div>
          </div>
        </div>
      </div>
    </div>
  );
}
