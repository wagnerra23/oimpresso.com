// "Como você quer preencher os tributos?" — escolha do passo 1 da natureza nova e o painel da nota de referência
// (UC-NFTR-73..77). Forma: prototipo-ui/cowork/Felipe/Configuracao de Tributos.html, `passoNat()` e `painelImport()`.
// O arquivo é lido aqui, no navegador (`lerNotaReferencia`); nada do XML vai ao servidor.
//
// Fica de fora o botão "Usar XML de exemplo" do protótipo: ele preenche a natureza com uma nota de demonstração,
// o que numa empresa real cadastraria tributos que ninguém conferiu.
import { useRef, useState, type DragEvent } from 'react';
import { FileUp, Pencil, Upload } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Grid, Inline, Stack } from '@/Components/layout';
import type { Importacao, Situacao } from './importarXml';

export type ModoPreenchimento = 'branco' | 'importar' | null;

const NOME_PASSO: Record<Importacao['linhas'][number]['passo'], string> = {
  nat: 'Natureza', cfop: 'CFOP', icms: 'ICMS', ipi: 'IPI', pc: 'PIS e COFINS', ibs: 'IBS e CBS',
};
const SITUACAO: Record<Situacao, [string, string]> = {
  ok: ['Copiado', 'var(--pos)'], ajuste: ['Ajustado', 'var(--warn)'], nao: ['Não encontrado', 'var(--text-mute)'],
};
const TH = 'whitespace-nowrap border-b border-[var(--border)] bg-[var(--bg-2)] px-2.5 py-2 text-left text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]';
const TD = 'border-b border-[var(--border-2)] px-2.5 py-[9px] align-top text-[12.5px] text-[var(--text)]';

function Opcao({ ativo, onClick, icone: Ico, titulo, texto }: {
  ativo: boolean; onClick: () => void; icone: typeof Pencil; titulo: string; texto: string;
}) {
  return (
    <button type="button" aria-pressed={ativo} onClick={onClick}
      className={`flex min-w-0 flex-col gap-1.5 rounded-lg border bg-[var(--surface)] p-3.5 text-left hover:border-[color-mix(in_oklch,var(--accent)_40%,var(--border))] ${
        ativo ? 'border-[var(--accent)] shadow-[0_0_0_3px_var(--accent-soft)]' : 'border-[var(--border)]'}`}>
      <Inline asChild gap={2}>
        <b className="text-[13.5px] font-semibold text-[var(--text)]">
          <Ico aria-hidden className="h-4 w-4" style={{ color: 'color-mix(in oklch, var(--accent) 62%, var(--text))' }} />{titulo}
        </b>
      </Inline>
      <span className="text-[12px] leading-snug text-[var(--text-dim)]">{texto}</span>
    </button>
  );
}

