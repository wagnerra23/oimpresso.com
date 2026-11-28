---
slug: 0457-plataforma-dor-cronica-associacao-associado-integracoes
number: 457
title: "Plataforma de fibromialgia e dor crônica: produto da oimpresso, módulos separados para associação, associado, clínica, pesquisa e integrações, com o paciente dono do próprio histórico"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-28"
module: null
quarter: 2026-Q4
tags: [fibromialgia, uniafibro, associacoes, fisio, captacao, pesquisa, lgpd, anvisa, rnds, fhir, argus, fcee]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0437-modulo-fisio-vertical-fisioterapia
  - 0450-fisio-notificar-componente-clinico-classe-ii-anvisa
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - 0452-captacao-projetos-de-captacao-de-recursos
  - 0454-uniafibro-estatuto-tipos-de-membro-projetos-e-dinheiro
pii: false
review_triggers:
  - "a FCEE assinar termo que permita integração técnica com o Argus"
  - "o oimpresso obter credenciamento de integrador na RNDS"
  - "uma associação fora de SC ou de outra condição (lúpus) pedir para usar"
  - "a consulta de enquadramento da Anvisa (ADR 0450) responder"
  - "projeto de pesquisa com universidade receber parecer do CEP"
---

# ADR 0457 · Plataforma de fibromialgia e dor crônica

> **Status `proposto`.** Pedido de [W] em 2026-11-28, textual: *"cada associação no Brasil deva usar, e
> todos que tenham fibromialgia deve usar e integrar tudo. Inclusive as consultas médicas e histórico de
> atendimento. Inclusive os dados do sistema argos. [...] separe os módulos para que possa funcionar na
> associação e no associado e módulos adicionais de integrações"*. Esta ADR fixa a arquitetura e as
> regras de dado antes de qualquer código. O merge é a ratificação.

## Contexto

**O que já existe** (medido em `origin/main` `abbe29b81`, 2026-11-28):

- `Modules/Fisio` (ADR 0437): prontuário COFFITO 414, portal do paciente por link + PIN, diário de dor,
  questionários, laudo biopsicossocial no modelo SES-SC (US-FISIO-060), exames por foto (US-FISIO-063),
  protocolo 11 de fibromialgia e a etapa 07 de pesquisa (proposta). 21 de 30 US prontas.
- `Modules/Associacoes` (ADR 0451) e `Modules/Captacao` (ADR 0452), com os tipos de membro e o dinheiro
  da ADR 0454. A Uniafibro é **uma empresa** com associações dentro dela.
- O app "Uniafibro" na Play (`com.oimpresso.fisio`), invólucro do portal do Fisio, e o site
  `uniafibro.com.br`.
- A ADR 0450 já separa um "componente clínico" a notificar na Anvisa, fora do ERP.

**O que trava o pedido hoje:**

1. A ADR 0451 deixou **fora** ligar a associada ao paciente do Fisio, porque são empresas diferentes e
   isso seria consulta entre empresas (ADR 0093). A D5 dela proíbe diagnóstico, laudo e CID no módulo.
2. A ADR 0437 §7 proíbe usar dado clínico de uma clínica em outra.
3. O Argus é sistema da FCEE. O Acordo de Cooperação Técnica 10.542/2026 com a AGFESC cobre o parecer
   de elegibilidade da carteira (CIPF), sem repasse e sem integração técnica.
4. Não existe hoje caminho legal para o oimpresso, empresa com fins lucrativos, ser o banco de pesquisa:
   a base de "estudo por órgão de pesquisa" da LGPD (art. 7º, IV, e 11, II, "c") só vale para órgão
   público ou pessoa jurídica sem fins lucrativos (art. 5º, XVIII), conforme o estudo técnico da ANPD.

### O que deu certo no mundo e no Brasil (pesquisa de 2026-11-28, fontes no fim)

