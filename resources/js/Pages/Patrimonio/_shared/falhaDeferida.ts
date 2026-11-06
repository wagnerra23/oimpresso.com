// Falha da carga DEFERIDA — o pedaço que o `<Deferred>` do Inertia não diz.
//
// O `<Deferred>` mostra o `fallback` enquanto a prop não chegou. Se a carga deferida FALHA
// (500, 503 do deploy, rede caída), a prop nunca chega e o esqueleto fica girando para sempre:
// a tela parece "carregando" quando já desistiu. O Inertia 3.6 tem `rescue` no `<Deferred>`, mas
// ele só acende para prop que o SERVIDOR marcou como resgatada, e o adapter Laravel 3.0.6 não
// marca nenhuma — então o cliente precisa descobrir sozinho.
//
// O critério é o mais simples que não mente: uma visita que PEDIU estas props (`only`) terminou
// e alguma delas continua `undefined`. Cobre os três jeitos de falhar sem distinguir — o que
// importa para quem opera é "não veio, tente de novo". Não depende de horário nem de contar
// erro: depende do dado ter chegado.
//
// Por que ler um ref no `finish`: o swap de página do Inertia (React) roda em `flushSync`, então
// quando o evento `finish` dispara as props novas já estão commitadas e o ref já foi atualizado
// pelo render. Ler `usePage()` dentro do listener veria o valor do render em que o efeito nasceu.

import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type VisitaVista = { only?: string[] };
type EventoVisita = { detail: { visit: VisitaVista } };

export interface FalhaDeferida {
  /** A carga terminou e a prop não veio. */
  falhou: boolean;
  /** Pede só estas props de novo, sem o diálogo de erro do Inertia (a tela já mostra o seu). */
  tentarDeNovo: () => void;
  /** Uma nova tentativa está em voo. */
  tentando: boolean;
}

export function useFalhaDeferida(chaves: string[]): FalhaDeferida {
  const { props } = usePage();
  const propsRef = useRef<Record<string, unknown>>(props as Record<string, unknown>);
  propsRef.current = props as Record<string, unknown>;

  const chave = chaves.join('|');
  const chavesRef = useRef(chaves);
  chavesRef.current = chaves;

  const [falhou, setFalhou] = useState(false);
  const [tentando, setTentando] = useState(false);

  useEffect(() => {
    const pede = (visita: VisitaVista) => (visita.only ?? []).some((k) => chavesRef.current.includes(k));
    const on = router.on as unknown as ((nome: string, cb: (e: EventoVisita) => void) => () => void) | undefined;
    // Router sem `on` só existe em teste que mocka o Inertia pela metade; em produção o método
    // sempre existe. Sem ouvir eventos o hook nunca acusa falha — a tela fica como era.
    if (typeof on !== 'function') return undefined;

    const tirarInicio = on('start', (e) => {
      const visita = e.detail.visit;
      // Visita cheia (sem `only`) recomeça a página: a próxima carga deferida decide de novo.
      if (pede(visita) || !(visita.only && visita.only.length)) setFalhou(false);
    });
    const tirarFim = on('finish', (e) => {
      if (!pede(e.detail.visit)) return;
      setTentando(false);
      setFalhou(chavesRef.current.some((k) => propsRef.current[k] === undefined));
    });
    return () => {
      tirarInicio();
      tirarFim();
    };
  }, [chave]);

  const tentarDeNovo = useCallback(() => {
    setTentando(true);
    router.reload({
      only: chavesRef.current,
      onHttpException: () => false,
      onNetworkError: () => false,
    } as never);
  }, []);

  return { falhou, tentarDeNovo, tentando };
}
