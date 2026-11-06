// UC-PCPNL-01 · a carga de uma prop deferida que falha vira estado de erro na tela, em vez de deixar o
// skeleton girando para sempre (forma: estado "erro" de prototipo-ui/cowork/Wagner/producao-cv-page.jsx).
//
// O `<Deferred>` do Inertia 3.0 (versão travada no lockfile) não tem saída de erro: com a resposta 500 ele
// segue no `fallback`, e o Inertia abre o modal com a página de erro. Aqui se escuta o `httpException` e o
// `networkError` enquanto a prop não chegou. A carga inicial pode começar antes deste efeito montar, por isso
// "carregando" nasce de `ausente` e não só do evento `start`. Ao marcar a falha, o modal é cancelado: a tela
// mostra o erro no lugar do conteúdo.
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const pedeProp = (only: unknown, prop: string) => Array.isArray(only) && only.includes(prop);

export function useFalhaDoDeferido(prop: string, ausente: boolean) {
  const [falhou, setFalhou] = useState(false);
  const carregando = useRef(ausente);
  carregando.current = carregando.current && ausente;

  useEffect(() => {
    const falha = () => {
      if (!carregando.current) return undefined;
      carregando.current = false;
      setFalhou(true);
      return false; // cancela o modal de erro do Inertia
    };
    const tira = [
      router.on('start', (e) => { if (pedeProp(e.detail.visit.only, prop)) { carregando.current = true; setFalhou(false); } }),
      router.on('httpException', falha),
      router.on('networkError', falha),
    ];
    return () => tira.forEach((f) => f());
  }, [prop]);

  const tentarDeNovo = useCallback(() => {
    setFalhou(false);
    router.reload({ only: [prop] });
  }, [prop]);

  return { falhou, tentarDeNovo };
}
