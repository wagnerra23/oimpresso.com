// Manufacturing/IngredientesEditor — o editor de ingredientes da receita (US-MANU-006), atrás de
// `/manufacturing/add-ingredient?variation_id=N&tela=nova`. Sem `?tela=nova` segue a janela Blade.
// Carimbado do PT-02 Form por criar-tela.mjs (UI-0013). Forma: protótipo
// `prototipo-ui/cowork/Wagner/manufacturing-recipe.jsx` → `MfgIngredientesEditor` (handoff §5).
// Plano e etapas: memory/requisitos/Manufacturing/RUNBOOK-ingredientes-editor.md.
//
// Etapa 2: a edição. Regras do handoff §5 que vivem aqui:
//  1. salvar exige ≥1 ingrediente (o servidor também recusa — #9051);
//  2. `disable_editing_ingredient_qty` → a quantidade vira texto;
//  3. sem permissão de gravar → tudo desabilitado + aviso;
//  4. trocar a sub-unidade troca o multiplicador junto (ele vem da sub-unidade, não é digitado);
//  5. o editor trabalha numa CÓPIA (o `useForm`); cancelar descarta.
// O custo mostrado é retorno de tela: quem grava o custo é o servidor (`store()` → `calculateCost`).
//
// Fora daqui, de propósito (RUNBOOK §3): "Nome" (a receita usa o nome do produto, que está na
// trilha) e "Preço de venda / Política de preço / Natureza fiscal" — o servidor não tem onde
// gravar nenhum dos três, e `final_price` guarda o CUSTO do lote, não o preço de venda.
import { useState, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, X } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Inline } from '@/Components/layout/inline';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { fmt, num } from './_lib/formato';
import {
  custosDaFicha,
  montarEnvio,
  multiplicador,
  subtotalDoIngrediente,
  type GrupoEditor,
  type LinhaEditor,
  type SubUnidade,
  type TipoCustoExtra,
} from './_lib/custo';
import BuscaInsumo, { type InsumoEncontrado } from './_components/BuscaInsumo';
import '../../../css/cowork-manufacturing-bundle.css';

