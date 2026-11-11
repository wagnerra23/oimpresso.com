// Laudo biopsicossocial integrado (US-FISIO-060; Lei 15.176/2025 e LBI art. 2º, §1º).
// Protótipo: prototipo-ui/cowork/Wagner/prototipos/fisio/LaudoBiopsicossocial.dc.html.
// Quatro seções, uma por inciso, cada uma com autor e assinatura. Seção registrada não muda:
// salvar de novo abre uma correção. O software sugere a seção III com o que mede, mas não conclui:
// o enquadramento é da perícia (ADR 0437 §5). Contrato: memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md.
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FileText } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import { Textarea } from '@/Components/ui/textarea';

type Assinatura = 'sistema' | 'externa';
const CONSELHOS = ['CRM', 'CREFITO', 'CRP', 'CRESS'] as const;

export interface SecaoLaudo {
  item: string;
  inciso: string;
  titulo: string;
  conselho_sugerido: string;
  registrada: { autor: string | null; conselho: string | null; registro: string | null; externa: boolean; registrado_em: string; texto: string | null } | null;
  rascunho: { texto: string | null; assinatura: Assinatura; autor_nome: string | null; conselho: string | null; registro_conselho: string | null } | null;
}
export interface LaudoBps { id: number; criado_em: string; secoes: SecaoLaudo[] }
interface Sugestao { texto: string; avisos: string[] }

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const opcoes = { preserveScroll: true, only: ['laudos_bps'] };

function Editor({ laudoId, secao, onFechar }: { laudoId: number; secao: SecaoLaudo; onFechar: () => void }) {
  const r = secao.rascunho;
  const [dados, setDados] = useState({
    texto: r?.texto ?? '',
    assinatura: (r?.assinatura ?? (secao.item === 'atividades' ? 'sistema' : 'externa')) as Assinatura,
    autor_nome: r?.autor_nome ?? '',
    conselho: r?.conselho ?? secao.conselho_sugerido,
    registro_conselho: r?.registro_conselho ?? '',
  });
  const [erros, setErros] = useState<Record<string, string>>({});
  const [sugestao, setSugestao] = useState<Sugestao | null>(null);
  const [ocupado, setOcupado] = useState(false);
  const url = `/fisio/laudos-bps/${laudoId}/secoes/${secao.item}`;
  const externa = dados.assinatura === 'externa';
  const set = (campo: keyof typeof dados, valor: string) => setDados((d) => ({ ...d, [campo]: valor }));

  const salvar = (depois?: () => void) => {
    setOcupado(true);
    router.put(url, dados, { ...opcoes, onError: setErros, onSuccess: () => { setErros({}); if (depois) depois(); else onFechar(); }, onFinish: () => setOcupado(false) });
  };
  const registrar = () => salvar(() => router.post(`${url}/registrar`, {}, { ...opcoes, onError: setErros, onSuccess: onFechar }));
  const verSugestao = async () => {
    const res = await fetch(`/fisio/laudos-bps/${laudoId}/sugestao-fisio`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } });
    if (res.ok) setSugestao(await res.json());
  };

  return (
    <div className="flex flex-col gap-3 rounded-lg border border-primary p-3" data-contract="fisio-laudo-bps-editor">
      <div className="flex flex-wrap gap-2" role="group" aria-label="Quem assina">
        <Button type="button" size="sm" variant={externa ? 'outline' : 'default'} aria-pressed={!externa} onClick={() => set('assinatura', 'sistema')}>Eu assino (CREFITO do meu perfil)</Button>
        <Button type="button" size="sm" variant={externa ? 'default' : 'outline'} aria-pressed={externa} onClick={() => set('assinatura', 'externa')}>Profissional de fora da clínica</Button>
      </div>
      {externa && (
        <div className="grid gap-2 sm:grid-cols-3">
          <div className="flex flex-col gap-1.5">
            <Label htmlFor={`bps-autor-${secao.item}`}>Nome de quem escreveu</Label>
            <Input id={`bps-autor-${secao.item}`} value={dados.autor_nome} onChange={(e) => set('autor_nome', e.target.value)} />
          </div>
          <div className="flex flex-col gap-1.5">
            <span className="text-sm font-medium" id={`bps-conselho-${secao.item}`}>Conselho</span>
            <Segmented aria-labelledby={`bps-conselho-${secao.item}`} value={dados.conselho} onValueChange={(v) => v && set('conselho', v)} options={CONSELHOS.map((c) => ({ value: c, label: c }))} />
          </div>
          <div className="flex flex-col gap-1.5">
            <Label htmlFor={`bps-registro-${secao.item}`}>Número do registro</Label>
            <Input id={`bps-registro-${secao.item}`} value={dados.registro_conselho} onChange={(e) => set('registro_conselho', e.target.value)} />
          </div>
        </div>
      )}
      {secao.item === 'atividades' && (
        sugestao ? (
          <div className="flex flex-col gap-1.5 rounded-md bg-muted p-3 text-sm" data-contract="fisio-laudo-bps-sugestao">
            <span className="text-xs font-semibold">Sugestão do Fisio — revise antes de usar</span>
            <p className="whitespace-pre-line">{sugestao.texto}</p>
            {sugestao.avisos.map((a) => <p key={a} className="text-xs text-muted-foreground">{a}</p>)}
            <Button type="button" size="sm" variant="outline" className="self-start" onClick={() => set('texto', dados.texto ? `${dados.texto}\n\n${sugestao.texto}` : sugestao.texto)}>Usar no texto</Button>
          </div>
        ) : (
          <Button type="button" size="sm" variant="outline" className="self-start" onClick={verSugestao}>Ver o que o Fisio mediu</Button>
        )
      )}
      <div className="flex flex-col gap-1.5">
        <Label htmlFor={`bps-texto-${secao.item}`}>Texto da seção</Label>
        <Textarea id={`bps-texto-${secao.item}`} rows={6} value={dados.texto} onChange={(e) => set('texto', e.target.value)} />
      </div>
      {Object.values(erros).map((m) => <p key={m} className="text-xs text-destructive">{m}</p>)}
      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" variant="outline" disabled={ocupado} onClick={() => salvar()}>Salvar rascunho</Button>
        <Button type="button" disabled={ocupado} onClick={registrar}>{externa ? 'Registrar transcrição' : 'Registrar e assinar'}</Button>
        <Button type="button" variant="ghost" onClick={onFechar}>Fechar</Button>
      </div>
      <p className="text-xs text-muted-foreground">
        {externa
          ? 'O PDF sai com espaço para a assinatura dele, à mão ou por certificado digital. Seu usuário fica registrado como quem transcreveu.'
          : 'Ao registrar, seu nome, CREFITO, data e hora são gravados pelo sistema. Depois disso a seção não muda.'}
      </p>
    </div>
  );
}

