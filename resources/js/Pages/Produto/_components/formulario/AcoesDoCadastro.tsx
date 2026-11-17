// Rodapé de ações do formulário de produto, na forma do protótipo (produto-blade-forms.jsx): Cancelar,
// os "Salvar e …" e o Salvar. Os botões mandam o mesmo `submit_type` dos botões do Blade
// (create.blade.php / edit.blade.php), que o store() e o update() já tratam: o servidor decide a
// tela seguinte.
//
// "Salvar e adicionar estoque inicial" (só no Novo): o destino (OpeningStockController@add) é Blade, e o
// store() devolve Inertia::location quando o envio vem da tela React, para o navegador abrir a página
// inteira (exceção ao Non-Goal do charter, 2026-11-06). Como no Blade, só com `product.opening_stock`, e
// desligado quando o produto não controla estoque.

import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';

interface Props {
  /** O id do <form> que os botões enviam. */
  form: string;
  modo: 'novo' | 'editar';
  /** Para onde o Cancelar volta. */
  cancelarHref: string;
  desabilitado: boolean;
  /** "Salvar e adicionar preços por grupo" só aparece com grupo de preço cadastrado, como no Blade. */
  comGruposDePreco?: boolean;
  /** "Salvar e adicionar estoque inicial": aparece com `product.opening_stock` (Novo). */
  podeEstoqueInicial?: boolean;
  /** O produto controla estoque: sem isso, o botão de estoque inicial fica desligado. */
  controlaEstoque?: boolean;
}

export function AcoesDoCadastro({
  form,
  modo,
  cancelarHref,
  desabilitado,
  comGruposDePreco,
  podeEstoqueInicial,
  controlaEstoque,
}: Props) {
  const novo = modo === 'novo';

  return (
    <Inline wrap justify="end" gap={2} className="mt-6" data-testid="acoes-cadastro">
      <Button variant="ghost" asChild>
        <Link href={cancelarHref}>Cancelar</Link>
      </Button>
      {comGruposDePreco && (
        <Button variant="outline" type="submit" form={form} name="submit_type" value="submit_n_add_selling_prices" disabled={desabilitado}>
          Salvar e adicionar preços por grupo
        </Button>
      )}
      {novo && podeEstoqueInicial && (
        <Button
          variant="outline"
          type="submit"
          form={form}
          name="submit_type"
          value="submit_n_add_opening_stock"
          disabled={desabilitado || !controlaEstoque}
        >
          Salvar e adicionar estoque inicial
        </Button>
      )}
      <Button variant="outline" type="submit" form={form} name="submit_type" value="save_n_add_another" disabled={desabilitado}>
        Salvar e adicionar outro
      </Button>
      <Button type="submit" form={form} disabled={desabilitado}>
        {novo ? 'Salvar' : 'Salvar alterações'}
      </Button>
    </Inline>
  );
}
