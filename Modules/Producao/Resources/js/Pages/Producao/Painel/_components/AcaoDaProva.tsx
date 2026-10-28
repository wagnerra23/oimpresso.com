// Ação por linha da fila de arte do Painel (UC-PCART-07). Âncora: `Painel` de
// prototipo-ui/cowork/Wagner/producao-cv-page.jsx ("Enviar nova prova" no ajuste, "Reenviar link" no
// aguardando, com o campo do link do arquivo novo e a linha de resultado).
// O banco só guarda o hash do token: reenviar gera link novo e o anterior deixa de valer.
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { json } from '../../Quadro/_lib/json';

interface Resposta { link: string; whatsapp: { enviado: boolean; motivo: string | null } | null }

export default function AcaoDaProva({ osId, ajuste }: { osId: number; ajuste: boolean }) {
  const [abrir, setAbrir] = useState(false);
  const [arquivo, setArquivo] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [feito, setFeito] = useState<Resposta | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const idCampo = `prova-${osId}`;

  async function enviar(corpo: { arquivo?: string }) {
    setEnviando(true); setErro(null);
    try {
      setFeito(await json<Resposta>('POST', `/producao/ordens/${osId}/aprovacao-arte/reenviar`, corpo));
      setAbrir(false);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Não foi possível enviar.');
    } finally {
      setEnviando(false);
    }
  }

  function submeter(e: FormEvent) {
    e.preventDefault();
    void enviar({ arquivo: arquivo.trim() });
  }

  const rotulo = feito ? (ajuste ? 'Nova prova enviada' : 'Reenviado agora') : (ajuste ? 'Enviar nova prova' : 'Reenviar link');

  return (
    <>
      <Button size="sm" variant="outline" disabled={enviando || feito !== null}
        onClick={() => (ajuste ? setAbrir(true) : void enviar({}))}>{rotulo}</Button>
      {abrir && (
        <form className="flex basis-full flex-wrap items-end gap-2" onSubmit={submeter}>
          <label htmlFor={idCampo} className="flex min-w-0 flex-[1_1_320px] flex-col gap-1 text-xs text-[var(--text-mute)]">Arquivo da nova prova (link)
            <Input id={idCampo} type="url" required autoFocus value={arquivo} onChange={(e) => setArquivo(e.target.value)}
              placeholder="https://drive.exemplo.com/prova-v2.pdf" />
          </label>
          <Button size="sm" type="submit" disabled={enviando}>Enviar ao cliente</Button>
          <Button size="sm" type="button" variant="ghost" onClick={() => setAbrir(false)}>Cancelar</Button>
        </form>
      )}
      {erro && <p className="basis-full text-xs text-[var(--color-destructive,var(--neg))]" role="alert">{erro}</p>}
      {feito && (feito.whatsapp?.enviado
        ? <p className="basis-full text-xs" role="status">Link novo enviado ao cliente pelo WhatsApp. O link anterior deixou de valer.</p>
        : <p className="basis-full break-all text-xs" role="status">Mande este link ao cliente você mesmo: <code>{feito.link}</code></p>)}
    </>
  );
}
