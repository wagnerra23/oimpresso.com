// Seção recolhível "Mais opções" do formulário de produto (Novo e Editar).
// Lembra se ficou aberta, por tela, no localStorage (`chaveAberto`).

import { useState } from 'react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import type { CamposProduto, DefinirCampo, OpcoesProduto } from './tipos';
import { opcoesDe } from './tipos';

interface Props {
  data: CamposProduto;
  definir: DefinirCampo;
  opcoes: OpcoesProduto;
  /** Chave do localStorage que lembra se a seção ficou aberta. */
  chaveAberto: string;
  /** Só no Novo, e só quando a empresa usa validade. */
  mostrarValidade?: boolean;
}

export function BlocoMaisOpcoes({ data, definir, opcoes, chaveAberto, mostrarValidade }: Props) {
  const [aberto, setAberto] = useState<boolean>(() => {
    try {
      return localStorage.getItem(chaveAberto) === '1';
    } catch {
      return false;
    }
  });

  const lembrar = (proximo: boolean) => {
    setAberto(proximo);
    try {
      localStorage.setItem(chaveAberto, proximo ? '1' : '0');
    } catch {
      // ignora
    }
  };

  return (
    <details
      open={aberto}
      onToggle={(e) => lembrar(e.currentTarget.open)}
      className="rounded-md bg-white border border-stone-200"
    >
      <summary className="px-6 py-3 cursor-pointer text-[14px] font-medium select-none">
        Mais opções
      </summary>
      <div className="px-6 pb-4 grid grid-cols-2 gap-4">
        <div>
          <Label htmlFor="weight">Peso</Label>
          <Input
            id="weight"
            type="number"
            step="0.001"
            value={String(data.weight ?? '')}
            onChange={(e) => definir('weight', e.target.value)}
            className="tabular-nums"
          />
        </div>
        <div className="col-span-2">
          <Label htmlFor="product_description">Descrição</Label>
          <Textarea
            id="product_description"
            rows={3}
            value={data.product_description ?? ''}
            onChange={(e) => definir('product_description', e.target.value)}
          />
        </div>
        <div>
          <Label htmlFor="product_custom_field1">Campo personalizado 1</Label>
          <Input
            id="product_custom_field1"
            value={data.product_custom_field1}
            onChange={(e) => definir('product_custom_field1', e.target.value)}
          />
        </div>
        <div>
          <Label htmlFor="product_custom_field2">Campo personalizado 2</Label>
          <Input
            id="product_custom_field2"
            value={data.product_custom_field2}
            onChange={(e) => definir('product_custom_field2', e.target.value)}
          />
        </div>
        {mostrarValidade && (
          <div>
            <Label htmlFor="expiry_period">Validade</Label>
            <Input
              id="expiry_period"
              type="number"
              value={data.expiry_period}
              onChange={(e) => definir('expiry_period', e.target.value)}
            />
          </div>
        )}
        {opcoes.warranties && Object.keys(opcoes.warranties).length > 0 && (
          <div>
            <Label htmlFor="warranty_id">Garantia</Label>
            <Select
              value={String(data.warranty_id ?? '')}
              onValueChange={(v) => definir('warranty_id', Number(v))}
            >
              <SelectTrigger id="warranty_id">
                <SelectValue placeholder="Nenhuma" />
              </SelectTrigger>
              <SelectContent>
                {opcoesDe(opcoes.warranties).map(([k, v]) => (
                  <SelectItem key={k} value={k}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
      </div>
    </details>
  );
}
