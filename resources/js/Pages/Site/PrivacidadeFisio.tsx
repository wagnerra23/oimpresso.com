// Política de privacidade do Portal do Paciente do Fisio e do app Uniafibro (com.oimpresso.fisio)
// — URL pública exigida pelo Google Play. Texto aprovado pelo [W] em 2026-11-13; revisão jurídica
// da Eliana [E] pendente (memory/requisitos/Site/RUNBOOK-privacidade.md §5). Inventário derivado do
// código de Modules/Fisio em origin/main 885d90bcf4 — não listar o que o portal não faz.

import { DocumentoPublico } from '@/Pages/Ponto/Publico/_components/DocumentoPublico'

export default function SitePrivacidadeFisio() {
  return (
    <DocumentoPublico
      titulo="Política de privacidade do Portal do Paciente e do app Uniafibro"
      atualizadoEm="13 de novembro de 2026"
      rotulo="Fisio · Portal do Paciente"
    >
      <p>
        Esta política vale para o <strong>Portal do Paciente</strong> do oimpresso Fisio e para o app{' '}
        <strong>Uniafibro</strong>, que abre esse portal no celular (Google Play, pacote
        com.oimpresso.fisio). Clínicas e profissionais de fisioterapia usam o portal para acompanhar o
        seu tratamento: o programa de exercícios, como você está, a agenda e os documentos. Aqui
        explicamos quais dados são tratados, por quê e quais são os seus direitos pela Lei Geral de
        Proteção de Dados (Lei 13.709/2018, LGPD).
      </p>

      <h2>Quem cuida dos seus dados</h2>
      <ul>
        <li>
          <strong>Controladora: a clínica ou o profissional que atende você.</strong> É quem decide quais
          dados registrar e para quê. Pedidos sobre o seu prontuário são feitos primeiro a ela.
        </li>
        <li>
          <strong>Operador: o oimpresso (WR2 Sistemas).</strong> Fornecemos e mantemos o sistema e
          tratamos os dados em nome da clínica.
        </li>
      </ul>
      <p>Cada clínica só enxerga os dados dos próprios pacientes.</p>

      <h2>Como você entra no portal</h2>
      <ul>
        <li>
          A clínica manda, normalmente pelo WhatsApp, um <strong>link pessoal</strong> e, numa segunda
          mensagem, um <strong>PIN de 4 dígitos</strong>. Não há senha nem cadastro.
        </li>
        <li>O link vale 30 dias. Quando a clínica gera um novo, o anterior deixa de funcionar.</li>
        <li>Depois de 5 PINs errados, o acesso fica bloqueado por 30 minutos.</li>
        <li>
          Se você escolher <strong>lembrar este aparelho</strong>, gravamos um cookie protegido no seu
          celular por 30 dias. Só nesse caso o app guarda uma cópia do programa para usar sem internet.
        </li>
        <li>Não compartilhe o link nem o PIN: quem tiver os dois vê as suas informações.</li>
      </ul>

      <h2>Quais dados são tratados</h2>
      <p>
        <strong>O que você informa no portal:</strong>
      </p>
      <ul>
        <li>
          check-in dos exercícios: o que fez, quantas vezes, dor de 0 a 10, esforço, sinais de alerta,
          crises e observações;
        </li>
        <li>pré-avaliação: regiões de dor, desde quando, o que piora ou melhora;</li>
        <li>documentos que você envia, como foto ou PDF de exame, receita, laudo ou atestado;</li>
        <li>marcações, cancelamentos e confirmações na agenda.</li>
      </ul>
      <p>
        <strong>O que a clínica registra no prontuário:</strong> identificação e contato; queixa,
        objetivos, avaliação e evolução; questionários de saúde; medicamentos; dados sociais usados no
        laudo biopsicossocial, como escolaridade, trabalho, renda familiar, CadÚnico e raça/cor; e laudos.
      </p>
      <p>
        Dados de saúde são <strong>dados sensíveis</strong> pela LGPD (art. 11). Eles são usados para o
        seu atendimento e para cumprir obrigações legais da clínica.
      </p>

      <h2>Para que usamos</h2>
      <ul>
        <li>Prestar e registrar o seu atendimento de fisioterapia.</li>
        <li>
          Enviar o link, o PIN e lembretes pelo WhatsApp, <strong>só se você autorizou</strong> o contato
          por WhatsApp.
        </li>
        <li>Gerar documentos que você pode baixar, como o laudo biopsicossocial.</li>
        <li>
          Pesquisa científica, <strong>só se você aceitar</strong> o termo de consentimento (TCLE) de um
          estudo aprovado por comitê de ética. Os dados saem sem nome, CPF, telefone ou data de nascimento,
          e você pode retirar o consentimento pelo próprio portal.
        </li>
      </ul>
      <p>
        <strong>
          Não vendemos dados, não usamos para publicidade e não usamos para treinar inteligência
          artificial.
        </strong>
      </p>

      <h2>Suas autorizações</h2>
      <p>
        O portal pede a sua autorização separadamente para cada uso: teleatendimento, contato por
        WhatsApp, uso da câmera, uso de imagem, envio de documentos e mensagens informativas. Você pode{' '}
        <strong>retirar qualquer autorização</strong> pelo portal a qualquer momento. O registro de que
        você autorizou e depois retirou fica guardado, com data, como prova.
      </p>

      <h2>Inteligência artificial</h2>
      <p>
        A clínica pode ligar um recurso que <strong>transcreve fotos de exames e receitas</strong> com
        inteligência artificial. Ele vem desligado e só funciona se a clínica ativar. Quando está ligado,
        a imagem é enviada a um provedor de IA (OpenAI ou Anthropic) só para fazer a transcrição, e o
        resultado só entra no prontuário depois que o profissional confere.
      </p>

      <h2>Com quem compartilhamos</h2>
      <ul>
        <li>
          <strong>Meta (WhatsApp)</strong>: para entregar o link, o PIN e lembretes, se você autorizou.
        </li>
        <li>
          <strong>Hospedagem</strong>: os dados e arquivos ficam em servidores contratados pelo oimpresso.
        </li>
        <li>
          <strong>Provedor de IA (OpenAI ou Anthropic)</strong>: só se a clínica ligar a transcrição.
          Esses provedores ficam fora do Brasil.
        </li>
        <li>
          <strong>Autoridades</strong>: quando a lei ou uma ordem judicial exigir.
        </li>
      </ul>
      <p>O portal não usa ferramentas de análise de navegação, anúncios nem cookies de rastreamento.</p>

      <h2>Por quanto tempo guardamos</h2>
      <p>
        O prontuário e os registros clínicos são guardados por <strong>no mínimo 20 anos</strong>, como
        exige a Lei 13.787/2018. Por isso, dados clínicos não podem ser apagados antes desse prazo, nem a
        seu pedido. Quando o atendimento termina, a clínica bloqueia o seu cadastro. Um documento que você
        enviou pode ser retirado da lista, mas continua guardado no prontuário.
      </p>

      <h2>Seus direitos</h2>
      <p>
        Pela LGPD (art. 18), você pode pedir confirmação de que tratamos seus dados, acesso, correção de
        dados errados, cópia do seu prontuário, informação sobre com quem compartilhamos e revogação dos
        consentimentos. Peça diretamente à sua clínica; se não conseguir, escreva para o encarregado do
        oimpresso, que encaminha. A exclusão completa não é possível para dados clínicos durante o prazo
        legal; os demais dados podem ser excluídos ou anonimizados quando não forem mais necessários.
      </p>

      <h2>Segurança</h2>
      <p>
        O link do portal é assinado; o PIN e o link são guardados de forma cifrada; há bloqueio contra
        tentativas repetidas; cada clínica vê só os próprios pacientes; e a conexão é sempre por HTTPS.
      </p>

      <h2>O que o app não faz</h2>
      <p>
        O app <strong>não faz teleconsulta por vídeo</strong> e <strong>não substitui</strong> a
        orientação do seu profissional de saúde. Em emergência, procure um serviço de saúde ou ligue 192
        (SAMU).
      </p>

      <h2>Mudanças nesta política</h2>
      <p>
        Quando esta política mudar, a data no topo será atualizada. Se a mudança afetar uma autorização
        sua, ela será pedida de novo.
      </p>

      <h2>Contato</h2>
      <p>
        Encarregado de dados do oimpresso:{' '}
        <a className="underline" href="mailto:lgpd@oimpresso.com.br">
          lgpd@oimpresso.com.br
        </a>
        .
      </p>
    </DocumentoPublico>
  )
}
