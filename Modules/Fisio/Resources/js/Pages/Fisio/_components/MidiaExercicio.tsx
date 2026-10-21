// Foto ou vídeo curto do exercício PRÓPRIO da clínica (UC-FBI-05..07).
// A validação (tipo, tamanho, declaração) é do servidor; aqui só mostrar, escolher e enviar.
// A mídia é servida por rota que confere a empresa — a tela nunca recebe caminho de disco.
// LGPD (ADR 0437): mídia do EXERCÍCIO, nunca do paciente — o envio exige a declaração.
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export interface MidiaResumo {
  tipo: 'foto' | 'video';
  url: string;
}

const ACEITOS = 'image/jpeg,image/png,image/webp,video/mp4';

/** A mídia em si: foto ou vídeo, sem som e sem autoplay. */
export function MidiaPreview({ midia, nome, className }: { midia: MidiaResumo; nome: string; className?: string }) {
  const classe = `w-full rounded-md border bg-muted object-cover ${className ?? ''}`;
  if (midia.tipo === 'video') {
    return (
      <video
        className={classe}
        src={midia.url}
        controls
        muted
        playsInline
        preload="metadata"
        aria-label={`Vídeo do exercício ${nome}`}
        data-contract="fisio-midia-video"
      />
    );
  }
  return <img className={classe} src={midia.url} alt={`Foto do exercício ${nome}`} loading="lazy" data-contract="fisio-midia-foto" />;
}

/** Campo do formulário de edição: mostra a mídia atual, troca e remove. */
export function MidiaCampo({ exercicioId, nome, midia }: { exercicioId: number | null; nome: string; midia: MidiaResumo | null }) {
  const [atual, setAtual] = useState<MidiaResumo | null>(midia);
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [declaro, setDeclaro] = useState(false);
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);

  if (exercicioId === null) {
    return <p className="text-xs text-muted-foreground">Salve o exercício para anexar uma foto ou um vídeo.</p>;
  }
  const rota = `/fisio/biblioteca/exercicios/${exercicioId}/midia`;

  const enviar = () => {
    if (!arquivo) {
      setErros({ midia: 'Escolha uma foto ou um vídeo.' });
      return;
    }
    setEnviando(true);
    router.post(
      rota,
      { midia: arquivo, declaro_sem_paciente: declaro ? '1' : '' },
      {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setAtual({ tipo: arquivo.type.startsWith('video/') ? 'video' : 'foto', url: `${rota}?v=${Date.now()}` });
          setArquivo(null);
          setDeclaro(false);
          setErros({});
        },
        onError: (e) => setErros(e as Record<string, string>),
        onFinish: () => setEnviando(false),
      },
    );
  };

  const remover = () => {
    setEnviando(true);
    router.delete(rota, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setAtual(null),
      onFinish: () => setEnviando(false),
    });
  };

  return (
    <div className="flex flex-col gap-2" data-contract="fisio-midia-campo">
      <Label htmlFor="fx-midia">Foto ou vídeo do exercício</Label>
      {atual && (
        <div className="flex flex-col gap-2">
          <MidiaPreview midia={atual} nome={nome} className="max-h-56" />
          <Button type="button" variant="outline" size="sm" className="self-start" disabled={enviando} onClick={remover}>
            <Trash2 className="size-4" aria-hidden="true" /> Remover mídia
          </Button>
        </div>
      )}
      <Input
        id="fx-midia"
        type="file"
        accept={ACEITOS}
        onChange={(e) => setArquivo(e.target.files?.[0] ?? null)}
      />
      <p className="text-xs text-muted-foreground">Foto JPG, PNG ou WebP (até 10 MB) ou vídeo MP4 curto (até 50 MB). Fica visível só para a sua clínica.</p>
      {erros.midia && <p className="text-xs text-destructive">{erros.midia}</p>}
      <div className="flex items-start gap-2">
        <Checkbox id="fx-midia-declaro" checked={declaro} onCheckedChange={(v) => setDeclaro(v === true)} />
        <Label htmlFor="fx-midia-declaro" className="text-sm font-normal leading-snug">
          A foto ou o vídeo mostra só o exercício e não mostra paciente.
        </Label>
      </div>
      {erros.declaro_sem_paciente && <p className="text-xs text-destructive">{erros.declaro_sem_paciente}</p>}
      <Button type="button" variant="outline" size="sm" className="self-start" disabled={enviando} onClick={enviar}>
        <ImagePlus className="size-4" aria-hidden="true" /> {atual ? 'Trocar mídia' : 'Enviar mídia'}
      </Button>
    </div>
  );
}
