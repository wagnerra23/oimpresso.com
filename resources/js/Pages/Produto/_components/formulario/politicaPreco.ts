// "Política de preço" do formulário de produto (CU-PROD-21), na forma do protótipo
// (produto-blade-forms.jsx, card "Impostos e preços"): o que acontece com o preço de venda quando o
// custo dos insumos muda na compra. Os nomes são os de Fluxos e da Fabricação (fsm-regras.jsx,
// manufacturing-recipe.jsx). Esta tela só grava a escolha; quem a lê é o recebimento da compra.

export type PoliticaPreco = 'fixo' | 'acompanha' | 'margem';

export interface EstadoPolitica {
  politica: PoliticaPreco;
  /** O que a pessoa digitou, com vírgula ou ponto. Só vale com a política `margem`. */
  margemAlvo: string;
}

/** As três opções, na ordem e com os nomes do protótipo. */
export const OPCOES_DE_POLITICA: Array<{ valor: PoliticaPreco; rotulo: string }> = [
  { valor: 'fixo', rotulo: 'Preço fixo — só a margem muda' },
  { valor: 'acompanha', rotulo: 'Acompanha o custo — mantém o markup' },
  { valor: 'margem', rotulo: 'Margem alvo — recalcula pelo custo' },
];

function ehPolitica(v: unknown): v is PoliticaPreco {
  return v === 'fixo' || v === 'acompanha' || v === 'margem';
}

/**
 * O estado inicial. Produto novo começa com Preço fixo (resposta do [W] na US-PROD-030 e o padrão
 * do protótipo). No Editar vem o que está gravado; a margem aparece com vírgula, como se digita.
 */
export function politicaInicial(gravado?: { politica?: string | null; margemAlvoPct?: number | null }): EstadoPolitica {
  const politica = ehPolitica(gravado?.politica) ? gravado.politica : 'fixo';
  const margem = gravado?.margemAlvoPct;
  return {
    politica,
    margemAlvo: politica === 'margem' && margem != null ? String(margem).replace('.', ',') : '',
  };
}

/** A margem como o servidor espera: ponto decimal, sem espaços. */
function margemDoEnvio(margemAlvo: string): string {
  return margemAlvo.trim().replace(',', '.');
}

/**
 * Os campos que o envio leva. Sem `inicial` (Novo) vão sempre. Com `inicial` (Editar) só quando algo
 * mudou, como o resto do Editar (UC-PEDIT-16): salvar sem mexer não regrava nada.
 */
export function camposDaPolitica(
  estado: EstadoPolitica,
  inicial?: EstadoPolitica,
): { politica_preco?: PoliticaPreco; margem_alvo_pct?: string } {
  const margem = estado.politica === 'margem' ? margemDoEnvio(estado.margemAlvo) : '';
  if (inicial) {
    const margemInicial = inicial.politica === 'margem' ? margemDoEnvio(inicial.margemAlvo) : '';
    if (estado.politica === inicial.politica && margem === margemInicial) return {};
  }
  return { politica_preco: estado.politica, margem_alvo_pct: margem };
}
