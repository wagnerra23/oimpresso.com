// Enviar foto de exame, receita ou atestado pelo portal (US-FISIO-063, parte 4; atestado em UC-FER-21). Protótipo: PacienteEnviarExame.dc.html.
// Casos: Portal.casos.md (UC-FPP-62..64) e exames-e-receitas.casos.md (UC-FER-17..20).
// Antes do primeiro envio o paciente aceita o consentimento próprio desta finalidade (separado do
// teleatendimento, revogável nas Configurações). O que ele envia chega à clínica como pendente de
// conferência; não entra no prontuário sozinho. O portal nunca devolve o arquivo nem transcrição.
import { ArrowLeft, Camera } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { enviar } from './portal';

export interface EnvioDocumentos {
  consentido: boolean;
  version: string | null;
  tipos: Record<string, string>;
  enviados: { tipo: string; enviado_em: string; situacao: 'aguardando' | 'no_prontuario' | 'nao_aceito' }[];
}

const SITUACAO: Record<string, string> = {
  aguardando: 'aguardando a clínica conferir', no_prontuario: 'guardado no seu prontuário', nao_aceito: 'a clínica não aceitou; fale com ela',
};
// Palavras do paciente para os tipos do servidor.
const ROTULO: Record<string, string> = {
  receita: 'Receita', atestado: 'Atestado médico', exame_imagem: 'Exame de imagem', exame_laboratorial: 'Exame de sangue', laudo_medico: 'Laudo médico', outro: 'Outro',
};
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export function TelaEnviarDocumento({ token, clinica, envio, onVoltar }: { token: string; clinica: string | null; envio: EnvioDocumentos; onVoltar: () => void }) {
  const [consentido, setConsentido] = useState(envio.consentido);
  const [aceito, setAceito] = useState(false);
  const [tipo, setTipo] = useState('receita');
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [recado, setRecado] = useState('');
  const [enviados, setEnviados] = useState(envio.enviados);
  const [estado, setEstado] = useState<'livre' | 'enviando'>('livre');
  const [msg, setMsg] = useState<{ ok: boolean; texto: string } | null>(null);
  const nome = clinica ?? 'a clínica';

  const autorizar = async () => {
    const r = await enviar('POST', `/fisio/p/${token}/consentimentos`, { purpose: 'documentos', aceito: true });
    if (r.status === 200 || r.status === 201) setConsentido(true);
    else setMsg({ ok: false, texto: 'Não deu para salvar a autorização. Confira a internet e tente de novo.' });
  };

  const mandar = async () => {
    if (!arquivo) return setMsg({ ok: false, texto: 'Tire uma foto ou escolha um arquivo.' });
    setEstado('enviando');
    setMsg(null);
    const corpo = new FormData();
    corpo.append('arquivo', arquivo);
    corpo.append('tipo', tipo);
    if (recado.trim()) corpo.append('observacao', recado.trim());
    let status = 0;
    let json: Record<string, unknown> = {};
    try {
      const res = await fetch(`/fisio/p/${token}/documentos`, {
        method: 'POST', credentials: 'same-origin', body: corpo,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      });
      status = res.status;
      json = await res.json().catch(() => ({}));
    } catch {
      status = 0;
    }
    setEstado('livre');
    if (status === 201) {
      setEnviados([{ tipo, enviado_em: new Date().toISOString(), situacao: 'aguardando' }, ...enviados]);
      setArquivo(null);
      setRecado('');
      return setMsg({ ok: true, texto: `Enviado. ${nome} vai conferir e guardar no seu prontuário.` });
    }
    const erros = (json.errors ?? {}) as Record<string, string[]>;
    const texto = erros.arquivo?.[0]
      ?? (status === 429 ? 'Você já mandou muitos arquivos hoje. Tente amanhã ou leve na consulta.' : 'Não deu para enviar agora. Confira a internet e tente de novo.');
    setMsg({ ok: false, texto });
  };

  return (
    <div className="flex flex-1 flex-col gap-5" data-contract="fisio-portal-enviar-documento">
      <div className="flex items-center gap-2">
        <Button type="button" variant="ghost" size="icon" onClick={onVoltar} aria-label="Voltar"><ArrowLeft aria-hidden="true" /></Button>
        <h1 className="text-2xl font-semibold leading-tight">Enviar exame, receita ou atestado</h1>
      </div>

      {!consentido ? (
        <section className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4" aria-labelledby="doc-aut" data-contract="fisio-portal-documentos-consentimento">
          <h2 id="doc-aut" className="text-base font-semibold">Antes do primeiro envio</h2>
          <p className="text-sm">
            A foto vai para {nome}, que confere e guarda no seu prontuário. Ela fica guardada com segurança e só a clínica vê.
            Você pode retirar esta autorização nas Configurações; o que já foi guardado continua no prontuário.
          </p>
          <div className="flex items-start gap-3">
            <Checkbox id="doc-aceito" className="mt-0.5 size-6" checked={aceito} onCheckedChange={(v) => setAceito(v === true)} />
            <Label htmlFor="doc-aceito" className="font-normal leading-snug">Autorizo enviar fotos de exames, receitas e atestados para {nome}.</Label>
          </div>
          <Button type="button" className="h-12" disabled={!aceito} onClick={() => void autorizar()}>Continuar</Button>
        </section>
      ) : (
        <section className="flex flex-col gap-4" aria-label="Envio">
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">O que é</legend>
            <div className="grid grid-cols-2 gap-2">
              {Object.keys(envio.tipos).map((t) => (
                <Button key={t} type="button" variant={tipo === t ? 'default' : 'outline'} className="h-12" aria-pressed={tipo === t} onClick={() => setTipo(t)}>
                  {ROTULO[t] ?? envio.tipos[t]}
                </Button>
              ))}
            </div>
          </fieldset>
          <Label htmlFor="doc-arquivo" className="flex h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-primary/40 bg-card text-center font-normal">
            <Camera className="size-7" aria-hidden="true" />
            <span className="font-medium">{arquivo ? arquivo.name : 'Tirar foto ou escolher arquivo'}</span>
            <span className="text-sm text-muted-foreground">foto ou PDF, até 10 MB</span>
          </Label>
          <input id="doc-arquivo" type="file" className="sr-only" accept="image/jpeg,image/png,image/webp,application/pdf" capture="environment"
            onChange={(e) => setArquivo(e.target.files?.[0] ?? null)} />
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="doc-recado">Recado para a clínica (opcional)</Label>
            <Input id="doc-recado" className="h-11" maxLength={500} placeholder="Ex.: ressonância da coluna" value={recado} onChange={(e) => setRecado(e.target.value)} />
          </div>
          <Button type="button" className="h-12" disabled={estado === 'enviando'} onClick={() => void mandar()}>
            {estado === 'enviando' ? 'Enviando…' : 'Enviar para a clínica'}
          </Button>
        </section>
      )}

      {msg && <p role={msg.ok ? 'status' : 'alert'} className={msg.ok ? 'text-sm' : 'text-sm text-destructive'}>{msg.texto}</p>}

      {enviados.length > 0 && (
        <section className="flex flex-col gap-1" aria-labelledby="doc-enviados">
          <h2 id="doc-enviados" className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Seus envios</h2>
          <ul className="flex flex-col gap-1 text-sm">
            {enviados.map((e, i) => (
              <li key={i}>{ROTULO[e.tipo] ?? e.tipo} · {new Date(e.enviado_em).toLocaleDateString('pt-BR')} · {SITUACAO[e.situacao]}</li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
