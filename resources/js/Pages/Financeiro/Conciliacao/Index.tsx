// @memcofre tela=/financeiro/conciliacao module=Financeiro status=implementada-mvp
//
// Onda 19 (2026-05-19) #49 — Conciliação OFX MVP.
// Upload arquivo OFX → parser → linhas pendentes → match fuzzy → user aprova.
//
// Persona Eliana [E]: importa OFX do banco → revisa sugestões → aprova ou ignora.
// Hierarquia visual canon: fin-curadoria + os-page-h + fin-stats + tabela.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, router } from '@inertiajs/react';
import { type ReactNode, type FormEvent, useState } from 'react';
import { Upload, Check, X, Search } from 'lucide-react';

interface Linha {
  id: number;
  data_movimento: string;
  descricao: string;
  valor: number;
  tipo: 'credit' | 'debit' | 'fee' | 'transfer' | 'unknown';
  status: 'pendente' | 'sugerido' | 'conciliado' | 'ignorado';
  titulo_id: number | null;
  match_score: number | null;
  source_file: string | null;
}

interface Stats {
  pendentes: number;
  sugeridos: number;
  conciliados: number;
  ignorados: number;
}

interface Props {
  linhas: Linha[];
  stats: Stats;
  contas: { id: number; nome: string }[];
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

const STATUS_CLR: Record<Linha['status'], string> = {
  pendente:   'bg-stone-50 text-stone-700 border-stone-200',
  sugerido:   'bg-amber-50 text-amber-700 border-amber-200',
  conciliado: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  ignorado:   'bg-stone-100 text-stone-400 border-stone-200',
};

function FinanceiroConciliacao({ linhas, stats, contas }: Props) {
  const [busca, setBusca] = useState('');

  const uploadForm = useForm<{ arquivo: File | null; conta_bancaria_id: string }>({
    arquivo: null,
    conta_bancaria_id: '',
  });

  const submitUpload = (e: FormEvent) => {
    e.preventDefault();
    if (!uploadForm.data.arquivo) return;
    uploadForm.post('/financeiro/conciliacao/upload', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        uploadForm.reset();
        const input = document.getElementById('ofx-file') as HTMLInputElement | null;
        if (input) input.value = '';
      },
    });
  };

  const confirmarMatch = (lineId: number, tituloId: number) => {
    router.post(`/financeiro/conciliacao/${lineId}/match`, { titulo_id: tituloId }, {
      preserveScroll: true,
    });
  };

  const ignorar = (lineId: number) => {
    router.post(`/financeiro/conciliacao/${lineId}/ignorar`, {}, { preserveScroll: true });
  };

  const filtradas = linhas.filter((l) =>
    !busca || l.descricao.toLowerCase().includes(busca.toLowerCase())
  );

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Onda 19 — header canon */}
      <header className="os-page-h fin-page-h">
        <div className="os-page-h-l fin-page-h-l">
          <h1>Conciliação <span className="fin-hero-title-sub">· OFX bancário</span></h1>
          <p>Importe extrato OFX → parser detecta transações → fuzzy match com títulos abertos → aprovar manualmente</p>
        </div>
      </header>

      {/* KPI strip canon */}
      <div className="fin-stats">
        <div className="fin-stat fin-stat-hero">
          <small>PENDENTES</small>
          <b>{stats.pendentes}</b>
          <span className="fin-stat-hint">linhas sem match automático</span>
        </div>
        <div className="fin-stat">
          <small>SUGERIDOS</small>
          <b className="fin-num-pos">{stats.sugeridos}</b>
          <span className="fin-stat-hint">match fuzzy proposto</span>
        </div>
        <div className="fin-stat">
          <small>CONCILIADOS</small>
          <b className="fin-num-pos">{stats.conciliados}</b>
          <span className="fin-stat-hint">aprovados pelo usuário</span>
        </div>
        <div className="fin-stat">
          <small>IGNORADOS</small>
          <b>{stats.ignorados}</b>
          <span className="fin-stat-hint">marcados pra fora do fluxo</span>
        </div>
      </div>

      {/* Upload form */}
      <div className="mt-4 p-4 bg-stone-50 border border-stone-200 rounded-md">
        <h3 className="text-[14px] font-semibold mb-3 flex items-center gap-2">
          <Upload size={14} />
          Importar extrato OFX
        </h3>
        <form onSubmit={submitUpload} className="flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[280px]">
            <label htmlFor="ofx-file" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium block mb-1">
              Arquivo OFX
            </label>
            <input
              id="ofx-file"
              type="file"
              accept=".ofx,.qfx,text/plain"
              onChange={(e) => uploadForm.setData('arquivo', e.target.files?.[0] ?? null)}
              className="text-[13px]"
            />
            {uploadForm.errors.arquivo && (
              <p className="text-[11px] text-rose-600 mt-1">{uploadForm.errors.arquivo}</p>
            )}
          </div>
          {contas.length > 0 && (
            <div className="min-w-[200px]">
              <label htmlFor="conta_id" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium block mb-1">
                Conta bancária (opcional)
              </label>
              <select
                id="conta_id"
                value={uploadForm.data.conta_bancaria_id}
                onChange={(e) => uploadForm.setData('conta_bancaria_id', e.target.value)}
                className="h-8 px-2 rounded-md border border-stone-300 text-[13px] bg-white w-full"
              >
                <option value="">Detectar do arquivo</option>
                {contas.map((c) => (
                  <option key={c.id} value={c.id}>{c.nome}</option>
                ))}
              </select>
            </div>
          )}
          <button
            type="submit"
            className="os-btn primary"
            disabled={!uploadForm.data.arquivo || uploadForm.processing}
          >
            <Upload size={13} />
            {uploadForm.processing ? 'Processando…' : 'Importar OFX'}
          </button>
        </form>
        <p className="text-[11px] text-stone-500 mt-2">
          Suporte OFX simples (parser detecta &lt;STMTTRN&gt; blocks). Pra CNAB / Open Banking API, próxima Onda.
        </p>
      </div>

      {/* Filtro busca */}
      {linhas.length > 0 && (
        <div className="mt-4 fin-search-wrap" style={{ maxWidth: 400 }}>
          <Search size={13} aria-hidden="true" />
          <input
            placeholder="Buscar por descrição…"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
          />
        </div>
      )}

      {/* Tabela linhas */}
      <div className="mt-3 rounded-md border border-stone-200 overflow-hidden">
        <table className="w-full text-[13px]">
          <thead>
            <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
              <th className="px-3 py-2 text-left font-medium w-[100px]">Data</th>
              <th className="px-3 py-2 text-left font-medium">Descrição</th>
              <th className="px-3 py-2 text-right font-medium w-[120px]">Valor</th>
              <th className="px-3 py-2 text-center font-medium w-[100px]">Tipo</th>
              <th className="px-3 py-2 text-center font-medium w-[110px]">Status</th>
              <th className="px-3 py-2 text-right font-medium w-[140px]">Ações</th>
            </tr>
          </thead>
          <tbody>
            {filtradas.length === 0 && (
              <tr>
                <td colSpan={6} className="py-12 text-center text-stone-500">
                  {linhas.length === 0
                    ? 'Nenhuma linha importada. Faça upload de um arquivo OFX acima pra começar.'
                    : 'Nenhuma linha encontrada com filtros atuais.'}
                </td>
              </tr>
            )}
            {filtradas.map((l) => (
              <tr key={l.id} className="border-b border-stone-100 hover:bg-stone-50/50">
                <td className="px-3 py-2 font-mono text-[12px] text-stone-600">{l.data_movimento.slice(0, 10)}</td>
                <td className="px-3 py-2">
                  <div className="truncate max-w-[400px]">{l.descricao}</div>
                  {l.source_file && (
                    <div className="text-[10px] text-stone-400">{l.source_file}</div>
                  )}
                </td>
                <td className="px-3 py-2 text-right font-mono tabular-nums">
                  <span className={l.valor < 0 ? 'fin-num-neg' : 'fin-num-pos'}>{brl(l.valor)}</span>
                </td>
                <td className="px-3 py-2 text-center text-[11px] text-stone-500">{l.tipo}</td>
                <td className="px-3 py-2 text-center">
                  <span className={`inline-block px-2 py-0.5 rounded border text-[11px] font-medium ${STATUS_CLR[l.status]}`}>
                    {l.status}
                  </span>
                  {l.match_score && (
                    <div className="text-[10px] text-stone-400 mt-0.5">match {(l.match_score * 100).toFixed(0)}%</div>
                  )}
                </td>
                <td className="px-3 py-2 text-right">
                  {l.status === 'sugerido' && l.titulo_id && (
                    <button
                      type="button"
                      className="os-btn ghost fin-btn-trilha"
                      style={{ padding: '4px 8px', fontSize: 11 }}
                      onClick={() => confirmarMatch(l.id, l.titulo_id!)}
                      title="Confirmar match"
                    >
                      <Check size={11} /> Confirmar
                    </button>
                  )}
                  {(l.status === 'pendente' || l.status === 'sugerido') && (
                    <button
                      type="button"
                      className="os-btn ghost"
                      style={{ padding: '4px 8px', fontSize: 11, color: 'oklch(0.55 0.10 25)' }}
                      onClick={() => ignorar(l.id)}
                      title="Ignorar linha"
                    >
                      <X size={11} /> Ignorar
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Footer canon */}
      <div className="fin-footer-tips">
        <span className="fin-footer-summary">
          <b>{filtradas.length}</b> linhas listadas
          <span className="fin-footer-sep">·</span>
          <b>{stats.pendentes}</b> pendentes
          <span className="fin-footer-sep">·</span>
          <b className="fin-num-pos">{stats.sugeridos}</b> sugeridos
          <span className="fin-footer-sep">·</span>
          <b className="fin-num-pos">{stats.conciliados}</b> conciliados
        </span>
        <span className="spacer" />
        <span>📥 Parser OFX simples · próxima Onda: CNAB + Open Banking API</span>
      </div>
    </div>
  );
}

FinanceiroConciliacao.layout = (page: ReactNode) => (
  <AppShellV2
    title="Conciliação OFX — Financeiro"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Conciliação' }]}
  >
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default FinanceiroConciliacao;
