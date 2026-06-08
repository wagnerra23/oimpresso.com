// TemplatePicker — Dialog pra selecionar template HSM/LOCAL e enviar via composer.
// US-WA-013 (UI Templates) plug em ConversationThread composer.
// Janela 24h Meta fechada + driver=meta_cloud só permite envio via template — este picker
// é o único caminho. Z-API/Baileys também aceita template (driver expande {{1}}, {{2}}
// e manda freeform — ver ZapiDriver::sendTemplate).
//
// ADRs: 0096 (Whatsapp module), 0039 (Cockpit pattern)

import { useMemo, useState } from 'react';
import { CheckCircle2, FileText, Send } from 'lucide-react';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Card } from '@/Components/ui/card';

import {
  expandTemplateBody,
  extractPlaceholders,
  type ReadyTemplate,
} from './helpers';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  templates: ReadyTemplate[];
  /**
   * Sender — recebe payload pronto pra POST atendimento.inbox.send com kind=template.
   * Retorna boolean: true se enviou com sucesso (fecha dialog), false mantém aberto.
   */
  onSend: (payload: {
    template_name: string;
    template_locale: string;
    template_params: string[];
  }) => void;
  sending: boolean;
}

export default function TemplatePicker({
  open, onOpenChange, templates, onSend, sending,
}: Props) {
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [params, setParams] = useState<Record<string, string>>({});

  const selected = useMemo(
    () => templates.find((t) => t.id === selectedId) ?? null,
    [templates, selectedId],
  );

  const placeholders = useMemo(
    () => (selected ? extractPlaceholders(selected.body) : []),
    [selected],
  );

  const allFilled = placeholders.every((p) => (params[p] ?? '').trim() !== '');

  function handleSelect(t: ReadyTemplate) {
    setSelectedId(t.id);
    setParams({});
  }

  function handleSend() {
    if (!selected || !allFilled || sending) return;
    // template_params é array ordenado (Meta-style {{1}}, {{2}}…); para placeholders
    // nomeados ({{nome}}), backend WhatsappTemplate::expandBody aceita ambos.
    const orderedParams = placeholders.map((p) => params[p] ?? '');
    onSend({
      template_name: selected.name,
      template_locale: selected.language,
      template_params: orderedParams,
    });
  }

  function reset() {
    setSelectedId(null);
    setParams({});
  }

  const preview = selected ? expandTemplateBody(selected.body, params) : '';

  return (
    <Dialog
      open={open}
      onOpenChange={(o) => {
        if (!o) reset();
        onOpenChange(o);
      }}
    >
      <DialogContent className="sm:max-w-2xl max-h-[85vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <FileText size={18} aria-hidden />
            Enviar template
          </DialogTitle>
          <DialogDescription>
            Selecione um template aprovado e preencha os campos. Templates LOCAL (Z-API/Baileys)
            sempre disponíveis; Meta HSM precisa estar APPROVED.
          </DialogDescription>
        </DialogHeader>

        <div className="flex-1 overflow-y-auto space-y-3 -mx-1 px-1">
          {templates.length === 0 ? (
            <Card className="p-6 text-center text-sm text-muted-foreground">
              Nenhum template ready cadastrado.{' '}
              <a href={route('whatsapp.templates.index')} className="underline text-primary">
                Cadastre em Templates →
              </a>
            </Card>
          ) : (
            <>
              {/* Lista compacta */}
              <div className="space-y-1.5">
                {templates.map((t) => (
                  <button
                    key={t.id}
                    type="button"
                    onClick={() => handleSelect(t)}
                    className={`w-full text-left rounded-md border px-3 py-2 transition hover:bg-accent ${
                      selectedId === t.id ? 'border-primary bg-accent' : 'border-border'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-2 min-w-0">
                      <div className="flex items-center gap-2 min-w-0">
                        <span className="font-mono text-sm font-medium truncate">{t.name}</span>
                        <Badge variant="outline" className="shrink-0">{t.language}</Badge>
                        <Badge variant="outline" className="shrink-0">{t.category}</Badge>
                        {t.status === 'APPROVED' && (
                          <Badge variant="outline" className="shrink-0 border-emerald-500 text-emerald-700 dark:text-emerald-400 dark:border-emerald-700 gap-0.5">
                            <CheckCircle2 size={11} aria-hidden />
                            Meta
                          </Badge>
                        )}
                        {t.status === 'LOCAL' && (
                          <Badge variant="outline" className="shrink-0 border-purple-500 text-purple-700 dark:text-purple-400 dark:border-purple-700">
                            LOCAL
                          </Badge>
                        )}
                      </div>
                    </div>
                    <div className="text-xs text-muted-foreground mt-1 line-clamp-2">
                      {t.body}
                    </div>
                  </button>
                ))}
              </div>

              {/* Form placeholders */}
              {selected && placeholders.length > 0 && (
                <Card className="p-3 space-y-2">
                  <div className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                    Variáveis
                  </div>
                  {placeholders.map((p) => (
                    <div key={p}>
                      <Label htmlFor={`tpl-param-${p}`}>{`{{${p}}}`}</Label>
                      <Input
                        id={`tpl-param-${p}`}
                        value={params[p] ?? ''}
                        onChange={(e) => setParams((prev) => ({ ...prev, [p]: e.target.value }))}
                        placeholder={`valor para ${p}`}
                      />
                    </div>
                  ))}
                </Card>
              )}

              {/* Preview */}
              {selected && (
                <Card className="p-3 bg-muted/30">
                  <div className="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1.5">
                    Pré-visualização
                  </div>
                  <div className="text-sm whitespace-pre-wrap break-words">
                    {preview || <em className="text-muted-foreground">(preencha as variáveis)</em>}
                  </div>
                </Card>
              )}
            </>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            onClick={handleSend}
            disabled={!selected || !allFilled || sending}
            className="gap-1.5"
          >
            <Send size={14} aria-hidden />
            {sending ? 'Enviando…' : 'Enviar template'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
