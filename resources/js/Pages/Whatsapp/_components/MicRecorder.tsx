import { useCallback, useEffect, useRef, useState } from 'react';
import { Mic, Check, X, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';

/**
 * Hotfix B3 (US-WA-072 extended, 2026-05-12) — gravador de áudio inline
 * pro composer do Inbox. Usa MediaRecorder API browser nativa.
 *
 * Estados:
 *   idle      → botão Mic aparece pronto pra começar
 *   recording → ícone vermelho pulsando + timer + botões Cancelar/Enviar
 *   uploading → spinner enquanto POST multipart está em flight
 *
 * Limite: 5 min (300_000ms) — auto-stop com toast.
 *
 * MIME preferido: `audio/ogg;codecs=opus` (compatível com WhatsApp +
 * Whisper API). Fallback `audio/webm` em browsers que não suportam OGG
 * (Safari sem MSE webm-opus suporta webm, OGG só em Firefox/Chrome).
 *
 * Tier 0 (ADR 0142): caller (`ConversationThread`) DESABILITA o botão
 * quando `composerKind === 'note'` — nota interna não tem mídia outbound.
 * Esta componente NÃO renderiza nada relacionado a nota — escopo separado.
 *
 * Permission: chamada `getUserMedia({audio:true})` dispara prompt browser.
 * Se usuário negar → mostra mensagem inline e volta pro estado idle.
 */
interface Props {
  /** Disabled quando contato bloqueado, modo nota, ou já tem upload em flight. */
  disabled: boolean;
  /**
   * Callback chamado quando atendente confirma envio. Recebe o blob OGG/WebM
   * gerado pelo MediaRecorder. Parent é responsável por construir FormData
   * + POST `route('atendimento.inbox.send_media', conv.id)`.
   * Retornar Promise — quando resolve/reject, componente sai do uploading.
   */
  onSend: (blob: Blob, durationS: number) => Promise<void>;
}

const MAX_DURATION_MS = 5 * 60 * 1000; // 5min — limite WhatsApp PTT

export default function MicRecorder({ disabled, onSend }: Props) {
  const [state, setState] = useState<'idle' | 'recording' | 'uploading'>('idle');
  const [elapsedMs, setElapsedMs] = useState(0);
  const [error, setError] = useState<string | null>(null);

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const chunksRef = useRef<Blob[]>([]);
  const streamRef = useRef<MediaStream | null>(null);
  const startTimeRef = useRef<number>(0);
  const tickIntervalRef = useRef<number | null>(null);
  const autoStopTimeoutRef = useRef<number | null>(null);
  // Indica se foi cancel intencional (não dispara onSend no `stop` event).
  const cancelRequestedRef = useRef(false);

  // Cleanup robusto — libera stream + intervals quando unmount ou state idle.
  const cleanup = useCallback(() => {
    if (tickIntervalRef.current !== null) {
      window.clearInterval(tickIntervalRef.current);
      tickIntervalRef.current = null;
    }
    if (autoStopTimeoutRef.current !== null) {
      window.clearTimeout(autoStopTimeoutRef.current);
      autoStopTimeoutRef.current = null;
    }
    if (streamRef.current) {
      // Para todos tracks → libera o microfone (LED apaga).
      streamRef.current.getTracks().forEach((t) => t.stop());
      streamRef.current = null;
    }
    mediaRecorderRef.current = null;
    chunksRef.current = [];
  }, []);

  useEffect(() => () => cleanup(), [cleanup]);

  function pickMimeType(): string {
    if (typeof MediaRecorder === 'undefined') return '';
    const candidates = [
      'audio/ogg;codecs=opus',
      'audio/webm;codecs=opus',
      'audio/webm',
    ];
    for (const m of candidates) {
      if (MediaRecorder.isTypeSupported(m)) return m;
    }
    return '';
  }

  async function startRecording() {
    setError(null);
    if (state !== 'idle') return;

    if (!navigator.mediaDevices?.getUserMedia) {
      setError('Microfone não suportado neste navegador.');
      return;
    }

    let stream: MediaStream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e: unknown) {
      const msg = e instanceof DOMException && e.name === 'NotAllowedError'
        ? 'Permissão de microfone negada.'
        : 'Falha ao acessar microfone.';
      setError(msg);
      return;
    }

    const mimeType = pickMimeType();
    let rec: MediaRecorder;
    try {
      rec = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
    } catch (e) {
      stream.getTracks().forEach((t) => t.stop());
      setError('MediaRecorder indisponível no navegador.');
      return;
    }

    chunksRef.current = [];
    cancelRequestedRef.current = false;
    streamRef.current = stream;
    mediaRecorderRef.current = rec;
    startTimeRef.current = Date.now();

    rec.addEventListener('dataavailable', (ev) => {
      if (ev.data && ev.data.size > 0) chunksRef.current.push(ev.data);
    });

    rec.addEventListener('stop', () => {
      const duration = Math.round((Date.now() - startTimeRef.current) / 1000);
      const localStream = streamRef.current;
      const wasCancelled = cancelRequestedRef.current;

      // Libera tracks ANTES do upload pra LED apagar imediato
      if (localStream) {
        localStream.getTracks().forEach((t) => t.stop());
      }
      streamRef.current = null;

      if (wasCancelled) {
        cleanup();
        setState('idle');
        setElapsedMs(0);
        return;
      }

      // Monta blob com o type efetivo do recorder (pode ter caído pra webm)
      const effectiveType = rec.mimeType || 'audio/ogg';
      const blob = new Blob(chunksRef.current, { type: effectiveType });

      if (blob.size === 0) {
        cleanup();
        setState('idle');
        setElapsedMs(0);
        setError('Áudio vazio — tente de novo.');
        return;
      }

      setState('uploading');
      onSend(blob, duration)
        .catch((err) => {
          setError(err instanceof Error ? err.message : 'Falha no envio.');
        })
        .finally(() => {
          cleanup();
          setState('idle');
          setElapsedMs(0);
        });
    });

    rec.start(250); // dispara `dataavailable` a cada 250ms
    setState('recording');
    setElapsedMs(0);

    // Tick visual de timer (segundos)
    tickIntervalRef.current = window.setInterval(() => {
      setElapsedMs(Date.now() - startTimeRef.current);
    }, 250);

    // Auto-stop em MAX_DURATION_MS — limite PTT WhatsApp
    autoStopTimeoutRef.current = window.setTimeout(() => {
      setError('Limite de 5 minutos atingido — enviando…');
      stopAndSend();
    }, MAX_DURATION_MS);
  }

  function stopAndSend() {
    const rec = mediaRecorderRef.current;
    if (!rec) return;
    cancelRequestedRef.current = false;
    if (rec.state !== 'inactive') rec.stop();
  }

  function cancelRecording() {
    const rec = mediaRecorderRef.current;
    cancelRequestedRef.current = true;
    if (rec && rec.state !== 'inactive') {
      rec.stop();
    } else {
      cleanup();
      setState('idle');
      setElapsedMs(0);
    }
  }

  if (state === 'recording') {
    const totalSec = Math.floor(elapsedMs / 1000);
    const mm = String(Math.floor(totalSec / 60)).padStart(2, '0');
    const ss = String(totalSec % 60).padStart(2, '0');
    return (
      <div
        className="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-red-50 dark:bg-red-950/30 border border-red-300 dark:border-red-700"
        data-testid="composer-mic-recording"
      >
        <span
          className="w-2 h-2 rounded-full bg-red-500 animate-pulse"
          aria-hidden
        />
        <span className="text-[11px] font-mono tabular-nums text-red-900 dark:text-red-200" aria-live="polite">
          {mm}:{ss}
        </span>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-7 w-7 p-0 text-red-700 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/40"
          onClick={cancelRecording}
          title="Cancelar gravação (descarta áudio)"
          aria-label="Cancelar gravação"
          data-testid="composer-mic-cancel"
        >
          <X size={14} aria-hidden />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-7 w-7 p-0 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/40"
          onClick={stopAndSend}
          title="Parar e enviar"
          aria-label="Enviar áudio"
          data-testid="composer-mic-send"
        >
          <Check size={14} aria-hidden />
        </Button>
      </div>
    );
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={startRecording}
        disabled={disabled || state === 'uploading'}
        className="h-8 px-2"
        title={disabled ? 'Indisponível neste modo' : 'Gravar áudio (max 5min)'}
        aria-label="Gravar áudio"
        data-testid="composer-mic-start"
      >
        {state === 'uploading'
          ? <Loader2 size={14} className="animate-spin" aria-hidden />
          : <Mic size={14} aria-hidden />}
      </Button>
      {error && (
        <span className="text-[10px] text-destructive max-w-[160px] truncate" title={error}>
          {error}
        </span>
      )}
    </>
  );
}
