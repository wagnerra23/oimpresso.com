// Página pública "Solicitar exclusão de conta e dados" do app de ponto — exigência do
// Google Play (e da App Store para apps com conta). RASCUNHO jurídico: revisão da Eliana [E].
// Regra dura: marcação de ponto é append-only por lei — explicar retenção, nunca prometer apagar.

import { DocumentoPublico } from './_components/DocumentoPublico'

export default function PontoPublicoExclusao() {
  return (
    <DocumentoPublico titulo="Solicitar exclusão de conta e dados — app de ponto" atualizadoEm="1º de outubro de 2026">
      <p>
        A sua conta no app de ponto do oimpresso <strong>é criada e gerenciada pelo seu
        empregador</strong>. Por isso, o caminho para encerrar a conta passa por ele.
      </p>

      <h2>Como pedir</h2>
      <ul>
        <li>
          <strong>Encerrar o acesso ao app:</strong> peça ao RH ou ao responsável pelo ponto na sua
          empresa. Ao desligar o seu cadastro, o app deixa de aceitar marcações.
        </li>
        <li>
          <strong>Se não conseguir falar com o empregador</strong>, escreva para{' '}
          <a className="underline" href="mailto:lgpd@oimpresso.com.br">lgpd@oimpresso.com.br</a>{' '}
          com o seu nome, o nome da empresa empregadora e o pedido. Encaminhamos ao empregador,
          que é o responsável pelos dados, e respondemos a você.
        </li>
      </ul>

      <h2>O que é apagado e o que precisa ser guardado</h2>
      <ul>
        <li>
          <strong>Pode ser encerrado:</strong> o seu acesso ao app (login) e o identificador do
          aparelho deixa de ser usado em novas marcações.
        </li>
        <li>
          <strong>Precisa ser guardado:</strong> as marcações de ponto já registradas, com horário e
          localização do momento da marcação, e as justificativas. A Portaria MTP nº 671/2021
          exige que o registro de ponto seja inviolável — ele <strong>não pode ser apagado nem
          alterado</strong>, nem a seu pedido. Esses registros ficam guardados enquanto houver
          obrigação legal de conservá-los.
        </li>
      </ul>
      <p>
        O app não coleta biometria nem imagem, então não há foto nem digital a apagar.
      </p>

      <p>
        Mais detalhes na <a className="underline" href="/privacidade/ponto">política de privacidade do app de ponto</a>.
      </p>
    </DocumentoPublico>
  )
}