| Referência | O que fez | O que copiamos | O que evitamos |
|---|---|---|---|
| **PatientsLikeMe** (EUA) | Rede de pacientes grátis, sem anúncio; receita vem de dado anonimizado e estudos para indústria; "dá dado, recebe dado" | Paciente grátis; devolver ao paciente o resultado agregado; publicar todas as parcerias | Vender acesso a dado como receita principal: a própria literatura a descreve como "a um erro do desastre" de confiança |
| **ArthritisPower** (EUA) | Registro de pesquisa **liderado por pacientes**, de uma ONG com universidade (UAB), financiado pelo PCORI; mais de 11 mil inscritos e mais de 45 artigos | Registro de pesquisa **dono = entidade sem fins lucrativos** + universidade; o app é ferramenta do registro | Registro dono de empresa privada |
| **Italian Fibromyalgia Registry** e **German Pain e-Registry** | Registros nacionais de fibromialgia com questionários padrão (FIQR) preenchidos na rotina clínica; devolvem estatística às clínicas | Questionários validados como dado comum; o registro nasce do atendimento que já acontece | Questionário com licença paga sem contrato |
| **Carenity** (França) | Comunidade de pacientes que vira CRO digital de estudos direto ao paciente | Recrutamento de estudo dentro do app, sempre opcional | Misturar comunidade com venda de estudo sem separação |
| **Kaia Health** (Alemanha, DiGA) | App de dor lombar listado no diretório DiGA após 6 estudos clínicos; vira reembolsável | **Evidência clínica como barreira de entrada**, não o registro em si | Prometer efeito clínico sem estudo |
| **Memed** (Brasil) | Prescrição digital grátis para médico, paciente e farmácia; virou padrão (60–70% do mercado) pelo efeito de rede | Grátis para quem gera a rede; a associação e o paciente não pagam para entrar | Receita por publicidade dirigida ao profissional dentro da ferramenta clínica |
| **RNDS** (Brasil) | Plataforma oficial de interoperabilidade do SUS, padrão HL7 FHIR, regulamentada pelo Decreto 12.560/2025; aberta à rede privada desde 2025 | **FHIR como modelo de dado clínico desde o primeiro dia** | Integração ponto a ponto com cada sistema |
| **SBIS/CFM** (Brasil) | Certificação de prontuário eletrônico; o NGS2 permite eliminar o papel | Meta de certificar o prontuário do Fisio | — |
| **Prosas** (Brasil) | Plataforma de editais para OSC | Captação como porta de entrada da associação (já é o Captacao) | — |
| **Fibro Social** (Brasil, concorrente) | Comunidade, cursos e app de uma associação nacional sediada em SC | — | Concorrer em comunidade; nosso espaço é gestão, captação, histórico e pesquisa |

**Lição comum:** quem venceu (a) é grátis para quem forma a rede, (b) deixa o dado de pesquisa sob uma
entidade sem fins lucrativos e com universidade, (c) devolve valor ao paciente e (d) usa **evidência,
padrão aberto e certificação** como barreira. **Nenhum venceu por registro regulatório**: o registro
na Anvisa autoriza vender, não impede o concorrente que cumprir as mesmas regras.

## Decisão

1. **D1 — O software é produto da oimpresso, não da Uniafibro.** A Uniafibro é a primeira rede que usa
   (como na ADR 0451 D2). O nome comercial é neutro e da empresa de [W]; não leva "Uniafibro" (marca de
   associação que a empresa não controla) nem só "Fibro" (ProFibro, Fibroline e Fibro Social já usam, e a
   plataforma vai atender lúpus e dor crônica). A escolha do nome é de [W], depois de busca no INPI.
   Cada rede aparece com a própria marca para o associado.

2. **D2 — Seis peças, cada uma com pacote próprio no superadmin** (padrão da ADR 0451 D2):

   | Peça | Para quem | O que tem | Base existente |
   |---|---|---|---|
   | **Núcleo do paciente** | todos | identidade única (gov.br quando possível), consentimentos por finalidade e destinatário, trilha de auditoria, registro pessoal de saúde | consentimentos do Fisio |
   | **Associação** | diretoria e representantes | rede, documentos, captação, emendas, prestação de contas, fluxo da carteira CIPF | `Associacoes` + `Captacao` |
   | **Associado** | paciente | app com diário da dor, histórico de consultas, exames e receitas, documentos, carteirinha, guia de direitos | portal do Fisio + área da associada |
   | **Clínica** | profissionais | Fisio hoje; prontuário médico e multiprofissional depois | `Fisio` |
   | **Pesquisa** | universidade e entidade de pesquisa | estudo aprovado no CEP, coleta nos momentos certos, exportação sem identificação | etapa 07 do Fisio, generalizada |
   | **Integrações** (adicionais) | conforme contrato | Argus/FCEE, RNDS, WhatsApp, Pix, SIGEF e Transferegov, gov.br | `Whatsapp`, `PaymentGateway` |

