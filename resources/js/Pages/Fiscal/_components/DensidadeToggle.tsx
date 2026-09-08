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
// O ESTADO mora em `../_lib/densidade-fiscal` — este arquivo exporta SÓ o componente,
// porque `react-refresh/only-export-components` reprova arquivo que mistura os dois
// (quebra o Fast Refresh do Vite: editar o hook remontaria a árvore em vez de preservar
// estado). O baseline do ESLint registrava 0 dessa regra aqui, então era regressão nova
// — a catraca pegou, e o conserto é separar, não subir o baseline.
//
// Estilo: reusa `.fx-density` / `.fx-density-{compact,comfort,relax}`, que já vivem
// em resources/css/fiscal-cockpit.css:877-948. Nenhuma classe nova.

import type { Densidade } from '../_lib/densidade-fiscal';

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
