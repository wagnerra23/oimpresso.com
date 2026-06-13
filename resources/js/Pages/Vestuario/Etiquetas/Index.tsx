// @memcofre
//   tela: /vestuario/etiquetas
//   stories: US-VEST-020 (Etiqueta TAG térmica + EAN-13 + QR Code)
//   adrs: 0093 multi-tenant · 0104 mwart · 0121 vertical Vestuario
//   spec: memory/requisitos/Vestuario/SPEC.md#US-VEST-020
//   runbook: memory/requisitos/Vestuario/RUNBOOK-etiqueta-tag.md
//   permissao: vestuario.etiqueta.view / vestuario.etiqueta.create

import { useState, type FormEvent } from 'react';
import { Head } from '@inertiajs/react';
import { Download, Printer, Plus, Trash2, FileText, QrCode } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface EtiquetaConfig {
  width_dots: number;
  height_dots: number;
  dpi: number;
  margin_dots: number;
  qr_enabled: boolean;
}

interface EtiquetaItem {
  id: string; // local UI id
  product_id: number;
  variation_id?: number;
  nome: string;
  tamanho: string;
  cor: string;
  colecao: string;
  preco: number;
  sku: string;
  ean13: string;
}

interface Props {
  config: EtiquetaConfig;
}

function emptyItem(): EtiquetaItem {
  return {
    id: crypto.randomUUID(),
    product_id: 1,
    nome: '',
    tamanho: 'M',
    cor: '',
    colecao: '',
    preco: 0,
    sku: '',
    ean13: '',
  };
}

