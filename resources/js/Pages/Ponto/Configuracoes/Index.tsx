// @docvault
//   tela: /ponto/configuracoes
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-005
//   rules: R-PONT-001, R-PONT-006
//   tests: Modules/PontoWr2/Tests/Feature/ConfiguracoesIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
import { Inline } from '@/Components/layout';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Clock, FileSpreadsheet, PiggyBank, ShieldCheck } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

// Nomes = os de `Modules/Ponto/Config/config.php` (publicado como `config/pontowr2.php`).
// Até 2026-09-28 esta tela lia 13 chaves que o config não tem e exibia "—"/"Não" onde havia
// valor. Toda leitura aqui é `config.<bloco>?.<chave>` DE PROPÓSITO: o UC-CFGIDX-02 extrai
// essas leituras deste arquivo e exige que cada uma chegue no payload do controller.
interface CltConfig {
  tolerancia_minutos_por_marcacao?: number;
  tolerancia_maxima_diaria_minutos?: number;
  interjornada_minima_horas?: number;
  intrajornada_minima_minutos?: number;
  hora_noturna_ficta_segundos?: number;
  adicional_noturno_percentual?: number;
  limite_he_diaria_horas?: number;
  adicional_he_percentual?: number;
  adicional_dsr_percentual?: number;
}

interface BhConfig {
  habilitado?: boolean;
  prazo_compensacao_meses?: number;
  saldo_maximo_horas?: number;
  saldo_minimo_horas?: number;
  multiplicador_credito?: number;
  multiplicador_debito?: number;
  converter_he_em_bh_default?: boolean;
}

interface RepConfig {
  tipos_permitidos?: string[];
  nsr_verificar_sequencia?: boolean;
  assinar_marcacoes?: boolean;
  certificado_icp_configurado?: boolean;
}

interface MarcacaoConfig {
  janela_correcao_minutos?: number;
  forcar_append_only?: boolean;
  hash_algoritmo?: string;
}

interface AfdConfig {
  encoding?: string;
  max_filesize_mb?: number;
  chunk_size_linhas?: number;
  validar_hash_registros?: boolean;
}

interface EsocialConfig {
  ambiente?: string;
  eventos?: string[];
  tp_amb?: number | string;
}

interface Props {
  config: {
    clt?: CltConfig;
    banco_horas?: BhConfig;
    rep?: RepConfig;
    marcacao?: MarcacaoConfig;
    afd?: AfdConfig;
    esocial?: EsocialConfig;
  };
}

const simNao = (v?: boolean) => (v === undefined ? '—' : v ? 'Sim' : 'Não');
const ou = (v: unknown) => (v === undefined || v === null || v === '' ? '—' : String(v));

/** 3150 s → "52min30s" (Art. 73 §1º: a hora noturna é contada como 52 min 30 s). */
function duracaoSegundos(s?: number): string {
  if (s === undefined) return '—';
  const min = Math.floor(s / 60);
  const seg = s % 60;
  return seg ? `${min}min${String(seg).padStart(2, '0')}s` : `${min}min`;
}