3. **D3 — O paciente é dono do próprio histórico.** Os dados continuam separados por empresa (ADR 0093
   segue intacta: nenhuma consulta entre empresas). O que circula entre clínica, associação e o próprio
   paciente é uma **transferência explícita, autorizada pelo paciente**, por destinatário e finalidade,
   revogável e auditada, no padrão de troca por consentimento da RNDS. O desenho técnico dessa
   transferência é **ADR própria antes de qualquer código** (onda 1). Esta decisão propõe emendar a
   ADR 0451 "Fora desta decisão" e a ADR 0437 §7 só nesse ponto: o dado clínico pode sair de uma empresa
   **quando o titular manda**, nunca por consulta da outra empresa.

4. **D4 — Quem controla cada dado (LGPD).** Clínica: prontuário. Associação: cadastro da associada.
   Paciente: o registro pessoal. Oimpresso: **operador**, com contrato de operador para cada controladora.
   Pesquisa: a controladora é a universidade ou uma entidade sem fins lucrativos com pesquisa no estatuto
   (a Uniafibro pode ser, se o estatuto disser); **nunca o oimpresso**. Estudo só roda com parecer do
   CEP (Lei 14.874/2024) e termo de consentimento; sai sem identificação.

5. **D5 — Argus só com termo assinado com a FCEE.** Proibido extrair dado do Argus com o login da
   associação, por automação de tela ou cópia em massa. Até haver termo de integração (aditivo ao ACT
   10.542/2026 ou instrumento novo), o módulo Associação apoia o trabalho da associação por dentro
   (conferência de documentos, checklist, rascunho do parecer) e a pessoa da associação lança no Argus.
   Uso de dado do Argus em pesquisa exige, além do termo, autorização da FCEE e parecer do CEP.

6. **D6 — Consultas e histórico chegam por quatro portas**, nesta ordem de prontidão: (a) o próprio
   paciente, por foto com transcrição conferida (US-FISIO-063); (b) as clínicas que usam a plataforma;
   (c) a RNDS, depois do credenciamento de integrador; (d) outros sistemas só por contrato. O modelo
   interno de dado clínico segue **HL7 FHIR** desde a primeira tabela nova.

7. **D7 — Regulatório: amplia a ADR 0450, não a substitui.** Ficam fora do escopo regulado: associação,
   captação, diário sem interpretação, histórico, carteira e coleta de pesquisa. Entra no componente
   clínico notificado tudo o que interpretar dado clínico ou sugerir conduta. Meta paralela: certificar o
   prontuário no SBIS/CFM (NGS2).

8. **D8 — Modelo de negócio sem vender o paciente.** Grátis para o paciente. A associação paga plano por
   faixa (ou o projeto que ela capta paga, ver D9). A clínica paga o Fisio. Pesquisa contratada paga o
   estudo, sempre por meio da entidade controladora e com a parceria publicada. Proibido: vender dado
   identificável, publicidade dirigida ao paciente, ranking de dor.

9. **D9 — Conflito de interesse declarado.** [W] apoia as associações na captação e é dono do software.
   Quando recurso público pagar a plataforma, a associação contrata com três orçamentos (ou chamamento),
   declara o vínculo no plano de trabalho e publica no site da rede. Sem isso, não se usa recurso
   público para pagar o sistema.

10. **D10 — Ondas** (até 300 linhas cada):
    0. Esta ADR.
    1. ADR técnica da transferência por consentimento (D3) e do modelo FHIR (D6).
    2. Associado: app com diário, histórico e documentos sobre o portal do Fisio.
    3. Associação: fluxo da carteira CIPF sem Argus (D5).
    4. Pesquisa: etapa 07 generalizada, ligada só depois de um CEP aprovado.
    5. Integrações: Argus depois do termo; RNDS depois do credenciamento.
    Em paralelo: a trilha da ADR 0450 (consultoria, enquadramento, sistema de qualidade) e o pedido de
    termo à FCEE.

## Fora desta decisão

- O nome comercial (D1), escolhido por [W].
- Preço de cada plano.
- Comunidade e fórum de pacientes (espaço do concorrente; revisitar só com sinal de cliente).
- Prescrição ou sugestão de tratamento por IA (segue a ADR 0437 §5 e a ADR 0450).

