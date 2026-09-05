// Manufacturing/Recipes — a tela de Fabricação em `/manufacturing/recipe`.
//
// FONTE DE DESIGN (âncora declarada no charter): `prototipo-ui/cowork/manufacturing-page.jsx`,
// resolvida por `node prototipo-ui/ancora.mjs Manufacturing/Recipes`. O espelho foi conferido
// contra o handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" e é IDÊNTICO (6 arquivos, 0 linhas de
// diferença) — este porte segue o protótipo, não o inventa.
//
// NORMATIVO: o README do handoff. §4.2 consulta · §4.3 drawer · §7 modelo de custo ·
// §8 ficha PT-07 · §11 acessibilidade medida · §16 mapa de campos · §17 R-01..R-24.
// F1 PLAN: memory/requisitos/Manufacturing/RUNBOOK-recipes.md
//
// PT-01 Lista (UI-0013). A estrutura de tabela NÃO usa `shared/DataTable` de propósito:
// ele exige `pagination` + `endpoint` do servidor (DataTable.tsx:113-152) e esta tela
// filtra/ordena/pagina no cliente sobre o conjunto do business — é a limitação que o
// próprio handoff registra em ADR `0412` e contorna com tabela local. As abas também são
// locais em vez de `PageHeaderTabs`... exceto que AQUI elas navegam de verdade.
//
// CSS: `cowork-manufacturing-bundle.css` aplicado INTEIRO (proibicoes.md §"Design System /
// Pacote Cowork novo" — 1ª aplicação nunca é cherry-pick). Escopo `.mfg-root`.

import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Pencil, Plus, Printer, Search } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import FichaPrint from './_components/FichaPrint';
import { faixaMargem, fmt, num, rotuloCustoExtra } from './_lib/formato';
import type { ContadoresProducao, Permissoes, Receita } from './_lib/tipos';
import '../../../css/cowork-manufacturing-bundle.css';

interface Props {
  recipes: Receita[];
  permissions: Permissoes;
  producao: ContadoresProducao;
  settings: { enable_updating_product_price: boolean };
}

const POR_PAG = 10;

/** Rotas legadas que continuam donas do CRUD — a tela nova aponta, não reimplementa. */
const ROTA_NOVA = '/manufacturing/recipe/create';
const ROTA_EDITAR_INGREDIENTES = '/manufacturing/add-ingredient?variation_id=';
const ROTA_PRODUZIR = '/manufacturing/production/create';

type ChaveOrd = 'name' | 'cat' | 'qtd' | 'total' | 'unit' | 'venda' | 'margem';

const CHAVES: Record<ChaveOrd, (r: Receita) => string | number> = {
  name: (r) => r.name.toLowerCase(),
  cat: (r) => r.cat + r.sub,
  qtd: (r) => r.custos.qtd_liq,
  total: (r) => r.custos.total,
  unit: (r) => r.custos.unit,
  venda: (r) => r.venda,
  margem: (r) => r.custos.margem,
};

