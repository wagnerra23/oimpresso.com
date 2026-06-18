// US-SELL-005 — ProductSearchAutocomplete (componente local da Sells/Create).
//
// Reusa endpoint legado /products/list (ProductController@getProducts → ProductUtil@filterProduct)
// que retorna N rows pra produto type='variable' (uma por variação).
//
// Dor 1 Larissa — R6 paridade Blade + agrupamento (2026-05-27):
//   PR #1755 R4 incrementou qty no carrinho quando user clica 2x na MESMA variação,
//   mas o dropdown continuava mostrando N linhas com nome IDÊNTICO ("Camiseta",
//   "Camiseta", "Camiseta") pra produto variable — Larissa não distinguia tamanhos
//   e via "muita quantidade de produtos repetidos".
//
//   Blade legacy (pos.js:284-286) renderiza `${item.name}-${item.variation}` quando
//   type==='variable' (ex "Camiseta-G"). NÃO agrupa — mostra N linhas distinguíveis.
//
//   Esta PR vai ALÉM da paridade Blade quando há ≥2 variações: agrupa por product_id
//   no dropdown e abre Popover de seleção (estado da arte 2026 — Shopify POS, Toast).
//   Quando há 1 variação só: renderiza linha simples com `-{variation}` (paridade Blade).
//
// Dor 3 Larissa — R5 search_fields (2026-05-27):
//   Mantém envio de `search_fields[]=name,sku,lot` (paridade Blade default pos.js:3076).
//   Sem isso, backend cai em fallback ['name','sku'] e perde busca por lote.
//
// Não vira shared ainda — extrair pra @/Components/shared só quando 2ª tela usar.
// Princípio R-DS-001 (reutilização sob demanda, não especulativa).
//
// TODO V3: modal "Configurar busca" (paridade configure_search_modal.blade.php)
// pra usuário marcar/desmarcar product_custom_field1..4 + persistir em localStorage.

import { useState, useEffect, useRef, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Search, Loader2, X, ChevronRight, Package, SlidersHorizontal } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import {
  Popover,
  PopoverTrigger,
  PopoverContent,
} from '@/Components/ui/popover';
import { Checkbox } from '@/Components/ui/checkbox';

// Backend devolve estes campos por row (ProductUtil@filterProduct:1715-1734).
// AP-12 F3 audit (memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md):
// tipo explícito > `[k: string]: unknown` pra detectar drift backend cedo.
export interface ProductSearchResult {
  product_id: number;
  variation_id: number;
  name: string;
  type?: 'variable' | 'single' | 'modifier' | 'combo';
  enable_stock?: 0 | 1;
  variation?: string; // variations.name (ex "P", "M", "G")
  sub_sku?: string;
  sku: string;
  selling_price?: number;
  variation_group_price?: number; // quando price_group filtra
  qty_available?: number;
  unit?: string;
  // Quando search_fields inclui 'lot':
  purchase_line_id?: number;
  lot_number?: string;
}

