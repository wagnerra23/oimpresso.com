// Estrela de favorito no card do exercício (UC-FBI-08). Favorito é do profissional na empresa da
// sessão; favoritar um exercício global não muda o exercício. Recarrega só a lista (`only`).
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Star } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';

export default function FavoritoBotao({ exercicioId, nome, favorito }: { exercicioId: number; nome: string; favorito: boolean }) {
  const [enviando, setEnviando] = useState(false);
  const url = `/fisio/biblioteca/exercicios/${exercicioId}/favorito`;
  const opcoes = {
    preserveScroll: true,
    preserveState: true,
    only: ['exercicios'],
    onStart: () => setEnviando(true),
    onFinish: () => setEnviando(false),
  };

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      disabled={enviando}
      aria-pressed={favorito}
      aria-label={favorito ? `Tirar ${nome} dos favoritos` : `Favoritar ${nome}`}
      onClick={() => (favorito ? router.delete(url, opcoes) : router.post(url, {}, opcoes))}
    >
      <Star className={cn('size-4', favorito && 'fill-current text-primary')} aria-hidden="true" />
    </Button>
  );
}
