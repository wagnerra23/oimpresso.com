// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/StockHistory.tsx
// MWART (ADR 0104) + divergência declarada blueprint Cowork (ADR 0149)
// Refs: RUNBOOK-produto-stock-history.md · StockHistory.charter.md
// Agent W2-C · 2026-05-15

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft, Filter } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

interface VariationLite {
  id: number;
  name: string;
  subSku: string;
}

interface OptionMap {
  [id: number]: string;
}

export interface ProdutoStockHistoryPageProps {
  product: {
    id: number;
    name: string;
    sku: string;
    type: 'single' | 'variable' | 'combo';
    unit: string | null;
  };
  variations: VariationLite[];
  businessLocations: OptionMap;
  permissions: {
    view: boolean;
  };
}

const HISTORY_VARIATION_KEY = 'oimpresso.produto.stockHistory.variation';
const HISTORY_LOCATION_KEY = 'oimpresso.produto.stockHistory.location';

function ProdutoStockHistory(props: ProdutoStockHistoryPageProps) {
  const { product, variations, businessLocations } = props;

  const [variationId, setVariationId] = useState<string>(() => {
    try {
      const stored = localStorage.getItem(HISTORY_VARIATION_KEY);
      return stored ?? String(variations[0]?.id ?? '');
    } catch {
      return String(variations[0]?.id ?? '');
    }
  });

  const [locationId, setLocationId] = useState<string>(() => {
    try {
      const stored = localStorage.getItem(HISTORY_LOCATION_KEY);
      const first = Object.keys(businessLocations)[0] ?? '';
      return stored ?? first;
    } catch {
      return Object.keys(businessLocations)[0] ?? '';
    }
  });

  const persistVariation = (v: string) => {
    setVariationId(v);
    try {
      localStorage.setItem(HISTORY_VARIATION_KEY, v);
    } catch {
      // ignora
    }
  };

  const persistLocation = (l: string) => {
    setLocationId(l);
    try {
      localStorage.setItem(HISTORY_LOCATION_KEY, l);
    } catch {
      // ignora
    }
  };

  return (
    <>
      <Head title={`Histórico de estoque · ${product.name}`} />

      <div className="min-h-screen bg-stone-50 text-stone-900">
        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 pt-4 pb-3 flex items-center justify-between">
            <div className="min-w-0">
              <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
                <Link href="/products" className="hover:underline">
                  Produtos
                </Link>
                <span className="text-stone-400">›</span>
                <Link href={`/products/${product.id}`} className="hover:underline">
                  {product.name}
                </Link>
                <span className="text-stone-400">›</span>
                <span className="text-stone-900 font-medium">Histórico de estoque</span>
              </div>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight truncate">
                Histórico de estoque · {product.name}
              </h1>
              <div className="mt-1 font-mono text-[12px] text-stone-500">
                {product.sku}
                {product.unit && <span className="ml-2 text-stone-400">· {product.unit}</span>}
              </div>
            </div>
            <Button variant="outline" asChild>
              <Link href={`/products/${product.id}`}>
                <ArrowLeft className="w-4 h-4 mr-1.5" />
                Voltar
              </Link>
            </Button>
          </div>
        </header>

        <main className="pb-16 px-6 mt-4 max-w-5xl">
          {/* Filter bar */}
          <section className="rounded-md bg-white border border-stone-200 shadow-sm p-4 flex items-center gap-3 flex-wrap">
            <Filter className="w-4 h-4 text-stone-500" />
            <div className="flex items-center gap-2">
              <label htmlFor="variation_select" className="text-[12.5px] text-stone-700">
                Variação:
              </label>
              <Select value={variationId} onValueChange={persistVariation}>
                <SelectTrigger id="variation_select" className="w-56">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {variations.map((v) => (
                    <SelectItem key={v.id} value={String(v.id)}>
                      {v.name} · {v.subSku}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-2">
              <label htmlFor="location_select" className="text-[12.5px] text-stone-700">
                Local:
              </label>
              <Select value={locationId} onValueChange={persistLocation}>
                <SelectTrigger id="location_select" className="w-48">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(businessLocations).map(([k, v]) => (
                    <SelectItem key={k} value={k}>
                      {v}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </section>

          {/* Placeholder pra timeline — o backend legacy retorna HTML partial via ajax.
              Wave 3 vai migrar o endpoint pra JSON e renderizar timeline rica.
              Aqui exibimos iframe/embed do partial existente ou link pro Blade fallback. */}
          <section className="mt-4 rounded-md bg-white border border-stone-200 shadow-sm p-6 text-center text-stone-600">
            <p className="text-[13px] mb-3">
              Timeline detalhada é carregada do endpoint legacy. Selecione variação e local acima
              pra inicializar.
            </p>
            <p className="text-[11.5px] text-stone-500">
              Wave 3 migrará timeline pra JSON com cores semânticas (entrada/saída/ajuste).
            </p>
            <Button variant="outline" className="mt-4" asChild>
              <a
                href={`/products/stock-history/${variationId}${
                  locationId ? `?location_id=${locationId}` : ''
                }`}
                target="_blank"
                rel="noopener noreferrer"
              >
                Ver histórico legacy (parcial)
              </a>
            </Button>
          </section>
        </main>
      </div>
    </>
  );
}

ProdutoStockHistory.layout = (page: ReactNode) => (
  <AppShellV2
    title="Histórico de estoque"
    breadcrumbItems={[
      { label: 'Inventário', href: '/products' },
      { label: 'Produtos', href: '/products' },
      { label: 'Histórico de estoque' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoStockHistory;
