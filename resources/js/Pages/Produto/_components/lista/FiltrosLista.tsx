// Painel "Filtros" da lista de produtos — `Filtros` do protótipo (produto-blade.jsx) com as
// opções da lista Blade (index.blade.php): tipo, categoria, unidade, imposto, marca, local,
// situação e "Não para venda"; e os dois filtros de módulo que só existem para a empresa que
// tem o módulo — "Modelo do dispositivo" (assistência técnica) e "WooCommerce habilitado".

import { useState } from 'react';
import { Search } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Checkbox } from '@/Components/ui/checkbox';
import { Button } from '@/Components/ui/button';
import type { FiltrosLista, Opcao, OpcoesLista } from './tipos';

// O Radix Select não aceita `value=""` (lápide §5 2026-06-29): "Todos" usa uma sentinela.
const TODOS = '__todos__';

const TIPOS: Opcao[] = [
  { id: 'single', nome: 'Único' },
  { id: 'variable', nome: 'Variável' },
  { id: 'combo', nome: 'Composição' },
];
const SITUACOES: Opcao[] = [
  { id: 'active', nome: 'Ativo' },
  { id: 'inactive', nome: 'Inativo' },
];

type ChaveSelect = 'type' | 'category_id' | 'unit_id' | 'tax_id' | 'brand_id' | 'location_id' | 'active_state' | 'repair_model_id';

interface Props {
  filtros: FiltrosLista;
  opcoes: OpcoesLista;
  woocommerce: boolean;
  aplicar: (patch: Partial<FiltrosLista>) => void;
}

function Campo({
  rotulo,
  valor,
  opcoes,
  vazio,
  aoMudar,
}: {
  rotulo: string;
  valor: string | null;
  opcoes: Opcao[];
  vazio: string;
  aoMudar: (v: string | null) => void;
}) {
  // Opção sem id viraria `value=""` e derrubaria o Select (lápide §5 2026-06-29).
  const validas = opcoes.filter((o) => o.id !== '');
  return (
    <label className="pb-fld">
      <span>{rotulo}</span>
      <Select value={valor ?? TODOS} onValueChange={(v) => aoMudar(v === TODOS ? null : v)}>
        <SelectTrigger aria-label={rotulo}>
          <SelectValue placeholder={vazio} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>{vazio}</SelectItem>
          {validas.map((o) => (
            <SelectItem key={o.id} value={o.id}>
              {o.nome}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </label>
  );
}

export default function FiltrosListaProdutos({ filtros, opcoes, woocommerce, aplicar }: Props) {
  const [aberto, setAberto] = useState(true);
  const campo = (k: ChaveSelect) => (v: string | null) => aplicar({ [k]: v } as Partial<FiltrosLista>);

  return (
    <section className="pb-widget" data-contract="produto-filtros" aria-label="Filtros">
      <h3>
        <Search size={13} aria-hidden /> Filtros {!aberto && <span className="n">recolhidos</span>}
      </h3>
      <div className="pb-widget-b">
        <div className="pb-filters-h" style={{ marginBottom: aberto ? 12 : 0 }}>
          <span className="pb-help">
            Mesmos filtros do índice de produtos: tipo, categoria, unidade, imposto, marca, local e situação.
          </span>
          <Button variant="ghost" size="sm" onClick={() => setAberto((a) => !a)}>
            {aberto ? 'Recolher' : 'Expandir'}
          </Button>
        </div>
        {aberto && (
          <div className="pb-grid c4">
            <Campo rotulo="Tipo de produto" valor={filtros.type} opcoes={TIPOS} vazio="Todos" aoMudar={campo('type')} />
            <Campo rotulo="Categoria" valor={filtros.category_id} opcoes={opcoes.categorias} vazio="Todas" aoMudar={campo('category_id')} />
            <Campo rotulo="Unidade" valor={filtros.unit_id} opcoes={opcoes.unidades} vazio="Todas" aoMudar={campo('unit_id')} />
            <Campo rotulo="Imposto" valor={filtros.tax_id} opcoes={opcoes.impostos} vazio="Todos" aoMudar={campo('tax_id')} />
            <Campo rotulo="Marca" valor={filtros.brand_id} opcoes={opcoes.marcas} vazio="Todas" aoMudar={campo('brand_id')} />
            <Campo rotulo="Local do negócio" valor={filtros.location_id} opcoes={opcoes.locais} vazio="Todos" aoMudar={campo('location_id')} />
            <Campo rotulo="Situação" valor={filtros.active_state} opcoes={SITUACOES} vazio="Todos" aoMudar={campo('active_state')} />
            {opcoes.modelos_dispositivo && (
              <Campo
                rotulo="Modelo do dispositivo"
                valor={filtros.repair_model_id}
                opcoes={opcoes.modelos_dispositivo}
                vazio="Todos"
                aoMudar={campo('repair_model_id')}
              />
            )}
            <div className="pb-fld" style={{ justifyContent: 'flex-end' }}>
              <label className="pb-chk">
                <Checkbox
                  checked={filtros.not_for_selling}
                  onCheckedChange={(v) => aplicar({ not_for_selling: v === true })}
                />
                <b>Não para venda</b>
              </label>
            </div>
            {woocommerce && (
              <div className="pb-fld" style={{ justifyContent: 'flex-end' }}>
                <label className="pb-chk">
                  <Checkbox
                    checked={filtros.woocommerce_enabled}
                    onCheckedChange={(v) => aplicar({ woocommerce_enabled: v === true })}
                  />
                  <b>WooCommerce habilitado</b>
                </label>
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  );
}

/** Chips "Filtrando por" — nada de filtro invisível (protótipo: `chips`). */
export function chipsDosFiltros(
  f: FiltrosLista,
  o: OpcoesLista,
): Array<{ chave: keyof FiltrosLista; rotulo: string }> {
  const nome = (lista: Opcao[] | null, id: string | null) => (lista ?? []).find((x) => x.id === id)?.nome ?? id ?? '';
  const out: Array<{ chave: keyof FiltrosLista; rotulo: string }> = [];
  if (f.type) out.push({ chave: 'type', rotulo: `Tipo: ${nome(TIPOS, f.type)}` });
  if (f.category_id) out.push({ chave: 'category_id', rotulo: `Categoria: ${nome(o.categorias, f.category_id)}` });
  if (f.unit_id) out.push({ chave: 'unit_id', rotulo: `Unidade: ${nome(o.unidades, f.unit_id)}` });
  if (f.tax_id) out.push({ chave: 'tax_id', rotulo: `Imposto: ${nome(o.impostos, f.tax_id)}` });
  if (f.brand_id) out.push({ chave: 'brand_id', rotulo: `Marca: ${nome(o.marcas, f.brand_id)}` });
  if (f.location_id) out.push({ chave: 'location_id', rotulo: `Local: ${nome(o.locais, f.location_id)}` });
  if (f.active_state) out.push({ chave: 'active_state', rotulo: `Situação: ${nome(SITUACOES, f.active_state)}` });
  if (f.repair_model_id) out.push({ chave: 'repair_model_id', rotulo: `Modelo: ${nome(o.modelos_dispositivo, f.repair_model_id)}` });
  if (f.not_for_selling) out.push({ chave: 'not_for_selling', rotulo: 'Não para venda' });
  if (f.woocommerce_enabled) out.push({ chave: 'woocommerce_enabled', rotulo: 'WooCommerce habilitado' });
  if (f.q) out.push({ chave: 'q', rotulo: `Busca: ${f.q}` });
  return out;
}
