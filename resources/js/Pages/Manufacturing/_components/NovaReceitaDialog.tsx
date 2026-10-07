// Janela "Nova receita" — US-MANU-006, decisões [W] 2026-10-06 (SPEC da Fabricação).
//
// Antes o botão levava a `/manufacturing/recipe/create`, que devolve só o miolo de um modal
// Bootstrap: a busca de produto dependia do select2 + scripts do layout Blade, que a tela React
// não carrega — em produção a busca não carregava. Esta janela substitui aquele modal e entrega
// para o MESMO editor legado (`/manufacturing/add-ingredient`): ela só LÊ, quem grava continua
// sendo o editor (charter: "Não escreve nada").
//
// Forma: `MfgNovaReceita` do protótipo (`manufacturing-recipe.jsx`), com as mudanças que o [W]
// decidiu depois dele:
//  (a) Categoria e Subcategoria vêm do PRODUTO, só exibidas, com link para editar no cadastro —
//      o protótipo as tinha como campos da receita;
//  (b) "Copiar de outra receita" leva ingredientes, quantidades, desperdício, custo extra e
//      instruções, e NÃO o preço de venda;
//  (c) produto que já tem receita avisa logo que é escolhido e abre a receita existente, em vez
//      de bloquear (o protótipo bloqueava).
// "Quantidade produzida" e "Unidade" do protótipo NÃO estão aqui: o editor legado já pede os
// dois, e levá-los por esta janela exigiria mudar o editor — fica para a US-MANU-006 do editor.
//
// Busca: papel "combobox" → `Command` do DS com `shouldFilter={false}` (busca no servidor), mesmo
// idioma do `Purchase/_components/GradeProductCombobox.tsx` (REGISTRY_DS_COMPONENTES §Combobox).

import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertCircle, Loader2, X } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Inline } from '@/Components/layout/inline';
import { Stack } from '@/Components/layout/stack';

/** Linha de `GET /manufacturing/nova-receita/produtos` (`RecipeBomService::buscarProdutosParaReceita`). */
export interface ProdutoNovaReceita {
  variation_id: number;
  product_id: number;
  nome: string;
  sku: string;
  categoria: string | null;
  subcategoria: string | null;
  /** (c) a receita que o produto já tem, ou `null`. */
  receita_id: number | null;
}

export interface ReceitaParaCopiar {
  id: number;
  nome: string;
}

const ROTA_BUSCA = '/manufacturing/nova-receita/produtos';
const ROTA_EDITOR = '/manufacturing/add-ingredient';
const DEBOUNCE_MS = 250;
const MIN_QUERY = 2;
/** Radix Select não aceita `value=""` — sentinela para "começar vazia". */
const SEM_COPIA = 'nenhuma';

