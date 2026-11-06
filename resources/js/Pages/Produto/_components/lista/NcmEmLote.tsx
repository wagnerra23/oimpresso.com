// "Informar NCM" — o mesmo NCM em vários produtos selecionados na lista (UC-PIDX-43, pedido da PraMoto,
// 2026-10-27). Aceita com ou sem pontos ("3921.90.90"); vale o NCM com 8 dígitos e diferente de 00000000,
// a regra da emissão e do card de Saúde fiscal. O servidor confere de novo (`bulkUpdateNcm`).

import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';

/** Só os dígitos, se formarem um NCM válido; senão null. */
export function ncmDigitado(texto: string): string | null {
  const d = texto.replace(/[^0-9]/g, '');
  return d.length === 8 && d !== '00000000' ? d : null;
}

interface Props {
  aberto: boolean;
  quantidade: number;
  container?: HTMLElement | null;
  aoFechar: () => void;
  aoConfirmar: (ncm: string) => void;
}

export default function NcmEmLote({ aberto, quantidade, container, aoFechar, aoConfirmar }: Props) {
  const [texto, setTexto] = useState('');
  const ncm = ncmDigitado(texto);
  const mostrarErro = texto.trim() !== '' && ncm === null;

  return (
    <Dialog
      open={aberto}
      onOpenChange={(o) => {
        if (!o) {
          setTexto('');
          aoFechar();
        }
      }}
    >
      <DialogContent container={container}>
        <DialogHeader>
          <DialogTitle>Informar NCM</DialogTitle>
          <DialogDescription>
            {quantidade} produto(s) selecionado(s). Todos passam a ter este NCM; o que tinham antes é trocado.
          </DialogDescription>
        </DialogHeader>
        <div className="pb-fld" data-contract="produto-ncm-em-lote">
          <label htmlFor="ncm-em-lote">NCM</label>
          <Input
            id="ncm-em-lote"
            inputMode="numeric"
            placeholder="0000.00.00"
            value={texto}
            aria-invalid={mostrarErro}
            aria-describedby="ncm-em-lote-ajuda"
            onChange={(e) => setTexto(e.target.value)}
          />
          <span id="ncm-em-lote-ajuda" className={mostrarErro ? 'text-[12px] text-destructive' : 'pb-help'}>
            {mostrarErro ? 'O NCM tem 8 dígitos e não pode ser 0000.00.00.' : 'Os 8 dígitos do NCM, com ou sem pontos.'}
          </span>
        </div>
        <DialogFooter>
          <Button
            variant="ghost"
            onClick={() => {
              setTexto('');
              aoFechar();
            }}
          >
            Cancelar
          </Button>
          <Button
            disabled={ncm === null}
            onClick={() => {
              if (ncm === null) return;
              setTexto('');
              aoConfirmar(ncm);
            }}
          >
            Informar em {quantidade}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
