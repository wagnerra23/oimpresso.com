// Exames e receitas que o paciente traz (US-FISIO-063). Protótipo: ExamesReceitas.dc.html.
// Casos: memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md (UC-FER-*).
// Documento do prontuário não se apaga: "Tirar da lista" pede o motivo e o servidor grava quem
// tirou. O arquivo é servido por rota que confere a empresa; a tela nunca recebe caminho de disco.
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FileText, ImageIcon, Paperclip, Sparkles } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import MedicamentosEmUso, { type Medicamento } from './MedicamentosEmUso';
import ConferirTranscricao, { TranscricaoConferida, type Transcricao } from './ConferirTranscricao';

export interface DocumentoFicha {
  id: number;
  tipo: string;
  data_documento: string | null;
  observacao: string | null;
  origem: 'clinica' | 'portal';
  status: 'prontuario' | 'pendente';
  formato: 'foto' | 'pdf' | null;
  url: string;
  anexado_por: string | null;
  anexado_em: string | null;
  transcrevivel: boolean;
  transcricao: Transcricao | null;
}

const ACEITOS = 'image/jpeg,image/png,image/webp,application/pdf';
const data = (iso: string | null) => (iso ? new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR') : 'sem data');

function Anexar({ pacienteId, tipos }: { pacienteId: number; tipos: Record<string, string> }) {
  const [tipo, setTipo] = useState('receita');
  const [dataDoc, setDataDoc] = useState('');
  const [obs, setObs] = useState('');
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);
  const [chave, setChave] = useState(0);

  const enviar = () => {
    if (!arquivo) {
      setErros({ arquivo: 'Escolha uma foto ou um PDF.' });
      return;
    }
    setEnviando(true);
    router.post(
      `/fisio/pacientes/${pacienteId}/documentos`,
      { arquivo, tipo, data_documento: dataDoc || null, observacao: obs || null },
      {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { setArquivo(null); setDataDoc(''); setObs(''); setErros({}); setChave((k) => k + 1); },
        onError: (e) => setErros(e as Record<string, string>),
        onFinish: () => setEnviando(false),
      },
    );
  };

  return (
    <fieldset className="flex flex-col gap-3 rounded-lg border border-border p-3" data-contract="fisio-documentos-anexar">
      <legend className="px-1 text-sm font-semibold">Anexar documento</legend>
      <div className="flex flex-col gap-1.5">
        <span id="doc-tipo" className="text-sm">Tipo</span>
        <Segmented aria-labelledby="doc-tipo" value={tipo} onValueChange={(v) => v && setTipo(v)}
          options={Object.entries(tipos).map(([value, label]) => ({ value, label }))} />
        {erros.tipo && <p className="text-xs text-destructive">{erros.tipo}</p>}
      </div>
      <div className="grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)]">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="doc-data">Data do documento</Label>
          <Input id="doc-data" type="date" value={dataDoc} onChange={(e) => setDataDoc(e.target.value)} />
          {erros.data_documento && <p className="text-xs text-destructive">{erros.data_documento}</p>}
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor="doc-obs">Observação (opcional)</Label>
          <Input id="doc-obs" maxLength={500} value={obs} onChange={(e) => setObs(e.target.value)} />
        </div>
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="doc-arquivo">Foto ou PDF</Label>
        <Input key={chave} id="doc-arquivo" type="file" accept={ACEITOS} onChange={(e) => setArquivo(e.target.files?.[0] ?? null)} />
        <p className="text-xs text-muted-foreground">JPG, PNG, WebP ou PDF, até 10 MB. Fica no prontuário e só a sua clínica vê.</p>
        {erros.arquivo && <p className="text-xs text-destructive">{erros.arquivo}</p>}
      </div>
      <Button type="button" className="self-start" disabled={enviando} onClick={enviar}>
        <Paperclip className="size-4" aria-hidden="true" /> Anexar
      </Button>
    </fieldset>
  );
}

