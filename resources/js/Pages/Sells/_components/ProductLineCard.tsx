// P0-1 RESPONSIVIDADE — ProductLineCard (componente local da Sells/Create).
//
// Card vertical 1-col pra UMA linha de produto da venda — usado em mobile (<768px).
// Substitui visualmente as 5 colunas horizontais da tabela legacy em viewports estreitos
// (Larissa atende celular: sem este card precisa de scroll horizontal pra ver "Preço").
//
// Quando usar:
//   - Wrap em <div className="md:hidden"> renderizando ProductLineCard pra cada produto
//   - Manter tabela atual em <div className="hidden md:block"> (>=768px)
//
// Dossier mãe: memory/sessions/2026-05-17-tela-venda-arte-responsivo.md §P0-1
// Referências: Shopify POS line item card · Toast Go 3 handheld 1-col
// Touch targets: 44px (Apple HIG) / 48dp (Material) — `h-11`
//
// Pattern espelha PaymentRow (mesmo nível, mesmo estilo Props + onChange + onRemove).
// Não vira shared ainda (R-DS-001 — extrair quando 2ª tela usar).

import { Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

// Shape espelha o item de `useForm({ products: [...] })` em Create.tsx:163-171.
// Exportado pra Create.tsx reusar `Array<ProductRow>` quando integrar.
export interface ProductRow {
  product_id: number;
  variation_id: number | null;
  name: string;
  sku: string;
  quantity: number;
  unit_price: number;
  discount: number;
  // Campos opcionais que aparecem em ProductSearchResult (display only).
  qty_available?: number;
  unit?: string;
}

interface Props {
  product: ProductRow;
  index: number;
  permissions: {
    editDiscount: boolean;
    editPrice: boolean;
    maxDiscount?: number | null;
  };
  /** TODO[Wagner integration]: `taxes` ainda não é usado por linha
   *  (shape useForm atual em Create.tsx:163-171 NÃO tem `tax_rate_id` por produto —
   *  tax_rate_id é por venda inteira em `data.tax_rate_id`).
   *  Mantemos a prop pra alinhar com assinatura definida na task; quando virar
   *  per-line, expor via <details> "Mais". */
  taxes: Record<number, string>;
  onChange: (
    index: number,
    field: keyof ProductRow,
    value: string | number | null,
  ) => void;
  onRemove: (index: number) => void;
  /** Erro de validação opcional vindo do parent (`errors[`products.${idx}.field`]`).
   *  role="alert" pra screen reader. */
  error?: string;
}

function formatBRL(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function ProductLineCard({
  product,
  index,
  permissions,
  taxes: _taxes,
  onChange,
  onRemove,
  error,
}: Props) {
  // Subtotal calculado igual `subtotalProdutos` reduce em Create.tsx:225-232.
  const lineSubtotal = Math.max(
    product.quantity * product.unit_price - product.discount,
    0,
  );

  return (
    <div
      role="group"
      aria-label={`Produto ${index + 1}: ${product.name}`}
      className="rounded-lg border border-border bg-card p-4 space-y-3"
    >
      {/* Header: nome + delete touch-target 44px */}
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="text-sm font-semibold text-foreground truncate">
            {product.name}
          </div>
          <div className="text-xs text-muted-foreground flex flex-wrap gap-x-2">
            <span>SKU {product.sku}</span>
            {product.qty_available !== undefined && (
              <span>est. {product.qty_available}</span>
            )}
          </div>
        </div>
        <Button
          type="button"
          variant="ghost"
          onClick={() => onRemove(index)}
          aria-label={`Remover produto ${product.name}`}
          className="h-11 w-11 shrink-0 text-muted-foreground hover:text-destructive"
        >
          <Trash2 className="h-5 w-5" />
        </Button>
      </div>

      {/* Linha 2 cols: Qtd + Preço unit */}
      <div className="grid grid-cols-2 gap-2">
        <div className="space-y-1.5">
          <Label htmlFor={`product-${index}-quantity`} className="text-xs">
            Quantidade
          </Label>
          <Input
            id={`product-${index}-quantity`}
            type="number"
            inputMode="decimal"
            min="0"
            step="1"
            value={product.quantity}
            onChange={(e) =>
              onChange(index, 'quantity', Number(e.target.value))
            }
            aria-label={`Quantidade de ${product.name}`}
            className="h-11 tabular-nums"
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor={`product-${index}-unit_price`} className="text-xs">
            Preço unit.
          </Label>
          <Input
            id={`product-${index}-unit_price`}
            type="number"
            inputMode="decimal"
            min="0"
            step="0.01"
            value={product.unit_price}
            onChange={(e) =>
              onChange(index, 'unit_price', Number(e.target.value))
            }
            disabled={!permissions.editPrice}
            aria-label={`Preço unitário de ${product.name}`}
            className="h-11 tabular-nums"
          />
        </div>
      </div>

      {/* Linha desconto inline — só renderiza se editDiscount */}
      {permissions.editDiscount && (
        <div className="space-y-1.5">
          <Label htmlFor={`product-${index}-discount`} className="text-xs">
            Desconto
          </Label>
          <Input
            id={`product-${index}-discount`}
            type="number"
            inputMode="decimal"
            min="0"
            step="0.01"
            value={product.discount}
            onChange={(e) =>
              onChange(index, 'discount', Number(e.target.value))
            }
            aria-label={`Desconto em ${product.name}`}
            className="h-11 tabular-nums"
          />
        </div>
      )}

      {/* Subtotal — label + valor à direita */}
      <div className="flex items-center justify-between border-t border-border pt-3">
        <span className="text-xs text-muted-foreground">Subtotal</span>
        <span className="text-sm font-semibold tabular-nums text-foreground">
          {formatBRL(lineSubtotal)}
        </span>
      </div>

      {/* Erro de validação por linha (parent passa via prop). */}
      {error && (
        <p className="text-xs text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