interface Props {
  produto: { variation_id: number; product_id: number; nome: string; sku: string; unidade: string; unidade_id: number; sub_unidades: SubUnidade[] };
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
const ROTA_RECEITAS = '/manufacturing/recipe';
// O Select do DS (Radix) quebra com valor vazio (§5 2026-06-29): a unidade base usa sentinela.
const BASE = 'base';
const paraValor = (id: number | null) => (id === null ? BASE : String(id));
const deValor = (v: string) => (v === BASE ? null : Number(v));

export default function IngredientesEditor({ produto, receita, grupos, grupos_da_empresa, perms, travar_qtd }: Props) {
  const podeEditar = perms.editar;
  // Regra 5: a ficha em edição é uma CÓPIA dos props. Cancelar só sai da tela.
  const form = useForm({
    total_quantity: receita.total_quantity,
    waste_percent: receita.waste_percent,
    extra_cost: receita.extra_cost,
    production_cost_type: receita.production_cost_type,
    instructions: receita.instructions,
    sub_unit_id: receita.sub_unit_id,
    grupos,
  });
  const { data, setData, processing, errors } = form;
  const [buscandoEm, setBuscandoEm] = useState<number | null>(null);
  const [novoGrupo, setNovoGrupo] = useState(false);
  const [nomeGrupo, setNomeGrupo] = useState('');

  const c = custosDaFicha(data.grupos, data);
  const nIng = data.grupos.reduce((s, g) => s + g.itens.length, 0);
  const rotuloExtra = data.production_cost_type === 'percentage' ? 'Percentual' : data.production_cost_type === 'per_unit' ? 'R$ / unidade' : 'Valor (R$)';
  const subsSaida = produto.sub_unidades.filter((s) => s.id !== produto.unidade_id);
  const gruposLivres = grupos_da_empresa.filter((n) => !data.grupos.some((g) => g.nome === n));

  // O protótipo deixa salvar só sem erro, e diz qual (QA 2026-09-29): nunca botão desativado mudo.
  const erros: Record<string, string> = {};
  if (!(data.total_quantity > 0)) erros.total_quantity = 'Precisa ser maior que zero.';
  if (!(data.waste_percent >= 0 && data.waste_percent < 100)) erros.waste_percent = 'Use de 0 a menos de 100%.';
  if (!(data.extra_cost >= 0)) erros.extra_cost = 'Não pode ser negativo.';
  const nLinhasInvalidas = data.grupos.reduce((s, g) => s + g.itens.filter((l) => !(l.quantidade > 0)).length, 0);
  const motivoBloqueio = nIng === 0
    ? 'A receita precisa de pelo menos 1 ingrediente.'
    : nLinhasInvalidas > 0
      ? `${nLinhasInvalidas} ingrediente${nLinhasInvalidas > 1 ? 's' : ''} com quantidade zero ou negativa.`
      : Object.keys(erros).length > 0
        ? 'Corrija os campos marcados para salvar.'
        : null;
  const errosServidor = Object.values(errors);

  const mudarGrupos = (fn: (gs: GrupoEditor[]) => GrupoEditor[]) => setData((d) => ({ ...d, grupos: fn(d.grupos) }));
  const mudarLinha = (gi: number, li: number, patch: Partial<LinhaEditor>) =>
    mudarGrupos((gs) => gs.map((g, i) => (i !== gi ? g : { ...g, itens: g.itens.map((l, j) => (j !== li ? l : { ...l, ...patch })) })));
  const removerLinha = (gi: number, li: number) => mudarGrupos((gs) => gs.map((g, i) => (i !== gi ? g : { ...g, itens: g.itens.filter((_, j) => j !== li) })));
  const incluirInsumo = (gi: number, ins: InsumoEncontrado) => {
    mudarGrupos((gs) => gs.map((g, i) => (i !== gi ? g : { ...g, itens: [...g.itens, { ...ins, linha_id: null, quantidade: 1, sub_unit_id: null, waste_percent: 0 }] })));
    setBuscandoEm(null);
  };
  const criarGrupo = (nome: string) => {
    const n = nome.trim();
    if (n === '' || data.grupos.some((g) => g.nome === n)) return;
    mudarGrupos((gs) => [...gs, { id: null, nome: n, descricao: '', sem_grupo: false, itens: [] }]);
    setNovoGrupo(false);
    setNomeGrupo('');
  };

  function salvar(e: React.FormEvent) {
    e.preventDefault();
    if (!podeEditar || motivoBloqueio) return;
    form.transform((d) => montarEnvio(produto.variation_id, d, d.grupos));
    form.post(ROTA_RECEITAS, { preserveScroll: true });
  }

  return (
    <form onSubmit={salvar} className="mfg-root mfg-ed" data-screen-label="Fabricação · Editor de ingredientes">
      <div className="mfg-crumb" data-contract="cabecalho">
        <a href={ROTA_RECEITAS}>Receitas</a>
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
          {data.grupos.map((g, gi) => (
            <div className="mfg-grp" key={`${g.id ?? 'novo'}-${g.nome}`}>
              <div className="mfg-grp-h">
                <b>{g.nome}</b>
                <span className="rounded-[3px] bg-[var(--surface)] px-[5px] py-px font-mono text-[10.5px] text-[var(--text-mute)]">{g.itens.length}</span>
                <span className="v">{fmt(g.itens.reduce((s, l) => s + subtotalDoIngrediente(l), 0))}</span>
                {podeEditar && (
                  <button type="button" className="mfg-mini danger" aria-label={`Remover grupo ${g.nome}`} title="Remover grupo" onClick={() => mudarGrupos((gs) => gs.filter((_, i) => i !== gi))}>
                    <X className="h-3 w-3" />
                  </button>
                )}
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
                const subs = l.sub_unidades.filter((s) => s.id !== l.unidade_base_id);
                const invalida = !(l.quantidade > 0);
                return (
                  <div className="mfg-ing mfg-ing6" key={`${l.variation_id}-${li}`}>
                    <span className="n">
                      {l.nome}
                      <small>
                        {l.sku}
                        {mult !== 1 ? ` · equivale a ${num(l.quantidade * mult, 3)} ${l.unidade_base}` : ''}
                      </small>
                    </span>
                    <span className="m">
                      {podeEditar && !travar_qtd ? (
                        <NumericInputPtBR
                          value={l.quantidade}
                          precision={3}
                          aria-label={`Quantidade de ${l.nome}`}
                          aria-invalid={invalida || undefined}
                          onChange={(n) => mudarLinha(gi, li, { quantidade: n })}
                        />
                      ) : (
                        num(l.quantidade, l.quantidade < 1 ? 3 : 2)
                      )}
                    </span>
                    <span className="m">
                      {podeEditar && subs.length > 0 ? (
                        // Regra 4: escolher a sub-unidade é escolher o multiplicador — ele vem dela.
                        <Escolha
                          rotulo={`Unidade de ${l.nome}`}
                          valor={paraValor(l.sub_unit_id)}
                          opcoes={[{ valor: BASE, rotulo: l.unidade_base }, ...subs.map((s) => ({ valor: String(s.id), rotulo: s.nome }))]}
                          onChange={(v) => mudarLinha(gi, li, { sub_unit_id: deValor(v) })}
                        />
                      ) : (
                        (l.sub_unidades.find((s) => s.id === l.sub_unit_id)?.nome ?? l.unidade_base)
                      )}
                    </span>
                    <span className="m">
                      {fmt(l.custo_unitario)} / {l.unidade_base}
                    </span>
                    <span className="m tot">{fmt(subtotalDoIngrediente(l))}</span>
                    <span>
                      {podeEditar && (
                        <button type="button" className="mfg-mini danger" aria-label={`Remover ${l.nome}`} title="Remover" onClick={() => removerLinha(gi, li)}>
                          <X className="h-3 w-3" />
                        </button>
                      )}
                    </span>
                  </div>
                );
              })}
              {g.itens.length === 0 && <p className="mfg-pick-empty">Grupo sem ingredientes.</p>}
              {podeEditar &&
                (buscandoEm === gi ? (
                  <BuscaInsumo grupo={g.nome} onEscolher={(ins) => incluirInsumo(gi, ins)} onFechar={() => setBuscandoEm(null)} />
                ) : (
                  <div className="px-3 pb-2.5 pt-2">
                    <Button type="button" size="sm" variant="outline" onClick={() => setBuscandoEm(gi)}>
                      <Plus className="mr-1 h-3 w-3" /> Ingrediente em {g.nome}
                    </Button>
                  </div>
                ))}
            </div>
          ))}

          {podeEditar &&
            (novoGrupo ? (
              <div className="mfg-pick">
                {gruposLivres.length > 0 && (
                  <div className="mfg-pick-list row">
                    {gruposLivres.map((n) => (
                      <button key={n} type="button" className="mfg-chip" onClick={() => criarGrupo(n)}>
                        {n}
                      </button>
                    ))}
                  </div>
                )}
                <Inline gap={2} className="pt-2">
                  <Input
                    autoFocus
                    value={nomeGrupo}
                    placeholder="Nome do grupo novo"
                    aria-label="Nome do grupo novo"
                    onChange={(e) => setNomeGrupo(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        criarGrupo(nomeGrupo);
                      }
                      if (e.key === 'Escape') setNovoGrupo(false);
                    }}
                  />
                  <Button type="button" size="sm" onClick={() => criarGrupo(nomeGrupo)}>
                    Criar grupo
                  </Button>
                  <Button type="button" size="sm" variant="outline" onClick={() => setNovoGrupo(false)}>
                    Cancelar
                  </Button>
                </Inline>
              </div>
            ) : (
              <div className="mt-3">
                <Button type="button" variant="outline" className="w-full" onClick={() => setNovoGrupo(true)}>
                  <Plus className="mr-1 h-3 w-3" /> Novo grupo de ingredientes
                </Button>
              </div>
            ))}
        </div>

        <aside className="mfg-ed-side">
          <FormSection title="Receita">
            <FormGrid>
              <Campo rotulo={`Qtd. produzida (${produto.unidade})`} erro={erros.total_quantity}>
                <NumericInputPtBR value={data.total_quantity} disabled={!podeEditar} aria-label="Quantidade produzida" onChange={(n) => setData('total_quantity', n)} />
              </Campo>
              <Campo rotulo="Sub-unidade de saída">
                <Escolha
                  rotulo="Sub-unidade de saída"
                  desabilitado={!podeEditar || subsSaida.length === 0}
                  valor={paraValor(data.sub_unit_id)}
                  opcoes={[
                    { valor: BASE, rotulo: produto.unidade },
                    ...subsSaida.map((s) => ({ valor: String(s.id), rotulo: `${s.nome} (1 = ${num(s.multiplicador, 2)} ${produto.unidade})` })),
                  ]}
                  onChange={(v) => setData('sub_unit_id', deValor(v))}
                />
              </Campo>
              <Campo
                rotulo="Desperdício (%)"
                erro={erros.waste_percent}
                dica={`rende ${num(c.rendimento, 2)} ${produto.unidade} de ${num(data.total_quantity, 2)}`}
              >
                <NumericInputPtBR value={data.waste_percent} disabled={!podeEditar} aria-label="Desperdício em porcentagem" onChange={(n) => setData('waste_percent', n)} />
              </Campo>
              <Campo rotulo="Custo extra">
                <Escolha
                  rotulo="Tipo do custo extra"
                  desabilitado={!podeEditar}
                  valor={data.production_cost_type}
                  opcoes={(Object.keys(TIPOS) as TipoCustoExtra[]).map((t) => ({ valor: t, rotulo: TIPOS[t] }))}
                  onChange={(v) => setData('production_cost_type', v as TipoCustoExtra)}
                />
              </Campo>
              <Campo rotulo={rotuloExtra} erro={erros.extra_cost}>
                <NumericInputPtBR value={data.extra_cost} disabled={!podeEditar} aria-label={rotuloExtra} onChange={(n) => setData('extra_cost', n)} />
              </Campo>
            </FormGrid>
          </FormSection>
          <FormSection title="Instruções">
            <Textarea value={data.instructions} disabled={!podeEditar} aria-label="Instruções" rows={4} onChange={(e) => setData('instructions', e.target.value)} />
          </FormSection>

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
          {!podeEditar && (
            <Alert>
              <AlertDescription>Sua permissão é apenas de leitura (manufacturing.access_recipe).</AlertDescription>
            </Alert>
          )}
        </aside>
      </div>

      <div className="mfg-ed-f" data-contract="acoes">
        <span className="sp" />
        {podeEditar && (motivoBloqueio || errosServidor.length > 0) && (
          <Inline asChild gap={1} align="center">
            <span role="status" className="text-[12px] font-medium text-[var(--color-destructive-fg)]">
              <AlertTriangle className="h-3.5 w-3.5" />
              {motivoBloqueio ?? errosServidor[0]}
            </span>
          </Inline>
        )}
        <Button type="button" variant="outline" asChild>
          <a href={ROTA_RECEITAS}>{podeEditar ? 'Cancelar' : 'Voltar para Receitas'}</a>
        </Button>
        {podeEditar && (
          <Button type="submit" disabled={processing || motivoBloqueio !== null}>
            {processing ? 'Salvando…' : 'Salvar receita'}
          </Button>
        )}
      </div>
    </form>
  );
}

