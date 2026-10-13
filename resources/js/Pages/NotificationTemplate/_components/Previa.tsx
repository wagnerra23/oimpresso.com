// Prévia por canal com as tags resolvidas pela AMOSTRA (UC-NOT-09). O HTML do e-mail passa pelo
// MESMO HtmlSanitizer do envio (POST /notification-templates/previa) e só então vai para um
// iframe `sandbox=""` — a tela mostra o que o destinatário receberia, e o sandbox fica como
// segunda camada. SMS e WhatsApp saem como texto, nunca como HTML.
import { useEffect, useState } from 'react';
import { Stack } from '@/Components/layout';
import { resolverAmostra } from './editor-modelo';

interface Props { canal: 'email' | 'sms' | 'whatsapp'; assunto: string; corpo: string }
type Estado = { html: string } | { carregando: true } | { erro: true };

const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
/** Espera a digitação parar antes de pedir a limpeza ao servidor. */
export const ESPERA_PREVIA_MS = 300;

export function Previa({ canal, assunto, corpo }: Props) {
  const texto = resolverAmostra(corpo);
  const [estado, setEstado] = useState<Estado>({ carregando: true });
  useEffect(() => {
    if (canal !== 'email') return;
    let vivo = true;
    setEstado({ carregando: true });
    const t = setTimeout(() => {
      fetch('/notification-templates/previa', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify({ html: texto }),
      }).then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
        .then((j: { html: string }) => { if (vivo) setEstado({ html: j.html }); })
        .catch(() => { if (vivo) setEstado({ erro: true }); });
    }, ESPERA_PREVIA_MS);
    return () => { vivo = false; clearTimeout(t); };
  }, [canal, texto]);
  return <Stack gap={2} data-contract="previa">
    <div>
      <h3 className="text-sm font-medium">Prévia</h3>
      <p className="text-xs text-muted-foreground">Tags resolvidas com dados de exemplo.</p>
    </div>
    {canal === 'email' ? <Stack gap={1}>
      <p className="text-sm font-semibold" data-contract="previa-assunto">{resolverAmostra(assunto) || '(sem assunto)'}</p>
      {'html' in estado
        ? <iframe title="Prévia do e-mail" sandbox="" srcDoc={estado.html || '<p>(vazio)</p>'}
            className="h-72 w-full rounded-sm border bg-background" />
        : <p role="status" className="rounded-sm border p-3 text-sm text-muted-foreground">
            {'erro' in estado ? 'Não foi possível gerar a prévia. Tente de novo.' : 'Atualizando prévia…'}</p>}
    </Stack> : <p data-contract="previa-texto" className="whitespace-pre-wrap rounded-sm border bg-muted p-3 text-sm">
      {texto || '(vazio)'}</p>}
  </Stack>;
}
