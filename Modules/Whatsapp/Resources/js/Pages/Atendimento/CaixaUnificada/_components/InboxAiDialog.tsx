// InboxAiDialog — "Resumir" e "Perguntar" da thread (PR-9 · inbox-ai.jsx).
//
// Chama os endpoints finos do InboxAiController (laravel/ai server-side,
// PII redigida, dry_run gateia custo). Resultado é SEMPRE revisado pelo
// humano — nada é enviado ao cliente automaticamente.

import { useState } from 'react';
import { Loader2, Sparkles } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Inline, Stack } from '@/Components/layout';

export type InboxAiMode = 'summarize' | 'ask';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: InboxAiMode;
  conversationId: number;
}

function csrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function InboxAiDialog({ open, onOpenChange, mode, conversationId }: Props) {
  const [question, setQuestion] = useState('');
  const [result, setResult] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  function run() {
    if (loading) return;
    if (mode === 'ask' && !question.trim()) return;
    setLoading(true);
    setError(null);
    setResult(null);
    const routeName = mode === 'summarize' ? 'atendimento.inbox.ai.summarize' : 'atendimento.inbox.ai.ask';
    fetch(route(routeName, conversationId), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(mode === 'ask' ? { question: question.trim() } : {}),
    })
      .then(async r => {
        const data = await r.json();
        if (!r.ok) throw new Error(data.error ?? 'Falha na IA.');
        setResult(data.text ?? '');
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => setLoading(false));
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(o) => { if (!o) { setResult(null); setError(null); setQuestion(''); } onOpenChange(o); }}
    >
      <DialogContent className="sm:max-w-md" data-testid="caixa-unif-ai-dialog">
        <DialogHeader>
          <DialogTitle className="inline-flex items-center gap-2">
            <Sparkles size={16} aria-hidden />
            {mode === 'summarize' ? 'Resumir conversa' : 'Perguntar sobre a conversa'}
          </DialogTitle>
          <DialogDescription>
            A IA responde só com o que está no transcript (dados sensíveis são
            redigidos antes). Revise antes de usar com o cliente.
          </DialogDescription>
        </DialogHeader>

        <Stack gap={2}>
          {mode === 'ask' && (
            <Inline gap={2} align="center">
              <Input
                value={question}
                onChange={e => setQuestion(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') run(); }}
                placeholder="Ex.: o cliente já confirmou o endereço de entrega?"
                data-testid="caixa-unif-ai-question"
              />
            </Inline>
          )}

          <Button
            type="button"
            onClick={run}
            disabled={loading || (mode === 'ask' && !question.trim())}
            className="gap-1.5"
            data-testid="caixa-unif-ai-run"
          >
            {loading
              ? (<><Loader2 size={14} className="animate-spin" aria-hidden /> Consultando…</>)
              : (mode === 'summarize' ? 'Gerar resumo' : 'Perguntar')}
          </Button>

          {error && (
            <p className="text-[11.5px] text-destructive" role="alert">{error}</p>
          )}
          {result !== null && (
            <div
              className="border rounded-md p-3 bg-muted/20 text-[12.5px] whitespace-pre-wrap break-words max-h-[45vh] overflow-y-auto"
              data-testid="caixa-unif-ai-result"
            >
              {result}
            </div>
          )}
        </Stack>
      </DialogContent>
    </Dialog>
  );
}
