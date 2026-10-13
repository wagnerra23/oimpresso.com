// Corpo do formulário de função (thread sistema/playbook/02, F3-2) — fora do .tsx para o react-refresh.

export interface ItemDoEditor { campo: string; tipo: 'checkbox' | 'radio'; valor: string; rotulo: string; marcado: boolean }
export interface Editor { id: number | null; nome: string; grupos: { titulo: string; itens: ItemDoEditor[] }[] }

export const chave = (i: ItemDoEditor) => `${i.campo}|${i.valor}`;

/** O corpo que o `<form>` da Blade mandaria com estes controles marcados. */
export function corpoDoFormulario(nome: string, itens: ItemDoEditor[], marcados: Set<string>): Record<string, unknown> {
  const permissions: string[] = [];
  const spg: string[] = [];
  const radio: Record<string, string> = {};
  let garcom = false;
  for (const i of itens) {
    if (!marcados.has(chave(i))) continue;
    if (i.campo === 'permissions[]') permissions.push(i.valor);
    else if (i.campo === 'spg_permissions[]') spg.push(i.valor);
    else if (i.campo === 'is_service_staff') garcom = true;
    else {
      const grupo = /^radio_option\[(.+)\]$/.exec(i.campo)?.[1];
      if (grupo) radio[grupo] = i.valor;
    }
  }
  return { name: nome.trim(), permissions, spg_permissions: spg, radio_option: radio, ...(garcom ? { is_service_staff: 1 } : {}) };
}
