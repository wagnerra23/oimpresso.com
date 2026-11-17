// Rascunho automático do Novo produto e aviso ao sair com alterações não salvas (UC-PCAD-30 ·
// UC-PEDIT-30), como no protótipo (produto-blade-forms.jsx: "Rascunho: o cadastro é longo —
// recarregar a página não pode perder o trabalho", `beforeunload` e "Rascunho salvo automaticamente").
//
// O rascunho fica no navegador, numa chave por empresa e usuário que o servidor manda
// (ProductController@create `rascunho`): uma empresa nunca recupera o rascunho de outra.
// Arquivos (imagem e folheto) não entram no rascunho.

import { useEffect, useRef } from 'react';

export const PREFIXO_RASCUNHO = 'oimpresso.produto.rascunho.';

export interface Rascunho {
  /** Quando foi gravado (ISO). */
  em: string;
  form: Record<string, unknown>;
  preco?: unknown;
  politica?: unknown;
  armazenagem?: unknown;
  metodoCusto?: unknown;
  /** UC-PCAD-32: a grade do produto variável. */
  grade?: unknown;
}

/** O formulário sem os arquivos: File não vira JSON e não volta. */
export function semArquivos(form: Record<string, unknown>): Record<string, unknown> {
  const fora: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(form)) {
    if (typeof File !== 'undefined' && v instanceof File) continue;
    if (k === 'image' || k === 'product_brochure') continue;
    fora[k] = v;
  }
  return fora;
}

export function lerRascunho(chave: string | undefined): Rascunho | null {
  if (!chave) return null;
  try {
    const bruto = window.localStorage.getItem(PREFIXO_RASCUNHO + chave);
    if (!bruto) return null;
    const r = JSON.parse(bruto) as Rascunho;
    return r && typeof r === 'object' && r.form && typeof r.form === 'object' ? r : null;
  } catch {
    return null;
  }
}

export function gravarRascunho(chave: string | undefined, r: Omit<Rascunho, 'em'>, agora = new Date()): string | null {
  if (!chave) return null;
  try {
    window.localStorage.setItem(PREFIXO_RASCUNHO + chave, JSON.stringify({ ...r, em: agora.toISOString() }));
    return agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  } catch {
    return null;
  }
}

export function apagarRascunho(chave: string | undefined): void {
  if (!chave) return;
  try {
    window.localStorage.removeItem(PREFIXO_RASCUNHO + chave);
  } catch {
    // navegador sem armazenamento: nada a apagar
  }
}

export const PERGUNTA_SAIR = 'Há alterações não salvas neste produto. Sair mesmo assim?';

/**
 * Avisa ao sair com alterações não salvas: fechar ou recarregar a aba (`beforeunload`) e navegar
 * por link dentro do sistema (visita GET do Inertia). Não pergunta no próprio salvar (POST/PUT) nem
 * nas recargas parciais da tela (ex.: a lista de unidades depois do "+"). `liberar()` desliga o aviso
 * para a navegação que vem depois de salvar.
 */
export function useAvisoAoSair(sujo: boolean): { liberar: () => void; travar: () => void } {
  const liberado = useRef(false);
  useEffect(() => {
    if (!sujo) return;
    const antesDeFechar = (e: BeforeUnloadEvent) => {
      if (liberado.current) return;
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', antesDeFechar);
    // O Inertia dispara `inertia:before` no document antes de cada visita, e cancelar o evento
    // cancela a visita (@inertiajs/core `fireBeforeEvent`, cancelable).
    const antesDeNavegar = (evento: Event) => {
      if (liberado.current) return;
      const visita = ((evento as CustomEvent).detail?.visit ?? {}) as { method?: string; only?: string[] };
      if (String(visita.method ?? 'get').toLowerCase() !== 'get') return;
      if (Array.isArray(visita.only) && visita.only.length > 0) return;
      if (!window.confirm(PERGUNTA_SAIR)) evento.preventDefault();
    };
    document.addEventListener('inertia:before', antesDeNavegar);
    return () => {
      window.removeEventListener('beforeunload', antesDeFechar);
      document.removeEventListener('inertia:before', antesDeNavegar);
    };
  }, [sujo]);
  return {
    liberar: () => {
      liberado.current = true;
    },
    travar: () => {
      liberado.current = false;
    },
  };
}