export default function EtiquetasIndex({ config }: Props) {
  const [items, setItems] = useState<EtiquetaItem[]>([emptyItem()]);
  const [copies, setCopies] = useState(1);
  const [submitting, setSubmitting] = useState<'zpl' | 'pdf' | null>(null);
  const [error, setError] = useState<string | null>(null);

  function addItem() {
    setItems((prev) => [...prev, emptyItem()]);
  }

  function removeItem(id: string) {
    setItems((prev) => (prev.length === 1 ? prev : prev.filter((i) => i.id !== id)));
  }

  function updateItem(id: string, field: keyof EtiquetaItem, value: string | number) {
    setItems((prev) => prev.map((i) => (i.id === id ? { ...i, [field]: value } : i)));
  }

  function buildPayload() {
    return {
      items: items.map((i) => ({
        product_id: i.product_id,
        variation_id: i.variation_id || undefined,
        nome: i.nome || undefined,
        tamanho: i.tamanho || undefined,
        cor: i.cor || undefined,
        colecao: i.colecao || undefined,
        preco: i.preco || undefined,
        sku: i.sku || undefined,
        ean13: i.ean13 || undefined,
      })),
      copies: Math.max(1, Math.min(100, copies)),
    };
  }

  async function submit(format: 'zpl' | 'pdf', e: FormEvent) {
    e.preventDefault();
    setSubmitting(format);
    setError(null);

    try {
      const csrfToken =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

      const res = await fetch(`/vestuario/etiquetas/lote/${format}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          Accept: format === 'pdf' ? 'application/pdf' : 'text/plain',
        },
        body: JSON.stringify(buildPayload()),
      });

      if (!res.ok) {
        const txt = await res.text();
        throw new Error(`HTTP ${res.status}: ${txt.slice(0, 200)}`);
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `etiquetas-${new Date().toISOString().slice(0, 10)}.${format}`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      setError(msg);
    } finally {
      setSubmitting(null);
    }
  }

  return (
    <>
      <Head title="Etiquetas — Vestuário" />

      <PageHeader
        title="Etiquetas TAG vestuário"
        subtitle="ZPL Argox/Zebra + PDF · US-VEST-020"
      />

      <div className="space-y-4 p-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Configuração atual (vestuario_settings)
            </CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-3 text-xs">
            <Badge variant="outline">{config.width_dots}×{config.height_dots} dots</Badge>
            <Badge variant="outline">{config.dpi} dpi</Badge>
            <Badge variant="outline">margem {config.margin_dots} dots</Badge>
            {config.qr_enabled ? (
              <Badge className="bg-success-soft text-success-fg">
                <QrCode className="mr-1 h-3 w-3" /> QR ativo
              </Badge>
            ) : (
              <Badge variant="secondary">QR desligado</Badge>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Lote ({items.length} item{items.length === 1 ? '' : 's'})</CardTitle>
            <div className="flex items-center gap-2">
              <Label htmlFor="copies" className="text-xs">cópias×</Label>
              <Input
                id="copies"
                type="number"
                min={1}
                max={100}
                value={copies}
                onChange={(e) => setCopies(Number(e.target.value) || 1)}
                className="w-20"
              />
              <Button type="button" variant="outline" size="sm" onClick={addItem}>
                <Plus className="mr-1 h-4 w-4" /> Item
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            {items.map((item) => (
              <div
                key={item.id}
                className="grid grid-cols-12 items-end gap-2 rounded-md border bg-muted/30 p-3"
              >
                <div className="col-span-1">
                  <Label className="text-xs">Produto ID</Label>
                  <Input
                    type="number"
                    value={item.product_id}
                    onChange={(e) => updateItem(item.id, 'product_id', Number(e.target.value) || 0)}
                  />
                </div>
                <div className="col-span-3">
                  <Label className="text-xs">Nome</Label>
                  <Input
                    value={item.nome}
                    onChange={(e) => updateItem(item.id, 'nome', e.target.value)}
                    placeholder="Camiseta Básica"
                  />
                </div>
                <div className="col-span-1">
                  <Label className="text-xs">Tam</Label>
                  <Input
                    value={item.tamanho}
                    onChange={(e) => updateItem(item.id, 'tamanho', e.target.value)}
                    placeholder="M"
                  />
                </div>
                <div className="col-span-2">
                  <Label className="text-xs">Cor</Label>
                  <Input
                    value={item.cor}
                    onChange={(e) => updateItem(item.id, 'cor', e.target.value)}
                    placeholder="Azul Marinho"
                  />
                </div>
                <div className="col-span-2">
                  <Label className="text-xs">Coleção</Label>
                  <Input
                    value={item.colecao}
                    onChange={(e) => updateItem(item.id, 'colecao', e.target.value)}
                    placeholder="Verão 2026"
                  />
                </div>
                <div className="col-span-1">
                  <Label className="text-xs">Preço</Label>
                  <Input
                    type="number"
                    step="0.01"
                    value={item.preco}
                    onChange={(e) => updateItem(item.id, 'preco', Number(e.target.value) || 0)}
                  />
                </div>
                <div className="col-span-1">
                  <Label className="text-xs">SKU</Label>
                  <Input
                    value={item.sku}
                    onChange={(e) => updateItem(item.id, 'sku', e.target.value)}
                    placeholder="auto"
                  />
                </div>
                <div className="col-span-1 flex justify-end">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => removeItem(item.id)}
                    disabled={items.length === 1}
                    aria-label="Remover item"
                  >
                    <Trash2 className="h-4 w-4 text-destructive" />
                  </Button>
                </div>
              </div>
            ))}

            {error && (
              <div className="rounded border border-destructive/20 bg-destructive-soft px-3 py-2 text-sm text-destructive-fg">
                {error}
              </div>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                onClick={(e) => submit('zpl', e)}
                disabled={submitting !== null}
              >
                {submitting === 'zpl' ? (
                  <>Gerando…</>
                ) : (
                  <>
                    <Printer className="mr-2 h-4 w-4" />
                    Baixar ZPL ({items.length * copies}×)
                  </>
                )}
              </Button>
              <Button
                type="button"
                onClick={(e) => submit('pdf', e)}
                disabled={submitting !== null}
              >
                {submitting === 'pdf' ? (
                  <>Gerando PDF…</>
                ) : (
                  <>
                    <FileText className="mr-2 h-4 w-4" />
                    Gerar PDF ({items.length * copies}×)
                  </>
                )}
              </Button>
            </div>
          </CardContent>
        </Card>

        <p className="text-xs text-muted-foreground">
          ZPL gera arquivo .zpl pra enviar TCP/USB direto pra impressora Argox/Zebra.
          PDF gera A4 com grid 4×8 etiquetas (32 por folha) — abre direto no navegador.
        </p>
      </div>
    </>
  );
}

EtiquetasIndex.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;
