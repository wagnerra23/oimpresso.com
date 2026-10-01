// Política de privacidade do app de ponto — URL pública exigida pelas lojas.
// RASCUNHO: o texto jurídico depende de revisão da Eliana [E] (RUNBOOK-publico.md).
// Inventário de dados derivado do código (MobileMarcacaoService) e da ADR 0383 — não inventar.

import { DocumentoPublico } from './_components/DocumentoPublico'

export default function PontoPublicoPrivacidade() {
  return (
    <DocumentoPublico titulo="Política de privacidade do app de ponto" atualizadoEm="1º de outubro de 2026">
      <p>
        O app de ponto do oimpresso serve para você registrar a sua jornada de trabalho pelo
        celular. Esta página explica quais dados ele trata, por quê e quais são os seus direitos
        pela Lei Geral de Proteção de Dados (Lei 13.709/2018, LGPD).
      </p>

      <h2>Quem é responsável pelos seus dados</h2>
      <ul>
        <li>
          <strong>Controlador: o seu empregador</strong> — a empresa que contratou o oimpresso e
          cadastrou você. É ela que decide registrar o ponto pelo celular e que responde pelas
          obrigações trabalhistas.
        </li>
        <li>
          <strong>Operador: o oimpresso</strong> — fornecemos o sistema e tratamos os dados em
          nome do seu empregador, seguindo as instruções dele.
        </li>
      </ul>

      <h2>Quais dados o app trata</h2>
      <ul>
        <li><strong>Data e horário</strong> de cada marcação, definidos pelo nosso servidor.</li>
        <li><strong>Tipo da marcação</strong>: entrada, saída para almoço, retorno ou saída.</li>
        <li>
          <strong>Localização (GPS)</strong> do aparelho <strong>somente no momento</strong> em
          que você toca para marcar o ponto. O app não acompanha a sua localização fora disso.
        </li>
        <li><strong>Identificador do aparelho</strong>, para saber de qual celular veio a marcação.</li>
        <li>
          <strong>Dados do seu cadastro</strong> informados pelo empregador, como nome e matrícula.
        </li>
        <li>
          <strong>Justificativas</strong> que você mesmo enviar pelo app (por exemplo, um
          esquecimento de marcação).
        </li>
      </ul>
      <p>
        <strong>O app não coleta biometria nem imagem</strong>: não usa câmera, foto, selfie,
        digital ou reconhecimento facial.
      </p>

      <h2>Para que usamos</h2>
      <p>
        Para registrar a jornada de trabalho como exige a Portaria MTP nº 671/2021 e a CLT, gerar o
        espelho de ponto e permitir que o seu empregador confira as marcações. A localização serve
        para o empregador verificar se a marcação foi feita no local de trabalho combinado. A base
        legal é o cumprimento de obrigação legal e a execução do contrato de trabalho (LGPD, art.
        7º, II e V).
      </p>

      <h2>Por quanto tempo guardamos</h2>
      <p>
        As marcações de ponto <strong>não podem ser apagadas nem alteradas</strong>: a Portaria
        MTP nº 671/2021 exige que o registro seja inviolável. Uma marcação errada é corrigida por
        uma justificativa, que fica registrada ao lado da original. Os registros são guardados
        enquanto existir obrigação legal de conservá-los, inclusive pelos prazos em que a
        legislação trabalhista permite questionar a jornada.
      </p>

      <h2>Com quem compartilhamos</h2>
      <p>
        Com o seu empregador, que é o dono do registro. Os dados ficam em servidores contratados
        pelo oimpresso no Brasil. Não vendemos os seus dados e não os usamos para publicidade.
        Podemos entregá-los a autoridades quando a lei obrigar, como em fiscalização do trabalho.
      </p>

      <h2>Seus direitos</h2>
      <p>
        Pela LGPD (art. 18), você pode pedir confirmação de que tratamos seus dados, acesso a eles,
        correção do cadastro e informação sobre com quem são compartilhados. Como a conta é criada
        pelo seu empregador, o pedido é feito primeiro a ele, que é o controlador. Se precisar,
        fale também com o oimpresso — veja <a className="underline" href="/privacidade/ponto/exclusao">como pedir a exclusão de conta e dados</a>.
      </p>

      <h2>Contato</h2>
      <p>
        Encarregado de dados do oimpresso: <a className="underline" href="mailto:lgpd@oimpresso.com.br">lgpd@oimpresso.com.br</a>.
      </p>
    </DocumentoPublico>
  )
}
