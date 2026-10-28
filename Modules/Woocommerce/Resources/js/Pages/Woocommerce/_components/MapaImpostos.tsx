// Mapa de impostos do painel do WooCommerce (thread woocommerce/playbook/01, F3-2).
//
// O mesmo bloco "Mapear taxas" da Blade `woocommerce::woocommerce.index`: cada taxa do ERP aponta para uma taxa da
// loja. As taxas da loja vêm da API dela, então a leitura (`/woocommerce/tax-rates-map`) só acontece quando a pessoa
// abre o mapa — o painel continua sem chamar a loja ao abrir. Gravar usa o endpoint de sempre (`map-taxrates`), com
// o mesmo `taxes[id_do_erp] = id_na_loja`; "Nenhuma" grava vazio, como o "Selecione" da Blade.

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Percent } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Stack } from '@/Components/layout';

interface TaxaErp { id: number; nome: string; aliquota: number; loja_id: number | null }
interface TaxaLoja { id: number; nome: string }
interface Mapa { erp: TaxaErp[]; loja: TaxaLoja[]; loja_respondeu: boolean }

const NENHUMA = 'nenhuma';

export default function MapaImpostos() {
  const [aberto, setAberto] = useState(false);
  const [mapa, setMapa] = useState<Mapa | null>(null);
  const [escolha, setEscolha] = useState<Record<number, string>>({});
  const [erro, setErro] = useState<string | null>(null);
  const [gravando, setGravando] = useState(false);

  const abrir = async () => {
    setAberto(true);
    setMapa(null);
    setErro(null);
    try {
      const r = await fetch('/woocommerce/tax-rates-map', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const m = (await r.json()) as Mapa;
      setMapa(m);
      setEscolha(Object.fromEntries(m.erp.map((t) => [t.id, t.loja_id !== null ? String(t.loja_id) : NENHUMA])));
    } catch {
      setErro('Não deu para carregar as taxas agora.');
    }
  };

  const salvar = () => {
    const taxes = Object.fromEntries(Object.entries(escolha).map(([id, v]) => [id, v === NENHUMA ? '' : v]));
    setGravando(true);
    router.post('/woocommerce/map-taxrates', { taxes }, {
      preserveScroll: true,
      onSuccess: () => setAberto(false),
      onFinish: () => setGravando(false),
    });
  };

  return (
    <>
      <Button variant="outline" onClick={abrir}><Percent className="size-4" aria-hidden="true" /> Mapear impostos</Button>
      <Sheet open={aberto} onOpenChange={setAberto}>
        <SheetContent className="w-full sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>Mapear impostos</SheetTitle>
            <SheetDescription>Cada taxa do ERP usa a taxa equivalente da loja quando o produto sobe.</SheetDescription>
          </SheetHeader>
          <Stack gap={3} className="px-4" data-contract="mapa-impostos">
            {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
            {!mapa && !erro && <p className="text-sm text-muted-foreground">Carregando as taxas…</p>}
            {mapa && !mapa.loja_respondeu && (
              <Alert>
                <AlertTitle>A loja não devolveu nenhuma taxa</AlertTitle>
                <AlertDescription>Ou a loja não respondeu, ou ela não tem taxa cadastrada. Sem a lista de taxas da loja não dá para mapear.</AlertDescription>
              </Alert>
            )}
            {mapa && mapa.erp.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma taxa cadastrada no ERP.</p>}
            {mapa && mapa.loja_respondeu && mapa.erp.map((t) => (
              <div key={t.id} className="grid grid-cols-2 items-center gap-3">
                <span className="text-sm">{t.nome} <span className="text-muted-foreground">({t.aliquota}%)</span></span>
                <Select value={escolha[t.id] ?? NENHUMA} onValueChange={(v) => setEscolha((e) => ({ ...e, [t.id]: v }))}>
                  <SelectTrigger aria-label={`Taxa da loja para ${t.nome}`}><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SafeSelectItem value={NENHUMA}>Nenhuma</SafeSelectItem>
                    {mapa.loja.map((l) => <SafeSelectItem key={l.id} value={String(l.id)}>{l.nome}</SafeSelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            ))}
          </Stack>
          <SheetFooter>
            <Button onClick={salvar} disabled={!mapa || !mapa.loja_respondeu || mapa.erp.length === 0 || gravando}>Salvar</Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </>
  );
}
