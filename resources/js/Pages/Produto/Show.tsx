// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/Show.tsx
// MWART (ADR 0104) + pattern reuse blueprint drawer Cowork → Page full (ADR 0149)
// Refs: RUNBOOK-produto-show.md · Show.charter.md
// Agent W2-C · 2026-05-15

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Edit, History, ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface ProdutoShowResumo {
  id: number;
  name: string;
  sku: string;
  type: 'single' | 'variable' | 'combo';
  category: string | null;
  subCategory: string | null;
  brand: string | null;
  unit: string | null;
  enableStock: boolean;
  alertQuantity: string | null;
  productDescription: string | null;
  image: string | null;
}

interface RackDetail {
  location_name?: string;
  rack?: string;
  row?: string;
  position?: string;
  current_stock?: number;
}

interface VariationDetail {
  id: number;
  name: string;
  sku: string;
  defaultPurchasePrice: number;
  defaultSellPrice: number;
}

export interface ProdutoShowPageProps {
  product: ProdutoShowResumo;
  rackDetails?: RackDetail[];
  variations?: VariationDetail[];
  permissions: {
    update: boolean;
    delete: boolean;
  };
}

const TABS = [
  { id: 'resumo', label: 'Resumo' },
  { id: 'variacoes', label: 'Variações' },
  { id: 'estoque', label: 'Estoque' },
] as const;

type TabId = (typeof TABS)[number]['id'];

function ProdutoShow(props: ProdutoShowPageProps) {
  const { product, permissions } = props;
  const [activeTab, setActiveTab] = useState<TabId>('resumo');

  return (
    <>
      <Head title={`${product.name} · Produto`} />
      <div className="min-h-screen bg-stone-50 text-stone-900">
        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 pt-4 pb-3 flex items-center justify-between">
            <div className="min-w-0">
              <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
                <Link href="/products" className="hover:underline">
                  Produtos
                </Link>
                <span className="text-stone-400">›</span>
                <span className="text-stone-900 font-medium">Detalhe</span>
              </div>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight truncate">
                {product.name}
              </h1>
              <div className="mt-1 flex items-center gap-3 text-[12px] text-stone-600">
                <span className="font-mono">{product.sku}</span>
                {product.category && (
                  <>
                    <span className="text-stone-400">·</span>
                    <span>{product.category}</span>
                  </>
                )}
                {product.unit && (
                  <>
                    <span className="text-stone-400">·</span>
                    <span>{product.unit}</span>
                  </>
                )}
              </div>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <Button variant="outline" asChild>
                <Link href="/products">
                  <ArrowLeft className="w-4 h-4 mr-1.5" />
                  Voltar
                </Link>
              </Button>
              <Button variant="outline" asChild>
                <Link href={`/products/stock-history/${product.id}`}>
                  <History className="w-4 h-4 mr-1.5" />
                  Histórico estoque
                </Link>
              </Button>
              {permissions.update && (
                <Button asChild>
                  <Link href={`/products/${product.id}/edit`}>
                    <Edit className="w-4 h-4 mr-1.5" />
                    Editar
                  </Link>
                </Button>
              )}
            </div>
          </div>

          {/* Tabs */}
          <nav className="px-6 pb-2 flex items-center gap-1 text-[12.5px]">
            {TABS.map((t) => (
              <button
                key={t.id}
                type="button"
                onClick={() => setActiveTab(t.id)}
                className={`h-7 px-3 rounded-md transition-colors duration-150 ${
                  activeTab === t.id
                    ? 'bg-stone-900 text-stone-50 font-medium'
                    : 'text-stone-600 hover:bg-stone-100'
                }`}
              >
                {t.label}
              </button>
            ))}
          </nav>
        </header>

        <main className="pb-16 px-6 mt-4 max-w-5xl">
          {activeTab === 'resumo' && <ResumoTab product={product} />}
          {activeTab === 'variacoes' && (
            <Deferred data="variations" fallback={<TabSkeleton />}>
              <VariacoesTab variations={props.variations ?? []} />
            </Deferred>
          )}
          {activeTab === 'estoque' && (
            <Deferred data="rackDetails" fallback={<TabSkeleton />}>
              <EstoqueTab rackDetails={props.rackDetails ?? []} />
            </Deferred>
          )}
        </main>
      </div>
    </>
  );
}