interface Props {
  locationId: number | null;
  onSelect: (product: ProductSearchResult) => void;
  placeholder?: string;
  disabled?: boolean;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY_LENGTH = 2;

// Sentinela anti-race pós-seleção (R7 — fix duplicação scanner 2026-05-28):
// Após handleSelectVariation rodar, um setTimeout-fetch agendado ANTES da seleção
// pode resolver em paralelo (clearTimeout não cancela Promise em flight) e fazer
// setResults+setOpen(true), reabrindo o dropdown DEPOIS de já ter limpo state.
// Larissa via dropdown "fantasma" e clicava no item → duplicação.
// Solução: marcar lastSelectedAtRef e ignorar setOpen tardio nesta janela.
const POST_SELECT_GRACE_MS = 500;

// Paridade Blade legacy (pos.js:3076 — set_search_fields default):
// `['name', 'sku', 'lot']`. Backend (ProductUtil@filterProduct) auto-adiciona
// `sub_sku` quando `sku` está presente (ProductController@getProducts:1522).
const DEFAULT_SEARCH_FIELDS = ['name', 'sku', 'lot'] as const;

// R10 (2026-05-28) — configure-search popover (paridade Blade
// configure_search_modal.blade.php). Larissa @ Rota Livre quer buscar por
// product_custom_field3 (referência interna fornecedor). Persistido localStorage
// com prefixo canon `oimpresso.` (auto-mem GOTCHAS preference).
const ALL_SEARCH_FIELDS = [
  { key: 'name', label: 'Nome do produto' },
  { key: 'sku', label: 'SKU' },
  { key: 'lot', label: 'Lote' },
  { key: 'product_custom_field1', label: 'Campo personalizado 1' },
  { key: 'product_custom_field2', label: 'Campo personalizado 2' },
  { key: 'product_custom_field3', label: 'Campo personalizado 3' },
  { key: 'product_custom_field4', label: 'Campo personalizado 4' },
] as const;
const SEARCH_FIELDS_STORAGE_KEY = 'oimpresso.sells.product_search_fields';

function loadSearchFields(): string[] {
  if (typeof window === 'undefined') return [...DEFAULT_SEARCH_FIELDS];
  try {
    const raw = window.localStorage.getItem(SEARCH_FIELDS_STORAGE_KEY);
    if (!raw) return [...DEFAULT_SEARCH_FIELDS];
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [...DEFAULT_SEARCH_FIELDS];
    const validKeys = new Set(ALL_SEARCH_FIELDS.map((f) => f.key));
    const filtered = parsed.filter((k): k is string => typeof k === 'string' && validKeys.has(k as never));
    // Failsafe — array vazio cairia em fallback backend (só nome). Mantém ao menos `name`.
    return filtered.length > 0 ? filtered : [...DEFAULT_SEARCH_FIELDS];
  } catch {
    return [...DEFAULT_SEARCH_FIELDS];
  }
}

function saveSearchFields(fields: string[]) {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(SEARCH_FIELDS_STORAGE_KEY, JSON.stringify(fields));
  } catch {
    // localStorage quota / private mode — silencioso
  }
}

// Agrupamento dropdown: 1 entrada por product_id. Quando >1 variação,
// abre Popover com lista. Quando 1 só, render direto com `-{variation}`
// se type==='variable' (paridade Blade pos.js:285-286).
type ProductGroup = {
  product_id: number;
  name: string;
  type: ProductSearchResult['type'];
  variations: ProductSearchResult[];
  total_qty_available: number;
  display_price?: number;
};

function groupResults(results: ProductSearchResult[]): ProductGroup[] {
  const map = new Map<number, ProductGroup>();
  for (const row of results) {
    const existing = map.get(row.product_id);
    const rowPrice = row.variation_group_price ?? row.selling_price;
    if (existing) {
      existing.variations.push(row);
      existing.total_qty_available += Number(row.qty_available ?? 0);
    } else {
      map.set(row.product_id, {
        product_id: row.product_id,
        name: row.name,
        type: row.type,
        variations: [row],
        total_qty_available: Number(row.qty_available ?? 0),
        display_price: rowPrice !== undefined ? Number(rowPrice) : undefined,
      });
    }
  }
  return Array.from(map.values());
}

