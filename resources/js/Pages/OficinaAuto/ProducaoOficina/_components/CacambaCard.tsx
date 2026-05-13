// Card individual de caçamba no Kanban Produção · Oficina — V2 RICA.
// Espelha 1:1 protótipo Cowork canon `prototipo-ui/prototipos/producao-oficina/visual-source.html`
// adaptado pra caçambas (5-6 linhas: OS# + chegou + plate + cliente + endereço + obs +
// rodapé com atendente avatar + dias · diárias + valor R$).
//
// Variantes:
//  - default: bg cinza claro (slate-50), border slate
//  - aprovacao (aguardando): bg amber leve + border-2 amber + strip rose-300 topo + ponto pulse
//  - pronta: opacidade reduzida + check verde (caçamba acabou manut., voltando pátio)
//
// Lição PR #717 — useMemo/useCallback nos handlers descendentes pra evitar re-render loop
// quando hierarquia profunda (Index → Coluna → Card × N).

import { memo } from 'react';
import { Clock, MapPin } from 'lucide-react';
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
  os_number: number | null;
  rental_created_at: string | null;
  rental_notes: string | null;
  cliente_nome: string | null;
  delivery_address: string | null;
  entered_at: string | null;
  expected_return: string | null;
  dias_locacao: number | null;
  daily_rate: number | null;
  valor_receber: number | null;
  atendente_nome: string | null;
  atendente_iniciais: string | null;
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

/** "desde 12/05 14h" — espelha visual-source.html "chegou 08:14" */
function formatDesde(iso: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const hh = String(d.getHours()).padStart(2, '0');
  return `desde ${dd}/${mm} ${hh}h`;
}

function truncate(s: string | null, max = 38): string {
  if (!s) return '—';
  return s.length > max ? s.slice(0, max - 1) + '…' : s;
}