## Alternativas consideradas

- **Produto com marca e CNPJ da Uniafibro.** Amarra o software a uma associação em disputa interna e a
  uma única condição. Recusada.
- **Um módulo único "Fibro".** Mistura prontuário, associação e pesquisa no mesmo lugar e repete o erro
  que a ADR 0451 evitou ao recusar estender o Fisio. Recusada.
- **Extrair dados do Argus com o login da AGFESC.** Uso fora da finalidade do acordo, sem base legal.
  Recusada.
- **Banco de pesquisa com o oimpresso como controlador.** A base legal de pesquisa não alcança empresa
  com fins lucrativos. Recusada.
- **Consulta direta entre empresas para montar o histórico.** Viola a ADR 0093. Recusada; substituída
  pela transferência autorizada pelo titular (D3).

## Justificativa

A barreira contra concorrente que [W] busca não vem do registro na Anvisa, que qualquer um com as mesmas
regras obtém. Vem de quatro coisas que os casos de sucesso mostram e que só quem chega primeiro acumula:
a rede de associações usando, a confiança do paciente (dado nunca vendido), a evidência publicada com
universidade e as integrações oficiais (FCEE, RNDS) e a certificação. Reabrir esta decisão se a Anvisa
classificar a plataforma inteira como dispositivo médico, ou se uma integração oficial exigir outro
desenho de controle de dados.

## Consequências

**Positivas:** reaproveita Fisio, Associações e Captação; cada peça se vende e se liga sozinha; o
caminho legal de pesquisa e de Argus fica claro antes do código.

**Negativas / Trade-offs:** a transferência por consentimento é mais trabalhosa que uma consulta direta;
pesquisa e Argus dependem de terceiros (CEP, FCEE) e não têm data de código.

**Riscos mitigados:** vazamento entre empresas (ADR 0093 intacta), uso indevido do Argus, pesquisa sem
base legal, conflito de interesse em recurso público, dependência de uma única associação.

## Referências

- ADR 0093, 0437, 0450, 0451, 0452, 0454.
- PatientsLikeMe — modelo de negócio e transparência: https://link.springer.com/referenceworkentry/10.1007/978-3-319-32010-6_162 · https://d3.harvard.edu/platform-digit/submission/patientslikeme-unquestionably-successful-but-only-one-misstep-from-disaster
- ArthritisPower — registro liderado por pacientes: https://creakyjoints.org/press-releases/creakyjoints-leverages-patient-voices-to-redesign-and-relaunch-the-arthritispower-research-registry · https://creakyjoints.org/press-releases/creakyjoints-recruits-over-eleven-thousand-patients-into-arthritispower
- Italian Fibromyalgia Registry: https://www.clinexprheumatol.org/abstract.asp?a=15131 · German Pain e-Registry: https://catalogues.ema.europa.eu/print/pdf/node/2869
- Carenity: https://www.cbinsights.com/company/carenity
- Kaia Health / DiGA: https://kaiahealth.com/newsroom/press-releases/worlds-most-broadly-covered-back-pain-offering-in-germany
- Memed: https://www.bloomberglinea.com.br/startups/a-memed-queimou-caixa-por-14-anos-agora-tem-lucro-e-mira-r-100-milhoes-em-receita/
- RNDS e Decreto 12.560/2025: https://legis.senado.leg.br/norma/41155564/publicacao/41160178 · https://medicinasa.com.br/?p=157175
- RDC Anvisa 657/2022: https://www.gov.br/anvisa/pt-br/assuntos/noticias-anvisa/2022/software-como-dispositivo-medico-perguntas-e-respostas/perguntas-respostas-rdc-657-de-2022-v1-01-09-2022.pdf/@@download/file
- Certificação SBIS/CFM: https://sistemas.cfm.org.br/normas/arquivos/pareceres/DF/2014/93_2014.pdf
- LGPD, órgão de pesquisa (estudo ANPD): https://www.mattosfilho.com.br/?p=72400
- Lei 14.874/2024 (pesquisa com seres humanos): https://www12.senado.leg.br/noticias/materias/2024/06/03/sancionadas-regras-para-pesquisa-clinica-em-ser-humano
- ProFibro (app acadêmico brasileiro): https://acpopc.csp.org.uk/node/1251740
