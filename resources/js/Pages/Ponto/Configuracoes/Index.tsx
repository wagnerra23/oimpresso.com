// @docvault
//   tela: /ponto/configuracoes
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-005
//   rules: R-PONT-001, R-PONT-006
//   tests: Modules/PontoWr2/Tests/Feature/ConfiguracoesIndexTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Clock, FileSpreadsheet, PiggyBank, Settings, ShieldCheck } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';

interface CltConfig {
  tolerancia_marcacao_minutos?: number;
  tolerancia_maxima_diaria_minutos?: number;
  intrajornada_minima_minutos?: number;
  interjornada_minima_minutos?: number;
  he_maxima_diaria_minutos?: number;
  noturno_inicio?: string;
  noturno_fim?: string;
  dsr_percentual?: number;
}

interface BhConfig {
  limite_credito_minutos?: number;
  prazo_expiracao_meses?: number;
}

interface RepConfig {
  imutabilidade_mysql?: boolean;
  hash_algoritmo?: string;
  nsr_autoincrement?: boolean;
}

interface AfdConfig {
  versao_portaria?: string;
  validar_hash_encadeado?: boolean;
}

interface Props {
  config: {
    clt?: CltConfig;
    banco_horas?: BhConfig;
    rep?: RepConfig;
    afd?: AfdConfig;
    esocial?: Record<string, unknown>;
  };
}

export default function ConfiguracoesIndex({ config }: Props) {
  return (
    <>
      <Head title="Configurações" />
      <div className="mx-auto max-w-6xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <Settings size={22} /> Configurações
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Parâmetros CLT e do módulo (read-only por enquanto). Para alterar, edite <code>config/pontowr2.php</code>.
            </p>
          </div>
          <Button asChild variant="outline">
            <Link href="/ponto/configuracoes/reps">Gerenciar REPs</Link>
          </Button>
        </header>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card className="border-t-4 border-t-blue-500">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Clock size={16} /> CLT — tolerâncias e limites
              </CardTitle>
              <CardDescription className="text-xs">Art. 58, 59, 66, 71, 73</CardDescription>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Tolerância por marcação">{config.clt?.tolerancia_marcacao_minutos ?? '—'} min <small>(Art. 58 §1º)</small></Row>
              <Row label="Tolerância máxima diária">{config.clt?.tolerancia_maxima_diaria_minutos ?? '—'} min</Row>
              <Row label="Intrajornada mínima">{config.clt?.intrajornada_minima_minutos ?? '—'} min <small>(Art. 71)</small></Row>
              <Row label="Interjornada mínima">{config.clt?.interjornada_minima_minutos ?? '—'} min <small>(Art. 66)</small></Row>
              <Row label="HE máxima diária">{config.clt?.he_maxima_diaria_minutos ?? '—'} min <small>(Art. 59)</small></Row>
              <Row label="Noturno">{config.clt?.noturno_inicio ?? '—'} às {config.clt?.noturno_fim ?? '—'} <small>(Art. 73)</small></Row>
              <Row label="DSR">{config.clt?.dsr_percentual ?? '—'}% <small>(Lei 605/49 Art. 9º)</small></Row>
            </CardContent>
          </Card>

          <Card className="border-t-4 border-t-emerald-500">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <PiggyBank size={16} /> Banco de Horas
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Limite crédito">{config.banco_horas?.limite_credito_minutos ?? '—'} min</Row>
              <Row label="Prazo expiração">{config.banco_horas?.prazo_expiracao_meses ?? '—'} meses</Row>
            </CardContent>
          </Card>

          <Card className="border-t-4 border-t-violet-500">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <ShieldCheck size={16} /> REPs e Imutabilidade
              </CardTitle>
              <CardDescription className="text-xs">Portaria MTP 671/2021</CardDescription>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Imutabilidade MySQL">
                {config.rep?.imutabilidade_mysql ? (
                  <Badge className="text-[10px]">ativada (triggers)</Badge>
                ) : (
                  <Badge variant="destructive" className="text-[10px]">desligada</Badge>
                )}
              </Row>
              <Row label="Hash encadeado">{config.rep?.hash_algoritmo ?? '—'}</Row>
              <Row label="NSR sequencial">{config.rep?.nsr_autoincrement ? 'Sim' : 'Não'}</Row>
            </CardContent>
          </Card>

          <Card className="border-t-4 border-t-amber-500">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <FileSpreadsheet size={16} /> AFD & eSocial
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-xs">
              <Row label="Versão Portaria">{config.afd?.versao_portaria ?? '—'}</Row>
              <Row label="Validar hash chain">{config.afd?.validar_hash_encadeado ? 'Sim' : 'Não'}</Row>
              <Row label="eSocial">stubs S-1010 / S-2230 / S-2240 (implementação fase 3)</Row>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

ConfiguracoesIndex.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Configurações' }]}>
    {page}
  </AppShell>
);

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="grid grid-cols-2 gap-2 py-1 border-b border-border last:border-0">
      <span className="text-muted-foreground">{label}</span>
      <span>{children}</span>
    </div>
  );
}
