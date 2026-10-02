// Manufacturing/Insumos — impacto reverso do insumo, em `/manufacturing/insumos`.
//
// FONTE DE DESIGN: `prototipo-ui/cowork/Wagner/manufacturing-insumos.jsx::MfgInsumosView` — mesmo
// bundle das ondas anteriores; nenhuma classe CSS nova.
// F1 PLAN: memory/requisitos/Manufacturing/RUNBOOK-insumos.md.
//
// US-MANU-005 (SPEC.md). 100% leitura — nenhum POST/PATCH/DELETE parte daqui.
//
// A SIMULAÇÃO É DO SERVIDOR, não do cliente: mexer no slider dispara um partial reload, e o
// backend recalcula com a MESMA fórmula de custo do resto do módulo. O cliente formata.
// Isso é o §9 do handoff ("a autoridade é o servidor") e evita uma segunda conta na tela.

import { router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Input } from '@/Components/ui/input';
import { fmt, num } from './_lib/formato';
import FabricacaoAbas from './_components/FabricacaoAbas';
import GradeFabricacao, { type ColunaGrade } from './_components/GradeFabricacao';
import StatusBadge from '@/Components/shared/StatusBadge';
import '../../../css/cowork-manufacturing-bundle.css';

interface LinhaInsumo {
  variation_id: number;
  nome: string;
  sku: string;
  custo: number;
  unidade: string;
  estoque: number;
  n_receitas: number;
  maior_peso: number;
}

interface LinhaUso {
  recipe_id: number;
  nome: string;
  sku: string;
  qtd: number;
  unidade_base: string;
  peso: number;
  unit_atual: number;
  unit_novo: number;
  margem_nova: number;
}

interface Props {
  insumos: LinhaInsumo[];
  selecionado: number | null;
  usos: LinhaUso[];
  variacao_pct: number;
  permissions: { prod: boolean };
  producao: { total: number; rascunhos: number };
  recipes_count: number;
}

const ROUTE = '/manufacturing/insumos';

/** Faixa do §4.4 — o servidor reclampa; aqui é só o que o controle oferece. */
const PCT_MIN = -30;
const PCT_MAX = 60;
const PCT_STEP = 5;

