// US-OFICINA-OS-LINK — Botão "Criar OS" no drawer SaleSheet.
//
// Suporta os 2 modos canônicos via DropdownMenu (UX previsível, sem modal extra):
//   - "Auto (padrão do business)" — lê business.os_default_per_line
//   - "1 OS pra venda toda"       — modo single (caso Martinho/caçambas)
//   - "1 OS por produto"          — modo per_line (caso ComunicacaoVisual/gráfica)
//
// Por que DropdownMenu vs Modal:
//   - 3 opções simples, 1 clique → ação. Modal seria over-engineer.
//   - Pattern alinha com FsmActionPanel (botões inline no drawer).

import { useState } from 'react';
import { CheckCircle2, ChevronDown, Loader2, Wrench } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

interface Props {
  transactionId: number;
  hasExistingOs?: boolean;
  onCreated?: () => void;
}

type Mode = 'auto' | 'single' | 'per_line';

const MODE_LABEL: Record<Mode, string> = {
  auto: 'Auto (padrão do business)',
  single: '1 OS pra venda toda',
  per_line: '1 OS por produto',
};

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

export default function CriarOsButton({
  transactionId,
  hasExistingOs = false,
  onCreated,
}: Props) {
  const [submitting, setSubmitting] = useState(false);

  const handleCreate = async (mode: Mode) => {
    if (submitting) return;
    setSubmitting(true);

    try {
      const res = await fetch(`/sells/${transactionId}/create-os`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mode }),
      });

      const json = await res.json();

      if (!res.ok || !json.success) {
        toast.error(json.msg ?? json.message ?? 'Falha ao criar OS.');
        return;
      }

      const created = json.created_count ?? 0;
      const existing = json.existing_count ?? 0;

      if (created > 0) {
        toast.success(json.message ?? `${created} OS criada(s).`);
      } else if (existing > 0) {
        toast.info(json.message ?? 'OS já existente — nenhuma criada.');
      } else {
        toast.warning(json.message ?? 'Nenhuma OS criada.');
      }

      onCreated?.();
    } catch (err) {
      toast.error(`Erro: ${(err as Error)?.message ?? err}`);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={submitting}
          className="h-7 px-2 text-xs"
        >
          {submitting ? (
            <Loader2 size={12} className="mr-1 animate-spin" />
          ) : (
            <Wrench size={12} className="mr-1" />
          )}
          {hasExistingOs ? 'Criar OS adicional' : 'Criar OS'}
          <ChevronDown size={11} className="ml-1 opacity-60" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel className="text-xs text-muted-foreground">
          Como criar a OS?
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onSelect={() => void handleCreate('auto')}
          disabled={submitting}
          className="text-sm"
        >
          <CheckCircle2 size={12} className="mr-2 text-emerald-600" />
          {MODE_LABEL.auto}
        </DropdownMenuItem>
        <DropdownMenuItem
          onSelect={() => void handleCreate('single')}
          disabled={submitting}
          className="text-sm"
        >
          {MODE_LABEL.single}
        </DropdownMenuItem>
        <DropdownMenuItem
          onSelect={() => void handleCreate('per_line')}
          disabled={submitting}
          className="text-sm"
        >
          {MODE_LABEL.per_line}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
