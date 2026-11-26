/**
 * Janela do botão "Colunas" da lista de vendas (decisão [W] 2026-11-25, item 08 da conferência
 * lista × protótipo). Regra das colunas em `colunasLista.ts`, provada em
 * `tests/js/sells-colunas-lista.test.ts`.
 *
 * Reordenar é por botão (↑ ↓), não por arrastar: funciona com teclado e leitor de tela, como no
 * `ColunasModal` da Venda V3.
 *
 * Monta dentro do `.cockpit` (prop `container`), senão os tokens do DS não chegam ao portal
 * (lápide §5 2026-10-19).
 */
import { ArrowDown, ArrowUp } from 'lucide-react';

import { Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';

import { useCockpit } from './v3/cockpit-portal';
import type { ColumnId } from './SellsTabelaUnificada';
import { COL_HEADERS } from './colunasRotulos';
import { FIXAS, alternar, foraDaLista, mover } from './colunasLista';

const VISAO_ROTULO = { operacional: 'Operacional', financeira: 'Financeira', producao: 'Produção' } as const;

const rotulo = (id: ColumnId) => (id === 'check' ? 'Seleção' : COL_HEADERS[id].label);

export default function ColunasListaModal({
  aberto,
  onFechar,
  visao,
  ativas,
  comissao,
  onAtivasChange,
  onRestaurar,
}: {
  aberto: boolean;
  onFechar: () => void;
  visao: keyof typeof VISAO_ROTULO;
  ativas: ColumnId[];
  comissao: boolean;
  onAtivasChange: (c: ColumnId[]) => void;
  onRestaurar: () => void;
}) {
  const cockpit = useCockpit();
  const fora = foraDaLista(ativas, comissao);

  return (
    <Dialog open={aberto} onOpenChange={(v) => !v && onFechar()}>
      <DialogContent container={cockpit} className="max-h-[88vh] sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>Colunas da lista de vendas</DialogTitle>
          <DialogDescription>
            Visão {VISAO_ROTULO[visao]} · {ativas.length} colunas. Cada visão guarda a própria
            escolha neste navegador, só para esta empresa.
          </DialogDescription>
        </DialogHeader>

        <Stack gap={4} className="min-h-0 overflow-auto">
          <Stack gap={1} role="list" aria-label="Colunas na lista, na ordem em que aparecem">
            {ativas.map((id, i) => {
              const fixa = FIXAS.includes(id);
              return (
                <Inline key={id} gap={2} align="center" role="listitem" className="rounded-md px-2 py-1">
                  <Checkbox
                    checked
                    disabled={fixa}
                    onCheckedChange={() => onAtivasChange(alternar(ativas, id))}
                    aria-label={`Mostrar ${rotulo(id)}`}
                  />
                  <span className="flex-1 text-sm">
                    {rotulo(id)}
                    {fixa && <span className="ml-2 text-xs text-muted-foreground">fixa</span>}
                  </span>
                  {!fixa && (
                    <>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        disabled={i <= FIXAS.length}
                        onClick={() => onAtivasChange(mover(ativas, id, -1))}
                        aria-label={`Subir ${rotulo(id)}`}
                      >
                        <ArrowUp />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        disabled={i === ativas.length - 1}
                        onClick={() => onAtivasChange(mover(ativas, id, 1))}
                        aria-label={`Descer ${rotulo(id)}`}
                      >
                        <ArrowDown />
                      </Button>
                    </>
                  )}
                </Inline>
              );
            })}
          </Stack>

          {fora.length > 0 && (
            <Stack gap={1}>
              <span className="text-xs font-medium text-muted-foreground">Fora da lista</span>
              {fora.map((id) => (
                <Inline key={id} gap={2} align="center" className="rounded-md px-2 py-1">
                  <Checkbox
                    checked={false}
                    onCheckedChange={() => onAtivasChange(alternar(ativas, id))}
                    aria-label={`Mostrar ${rotulo(id)}`}
                  />
                  <span className="flex-1 text-sm">{rotulo(id)}</span>
                </Inline>
              ))}
            </Stack>
          )}
        </Stack>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onRestaurar}>
            Restaurar padrão
          </Button>
          <Button type="button" onClick={onFechar}>
            Fechar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
