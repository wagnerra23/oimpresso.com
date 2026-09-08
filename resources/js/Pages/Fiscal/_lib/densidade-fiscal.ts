// densidade-fiscal.ts — a preferência de densidade da tabela, compartilhada pelas
// telas de notas do Fiscal (Cockpit · NF-e · NFS-e).
//
// POR QUE MORA AQUI E NÃO NO `_components/DensidadeToggle.tsx`
// ===========================================================
// O hook e as constantes viviam junto do componente, e o `react-refresh/only-export-components`
// reprovou: um arquivo que exporta componente E não-componente quebra o Fast Refresh do Vite
// (editar o hook faz o React remontar a árvore em vez de preservar estado). O baseline do
// ESLint registrava 0 dessa regra para este arquivo, então era regressão nova — a catraca
// pegou, e o conserto é separar, não subir o baseline.
//
// A preferência é do OPERADOR, não da tela: quem escolhe "compacto" na lista de NF-e espera
// o mesmo ao abrir NFS-e. Por isso a chave de storage é UMA e mora aqui — a mesma que a
// fonte de design declara em prototipo-ui/cowork/fiscal-page.jsx:358,363. Se ela divergir,
// a preferência para de acompanhar a navegação e o `fiscal-densidade.test.tsx` quebra.

import { useEffect, useState } from 'react';

export type Densidade = 'compact' | 'comfort' | 'relax';

/** Chave única da preferência — espelha `fxLS("oimpresso.fiscal.densidade")` do protótipo. */
export const DENSIDADE_STORAGE_KEY = 'oimpresso.fiscal.densidade';

export const DENSIDADE_PADRAO: Densidade = 'comfort';

const VALIDAS: readonly Densidade[] = ['compact', 'comfort', 'relax'];

function ehDensidade(v: unknown): v is Densidade {
  return typeof v === 'string' && (VALIDAS as readonly string[]).includes(v);
}

/**
 * Densidade persistida, compartilhada entre as telas de notas.
 *
 * A leitura do storage acontece em `useEffect`, NÃO no initializer do `useState`:
 * este app tem SSR (resources/js/ssr.tsx) e `localStorage` não existe no Node — ler
 * no initializer derruba o render do servidor. O preço é um frame no padrão antes de
 * hidratar, que é invisível porque só muda o padding das células.
 *
 * Todo acesso vai em try/catch: em janela privada (ou com storage bloqueado pelo
 * navegador) o getter em si lança, e a tela não pode cair por causa de uma preferência
 * cosmética. Mesmo idioma de `lsGet`/`lsSet` do Financeiro.
 */
export function useDensidadeFiscal(): [Densidade, (d: Densidade) => void] {
  const [densidade, setDensidade] = useState<Densidade>(DENSIDADE_PADRAO);

  useEffect(() => {
    try {
      const salva = window.localStorage.getItem(DENSIDADE_STORAGE_KEY);
      if (ehDensidade(salva)) setDensidade(salva);
    } catch {
      /* storage indisponível — segue no padrão */
    }
  }, []);

  const escolher = (d: Densidade) => {
    setDensidade(d);
    try {
      window.localStorage.setItem(DENSIDADE_STORAGE_KEY, d);
    } catch {
      /* storage indisponível — a escolha vale só nesta sessão */
    }
  };

  return [densidade, escolher];
}