function Linha({ doc, tipos, podeEditar, transcricaoIa }: { doc: DocumentoFicha; tipos: Record<string, string>; podeEditar: boolean; transcricaoIa: boolean }) {
  const [tirando, setTirando] = useState(false);
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [lendo, setLendo] = useState(false);
  const Icone = doc.formato === 'pdf' ? FileText : ImageIcon;
  const receita = doc.tipo === 'receita';
  const t = doc.transcricao;

  const sugerir = () => {
    setLendo(true);
    router.post(`/fisio/documentos/${doc.id}/transcricao`, {}, {
      preserveScroll: true,
      onError: (e) => setErro((e as Record<string, string>).transcricao ?? 'Não foi possível ler o documento.'),
      onFinish: () => setLendo(false),
    });
  };

  const tirar = () => {
    router.post(`/fisio/documentos/${doc.id}/tirar`, { motivo }, {
      preserveScroll: true,
      onError: (e) => setErro((e as Record<string, string>).motivo ?? 'Não foi possível tirar da lista.'),
    });
  };

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border p-3" data-contract="fisio-documento">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <Badge variant="secondary">{tipos[doc.tipo] ?? doc.tipo}</Badge>
        {doc.status === 'pendente' && <Badge variant="outline">Enviado pelo paciente · aguardando conferência</Badge>}
        <span className="text-muted-foreground">{data(doc.data_documento)}</span>
        {doc.anexado_por && <span className="text-muted-foreground">· anexado por {doc.anexado_por}</span>}
        <span className="ml-auto flex gap-2">
          <Button size="sm" variant="outline" asChild>
            <a href={doc.url} target="_blank" rel="noopener noreferrer"><Icone className="size-4" aria-hidden="true" /> Abrir</a>
          </Button>
          {podeEditar && transcricaoIa && doc.transcrevivel && !t && (
            <Button size="sm" variant="outline" disabled={lendo} onClick={sugerir}>
              <Sparkles className="size-4" aria-hidden="true" /> {lendo ? 'Lendo a foto…' : 'Sugerir campos pela IA'}
            </Button>
          )}
          {podeEditar && doc.status === 'prontuario' && !tirando && (
            <Button size="sm" variant="ghost" onClick={() => setTirando(true)}>Tirar da lista</Button>
          )}
        </span>
      </div>
      {doc.observacao && <p className="text-sm">{doc.observacao}</p>}
      {t?.status === 'pendente' && (podeEditar
        ? <ConferirTranscricao t={t} receita={receita} />
        : <p className="text-xs text-muted-foreground">Transcrição sugerida aguardando conferência.</p>)}
      {t?.status === 'conferida' && <TranscricaoConferida t={t} receita={receita} />}
      {!tirando && erro && <p className="text-xs text-destructive">{erro}</p>}
      {tirando && (
        <div className="flex flex-col gap-2">
          <Label htmlFor={`doc-motivo-${doc.id}`}>Por que sai da lista?</Label>
          <Input id={`doc-motivo-${doc.id}`} maxLength={255} value={motivo} onChange={(e) => setMotivo(e.target.value)} />
          {erro && <p className="text-xs text-destructive">{erro}</p>}
          <p className="text-xs text-muted-foreground">O documento continua guardado no prontuário. Fica registrado quem tirou e quando.</p>
          <div className="flex gap-2">
            <Button size="sm" variant="destructive" onClick={tirar}>Tirar da lista</Button>
            <Button size="sm" variant="ghost" onClick={() => { setTirando(false); setErro(null); }}>Cancelar</Button>
          </div>
        </div>
      )}
    </li>
  );
}

function ChaveTranscricao({ ligada }: { ligada: boolean }) {
  const [confirmando, setConfirmando] = useState(false);
  const trocar = () => router.post('/fisio/documentos/transcricao-ia', { ligado: !ligada }, { preserveScroll: true, onFinish: () => setConfirmando(false) });
  return (
    <div className="flex flex-col gap-2 rounded-md bg-muted px-3 py-2 text-xs" data-contract="fisio-transcricao-chave">
      <div className="flex flex-wrap items-center gap-2">
        <span>Transcrição pela IA: <strong>{ligada ? 'ligada' : 'desligada'}</strong> nesta clínica.</span>
        <Button size="sm" variant="outline" className="ml-auto" onClick={() => (ligada ? trocar() : setConfirmando(true))}>{ligada ? 'Desligar' : 'Ligar'}</Button>
      </div>
      {confirmando && (
        <div className="flex flex-col gap-2">
          <p>A foto vai para o provedor de IA configurado, que não usa o conteúdo para treino. A IA só copia o que está escrito; cada campo é conferido por alguém da clínica antes de ir ao prontuário.</p>
          <div className="flex gap-2">
            <Button size="sm" onClick={trocar}>Ligar</Button>
            <Button size="sm" variant="ghost" onClick={() => setConfirmando(false)}>Cancelar</Button>
          </div>
        </div>
      )}
    </div>
  );
}

export default function ExamesReceitas({ pacienteId, tipos, documentos, medicamentos, podeEditar, transcricaoIa = false }: {
  pacienteId: number;
  tipos: Record<string, string>;
  documentos: DocumentoFicha[];
  medicamentos: Medicamento[];
  podeEditar: boolean;
  transcricaoIa?: boolean;
}) {
  return (
    <div className="flex flex-col gap-4" data-contract="fisio-exames-receitas">
      <p className="text-sm text-muted-foreground">Documentos que o paciente trouxe. Fazem parte do prontuário: tirar da lista não apaga o arquivo.</p>
      {podeEditar && <ChaveTranscricao ligada={transcricaoIa} />}
      {podeEditar && <Anexar pacienteId={pacienteId} tipos={tipos} />}
      {documentos.length === 0 ? (
        <p className="text-sm text-muted-foreground">Nenhum exame ou receita anexado.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {documentos.map((d) => <Linha key={d.id} doc={d} tipos={tipos} podeEditar={podeEditar} transcricaoIa={transcricaoIa} />)}
        </ul>
      )}
      <MedicamentosEmUso pacienteId={pacienteId} medicamentos={medicamentos} podeEditar={podeEditar} />
    </div>
  );
}