function Linha({ laudoId, secao, podeEditar, aberta, onAbrir, onFechar }: { laudoId: number; secao: SecaoLaudo; podeEditar: boolean; aberta: boolean; onAbrir: () => void; onFechar: () => void }) {
  const reg = secao.registrada;
  const acao = secao.rascunho ? 'Continuar rascunho' : reg ? 'Corrigir' : 'Escrever';
  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border p-3">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="w-6 font-mono text-xs text-muted-foreground">{secao.inciso}</span>
        <span className="flex-1">
          {secao.titulo}{' '}
          <span className="text-muted-foreground">
            · {reg ? `${reg.autor ?? ''} · ${reg.conselho ?? ''} ${reg.registro ?? ''}${reg.externa ? ' · externa' : ''}` : `costuma ser ${secao.conselho_sugerido}`}
          </span>
        </span>
        {secao.rascunho && <Badge variant="outline">Rascunho</Badge>}
        <Badge variant={reg ? 'secondary' : 'outline'}>{reg ? `Registrada ${reg.registrado_em}` : 'Em branco no PDF'}</Badge>
        {podeEditar && !aberta && <Button size="sm" variant="ghost" onClick={onAbrir}>{acao}</Button>}
      </div>
      {aberta && <Editor laudoId={laudoId} secao={secao} onFechar={onFechar} />}
    </li>
  );
}

export default function LaudoBiopsicossocial({ pacienteId, laudos, podeEditar }: { pacienteId: number; laudos: LaudoBps[]; podeEditar: boolean }) {
  const [aberta, setAberta] = useState<string | null>(null);
  const laudo = laudos[0];
  const novo = () => router.post(`/fisio/pacientes/${pacienteId}/laudos-bps`, {}, opcoes);

  return (
    <div className="flex flex-col gap-3" data-contract="fisio-laudo-bps">
      <div className="flex flex-wrap items-center gap-2">
        {laudo && <span className="text-xs text-muted-foreground">Laudo nº {laudo.id} · aberto em {laudo.criado_em} · Lei 15.176/2025 e LBI art. 2º, §1º</span>}
        <div className="ml-auto flex gap-2">
          {laudo && (
            <Button variant="outline" size="sm" asChild>
              <a href={`/fisio/laudos-bps/${laudo.id}/pdf`} data-contract="fisio-laudo-bps-pdf"><FileText className="size-4" aria-hidden="true" /> Baixar PDF</a>
            </Button>
          )}
          {podeEditar && <Button variant="outline" size="sm" onClick={novo}>Novo laudo</Button>}
        </div>
      </div>
      <p className="text-xs text-muted-foreground">Cada seção é escrita e assinada por quem avaliou. Seção registrada não muda: corrigir abre uma versão nova. O laudo descreve; o enquadramento é da perícia.</p>
      {laudo ? (
        <ol className="flex flex-col gap-2">
          {laudo.secoes.map((s) => (
            <Linha key={s.item} laudoId={laudo.id} secao={s} podeEditar={podeEditar} aberta={aberta === s.item} onAbrir={() => setAberta(s.item)} onFechar={() => setAberta(null)} />
          ))}
        </ol>
      ) : (
        <p className="text-sm text-muted-foreground">Nenhum laudo biopsicossocial para este paciente.</p>
      )}
    </div>
  );
}
