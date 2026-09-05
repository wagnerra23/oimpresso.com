// Manufacturing/Insumos — impacto reverso do insumo, em `/manufacturing/insumos`.
//
// FONTE DE DESIGN: `prototipo-ui/cowork/manufacturing-insumos.jsx::MfgInsumosView` — mesmo
// bundle das ondas anteriores; nenhuma classe CSS nova.
// F1 PLAN: memory/requisitos/Manufacturing/RUNBOOK-insumos.md.
//
// US-MANU-005 (SPEC.md). 100% leitura — nenhum POST/PATCH/DELETE parte daqui.
//
// A SIMULAÇÃO É DO SERVIDOR, não do cliente: mexer no slider dispara um partial reload, e o
// backend recalcula com a MESMA fórmula de custo do resto do módulo. O cliente formata.
// Isso é o §9 do handoff ("a autoridade é o servidor") e evita uma segunda conta na tela.

import { Link, router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Input } from '@/Components/ui/input';
import { fmt, num } from './_lib/formato';
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

  return (
    <div className="mfg-root" data-screen-label="Manufacturing · Insumos">
      <div className="os-page-h" data-contract="cabecalho">
        <div className="os-page-h-l">
          <h1>Manufacturing</h1>
          <p>Insumos · quem sobe de custo quando o preço de compra muda</p>
        </div>
      </div>

      <nav className="mfg-tabs" aria-label="Manufacturing">
        <Link className="mfg-tab" href="/manufacturing/recipe">
          Receitas
          <span className="mfg-tab-n">{recipes_count}</span>
        </Link>
        <span className="mfg-tab act" aria-current="page">
          Insumos
        </span>
        {permissions.prod && (
          <Link className="mfg-tab" href="/manufacturing/production">
            Ordens de produção
            <span className="mfg-tab-n">
              {producao.total}
              {producao.rascunhos ? ` · ${producao.rascunhos} rasc.` : ''}
            </span>
          </Link>
        )}
        <Link className="mfg-tab" href="/manufacturing/report">
          Relatório
        </Link>
        <Link className="mfg-tab" href="/manufacturing/settings">
          Configurações
        </Link>
      </nav>

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
        <div className="mfg-table ins">
          <div className="mfg-tr mfg-thead">
            <span className="mfg-th">Insumo</span>
            <span className="mfg-th">Código</span>
            <span className="mfg-th r">Custo</span>
            <span className="mfg-th r">Estoque</span>
            <span className="mfg-th r">Receitas</span>
            <span className="mfg-th r">Maior peso</span>
          </div>

          {filtrados.map((i) => (
            <div
              key={i.variation_id}
              className={`mfg-tr${i.n_receitas ? ' mfg-row' : ''}`}
              // role/tabIndex incondicionais (igual Recipes.tsx): toda linha desta lista é
              // clicável por construção — a derivação só traz insumo COM receita
              // (RUNBOOK-insumos.md §2). Os guardas de n_receitas ficam nos handlers.
              role="button"
              tabIndex={0}
              onClick={() => i.n_receitas && abrir(i.variation_id)}
              onKeyDown={(e) => {
                if (i.n_receitas && (e.key === 'Enter' || e.key === ' ')) {
                  e.preventDefault();
                  abrir(i.variation_id);
                }
              }}
            >
              <span className="mfg-name">
                <b>{i.nome}</b>
              </span>
              <span className="mfg-sku">{i.sku}</span>
              <span className="mfg-num r">
                {fmt(i.custo)}
                <span className="mfg-u">/ {i.unidade}</span>
              </span>
              <span className="mfg-num dim r">
                {num(i.estoque, 0)}
                <span className="mfg-u">{i.unidade}</span>
              </span>
              <span className="mfg-num r">{i.n_receitas || '—'}</span>
              <span className="r">
                {/* O estado "sem receita" não ocorre com a derivação atual da lista — o
                    caminho fica aqui de propósito (RUNBOOK-insumos.md §2). */}
                {i.n_receitas ? (
                  <span
                    className={`mfg-pill ${i.maior_peso >= 50 ? 'bad' : i.maior_peso >= 25 ? 'warn' : 'ok'}`}
                  >
                    {num(i.maior_peso, 0)}% do custo
                  </span>
                ) : (
                  <span className="mfg-cat">sem receita</span>
                )}
              </span>
            </div>
          ))}

          {filtrados.length === 0 && (
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
    title="Insumos · Manufacturing"
    breadcrumbItems={[{ label: 'Manufacturing' }, { label: 'Insumos' }]}
  >
    {page}
  </AppShellV2>
);