function Escolha({ rotulo, valor, opcoes, onChange, desabilitado = false }: {
  rotulo: string;
  valor: string;
  opcoes: Array<{ valor: string; rotulo: string }>;
  onChange: (v: string) => void;
  desabilitado?: boolean;
}) {
  return (
    <Select value={valor} onValueChange={onChange} disabled={desabilitado}>
      <SelectTrigger className="w-full" aria-label={rotulo}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {opcoes.map((o) => (
          <SelectItem key={o.valor} value={o.valor}>
            {o.rotulo}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

function Campo({ rotulo, erro, dica, children }: { rotulo: string; erro?: string; dica?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="text-[11px] font-semibold uppercase tracking-[0.04em] text-[var(--text-dim)]">{rotulo}</span>
      {children}
      {erro ? (
        <span role="alert" className="block text-[11.5px] text-[var(--color-destructive-fg)]">
          {erro}
        </span>
      ) : (
        dica && <span className="block text-[11.5px] text-[var(--text-dim)]">{dica}</span>
      )}
    </label>
  );
}

IngredientesEditor.layout = (page: ReactNode) => (
  <AppShellV2 title="Editor de ingredientes · Fabricação" breadcrumbItems={[{ label: 'Fabricação' }, { label: 'Receitas' }, { label: 'Ingredientes' }]}>
    {page}
  </AppShellV2>
);