export default function Recipes({ recipes = [], permissions, producao, settings }: Props) {
  const [q, setQ] = useState('');
  const [cat, setCat] = useState('Todas');
  const [kpi, setKpi] = useState<'margem' | 'custo' | null>(null);
  const [ord, setOrd] = useState<{ k: ChaveOrd; dir: 'asc' | 'desc' }>({ k: 'name', dir: 'asc' });
  const [pag, setPag] = useState(1);
  const [sel, setSel] = useState<number[]>([]);
  const [openId, setOpenId] = useState<number | null>(null);
  const [imprimir, setImprimir] = useState<{ itens: Receita[]; semCusto: boolean } | null>(null);
  const buscaRef = useRef<HTMLInputElement>(null);

  // R-04 — `/` foca a busca; `/` digitado DENTRO de campo continua sendo `/`.
  // R-14 — `esc` fecha o drawer.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const alvo = e.target as HTMLElement | null;
      const emCampo = /^(INPUT|TEXTAREA|SELECT)$/.test(alvo?.tagName ?? '');
      if (e.key === '/' && !emCampo) {
        e.preventDefault();
        buscaRef.current?.focus();
      }
      if (e.key === 'Escape') setOpenId(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const CATS = useMemo(
    () => ['Todas', ...Array.from(new Set(recipes.map((r) => r.cat)))],
    [recipes],
  );

  // Derivados — NUNCA em estado (§13 do handoff).
  const filtradas = useMemo(() => {
    const t = q.trim().toLowerCase();
    const base = recipes.filter((r) => {
      if (cat !== 'Todas' && r.cat !== cat) return false;
      if (kpi === 'margem' && r.custos.margem >= 45) return false; // R-05
      if (kpi === 'custo' && r.waste < 8) return false;
      // R-03 — a busca casa nome + SKU + categoria + subcategoria.
      return !t || `${r.name} ${r.sku} ${r.cat} ${r.sub}`.toLowerCase().includes(t);
    });
    const f = CHAVES[ord.k] ?? CHAVES.name;
    return [...base].sort((a, b) => {
      const va = f(a);
      const vb = f(b);
      const s = va > vb ? 1 : va < vb ? -1 : 0;
      return ord.dir === 'asc' ? s : -s;
    });
  }, [recipes, q, cat, kpi, ord]);

  const nPags = Math.max(1, Math.ceil(filtradas.length / POR_PAG));
  const pagina = Math.min(pag, nPags);
  const visiveis = filtradas.slice((pagina - 1) * POR_PAG, pagina * POR_PAG); // R-07

  // R-06 — ordenar alterna a direção e volta pra página 1.
  const ordenar = (k: ChaveOrd) => {
    setOrd((o) => ({ k, dir: o.k === k && o.dir === 'asc' ? 'desc' : 'asc' }));
    setPag(1);
  };

  const magra = recipes.filter((r) => r.custos.margem < 45).length;
  const perda = recipes.filter((r) => r.waste >= 8).length;
  const custoMed = recipes.length
    ? recipes.reduce((s, r) => s + r.custos.unit, 0) / recipes.length
    : 0;

  // R-08 — "selecionar todas" marca as FILTRADAS, não só as visíveis.
  const allSel = filtradas.length > 0 && filtradas.every((r) => sel.includes(r.id));
  const aberta = recipes.find((r) => r.id === openId) ?? null;
  const selecionadas = recipes.filter((r) => sel.includes(r.id));

  const Th = ({ k, children, r: right }: { k: ChaveOrd; children: ReactNode; r?: boolean }) => (
    <button
      type="button"
      className={`mfg-th sort${right ? ' r' : ''}${ord.k === k ? ' act' : ''}`}
      onClick={() => ordenar(k)}
    >
      {right && <span className="ind">{ord.k === k ? (ord.dir === 'asc' ? '↑' : '↓') : '⇵'}</span>}
      {children}
      {!right && <span className="ind">{ord.k === k ? (ord.dir === 'asc' ? '↑' : '↓') : '⇵'}</span>}
    </button>
  );

  return (
    <div className="mfg-root" data-screen-label="Manufacturing · Receitas">
      <div className="os-page-h" data-contract="cabecalho">
        <div className="os-page-h-l">
          <h1>Manufacturing</h1>
          <p>
            {recipes.length} receita{recipes.length === 1 ? '' : 's'} · {producao.total} ordem
            {producao.total === 1 ? '' : 's'} de produção · custo recalculado pelo preço atual dos
            ingredientes
          </p>
        </div>
        <div className="os-page-h-r">
          {permissions.criar && (
            <Button asChild size="sm">
              <a href={ROTA_NOVA}>
                <Plus className="mr-1.5 h-3.5 w-3.5" /> Nova receita
              </a>
            </Button>
          )}
        </div>
      </div>

      {/* §4.1 — abas do módulo. Cada uma navega pra uma tela que EXISTE hoje.
          "Insumos" passou a existir na US-MANU-005 (`usosDoInsumo` no RecipeBomService) — o
          §18.3 do handoff dizia "sem backend, a aba não sai", e o backend saiu. */}
      <nav className="mfg-tabs" aria-label="Manufacturing">
        <span className="mfg-tab act" aria-current="page">
          Receitas
          <span className="mfg-tab-n">{recipes.length}</span>
        </span>
        <Link className="mfg-tab" href="/manufacturing/insumos">
          Insumos
        </Link>
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
        {/* `Link` (Inertia) pra tela IRMÃ em React. Até 2026-09-04 esta aba era uma âncora
            crua apontando pra rota Blade legada do módulo: saía do SPA e abria a tela
            antiga — foi o que o [F] viu ao clicar em Configurações. O cutover da rota
            legada segue PENDENTE e é decisão [W] (RUNBOOK-settings.md §"Rota nova, sem
            cutover"); esta aba só deixa de contradizer as irmãs. */}
        <Link className="mfg-tab" href="/manufacturing/settings">
          Configurações
        </Link>
      </nav>

      {/* §4.2 — 4 KPIs; o 2º e o 3º FILTRAM (liga/desliga), o 1º e o 4º são leitura (R-05). */}
      <div className="mfg-kpis" data-contract="kpis">
        <div className="mfg-kpi">
          <span className="mfg-kpi-l">Custo médio / unidade</span>
          <span className="mfg-kpi-v">{fmt(custoMed)}</span>
          <span className="mfg-kpi-s">
            média das {recipes.length} receita{recipes.length === 1 ? '' : 's'}
          </span>
        </div>
        <button
          type="button"
          className={`mfg-kpi${kpi === 'margem' ? ' act' : ''}`}
          aria-pressed={kpi === 'margem'}
          onClick={() => {
            setKpi(kpi === 'margem' ? null : 'margem');
            setPag(1);
          }}
        >
          <span className="mfg-kpi-l">Margem abaixo de 45%</span>
          <span className="mfg-kpi-v warn">{magra}</span>
          <span className="mfg-kpi-s">preço de venda desatualizado</span>
        </button>
        <button
          type="button"
          className={`mfg-kpi${kpi === 'custo' ? ' act' : ''}`}
          aria-pressed={kpi === 'custo'}
          onClick={() => {
            setKpi(kpi === 'custo' ? null : 'custo');
            setPag(1);
          }}
        >
          <span className="mfg-kpi-l">Desperdício ≥ 8%</span>
          <span className="mfg-kpi-v warn">{perda}</span>
          <span className="mfg-kpi-s">revisar plotagem / encaixe</span>
        </button>
        <div className="mfg-kpi">
          <span className="mfg-kpi-l">Produção do mês</span>
          <span className="mfg-kpi-v">{producao.mes_final}</span>
          <span className="mfg-kpi-s">
            {producao.mes_rascunho} rascunho{producao.mes_rascunho === 1 ? '' : 's'} em aberto
          </span>
        </div>
      </div>

      <div className="mfg-bar" data-contract="filtros">
        <div className="mfg-s">
          <Search size={14} className="ic" aria-hidden />
          <input
            ref={buscaRef}
            placeholder="Buscar receita por nome, SKU, categoria…  (tecla /)"
            value={q}
            onChange={(e) => {
              setQ(e.target.value);
              setPag(1);
            }}
            aria-label="Buscar receita"
          />
        </div>
        <div className="mfg-chips">
          {CATS.map((c) => (
            <button
              type="button"
              key={c}
              className={`mfg-chip${cat === c ? ' act' : ''}`}
              aria-pressed={cat === c}
              onClick={() => {
                setCat(c);
                setPag(1);
              }}
            >
              {c}
            </button>
          ))}
        </div>
      </div>

      <div className="mfg-tablewrap" data-contract="lista">
        <div className="mfg-table">
          <div className="mfg-tr mfg-thead">
            <Checkbox
              checked={allSel}
              onCheckedChange={() => setSel(allSel ? [] : filtradas.map((r) => r.id))}
              aria-label="Selecionar todas"
            />
            <Th k="name">Receita</Th>
            <Th k="cat">Categoria</Th>
            <Th k="qtd" r>
              Quantidade
            </Th>
            <Th k="total" r>
              Custo total
            </Th>
            <Th k="unit" r>
              Custo unitário
            </Th>
            <Th k="venda" r>
              Venda
            </Th>
            <Th k="margem" r>
              Margem
            </Th>
          </div>

          {visiveis.map((r) => (
            <div
              key={r.id}
              role="button"
              tabIndex={0}
              className={`mfg-tr mfg-row${sel.includes(r.id) ? ' sel' : ''}`}
              onClick={() => setOpenId(r.id)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  setOpenId(r.id);
                }
              }}
            >
              <Checkbox
                checked={sel.includes(r.id)}
                // §4.2 — clicar no checkbox NÃO abre o drawer.
                onClick={(e) => e.stopPropagation()}
                onCheckedChange={() =>
                  setSel((s) => (s.includes(r.id) ? s.filter((x) => x !== r.id) : [...s, r.id]))
                }
                aria-label={`Selecionar ${r.name}`}
              />
              <span className="mfg-name">
                <b>{r.name}</b>
                <span className="mfg-sku">
                  {r.sku} · {r.n_ingredientes} ingrediente{r.n_ingredientes === 1 ? '' : 's'}
                </span>
              </span>
              <span className="mfg-cat">
                {r.cat} <i>/ {r.sub}</i>
              </span>
              {/* R-09 — a coluna DECLARA a unidade que está exibindo. Com sub-unidade de
                  saída, mostra a quantidade convertida COM o rótulo da sub-unidade. */}
              <span className="mfg-num r">
                {r.sub_un && r.sub_fator
                  ? num(r.custos.qtd_liq * r.sub_fator, 2)
                  : num(r.custos.qtd_liq, 2)}
                <span className="mfg-u">{r.sub_un ?? r.un}</span>
              </span>
              <span className="mfg-num r">{fmt(r.custos.total)}</span>
              <span className="mfg-num r">{fmt(r.custos.unit)}</span>
              <span className="mfg-num dim r">{fmt(r.venda)}</span>
              <span className="r">
                {/* R-10 — 3 faixas de cor. */}
                <span className={`mfg-pill ${faixaMargem(r.custos.margem)}`}>
                  {num(r.custos.margem, 0)}%
                </span>
              </span>
            </div>
          ))}

          {filtradas.length === 0 && (
            <div className="mfg-empty">
              <b>Nenhuma receita encontrada</b>
              <span>Ajuste a busca, troque a categoria ou limpe o filtro de KPI.</span>
            </div>
          )}
        </div>

        {filtradas.length > POR_PAG && (
          <div className="mfg-pag">
            <span>
              {(pagina - 1) * POR_PAG + 1}–{Math.min(pagina * POR_PAG, filtradas.length)} de{' '}
              {filtradas.length}
            </span>
            <span className="sp" />
            <button type="button" disabled={pagina === 1} onClick={() => setPag(pagina - 1)}>
              ‹
            </button>
            {Array.from({ length: nPags }, (_, i) => i + 1).map((n) => (
              <button
                type="button"
                key={n}
                className={n === pagina ? 'act' : ''}
                onClick={() => setPag(n)}
              >
                {n}
              </button>
            ))}
            <button type="button" disabled={pagina === nPags} onClick={() => setPag(pagina + 1)}>
              ›
            </button>
          </div>
        )}
      </div>

      {/* §4.2 BulkBar. A 3ª ação do protótipo — "Atualizar preço de venda do produto" — NÃO
          entra: §18.1 diz literalmente "Não implemente esse fator 2" e a regra de markup real
          não foi decidida. Escrever em N preços é Tier 0 de VALOR (proibicoes.md §REGRA MESTRE),
          exige dupla prova + antes→depois + aprovação [W]. Fica declarado, não silenciado.
          A flag `enable_updating_product_price` vem no payload esperando essa decisão. */}
      {sel.length > 0 && (
        <div className="mfg-bulk">
          <b>
            {sel.length} receita{sel.length > 1 ? 's' : ''} selecionada{sel.length > 1 ? 's' : ''}
          </b>
          <span className="sp" />
          <Button variant="ghost" size="sm" onClick={() => setSel([])}>
            Limpar
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setImprimir({ itens: selecionadas, semCusto: false })}
          >
            <Printer className="mr-1.5 h-3.5 w-3.5" /> Imprimir fichas
          </Button>
        </div>
      )}

      {aberta && (
        <RecipeDrawer
          r={aberta}
          perms={permissions}
          precoEmMassaLiberado={settings.enable_updating_product_price}
          onClose={() => setOpenId(null)}
          onImprimir={(semCusto) => setImprimir({ itens: [aberta], semCusto })}
        />
      )}

      {imprimir && (
        <FichaPrint
          itens={imprimir.itens}
          semCusto={imprimir.semCusto}
          onDone={() => setImprimir(null)}
        />
      )}
    </div>
  );
}

