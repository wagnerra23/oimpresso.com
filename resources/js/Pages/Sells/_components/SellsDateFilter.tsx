// US-SELL-018 — Filtro multi-data com presets Dia/Semana/Mês/Ano + Personalizado.
// Refs: ADR 0136 (Sells: Lista vs Grade Avançada), US-SELL-021 (campo data dropdown).
//
// Composto por 3 partes:
//  1. Segmented control 5 botões (Dia | Semana | Mês | Ano | Personalizado)
//  2. Dropdown "Tipo de data" (7 opções canon — mesmo whitelist do US-SELL-021)
//  3. Popover "Personalizado" com 2 inputs HTML5 type="date" (date_from + date_to)
//
// Estado controlado via props pelo Index.tsx (lift state up). Backend já aceita
// date_from / date_to (US-SELL-018 backend done — ver SellController@inertiaList:915-955).
//
// PT-BR enforce: labels visíveis sempre PT-BR ("Dia", "Semana", "Mês", "Ano",
// "Personalizado", "Tipo de data", "De", "Até").

import { useState } from 'react';
import {
  startOfDay, endOfDay,
  startOfWeek, endOfWeek,
  startOfMonth, endOfMonth,
  startOfYear, endOfYear,
  format as formatDateFn,
} from 'date-fns';
import { CalendarRange, ChevronDown } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

export type DateFilterPreset = 'day' | 'week' | 'month' | 'year' | 'custom' | 'all';

// 7 datas canon (mesma whitelist backend US-SELL-021).
export type DateField =
  | 'transaction_date'
  | 'updated_at'
  | 'nfe_issued_at'
  | 'invoiced_at'
  | 'invoice_sent_at'
  | 'competence_date'
  | 'due_date';

export const DATE_FIELD_LABEL: Record<DateField, string> = {
  transaction_date: 'Emissão',
  updated_at: 'Última alteração',
  nfe_issued_at: 'Emissão NF',
  invoiced_at: 'Faturamento',
  invoice_sent_at: 'Envio do faturamento',
  competence_date: 'Competência',
  due_date: 'Prometido',
};

export const DATE_FIELD_OPTIONS: DateField[] = [
  'transaction_date',
  'updated_at',
  'nfe_issued_at',
  'invoiced_at',
  'invoice_sent_at',
  'competence_date',
  'due_date',
];

interface SellsDateFilterProps {
  preset: DateFilterPreset;
  dateFrom: string; // YYYY-MM-DD ou ''
  dateTo: string; // YYYY-MM-DD ou ''
  dateField: DateField;
  onChange: (next: {
    preset: DateFilterPreset;
    dateFrom: string;
    dateTo: string;
    dateField: DateField;
  }) => void;
}

// Calcula range pra cada preset usando date-fns. Semana começa em
// segunda-feira (weekStartsOn: 1 — convenção BR).
export function computePresetRange(preset: DateFilterPreset, now: Date = new Date()): {
  dateFrom: string;
  dateTo: string;
} {
  if (preset === 'all') {
    return { dateFrom: '', dateTo: '' };
  }
  let from: Date;
  let to: Date;
  switch (preset) {
    case 'day':
      from = startOfDay(now);
      to = endOfDay(now);
      break;
    case 'week':
      from = startOfWeek(now, { weekStartsOn: 1 });
      to = endOfWeek(now, { weekStartsOn: 1 });
      break;
    case 'month':
      from = startOfMonth(now);
      to = endOfMonth(now);
      break;
    case 'year':
      from = startOfYear(now);
      to = endOfYear(now);
      break;
    default:
      return { dateFrom: '', dateTo: '' };
  }
  return {
    dateFrom: formatDateFn(from, 'yyyy-MM-dd'),
    dateTo: formatDateFn(to, 'yyyy-MM-dd'),
  };
}

const PRESET_LABEL: Record<Exclude<DateFilterPreset, 'all'>, string> = {
  day: 'Dia',
  week: 'Semana',
  month: 'Mês',
  year: 'Ano',
  custom: 'Personalizado',
};

const PRESET_ORDER: Array<Exclude<DateFilterPreset, 'all'>> = [
  'day', 'week', 'month', 'year', 'custom',
];

