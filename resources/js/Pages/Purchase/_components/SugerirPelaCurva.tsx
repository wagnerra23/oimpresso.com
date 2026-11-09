// US-VEST-031: quem compra digita o total e a grade recebe a proporção vendida (conta do servidor).
// É só sugestão: nada entra na compra até "Adicionar à compra" e "Salvar compra".

import { useEffect, useRef, useState } from 'react';
import { BarChart3 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { FieldError } from '@/Components/ui/field-state';
import type { GradeCol, GradeRow } from './GradeMatrixInput';
import { sugestaoParaGrade, textoDoMotivo, type RespostaDaCurva, type SugestaoNaGrade } from './curvaDeCompra';

// A célula da grade aceita até 9999 (GradeMatrixInput).
const TOTAL_MAXIMO = 9999;

interface Props {
  productId: number;
  rows: GradeRow[];
  cols: GradeCol[];
  cellVariationMap: Record<string, number>;
  onSugerir: (sugestao: SugestaoNaGrade) => void;
}

export default function SugerirPelaCurva({ productId, rows, cols, cellVariationMap, onSugerir }: Props) {
  const [total, setTotal] = useState('');
  const [carregando, setCarregando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const pedido = useRef<AbortController | null>(null);
  useEffect(() => () => pedido.current?.abort(), []);

  const valido = /^\d+$/.test(total) && Number(total) >= 1 && Number(total) <= TOTAL_MAXIMO;

  const sugerir = async () => {
    if (!valido || carregando) return;
    setCarregando(true);
    setErro(null);
    setAviso(null);
    pedido.current = new AbortController();
    try {
      const res = await fetch(`/vestuario/curva-compra?${new URLSearchParams({ product_id: String(productId), total })}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: pedido.current.signal,
      });
      if (!res.ok) throw new Error(String(res.status));
      const r = (await res.json()) as RespostaDaCurva;
      if (r.linhas.length === 0) return setAviso(textoDoMotivo(r.motivo, r.dias));
      const sugestao = sugestaoParaGrade(r.linhas, rows, cols, cellVariationMap);
      if (sugestao.pecasFora > 0) return setErro('A sugestão tem tamanho que esta grade não mostra. Preencha à mão.');
      onSugerir(sugestao);
      setAviso(`Sugestão pelas vendas ${r.base === 'categoria' ? 'da categoria' : 'deste produto'} nos últimos ${r.dias} dias. Confira e ajuste antes de adicionar.`);
    } catch (e) {
      if ((e as Error).name !== 'AbortError') setErro('Não foi possível calcular a sugestão.');
    } finally {
      setCarregando(false);
    }
  };

  return (
    <div className="flex items-center gap-2 flex-wrap">
      <Label htmlFor="curva-total" className="text-[11px] text-muted-foreground">Total de peças</Label>
      <Input
        id="curva-total"
        type="number"
        min={1}
        max={TOTAL_MAXIMO}
        value={total}
        onChange={(e) => setTotal(e.target.value)}
        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); void sugerir(); } }}
        className="h-8 w-20 text-[12.5px] text-right tabular-nums"
      />
      <Button type="button" size="sm" variant="outline" onClick={() => void sugerir()} disabled={!valido || carregando}>
        <BarChart3 className="h-4 w-4 mr-1" /> {carregando ? 'Calculando…' : 'Sugerir pela curva'}
      </Button>
      {aviso && <p role="status" className="text-[12px] text-muted-foreground">{aviso}</p>}
      <FieldError>{erro}</FieldError>
    </div>
  );
}
