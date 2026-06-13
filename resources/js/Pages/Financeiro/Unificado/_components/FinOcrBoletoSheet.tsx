/**
 * FinOcrBoletoSheet — Onda 23 (2026-05-20) US-FIN-029
 * -----------------------------------------------------
 * KILLER feature vs Conta Azul: Eliana cola foto/PDF do boleto recebido →
 * sistema extrai linha digitável + valor + vencimento + beneficiário
 * automaticamente, pré-preenche form Novo Título (a pagar).
 *
 * Fluxo UI:
 *   1. Upload (PNG/JPG/PDF max 5MB)
 *   2. Spinner OCR (~3-5s)
 *   3. Preview campos extraídos (editáveis pra correção)
 *   4. CTA "Cadastrar título" → POST /financeiro/unificado (store) com pré-preenchimento
 *
 * Backend: POST /financeiro/unificado/ocr-boleto retorna {success, extracted: {...}}.
 */
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

interface OcrExtracted {
  linha_digitavel: string;
  codigo_barras: string | null;
  valor: number | null;
  vencimento: string | null;
  beneficiario_nome: string | null;
  beneficiario_cnpj_masked: string | null;
  beneficiario_cnpj: string | null;
  pagador_nome: string | null;
  confidence: number | null;
}

interface Props {
  open: boolean;
  onClose: () => void;
}

const brl = (v: number | null) =>
  v != null
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)
    : '—';

