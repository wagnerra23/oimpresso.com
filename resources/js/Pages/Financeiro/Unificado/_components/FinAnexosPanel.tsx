/**
 * FinAnexosPanel — US-FIN-026 (Onda 22)
 * --------------------------------------
 * Lista + upload + download + delete de anexos (NF/comprovante/PDF/imagem)
 * de um título financeiro. Fecha workflow Anexos NF (Onda 20 era só POST upload).
 *
 * Backend canon:
 *   GET    /financeiro/unificado/{id}/anexos                    listarAnexos
 *   POST   /financeiro/unificado/{id}/anexos  (multipart)       anexar
 *   GET    /financeiro/unificado/{id}/anexos/{anexoId}/download baixarAnexo (stream)
 *   DELETE /financeiro/unificado/{id}/anexos/{anexoId}          removerAnexo (soft)
 *
 * UX:
 *  - lazy fetch on mount (apenas quando drawer abre + tab detalhes ativa)
 *  - thumbnail por mime (📄 PDF · 🖼 imagem · 📎 outros)
 *  - lista compacta com nome, tamanho humanizado, data upload, ações
 *  - confirma destructive (delete) via window.confirm
 *  - business_id scope é responsabilidade do controller (zero cliente)
 */
import { router } from '@inertiajs/react';
import { useEffect, useState, useCallback } from 'react';

interface Anexo {
  id: number;
  nome: string;
  mime: string;
  tamanho_bytes: number;
  uploaded_by: number | null;
  created_at: string;
}

interface Props {
  tituloId: number;
}

function humanSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function thumbForMime(mime: string): string {
  if (mime?.startsWith('image/')) return '🖼';
  if (mime === 'application/pdf') return '📄';
  if (mime === 'application/xml' || mime === 'text/xml') return '📋';
  return '📎';
}

function dateLabel(iso: string): string {
  try {
    const d = new Date(iso);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: '2-digit' })
      + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  } catch {
    return iso;
  }
}

export function FinAnexosPanel({ tituloId }: Props) {
  const [anexos, setAnexos] = useState<Anexo[]>([]);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  // Fila P7a (inventário 2026-07-07) — drag&drop + seleção múltipla
  // (protótipo financeiro-ops.jsx:203-205). Estado visual da zona de drop.
  const [dragOver, setDragOver] = useState(false);

  const fetchAnexos = useCallback(() => {
    setLoading(true);
    setErrorMsg(null);
    fetch(`/financeiro/unificado/${tituloId}/anexos`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((data) => setAnexos(data.anexos ?? []))
      .catch((e) => setErrorMsg(`Falha ao carregar anexos: ${e.message ?? e}`))
      .finally(() => setLoading(false));
  }, [tituloId]);

  useEffect(() => { fetchAnexos(); }, [fetchAnexos]);

  // Rota aceita 1 arquivo por request — múltiplos entram em fila sequencial
  // (client-side, mesmo endpoint). fetchAnexos + uploading=false só no fim da fila.
  const enviarArquivos = (files: File[] | FileList) => {
    const fila = Array.from(files);
    if (fila.length === 0) return;
    setUploading(true);
    const enviar = (i: number) => {
      const arq = fila[i];
      if (!arq) { setUploading(false); fetchAnexos(); return; }
      const formData = new FormData();
      formData.append('arquivo', arq);
      router.post(`/financeiro/unificado/${tituloId}/anexos`, formData, {
        forceFormData: true,
        preserveScroll: true,
        onError: () => setErrorMsg(`Falha no upload de "${arq.name}" — arquivo > 10MB ou tipo inválido?`),
        onFinish: () => {
          if (i + 1 < fila.length) enviar(i + 1);
          else { setUploading(false); fetchAnexos(); }
        },
      });
    };
    enviar(0);
  };

  const onUpload = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf,.png,.jpg,.jpeg,.xml';
    input.multiple = true;
    input.onchange = (e) => {
      const files = (e.target as HTMLInputElement).files;
      if (files) enviarArquivos(files);
    };
    input.click();
  };

  const onDelete = (anexo: Anexo) => {
    if (!window.confirm(`Remover anexo "${anexo.nome}"? Ação reversível via soft-delete.`)) return;
    router.delete(`/financeiro/unificado/${tituloId}/anexos/${anexo.id}`, {
      preserveScroll: true,
      onSuccess: () => fetchAnexos(),
      onError: () => setErrorMsg('Falha ao remover anexo.'),
    });
  };

  return (
    // eslint-disable-next-line jsx-a11y/no-static-element-interactions -- drop-zone é
    // enhancement progressivo (protótipo ops.jsx:205); o caminho acessível por teclado
    // é o botão "Anexar" logo abaixo — o container não precisa ser focável.
    <div
      className={`border-t border-stone-200 pt-4 rounded-b transition-colors ${dragOver ? 'bg-primary/5 outline-dashed outline-1 outline-primary/50' : ''}`}
      onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
      onDragLeave={() => setDragOver(false)}
      onDrop={(e) => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files.length) enviarArquivos(e.dataTransfer.files); }}
    >
      <div className="flex items-center justify-between mb-2">
        <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
          Anexos {anexos.length > 0 && <span className="text-stone-400">({anexos.length})</span>}
        </div>
        <button
          type="button"
          className="os-btn ghost text-[12px]"
          onClick={onUpload}
          disabled={uploading}
        >
          {uploading ? '⏳ Enviando…' : '📎 Anexar'}
        </button>
      </div>

      {errorMsg && (
        <div className="mb-2 text-[12px] text-destructive-fg bg-destructive-soft border border-destructive/20 rounded px-2 py-1">
          {errorMsg}
        </div>
      )}

      {loading && anexos.length === 0 && (
        <div className="text-[12px] text-stone-500 italic">Carregando…</div>
      )}

      {!loading && anexos.length === 0 && (
        <div className="text-[12px] text-stone-500 italic">
          Arraste a NF, o comprovante ou a foto do boleto aqui — ou clique em <b>Anexar</b>. Aceita PDF, PNG, JPG e XML (máx 10MB).
        </div>
      )}

      {anexos.length > 0 && (
        <ul className="space-y-1">
          {anexos.map((a) => (
            <li
              key={a.id}
              className="flex items-center gap-2 px-2 py-1.5 rounded border border-stone-200 bg-stone-50/50 text-[12px]"
            >
              <span className="text-[16px]" aria-hidden="true">{thumbForMime(a.mime)}</span>
              <div className="flex-1 min-w-0">
                <div className="font-medium text-stone-800 truncate" title={a.nome}>{a.nome}</div>
                <div className="text-[10.5px] text-stone-500">
                  {humanSize(a.tamanho_bytes)} · {dateLabel(a.created_at)}
                </div>
              </div>
              <a
                href={`/financeiro/unificado/${tituloId}/anexos/${a.id}/download`}
                className="os-btn ghost text-[11px]"
                title="Baixar arquivo"
              >
                ⬇
              </a>
              <button
                type="button"
                className="os-btn ghost text-[11px]"
                style={{ color: 'oklch(0.55 0.10 25)' }}
                onClick={() => onDelete(a)}
                title="Remover anexo (soft delete)"
              >
                ✗
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