/** Endereço do editor legado. Sem cópia quando o produto já tem receita (abre a existente). */
function urlDoEditor(variationId: number, copiarDe: number | null): string {
  const q = new URLSearchParams({ variation_id: String(variationId) });
  if (copiarDe) q.set('copy_recipe_id', String(copiarDe));
  return `${ROTA_EDITOR}?${q.toString()}`;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export default function NovaReceitaDialog({ open, onOpenChange }: Props) {
  const [query, setQuery] = useState('');
  const [opcoes, setOpcoes] = useState<ProdutoNovaReceita[]>([]);
  const [buscando, setBuscando] = useState(false);
  const [erroBusca, setErroBusca] = useState(false);
  const [produto, setProduto] = useState<ProdutoNovaReceita | null>(null);
  const [copiarDe, setCopiarDe] = useState<string>(SEM_COPIA);
  const [receitas, setReceitas] = useState<ReceitaParaCopiar[] | null>(null);

  // Ao abrir: zera a janela e pede a lista de "copiar de" num reload parcial (só ela é calculada).
  useEffect(() => {
    if (!open) return;
    setQuery('');
    setOpcoes([]);
    setProduto(null);
    setCopiarDe(SEM_COPIA);
    setErroBusca(false);
    router.reload({
      only: ['receitas_copia'],
      onSuccess: (page) => {
        const lista = (page.props as { receitas_copia?: ReceitaParaCopiar[] }).receitas_copia;
        setReceitas(Array.isArray(lista) ? lista : []);
      },
    });
  }, [open]);

  // Busca no servidor, com debounce e cancelamento da requisição anterior.
  useEffect(() => {
    const termo = query.trim();
    if (termo.length < MIN_QUERY) {
      setOpcoes([]);
      setErroBusca(false);
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
        const data = (await res.json()) as { produtos?: ProdutoNovaReceita[] };
        setOpcoes(Array.isArray(data.produtos) ? data.produtos : []);
        setErroBusca(false);
      } catch (err) {
        if ((err as Error).name !== 'AbortError') {
          setOpcoes([]);
          setErroBusca(true);
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

  const temReceita = produto?.receita_id != null;
  // Trocar produto recomeça a busca: o texto anterior ficava no campo e o que se digitava a seguir
  // se juntava a ele ("Documenta" + "ca" = "Documentaca"), visto em produção em 2026-10-07.
  const trocarProduto = () => {
    setProduto(null);
    setQuery('');
    setOpcoes([]);
    setCopiarDe(SEM_COPIA);
  };
  const continuar = () => {
    if (!produto) return;
    // O editor é Blade (página inteira, com o layout dele) — navegação de verdade, não Inertia.
    window.location.href = urlDoEditor(produto.variation_id, temReceita || copiarDe === SEM_COPIA ? null : Number(copiarDe));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      {/* `grid-cols-[minmax(0,1fr)]`: o `DialogContent` é grid, e a coluna implícita crescia até o
          nome de produto mais comprido — medido em produção (2026-10-07): lista de 740px numa janela
          de 520px, empurrando os botões pra fora. Com a coluna limitada, o nome corta com "…". */}
      <DialogContent className="grid-cols-[minmax(0,1fr)] sm:max-w-[520px]" data-screen-label="Fabricação · Nova receita">
        <DialogHeader>
          <DialogTitle>Nova receita</DialogTitle>
          <DialogDescription>A receita pertence a um produto do catálogo.</DialogDescription>
        </DialogHeader>

        <Stack gap={3}>
          {!produto ? (
            <Stack gap={1}>
              <Label htmlFor="nova-receita-produto">Produto</Label>
              <Command shouldFilter={false} className="rounded-md border">
                <div className="relative">
                  <CommandInput
                    id="nova-receita-produto"
                    value={query}
                    onValueChange={setQuery}
                    placeholder="Buscar por nome ou SKU…"
                    autoFocus
                  />
                  {buscando && (
                    <Loader2
                      aria-hidden="true"
                      className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                    />
                  )}
                </div>
                {query.trim().length >= MIN_QUERY && (
                  <CommandList>
                    {!buscando && (
                      <CommandEmpty>
                        {erroBusca ? 'Não foi possível buscar agora. Tente de novo.' : 'Nenhum produto encontrado.'}
                      </CommandEmpty>
                    )}
                    {opcoes.map((p) => (
                      <CommandItem key={p.variation_id} value={String(p.variation_id)} onSelect={() => setProduto(p)}>
                        <span className="min-w-0 flex-1">
                          <span className="block truncate">{p.nome}</span>
                          <span className="block truncate text-xs text-muted-foreground">
                            {p.sku}
                            {p.receita_id != null && ' · já tem receita'}
                          </span>
                        </span>
                      </CommandItem>
                    ))}
                  </CommandList>
                )}
              </Command>
            </Stack>
          ) : (
            <Stack gap={1}>
              <Label>Produto</Label>
              <Inline gap={2} justify="between" className="rounded-md border px-3 py-2" data-testid="produto-escolhido">
                <span className="min-w-0">
                  <span className="block truncate font-medium">{produto.nome}</span>
                  <span className="block truncate text-xs text-muted-foreground">{produto.sku}</span>
                </span>
                <Button variant="ghost" size="sm" onClick={trocarProduto} aria-label="Trocar produto">
                  <X aria-hidden="true" className="size-4" />
                </Button>
              </Inline>
            </Stack>
          )}

          {produto && temReceita && (
            <Alert>
              <AlertCircle />
              <AlertTitle>Este produto já tem receita</AlertTitle>
              <AlertDescription>Abra a receita existente para ver ou alterar os ingredientes.</AlertDescription>
            </Alert>
          )}

          {produto && (
            <Stack gap={1}>
              <Inline gap={3} className="text-sm">
                <span>
                  <span className="text-muted-foreground">Categoria: </span>
                  {produto.categoria ?? '—'}
                </span>
                <span>
                  <span className="text-muted-foreground">Subcategoria: </span>
                  {produto.subcategoria ?? '—'}
                </span>
              </Inline>
              <p className="text-xs text-muted-foreground">
                Categoria e subcategoria são do produto.{' '}
                <a href={`/products/${produto.product_id}/edit`} className="underline underline-offset-2">
                  Editar no cadastro do produto
                </a>
              </p>
            </Stack>
          )}

          {produto && !temReceita && (
            <Stack gap={1}>
              <Label htmlFor="nova-receita-copiar">Copiar de outra receita</Label>
              <Select value={copiarDe} onValueChange={setCopiarDe}>
                <SelectTrigger id="nova-receita-copiar" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SEM_COPIA}>Começar vazia</SelectItem>
                  {(receitas ?? [])
                    .filter((r) => r.id > 0 && r.nome)
                    .map((r) => (
                      <SelectItem key={r.id} value={String(r.id)}>
                        {r.nome}
                      </SelectItem>
                    ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                Copia ingredientes, quantidades, desperdício, custo extra e instruções. O preço de venda
                não é copiado.
              </p>
            </Stack>
          )}
        </Stack>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button disabled={!produto} onClick={continuar}>
            {temReceita ? 'Abrir receita existente' : 'Continuar para os ingredientes'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