export default function ConfiguracoesIndex({ config }: Props) {
  return (
    <>
      <div className="mx-auto max-w-6xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <PontoAreaHeader active="configuracoes" />
        <Inline gap={2} justify="end">
          <Button asChild variant="outline">
            <Link href="/ponto/configuracoes/reps">Gerenciar REPs</Link>
          </Button>
        </Inline>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card className="border-t-4 border-t-info" data-contract="configuracoes-regras-clt-reforma-trabalhista">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Clock size={16} /> CLT — tolerâncias e limites
              </CardTitle>
              <CardDescription className="text-xs">Art. 58, 59, 66, 71, 73 · CF/88 · Lei 605/49</CardDescription>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Tolerância por marcação">{ou(config.clt?.tolerancia_minutos_por_marcacao)} min <small>(Art. 58 §1º)</small></Row>
              <Row label="Tolerância máxima diária">{ou(config.clt?.tolerancia_maxima_diaria_minutos)} min <small>(Art. 58 §1º)</small></Row>
              <Row label="Interjornada mínima">{ou(config.clt?.interjornada_minima_horas)} h <small>(Art. 66)</small></Row>
              <Row label="Intrajornada mínima">{ou(config.clt?.intrajornada_minima_minutos)} min <small>(Art. 71)</small></Row>
              <Row label="Hora noturna ficta">{duracaoSegundos(config.clt?.hora_noturna_ficta_segundos)} <small>(Art. 73 §1º)</small></Row>
              <Row label="Adicional noturno">{ou(config.clt?.adicional_noturno_percentual)}% <small>(Art. 73)</small></Row>
              <Row label="Limite de HE diária">{ou(config.clt?.limite_he_diaria_horas)} h <small>(Art. 59)</small></Row>
              <Row label="Adicional de HE">{ou(config.clt?.adicional_he_percentual)}% <small>(Art. 7º XVI CF/88)</small></Row>
              <Row label="Adicional DSR">{ou(config.clt?.adicional_dsr_percentual)}% <small>(Lei 605/49 Art. 9º)</small></Row>
            </CardContent>
          </Card>

          <Card className="border-t-4 border-t-success" data-contract="configuracoes-banco-de-horas">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <PiggyBank size={16} /> Banco de Horas
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Habilitado">{simNao(config.banco_horas?.habilitado)}</Row>
              <Row label="Prazo de compensação">{ou(config.banco_horas?.prazo_compensacao_meses)} meses <small>(Reforma Trabalhista — acordo individual)</small></Row>
              <Row label="Saldo máximo">{ou(config.banco_horas?.saldo_maximo_horas)} h</Row>
              <Row label="Saldo mínimo">{ou(config.banco_horas?.saldo_minimo_horas)} h</Row>
              <Row label="Multiplicador crédito">{ou(config.banco_horas?.multiplicador_credito)}x</Row>
              <Row label="Multiplicador débito">{ou(config.banco_horas?.multiplicador_debito)}x</Row>
              <Row label="Converter HE em BH automaticamente">{simNao(config.banco_horas?.converter_he_em_bh_default)}</Row>
            </CardContent>
          </Card>

          <Card className="border-t-4 border-t-primary" data-contract="configuracoes-rep-e-imutabilidade-de-marcacoes">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <ShieldCheck size={16} /> REPs e Imutabilidade
              </CardTitle>
              <CardDescription className="text-xs">Portaria MTP 671/2021</CardDescription>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Tipos de REP permitidos">
                {(config.rep?.tipos_permitidos ?? []).length
                  ? (config.rep?.tipos_permitidos ?? []).map((t) => (
                      <Badge key={t} variant="outline" className="mr-1 text-[10px]">{t.replace('_', '-')}</Badge>
                    ))
                  : '—'}
              </Row>
              <Row label="Verificar sequência NSR">{simNao(config.rep?.nsr_verificar_sequencia)}</Row>
              <Row label="Assinar marcações (ICP-Brasil)">{simNao(config.rep?.assinar_marcacoes)}</Row>
              <Row label="Certificado ICP configurado">
                {config.rep?.certificado_icp_configurado ? (
                  <Badge className="text-[10px]">Sim</Badge>
                ) : (
                  <Badge variant="destructive" className="text-[10px]">Não</Badge>
                )}
              </Row>
              <Row label="Janela de correção">{ou(config.marcacao?.janela_correcao_minutos)} min</Row>
              <Row label="Append-only forçado">{simNao(config.marcacao?.forcar_append_only)}</Row>
              <Row label="Hash"><span className="font-mono">{ou(config.marcacao?.hash_algoritmo)}</span></Row>
            </CardContent>
          </Card>

          <Card className="border-t-4 border-t-amber-500" data-contract="configuracoes-afd-importacao-esocial">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <FileSpreadsheet size={16} /> AFD & eSocial
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Encoding"><span className="font-mono">{ou(config.afd?.encoding)}</span></Row>
              <Row label="Tamanho máximo">{ou(config.afd?.max_filesize_mb)} MB</Row>
              <Row label="Chunk de processamento">{ou(config.afd?.chunk_size_linhas)} linhas</Row>
              <Row label="Validar hash de registros">{simNao(config.afd?.validar_hash_registros)}</Row>
              <Row label="Ambiente eSocial"><span className="font-mono">{ou(config.esocial?.ambiente)}</span></Row>
              <Row label="Eventos eSocial">{(config.esocial?.eventos ?? []).join(' · ') || '—'} <small>(stubs — fase 3)</small></Row>
              <Row label="tpAmb">{ou(config.esocial?.tp_amb)}</Row>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

ConfiguracoesIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Configurações · Ponto" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Configurações' }]}>
    {page}
  </AppShellV2>
);

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-2 gap-2 py-1 border-b border-border last:border-0">
      <span className="text-muted-foreground">{label}</span>
      <span>{children}</span>
    </div>
  );
}