export default function SellsDateFilter({
  preset,
  dateFrom,
  dateTo,
  dateField,
  onChange,
}: SellsDateFilterProps) {
  const [customOpen, setCustomOpen] = useState(false);
  // Buffer local pro popover Personalizado (evita refetch a cada keystroke).
  const [customFrom, setCustomFrom] = useState(dateFrom);
  const [customTo, setCustomTo] = useState(dateTo);

  function handlePresetClick(p: Exclude<DateFilterPreset, 'all'>) {
    if (p === 'custom') {
      setCustomFrom(dateFrom);
      setCustomTo(dateTo);
      setCustomOpen(true);
      return;
    }
    const range = computePresetRange(p);
    onChange({
      preset: p,
      dateFrom: range.dateFrom,
      dateTo: range.dateTo,
      dateField,
    });
  }

  function handleCustomApply() {
    onChange({
      preset: 'custom',
      dateFrom: customFrom,
      dateTo: customTo,
      dateField,
    });
    setCustomOpen(false);
  }

  function handleCustomClear() {
    setCustomFrom('');
    setCustomTo('');
    onChange({
      preset: 'all',
      dateFrom: '',
      dateTo: '',
      dateField,
    });
    setCustomOpen(false);
  }

  function handleDateFieldChange(field: DateField) {
    onChange({
      preset,
      dateFrom,
      dateTo,
      dateField: field,
    });
  }

  return (
    <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Filtro de datas">
      {/* Segmented control 5 presets */}
      <div
        role="group"
        aria-label="Período"
        className="inline-flex items-center rounded-lg border border-border bg-muted/40 p-0.5"
      >
        {PRESET_ORDER.map((p) => {
          const isActive = preset === p;
          if (p === 'custom') {
            // Botão Personalizado abre popover.
            return (
              <Popover key={p} open={customOpen} onOpenChange={setCustomOpen}>
                <PopoverTrigger asChild>
                  <button
                    type="button"
                    onClick={() => handlePresetClick(p)}
                    aria-pressed={isActive}
                    title="Faixa personalizada de datas"
                    className={
                      'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors ' +
                      (isActive
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground')
                    }
                  >
                    <CalendarRange size={13} />
                    {PRESET_LABEL[p]}
                  </button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-72 p-3">
                  <div className="space-y-3">
                    <div className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Faixa personalizada
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                      <label className="flex flex-col gap-1">
                        <span className="text-[11px] text-muted-foreground">De</span>
                        <input
                          type="date"
                          value={customFrom}
                          max={customTo || undefined}
                          onChange={(e) => setCustomFrom(e.target.value)}
                          className="h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                          aria-label="Data inicial"
                        />
                      </label>
                      <label className="flex flex-col gap-1">
                        <span className="text-[11px] text-muted-foreground">Até</span>
                        <input
                          type="date"
                          value={customTo}
                          min={customFrom || undefined}
                          onChange={(e) => setCustomTo(e.target.value)}
                          className="h-8 rounded-md border border-input bg-background px-2 text-xs text-foreground"
                          aria-label="Data final"
                        />
                      </label>
                    </div>
                    <div className="flex items-center justify-between gap-2 pt-1">
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={handleCustomClear}
                        className="h-7 px-2 text-xs"
                      >
                        Limpar
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        onClick={handleCustomApply}
                        disabled={!customFrom && !customTo}
                        className="h-7 px-3 text-xs"
                      >
                        Aplicar
                      </Button>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
            );
          }
          return (
            <button
              key={p}
              type="button"
              onClick={() => handlePresetClick(p)}
              aria-pressed={isActive}
              title={`Filtrar pelo ${PRESET_LABEL[p].toLowerCase()} corrente`}
              className={
                'inline-flex items-center rounded-md px-3 py-1.5 text-xs font-medium transition-colors ' +
                (isActive
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground')
              }
            >
              {PRESET_LABEL[p]}
            </button>
          );
        })}
      </div>

      {/* Dropdown "Tipo de data" — mesma whitelist 7 opções US-SELL-021 */}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <button
            type="button"
            className="inline-flex items-center gap-1 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted transition-colors"
            aria-label={`Tipo de data atual: ${DATE_FIELD_LABEL[dateField]}. Clique pra trocar.`}
            title={`Tipo de data: ${DATE_FIELD_LABEL[dateField]}`}
          >
            <span className="text-[10px] uppercase tracking-wider text-muted-foreground">
              Tipo de data:
            </span>
            <span>{DATE_FIELD_LABEL[dateField]}</span>
            <ChevronDown size={11} className="text-muted-foreground" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" className="w-56">
          {DATE_FIELD_OPTIONS.map((opt) => {
            const isActive = opt === dateField;
            return (
              <DropdownMenuItem
                key={opt}
                onSelect={() => handleDateFieldChange(opt)}
                className={isActive ? 'font-medium text-foreground' : ''}
              >
                {isActive && <span className="mr-1.5 text-primary" aria-hidden>•</span>}
                {!isActive && <span className="mr-1.5 w-2 inline-block" aria-hidden />}
                {DATE_FIELD_LABEL[opt]}
              </DropdownMenuItem>
            );
          })}
        </DropdownMenuContent>
      </DropdownMenu>

      {/* Range exibido (quando custom ativo) */}
      {preset === 'custom' && (dateFrom || dateTo) && (
        <span className="text-[11px] text-muted-foreground tabular-nums">
          {dateFrom || '…'} → {dateTo || '…'}
        </span>
      )}
    </div>
  );
}