function CacambaCardImpl({ cacamba, variant, onClick }: Props) {
  const isAprovacao = variant === 'aguardando';
  const isPronta = variant === 'pronta';
  const isAtiva = variant === 'locada' || variant === 'aguardando';

  const cardClasses = isAprovacao
    ? 'bg-amber-50 border-2 border-rose-300 hover:border-rose-500'
    : 'bg-slate-50 border border-slate-200 hover:border-slate-400';

  const opacityClass = isPronta ? 'opacity-90' : '';

  const desde = formatDesde(cacamba.rental_created_at);
  const osLabel = cacamba.os_number ? `OS #${cacamba.os_number}` : null;

  return (
    <article
      className={`${cardClasses} ${opacityClass} relative rounded p-3 cursor-pointer transition-colors`}
      onClick={() => onClick(cacamba)}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick(cacamba);
        }
      }}
      role="button"
      tabIndex={0}
      aria-label={`Caçamba ${cacamba.vehicle_number ?? cacamba.plate}${cacamba.cliente_nome ? ` — ${cacamba.cliente_nome}` : ''}`}
    >
      {/* Strip rose top — sinal urgência (espelha .ofc-card-urgent-strip) */}
      {isAprovacao && (
        <span
          className="absolute top-0 left-0 right-0 h-[2px] bg-rose-500 rounded-t"
          aria-hidden="true"
        />
      )}
      {/* Ponto pulse rose canto superior direito (overdue) */}
      {isAprovacao && (
        <span
          className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-rose-500 animate-pulse"
          aria-hidden="true"
          title="Caçamba com prazo vencido"
        />
      )}

      {/* Linha 1: OS # + timestamp "desde dd/mm hh" */}
      <div className="flex items-center justify-between mb-1.5 text-[10px]">
        <span className="font-mono text-slate-500">
          {osLabel ?? <span className="text-slate-400 italic">sem OS</span>}
        </span>
        {desde && <span className="text-slate-400 tracking-tight">{desde}</span>}
      </div>

      {/* Linha 2: placa Mercosul + capacidade badge */}
      <div className="flex items-start justify-between mb-2 gap-2">
        <div className="flex flex-col gap-0.5 min-w-0">
          <MercosulPlate plate={cacamba.plate} size="sm" />
          {cacamba.vehicle_number && cacamba.vehicle_number !== cacamba.plate ? (
            <span className="text-[10px] text-slate-500 tracking-wide font-mono">
              {cacamba.vehicle_number}
            </span>
          ) : null}
        </div>
        {isAprovacao ? (
          <span className="text-[10px] px-1.5 py-0.5 bg-rose-500 text-white rounded font-semibold uppercase tracking-wide whitespace-nowrap">
            Recolher
          </span>
        ) : isPronta ? (
          <span className="text-xs text-emerald-700 whitespace-nowrap font-medium">
            ✓ pronta
          </span>
        ) : cacamba.capacity_m3 != null ? (
          <span
            className={
              'text-xs px-1.5 py-0.5 rounded whitespace-nowrap font-medium ' +
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

      {/* Linha 3: cliente atual (font-medium destaque) */}
      {cacamba.cliente_nome ? (
        <div
          className="text-[13px] font-medium text-slate-900 mb-1 truncate"
          title={cacamba.cliente_nome}
        >
          {truncate(cacamba.cliente_nome, 38)}
        </div>
      ) : variant === 'manutencao' ? (
        <div className="text-xs italic text-slate-500 mb-1">— em oficina</div>
      ) : variant === 'disponivel' ? (
        <div className="text-xs italic text-slate-500 mb-1">— pátio matriz</div>
      ) : (
        <div className="text-xs text-slate-400 mb-1">—</div>
      )}

      {/* Linha 4: endereço delivery (com pin icon) */}
      {cacamba.delivery_address ? (
        <div className="flex items-start gap-1 text-[11px] text-slate-500 mb-1">
          <MapPin size={10} className="mt-0.5 flex-shrink-0 text-slate-400" />
          <span className="truncate" title={cacamba.delivery_address}>
            {truncate(cacamba.delivery_address, 36)}
          </span>
        </div>
      ) : null}

      {/* Linha 5: observação (italic, se preenchida) — espelha .ofc-symptom */}
      {cacamba.rental_notes ? (
        <p
          className="text-[11px] italic text-slate-500 leading-snug mb-1 line-clamp-2"
          title={cacamba.rental_notes}
        >
          {cacamba.rental_notes}
        </p>
      ) : null}

      {/* Linha 6 — rodapé: avatar atendente + dias · diárias  |  valor R$ */}
      {isAtiva && cacamba.dias_locacao != null ? (
        <div
          className={
            'mt-2 pt-2 border-t flex items-center justify-between text-[11px] gap-2 ' +
            (isAprovacao ? 'border-rose-200' : 'border-slate-200')
          }
        >
          <div className="flex items-center gap-1.5 min-w-0">
            {cacamba.atendente_iniciais ? (
              <span
                className="inline-flex items-center justify-center w-[18px] h-[18px] rounded-full bg-slate-200 text-slate-700 text-[9px] font-semibold flex-shrink-0"
                title={cacamba.atendente_nome ?? ''}
                aria-label={`Atendente ${cacamba.atendente_nome ?? ''}`}
              >
                {cacamba.atendente_iniciais}
              </span>
            ) : null}
            <span className="text-slate-600 truncate">
              {cacamba.atendente_nome
                ? truncate(cacamba.atendente_nome.split(' ')[0], 12)
                : <span className="text-slate-400 italic">sem atendente</span>}
            </span>
            <span className="text-slate-300">·</span>
            <span className="text-slate-600 tabular-nums whitespace-nowrap">
              {cacamba.dias_locacao}{' '}
              {cacamba.dias_locacao === 1 ? 'diária' : 'diárias'}
            </span>
          </div>
          {cacamba.valor_receber != null && cacamba.valor_receber > 0 ? (
            <span
              className={
                'font-semibold tabular-nums whitespace-nowrap ' +
                (isAprovacao ? 'text-rose-700' : 'text-emerald-700')
              }
            >
              {formatBRL(cacamba.valor_receber)}
            </span>
          ) : null}
        </div>
      ) : variant === 'pronta' ? (
        <div className="mt-2 pt-2 border-t border-slate-200 text-[11px] text-slate-500 flex items-center gap-1">
          <Clock size={10} className="flex-shrink-0" />
          Aguardando voltar ao pátio
        </div>
      ) : variant === 'manutencao' ? (
        <div className="mt-2 pt-2 border-t border-slate-200 text-[11px] text-slate-500">
          Em diagnóstico
          {cacamba.entered_at ? (
            <span className="text-slate-400"> · {relativeDays(cacamba.entered_at)}</span>
          ) : null}
        </div>
      ) : null}
    </article>
  );
}

// memo — re-render só quando props efetivos mudam (lição PR #717)
export default memo(CacambaCardImpl);
