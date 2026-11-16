// "Imagem e folheto" do formulário de produto (UC-PCAD-29 · UC-PEDIT-29), dentro de "Dados do produto"
// como no protótipo (produto-blade-forms.jsx: "Arraste a imagem (1:1, até 5 MB) + folheto em PDF").
//
// Novo: os arquivos vão junto com o produto ao salvar (o useForm vira multipart só quando há arquivo).
// Editar: cada arquivo grava na hora, pela rota POST /products/{id}/midia. O resto do Editar segue
// indo como JSON: mandar o Editar inteiro como multipart muda como as listas vazias chegam ao servidor.

import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Inline, Stack } from '@/Components/layout';
import type { CamposProduto, DefinirCampo } from './tipos';
import { ACEITA_FOLHETO, LIMITE_BYTES, enviarMidia } from './envioMidia';
import type { Folheto } from './envioMidia';

function previa(arquivo: File | null | undefined): string | null {
  if (!arquivo || typeof URL.createObjectURL !== 'function') return null;
  return URL.createObjectURL(arquivo);
}

export function CampoMidia({
  modo,
  data,
  definir,
  produtoId,
  imagemGravada,
  folhetoGravado,
}: {
  modo: 'novo' | 'editar';
  data: CamposProduto;
  definir: DefinirCampo;
  produtoId?: number;
  imagemGravada?: string | null;
  folhetoGravado?: Folheto | null;
}) {
  const [imagem, setImagem] = useState<string | null>(imagemGravada ?? null);
  const [folheto, setFolheto] = useState<Folheto | null>(folhetoGravado ?? null);
  const [aviso, setAviso] = useState<{ tipo: 'ok' | 'erro'; texto: string } | null>(null);
  const [gravando, setGravando] = useState(false);

  const escolher = async (campo: 'image' | 'product_brochure', arquivo: File | undefined) => {
    if (!arquivo) return;
    if (arquivo.size > LIMITE_BYTES) {
      setAviso({ tipo: 'erro', texto: 'O arquivo passa do limite de 5 MB.' });
      return;
    }
    setAviso(null);
    if (modo === 'novo') {
      definir(campo, arquivo);
      if (campo === 'image') setImagem(previa(arquivo));
      return;
    }
    if (!produtoId) return;
    setGravando(true);
    const r = await enviarMidia(produtoId, campo, arquivo);
    setGravando(false);
    if ('erro' in r) {
      setAviso({ tipo: 'erro', texto: r.erro });
      return;
    }
    if (campo === 'image') setImagem(r.imagem);
    else setFolheto(r.folheto);
    setAviso({ tipo: 'ok', texto: campo === 'image' ? 'Imagem gravada.' : 'Folheto gravado.' });
  };

  const novoFolheto = modo === 'novo' ? (data.product_brochure ?? null) : null;

  return (
    <Stack gap={2}>
      <Label>Imagem e folheto</Label>
      <Inline gap={3} align="start">
        {imagem ? (
          <img src={imagem} alt="Imagem do produto" className="h-16 w-16 rounded border border-border object-cover" />
        ) : (
          <div className="h-16 w-16 rounded border border-dashed border-border" aria-hidden />
        )}
        <Stack gap={2}>
          <Inline gap={2} wrap>
            <Button asChild variant="outline" size="sm" disabled={gravando}>
              <label htmlFor="produto-imagem">{imagem ? 'Trocar imagem' : 'Escolher imagem'}</label>
            </Button>
            <input
              id="produto-imagem"
              type="file"
              accept="image/*"
              className="sr-only"
              onChange={(e) => escolher('image', e.target.files?.[0])}
            />
            <Button asChild variant="outline" size="sm" disabled={gravando}>
              <label htmlFor="produto-folheto">{folheto || novoFolheto ? 'Trocar folheto' : 'Escolher folheto'}</label>
            </Button>
            <input
              id="produto-folheto"
              type="file"
              accept={ACEITA_FOLHETO}
              className="sr-only"
              onChange={(e) => escolher('product_brochure', e.target.files?.[0])}
            />
          </Inline>
          <p className="text-[12px] text-muted-foreground">
            {novoFolheto
              ? `Folheto: ${novoFolheto.name}`
              : folheto
                ? (
                  <a href={folheto.url} target="_blank" rel="noreferrer" className="underline">
                    Folheto: {folheto.nome}
                  </a>
                )
                : 'Imagem quadrada (1:1) e folheto em PDF, até 5 MB cada.'}
          </p>
          {gravando && <p className="text-[12px] text-muted-foreground">Gravando…</p>}
          {aviso && (
            <p role={aviso.tipo === 'erro' ? 'alert' : 'status'} className={aviso.tipo === 'erro' ? 'text-[12px] text-destructive' : 'text-[12px] text-muted-foreground'}>
              {aviso.texto}
            </p>
          )}
        </Stack>
      </Inline>
    </Stack>
  );
}
