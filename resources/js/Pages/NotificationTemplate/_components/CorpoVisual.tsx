// Modo Visual do corpo do e-mail (UC-NOT-21 / UC-NOT-09). O HTML do modelo é dado de terceiro:
// ele só é renderizado dentro de um iframe `sandbox="allow-same-origin"` SEM `allow-scripts`,
// então <script>, on* e javascript: não executam — a página só lê e escreve o DOM dele.
import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export interface CorpoVisualApi {
  /** Insere a tag na última posição do cursor no corpo (ou no fim, se nunca houve cursor). */
  inserir: (tag: string) => void;
  comando: (cmd: 'bold' | 'italic' | 'insertUnorderedList' | 'createLink', valor?: string) => void;
}
interface Props { html: string; onHtml: (html: string) => void; onFoco?: () => void }

export const CorpoVisual = forwardRef<CorpoVisualApi, Props>(function CorpoVisual({ html, onHtml, onFoco }, ref) {
  const frame = useRef<HTMLIFrameElement>(null);
  const ultimo = useRef<string | null>(null);
  const intervalo = useRef<Range | null>(null);
  const corpo = () => frame.current?.contentDocument?.body ?? null;
  const emitir = () => { const b = corpo(); if (b) { ultimo.current = b.innerHTML; onHtml(b.innerHTML); } };

  useEffect(() => {
    const doc = frame.current?.contentDocument;
    if (!doc?.body) return;
    doc.body.contentEditable = 'true';
    doc.body.setAttribute('aria-label', 'Corpo do e-mail');
    const guardar = () => {
      const s = doc.getSelection();
      if (s && s.rangeCount && doc.body.contains(s.anchorNode)) intervalo.current = s.getRangeAt(0).cloneRange();
    };
    const foco = () => onFoco?.();
    doc.body.addEventListener('input', emitir);
    ['keyup', 'mouseup', 'blur'].forEach((e) => doc.body.addEventListener(e, guardar));
    doc.body.addEventListener('focus', foco);
    return () => {
      doc.body.removeEventListener('input', emitir);
      ['keyup', 'mouseup', 'blur'].forEach((e) => doc.body.removeEventListener(e, guardar));
      doc.body.removeEventListener('focus', foco);
    };
  // emitir/onFoco leem refs e a última prop — religar a cada render trocaria o listener sem ganho.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Só reescreve o DOM quando o valor veio de fora (restaurar, descartar, troca de modelo);
  // reescrever a cada tecla jogaria o cursor para o começo.
  useEffect(() => {
    const b = corpo();
    if (b && html !== ultimo.current) { b.innerHTML = html; ultimo.current = html; intervalo.current = null; }
  }, [html]);

  useImperativeHandle(ref, () => ({
    inserir: (tag) => {
      const doc = frame.current?.contentDocument; const b = corpo();
      if (!doc || !b) return;
      const r = intervalo.current ?? (() => { const x = doc.createRange(); x.selectNodeContents(b); x.collapse(false); return x; })();
      r.deleteContents();
      const no = doc.createTextNode(tag);
      r.insertNode(no); r.setStartAfter(no); r.collapse(true);
      intervalo.current = r;
      const s = doc.getSelection(); s?.removeAllRanges(); s?.addRange(r);
      emitir();
    },
    comando: (cmd, valor) => {
      const doc = frame.current?.contentDocument;
      if (!doc) return;
      corpo()?.focus();
      if (intervalo.current) { const s = doc.getSelection(); s?.removeAllRanges(); s?.addRange(intervalo.current); }
      doc.execCommand?.(cmd, false, valor);
      emitir();
    },
  }));

  return <iframe ref={frame} title="Editor visual do corpo do e-mail" sandbox="allow-same-origin"
    className="h-56 w-full rounded-sm border bg-background" data-contract="editor-visual" />;
});
