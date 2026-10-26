---
status: proposal
data: "2026-10-26"
autor: "sessão lacunas Ponto e RH [C]"
decisor: "Wagner [W]"
revisora: "Eliana [E]"
related_adrs:
  - 0413-ponto-fechamento-competencia-conformidade-relatorios-legais
  - 0419-ponto-rep-p-escopo-ratificado-w10
  - 0420-ponto-aej-le-apuracao-e-falha-fechada
  - 0383-ponto-interno-nao-coleta-biometria
---

# Ponto e RH: o que só o [W] destrava

Levantamento de lacunas de 2026-10-25, frente Ponto e RH. Cada item abaixo foi re-medido no
`main` em 2026-10-26 (`93d6a5d11c`). O que dava para fazer sem decisão está em PR; o que sobra
está aqui, com a pergunta exata e o que eu faço logo depois da resposta.

O detalhe legal (artigos, FAQ do MTE, passo a passo do INPI) já está em
[LEGAL-REP-P-portaria-671.md](../../requisitos/Ponto/LEGAL-REP-P-portaria-671.md). Este documento
não repete aquele: só ordena as decisões.

## 1. Assinatura ICP-Brasil do AFD, do AEJ e do comprovante

**Estado.** AFD (#8224) e AEJ (#8242) saem no leiaute certo, com a linha reservada da assinatura,
mas sem o arquivo `.p7s`. O comprovante por batida (US-PONTO-010) não existe. A ADR 0413 D2 deixou
a assinatura ICP fora e exige ADR nova. O FAQ 33/34 do MTE diz que quem assina é o
**desenvolvedor**, não o empregador cliente.

**Proposta de ADR (a escrever depois do sim):**

- Um **e-CNPJ A1** da empresa desenvolvedora assina AFD e AEJ em CAdES destacado (`.p7s`) e o
  comprovante em PAdES. A1 porque fica no servidor; A3 exige token e não assina sozinho.
- Certificado e senha no **Vaultwarden**, lidos em runtime. Já existem as chaves
  `PONTO_CERT_ICP_PATH` e `PONTO_CERT_ICP_PASS` em `Modules/Ponto/Config/config.php`, sem uso.
- Sem certificado configurado, AFD e AEJ continuam saindo **sem** `.p7s` (como hoje) e o
  comprovante **não** sai. Nada de assinatura falsa.
- Renovação anual com alerta 30 dias antes do vencimento.

**Pergunta ao [W]:** compra o e-CNPJ A1 e aprova a ADR nesses termos? (L5 do LEGAL-REP-P)

**Depois do sim, eu faço:** ADR · serviço de assinatura · `.p7s` no AFD e AEJ · comprovante
PAdES por batida no app (US-PONTO-010), cada um num PR.

## 2. Registro no INPI e atestado técnico

**Estado.** Sem o número do INPI em `PONTO_REP_P_INPI`, o AFD e o AEJ recusam. Não existe atestado
técnico (art. 89, Anexo VII). Nenhuma descrição de loja pode dizer "homologado" ou "certificado".

**O que é do [W] (não automatizável):**

1. Dizer qual CNPJ é o titular do registro e o desenvolvedor no AFD (L1).
2. Pedido no e-Software do INPI com assinatura qualificada ICP-Brasil (o mesmo e-CNPJ do item 1
   serve), GRU código 730, Declaração de Veracidade — passo a passo no LEGAL-REP-P §4.1.
3. Assinar o atestado como responsável legal (pessoa física, certificado A1 ou A3) junto com o
   responsável técnico (art. 89 §2º, FAQ 45).

**O que é da [E]:** conferir no DOU o texto vigente dos arts. 74-101 e do Anexo VII, e redigir o
atestado no modelo do Anexo VII (L4, L6).

**Eu deixo pronto depois do sim:** o hash do código-fonte da versão registrada (o INPI recebe só
o hash), a tag de versão e o lugar do número no `.env`. Não gero o hash antes de saber o escopo
do registro (L2), porque o hash muda com o escopo.

## 3. Eventos ao eSocial

**Estado.** O card "Eventos eSocial" do catálogo de relatórios está `disponivel: false`
(`RelatorioController.php:64`). Não existe nenhum código de eSocial no repo.

**Por que não construo agora.** O eSocial é um webservice do governo com certificado ICP, lote
assinado em XML, retorno assíncrono e tabelas próprias. Os eventos que o ponto alimenta
(S-1200 remuneração, S-2230 afastamento) dependem da **folha**, que não existe (item 4). Começar
pelo eSocial antes da folha seria enviar evento de remuneração sem remuneração calculada.

**Recorte proposto:**

- **E1.** S-2230 (afastamento, incluindo férias) a partir das licenças do HRM e do
  `FeriasService` (US-PONTO-018). Não depende de folha.
- **E2.** S-1200/S-1210 só depois da folha (item 4).
- **E3.** S-2200/S-2206 (admissão e alteração) só se o oimpresso passar a ser o cadastro oficial
  do empregado; hoje o cliente usa a contabilidade para isso.

**Pergunta ao [W]:** o oimpresso deve transmitir ao eSocial, ou só gerar os dados para a
contabilidade transmitir? A segunda opção é a de Tangerino e Pontotel e não precisa de
certificado do cliente.

## 4. Folha de pagamento CLT

**Estado.** Só a folha genérica do UltimatePOS no Essentials (`PayrollGroup`), sem INSS, IRRF,
FGTS nem holerite. O SPEC do Ponto declara folha como Non-Goal.

**Recorte proposto (do menor para o maior):**

- **F0. Exportação para a folha da contabilidade.** Arquivo por competência com horas normais,
  HE 50%, HE 100%, adicional noturno, faltas, atrasos, DSR e banco de horas, por colaborador.
  Já está em construção nesta frente (P1, relatórios HE/BH/Atrasos). É o que a maioria dos
  clientes pequenos usa.
- **F1. Holerite informativo**, sem cálculo de encargo: proventos e descontos digitados pelo RH.
- **F2. Folha com cálculo** (INSS por faixa, IRRF, FGTS, 13º, férias com 1/3, rescisão). Exige
  tabelas legais anuais, dupla prova de valor e revisão da [E]. Concorre com Domínio e Alterdata.

**Pergunta ao [W]:** paro em F0, ou F1/F2 entram no roadmap? F2 é um módulo inteiro.

## 5. Questões já abertas no LEGAL-REP-P que seguem sem resposta

- **L3:** recusar a batida por GPS acima de 500 m ou relógio fora de 30 s conflita com o art. 74
  ("registrar fielmente")? [E] avalia, [W] decide.

## Fora desta frente

- Biometria facial: fora por decisão [W] de 2026-08-27 (LGPD, ADR 0383).
- Batida offline no app: o FAQ 52 do MTE diz que não é obrigatória para REP-P. Não entra.
