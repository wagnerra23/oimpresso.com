/**
 * Pages/ComunicacaoVisual/Instalacao — o instalador no celular, na obra (US-COMVIS-007 · UC-PCV-25).
 *
 * Âncora de design: a tela "Instalação" do celular em prototipo-ui/cowork/Wagner/producao-cv-campo.jsx
 * (OS · dia e hora, cliente, endereço com "Abrir no mapa", checklist com foto antes, foto depois e assinatura).
 * Envia as duas fotos, colhe a assinatura do cliente (com o nome e o aceite de guardá-la) e conclui a
 * instalação. Concluir só fica ativo quando o comprovante tem os três itens.
 *
 * A tela não decide o que pode: manda tudo pela API do comprovante
 * (POST /comunicacao-visual/api/instalacoes/{id}/fotos|assinatura|concluir) e, quando ela recusa
 * (instalação concluída, arquivo que não é imagem, assinatura já colhida, item faltando), mostra a
 * mensagem dela no alerta "Não enviado".
 *
 * Persona: instalador da gráfica, no celular, em pé na frente da fachada. Tokens DS v4, sem cor crua.
 *
 * @see resources/js/Pages/ComunicacaoVisual/Instalacao.charter.md
 * @see resources/js/Pages/ComunicacaoVisual/Instalacao.casos.md
 * @see Modules/ComunicacaoVisual/Http/Controllers/InstalacaoCampoController.php
 */
import { useState, type ChangeEvent, type ReactNode } from 'react';
import { Head, router } from '@inertiajs/react';
import { Camera, Check, MapPin } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { QuadroAssinatura } from '@/Components/shared/QuadroAssinatura';
import { Inline, Stack } from '@/Components/layout';

export interface InstalacaoCampo {
  id: number;
  os_numero: string | null;
  cliente: string | null;
  endereco: string | null;
  inicio: string | null; // ISO com o offset do servidor
  fim: string | null;
  equipe: string[];
  veiculo: string | null;
  status: string;
}

export interface Comprovante {
  foto_antes: boolean;
  foto_depois: boolean;
  assinatura: { assinado_por: string | null; em: string } | null;
  status: string;
  pode_concluir: boolean;
}

interface Props {
  instalacao: InstalacaoCampo;
  comprovante: Comprovante;
  podeRegistrar: boolean;
}

type TipoFoto = 'foto_antes' | 'foto_depois';

const ESTADO: Record<string, [string, 'info' | 'warning' | 'success' | 'neutral']> = {
  agendada: ['Agendada', 'info'],
  em_execucao: ['Em execução', 'warning'],
  aguardando_reagendamento: ['A reagendar', 'warning'],
  concluida: ['Concluída', 'success'],
  cancelada: ['Cancelada', 'neutral'],
};

// Data e hora saem do texto ISO do servidor, sem conversão para o fuso do navegador (mesma regra da Agenda).
const ddmm = (iso: string) => `${iso.slice(8, 10)}/${iso.slice(5, 7)}`;
const hora = (iso: string) => iso.slice(11, 16);

