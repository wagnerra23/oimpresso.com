// Busca de insumo do editor de ingredientes (handoff Fabricação §5): no máximo 7 resultados,
// `Enter` escolhe o primeiro, `Esc` fecha. Pergunta ao `/manufacturing/editor-receita/insumos`
// (RecipeBomService::buscarInsumos), que já devolve custo de hoje, unidade e sub-unidades — a linha
// nasce pronta. Mesmo padrão de busca da NovaReceitaDialog: debounce + cancela a anterior.
import { useEffect, useState } from 'react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { fmt } from '../_lib/formato';
import type { LinhaEditor } from '../_lib/custo';

const ROTA_BUSCA = '/manufacturing/editor-receita/insumos';
const MIN_QUERY = 2;
const DEBOUNCE_MS = 250;

export type InsumoEncontrado = Omit<LinhaEditor, 'linha_id' | 'quantidade' | 'sub_unit_id' | 'waste_percent'>;

interface Props {
  grupo: string;
  onEscolher: (insumo: InsumoEncontrado) => void;
  onFechar: () => void;
}

export default function BuscaInsumo({ grupo, onEscolher, onFechar }: Props) {
  const [query, setQuery] = useState('');
  const [opcoes, setOpcoes] = useState<InsumoEncontrado[]>([]);
  const [buscando, setBuscando] = useState(false);
  const [erro, setErro] = useState(false);

  useEffect(() => {
    const termo = query.trim();
    if (termo.length < MIN_QUERY) {
      setOpcoes([]);
      setErro(false);
      return;
    }
    const controller = new AbortController();
    const handle = setTimeout(async () => {
      setBuscando(true);
      try {
        const res = await fetch(`${ROTA_BUSCA}?${new URLSearchParams({ q: termo }).toString()}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          signal: controller.signal,
        });
        if (!res.ok) throw new Error(String(res.status));
        const data = (await res.json()) as { insumos?: InsumoEncontrado[] };
        setOpcoes(Array.isArray(data.insumos) ? data.insumos.slice(0, 7) : []);
        setErro(false);
      } catch (err) {
        if ((err as Error).name !== 'AbortError') {
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
  }, [query]);

  return (
    <div className="mfg-pick">
      <Input
        autoFocus
        value={query}
        placeholder="Buscar insumo por nome ou código"
        aria-label={`Buscar insumo para ${grupo}`}
        onChange={(e) => setQuery(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Escape') onFechar();
          if (e.key === 'Enter') {
            e.preventDefault();
            if (opcoes[0]) onEscolher(opcoes[0]);
          }
        }}
      />
      <div className="mfg-pick-list" role="listbox" aria-label="Insumos encontrados">
        {opcoes.map((o) => (
          <button key={o.variation_id} type="button" role="option" aria-selected={false} className="mfg-pick-i" onClick={() => onEscolher(o)}>
            <span className="n">
              {o.nome}
              <small>{o.sku}</small>
            </span>
            <span className="c">
              {fmt(o.custo_unitario)}
              <small>/ {o.unidade_base}</small>
            </span>
          </button>
        ))}
        {!buscando && query.trim().length >= MIN_QUERY && opcoes.length === 0 && (
          <p className="mfg-pick-empty">{erro ? 'Não foi possível buscar agora. Tente de novo.' : 'Nenhum insumo encontrado.'}</p>
        )}
      </div>
      <div className="pt-2">
        <Button type="button" size="sm" variant="outline" onClick={onFechar}>
          Cancelar
        </Button>
      </div>
    </div>
  );
}