export function FinOcrBoletoSheet({ open, onClose }: Props) {
  const [phase, setPhase] = useState<'upload' | 'processing' | 'preview' | 'submitting'>('upload');
  const [error, setError] = useState<string | null>(null);
  const [extracted, setExtracted] = useState<OcrExtracted | null>(null);
  const [costUsd, setCostUsd] = useState<number>(0);
  const [fromCache, setFromCache] = useState<boolean>(false);

  // Campos editáveis pós-OCR.
  const [editValor, setEditValor] = useState('');
  const [editVencimento, setEditVencimento] = useState('');
  const [editContraparte, setEditContraparte] = useState('');
  const [editDescricao, setEditDescricao] = useState('');

  const reset = () => {
    setPhase('upload');
    setError(null);
    setExtracted(null);
    setEditValor('');
    setEditVencimento('');
    setEditContraparte('');
    setEditDescricao('');
  };

  const handleClose = () => {
    reset();
    onClose();
  };

  const onFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
      setError('Arquivo muito grande (limite 5MB).');
      return;
    }

    setPhase('processing');
    setError(null);

    const formData = new FormData();
    formData.append('arquivo', file);

    try {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
      const resp = await fetch('/financeiro/unificado/ocr-boleto', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfMeta?.content ?? '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const data = await resp.json();

      if (!resp.ok || !data.success) {
        setError(data.error ?? `Falha (HTTP ${resp.status}).`);
        setPhase('upload');
        return;
      }

      setExtracted(data.extracted);
      setCostUsd(data.cost_usd ?? 0);
      setFromCache(data.from_cache ?? false);
      setEditValor(data.extracted.valor != null ? String(data.extracted.valor) : '');
      setEditVencimento(data.extracted.vencimento ?? '');
      setEditContraparte(data.extracted.beneficiario_nome ?? '');
      setEditDescricao(`Boleto OCR ${data.extracted.linha_digitavel.slice(0, 20)}...`);
      setPhase('preview');
    } catch (err) {
      setError(`Falha de rede: ${(err as Error).message ?? err}`);
      setPhase('upload');
    }
  };

  const handleSubmit = () => {
    if (!extracted) return;

    setPhase('submitting');

    const payload = {
      tipo: 'pagar',
      contraparte_descricao: editContraparte || extracted.beneficiario_nome || 'OCR boleto',
      contraparte_cnpj: extracted.beneficiario_cnpj || null,
      valor: parseFloat(editValor.replace(',', '.')) || 0,
      vencimento: editVencimento,
      descricao: editDescricao,
      linha_digitavel: extracted.linha_digitavel,
      codigo_barras: extracted.codigo_barras,
      origem: 'ocr_boleto',
    };

    router.post('/financeiro/unificado', payload, {
      preserveScroll: true,
      onSuccess: () => {
        handleClose();
      },
      onError: (errors) => {
        const msg = Object.values(errors).flat().join(' · ') || 'Erro ao cadastrar título.';
        setError(msg);
        setPhase('preview');
      },
    });
  };

  return (
    <Sheet open={open} onOpenChange={(o) => !o && handleClose()}>
      <SheetContent side="right" className="w-full sm:max-w-lg overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="text-xl font-semibold tracking-tight">
            📷 Importar boleto via foto/PDF
          </SheetTitle>
          <p className="text-[13px] text-stone-500 leading-relaxed">
            Cole ou tire foto do boleto recebido — sistema extrai linha digitável, valor,
            vencimento e beneficiário automaticamente via IA (OpenAI Vision).
          </p>
        </SheetHeader>

        <div className="mt-6 space-y-6">
          {error && (
            <div className="text-[13px] text-destructive-fg bg-destructive-soft border border-destructive/20 rounded px-3 py-2">
              {error}
            </div>
          )}

          {phase === 'upload' && (
            <div className="space-y-4">
              <label className="block">
                <span className="text-[13px] font-medium text-stone-700">
                  Selecionar arquivo (PNG, JPG ou PDF · máx 5MB)
                </span>
                <input
                  type="file"
                  accept=".png,.jpg,.jpeg,.pdf"
                  onChange={onFileChange}
                  className="mt-2 block w-full text-[13px] text-stone-600
                             file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0
                             file:text-[13px] file:font-medium file:bg-stone-900 file:text-white
                             hover:file:bg-stone-800 cursor-pointer"
                />
              </label>

              <div className="text-[12px] text-stone-500 leading-relaxed border-t border-stone-100 pt-3">
                💡 <strong>Dica:</strong> tire foto frontal nítida, sem cortes nas bordas.
                PDF ainda não suportado nesta versão — converta para imagem (screenshot).
              </div>
            </div>
          )}

          {phase === 'processing' && (
            <div className="py-12 text-center space-y-3">
              <div className="text-4xl animate-pulse">🔍</div>
              <p className="text-[14px] font-medium text-stone-700">Analisando boleto...</p>
              <p className="text-[12px] text-stone-500">
                Identificando linha digitável e campos via IA. Demora ~3-5 segundos.
              </p>
            </div>
          )}

          {phase === 'preview' && extracted && (
            <div className="space-y-4">
              <div className="bg-success-soft border border-success/20 rounded px-3 py-2 text-[12px] text-success-fg">
                ✓ Extração bem-sucedida {extracted.confidence != null && (
                  <span className="ml-1 opacity-75">(confiança {Math.round(extracted.confidence * 100)}%)</span>
                )}
                {fromCache && <span className="ml-2 italic opacity-75">· reaproveitado (mesmo arquivo)</span>}
                {!fromCache && costUsd > 0 && (
                  <span className="ml-2 opacity-60">· US$ {costUsd.toFixed(4)}</span>
                )}
              </div>

              <div className="space-y-3">
                <div>
                  <label className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
                    Linha digitável
                  </label>
                  <div className="font-mono text-[12px] text-stone-700 bg-stone-50 border border-stone-200 rounded px-2 py-1 mt-1 break-all">
                    {extracted.linha_digitavel}
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
                      Valor (R$)
                    </label>
                    <Input
                      type="text"
                      value={editValor}
                      onChange={(e) => setEditValor(e.target.value)}
                      placeholder="0,00"
                      className="mt-1 text-[13px]"
                    />
                    {extracted.valor != null && (
                      <div className="text-[10.5px] text-stone-500 mt-0.5">
                        OCR detectou: {brl(extracted.valor)}
                      </div>
                    )}
                  </div>

                  <div>
                    <label className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
                      Vencimento
                    </label>
                    <Input
                      type="date"
                      value={editVencimento}
                      onChange={(e) => setEditVencimento(e.target.value)}
                      className="mt-1 text-[13px]"
                    />
                  </div>
                </div>

                <div>
                  <label className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
                    Beneficiário
                  </label>
                  <Input
                    type="text"
                    value={editContraparte}
                    onChange={(e) => setEditContraparte(e.target.value)}
                    placeholder="Nome do beneficiário"
                    className="mt-1 text-[13px]"
                  />
                  {extracted.beneficiario_cnpj_masked && (
                    <div className="text-[10.5px] text-stone-500 mt-0.5 font-mono">
                      CNPJ: {extracted.beneficiario_cnpj_masked}
                    </div>
                  )}
                </div>

                <div>
                  <label className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
                    Descrição
                  </label>
                  <Input
                    type="text"
                    value={editDescricao}
                    onChange={(e) => setEditDescricao(e.target.value)}
                    className="mt-1 text-[13px]"
                  />
                </div>
              </div>

              <div className="flex gap-2 pt-3 border-t border-stone-100">
                <Button variant="outline" onClick={reset} className="flex-1">
                  Outro arquivo
                </Button>
                <Button onClick={handleSubmit} className="flex-1">
                  Cadastrar título a pagar
                </Button>
              </div>
            </div>
          )}

          {phase === 'submitting' && (
            <div className="py-12 text-center space-y-3">
              <div className="text-4xl animate-pulse">💾</div>
              <p className="text-[14px] font-medium text-stone-700">Cadastrando título...</p>
            </div>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