function csrf(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export default function Instalacao({ instalacao, comprovante, podeRegistrar }: Props) {
  const [enviando, setEnviando] = useState<string | null>(null);
  const [png, setPng] = useState<string | null>(null);
  const [assinadoPor, setAssinadoPor] = useState('');
  const [aceite, setAceite] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const fechada = comprovante.status === 'concluida' || comprovante.status === 'cancelada';
  const [rotulo, tom] = ESTADO[instalacao.status] ?? [instalacao.status, 'neutral'];
  const quando = instalacao.inicio
    ? `${ddmm(instalacao.inicio)} às ${hora(instalacao.inicio)}${instalacao.fim ? `–${hora(instalacao.fim)}` : ''}`
    : 'Sem horário';

  /** POST na API do comprovante. Sucesso recarrega só as props pedidas; recusa vira o alerta "Não enviado". */
  async function postar(acao: string, caminho: string, corpo: FormData | Record<string, unknown>, sucesso: number, recarregar: string[]) {
    setEnviando(acao);
    setErro(null);
    const json = !(corpo instanceof FormData);
    try {
      const res = await fetch(`/comunicacao-visual/api/instalacoes/${instalacao.id}/${caminho}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
          ...(json ? { 'Content-Type': 'application/json' } : {}),
        },
        body: json ? JSON.stringify(corpo) : corpo,
      });
      if (res.status === sucesso) {
        router.reload({ only: recarregar });
        return true;
      }
      const resposta = await res.json().catch(() => ({}));
      setErro(resposta?.message ?? `Não foi enviado (HTTP ${res.status}).`);
    } catch {
      setErro('Sem conexão: nada foi enviado. Tente de novo.');
    } finally {
      setEnviando(null);
    }
    return false;
  }

  function enviarFoto(tipo: TipoFoto, e: ChangeEvent<HTMLInputElement>) {
    const arquivo = e.target.files?.[0];
    e.target.value = '';
    if (!arquivo) return;
    const dados = new FormData();
    dados.append('tipo', tipo);
    dados.append('arquivo', arquivo);
    void postar(tipo, 'fotos', dados, 201, ['comprovante']);
  }

  async function salvarAssinatura() {
    if (!png) return;
    const corpo = { png, assinado_por: assinadoPor.trim(), consentimento: aceite };
    if (await postar('assinatura', 'assinatura', corpo, 201, ['comprovante'])) {
      setPng(null);
      setAssinadoPor('');
      setAceite(false);
    }
  }

  const fotos: { tipo: TipoFoto; nome: string; feito: boolean }[] = [
    { tipo: 'foto_antes', nome: 'Foto antes', feito: comprovante.foto_antes },
    { tipo: 'foto_depois', nome: 'Foto depois', feito: comprovante.foto_depois },
  ];

  return (
    <>
      <Head title={`Instalação ${instalacao.os_numero ?? `#${instalacao.id}`}`} />

      <div data-contract="cabecalho">
        <PageHeader title="Instalação" subtitle={instalacao.os_numero ? `OS ${instalacao.os_numero}` : undefined} />
      </div>

      <div className="mx-auto w-full max-w-md space-y-4 p-4">
        <Card data-contract="instalacao">
          <CardContent className="pt-4">
            <Stack gap={1}>
              <Inline gap={2} justify="between">
                <span className="text-xs text-muted-foreground">{quando}</span>
                <Badge variant={tom}>{rotulo}</Badge>
              </Inline>
              <b className="text-base">{instalacao.cliente ?? 'Cliente não informado'}</b>
              {instalacao.endereco ? (
                <>
                  <span className="text-sm text-muted-foreground">{instalacao.endereco}</span>
                  <a
                    className="inline-flex items-center gap-1 text-sm text-primary underline-offset-2 hover:underline"
                    href={`https://maps.google.com/?q=${encodeURIComponent(instalacao.endereco)}`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    <MapPin size={14} aria-hidden /> Abrir no mapa
                  </a>
                </>
              ) : (
                <span className="text-sm text-muted-foreground">Endereço não cadastrado no cliente.</span>
              )}
              <span className="text-xs text-muted-foreground">
                {[instalacao.equipe.join(' + '), instalacao.veiculo].filter(Boolean).join(' · ')}
              </span>
            </Stack>
          </CardContent>
        </Card>

        <Card data-contract="checklist">
          <CardHeader>
            <CardTitle>Comprovante</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="divide-y divide-border" aria-label="Comprovante da instalação">
              {fotos.map((f) => (
                <Inline asChild key={f.tipo} gap={3} justify="between">
                  <li data-item={f.tipo} className="min-h-12 py-2">
                  <Inline gap={2}>
                    <Marca feito={f.feito} />
                    <span>{f.nome}</span>
                  </Inline>
                  {podeRegistrar && !fechada ? (
                    <label className="inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-md border border-border px-3 text-sm has-[:disabled]:opacity-50">
                      <Camera size={15} aria-hidden />
                      {enviando === f.tipo ? 'Enviando…' : f.feito ? 'Refazer foto' : 'Tirar foto'}
                      <input
                        type="file"
                        accept="image/*"
                        capture="environment"
                        className="sr-only"
                        aria-label={`${f.feito ? 'Refazer' : 'Tirar'} ${f.nome.toLowerCase()}`}
                        disabled={enviando !== null}
                        onChange={(e) => enviarFoto(f.tipo, e)}
                      />
                    </label>
                  ) : (
                    <span className="text-sm text-muted-foreground">{f.feito ? 'Enviada' : 'Pendente'}</span>
                  )}
                  </li>
                </Inline>
              ))}
              <Inline asChild gap={3} justify="between">
                <li data-item="assinatura" className="min-h-12 py-2">
                <Inline gap={2}>
                  <Marca feito={comprovante.assinatura !== null} />
                  <span>Assinatura do cliente</span>
                </Inline>
                <span className="text-sm text-muted-foreground">
                  {comprovante.assinatura ? `Assinado por ${comprovante.assinatura.assinado_por ?? 'cliente'}` : 'Pendente'}
                </span>
                </li>
              </Inline>
            </ul>
            {podeRegistrar && !fechada && comprovante.assinatura === null && (
              <Stack gap={2} data-contract="assinatura" className="mt-3">
                <QuadroAssinatura onChange={setPng} disabled={enviando !== null} rotulo="Quadro de assinatura do cliente" />
                <Stack gap={1}>
                  <Label htmlFor="cv-assinado-por">Nome de quem assinou</Label>
                  <Input
                    id="cv-assinado-por"
                    value={assinadoPor}
                    maxLength={120}
                    onChange={(e) => setAssinadoPor(e.target.value)}
                  />
                </Stack>
                <Inline gap={2} align="start">
                  <Checkbox id="cv-aceite" checked={aceite} onCheckedChange={(v) => setAceite(v === true)} />
                  <Label htmlFor="cv-aceite" className="text-sm font-normal leading-snug">
                    O cliente concorda que esta assinatura fique guardada como comprovante da instalação.
                  </Label>
                </Inline>
                <Button
                  type="button"
                  variant="outline"
                  disabled={!png || assinadoPor.trim() === '' || !aceite || enviando !== null}
                  onClick={salvarAssinatura}
                >
                  {enviando === 'assinatura' ? 'Salvando…' : 'Salvar assinatura'}
                </Button>
              </Stack>
            )}
          </CardContent>
        </Card>

        {podeRegistrar && !fechada && (
          <Stack gap={1} data-contract="concluir">
            <Button
              type="button"
              className="h-11 w-full"
              disabled={!comprovante.pode_concluir || enviando !== null}
              onClick={() => postar('concluir', 'concluir', {}, 200, ['comprovante', 'instalacao'])}
            >
              {enviando === 'concluir' ? 'Concluindo…' : 'Concluir instalação'}
            </Button>
            {!comprovante.pode_concluir && (
              <span className="text-xs text-muted-foreground">Libera com foto antes, foto depois e assinatura do cliente.</span>
            )}
          </Stack>
        )}
        {comprovante.status === 'concluida' && (
          <Alert data-contract="concluida">
            <AlertTitle>Instalação concluída</AlertTitle>
            <AlertDescription>O comprovante está completo e não recebe mais fotos nem assinatura.</AlertDescription>
          </Alert>
        )}

        {erro && (
          <Alert variant="destructive" data-contract="erro">
            <AlertTitle>Não enviado</AlertTitle>
            <AlertDescription>{erro}</AlertDescription>
          </Alert>
        )}
        {!podeRegistrar && !fechada && (
          <p className="text-sm text-muted-foreground">Você pode ver esta instalação, mas não registrar o comprovante.</p>
        )}
      </div>
    </>
  );
}

function Marca({ feito }: { feito: boolean }) {
  return (
    <span
      aria-hidden
      className={`inline-flex size-5 items-center justify-center rounded-full border ${feito ? 'border-primary bg-primary text-primary-foreground' : 'border-border'}`}
    >
      {feito ? <Check size={13} /> : null}
    </span>
  );
}

Instalacao.layout = (page: ReactNode) => (
  <AppShellV2
    title="Comunicação Visual"
    breadcrumbItems={[
      { label: 'Comunicação Visual', href: '/comunicacao-visual' },
      { label: 'Agenda de instalação', href: '/comunicacao-visual/agenda' },
      { label: 'Instalação' },
    ]}
  >
    {page}
  </AppShellV2>
);