function formatBRL(value: number) {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function ProductSearchAutocomplete({
  locationId,
  onSelect,
  placeholder = 'Buscar por nome, SKU, código de barras ou lote…',
  disabled = false,
}: Props) {
  const [query, setQuery] = useState('');
  // Onda 4 (ADR 0211) — `debouncedQuery` é o termo já-debounceado que alimenta a
  // queryKey do useQuery. Substitui o setTimeout+fetch manual: TanStack Query
  // cancela request anterior quando a key muda (passa `signal` ao queryFn) e
  // descarta resultado stale → R7 raiz eliminado neste path.
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [results, setResults] = useState<ProductSearchResult[]>([]);
  // `loading` indica fetch em vôo (useQuery debounce OU scanner sync). O setter
  // `setLoading` é mantido como API canônica (preserva R7 — defesa-em-profundidade
  // + assertions Pest). useQuery espelha seu `isFetching` aqui via useEffect.
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const [expandedProductId, setExpandedProductId] = useState<number | null>(null);
  // G3 (2026-05-31) — navegação por teclado no dropdown (paridade CustomerSearchAutocomplete).
  // `highlightedIndex` percorre os GRUPOS do dropdown; `variationHighlightedIndex`
  // percorre as VARIAÇÕES dentro do popover aberto (caso multi-tamanho — o comum
  // pra Larissa @ vestuário). -1 = nada destacado. Larissa opera teclado+scanner,
  // então o dropdown precisa ser 100% navegável sem mouse (gap board → Champion).
  const [highlightedIndex, setHighlightedIndex] = useState<number>(-1);
  const [variationHighlightedIndex, setVariationHighlightedIndex] = useState<number>(-1);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  // R7 anti-race — timestamp da última seleção. Bloqueia fetches em flight do
  // useEffect debounce de reabrirem o dropdown logo após handleSelectVariation.
  const lastSelectedAtRef = useRef(0);
  // R10 configure-search — fields persistidos em localStorage.
  const [searchFields, setSearchFields] = useState<string[]>(() => loadSearchFields());
  const [configureOpen, setConfigureOpen] = useState(false);

  const toggleSearchField = (key: string) => {
    setSearchFields((prev) => {
      const next = prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key];
      // Failsafe — não permite array vazio (sem isso busca cai em fallback backend).
      const safe = next.length > 0 ? next : ['name'];
      saveSearchFields(safe);
      return safe;
    });
  };

  const groups = useMemo(() => groupResults(results).slice(0, 10), [results]);

  // Helper canônico de fetch — usado tanto pelo useQuery (path debounce) quanto
  // pelo scanner sync (fetchProductsNow no Enter). `signal` opcional: useQuery
  // injeta o dele (cancelamento automático); scanner sync chama sem signal.
  const fetchProducts = async (
    term: string,
    signal?: AbortSignal,
  ): Promise<ProductSearchResult[]> => {
    if (!locationId || term.length < MIN_QUERY_LENGTH) return [];
    const params = new URLSearchParams({ term });
    params.set('location_id', String(locationId));
    // R10 — searchFields configurável via popover, persistido em localStorage
    searchFields.forEach((f) => params.append('search_fields[]', f));
    const res = await fetch(`/products/list?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      signal,
    });
    if (!res.ok) return [];
    const data = (await res.json()) as ProductSearchResult[];
    return Array.isArray(data) ? data : [];
  };

  // Scanner físico: envia código + Enter em <50ms. Paridade Blade pos.js:186-208 —
  // ao Enter, se 1 result exato → auto-select sem precisar clicar.
  // Coexiste com useQuery (ADR 0211): o scanner precisa de fetch SÍNCRONO no
  // momento do Enter, sem esperar o debounce do useQuery disparar.
  const fetchProductsNow = async (term: string): Promise<ProductSearchResult[]> => {
    try {
      return await fetchProducts(term);
    } catch {
      return [];
    }
  };

  // Onda 4 (ADR 0211) — debounce do termo de busca. Mantém DEBOUNCE_MS canon
  // (250ms) mas só atualiza `debouncedQuery` (a queryKey), sem fetch manual.
  // O cancelamento de request stale agora é responsabilidade do TanStack Query.
  useEffect(() => {
    if (query.length < MIN_QUERY_LENGTH) {
      setDebouncedQuery('');
      return;
    }
    const handle = setTimeout(() => setDebouncedQuery(query), DEBOUNCE_MS);
    return () => clearTimeout(handle);
  }, [query]);

  // Onda 4 (ADR 0211) — useQuery substitui o setTimeout+fetch manual como path
  // canônico de data-fetching. queryKey inclui term + locationId + searchFields:
  // qualquer mudança cancela o request anterior (signal automático) e descarta
  // resultado stale → R7 (race scanner) é estruturalmente impossível neste path.
  //  - staleTime/gcTime/retry herdam o default do QueryClient (app.tsx)
  //  - enabled gate evita disparar com termo curto ou sem local
  const productQuery = useQuery({
    queryKey: ['products', debouncedQuery, locationId, searchFields],
    queryFn: ({ signal }) => fetchProducts(debouncedQuery, signal),
    enabled: debouncedQuery.length >= MIN_QUERY_LENGTH && !!locationId,
  });

  // Espelha o resultado do useQuery em `results` (consumido por `groups` + dropdown).
  // R7 defesa-em-profundidade (mantida na transição — ADR 0211 §"cinto + suspensório"):
  // ignora dado tardio que reabriria o dropdown logo após uma seleção (POST_SELECT_GRACE_MS).
  useEffect(() => {
    if (productQuery.data === undefined) return;
    if (Date.now() - lastSelectedAtRef.current < POST_SELECT_GRACE_MS) return;
    setResults(productQuery.data);
    setOpen(true);
    setExpandedProductId(null);
    // G3 — dado novo zera o destaque de teclado (índice antigo ficaria stale).
    setHighlightedIndex(-1);
    setVariationHighlightedIndex(-1);
  }, [productQuery.data]);

  // Limpa results quando o termo cai abaixo do mínimo (debounce já zerou a key).
  useEffect(() => {
    if (debouncedQuery.length < MIN_QUERY_LENGTH) setResults([]);
  }, [debouncedQuery]);

  // Espelha o estado de fetch do useQuery no `loading` canônico (consumido pela
  // UI + guard `if (loading) return` do Enter handler). O scanner sync (Enter)
  // ainda chama setLoading direto pra o seu próprio fetch.
  useEffect(() => {
    setLoading(productQuery.isFetching);
  }, [productQuery.isFetching]);

  // ── R7 defesa-em-profundidade (DORMENTE na transição — ADR 0211) ─────────────
  // Bloco manual preservado intencionalmente durante a adoção do TanStack Query.
  // useQuery acima é o path LIVE de fetch; este useEffect NÃO refaz a rede (early
  // return), mas mantém o AbortController + sentinela `lastSelectedAtRef` +
  // POST_SELECT_GRACE_MS como rede de segurança documentada e como referência da
  // migração. Removido na última tela MWART migrada (ADR 0211 plano Fase 2).
  const R7_LEGACY_FETCH_ENABLED: boolean = false;
  useEffect(() => {
    if (!R7_LEGACY_FETCH_ENABLED) return; // useQuery é o path canônico (ADR 0211)
    if (query.length < MIN_QUERY_LENGTH) {
      setResults([]);
      return;
    }

    const controller = new AbortController();
    const handle = setTimeout(async () => {
      // Sentinela pré-fetch — se uma seleção acabou de rolar e setQuery('') ainda
      // está pendente no render queue, este timeout dispararia órfão.
      if (Date.now() - lastSelectedAtRef.current < POST_SELECT_GRACE_MS) return;

      setLoading(true);
      try {
        const params = new URLSearchParams({ term: query });
        if (locationId) params.set('location_id', String(locationId));

        // R10 — search_fields[] configurável via popover (paridade Blade
        // configure_search_modal.blade.php), persistido em localStorage.
        searchFields.forEach((field) => {
          params.append('search_fields[]', field);
        });

        const res = await fetch(`/products/list?${params.toString()}`, {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          signal: controller.signal,
        });

        // Guards pós-await — cleanup do useEffect ou seleção podem ter acontecido
        // enquanto o fetch estava em vôo.
        if (controller.signal.aborted) return;
        if (Date.now() - lastSelectedAtRef.current < POST_SELECT_GRACE_MS) return;

        if (!res.ok) {
          setResults([]);
          return;
        }

        const data = (await res.json()) as ProductSearchResult[];
        if (controller.signal.aborted) return;
        if (Date.now() - lastSelectedAtRef.current < POST_SELECT_GRACE_MS) return;

        setResults(Array.isArray(data) ? data : []);
        setOpen(true);
        setExpandedProductId(null);
      } catch (err) {
        if ((err as Error).name === 'AbortError') return;
        setResults([]);
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }, DEBOUNCE_MS);

    return () => {
      clearTimeout(handle);
      controller.abort();
    };
    // R10 — `searchFields` na dep array: ao alternar campos no popover,
    // re-dispara busca com novos filtros pra UX feedback imediato.
    // R7_LEGACY_FETCH_ENABLED é const estável (false) — bloco dormente (ADR 0211).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query, locationId, searchFields]);

  // Click fora fecha dropdown
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      const target = e.target as Element | null;
      // Radix renderiza PopoverContent num Portal em document.body — FORA do
      // containerRef. Sem este guard, o mousedown num item do popover de variação
      // ("Escolha o tamanho") conta como "clique fora" e dispara setOpen(false) +
      // setExpandedProductId(null) ANTES do onClick do <button> da variação rodar:
      // o Popover desmonta e a seleção nunca chega em onSelect → a variação não é
      // adicionada à venda (Larissa @ Rota Livre 2026-06-18: "ao selecionar o
      // tamanho ele não é adicionado, está sendo preciso digitar o SKU"). Digitar
      // o SKU funcionava porque cai no handler de teclado (Enter), sem clique no
      // portal. Cobre também o popover de configurar-busca (mesmo wrapper Radix).
      if (target?.closest('[data-radix-popper-content-wrapper]')) return;
      if (containerRef.current && !containerRef.current.contains(target as Node)) {
        setOpen(false);
        setExpandedProductId(null);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleKeyDown = async (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      // G3 — popover de variação aberto: Esc fecha só o popover (volta pro grupo).
      if (expandedProductId !== null) {
        setExpandedProductId(null);
        setVariationHighlightedIndex(-1);
        return;
      }
      setOpen(false);
      setExpandedProductId(null);
      setHighlightedIndex(-1);
      inputRef.current?.blur();
      return;
    }

    // G3 — navegação por seta (paridade CustomerSearchAutocomplete:170-181).
    // Quando o popover de variação está aberto, as setas navegam as VARIAÇÕES;
    // caso contrário, navegam os GRUPOS do dropdown.
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (expandedProductId !== null) {
        const grp = groups.find((g) => g.product_id === expandedProductId);
        const n = grp ? grp.variations.length : 0;
        setVariationHighlightedIndex((prev) => Math.min(prev + 1, n - 1));
        return;
      }
      if (!open && groups.length > 0) setOpen(true);
      setHighlightedIndex((prev) => Math.min(prev + 1, groups.length - 1));
      return;
    }

    if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (expandedProductId !== null) {
        setVariationHighlightedIndex((prev) => Math.max(prev - 1, 0));
        return;
      }
      setHighlightedIndex((prev) => Math.max(prev - 1, -1));
      return;
    }

    // Enter — paridade Blade (scanner físico + UX teclado).
    // Cenários:
    //   1) Scanner envia código + Enter em <50ms. Results podem estar vazios ainda
    //      (debounce 250ms não disparou). → fetch síncrono + auto-select.
    //   2) User digitou + ja viu dropdown com 1 produto + 1 variação → auto-select.
    //   3) Match SKU exato em N results → auto-select essa variação.
    //   4) Múltiplos resultados sem match exato → mantém dropdown aberto pra user escolher.
    if (e.key === 'Enter' && query.length >= MIN_QUERY_LENGTH) {
      e.preventDefault(); // evita submit acidental do form parent

      // R7 anti-race — se loading=true, um fetch SYNC já está rodando (scanner path
      // anterior). Um 2º Enter rápido (operadora insistindo) cairia no mesmo branch
      // e poderia duplicar. Ignora.
      if (loading) return;

      // G3 — popover de variação aberto: Enter seleciona a variação destacada
      // (ou a 1ª, se nenhuma foi navegada). Precede o scanner/match — o usuário
      // já está num sub-fluxo explícito de escolha de tamanho.
      if (expandedProductId !== null) {
        const grp = groups.find((g) => g.product_id === expandedProductId);
        const idx = variationHighlightedIndex >= 0 ? variationHighlightedIndex : 0;
        const v = grp?.variations[idx];
        if (v) handleSelectVariation(v);
        return;
      }

      // G3 — grupo destacado por seta: 1 variação → seleciona direto; multi → abre
      // o popover e destaca a 1ª variação. Só dispara quando o usuário navegou de
      // fato (highlightedIndex >= 0); o scanner (Enter sem seta) cai no match exato
      // abaixo com highlightedIndex == -1 — comportamento legado intacto.
      if (highlightedIndex >= 0 && groups[highlightedIndex]) {
        const grp = groups[highlightedIndex];
        const onlyVar = grp.variations[0];
        if (grp.variations.length === 1 && onlyVar) {
          handleSelectVariation(onlyVar);
        } else {
          setExpandedProductId(grp.product_id);
          setVariationHighlightedIndex(0);
        }
        return;
      }

      const q = query.trim();

      // Match exato SKU/sub_sku no results atuais (instant path).
      const exact = results.find(
        (r) => r.sku === q || r.sub_sku === q,
      );
      if (exact) {
        handleSelectVariation(exact);
        return;
      }

      // 1 grupo + 1 variação no dropdown atual.
      if (groups.length === 1 && groups[0]?.variations.length === 1) {
        const only = groups[0].variations[0];
        if (only) {
          handleSelectVariation(only);
          return;
        }
      }

      // Scanner path: results vazios mas query parece código de barras.
      // Força fetch SYNC e auto-select se 1 resultado OU match SKU exato.
      if (results.length === 0 && !loading) {
        setLoading(true);
        try {
          const fresh = await fetchProductsNow(q);
          const exactFresh = fresh.find((r) => r.sku === q || r.sub_sku === q);
          if (exactFresh) {
            handleSelectVariation(exactFresh);
            return;
          }
          if (fresh.length === 1 && fresh[0]) {
            handleSelectVariation(fresh[0]);
            return;
          }
          // Múltiplos / nenhum → atualiza state pra abrir dropdown
          setResults(fresh);
          setOpen(true);
        } finally {
          setLoading(false);
        }
      }
    }
  };

  const handleClear = () => {
    lastSelectedAtRef.current = Date.now(); // bloqueia fetch pendente reabrir dropdown
    setQuery('');
    setResults([]);
    setOpen(false);
    setExpandedProductId(null);
    setHighlightedIndex(-1);
    setVariationHighlightedIndex(-1);
    inputRef.current?.focus();
  };

  const handleSelectVariation = (variation: ProductSearchResult) => {
    // R7 anti-race — marca timestamp ANTES dos setStates pra que o useEffect debounce
    // (caso resolva tardiamente) skipe o setOpen(true) na sentinela.
    lastSelectedAtRef.current = Date.now();
    onSelect(variation);
    setQuery('');
    setResults([]);
    setOpen(false);
    setExpandedProductId(null);
    setHighlightedIndex(-1);
    setVariationHighlightedIndex(-1);
    inputRef.current?.focus();
  };

  const handleGroupClick = (group: ProductGroup) => {
    // 1 variação → adiciona direto (mesmo se variable, sem popover overhead).
    const only = group.variations[0];
    if (group.variations.length === 1 && only) {
      handleSelectVariation(only);
      return;
    }
    // >1 variação → toggla popover (open/close em click)
    setExpandedProductId((prev) => (prev === group.product_id ? null : group.product_id));
    // G3 — abrir/fechar por clique zera o destaque de variação (teclado retoma do 0).
    setVariationHighlightedIndex(-1);
  };

  return (
    <div ref={containerRef} className="relative w-full">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          ref={inputRef}
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => groups.length > 0 && setOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled || !locationId}
          // variant=shadcn pra permitir pl-9 (ícone-prefix). Default cowork tem
          // padding hardcoded `0 8px` no .cw-input que sobrepõe utilities Tailwind
          // — ícone Search fica em cima do texto. ADR UI-0015 default cowork 2026-05-27.
          variant="shadcn"
          className="pl-9 pr-16"
          aria-label="Buscar produto"
          aria-expanded={open}
          aria-haspopup="listbox"
          autoComplete="off"
        />
        {loading && (
          <Loader2 className="absolute right-10 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-muted-foreground" />
        )}
        {!loading && query.length > 0 && (
          <button
            type="button"
            onClick={handleClear}
            aria-label="Limpar busca"
            className="absolute right-10 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        )}
        {/* R10 (2026-05-28) — configure-search popover. Paridade Blade
            configure_search_modal.blade.php. Sempre visível (não depende de
            query) — Larissa pode pré-configurar antes de digitar. */}
        <Popover open={configureOpen} onOpenChange={setConfigureOpen}>
          <PopoverTrigger asChild>
            <button
              type="button"
              aria-label="Configurar campos de busca"
              className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground focus:outline-none"
              data-testid="configure-search-trigger"
            >
              <SlidersHorizontal className="h-4 w-4" />
            </button>
          </PopoverTrigger>
          <PopoverContent
            side="bottom"
            align="end"
            sideOffset={8}
            className="w-72 p-0"
          >
            <div className="border-b border-border px-3 py-2">
              <p className="text-xs font-medium text-foreground">
                Buscar produto por:
              </p>
              <p className="text-[10px] text-muted-foreground mt-0.5">
                Persistido neste navegador
              </p>
            </div>
            <ul className="py-1" role="group" aria-label="Campos de busca">
              {ALL_SEARCH_FIELDS.map((field) => {
                const checked = searchFields.includes(field.key);
                return (
                  <li key={field.key}>
                    <label htmlFor={`search-field-${field.key}`} className="flex w-full cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground">
                      <Checkbox
                        id={`search-field-${field.key}`}
                        checked={checked}
                        onCheckedChange={() => toggleSearchField(field.key)}
                        data-testid={`search-field-${field.key}`}
                      />
                      <span className="select-none">{field.label}</span>
                    </label>
                  </li>
                );
              })}
            </ul>
          </PopoverContent>
        </Popover>
      </div>

      {!locationId && (
        <p className="mt-1 text-xs text-muted-foreground">
          Selecione um local antes de buscar produtos.
        </p>
      )}

      {locationId && query.length === 0 && (
        <p className="mt-1 text-xs text-muted-foreground">
          Digite ou bipe: nome, SKU, código de barras ou lote.
        </p>
      )}

      {open && groups.length > 0 && (
        <div
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-72 overflow-auto rounded-md border border-border bg-popover shadow-md"
        >
          {groups.map((group, index) => {
            const isExpanded = expandedProductId === group.product_id;
            const isHighlighted = index === highlightedIndex; // G3 — destaque teclado
            const firstVar = group.variations[0];
            // group.variations sempre tem ≥1 (garantido pelo groupResults).
            // Type guard explícito pro TS narrow.
            if (!firstVar) return null;
            const hasMultiple = group.variations.length > 1;

            // 1 variação — render direto. Mostra `-{variation}` se variable (paridade Blade).
            if (!hasMultiple) {
              const isVariable = group.type === 'variable' && firstVar.variation;
              const displayName = isVariable ? `${group.name} - ${firstVar.variation}` : group.name;
              const skuLabel = firstVar.sub_sku ?? firstVar.sku;
              return (
                <button
                  key={`g-${group.product_id}-${firstVar.variation_id}`}
                  type="button"
                  role="option"
                  aria-selected={isHighlighted}
                  onClick={() => handleSelectVariation(firstVar)}
                  className={`flex w-full items-center justify-between px-3 py-2 text-left text-sm focus:outline-none ${
                    isHighlighted
                      ? 'bg-accent text-accent-foreground'
                      : 'hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground'
                  }`}
                >
                  <div className="flex flex-col min-w-0">
                    <span className="font-medium truncate">{displayName}</span>
                    <span className="text-xs text-muted-foreground">
                      SKU {skuLabel}
                      {firstVar.lot_number && <> · lote {firstVar.lot_number}</>}
                      {firstVar.qty_available !== undefined && (
                        <> · est. {firstVar.qty_available}{firstVar.unit ? firstVar.unit : ''}</>
                      )}
                    </span>
                  </div>
                  {firstVar.selling_price !== undefined && (
                    <span className="ml-3 shrink-0 text-sm tabular-nums">
                      {formatBRL(Number(firstVar.variation_group_price ?? firstVar.selling_price))}
                    </span>
                  )}
                </button>
              );
            }

            // >1 variações — linha agrupada + Popover side=right com lista
            return (
              <Popover
                key={`g-${group.product_id}`}
                open={isExpanded}
                onOpenChange={(o) => {
                  setExpandedProductId(o ? group.product_id : null);
                  if (!o) setVariationHighlightedIndex(-1); // G3 — fechar zera destaque
                }}
              >
                <PopoverTrigger asChild>
                  <button
                    type="button"
                    role="option"
                    aria-selected={isHighlighted}
                    aria-expanded={isExpanded}
                    aria-haspopup="listbox"
                    onClick={() => handleGroupClick(group)}
                    className={`flex w-full items-center justify-between px-3 py-2 text-left text-sm focus:outline-none ${
                      isHighlighted || isExpanded
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground'
                    }`}
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <Package className="h-4 w-4 text-muted-foreground shrink-0" aria-hidden />
                      <div className="flex flex-col min-w-0">
                        <span className="font-medium truncate">{group.name}</span>
                        <span className="text-xs text-muted-foreground">
                          {group.variations.length} tamanhos
                          {group.total_qty_available > 0 && (
                            <> · est. total {group.total_qty_available}</>
                          )}
                        </span>
                      </div>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      {group.display_price !== undefined && (
                        <span className="text-sm tabular-nums">{formatBRL(group.display_price)}</span>
                      )}
                      <ChevronRight
                        className={`h-4 w-4 text-muted-foreground transition-transform ${
                          isExpanded ? 'rotate-90' : ''
                        }`}
                        aria-hidden
                      />
                    </div>
                  </button>
                </PopoverTrigger>
                <PopoverContent
                  side="right"
                  align="start"
                  sideOffset={8}
                  className="w-80 p-0"
                  // Foco permanece no input principal: o popover é navegado pelas setas
                  // do próprio input (G3). Prevenir open+close auto-focus evita que o
                  // Radix mande o foco pro PopoverContent (abrir) ou pro trigger (fechar)
                  // — qualquer um dos dois quebraria a navegação por teclado seguinte.
                  onOpenAutoFocus={(e) => e.preventDefault()}
                  onCloseAutoFocus={(e) => e.preventDefault()}
                >
                  <div className="border-b border-border px-3 py-2">
                    <p className="text-xs font-medium text-muted-foreground">
                      Escolha o tamanho de {group.name}
                    </p>
                  </div>
                  <ul role="listbox" aria-label={`Variações de ${group.name}`} className="max-h-72 overflow-auto">
                    {group.variations.map((v, vIndex) => (
                      <li key={`v-${v.variation_id}`}>
                        <button
                          type="button"
                          role="option"
                          aria-selected={vIndex === variationHighlightedIndex}
                          onClick={() => handleSelectVariation(v)}
                          className={`flex w-full items-center justify-between px-3 py-2 text-left text-sm focus:outline-none ${
                            vIndex === variationHighlightedIndex
                              ? 'bg-accent text-accent-foreground'
                              : 'hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground'
                          }`}
                        >
                          <div className="flex flex-col min-w-0">
                            <span className="font-medium truncate">
                              {v.variation ?? 'Padrão'}
                            </span>
                            <span className="text-xs text-muted-foreground">
                              SKU {v.sub_sku ?? v.sku}
                              {v.lot_number && <> · lote {v.lot_number}</>}
                              {v.qty_available !== undefined && (
                                <> · est. {v.qty_available}{v.unit ?? ''}</>
                              )}
                            </span>
                          </div>
                          {v.selling_price !== undefined && (
                            <span className="ml-3 shrink-0 text-sm tabular-nums">
                              {formatBRL(Number(v.variation_group_price ?? v.selling_price))}
                            </span>
                          )}
                        </button>
                      </li>
                    ))}
                  </ul>
                </PopoverContent>
              </Popover>
            );
          })}
        </div>
      )}

      {open && query.length >= MIN_QUERY_LENGTH && groups.length === 0 && !loading && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-md">
          Nenhum produto encontrado para "{query}".
        </div>
      )}
    </div>
  );
}
