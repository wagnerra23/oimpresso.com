// Painel "Filtros" da lista de produtos — `Filtros` do protótipo (produto-blade.jsx) com as
// opções da lista Blade (index.blade.php): tipo, categoria, unidade, imposto, marca, local,
// situação e "Não para venda"; e os dois filtros de módulo que só existem para a empresa que
// tem o módulo — "Modelo do dispositivo" (assistência técnica), "WooCommerce habilitado" e
// "Natureza fiscal" (módulo Fiscal, UC-PIDX-31).

import { useState } from 'react';
import { Search } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Checkbox } from '@/Components/ui/checkbox';
import { Button } from '@/Components/ui/button';
import { NATUREZAS_FISCAIS, SITUACOES, TIPOS_PRODUTO, type FiltrosLista, type Opcao, type OpcoesLista } from './tipos';

// O Radix Select não aceita `value=""` (lápide §5 2026-06-29): "Todos" usa uma sentinela.
const TODOS = '__todos__';

type ChaveSelect = 'type' | 'category_id' | 'unit_id' | 'tax_id' | 'brand_id' | 'location_id' | 'active_state' | 'repair_model_id' | 'natureza_fiscal';

interface Props {
  filtros: FiltrosLista;
  opcoes: OpcoesLista;
  woocommerce: boolean;
  /** Empresa com o módulo Fiscal: mostra o filtro "Natureza fiscal" (UC-PIDX-31). */
  naturezaFiscal?: boolean;
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
  // `div`, não `label`: o Select do Radix é um botão, não um controle nativo — o nome acessível
  // vem do `aria-label` do gatilho.
  return (
    <div className="pb-fld">
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
    </div>
  );
}

export default function FiltrosListaProdutos({ filtros, opcoes, woocommerce, naturezaFiscal = false, aplicar }: Props) {
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
            Mesmos filtros do índice de produtos: tipo, categoria, unidade, {opcoes.impostos.length > 0 && 'imposto, '}marca, local e situação.
          </span>
          <Button variant="ghost" size="sm" onClick={() => setAberto((a) => !a)}>
            {aberto ? 'Recolher' : 'Expandir'}
          </Button>
        </div>
        {aberto && (
          <div className="pb-grid c4">
            <Campo rotulo="Tipo de produto" valor={filtros.type} opcoes={TIPOS_PRODUTO} vazio="Todos" aoMudar={campo('type')} />
            <Campo rotulo="Categoria" valor={filtros.category_id} opcoes={opcoes.categorias} vazio="Todas" aoMudar={campo('category_id')} />
            <Campo rotulo="Unidade" valor={filtros.unit_id} opcoes={opcoes.unidades} vazio="Todas" aoMudar={campo('unit_id')} />
            {/* Só com alíquota cadastrada: sem ela não há imposto para filtrar (decisão [F] 2026-10-26). */}
            {opcoes.impostos.length > 0 && (
              <Campo rotulo="Imposto" valor={filtros.tax_id} opcoes={opcoes.impostos} vazio="Todos" aoMudar={campo('tax_id')} />
            )}
            <Campo rotulo="Marca" valor={filtros.brand_id} opcoes={opcoes.marcas} vazio="Todas" aoMudar={campo('brand_id')} />
            <Campo rotulo="Local do negócio" valor={filtros.location_id} opcoes={opcoes.locais} vazio="Todos" aoMudar={campo('location_id')} />
            <Campo rotulo="Situação" valor={filtros.active_state} opcoes={SITUACOES} vazio="Todos" aoMudar={campo('active_state')} />
            {/* UC-PIDX-31: só com o módulo Fiscal. */}
            {naturezaFiscal && (
              <Campo rotulo="Natureza fiscal" valor={filtros.natureza_fiscal} opcoes={NATUREZAS_FISCAIS} vazio="Todas" aoMudar={campo('natureza_fiscal')} />
            )}
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
              <div className="pb-chk">
                <Checkbox
                  id="filtro-nao-para-venda"
                  checked={filtros.not_for_selling}
                  onCheckedChange={(v) => aplicar({ not_for_selling: v === true })}
                />
                <label htmlFor="filtro-nao-para-venda">
                  <b>Não para venda</b>
                </label>
              </div>
            </div>
            {woocommerce && (
              <div className="pb-fld" style={{ justifyContent: 'flex-end' }}>
                <div className="pb-chk">
                  <Checkbox
                    id="filtro-woocommerce"
                    checked={filtros.woocommerce_enabled}
                    onCheckedChange={(v) => aplicar({ woocommerce_enabled: v === true })}
                  />
                  <label htmlFor="filtro-woocommerce">
                    <b>WooCommerce habilitado</b>
                  </label>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  );
}
