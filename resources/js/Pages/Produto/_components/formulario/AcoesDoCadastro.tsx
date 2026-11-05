// Rodapé de ações do formulário de produto, na forma do protótipo (produto-blade-forms.jsx): Cancelar,
// os "Salvar e …" e o Salvar. Os botões mandam o mesmo `submit_type` dos botões do Blade
// (create.blade.php / edit.blade.php), que o store() e o update() já tratam: o servidor decide a
// tela seguinte.
//
// Fora daqui, de propósito: "Salvar e adicionar estoque inicial". O destino (OpeningStockController@add)
// é Blade, e a visita do Inertia não abre uma página Blade depois do envio. Entra quando o store()
// devolver Inertia::location para esse caso, o que é exceção ao Non-Goal do charter e decisão do [W].

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
}

export function AcoesDoCadastro({ form, modo, cancelarHref, desabilitado, comGruposDePreco }: Props) {
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
      <Button variant="outline" type="submit" form={form} name="submit_type" value="save_n_add_another" disabled={desabilitado}>
        Salvar e adicionar outro
      </Button>
      <Button type="submit" form={form} disabled={desabilitado}>
        {novo ? 'Salvar' : 'Salvar alterações'}
      </Button>
    </Inline>
  );
}
