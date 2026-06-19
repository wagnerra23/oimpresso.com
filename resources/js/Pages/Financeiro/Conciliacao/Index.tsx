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
import { Upload, Check, X, Search, Inbox, RotateCcw } from 'lucide-react';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader } from '@/Components/PageHeader';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';

interface Linha {
  uid: string;                  // "ofx:123" | "api:456" — chave estável (2 origens)
  origem: 'ofx' | 'api';        // OFX upload manual | extrato sync API (ADR 0236 Fase 1)
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
  filters: { incluir_resolvidos: boolean };
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

const STATUS_CLR: Record<Linha['status'], string> = {
  pendente:   'bg-stone-50 text-stone-700 border-stone-200',
  sugerido:   'bg-amber-50 text-amber-700 border-amber-200',
  conciliado: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  ignorado:   'bg-stone-100 text-stone-400 border-stone-200',
};

function FinanceiroConciliacao({ linhas, stats, contas, filters }: Props) {
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

  const confirmarMatch = (lineId: number, tituloId: number, origem: Linha['origem']) => {
    router.post(`/financeiro/conciliacao/${lineId}/match`, { titulo_id: tituloId, origem }, {
      preserveScroll: true,
    });
  };

  const ignorar = (lineId: number, origem: Linha['origem']) => {
    router.post(`/financeiro/conciliacao/${lineId}/ignorar`, { origem }, { preserveScroll: true });
  };

  // Reabrir (undo): volta linha conciliada/ignorada pra pendente.
  const reabrir = (lineId: number, origem: Linha['origem']) => {
    router.post(`/financeiro/conciliacao/${lineId}/reabrir`, { origem }, { preserveScroll: true });
  };

  // Toggle "ver conciliados/ignorados" — recarrega o index com/sem o query param.
  // GET só dos dados de `linhas`+`filters` (preserva scroll/estado de form).
  const toggleResolvidos = () => {
    router.get(
      '/financeiro/conciliacao',
      filters.incluir_resolvidos ? {} : { incluir_resolvidos: 1 },
      { preserveScroll: true, preserveState: true, only: ['linhas', 'filters'] },
    );
  };

  const filtradas = linhas.filter((l) =>
    !busca || l.descricao.toLowerCase().includes(busca.toLowerCase())
  );

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Onda 19 — header canon */}
      {/* Wave 4 (2026-05-25): migrado pra <PageHeader> canon v3.8 */}
      <PageHeader
        title="Conciliação"
        suffix=" · OFX bancário"
        subtitle={<>Importe extrato OFX → parser detecta transações → fuzzy match com títulos abertos → aprovar manualmente</>}
      >
        <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
          <FinanceiroSubNav active="conciliacao" hidePrimary />
        </div>
      </PageHeader>

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
              <p className="text-[11px] text-destructive mt-1">{uploadForm.errors.arquivo}</p>
            )}
          </div>
          {contas.length > 0 && (
            <div className="min-w-[200px]">
              <label htmlFor="conta_id" className="text-[11px] uppercase tracking-widest text-stone-500 font-medium block mb-1">
                Conta bancária (opcional)
              </label>
              <Select
                value={uploadForm.data.conta_bancaria_id || '__none__'}
                onValueChange={(v) => uploadForm.setData('conta_bancaria_id', v === '__none__' ? '' : v)}
              >
                <SelectTrigger id="conta_id" className="w-full" aria-label="Conta bancária">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">Detectar do arquivo</SelectItem>
                  {contas.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
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

      {/* Filtro busca + toggle "ver conciliados/ignorados" */}
      {linhas.length > 0 && (
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <div className="fin-search-wrap" style={{ maxWidth: 400, flex: 1 }}>
            <Search size={13} aria-hidden="true" />
            <input
              placeholder="Buscar por descrição…"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
            />
          </div>
          <label htmlFor="conc-incluir-resolvidos" className="flex items-center gap-1.5 text-[12px] text-stone-600 cursor-pointer select-none">
            <Checkbox
              id="conc-incluir-resolvidos"
              checked={filters.incluir_resolvidos}
              onCheckedChange={() => toggleResolvidos()}
            />
            Ver conciliados/ignorados
          </label>
        </div>
      )}

      {/* Tabela linhas */}
      <div className="mt-3 rounded-md border border-stone-200 overflow-hidden">
        <table className="w-full text-[13px]">
          <thead>
            <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
              <th className="px-3 py-2 text-left font-medium w-[100px]">Data</th>
              <th className="px-3 py-2 text-center font-medium w-[80px]">Origem</th>
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
                <td colSpan={7} className="py-12 text-center text-stone-500">
                  {linhas.length === 0
                    ? 'Nenhuma linha importada. Faça upload de um arquivo OFX acima pra começar.'
                    : 'Nenhuma linha encontrada com filtros atuais.'}
                </td>
              </tr>
            )}
            {filtradas.map((l) => (
              <tr key={l.uid} className="border-b border-stone-100 hover:bg-stone-50/50">
                <td className="px-3 py-2 font-mono text-[12px] text-stone-600">{l.data_movimento.slice(0, 10)}</td>
                <td className="px-3 py-2 text-center">
                  <span
                    className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-medium border ${
                      l.origem === 'api'
                        ? 'bg-accent text-accent-foreground border-transparent'
                        : 'bg-transparent text-muted-foreground border-border'
                    }`}
                    title={l.origem === 'api' ? 'Sincronizado via API do banco' : 'Importado de arquivo OFX'}
                  >
                    {l.origem === 'api' ? 'Banco' : 'OFX'}
                  </span>
                </td>
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
                      onClick={() => confirmarMatch(l.id, l.titulo_id!, l.origem)}
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
                      onClick={() => ignorar(l.id, l.origem)}
                      title="Ignorar linha"
                    >
                      <X size={11} /> Ignorar
                    </button>
                  )}
                  {(l.status === 'conciliado' || l.status === 'ignorado') && (
                    <button
                      type="button"
                      className="os-btn ghost"
                      style={{ padding: '4px 8px', fontSize: 11 }}
                      onClick={() => reabrir(l.id, l.origem)}
                      title="Reabrir — volta para pendente"
                    >
                      <RotateCcw size={11} /> Reabrir
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
        <span className="inline-flex items-center gap-1"><Inbox className="h-3.5 w-3.5" /> Parser OFX simples · próxima Onda: CNAB + Open Banking API</span>
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
