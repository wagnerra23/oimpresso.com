// @patrimonio · devolve o foco a um alvo que pode ainda não existir no primeiro quadro.
//
// Compartilhado por Alocações (thread 18) e Manutenções (thread 19): nas duas telas fechar o
// drawer é NAVEGAÇÃO de volta pra lista, e a devolução de foco do Radix não tem trigger quando
// a página foi aberta direto pela URL. A lista vem em prop DEFERIDA, então o botão da linha
// pode chegar alguns quadros depois — tenta o preferido, e só então cai nos de reserva.

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
