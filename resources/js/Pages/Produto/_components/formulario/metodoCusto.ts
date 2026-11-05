// "Método de custo" do formulário de produto (US-COM-024 · UC-FLX-38).
//
// Quem desenha e grava é o módulo Compras, pelos ganchos do core (`product_form_part` e
// `after_product_saved`, Modules/Compras/Http/Controllers/DataController.php). O formulário Blade já
// tinha o campo; aqui ele chega pela prop `metodoCusto` do ProductController e sai no envio com os
// mesmos dois campos do Blade: `has_module_data` (sem ele o core não chama os ganchos) e
// `compras_metodo_custo`. Quem decide o que gravar continua sendo o MetodoCustoDoProduto.

/** O que o ProductController entrega; `null` quando o Compras não está no pacote da empresa. */
export interface MetodoCustoDoFormulario {
  /** `medio`, `ultimo`, `''` (não definido) ou `misto` (variações com métodos diferentes). */
  atual: string;
  /** A regra da empresa em Fluxos → Compra: `medio`, `ultimo` ou `por_insumo`. */
  empresa: string;
  /** O usuário tem `purchase.update`. Sem isso o campo aparece desabilitado e não vai no envio. */
  editavel: boolean;
}

/** O Select do Radix não aceita item com valor vazio: "Não definido" é `''` no envio. */
export const NAO_DEFINIDO = 'nao-definido';

/** As opções do campo, na ordem do Blade. `misto` só aparece quando é o estado atual. */
export function opcoesDoMetodo(atual: string): Array<{ valor: string; rotulo: string }> {
  const opcoes = [
    { valor: NAO_DEFINIDO, rotulo: 'Não definido' },
    { valor: 'medio', rotulo: 'Médio' },
    { valor: 'ultimo', rotulo: 'Último custo' },
  ];
  return atual === 'misto'
    ? [{ valor: 'misto', rotulo: 'Variações com métodos diferentes (manter)' }, ...opcoes]
    : opcoes;
}

const METODO_DA_EMPRESA: Record<string, string> = {
  medio: 'Custo médio ponderado',
  ultimo: 'Último preço de compra',
  por_insumo: 'Definido em cada insumo',
};

/** O texto "Hoje a empresa usa: …", com o mesmo rótulo do Blade. */
export function metodoDaEmpresa(empresa: string): string {
  return METODO_DA_EMPRESA[empresa] ?? empresa;
}

/** O valor inicial do campo: o método gravado, ou "Não definido". */
export function valorInicialDoMetodo(metodo: MetodoCustoDoFormulario | null | undefined): string {
  return metodo?.atual ? metodo.atual : NAO_DEFINIDO;
}

/**
 * Os campos que o envio leva. Nada quando o campo não existe para a empresa, não é editável ou não
 * mudou: campo ausente não muda o método de nenhuma variação, como no Blade sem o campo. Mandar só
 * quando mudou segue a regra do Editar para valores (UC-PEDIT-16): salvar sem mexer não regrava nada.
 */
export function camposDoMetodoCusto(
  metodo: MetodoCustoDoFormulario | null | undefined,
  valor: string,
): { has_module_data?: 1; compras_metodo_custo?: string } {
  if (!metodo || !metodo.editavel || valor === valorInicialDoMetodo(metodo)) return {};
  return { has_module_data: 1, compras_metodo_custo: valor === NAO_DEFINIDO ? '' : valor };
}
