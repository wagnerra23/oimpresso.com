// Política de privacidade do app oimpresso (ERP no celular + registro de ponto) — URL pública
// exigida pelas lojas. RASCUNHO: o texto jurídico depende de revisão da Eliana [E]
// (memory/requisitos/Site/RUNBOOK-privacidade.md). Inventário derivado do código e de
// docs/lojas-app/textos/privacidade-lojas.md — não listar o que o app não coleta.

import { DocumentoPublico } from '@/Pages/Ponto/Publico/_components/DocumentoPublico'

export default function SitePrivacidade() {
  return (
    <DocumentoPublico titulo="Política de privacidade do app oimpresso" atualizadoEm="1º de outubro de 2026" rotulo="App oimpresso">
      <p>
        O app oimpresso leva para o celular o sistema de gestão da sua empresa (vendas, orçamentos,
        produção, ordens de serviço, estoque e financeiro) e o registro de ponto dos colaboradores.
        Esta página explica quais dados ele trata, por quê e quais são os seus direitos pela Lei
        Geral de Proteção de Dados (Lei 13.709/2018, LGPD).
      </p>

      <h2>Quem é responsável pelos dados</h2>
      <ul>
        <li>
          <strong>Controladora: a empresa cliente do oimpresso</strong> — a empresa que contratou o
          sistema e criou a sua conta. É ela que decide quais dados cadastrar e quem tem acesso.
        </li>
        <li>
          <strong>Operador: o oimpresso</strong> — fornecemos o sistema e tratamos os dados em nome
          da empresa cliente, seguindo as instruções dela.
        </li>
      </ul>
      <p>Não é possível criar conta pelo app: o acesso é sempre liberado pela empresa.</p>

      <h2>Quais dados o app trata</h2>
      <ul>
        <li><strong>Dados da sua conta</strong>: nome, e-mail e usuário de acesso, cadastrados pela empresa.</li>
        <li>
          <strong>Dados que você registra no trabalho</strong>: vendas, orçamentos, ordens de serviço,
          produção, estoque, financeiro, tarefas e notas.
        </li>
        <li>
          <strong>Dados de clientes e fornecedores da empresa</strong> que você cadastra ou consulta,
          como nome, CPF ou CNPJ, telefone, e-mail e endereço.
        </li>
        <li>
          <strong>Fotos e arquivos</strong> que você escolher anexar, por exemplo a uma ordem de serviço.
          O app só acessa as suas fotos quando você seleciona uma.
        </li>
        <li>
          <strong>Registro de ponto</strong>, se a empresa usar: data, horário, tipo da marcação e a
          localização (GPS) do aparelho <strong>somente no momento</strong> em que você marca o ponto.
          Detalhes na <a className="underline" href="/privacidade/ponto">política do registro de ponto</a>.
        </li>
        <li>
          <strong>Identificador do aparelho e token de notificação</strong>, para saber de qual celular
          veio o acesso e para enviar avisos do sistema.
        </li>
        <li><strong>Registros de acesso</strong>: endereço IP, data e hora de uso do sistema.</li>
      </ul>
      <p>
        <strong>O app não usa a localização em segundo plano</strong> e o registro de ponto não coleta
        biometria nem imagem.
      </p>

      <h2>Para que usamos</h2>
      <p>
        Para fazer o sistema funcionar para a sua empresa: registrar e consultar as operações, emitir
        documentos, gerar relatórios, registrar a jornada de trabalho como exige a Portaria MTP
        nº 671/2021 e manter a segurança das contas. Não usamos os dados para publicidade e não os
        vendemos.
      </p>

      <h2>Com quem compartilhamos</h2>
      <p>
        Com a empresa cliente, que é a dona dos dados. Os dados ficam em servidores contratados pelo
        oimpresso. Quando a própria empresa usa integrações do sistema (por exemplo, emissão de nota
        fiscal ou cobrança bancária), os dados necessários vão para esses serviços em nome dela. Podemos
        entregar dados a autoridades quando a lei obrigar.
      </p>

      <h2>Por quanto tempo guardamos</h2>
      <p>
        Enquanto a empresa cliente usar o sistema e, depois disso, pelos prazos que a lei exige — por
        exemplo, documentos fiscais e registros de ponto. Marcações de ponto não podem ser apagadas nem
        alteradas: a correção é feita por justificativa, registrada ao lado da original.
      </p>

      <h2>Seus direitos e exclusão de conta</h2>
      <p>
        Pela LGPD (art. 18), você pode pedir confirmação de que tratamos seus dados, acesso, correção e
        informação sobre compartilhamento. Como a conta é criada pela empresa, o pedido — inclusive o de
        excluir a conta — é feito primeiro a ela. Se a empresa não responder, escreva para o encarregado
        do oimpresso; dados que a lei obriga a guardar são mantidos pelo prazo legal. Para o registro de
        ponto, veja também <a className="underline" href="/privacidade/ponto/exclusao">como pedir a exclusão</a>.
      </p>

      <h2>Contato</h2>
      <p>
        Encarregado de dados do oimpresso: <a className="underline" href="mailto:lgpd@oimpresso.com.br">lgpd@oimpresso.com.br</a>.
      </p>
    </DocumentoPublico>
  )
}
