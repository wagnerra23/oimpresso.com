// DensidadeToggle.tsx — o controle de densidade da tabela, compartilhado pelas
// telas de notas do Fiscal (Cockpit · NF-e · NFS-e).
//
// POR QUE É UM COMPONENTE, E NÃO TRÊS CÓPIAS
// ==========================================
// Na fonte de design as três telas são a MESMA função — `FxNotasPage`, chamada com
// `preset` diferente (prototipo-ui/cowork/fiscal-page.jsx:346,541-543). Densidade,
// busca e pager são compartilhados por construção. A produção separou em três
// arquivos, e o controle ficou só no Cockpit; extrair aqui recupera o dono único.
//
// A preferência é do OPERADOR, não da tela: quem escolhe "compacto" na lista de
// NF-e espera o mesmo ao abrir NFS-e. Por isso a chave de storage é UMA
// (`DENSIDADE_STORAGE_KEY`) e mora aqui — a mesma que a fonte de design declara em
// fiscal-page.jsx:358,363. Se ela divergir, a preferência para de acompanhar a
// navegação e o `DensidadeContratoTest` quebra.
//
// Estilo: reusa `.fx-density` / `.fx-density-{compact,comfort,relax}`, que já vivem
// em resources/css/fiscal-cockpit.css:877-948. Nenhuma classe nova.

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
 * este app tem SSR ligado (resources/js/ssr.tsx) e `localStorage` não existe no
 * Node — ler no initializer derruba o render do servidor. O preço é um frame no
 * padrão antes de hidratar, que é invisível porque só muda o padding das células.
 *
 * Todo acesso vai em try/catch: em janela privada (ou com storage bloqueado pelo
 * navegador) o getter em si lança, e a tela não pode cair por causa de uma
 * preferência cosmética. Mesmo idioma de `lsGet`/`lsSet` do Financeiro.
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

interface DensidadeToggleProps {
  value: Densidade;
  onChange: (d: Densidade) => void;
}

/**
 * Os três botões de densidade. `role="radiogroup"` + `aria-pressed` vêm do Cockpit
 * (era a única tela que tinha o controle) e do protótipo — não reinvento a semântica aqui.
 *
 * Cada ícone declara `aria-hidden`: o lucide não emite sozinho, e o
 * `fiscal-nfe-teclado.test.tsx` (UC-FNFE-11) exige que nenhum ícone decorativo da
 * `.fx-page` chegue ao leitor de tela. Mas o ícone aqui é o ÚNICO conteúdo do botão —
 * e a nota daquele mesmo teste diz a regra: *"ícone sozinho em botão precisa de rótulo
 * no botão, não de aria-hidden no ícone"*. Daí o `aria-label` explícito, que não
 * depende do `title` ser promovido a nome acessível pelo navegador.
 */
export default function DensidadeToggle({ value, onChange }: DensidadeToggleProps) {
  return (
    <div className="fx-density" role="radiogroup" aria-label="Densidade da tabela">
      <button
        type="button"
        className={value === 'compact' ? 'active' : ''}
        onClick={() => onChange('compact')}
        title="Compacto"
        aria-label="Densidade compacta"
        aria-pressed={value === 'compact'}
      >
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <rect x="3" y="6" width="18" height="2" />
          <rect x="3" y="11" width="18" height="2" />
          <rect x="3" y="16" width="18" height="2" />
        </svg>
      </button>
      <button
        type="button"
        className={value === 'comfort' ? 'active' : ''}
        onClick={() => onChange('comfort')}
        title="Confortável"
        aria-label="Densidade confortável"
        aria-pressed={value === 'comfort'}
      >
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <rect x="3" y="4" width="18" height="3" />
          <rect x="3" y="10" width="18" height="3" />
          <rect x="3" y="16" width="18" height="3" />
        </svg>
      </button>
      <button
        type="button"
        className={value === 'relax' ? 'active' : ''}
        onClick={() => onChange('relax')}
        title="Relaxado"
        aria-label="Densidade relaxada"
        aria-pressed={value === 'relax'}
      >
        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <rect x="3" y="3" width="18" height="5" />
          <rect x="3" y="11" width="18" height="5" />
        </svg>
      </button>
    </div>
  );
}