/** §4.3 — drawer de LEITURA da receita. Grupos com subtotal + quadro de custo + a nota. */
function RecipeDrawer({
  r,
  perms,
  precoEmMassaLiberado,
  onClose,
  onImprimir,
}: {
  r: Receita;
  perms: Permissoes;
  /** `business.manufacturing_settings.enable_updating_product_price` — hoje só informa. */
  precoEmMassaLiberado: boolean;
  onClose: () => void;
  onImprimir: (semCusto: boolean) => void;
}) {
  return (
    <>
      {/* R-14 — clique no scrim fecha (o `esc` está no listener da página). */}
      <div className="mfg-scrim" onClick={onClose} aria-hidden />
      <aside className="mfg-drw" role="dialog" aria-label={`Receita ${r.name}`}>
        <div className="mfg-drw-h">
          <div>
            <h2>{r.name}</h2>
            <p>
              {r.sku} · {r.cat} / {r.sub} · rende {num(r.custos.qtd_liq, 2)} {r.un}
              {r.atualizado ? ` · atualizado ${r.atualizado}` : ''}
            </p>
          </div>
          <button type="button" className="mfg-x" onClick={onClose} aria-label="Fechar">
            ✕
          </button>
        </div>

        <div className="mfg-drw-b">
          {r.grupos.map((g) => (
            <div className="mfg-grp" key={g.g}>
              <div className="mfg-grp-h">
                <b>{g.g}</b>
                <span className="v">{fmt(g.subtotal)}</span>
              </div>
              {g.itens.map((i) => (
                <div className="mfg-ing" key={i.id}>
                  <span className="n">
                    {i.nome}
                    <small>
                      {i.sku}
                      {i.multiplicador > 1
                        ? ` · ${num(i.quantidade * i.multiplicador, 3)} ${i.unidade_base}`
                        : ''}
                    </small>
                  </span>
                  <span className="m">
                    {num(i.quantidade, i.quantidade < 1 ? 3 : 2)} {i.unidade}
                  </span>
                  <span className="m">{fmt(i.custo_unitario)}</span>
                  <span className="m tot">{fmt(i.subtotal)}</span>
                </div>
              ))}
              {g.itens.length === 0 && <p className="mfg-pick-empty">Grupo sem ingredientes.</p>}
            </div>
          ))}

          <div className="mfg-sec">
            <span>Custo</span>
            <span className="ln" />
          </div>
          <dl className="mfg-tot">
            <dt>Ingredientes</dt>
            <dd>{fmt(r.custos.ingredientes)}</dd>
            <dt>Custo extra ({rotuloCustoExtra(r.custo_tipo, r.extra, r.un)})</dt>
            <dd>{fmt(r.custos.extra)}</dd>
            <dt>Desperdício</dt>
            <dd>
              {num(r.waste, 0)}% · rende {num(r.custos.qtd_liq, 2)} de {num(r.qtd, 2)} {r.un}
            </dd>
            {r.sub_un && r.sub_fator ? (
              <>
                <dt>Sub-unidade de saída</dt>
                <dd>
                  {num(r.custos.qtd_liq * r.sub_fator, 2)} {r.sub_un}
                </dd>
              </>
            ) : null}
            <hr />
            <dt className="mfg-tot-big-dt">Custo por {r.un}</dt>
            <dd className="mfg-tot-big-dd">{fmt(r.custos.unit)}</dd>
            <dt>Preço de venda atual</dt>
            <dd>{fmt(r.venda)}</dd>
            <dt>Margem</dt>
            <dd>{num(r.custos.margem, 1)}%</dd>
          </dl>

          {/* Texto verbatim §4.3 — é a frase que explica por que o número muda sozinho. */}
          <p className="mfg-note">
            O custo é recalculado a cada leitura a partir do preço atual dos ingredientes — a
            receita não guarda valor congelado. Uma compra de insumo salva em{' '}
            <button type="button" className="mfg-link" onClick={() => router.visit('/purchases')}>
              Compras
            </button>{' '}
            muda este número.
            {precoEmMassaLiberado
              ? ' A configuração de atualizar o preço do produto está ligada e vale para a produção;' +
                ' a atualização em massa a partir desta lista ainda não foi liberada.'
              : ''}
          </p>
        </div>

        <div className="mfg-drw-f">
          <Button variant="ghost" size="sm" onClick={onClose}>
            Fechar
          </Button>
          <Button variant="ghost" size="sm" onClick={() => onImprimir(false)}>
            <Printer className="mr-1.5 h-3.5 w-3.5" /> Ficha com custo
          </Button>
          <Button variant="ghost" size="sm" onClick={() => onImprimir(true)}>
            Via de produção
          </Button>
          {perms.prod && (
            <Button asChild variant="ghost" size="sm">
              <a href={ROTA_PRODUZIR}>Produzir</a>
            </Button>
          )}
          {perms.editar && (
            <Button asChild variant="ghost" size="sm">
              <a href={`${ROTA_EDITAR_INGREDIENTES}${r.variation_id}`}>
                <Pencil className="mr-1.5 h-3.5 w-3.5" /> Editar ingredientes
              </a>
            </Button>
          )}
        </div>
      </aside>
    </>
  );
}

Recipes.layout = (page: ReactNode) => (
  <AppShellV2
    title="Receitas · Manufacturing"
    breadcrumbItems={[{ label: 'Manufacturing' }, { label: 'Receitas' }]}
  >
    {page}
  </AppShellV2>
);
