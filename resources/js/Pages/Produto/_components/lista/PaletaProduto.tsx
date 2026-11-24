// Ctrl+K / ⌘K da lista de produtos: "Telas do módulo" + "Produtos", como a `PaletaProduto` do
// protótipo (`prototipo-ui/cowork/Felipe/produto-blade.jsx`). Sem ela, o atalho abria a busca
// geral de tarefas da Forja (`Components/CommandPalette`, dono do atalho no `AppShellV2`).
//
// - Telas: lidas do menu do shell (`shell.menu`), o mesmo que o menu lateral mostra — vem do
//   `AdminSidebarMenu`, já filtrado pela permissão de cada tela. Lista escrita aqui à mão
//   envelheceria e mostraria link que dá 403.
// - Produtos: busca em `/products/list-no-variation` (nome, SKU, sub-SKU; escopo da empresa no
//   servidor), a partir de 2 letras.
//
// O atalho é tomado da paleta global pelo `defaultPrevented`: a escuta aqui é de captura, roda
// antes da do shell, e o shell ignora o evento já tratado.

import { useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/Components/ui/command';
import type { MenuItem, SharedProps } from '@/Types';

type ProdutoAchado = { id: number; name: string; sku: string | null };

/** Caminho de um href do menu (`action()` gera URL absoluta). */
function caminho(href: string): string {
  try {
    return new URL(href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
  } catch {
    return href;
  }
}

/** O grupo do menu que contém a lista de produtos (`/products`) — as telas do módulo. */
function telasDoModulo(menu: MenuItem[] | undefined): { label: string; href: string }[] {
  for (const grupo of menu ?? []) {
    const filhos = grupo.children ?? [];
    if (filhos.some((f) => f.href && caminho(f.href) === '/products')) {
      return filhos.filter((f) => f.href && f.href !== '#').map((f) => ({ label: f.label, href: f.href as string }));
    }
  }
  return [];
}

interface Props {
  /** Abre a tela (Inertia ou página inteira, como o resto da lista). */
  irPara: (url: string) => void;
  /** Abre o produto escolhido. */
  abrirProduto: (id: number) => void;
}

export default function PaletaProduto({ irPara, abrirProduto }: Props) {
  const { shell } = usePage<SharedProps>().props;
  const telas = useMemo(() => telasDoModulo(shell?.menu), [shell?.menu]);
  const [aberta, setAberta] = useState(false);
  const [busca, setBusca] = useState('');
  const [produtos, setProdutos] = useState<ProdutoAchado[]>([]);
  const [buscando, setBuscando] = useState(false);
  const abortar = useRef<AbortController | null>(null);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        setAberta((v) => !v);
      }
    };
    window.addEventListener('keydown', onKey, true);
    return () => window.removeEventListener('keydown', onKey, true);
  }, []);

  useEffect(() => {
    if (!aberta) {
      setBusca('');
      setProdutos([]);
    }
  }, [aberta]);

  useEffect(() => {
    abortar.current?.abort();
    const termo = busca.trim();
    if (!aberta || termo.length < 2) {
      setProdutos([]);
      setBuscando(false);
      return;
    }
    setBuscando(true);
    const ctrl = new AbortController();
    abortar.current = ctrl;
    const t = setTimeout(() => {
      fetch(`/products/list-no-variation?term=${encodeURIComponent(termo)}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: ctrl.signal,
      })
        .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
        .then((lista: ProdutoAchado[]) => {
          setProdutos(Array.isArray(lista) ? lista.slice(0, 8) : []);
          setBuscando(false);
        })
        .catch((err) => {
          if (err?.name === 'AbortError') return;
          setProdutos([]);
          setBuscando(false);
        });
    }, 220);
    return () => {
      clearTimeout(t);
      ctrl.abort();
    };
  }, [busca, aberta]);

  return (
    <CommandDialog
      open={aberta}
      onOpenChange={setAberta}
      title="Ir para"
      description="Telas do módulo de produtos e busca de produto por nome ou SKU"
    >
      <CommandInput placeholder="Tela do módulo, ou nome / SKU do produto…" value={busca} onValueChange={setBusca} />
      <CommandList>
        <CommandEmpty>{buscando ? 'Buscando…' : 'Nada encontrado.'}</CommandEmpty>
        {telas.length > 0 && (
          <CommandGroup heading="Telas do módulo">
            {telas.map((t) => (
              <CommandItem
                key={t.href}
                value={`tela ${t.label}`}
                onSelect={() => {
                  setAberta(false);
                  irPara(t.href);
                }}
              >
                {t.label}
              </CommandItem>
            ))}
          </CommandGroup>
        )}
        {produtos.length > 0 && (
          <CommandGroup heading="Produtos">
            {produtos.map((p) => (
              <CommandItem
                key={p.id}
                // O servidor já filtrou por nome/SKU; o termo entra no valor para o cmdk não esconder.
                value={`produto ${p.id} ${p.name} ${p.sku ?? ''} ${busca}`}
                onSelect={() => {
                  setAberta(false);
                  abrirProduto(p.id);
                }}
              >
                <span className="truncate">{p.name}</span>
                {p.sku && <span className="ml-auto font-mono text-xs text-muted-foreground">{p.sku}</span>}
              </CommandItem>
            ))}
          </CommandGroup>
        )}
      </CommandList>
    </CommandDialog>
  );
}
