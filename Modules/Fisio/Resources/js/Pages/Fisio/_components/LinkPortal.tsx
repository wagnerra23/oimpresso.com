// Link do portal do paciente, na ficha (US-FISIO-007a · UC-FPS-33..35).
// Gerar devolve o link e o PIN UMA vez: o servidor guarda só os hashes, então perdeu = gerar
// outro (o anterior morre). Revogar derruba o link vivo. Ambos exigem fisio.editar.
import { useState } from 'react';
import { Button } from '@/Components/ui/button';

interface Gerado { url: string; pin: string; expires_at: string }

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const data = (iso: string) => new Date(iso).toLocaleDateString('pt-BR');

export default function LinkPortal({ pacienteId, podeGerar }: { pacienteId: number; podeGerar: boolean }) {
  const [gerado, setGerado] = useState<Gerado | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const chamar = async (metodo: 'POST' | 'DELETE') => {
    setEnviando(true);
    setMsg(null);
    const res = await fetch(`/fisio/pacientes/${pacienteId}/portal`, {
      method: metodo,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = (await res.json().catch(() => ({}))) as Partial<Gerado> & { message?: string };
    setEnviando(false);
    if (metodo === 'POST' && res.status === 201) return setGerado(json as Gerado);
    if (metodo === 'DELETE' && res.ok) { setGerado(null); return setMsg('Link revogado. O paciente não entra mais com ele.'); }
    setMsg(json.message ?? 'Não foi possível concluir. Tente de novo.');
  };

  if (!podeGerar) return null;
  return (
    <div className="flex flex-col gap-2 border-t border-border pt-3" data-contract="fisio-portal-link">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="text-muted-foreground">Portal do paciente</span>
        <Button type="button" size="sm" variant="outline" className="ml-auto" disabled={enviando} onClick={() => chamar('POST')}>Gerar link do portal</Button>
        <Button type="button" size="sm" variant="ghost" disabled={enviando} onClick={() => chamar('DELETE')}>Revogar</Button>
      </div>
      {gerado && (
        <div className="flex flex-col gap-1 rounded-lg border border-border p-3 text-sm">
          <p className="break-all">{gerado.url}</p>
          <p>PIN: <strong className="font-mono">{gerado.pin}</strong> · vale até {data(gerado.expires_at)}</p>
          <p className="text-xs text-muted-foreground">Anote ou envie agora: o PIN não aparece de novo. Mande o PIN separado do link.</p>
        </div>
      )}
      {msg && <p className="text-xs text-muted-foreground" role="status">{msg}</p>}
    </div>
  );
}