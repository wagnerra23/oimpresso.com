// @patrimonio · devolução de foco ao fechar os drawers de Alocações (thread 18 §D.5).
//
// Fora da Page de propósito: `react-refresh/only-export-components` só aceita o componente
// exportado num arquivo de Page.

import type { Formulario } from './Drawers';

/**
 * Para onde o foco volta quando o drawer fecha.
 *
 * Fechar o drawer é uma NAVEGAÇÃO (de `/create` ou `/edit` de volta pra lista), não o `close` do
 * `Sheet` — então a devolução de foco do Radix não tem a quem devolver quando a página foi aberta
 * direto pela URL. Medido em produção em 2026-09-30: `/asset/allocation/create` carregado direto,
 * Cancelar → foco no `BODY`. O alvo é o botão que abre aquele drawer: o da própria linha
 * (editar/devolver), ou "Alocar recurso". A linha vem na prop DEFERIDA, então ela pode ainda não
 * existir no primeiro quadro — por isso tenta por alguns quadros antes de cair no botão do header.
 */
export function alvoDoFoco(formulario: Formulario | undefined): string[] {
  const header = '#patrimonio-alocar-recurso';
  if (!formulario || formulario.modo === 'alocar') return [header];
  const id = formulario.modo === 'devolver' ? formulario.alocacao.id : formulario.alocacao?.id;
  if (!id) return [header];
  return [`[data-acao="${formulario.modo === 'devolver' ? 'devolver' : 'editar'}-${id}"]`, header];
}

export function devolverFoco(seletores: string[], tentativas = 30): void {
  const [preferido, ...resto] = seletores;
  const el = preferido ? document.querySelector<HTMLElement>(preferido) : null;
  if (el) { el.focus(); return; }
  if (tentativas > 0 && resto.length) {
    requestAnimationFrame(() => devolverFoco(seletores, tentativas - 1));
    return;
  }
  if (resto.length) devolverFoco(resto, 0);
}
