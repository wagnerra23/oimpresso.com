/**
 * ColunasModal — onda 6 do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-colunas.jsx`: escolher e reordenar as
 * colunas do grid de itens, incluindo as fiscais. O domínio (catálogo, saneamento,
 * mover, alternar) mora em `colunas-dominio.ts`, provado em
 * `tests/js/colunas-dominio.test.ts` — 21/21.
 *
 * ⚠️ Sem cálculo de valor: decide só o que aparece e em que ordem.
 *
 * Reordenar é por BOTÃO (‹ ›), não por arrastar. Não é simplificação preguiçosa:
 * drag-and-drop sem alternativa de teclado é inacessível, e o `a11y:check` do projeto
 * reprova com razão. Os botões funcionam com mouse, teclado e leitor de tela — e a
 * fonte descreve a intenção ("arrastar e ordenar"), não a técnica.
 */

import { useState } from 'react';

import { Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';

import {
  COLUNAS,
  GRUPOS,
  alternarColuna,
  colunasPadrao,
  definicaoDe,
  moverColuna,
} from './colunas-dominio';
import { Lbl, Pill } from './primitivos';

export default function ColunasModal({
  aberto,
  onFechar,
  ativas,
  onAtivasChange,
}: {
  aberto: boolean;
  onFechar: () => void;
  ativas: string[];
  onAtivasChange: (c: string[]) => void;
}) {
  const [selecionada, setSelecionada] = useState<string | null>(null);

  const mover = (delta: number) => {
    if (!selecionada) return;
    const de = ativas.indexOf(selecionada);
    onAtivasChange(moverColuna(ativas, de, de + delta));
  };

  return (
    <Dialog open={aberto} onOpenChange={(v) => !v && onFechar()}>
      <DialogContent className="max-h-[88vh] sm:max-w-[900px]">
        <DialogHeader>
          <DialogTitle>Colunas do grid ({ativas.length} de {COLUNAS.length})</DialogTitle>
        </DialogHeader>

        <Stack gap={4} className="min-h-0 overflow-auto">
          {/* ─── ordem das ativas ────────────────────────────────────────── */}
          <div>
            <Inline gap={2} align="center" className="mb-2 flex-wrap">
              <Lbl className="mb-0">Ordem das colunas ativas</Lbl>
              <span className="text-[11px] text-muted-foreground">
                selecione uma e use ‹ › — coluna fixa não sai do lugar
              </span>
              <Inline gap={1} align="center" className="ml-auto">
                <Button type="button" variant="outline" size="sm" disabled={!selecionada} onClick={() => mover(-1)}>
                  ‹
                </Button>
                <Button type="button" variant="outline" size="sm" disabled={!selecionada} onClick={() => mover(1)}>
                  ›
                </Button>
              </Inline>
            </Inline>

            <Inline gap={2} align="center" className="flex-wrap rounded-lg border border-border p-2">
              {ativas.map((k) => {
                const def = definicaoDe(k);
                if (!def) return null;
                const ativa = selecionada === k;
                return (
                  <button
                    key={k}
                    type="button"
                    aria-pressed={ativa}
                    onClick={() => setSelecionada(ativa ? null : k)}
                    className={
                      'h-7 rounded-full px-3 text-[11.5px] font-semibold ' +
                      (ativa
                        ? 'border border-primary bg-primary/10 text-primary'
                        : 'border border-border bg-muted text-muted-foreground hover:text-foreground')
                    }
                  >
                    {def.label}
                    {def.fixa && <span className="ml-1 opacity-60">fixa</span>}
                  </button>
                );
              })}
            </Inline>
          </div>

          {/* ─── catálogo por grupo ──────────────────────────────────────── */}
          {GRUPOS.map((g) => {
            const doGrupo = COLUNAS.filter((c) => c.grupo === g.k);
            if (doGrupo.length === 0) return null;
            return (
              <div key={g.k}>
                <Inline gap={2} align="center" className="mb-2">
                  <Lbl className="mb-0">{g.label}</Lbl>
                  <Pill>{doGrupo.filter((c) => ativas.includes(c.k)).length}/{doGrupo.length}</Pill>
                </Inline>
                <Inline gap={3} align="center" className="flex-wrap">
                  {doGrupo.map((c) => (
                    <Inline key={c.k} gap={2} align="center" className="min-w-[200px]">
                      <Checkbox
                        id={`col-${c.k}`}
                        checked={ativas.includes(c.k)}
                        disabled={c.fixa}
                        onCheckedChange={() => onAtivasChange(alternarColuna(ativas, c.k))}
                      />
                      <label
                        htmlFor={`col-${c.k}`}
                        className={'cursor-pointer text-[12.5px] ' + (c.fixa ? 'text-muted-foreground' : '')}
                      >
                        {c.label}
                        {c.fixa && <span className="ml-1 text-[10.5px]">(fixa)</span>}
                      </label>
                    </Inline>
                  ))}
                </Inline>
              </div>
            );
          })}
        </Stack>

        <DialogFooter className="sm:justify-between">
          <Button type="button" variant="outline" onClick={() => onAtivasChange(colunasPadrao())}>
            Restaurar padrão
          </Button>
          <Button type="button" onClick={onFechar}>
            Aplicar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
