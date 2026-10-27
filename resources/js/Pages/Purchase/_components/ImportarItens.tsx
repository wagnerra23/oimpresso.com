// ImportarItens — botão "Importar planilha" do card de itens da Nova compra.
// Porta o modal de importação da Blade (purchase/partials/import_purchase_products_modal):
// mesmo modelo de planilha (public/files/import_purchase_products_template.xls), lido pelo
// PurchaseController@importarItens, que devolve as linhas prontas. A tela só acrescenta.

import { useRef, useState } from 'react';
import { Button } from '@/Components/ui/button';

export interface LinhaImportada {
  product_name: string;
  product_id: number;
  variation_id: number;
  unit_id: number | null;
  unit_name: string;
  quantity: number;
  pp_without_discount: number;
  discount_percent: number;
  item_tax: number;
  tax_id: number | null;
  purchase_price: number;
  purchase_price_inc_tax: number;
}

interface Props {
  locationId: string;
  onLinhas: (linhas: LinhaImportada[]) => void;
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function ImportarItens({ locationId, onLinhas }: Props) {
  const entrada = useRef<HTMLInputElement>(null);
  const [enviando, setEnviando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const enviar = async (arquivo: File) => {
    setErro(null);
    setEnviando(true);
    try {
      const corpo = new FormData();
      corpo.append('file', arquivo);
      corpo.append('location_id', locationId);
      const res = await fetch('/purchases/importar-itens', {
        method: 'POST',
        body: corpo,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      });
      const dados = (await res.json().catch(() => null)) as { linhas?: LinhaImportada[]; erro?: string | null; message?: string; errors?: Record<string, string[]> } | null;
      if (!res.ok || !dados) {
        setErro(dados?.erro ?? dados?.errors?.file?.[0] ?? dados?.message ?? 'Não foi possível importar a planilha.');
        return;
      }
      onLinhas(dados.linhas ?? []);
    } catch {
      setErro('Não foi possível importar a planilha.');
    } finally {
      setEnviando(false);
      if (entrada.current) entrada.current.value = '';
    }
  };

  return (
    <div className="flex flex-wrap items-center gap-2" data-contract="purchase-importar-itens">
      <input
        ref={entrada}
        type="file"
        accept=".xls,.xlsx,.csv"
        className="hidden"
        aria-label="Planilha de itens"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) void enviar(f);
        }}
      />
      <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => entrada.current?.click()}>
        {enviando ? 'Importando…' : 'Importar planilha'}
      </Button>
      <a href="/files/import_purchase_products_template.xls" download className="text-[12px] text-primary underline-offset-2 hover:underline">
        Baixar modelo
      </a>
      {erro && <p role="alert" className="w-full text-[12px] text-destructive">{erro}</p>}
    </div>
  );
}
