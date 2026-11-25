// Campo "Locais do negócio" da tela Novo produto: em quais locais o produto é vendido. Fica dentro
// de "Dados do produto", como no protótipo (produto-blade-forms.jsx).
// Sem local, o produto nasce sem `product_locations` e não aparece para venda em local nenhum.
// Mesma regra do cadastro Blade (create.blade.php `$default_location`): com um local só, ele já
// vem marcado; com mais de um, quem cadastra escolhe.

import '../../../../../css/cowork-produto-bundle.css';
import type { CamposProduto, DefinirCampo, OptionMap } from './tipos';
import { opcoesDe } from './tipos';

interface Props {
  data: CamposProduto;
  definir: DefinirCampo;
  locais: OptionMap;
}

export function CampoLocais({ data, definir, locais }: Props) {
  const lista = opcoesDe(locais);
  if (lista.length === 0) return null;
  const marcados = data.product_locations ?? [];

  // Forma do protótipo: etiquetas (.pb-tags/.pb-tag do bundle do Produto). Marcada com ✕, desmarcada
  // apagada com +. Cada etiqueta é uma caixa de marcar para o leitor de tela (role="checkbox").
  return (
    <fieldset aria-label="Locais do negócio" className="min-w-0">
      <legend className="cw-label mb-[5px]">Locais do negócio</legend>
      <div className="pb-tags">
        {lista.map(([id, nome]) => {
          const n = Number(id);
          const marcado = marcados.includes(n);
          return (
            <button
              key={id}
              type="button"
              role="checkbox"
              aria-checked={marcado}
              aria-label={nome}
              data-state={marcado ? 'checked' : 'unchecked'}
              className="pb-tag"
              style={{ opacity: marcado ? 1 : 0.4, border: 0, cursor: 'pointer' }}
              onClick={() =>
                definir('product_locations', marcado ? marcados.filter((x) => x !== n) : [...marcados, n])
              }
            >
              {nome}
              <span aria-hidden>{marcado ? '✕' : '+'}</span>
            </button>
          );
        })}
      </div>
    </fieldset>
  );
}
