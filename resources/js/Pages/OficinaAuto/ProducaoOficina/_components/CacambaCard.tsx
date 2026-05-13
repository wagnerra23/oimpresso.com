// Card individual de caçamba no Kanban Produção · Oficina.
// Espelha 1:1 protótipo Cowork canon (prototipo-ui/prototipos/producao-oficina/F1.html)
// adaptado pra caçambas (plate mono + capacidade + cliente + endereço + footer).
//
// Variantes:
//  - default: bg cinza claro (ink-50), border cinza
//  - aprovacao: bg amber leve (accent-50), border amber, badge "Recolher" — coluna "Aguardando recolhimento"
//  - pronta: opacidade reduzida + check verde (caçamba acabou manut., voltando pátio)
//
// Lição PR #717 — useMemo/useCallback nos handlers descendentes pra evitar re-render loop
// quando hierarquia profunda (Index → Coluna → Card × N).

import { memo } from 'react';
import MercosulPlate from './MercosulPlate';

export type CacambaStatus = 'disponivel' | 'locada' | 'aguardando' | 'manutencao' | 'pronta';

export interface CacambaCardData {
  id: number;
  plate: string;
  vehicle_number: string | null;
  capacity_m3: number | null;
  current_status: string;
  is_overdue: boolean;
  current_rental_id: number | null;
  cliente_nome: string | null;
  delivery_address: string | null;
  entered_at: string | null;
  expected_return: string | null;
  dias_locacao: number | null;
  valor_receber: number | null;
}

interface Props {
  cacamba: CacambaCardData;
  variant: CacambaStatus;
  onClick: (cacamba: CacambaCardData) => void;
}

const formatBRL = (value: number | null | undefined) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value ?? 0));

function relativeDays(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  const diffMs = Date.now() - d.getTime();
  const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
  if (days === 0) return 'hoje';
  if (days === 1) return 'há 1 dia';
  if (days < 30) return `há ${days} dias`;
  const months = Math.floor(days / 30);
  return months === 1 ? 'há 1 mês' : `há ${months} meses`;
}

function truncate(s: string | null, max = 38): string {
  if (!s) return '—';
  return s.length > max ? s.slice(0, max - 1) + '…' : s;
}

function CacambaCardImpl({ cacamba, variant, onClick }: Props) {
  const isAprovacao = variant === 'aguardando';
  const isPronta = variant === 'pronta';

  const cardClasses = isAprovacao
    ? 'bg-amber-50 border-2 border-amber-200 hover:border-amber-500'
    : 'bg-slate-50 border border-slate-200 hover:border-slate-400';

  const opacityClass = isPronta ? 'opacity-90' : '';

  const headerLabel = cacamba.vehicle_number ?? cacamba.plate;
  const subPlate = cacamba.vehicle_number && cacamba.plate !== cacamba.vehicle_number
    ? cacamba.plate
    : null;

  return (
    <article
      className={`${cardClasses} ${opacityClass} rounded p-3 cursor-pointer transition-colors`}
      onClick={() => onClick(cacamba)}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick(cacamba);
        }
      }}
      role="button"
      tabIndex={0}
      aria-label={`Caçamba ${headerLabel}${cacamba.cliente_nome ? ` — ${cacamba.cliente_nome}` : ''}`}
    >
      {/* Linha 1: placa Mercosul visual + badge capacidade ou status */}
      <div className="flex items-start justify-between mb-2 gap-2">
        <div className="flex flex-col gap-1">
          <MercosulPlate plate={cacamba.plate} size="sm" />
          {subPlate || (cacamba.vehicle_number && cacamba.vehicle_number !== cacamba.plate) ? (
            <span className="text-[10px] text-slate-500 tracking-wide">{cacamba.vehicle_number}</span>
          ) : null}
        </div>
        {isAprovacao ? (
          <span className="text-[10px] px-1.5 py-0.5 bg-amber-500 text-white rounded font-medium uppercase tracking-wide whitespace-nowrap">
            Recolher
          </span>
        ) : isPronta ? (
          <span className="text-xs text-emerald-700 whitespace-nowrap">✓ pronta</span>
        ) : cacamba.capacity_m3 != null ? (
          <span
            className={
              'text-xs px-1.5 py-0.5 rounded whitespace-nowrap ' +
              (variant === 'locada'
                ? 'bg-blue-50 text-blue-700'
                : variant === 'manutencao'
                  ? 'bg-violet-50 text-violet-700'
                  : 'bg-slate-100 text-slate-600')
            }
          >
            {Number(cacamba.capacity_m3)}m³
          </span>
        ) : null}
      </div>

      {/* Linha 2: cliente atual */}
      {cacamba.cliente_nome ? (
        <div className="text-xs text-slate-700 mb-1 truncate" title={cacamba.cliente_nome}>
          {truncate(cacamba.cliente_nome, 40)}
        </div>
      ) : variant === 'manutencao' ? (
        <div className="text-xs italic text-slate-500 mb-1">— em oficina</div>
      ) : variant === 'disponivel' ? (
        <div className="text-xs italic text-slate-500 mb-1">— pátio matriz</div>
      ) : (
        <div className="text-xs text-slate-400 mb-1">—</div>
      )}

      {/* Linha 3: endereço delivery */}
      {cacamba.delivery_address ? (
        <div className="text-xs text-slate-500 truncate" title={cacamba.delivery_address}>
          {truncate(cacamba.delivery_address, 38)}
        </div>
      ) : null}

      {/* Linha 4 — rodapé: dias + valor (só pra locadas/aguardando) */}
      {(variant === 'locada' || variant === 'aguardando') && cacamba.dias_locacao != null ? (
        <div
          className={
            'mt-2 pt-2 border-t flex items-center justify-between text-[11px] ' +
            (isAprovacao ? 'border-amber-200 text-amber-700' : 'border-slate-200 text-slate-600')
          }
        >
          <span>
            {relativeDays(cacamba.entered_at)} · {cacamba.dias_locacao}{' '}
            {cacamba.dias_locacao === 1 ? 'diária' : 'diárias'}
          </span>
          {cacamba.valor_receber != null && cacamba.valor_receber > 0 ? (
            <span className={isAprovacao ? 'font-medium' : 'text-emerald-700'}>
              {formatBRL(cacamba.valor_receber)}
            </span>
          ) : null}
        </div>
      ) : variant === 'pronta' ? (
        <div className="mt-2 pt-2 border-t border-slate-200 text-xs text-slate-500">
          Aguardando voltar ao pátio
        </div>
      ) : variant === 'manutencao' ? (
        <div className="mt-2 pt-2 border-t border-slate-200 text-xs text-slate-500">
          Em diagnóstico
        </div>
      ) : null}
    </article>
  );
}

// memo — re-render só quando props efetivos mudam (lição PR #717)
export default memo(CacambaCardImpl);
