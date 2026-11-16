// Subcategoria (UC-PCAD-25 · UC-PEDIT-25) e sub-unidades (UC-PCAD-26 · UC-PEDIT-26) do formulário de
// produto, dentro de "Dados do produto", como no protótipo (produto-blade-forms.jsx). Só aparecem
// quando a empresa usa subcategoria ou sub-unidade, como o Blade (create.blade.php) esconde os campos.
//
// As sub-unidades seguem o cadastro de unidades: o multiplicador (1 caixa = 12 unidades) é da
// unidade, não do produto. O protótipo desenha um multiplicador no produto; aqui o produto só escolhe
// quais unidades do cadastro valem para ele, que é o que a venda e a compra leem (Util::getSubUnits).

import { Inline } from '@/Components/layout';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import type { CamposProduto, DefinirCampo, SubUnidade } from './tipos';

/** O Select do Radix não aceita item com valor vazio: "sem subcategoria" é `''` no envio. */
const SEM_SUBCATEGORIA = 'sem-subcategoria';

export function CampoSubcategoria({
  data,
  definir,
  porCategoria,
}: {
  data: CamposProduto;
  definir: DefinirCampo;
  porCategoria: Record<string, Record<string, string>>;
}) {
  const opcoes = Object.entries(porCategoria[String(data.category_id ?? '')] ?? {}).filter(([id]) => id !== '');
  const atual = String(data.sub_category_id ?? '');

  return (
    <div>
      <Label htmlFor="sub_category_id">Subcategoria</Label>
      <Select
        value={atual === '' ? SEM_SUBCATEGORIA : atual}
        onValueChange={(v) => definir('sub_category_id', v === SEM_SUBCATEGORIA ? '' : Number(v))}
        disabled={opcoes.length === 0}
      >
        <SelectTrigger id="sub_category_id">
          <SelectValue placeholder="Nenhuma" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={SEM_SUBCATEGORIA}>Nenhuma</SelectItem>
          {opcoes.map(([id, nome]) => (
            <SelectItem key={id} value={id}>
              {nome}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

export function CampoSubUnidades({
  data,
  definir,
  porUnidade,
}: {
  data: CamposProduto;
  definir: DefinirCampo;
  porUnidade: Record<string, SubUnidade[]>;
}) {
  const unidades = porUnidade[String(data.unit_id ?? '')] ?? [];
  const principal = unidades[0];
  const marcadas = (data.sub_unit_ids ?? []).map(Number);

  return (
    <fieldset aria-label="Sub-unidades relacionadas" className="min-w-0">
      <legend className="text-sm font-medium leading-none mb-2">Sub-unidades relacionadas</legend>
      {unidades.length === 0 ? (
        <p className="text-[12px] text-muted-foreground">Escolha a unidade para ver as sub-unidades.</p>
      ) : (
        <Inline wrap gap={4}>
          {unidades.map((u) => (
            <Inline key={u.id} gap={2}>
              <Checkbox
                id={`produto-subunidade-${u.id}`}
                checked={marcadas.includes(u.id)}
                onCheckedChange={(v) =>
                  definir('sub_unit_ids', v === true ? [...marcadas, u.id] : marcadas.filter((x) => x !== u.id))
                }
              />
              <Label htmlFor={`produto-subunidade-${u.id}`}>
                {u.nome}
                {principal && u.id !== principal.id && (
                  <span className="text-muted-foreground font-normal">
                    {' '}
                    (1 = {String(u.multiplicador).replace('.', ',')} {principal.nome})
                  </span>
                )}
              </Label>
            </Inline>
          ))}
        </Inline>
      )}
      <p className="text-[12px] text-muted-foreground mt-1">Ex.: caixa e unidade, para vender fracionado.</p>
    </fieldset>
  );
}
