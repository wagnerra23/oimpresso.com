// "Imagem e folheto" do formulário de produto (UC-PCAD-29 · UC-PEDIT-29), dentro de "Dados do produto"
// como no protótipo (produto-blade-forms.jsx: "Arraste a imagem (1:1, até 5 MB) + folheto em PDF").
//
// Novo: os arquivos vão junto com o produto ao salvar (o useForm vira multipart só quando há arquivo).
// Editar: cada arquivo grava na hora, pela rota POST /products/{id}/midia. O resto do Editar segue
// indo como JSON: mandar o Editar inteiro como multipart muda como as listas vazias chegam ao servidor.

import '../../../../../css/cowork-produto-bundle.css';
import { useState } from 'react';
import type { DragEvent } from 'react';
import { Label } from '@/Components/ui/label';
import { Stack } from '@/Components/layout';
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

  // Arrastar: imagem vai para a imagem do produto; qualquer outro arquivo, para o folheto (o folheto
  // também aceita imagem, mas quem arrasta uma imagem quer a foto do produto).
  const [sobre, setSobre] = useState(false);
  const soltar = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setSobre(false);
    const arquivo = e.dataTransfer.files?.[0];
    if (!arquivo || gravando) return;
    escolher(arquivo.type.startsWith('image/') ? 'image' : 'product_brochure', arquivo);
  };

  return (
    <Stack gap={2}>
      <Label>Imagem e folheto</Label>
      {/* Forma do protótipo: uma área de arrastar (.pb-img do bundle do Produto). Clicar no texto abre
          a escolha do arquivo; os dois <input type="file"> seguem sendo o caminho do teclado. */}
      <div
        data-testid="area-midia"
        role="region"
        aria-label="Área para soltar a imagem e o folheto"
        className="pb-img"
        style={{ aspectRatio: 'auto', minHeight: 78, outline: sobre ? '2px dashed var(--accent)' : undefined }}
        onDragOver={(e) => {
          e.preventDefault();
          setSobre(true);
        }}
        onDragLeave={() => setSobre(false)}
        onDrop={soltar}
      >
        <div>
          {imagem && (
            <img src={imagem} alt="Imagem do produto" className="mx-auto mb-1 h-12 w-12 rounded border border-border object-cover" />
          )}
          <label htmlFor="produto-imagem" className="cursor-pointer">
            {imagem ? 'Arraste outra imagem ou clique para trocar' : 'Arraste a imagem (1:1, até 5 MB)'}
          </label>
          <br />
          <small>
            <label htmlFor="produto-folheto" className="cursor-pointer">
              {folheto || novoFolheto ? '+ trocar o folheto' : '+ folheto em PDF'}
            </label>
          </small>
        </div>
      </div>
      <input
        id="produto-imagem"
        type="file"
        accept="image/*"
        className="sr-only"
        disabled={gravando}
        onChange={(e) => escolher('image', e.target.files?.[0])}
      />
      <input
        id="produto-folheto"
        type="file"
        accept={ACEITA_FOLHETO}
        className="sr-only"
        disabled={gravando}
        onChange={(e) => escolher('product_brochure', e.target.files?.[0])}
      />
      {(novoFolheto || folheto) && (
        <p className="text-[12px] text-muted-foreground">
          {novoFolheto ? (
            `Folheto: ${novoFolheto.name}`
          ) : (
            <a href={folheto!.url} target="_blank" rel="noreferrer" className="underline">
              Folheto: {folheto!.nome}
            </a>
          )}
        </p>
      )}
      {gravando && <p className="text-[12px] text-muted-foreground">Gravando…</p>}
      {aviso && (
        <p role={aviso.tipo === 'erro' ? 'alert' : 'status'} className={aviso.tipo === 'erro' ? 'text-[12px] text-destructive' : 'text-[12px] text-muted-foreground'}>
          {aviso.texto}
        </p>
      )}
    </Stack>
  );
}
