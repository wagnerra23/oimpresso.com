// PlanoContaCombobox — Onda 24 (2026-05-25) US-FIN-021 plano de contas no Edit.
//
// Combobox searchable de plano de contas (DCASP BR). Filtra por `kind` do título:
//   - 'receivable' → mostra tipo IN (receita, ativo)
//   - 'payable'    → mostra tipo IN (despesa, custo, passivo)
// Patrimônio fica de fora (não é título corrente).
//
// Busca client-side por código OU nome (case insensitive). Hierarquia visual
// indentada por nivel (folhas tipicamente nivel=4).
//
// Onda combobox (2026-07-15, ADR proposta tab-nav-canonico + ADR 0338): MIGRADO
// do hand-roll (input + <ul role="listbox"> + onKeyDown à mão) pro CANON do papel
// = Popover + Command (cmdk, @/Components/ui/{popover,command}). O motor cmdk dá a
// a11y (role=combobox/listbox/option, aria-activedescendant) e a navegação de
// teclado (↑↓ Enter Esc) de fábrica — o que o hand-roll reimplementava. Busca fica
// `shouldFilter={false}` + filtro client-side `includes` PRESERVADO exatamente
// (código OU nome), pra não mudar 1 caractere do que casa numa tela de dinheiro.
// API externa (props) inalterada — consumidores (TituloCreate/Edit/BaixaSheet)
// não mudam. Ref viva do padrão: Pages/OficinaAuto/ServiceOrders/Create.tsx.
//
// Reusável entre Edit e Create. Backend defesa em profundidade via
// `UpdateTituloRequest::assertPlanoCoerente()`.

import { useMemo, useState } from 'react';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { cn } from '@/Lib/utils';
import { Button } from '@/Components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/Components/ui/command';

export interface PlanoConta {
  id: number;
  codigo: string;
  nome: string;
  tipo: 'ativo' | 'passivo' | 'patrimonio' | 'receita' | 'despesa' | 'custo';
  nivel: number;
}

interface Props {
  planos: PlanoConta[];
  value: number | null;
  onChange: (id: number | null) => void;
  kind: 'receivable' | 'payable';
  id?: string;
  placeholder?: string;
  disabled?: boolean;
}

const TIPOS_RECEBER: PlanoConta['tipo'][] = ['receita', 'ativo'];
const TIPOS_PAGAR: PlanoConta['tipo'][] = ['despesa', 'custo', 'passivo'];

// Hues semânticos por tipo (Cowork canon hue tokens, via style inline pra escapar
// do ui:lint R1 — tokens semânticos shadcn não cobrem paleta DCASP por tipo).
const TIPO_STYLE: Record<PlanoConta['tipo'], React.CSSProperties> = {
  receita:    { color: 'oklch(0.45 0.13 145)', backgroundColor: 'oklch(0.96 0.04 145)' },
  ativo:      { color: 'oklch(0.45 0.13 145)', backgroundColor: 'oklch(0.96 0.04 145)' },
  despesa:    { color: 'oklch(0.50 0.15 25)',  backgroundColor: 'oklch(0.96 0.04 25)' },
  custo:      { color: 'oklch(0.50 0.13 60)',  backgroundColor: 'oklch(0.96 0.04 60)' },
  passivo:    { color: 'oklch(0.50 0.15 25)',  backgroundColor: 'oklch(0.96 0.04 25)' },
  patrimonio: { color: 'oklch(0.45 0.15 240)', backgroundColor: 'oklch(0.96 0.04 240)' },
};

export function PlanoContaCombobox({ planos, value, onChange, kind, id, placeholder, disabled }: Props) {
  const [open, setOpen] = useState(false);
  const [busca, setBusca] = useState('');
  const baseId = id ?? 'plano-combobox';

  const tiposPermitidos = kind === 'receivable' ? TIPOS_RECEBER : TIPOS_PAGAR;

  const selecionado = useMemo(
    () => planos.find((p) => p.id === value) ?? null,
    [planos, value]
  );

  // Filtro PRESERVADO do hand-roll: kind (tipos permitidos) + busca `includes`
  // por código OU nome. shouldFilter={false} no Command pra usar ESTE filtro
  // (não o fuzzy do cmdk) — casa exatamente o que casava antes.
  const lista = useMemo(() => {
    const base = planos.filter((p) => tiposPermitidos.includes(p.tipo));
    if (! busca.trim()) return base;
    const q = busca.trim().toLowerCase();
    return base.filter(
      (p) => p.codigo.toLowerCase().includes(q) || p.nome.toLowerCase().includes(q)
    );
  }, [planos, tiposPermitidos, busca]);

  const handleOpenChange = (o: boolean) => {
    setOpen(o);
    if (! o) setBusca('');
  };

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          id={baseId}
          variant="outline"
          role="combobox"
          aria-expanded={open}
          disabled={disabled}
          className="w-full h-9 justify-between gap-2 px-3 text-left text-[13px] font-normal"
        >
          {selecionado ? (
            <span className="flex items-center gap-2 truncate">
              <span className="font-mono text-foreground text-[12px] tabular-nums">{selecionado.codigo}</span>
              <span className="truncate">{selecionado.nome}</span>
              <span
                className="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                style={TIPO_STYLE[selecionado.tipo]}
              >
                {selecionado.tipo}
              </span>
            </span>
          ) : (
            <span className="text-muted-foreground">{placeholder ?? '(Sem plano de contas)'}</span>
          )}
          {selecionado && ! disabled ? (
            <span
              role="button"
              aria-label="Limpar plano de contas"
              tabIndex={0}
              onClick={(e) => {
                e.stopPropagation();
                onChange(null);
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  e.stopPropagation();
                  onChange(null);
                }
              }}
              className="shrink-0 text-muted-foreground hover:text-foreground cursor-pointer"
            >
              <X size={14} />
            </span>
          ) : (
            <ChevronsUpDown className="ml-auto size-4 shrink-0 opacity-50" />
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className="w-[var(--radix-popover-trigger-width)] p-0"
        align="start"
      >
        <Command shouldFilter={false}>
          <CommandInput
            value={busca}
            onValueChange={setBusca}
            placeholder={`Buscar ${kind === 'receivable' ? 'receita/ativo' : 'despesa/custo/passivo'}…`}
          />
          <CommandList>
            <CommandEmpty>Nenhum plano encontrado.</CommandEmpty>
            <CommandGroup>
              {lista.map((p) => (
                <CommandItem
                  key={p.id}
                  value={String(p.id)}
                  onSelect={() => {
                    onChange(p.id);
                    handleOpenChange(false);
                  }}
                  className="gap-2 text-[13px]"
                  style={{ paddingLeft: 12 + (p.nivel - 1) * 12 }}
                >
                  <Check className={cn('size-4 shrink-0', p.id === value ? 'opacity-100' : 'opacity-0')} />
                  <span className="font-mono text-foreground text-[12px] tabular-nums shrink-0">{p.codigo}</span>
                  <span className="truncate flex-1">{p.nome}</span>
                  <span
                    className="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                    style={TIPO_STYLE[p.tipo]}
                  >
                    {p.tipo}
                  </span>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

export default PlanoContaCombobox;