ProdutoShow.layout = (page: ReactNode) => (
  <AppShellV2
    title="Produto"
    breadcrumbItems={[
      { label: 'Inventário', href: '/products' },
      { label: 'Produtos', href: '/products' },
      { label: 'Detalhe' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoShow;

/* ─── Subcomponentes ─────────────────────────────────────────────────── */

function ResumoTab({ product }: { product: ProdutoShowResumo }) {
  return (
    <div className="space-y-4">
      <section className="rounded-md bg-white border border-stone-200 shadow-sm p-6">
        <h3 className="text-[13px] font-medium uppercase tracking-widest text-stone-500 mb-3">
          Identificação
        </h3>
        <dl className="grid grid-cols-2 gap-4 text-[13px]">
          <FieldRow label="Nome" value={product.name} />
          <FieldRow label="SKU" value={<span className="font-mono">{product.sku}</span>} />
          <FieldRow label="Tipo" value={product.type} />
          <FieldRow label="Unidade" value={product.unit ?? '—'} />
          <FieldRow label="Categoria" value={product.category ?? '—'} />
          <FieldRow label="Sub-categoria" value={product.subCategory ?? '—'} />
          <FieldRow label="Marca" value={product.brand ?? '—'} />
          <FieldRow
            label="Estoque controlado?"
            value={product.enableStock ? 'Sim' : 'Não'}
          />
        </dl>
      </section>
      {product.productDescription && (
        <section className="rounded-md bg-white border border-stone-200 shadow-sm p-6">
          <h3 className="text-[13px] font-medium uppercase tracking-widest text-stone-500 mb-3">
            Descrição
          </h3>
          <p className="text-[13px] text-stone-700 whitespace-pre-wrap">
            {product.productDescription}
          </p>
        </section>
      )}
    </div>
  );
}

function FieldRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <dt className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">{label}</dt>
      <dd className="mt-1 text-stone-900">{value}</dd>
    </div>
  );
}

function VariacoesTab({ variations }: { variations: VariationDetail[] }) {
  if (variations.length === 0) {
    return (
      <div className="rounded-md bg-white border border-stone-200 p-6 text-center text-stone-500 text-[13px]">
        Produto sem variações cadastradas.
      </div>
    );
  }
  return (
    <div className="rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
          <tr className="border-b border-stone-200 bg-stone-50/40">
            <th className="pl-6 pr-3 py-2">Variação</th>
            <th className="pr-3 py-2 w-32">SKU</th>
            <th className="pr-3 py-2 w-32 text-right">Preço compra</th>
            <th className="pr-6 py-2 w-32 text-right">Preço venda</th>
          </tr>
        </thead>
        <tbody>
          {variations.map((v) => (
            <tr key={v.id} className="border-b border-stone-100" style={{ height: 40 }}>
              <td className="pl-6 pr-3 text-[13px] font-medium">{v.name}</td>
              <td className="pr-3 font-mono text-[11.5px] text-stone-500">{v.sku}</td>
              <td className="pr-3 text-[12.5px] text-right tabular-nums">
                {v.defaultPurchasePrice.toLocaleString('pt-BR', {
                  style: 'currency',
                  currency: 'BRL',
                })}
              </td>
              <td className="pr-6 text-[12.5px] text-right tabular-nums font-semibold">
                {v.defaultSellPrice.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function EstoqueTab({ rackDetails }: { rackDetails: RackDetail[] }) {
  if (rackDetails.length === 0) {
    return (
      <div className="rounded-md bg-white border border-stone-200 p-6 text-center text-stone-500 text-[13px]">
        Sem rack/localização cadastrada.
      </div>
    );
  }
  return (
    <div className="rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
          <tr className="border-b border-stone-200 bg-stone-50/40">
            <th className="pl-6 pr-3 py-2">Local</th>
            <th className="pr-3 py-2">Rack/Row/Position</th>
            <th className="pr-6 py-2 w-32 text-right">Estoque atual</th>
          </tr>
        </thead>
        <tbody>
          {rackDetails.map((rd, idx) => (
            <tr key={idx} className="border-b border-stone-100" style={{ height: 36 }}>
              <td className="pl-6 pr-3 text-[13px]">{rd.location_name ?? '—'}</td>
              <td className="pr-3 text-[12px] font-mono text-stone-600">
                {[rd.rack, rd.row, rd.position].filter(Boolean).join(' / ') || '—'}
              </td>
              <td className="pr-6 text-[12.5px] text-right tabular-nums">
                {rd.current_stock ?? '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function TabSkeleton() {
  return (
    <div className="rounded-md bg-white border border-stone-200 p-6">
      <div className="h-4 w-32 bg-stone-200 rounded animate-pulse mb-3" />
      <div className="space-y-2">
        <div className="h-3 w-full bg-stone-200 rounded animate-pulse" />
        <div className="h-3 w-3/4 bg-stone-200 rounded animate-pulse" />
        <div className="h-3 w-2/3 bg-stone-200 rounded animate-pulse" />
      </div>
    </div>
  );
}
