// Busca de variação por nome ou SKU para montar o lote de etiquetas (US-VEST-020).
//
// Endpoint: /products/list, o mesmo da venda (ProductUtil::filterProduct, por business_id da sessão);
// sem local, porque etiqueta não depende de saldo. Motor: Command inline com shouldFilter={false},
// como GradeProductCombobox da compra (REGISTRY_DS_COMPONENTES §Combobox).
//
// Não usa ProductSearchAutocomplete: ele exige local e carrega regra de preço de venda.
import { useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';

export interface VariacaoEncontrada {
  product_id: number;
  variation_id: number;
  name: string;
  type?: string;
  variation?: string;
  sub_sku?: string;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY = 2;
const MAX_OPCOES = 12;

// `DUMMY` é o nome da variação de produto sem grade no UltimatePOS.
export function rotuloDaVariacao(v: VariacaoEncontrada): string {
  return v.variation && v.variation !== 'DUMMY' ? v.variation : '';
}

export default function BuscaVariacao({ onEscolher }: { onEscolher: (v: VariacaoEncontrada) => void }) {
  const [query, setQuery] = useState('');
  const [opcoes, setOpcoes] = useState<VariacaoEncontrada[]>([]);
  const [buscando, setBuscando] = useState(false);
  const [erro, setErro] = useState(false);

  const termo = query.trim();
  const podeBuscar = termo.length >= MIN_QUERY;

  useEffect(() => {
    if (!podeBuscar) {
      setOpcoes([]);
      setErro(false);
      return;
    }
    const controller = new AbortController();
    const handle = setTimeout(async () => {
      setBuscando(true);
      try {
        const res = await fetch(`/products/list?${new URLSearchParams({ term: termo }).toString()}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          signal: controller.signal,
        });
        if (!res.ok) throw new Error(String(res.status));
        const dados = (await res.json()) as VariacaoEncontrada[];
        setOpcoes(Array.isArray(dados) ? dados.slice(0, MAX_OPCOES) : []);
        setErro(false);
      } catch (e) {
        if ((e as Error).name !== 'AbortError') {
          setOpcoes([]);
          setErro(true);
        }
      } finally {
        if (!controller.signal.aborted) setBuscando(false);
      }
    }, DEBOUNCE_MS);
    return () => {
      clearTimeout(handle);
      controller.abort();
    };
  }, [termo, podeBuscar]);

  function escolher(v: VariacaoEncontrada) {
    onEscolher(v);
    setQuery('');
    setOpcoes([]);
  }

  return (
    <div>
      {/* O cmdk controla o id do input: o rótulo acessível é o `label` do Command. */}
      <span aria-hidden="true" className="text-xs font-medium">Buscar produto</span>
      <Command label="Buscar produto" shouldFilter={false} className="relative overflow-visible rounded-md border border-input bg-background">
        <CommandInput
          value={query}
          onValueChange={setQuery}
          placeholder="Digite o nome ou o SKU"
        />
        {buscando && (
          <Loader2 aria-hidden="true" className="absolute right-2 top-2.5 size-4 animate-spin text-muted-foreground" />
        )}
        {podeBuscar && !buscando && (
          <CommandList>
            <CommandEmpty>{erro ? 'Não foi possível buscar agora. Tente de novo.' : 'Nenhum produto encontrado.'}</CommandEmpty>
            {opcoes.map((v) => (
              <CommandItem key={v.variation_id} value={String(v.variation_id)} onSelect={() => escolher(v)}>
                {[v.name, rotuloDaVariacao(v), v.sub_sku].filter(Boolean).join(' · ')}
              </CommandItem>
            ))}
          </CommandList>
        )}
      </Command>
    </div>
  );
}
