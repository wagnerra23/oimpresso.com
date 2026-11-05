// ConfiguracoesCabecalho — o cabeçalho comum das telas de Configurações, como o protótipo
// (prototipo-ui/cowork/Wagner/configuracoes-page.jsx, `ConfiguracoesPage`): título "Configurações",
// o nome da tela na linha de baixo, ações e o endereço à direita, e a faixa de abas numa camada
// própria ABAIXO da borda do cabeçalho.
//
// Não usa o `PageHeader` do Shell: com ele as abas iam para a linha do título (slot `subnav`) ou
// para dentro da borda do cabeçalho (slot `below`), e o cabeçalho media 132px contra 70px no
// protótipo. Mesmo caminho das telas de Fabricação, que montam o `.os-page-h` do protótipo.
// O CSS (`.os-page-h`, `.hrm-tabs`, `.hrm-scope`) está em cowork-configuracoes-bundle.css, sob `.cfg-page`.

import type { ReactNode } from 'react';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';
import '../../../../css/cowork-configuracoes-bundle.css';

interface Props {
  /** Nome da tela, como no menu: "Locais comerciais", "Impressoras de recibos"… */
  tela: string;
  /** Endereço da tela, mostrado à direita como no protótipo. */
  rota: string;
  /** Contagem que a tela já mostrava (ex.: "3 locais ativos de 5"). Fica à direita, antes das ações. */
  resumo?: ReactNode;
  acoes?: ReactNode;
}

export default function ConfiguracoesCabecalho({ tela, rota, resumo, acoes }: Props) {
  return (
    <>
      <header className="os-page-h" data-contract="page-header">
        <div className="os-page-h-l">
          <h1>Configurações</h1>
          <p>{tela} — o grupo “Configurações” do menu antigo, tela por tela</p>
        </div>
        <div className="os-page-h-r">
          {resumo ? <span className="cfg-resumo" data-contract="resumo">{resumo}</span> : null}
          {acoes}
          <span className="hrm-scope" data-contract="escopo">{rota}</span>
        </div>
      </header>
      <ConfiguracoesSubNav emLinha />
    </>
  );
}
