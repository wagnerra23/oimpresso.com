// Seção "Locais do negócio" da tela Novo produto: em quais locais o produto é vendido.
// Sem local, o produto nasce sem `product_locations` e não aparece para venda em local nenhum.
// Mesma regra do cadastro Blade (create.blade.php `$default_location`): com um local só, ele já
// vem marcado; com mais de um, quem cadastra escolhe.

import { Inline } from '@/Components/layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import type { CamposProduto, DefinirCampo, OptionMap } from './tipos';
import { opcoesDe } from './tipos';

interface Props {
  data: CamposProduto;
  definir: DefinirCampo;
  locais: OptionMap;
}

export function BlocoLocais({ data, definir, locais }: Props) {
  const lista = opcoesDe(locais);
  if (lista.length === 0) return null;
  const marcados = data.product_locations ?? [];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Locais do negócio</CardTitle>
      </CardHeader>
      <CardContent>
        <fieldset aria-label="Locais do negócio">
          <Inline wrap gap={4}>
            {lista.map(([id, nome]) => {
              const n = Number(id);
              return (
                <Inline key={id} gap={2}>
                  <Checkbox
                    id={`produto-local-${id}`}
                    checked={marcados.includes(n)}
                    onCheckedChange={(v) =>
                      definir('product_locations', v === true ? [...marcados, n] : marcados.filter((x) => x !== n))
                    }
                  />
                  <Label htmlFor={`produto-local-${id}`}>{nome}</Label>
                </Inline>
              );
            })}
          </Inline>
        </fieldset>
      </CardContent>
    </Card>
  );
}