export default function ImportarNota({ modo, onBranco, onImportar, resultado, erro, onArquivo, onOutro }: {
  modo: ModoPreenchimento;
  onBranco: () => void;
  onImportar: () => void;
  resultado: (Importacao & { arquivo: string }) | null;
  erro: string | null;
  onArquivo: (arquivo: File) => void;
  onOutro: () => void;
}) {
  const input = useRef<HTMLInputElement>(null);
  const [sobre, setSobre] = useState(false);
  const soltar = (e: DragEvent) => {
    e.preventDefault();
    setSobre(false);
    const f = e.dataTransfer.files?.[0];
    if (f) onArquivo(f);
  };
  const nOk = resultado?.linhas.filter((l) => l.situacao === 'ok').length ?? 0;
  const nAj = resultado?.linhas.filter((l) => l.situacao === 'ajuste').length ?? 0;

  return (
    <Stack gap={3} className="border-t border-[var(--border-2)] pt-4" data-contract="naturezas-preenchimento">
      <span className="block text-[10.5px] font-semibold uppercase tracking-[.04em] text-[var(--text-dim)]">
        Como você quer preencher os tributos?
      </span>
      <Grid gap={3} style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 240px), 1fr))' }}>
        <Opcao ativo={modo === 'branco'} onClick={onBranco} icone={Pencil} titulo="Iniciar em branco"
          texto="Você preenche CFOP, ICMS, IPI, PIS, COFINS, IBS e CBS, um passo de cada vez." />
        <Opcao ativo={modo === 'importar'} onClick={onImportar} icone={Upload} titulo="Importar nota de referência"
          texto="O sistema lê o XML de uma nota já emitida e preenche os passos com as tags encontradas. Você revisa cada um." />
      </Grid>

      {modo === 'importar' && (resultado ? (
        <Stack gap={2}>
          <Inline wrap align="start" gap={3}>
            <div className="min-w-0 flex-[1_1_260px]">
              <b className="block text-[13px] font-semibold leading-snug text-[var(--text)]">{resultado.arquivo}</b>
              <span className="block text-[11.5px] leading-snug text-[var(--text-dim)]">
                Nota {resultado.info.nNF || '—'} · {resultado.info.emitente || 'emitente não identificado'} · {resultado.info.itens}{' '}
                {resultado.info.itens > 1 ? 'itens' : 'item'} · {nOk} campos copiados, {nAj} ajustados
              </span>
            </div>
            <Button size="sm" variant="ghost" onClick={onOutro}>Importar outro XML</Button>
          </Inline>
          <div className="overflow-x-auto rounded-lg border border-[var(--border)]">
            <table className="w-full border-collapse">
              <thead>
                <tr>
                  <th className={TH}>Passo</th><th className={TH}>Campo</th><th className={TH}>Tag no XML</th>
                  <th className={TH}>Valor lido</th><th className={TH}>Situação</th>
                </tr>
              </thead>
              <tbody>
                {resultado.linhas.map((l, i) => {
                  const [texto, cor] = SITUACAO[l.situacao];
                  return (
                    <tr key={i}>
                      <td className={TD}>{NOME_PASSO[l.passo]}</td>
                      <td className={TD}>
                        {l.campo}
                        {l.obs && <small className="block text-[11px] leading-snug text-[var(--text-dim)]">{l.obs}</small>}
                      </td>
                      <td className={`${TD} whitespace-nowrap font-mono text-[11.5px] text-[var(--text-dim)]`}>{l.tag}</td>
                      <td className={`${TD} whitespace-nowrap font-mono text-xs font-semibold`}>{l.valor || '—'}</td>
                      <td className={TD}>
                        <span className="inline-flex h-5 items-center gap-1.5 whitespace-nowrap text-[10.5px] font-semibold uppercase tracking-[.04em]"
                          style={{ color: `color-mix(in oklch, ${cor} 62%, var(--text))` }}>
                          <span aria-hidden className="h-1.5 w-1.5 flex-none rounded-full" style={{ background: cor }} />{texto}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p className="m-0 text-[11.5px] leading-snug text-[var(--text-dim)]">
            Valores da nota (vProd, vBC, vICMS e totais) não são guardados: a natureza só leva códigos e a origem das alíquotas.
          </p>
        </Stack>
      ) : (
        <Stack gap={3} align="center"
          className={`rounded-[10px] border-[1.5px] border-dashed px-4 py-6 text-center ${
            sobre ? 'border-[var(--accent)] bg-[color-mix(in_oklch,var(--accent)_6%,var(--bg-2))]' : 'border-[var(--border)] bg-[var(--bg-2)]'}`}
          onDragOver={(e) => { e.preventDefault(); setSobre(true); }} onDragLeave={() => setSobre(false)} onDrop={soltar}>
          <p className="m-0 max-w-[52ch] text-[12.5px] leading-normal text-[var(--text-dim)]">
            <b className="text-[var(--text)]">Arraste o XML de uma NF-e ou NFC-e já emitida</b> com a mesma operação. O sistema lê
            natOp, finalidade, CFOP e os grupos ICMS, IPI, PIS, COFINS e IBSCBS do primeiro item.
          </p>
          <input ref={input} type="file" accept=".xml,text/xml,application/xml" className="sr-only" id="nat-xml"
            aria-label="Arquivo XML da nota de referência"
            onChange={(e) => { const f = e.target.files?.[0]; if (f) onArquivo(f); e.target.value = ''; }} />
          <Button size="sm" onClick={() => input.current?.click()}>
            <FileUp className="mr-1.5 h-4 w-4" />Escolher arquivo XML
          </Button>
          {erro && <p role="alert" className="m-0 text-[12.5px] font-semibold" style={{ color: 'color-mix(in oklch, var(--neg) 62%, var(--text))' }}>{erro}</p>}
        </Stack>
      ))}
    </Stack>
  );
}
