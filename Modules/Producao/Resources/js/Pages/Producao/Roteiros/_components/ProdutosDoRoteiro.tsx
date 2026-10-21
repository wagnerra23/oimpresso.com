// "Produtos que usam este roteiro" (UC-FLX-07 · US-PCP-026). Forma: bloco `pcv-rot-prod` da vista
// Roteiros do protótipo (producao-cv-os.jsx) — chips + "Ligar produto". O combobox é o canon do DS
// (Popover + Command, busca no servidor com shouldFilter={false}).
// Cada mudança grava na hora: PUT /producao/roteiros/{id}/produtos substitui a lista do roteiro, e
// o servidor tira o produto do roteiro em que ele estava (o vínculo é único por produto).
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Plus, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';

export interface ProdutoLigado { id: number; nome: string; sku: string | null }
interface Achado extends ProdutoLigado { roteiro_id: number | null; roteiro_nome: string | null }

export default function ProdutosDoRoteiro({ roteiroId, produtos }: { roteiroId: number; produtos: ProdutoLigado[] }) {
  const [aberto, setAberto] = useState(false);
  const [q, setQ] = useState('');
  const [achados, setAchados] = useState<Achado[]>([]);
  const [buscando, setBuscando] = useState(false);
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const pedido = useRef(0);

  useEffect(() => {
    const termo = q.trim();
    if (termo.length < 2) { setAchados([]); return; }
    const n = ++pedido.current;
    setBuscando(true);
    const t = setTimeout(async () => {
      try {
        const res = await fetch(`/producao/roteiros/produtos/busca?q=${encodeURIComponent(termo)}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const dados: Achado[] = res.ok ? await res.json() : [];
        if (n === pedido.current) setAchados(dados);
      } finally {
        if (n === pedido.current) setBuscando(false);
      }
    }, 250);
    return () => clearTimeout(t);
  }, [q]);

  const gravar = (ids: number[]) => {
    setSalvando(true);
    setErro(null);
    router.put(`/producao/roteiros/${roteiroId}/produtos`, { product_ids: ids }, {
      preserveScroll: true,
      only: ['roteiros'],
      onError: () => setErro('Não deu para salvar os produtos deste roteiro. Confira se o produto é desta empresa.'),
      onFinish: () => setSalvando(false),
    });
  };
  const ids = produtos.map((p) => p.id);
  const ligar = (p: Achado) => { setAberto(false); setQ(''); if (!ids.includes(p.id)) gravar([...ids, p.id]); };
  const desligar = (id: number) => gravar(ids.filter((x) => x !== id));
  const fora = achados.filter((p) => !ids.includes(p.id));

  return (
    <div className="pcv-rot-prod" data-contract="producao-roteiro-produtos">
      <span className="pcv-eyebrow" id={`rot-prod-${roteiroId}`}>Produtos que usam este roteiro</span>
      <div className="pcv-rot-chips" role="list" aria-labelledby={`rot-prod-${roteiroId}`}>
        {produtos.map((p) => (
          <span key={p.id} role="listitem" className="pcv-chip inline-flex items-center gap-1">
            {p.nome}
            <button type="button" className="-mr-1 inline-flex size-5 items-center justify-center rounded-full hover:bg-muted"
              onClick={() => desligar(p.id)} disabled={salvando} aria-label={`Tirar ${p.nome} deste roteiro`}>
              <X className="size-3" aria-hidden="true" />
            </button>
          </span>
        ))}
        {produtos.length === 0 && <span className="pcv-help">Nenhum produto ainda.</span>}
        <Popover open={aberto} onOpenChange={setAberto}>
          <PopoverTrigger asChild>
            <Button size="sm" variant="outline" role="combobox" aria-expanded={aberto} disabled={salvando}>
              <Plus className="size-3.5" aria-hidden="true" /> Ligar produto
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-80 p-0" align="start">
            <Command shouldFilter={false}>
              <CommandInput value={q} onValueChange={setQ} placeholder="Nome ou código do produto" />
              <CommandList>
                <CommandEmpty>{q.trim().length < 2 ? 'Digite ao menos 2 letras.' : buscando ? 'Buscando…' : 'Nenhum produto encontrado.'}</CommandEmpty>
                {fora.length > 0 && (
                  <CommandGroup>
                    {fora.map((p) => (
                      <CommandItem key={p.id} value={String(p.id)} onSelect={() => ligar(p)}>
                        <div className="flex min-w-0 flex-col">
                          <span className="truncate">{p.nome}</span>
                          <small className="text-xs text-muted-foreground">
                            {[p.sku, p.roteiro_nome ? `sai do roteiro ${p.roteiro_nome}` : null].filter(Boolean).join(' · ')}
                          </small>
                        </div>
                      </CommandItem>
                    ))}
                  </CommandGroup>
                )}
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>
      </div>
      {erro && <p className="text-xs text-destructive" role="alert">{erro}</p>}
      <p className="pcv-help">Cada item aprovado vira uma OS com as etapas do roteiro do produto dele. Ligar aqui um produto que está em outro roteiro tira ele de lá. Produto sem roteiro não vira OS.</p>
    </div>
  );
}
