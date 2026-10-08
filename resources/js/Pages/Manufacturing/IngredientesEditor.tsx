// Manufacturing/IngredientesEditor — o editor de ingredientes da receita (US-MANU-006), atrás de
// `/manufacturing/add-ingredient?variation_id=N&tela=nova`. Sem `?tela=nova` segue a janela Blade.
// Carimbado do PT-02 Form por criar-tela.mjs (UI-0013). Forma: protótipo
// `prototipo-ui/cowork/Wagner/manufacturing-recipe.jsx` → `MfgIngredientesEditor` (handoff §5).
// Plano e etapas: memory/requisitos/Manufacturing/RUNBOOK-ingredientes-editor.md.
//
// ETAPA 1 (esta): a ficha aberta em MODO LEITURA — grupos, linhas, custo de hoje e o "Custo ao vivo".
// A edição (quantidade, sub-unidade, grupos, busca de insumo e salvar no `store()`) é a etapa 2.
//
// Fora daqui, de propósito (RUNBOOK §3): "Nome" (a receita usa o nome do produto, que está na
// trilha) e "Preço de venda / Política de preço / Natureza fiscal" — o servidor não tem onde
// gravar nenhum dos três, e `final_price` guarda o CUSTO do lote, não o preço de venda.
import type { ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { fmt, num } from './_lib/formato';
import { custosDaFicha, multiplicador, subtotalDoIngrediente, type GrupoEditor, type SubUnidade, type TipoCustoExtra } from './_lib/custo';
import '../../../css/cowork-manufacturing-bundle.css';

interface Props {
  produto: { variation_id: number; product_id: number; nome: string; sku: string; unidade: string; sub_unidades: SubUnidade[] };
  receita: {
    id: number | null;
    copiada_de: number | null;
    total_quantity: number;
    waste_percent: number;
    extra_cost: number;
    production_cost_type: TipoCustoExtra;
    instructions: string;
    sub_unit_id: number | null;
  };
  grupos: GrupoEditor[];
  grupos_da_empresa: string[];
  perms: { editar: boolean };
  travar_qtd: boolean;
}

const TIPOS: Record<TipoCustoExtra, string> = { fixed: 'Valor fixo', percentage: '% dos ingredientes', per_unit: 'Por unidade produzida' };

export default function IngredientesEditor({ produto, receita, grupos, perms, travar_qtd }: Props) {
  // A ficha em edição é uma CÓPIA dos props (handoff §5 regra 5): cancelar descarta.
  const form = useForm({ ...receita, grupos });
  const { data } = form;
  const c = custosDaFicha(data.grupos, data);
  const nIng = data.grupos.reduce((s, g) => s + g.itens.length, 0);
  const subSaida = produto.sub_unidades.find((s) => s.id === data.sub_unit_id && s.multiplicador !== 1);
  const rotuloExtra = data.production_cost_type === 'percentage' ? 'Percentual' : data.production_cost_type === 'per_unit' ? 'R$ / unidade' : 'Valor (R$)';

  return (
    <div className="mfg-root mfg-ed" data-screen-label="Fabricação · Editor de ingredientes">
      <div className="mfg-crumb" data-contract="cabecalho">
        <a href="/manufacturing/recipe">Receitas</a>
        <span>/</span>
        <b>{produto.nome}</b>
        <span className="sp" />
        <span className="mfg-crumb-meta">
          {produto.sku} · {nIng} ingredientes em {data.grupos.length} grupos
        </span>
      </div>

      <div className="mfg-ed-cols">
        <div className="mfg-ed-main" data-contract="formulario">
          {data.grupos.length === 0 && <p className="mfg-pick-empty">Esta receita ainda não tem ingredientes.</p>}
          {data.grupos.map((g, gi) => {
            const subtotal = g.itens.reduce((s, l) => s + subtotalDoIngrediente(l), 0);
            return (
              <div className="mfg-grp" key={`${g.id ?? 'novo'}-${gi}`}>
                <div className="mfg-grp-h">
                  <b>{g.nome}</b>
                  <span className="mfg-grp-n">{g.itens.length}</span>
                  <span className="v">{fmt(subtotal)}</span>
                </div>
                <div className="mfg-ing mfg-ing6 mfg-ing-h">
                  <span className="n">Ingrediente</span>
                  <span className="m">Quantidade</span>
                  <span className="m">Unidade</span>
                  <span className="m">Custo unit.</span>
                  <span className="m">Subtotal</span>
                  <span />
                </div>
                {g.itens.map((l, li) => {
                  const mult = multiplicador(l);
                  const unidade = l.sub_unidades.find((s) => s.id === l.sub_unit_id)?.nome ?? l.unidade_base;
                  return (
                    <div className="mfg-ing mfg-ing6" key={`${l.variation_id}-${li}`}>
                      <span className="n">
                        {l.nome}
                        <small>
                          {l.sku}
                          {mult !== 1 ? ` · equivale a ${num(l.quantidade * mult, 3)} ${l.unidade_base}` : ''}
                        </small>
                      </span>
                      <span className="m">{num(l.quantidade, l.quantidade < 1 ? 3 : 2)}</span>
                      <span className="m">{unidade}</span>
                      <span className="m">
                        {fmt(l.custo_unitario)} / {l.unidade_base}
                      </span>
                      <span className="m tot">{fmt(subtotalDoIngrediente(l))}</span>
                      <span />
                    </div>
                  );
                })}
              </div>
            );
          })}
        </div>

        <aside className="mfg-ed-side">
          <FormSection title="Receita">
            <FormGrid>
              <Campo rotulo="Qtd. produzida" valor={`${num(data.total_quantity, 2)} ${produto.unidade}`} />
              <Campo
                rotulo="Sub-unidade de saída"
                valor={subSaida ? `${subSaida.nome} (1 ${produto.unidade} = ${num(subSaida.multiplicador, 2)})` : '—'}
              />
              <Campo
                rotulo="Desperdício (%)"
                valor={num(data.waste_percent, 2)}
                dica={`rende ${num(c.rendimento, 2)} ${produto.unidade} de ${num(data.total_quantity, 2)}`}
              />
              <Campo rotulo="Custo extra" valor={TIPOS[data.production_cost_type] ?? TIPOS.fixed} />
              <Campo rotulo={rotuloExtra} valor={num(data.extra_cost, 2)} />
            </FormGrid>
          </FormSection>
          {data.instructions && (
            <FormSection title="Instruções">
              <p className="whitespace-pre-line text-[13px]">{data.instructions}</p>
            </FormSection>
          )}

          <div className="mfg-sec">
            <span>Custo ao vivo</span>
            <span className="ln" />
          </div>
          <dl className="mfg-tot">
            <dt>Ingredientes ({nIng})</dt>
            <dd>{fmt(c.ingredientes)}</dd>
            <dt>Custo extra</dt>
            <dd>{fmt(c.extra)}</dd>
            <hr />
            <dt>Custo por {produto.unidade}</dt>
            <dd>{fmt(c.unitario)}</dd>
          </dl>
          {travar_qtd && (
            <Alert>
              <AlertDescription>Edição de quantidade de ingrediente está bloqueada em Configurações.</AlertDescription>
            </Alert>
          )}
          {!perms.editar && (
            <Alert>
              <AlertDescription>Sua permissão é apenas de leitura (manufacturing.access_recipe).</AlertDescription>
            </Alert>
          )}
        </aside>
      </div>

      <div className="mfg-ed-f" data-contract="acoes">
        <span className="sp" />
        <Button variant="outline" asChild>
          <a href="/manufacturing/recipe">Voltar para Receitas</a>
        </Button>
      </div>
    </div>
  );
}

function Campo({ rotulo, valor, dica }: { rotulo: string; valor: string; dica?: string }) {
  return (
    <div>
      <div className="text-[11px] font-semibold uppercase tracking-[0.04em] text-[var(--text-dim)]">{rotulo}</div>
      <div className="text-[13px]">{valor}</div>
      {dica && <div className="text-[11.5px] text-[var(--text-dim)]">{dica}</div>}
    </div>
  );
}

IngredientesEditor.layout = (page: ReactNode) => (
  <AppShellV2 title="Editor de ingredientes · Fabricação" breadcrumbItems={[{ label: 'Fabricação' }, { label: 'Receitas' }, { label: 'Ingredientes' }]}>
    {page}
  </AppShellV2>
);
