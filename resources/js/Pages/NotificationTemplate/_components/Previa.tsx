// Prévia por canal com as tags resolvidas pela AMOSTRA (UC-NOT-09). O HTML do e-mail é dado de
// terceiro: vai para um iframe `sandbox=""` (sem scripts, sem formulário, sem navegar a página);
// SMS e WhatsApp saem como texto, nunca como HTML.
import { Stack } from '@/Components/layout';
import { resolverAmostra } from './editor-modelo';

interface Props { canal: 'email' | 'sms' | 'whatsapp'; assunto: string; corpo: string }

export function Previa({ canal, assunto, corpo }: Props) {
  const texto = resolverAmostra(corpo);
  return <Stack gap={2} data-contract="previa">
    <div>
      <h3 className="text-sm font-medium">Prévia</h3>
      <p className="text-xs text-muted-foreground">Tags resolvidas com dados de exemplo.</p>
    </div>
    {canal === 'email' ? <Stack gap={1}>
      <p className="text-sm font-semibold" data-contract="previa-assunto">{resolverAmostra(assunto) || '(sem assunto)'}</p>
      <iframe title="Prévia do e-mail" sandbox="" srcDoc={texto || '<p>(vazio)</p>'}
        className="h-72 w-full rounded-sm border bg-background" />
    </Stack> : <p data-contract="previa-texto" className="whitespace-pre-wrap rounded-sm border bg-muted p-3 text-sm">
      {texto || '(vazio)'}</p>}
  </Stack>;
}
