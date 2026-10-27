// Ciência do espelho — US-PONTO-020. Aviso do último mês FECHADO pela empresa, dentro do
// "Meu espelho": o colaborador declara ciência uma vez; depois a tela mostra quando foi e se o
// espelho ainda é o mesmo que ele viu. Só o fechado aceita ciência (ADR 0413: não reabre).
import { Deferred } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';

export interface CienciaEstado {
  mes: string;
  competencia_fechada: boolean;
  ciencia: { ciente_em: string; confere: boolean } | null;
}

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function mesExtenso(mes: string): string {
  const [a, m] = mes.split('-').map(Number);
  return new Date(a ?? 2000, (m ?? 1) - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
}

function Aviso({ estado }: { estado: CienciaEstado | null | undefined }) {
  const [dada, setDada] = useState<CienciaEstado['ciencia']>(estado?.ciencia ?? null);
  const [enviando, setEnviando] = useState(false);
  if (!estado) return null;

  const darCiencia = async () => {
    setEnviando(true);
    try {
      const r = await fetch('/ponto/mobile/espelho/ciencia', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        body: JSON.stringify({ mes: estado.mes }),
      });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) {
        toast.error(j.mensagem ?? 'Não foi possível registrar a ciência.');
        return;
      }
      setDada(j.ciencia);
      toast.success(`Ciência do espelho de ${mesExtenso(estado.mes)} registrada.`);
    } finally {
      setEnviando(false);
    }
  };

  if (dada) {
    const quando = new Date(dada.ciente_em).toLocaleDateString('pt-BR');
    return (
      <p data-contract="repp-espelho-ciencia" className="rounded-md border p-3 text-sm text-muted-foreground">
        Você deu ciência do espelho de {mesExtenso(estado.mes)} em {quando}.
        {!dada.confere && (
          <span className="block text-warning">O espelho desse mês mudou depois da sua ciência. Fale com o RH.</span>
        )}
      </p>
    );
  }

  return (
    <Inline data-contract="repp-espelho-ciencia" justify="between" align="center" gap={3} wrap className="rounded-md border p-3 text-sm">
      <span>O espelho de {mesExtenso(estado.mes)} foi fechado pela empresa. Confira e dê sua ciência.</span>
      <Button type="button" size="sm" className="min-h-11" disabled={enviando} onClick={darCiencia}>
        Dou ciência
      </Button>
    </Inline>
  );
}

export default function CienciaEspelho({ estado }: { estado?: CienciaEstado | null }) {
  return (
    <Deferred data="ciencia_espelho" fallback={null}>
      <Aviso estado={estado} />
    </Deferred>
  );
}