export default function Insumos({
  insumos,
  selecionado,
  usos,
  variacao_pct,
  permissions,
  producao,
  recipes_count,
}: Props) {
  const [busca, setBusca] = useState('');

  const filtrados = insumos.filter((i) =>
    `${i.nome} ${i.sku}`.toLowerCase().includes(busca.trim().toLowerCase()),
  );

  const abrir = (variationId: number, pct = variacao_pct) => {
    router.get(
      ROUTE,
      { insumo: variationId, variacao_pct: pct },
      { preserveState: true, preserveScroll: true, only: ['usos', 'selecionado', 'variacao_pct'] },
    );
  };

  const fechar = () => {
    router.get(
      ROUTE,
      {},
      { preserveState: true, preserveScroll: true, only: ['usos', 'selecionado', 'variacao_pct'] },
    );
  };

  // R-14 do módulo: o drawer fecha com Esc, não só no scrim/✕. Mesmo padrão de Recipes.tsx.
  useEffect(() => {
    if (!selecionado) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") fechar();
    };
    window.addEventListener("keydown", onKey);

    return () => window.removeEventListener("keydown", onKey);
  }, [selecionado]);

  const sel = selecionado ? (insumos.find((i) => i.variation_id === selecionado) ?? null) : null;

  // Colunas como o `DataGrid` do protótipo (manufacturing-insumos.jsx).
  const COLUNAS: ColunaGrade<LinhaInsumo>[] = [
    { key: 'n', label: 'Insumo', render: (i) => i.nome },
    { key: 'sku', label: 'Código', mono: true, render: (i) => i.sku },
    { key: 'c', label: 'Custo', align: 'right', mono: true, render: (i) => `${fmt(i.custo)} / ${i.unidade}` },
    { key: 'est', label: 'Estoque', align: 'right', mono: true, render: (i) => `${num(i.estoque, 0)} ${i.unidade}` },
    { key: 'rec', label: 'Receitas', align: 'right', mono: true, render: (i) => i.n_receitas || '—' },
    {
      key: 'peso',
      label: 'Maior peso',
      align: 'right',
      // O estado "sem receita" não ocorre com a derivação atual da lista — o caminho fica aqui
      // de propósito (RUNBOOK-insumos.md §2).
      render: (i) =>
        i.n_receitas ? (
          <StatusBadge
            kind="peso"
            value={i.maior_peso >= 50 ? 'alto' : i.maior_peso >= 25 ? 'medio' : 'baixo'}
            label={`${num(i.maior_peso, 0)}% do custo`}
            tone={i.maior_peso >= 50 ? 'danger' : i.maior_peso >= 25 ? 'warning' : 'success'}
          />
        ) : (
          'sem receita'
        ),
    },
  ];

  return (
    <div className="mfg-root" data-screen-label="Fabricação · Insumos">
      <div className="os-page-h" data-contract="cabecalho">
        <div className="os-page-h-l">
          <h1>Fabricação</h1>
          <p>Insumos · quem sobe de custo quando o preço de compra muda</p>
        </div>
      </div>

      <FabricacaoAbas ativa="insumos" receitas={recipes_count} producao={producao} podeProduzir={permissions.prod} />

      <div className="mfg-bar" data-contract="busca">
        <Input
          className="max-w-sm"
          placeholder="Buscar insumo por nome ou SKU…"
          value={busca}
          onChange={(e) => setBusca(e.target.value)}
          aria-label="Buscar insumo"
        />
        <span className="mfg-crumb-meta">
          clique num insumo para ver quem sobe de custo quando o preço muda
        </span>
      </div>

      <div className="mfg-tablewrap" data-contract="lista">
        {filtrados.length > 0 ? (
          <GradeFabricacao<LinhaInsumo>
            caption="Insumos"
            colunas={COLUNAS}
            linhas={filtrados}
            idDe={(i) => i.variation_id}
            // Toda linha desta lista é clicável por construção — a derivação só traz insumo COM
            // receita (RUNBOOK-insumos.md §2). O guarda de n_receitas fica aqui mesmo assim.
            clicavel={(i) => !!i.n_receitas}
            onLinha={(i) => abrir(i.variation_id)}
          />
        ) : (
          <div className="mfg-empty">
            <b>Nenhum insumo encontrado</b>
            <span>
              {insumos.length === 0
                ? 'Nenhuma receita deste negócio declara ingredientes ainda.'
                : 'Ajuste a busca para ver mais resultados.'}
            </span>
          </div>
        )}
      </div>

      {sel && (
        <>
          <div className="mfg-scrim" onClick={fechar} aria-hidden />
          <aside className="mfg-drw" role="dialog" aria-label={`Impacto de ${sel.nome}`}>
            <div className="mfg-drw-h">
              <div>
                <h2>{sel.nome}</h2>
                <p>
                  {sel.sku} · {fmt(sel.custo)} / {sel.unidade} · estoque {num(sel.estoque, 0)}{' '}
                  {sel.unidade} · usado em {usos.length} receita{usos.length === 1 ? '' : 's'}
                </p>
              </div>
              <button className="mfg-x" onClick={fechar} aria-label="Fechar">
                ✕
              </button>
            </div>

            <div className="mfg-drw-b">
              <div className="mfg-sec">
                <span>Simular variação de preço</span>
                <span className="ln" />
              </div>
              <div className="mfg-sim">
                <input
                  type="range"
                  min={PCT_MIN}
                  max={PCT_MAX}
                  step={PCT_STEP}
                  value={variacao_pct}
                  onChange={(e) => abrir(sel.variation_id, Number(e.target.value))}
                  aria-label="Variação simulada do preço do insumo"
                />
                <b className={variacao_pct > 0 ? 'up' : variacao_pct < 0 ? 'down' : ''}>
                  {variacao_pct > 0 ? '+' : ''}
                  {variacao_pct}%
                </b>
                <span>
                  {fmt(sel.custo)} → {fmt(sel.custo * (1 + variacao_pct / 100))} / {sel.unidade}
                </span>
              </div>

              <div className="mfg-sec">
                <span>Receitas afetadas</span>
                <span className="ln" />
              </div>
              <div className="mfg-grp">
                <div className="mfg-ing mfg-ing5 mfg-ing-h">
                  <span className="n">Receita</span>
                  <span className="m">Consumo</span>
                  <span className="m">Custo / un</span>
                  <span className="m">
                    Com {variacao_pct > 0 ? '+' : ''}
                    {variacao_pct}%
                  </span>
                  <span className="m">Margem</span>
                </div>
                {usos.map((u) => (
                  <div className="mfg-ing mfg-ing5" key={u.recipe_id}>
                    <span className="n">
                      <b>{u.nome}</b>
                      <small>
                        {u.sku} · {num(u.peso, 0)}% do custo
                      </small>
                    </span>
                    <span className="m">
                      {num(u.qtd, u.qtd < 1 ? 3 : 2)} {u.unidade_base}
                    </span>
                    <span className="m">{fmt(u.unit_atual)}</span>
                    <span className="m tot">{fmt(u.unit_novo)}</span>
                    <span className="m">
                      <span
                        className={`mfg-pill ${u.margem_nova >= 55 ? 'ok' : u.margem_nova >= 45 ? 'warn' : 'bad'}`}
                      >
                        {num(u.margem_nova, 0)}%
                      </span>
                    </span>
                  </div>
                ))}
              </div>

              <p className="mfg-note">
                A conta usa o consumo da receita já convertido para a unidade base. Uma nota
                lançada em{' '}
                <a className="mfg-link" href="/purchases">
                  Compras
                </a>{' '}
                aplica a variação de verdade.
              </p>
            </div>
          </aside>
        </>
      )}
    </div>
  );
}

Insumos.layout = (page: ReactNode) => (
  <AppShellV2
    title="Insumos · Fabricação"
    breadcrumbItems={[{ label: 'Fabricação' }, { label: 'Insumos' }]}
  >
    {page}
  </AppShellV2>
);
