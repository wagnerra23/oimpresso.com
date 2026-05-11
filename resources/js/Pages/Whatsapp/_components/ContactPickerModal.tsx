import { useEffect, useRef, useState } from 'react';
import { Search, Phone, Mail, X, UserCheck } from 'lucide-react';

import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

import Avatar from './Avatar';
import type { ContactSearchResult } from './helpers';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Route name pro GET de search (ex: `atendimento.inbox.contacts.search`). */
  searchRouteName: string;
  /** Callback quando atendente seleciona um contato — recebe `contact_id`. */
  onSelect: (contactId: number) => void;
  /** Phone do customer pra hint inicial (preenche search se vazio). */
  customerPhone?: string;
}

/**
 * Modal de busca de Contact UltimatePOS pra vincular à conversa (US-WA-064).
 *
 * UX:
 *  - Input com debounce 250ms (mesmo padrão do search global do Inbox)
 *  - 1ª render: pré-preenche query com customer_phone pra match óbvio
 *  - Lista de até 15 results com avatar + nome + phones + tipo badge
 *  - Click vincula via callback + fecha modal
 *  - Esc fecha
 *
 * Multi-tenant Tier 0: backend filtra por business_id, frontend nunca
 * vê CRM cross-tenant. PII sensível (CPF/CNPJ) não é retornado pelo
 * endpoint — só display data.
 */
export default function ContactPickerModal({
  open, onOpenChange, searchRouteName, onSelect, customerPhone,
}: Props) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<ContactSearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const abortRef = useRef<AbortController | null>(null);

  // Pré-preenche query com customer_phone quando abre (atendente já busca
  // pelo phone do cliente — caso mais comum)
  useEffect(() => {
    if (open && !query && customerPhone) {
      // Remove + e busca pelos últimos 9 digitos (ignora DDI/DDD prefixos)
      const cleaned = customerPhone.replace(/\D/g, '').slice(-9);
      if (cleaned.length >= 4) setQuery(cleaned);
    }
    if (open) {
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [open, customerPhone]);

  // Esc fecha
  useEffect(() => {
    if (!open) return;
    function handler(e: KeyboardEvent) {
      if (e.key === 'Escape') onOpenChange(false);
    }
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onOpenChange]);

  // Debounce search 250ms — cancela request anterior se nova digitação
  useEffect(() => {
    if (!open) return;
    if (query.trim().length < 2) {
      setResults([]);
      return;
    }
    const timer = setTimeout(async () => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;
      setLoading(true);
      try {
        const res = await fetch(
          `${route(searchRouteName)}?q=${encodeURIComponent(query)}`,
          { signal: controller.signal, headers: { Accept: 'application/json' } },
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        setResults(json.contacts ?? []);
      } catch (e: any) {
        if (e.name !== 'AbortError') {
          console.error('[ContactPickerModal] search failed', e);
          setResults([]);
        }
      } finally {
        setLoading(false);
      }
    }, 250);
    return () => clearTimeout(timer);
  }, [query, open, searchRouteName]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center pt-[10vh] bg-black/40 backdrop-blur-sm"
      onClick={(e) => {
        if (e.target === e.currentTarget) onOpenChange(false);
      }}
      role="dialog"
      aria-modal="true"
      aria-label="Vincular contato à conversa"
    >
      <Card className="w-full max-w-lg max-h-[80vh] flex flex-col mx-4 shadow-2xl">
        <div className="border-b px-4 py-3 flex items-center justify-between gap-2 shrink-0">
          <div className="font-semibold text-sm inline-flex items-center gap-2">
            <UserCheck size={16} aria-hidden />
            Vincular contato
          </div>
          <Button
            variant="ghost"
            size="sm"
            className="h-7 w-7 p-0"
            onClick={() => onOpenChange(false)}
            aria-label="Fechar"
          >
            <X size={14} aria-hidden />
          </Button>
        </div>

        <div className="p-3 border-b shrink-0">
          <div className="relative">
            <Input
              ref={inputRef}
              type="search"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Buscar por nome, telefone, e-mail…"
              className="pl-8"
              aria-label="Buscar contato"
            />
            <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none" aria-hidden />
          </div>
          <div className="text-[11px] text-muted-foreground mt-1.5">
            {loading ? 'Buscando…' : query.length >= 2 ? `${results.length} resultado${results.length !== 1 ? 's' : ''}` : 'Digite ao menos 2 caracteres'}
          </div>
        </div>

        <div className="flex-1 overflow-y-auto">
          {results.length === 0 && query.trim().length >= 2 && !loading && (
            <div className="p-8 text-center text-sm text-muted-foreground">
              Nenhum contato encontrado para "{query}".
              <div className="text-xs mt-2 opacity-70">
                Crie o contato em <a href="/contacts/create" className="underline">/contacts/create</a> e volte aqui.
              </div>
            </div>
          )}
          {results.map((c) => (
            <button
              key={c.id}
              type="button"
              onClick={() => {
                onSelect(c.id);
                onOpenChange(false);
              }}
              className="w-full text-left flex items-center gap-3 px-4 py-2.5 border-b border-border hover:bg-accent transition-colors"
            >
              <Avatar name={c.name} size="sm" />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-1.5 text-sm">
                  <span className="font-medium truncate">{c.name}</span>
                  <ContactTypeBadge type={c.type} />
                </div>
                <div className="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                  {c.mobile && <span className="inline-flex items-center gap-1"><Phone size={10} aria-hidden />{c.mobile}</span>}
                  {c.email && <span className="inline-flex items-center gap-1 truncate"><Mail size={10} aria-hidden />{c.email}</span>}
                </div>
                {c.supplier_business_name && (
                  <div className="text-[10px] text-muted-foreground italic mt-0.5 truncate">
                    {c.supplier_business_name}
                  </div>
                )}
              </div>
            </button>
          ))}
        </div>

        <div className="border-t px-4 py-2.5 text-[11px] text-muted-foreground shrink-0">
          Não achou o contato? <a href="/contacts/create" target="_blank" className="underline">Criar novo</a> no UltimatePOS.
        </div>
      </Card>
    </div>
  );
}

function ContactTypeBadge({ type }: { type: string }) {
  const map: Record<string, { label: string; cls: string }> = {
    customer: { label: 'Cliente',    cls: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' },
    supplier: { label: 'Fornecedor', cls: 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400' },
    both:     { label: 'Cli+Forn',    cls: 'bg-purple-50 text-purple-700 dark:bg-purple-950/30 dark:text-purple-400' },
    lead:     { label: 'Lead',        cls: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' },
  };
  const conf = map[type] ?? { label: type, cls: 'bg-slate-100 text-slate-700' };
  return (
    <span className={`text-[9px] px-1.5 py-0.5 rounded-full uppercase tracking-wide font-medium shrink-0 ${conf.cls}`}>
      {conf.label}
    </span>
  );
}
