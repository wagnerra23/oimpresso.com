// PontoAreaHeader — o header de MÓDULO do Ponto, igual em todas as telas da área.
//
// W9 ([W] 2026-09-28, ADR 0418): "o protótipo está correto" + "olha o protótipo inteiro".
// A âncora é o shell `PontoPage` (`prototipo-ui/cowork/Wagner/ponto-page.jsx`), que monta
// TODA tela do Ponto com duas peças do padrão de módulo (`modulo-padrao.jsx`):
//   · `MP.Header` — glyph `clock` · título "Ponto" · papel "Ponto eletrônico · Portaria MTP
//     671/2021" · linha de contexto · selo "Atualizado HH:MM" (reapura) · ação "Nova
//     intercorrência";
//   · `MP.Tabs` — as abas de área, em faixa própria abaixo do header.
// Até aqui cada tela tinha o seu `<header className="os-page-h">` com título próprio
// ("Escalas · Padrões de jornada") e as abas espremidas à direita. No protótipo não existe
// título por tela: a aba ativa diz onde se está, e o que é da tela vai para o corpo.
//
// Peças COPIADAS, não inventadas:
//   · shape (`PageHeader` + `below` com as abas) — `Jana/_components/JanaAreaHeader.tsx`;
//   · linha de contexto e selo de frescor — `Patrimonio/Index.tsx` (`LinhaDeContexto`,
//     `PilulaFrescor`), que já traduziu o `CliPageHead` do protótipo pro canon.
//
// Contagens das abas: `ponto_abas`, prop DIFERIDA compartilhada por `CheckPontoAccess` em toda
// rota /ponto (`AbasContadoresService`). No 1º paint ela é `undefined` e as abas saem sem
// número; chegam quando o request diferido volta. Conformidade vem `null` sem apuração — sem
// número, nunca 0.
//
// Linha de contexto: empresa · competência · "N colaboradores no ponto" — os dois últimos em
// `ponto_contexto` (mesma prop diferida das contagens, `AbasContadoresService::contexto`).
// Pedaço que ainda não chegou sai do join (o `filter` do protótipo), não vira número inventado.
// SEM LOCAL, declarado: o protótipo escreve "matriz", mas o Ponto não tem noção de local —
// nomear um afirmaria um escopo que os números não aplicam (a mesma recusa do Patrimônio).

import { useState, type ReactNode } from 'react';
import { Link, router } from '@inertiajs/react';
import { useBusiness, usePageProps } from '@/Hooks/usePageProps';
import { PageHeader } from '@/Components/PageHeader';
import { Icon } from '@/Components/Icon';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';
import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';

const horaCurta = (): string =>
  new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

/** Linha de contexto do `CliPageHead` (mono 11px, uppercase) — idioma de `Patrimonio/Index.tsx`. */
function LinhaDeContexto({ partes }: { partes: Array<string | null | undefined> }) {
  const linha = partes.filter((p): p is string => p != null && p !== '').join(' · ');
  if (!linha) return null;
  return (
    <p className="-mb-5 px-6 pt-4 font-mono text-[11px] font-medium uppercase tracking-[0.04em] text-muted-foreground [text-wrap:pretty]">
      {linha}
    </p>
  );
}

export interface PontoAreaHeaderProps {
  /** Key do ghost ativo no `DataController` do Ponto (`dashboard`, `escalas`, …). */
  active: string;
  /**
   * Hora da apuração quando a tela a tem (o Painel recebe `server_time`). Sem ela, o selo
   * mostra a hora do render e avança quando o reload volta — o mesmo do `JanaAreaHeader`.
   */
  atualizadoAs?: string;
}

export default function PontoAreaHeader({ active, atualizadoAs }: PontoAreaHeaderProps): ReactNode {
  // Nome da empresa: mesma fonte e mesma ordem do Patrimônio (shell primeiro, sessão de
  // fallback). Sem default — imprimir um nome qualquer afirmaria um tenant que não é o do
  // usuário (ADR 0093). Os dois hooks são chamados sempre (Rules of Hooks).
  const shell = usePageProps().shell as ({ cockpit?: { businessNome?: string } } | undefined);
  const nomeDoShell = shell?.cockpit?.businessNome ?? null;
  const nomeDaSessao = useBusiness()?.name ?? null;
  const negocio = nomeDoShell ?? nomeDaSessao;
  const diferidas = usePageProps() as {
    ponto_abas?: Record<string, number | null>;
    ponto_contexto?: { competencia: string; colaboradores_no_ponto: number };
  };
  const contagens = diferidas.ponto_abas;
  const ctx = diferidas.ponto_contexto;
  const noPonto = ctx
    ? `${ctx.colaboradores_no_ponto} ${ctx.colaboradores_no_ponto === 1 ? 'colaborador' : 'colaboradores'} no ponto`
    : null;

  const [hora, setHora] = useState(() => atualizadoAs ?? horaCurta());
  const [reapurando, setReapurando] = useState(false);
  const reapurar = () => {
    setReapurando(true);
    router.reload({
      onSuccess: () => setHora(horaCurta()),
      onFinish: () => setReapurando(false),
    });
  };

  return (
    <div data-contract="ponto-area-header">
      <LinhaDeContexto partes={[negocio, ctx?.competencia, noPonto]} />
      <PageHeader
        titleWeight="semibold"
        leading={
          <span aria-hidden className="mr-2 inline-flex translate-y-[1px] align-middle text-muted-foreground">
            <Icon name="clock" size={18} strokeWidth={1.8} />
          </span>
        }
        title="Ponto"
        subtitle="Ponto eletrônico · Portaria MTP 671/2021"
        actions={
          <Inline gap={2} align="center">
            {/* 1º item de `actions`, como no `CliPageHead` ({frescor}{acoes}). É BOTÃO de
                reapuração (o protótipo passa `onRefresh`), não texto. */}
            <button
              type="button"
              onClick={reapurar}
              disabled={reapurando}
              title="Reapurar agora"
              className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-2 py-0.5 text-[11px] text-muted-foreground transition-colors hover:text-foreground disabled:opacity-60"
            >
              <span aria-hidden className="size-1.5 rounded-full bg-success" />
              {reapurando ? 'Reapurando…' : `Atualizado ${hora}`}
            </button>
            <Button size="sm" asChild>
              <Link href="/ponto/intercorrencias/create">
                <Icon name="plus" size={14} className="mr-1" aria-hidden />
                Nova intercorrência
              </Link>
            </Button>
          </Inline>
        }
        // `pt-[15px]`: medido contra o protótipo (1280, dark, Escalas) — subtítulo→barra = 29px
        // na âncora (15 até o fim do `.cli-ph` + 14 de respiro) contra 14px sem este padding.
        below={
          <div className="px-6 pt-[15px]">
            <PontoSubNav active={active} hidePrimary badges={contagens} />
          </div>
        }
      />
    </div>
  );
}
