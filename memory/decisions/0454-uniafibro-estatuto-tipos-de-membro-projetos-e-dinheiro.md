---
slug: 0454-uniafibro-estatuto-tipos-de-membro-projetos-e-dinheiro
number: 454
title: "Uniafibro conforme o estatuto: tipos de membro, quem é associado, controle de parceria e dinheiro — emenda à 0451 e complemento à 0452"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-16"
module: null
quarter: 2026-Q4
tags: [associacoes, captacao, financeiro, uniafibro, estatuto, mrosc, lgpd, dado-sensivel, multi-tenant]
supersedes: []
supersedes_partially:
  - 0451-associacoes-rede-uniafibro-area-de-membros
superseded_by: []
related:
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "o texto do estatuto mudar antes do registro (o documento ainda tem sugestões não aceitas)"
  - "a Assembleia aprovar o Regimento Interno (reserva financeira, cooperação entre filiadas no mesmo município)"
  - "uma filiada pedir para usar o sistema com os próprios associados (vira empresa própria)"
  - "a Uniafibro passar a atuar em rede pelo art. 35-A da Lei 13.019/2014 (Art. 71 do estatuto)"
---

# ADR 0454 · Uniafibro conforme o estatuto

> **Status `proposto`.** [W] pediu em 2026-11-16 que o desenho do sistema siga o estatuto
> (*"o controle de projetos, financeiro e o dashboard dos grupos, cidades e CNPJ vinculados são
> assuntos diferentes. Confira o estatuto e as regras de controle dos projetos e fundos"*) e aprovou
> escrever esta emenda (*"sim, escreve a emenda"*). O merge desta ADR é o ato de ratificação.

## Contexto

A [ADR 0451](0451-associacoes-rede-uniafibro-area-de-membros.md) (aceita) desenhou a rede como
cadastro e documentos, com dinheiro **fora**. A ADR 0452 (Captação, proposta no PR #10895) cobre o
ciclo do projeto, também com o Financeiro fora (D7).

Fonte desta emenda: *Estatuto Social – UNIAFIBRO-SC (versão revisada)*, documento do [W] no claude.ai,
lido inteiro em 2026-11-16. O documento ainda tem sugestões não aceitas e **ainda não foi registrado**;
até o registro, vale o estatuto atual da AGFESC. O sistema é desenhado para o estatuto novo porque é
ele que vai operar.

O que o estatuto exige e o desenho atual não cobre:

1. **Quatro tipos de membro com regras diferentes** (Art. 11): *filiada* (associação com CNPJ próprio,
   autônoma — Art. 13 § 1º), *unidade própria* (sede e filiais, mesmo CNPJ da Uniafibro, gestão
   financeira centralizada — Art. 11-A §§ 9º–13), *grupo em formação* (sem CNPJ, voz sem voto) e
   *associado individual* (pessoa, vinculada à sede ou a uma filial própria, com consentimento LGPD).
   Cada filiada declara os municípios em que atua (Art. 11 § 4º).
2. **A AGFESC não é uma filiada.** O Art. 88 diz que a Uniafibro **é** a AGFESC reformada: mantém
   personalidade jurídica, CNPJ, patrimônio e parcerias. Depois do registro, Criciúma é unidade
   própria e os associados da AGFESC viram associados individuais (Art. 88 § 2º). A 0451 e a 0452
   dizem "a Uniafibro ainda não tem CNPJ"; depois do registro ela tem o CNPJ da AGFESC.
3. **O próprio estatuto pede o sistema:** "as despesas, os atendimentos e as metas de cada parceria
   são registrados mensalmente no sistema de controle da UNIAFIBRO-SC" (Art. 11-A § 10, IV).
4. **Controle de parceria da unidade própria** (Art. 11-A § 10): aprovação prévia e por escrito da
   Diretoria com parecer do Conselho Fiscal antes de assinar; conta bancária específica por parceria,
   movimentada por 2 responsáveis; nenhum pagamento em espécie ou sem nota/recibo; exame mensal do
   Conselho Fiscal, com aviso de irregularidade em 5 dias; a Diretoria pode suspender a execução
   financeira da unidade (§ 11).
5. **Dinheiro com origem:** a contribuição da filiada (Art. 15-A) incide só sobre receita privada
   acima do piso; recurso público e doação com destino fixado ficam fora da base (§ 2º), e é vedado
   cobrar percentual sobre recurso público da filiada (Art. 61-A). As contribuições formam o
   **Fundo de Fortalecimento da Rede**, com registro contábil separado (§ 4º). A unidade presta
   contas todo mês, com demonstração separada (Art. 11-A § 7º). Documentos guardados por 10 anos
   (Art. 65).

## Decisão

Três assuntos, três donos — sem módulo novo além dos que já existem ou estão propostos:

| Assunto | Dono | Esta ADR muda |
|---|---|---|
| Rede: quem é quem, cidades e CNPJs | `Modules/Associacoes` (0451) | E1, E2 |
| Projetos e parcerias | `Modules/Captacao` (0452) | E3 |
| Dinheiro | `Modules/Financeiro` (existente) | E4, E5 |

1. **E1 — Tipo de membro e municípios.** `assoc_associacoes` passa a ter `tipo`
   (`filiada` · `unidade_propria` · `grupo_em_formacao`) e cada membro declara os municípios em que
   atua (Art. 11 § 4º), com a forma de presença (sede, filial com CNPJ, núcleo). O painel da rede
   conta por tipo, por município e por CNPJ. A AGFESC entra como `unidade_propria` (Criciúma), não
   como filiada.
2. **E2 — Associado individual só da Uniafibro (emenda à 0451 D3/D5).** Pessoa cadastrada como
   associada só pode estar vinculada a membro do tipo `unidade_propria`. Os associados de uma
   **filiada** são dela (CNPJ próprio, controladora LGPD própria): a Uniafibro guarda deles só
   números agregados — quantidade e atendimentos por semestre (Art. 15-A, I) — como o Observatório
   exige (Art. 58, dado agregado ou anonimizado). Se uma filiada quiser cadastrar os próprios
   associados no sistema, ela vira empresa própria no oimpresso (review trigger).
3. **E3 — Parceria na Captação (complemento à 0452).** O projeto ganha:
   - **executante** (o membro que executa) separado do proponente;
   - **origem do recurso** em cada aporte: `publica` · `privada` · `privada_destino_fixado`;
   - **aprovação prévia da Diretoria e parecer do Conselho Fiscal** registrados (quem, quando,
     documento) antes de o projeto com recurso público passar a `execucao` — o sistema avisa quando
     falta; não bloqueia;
   - **conta bancária específica** da parceria (identificação, nunca credencial);
   - **metas do plano de trabalho** e o registro mensal de **atendimentos e metas realizadas**, ao lado
     das despesas que a 0452 já prevê;
   - **exame mensal do Conselho Fiscal** registrado por mês de execução, com alerta se passar o prazo;
   - comprovante com forma de pagamento; `especie` não é aceita (Art. 11-A § 10, III);
   - **termo de cooperação** ou **termo de atuação em rede** como documento do projeto
     (Art. 71 §§ 1º e 4º), com a taxa de apoio quando houver (Art. 15-A, III).
4. **E4 — Dinheiro no Financeiro (revê 0451 "fora" e 0452 D7).** O dinheiro não fica em tabela
   paralela: usa o `Modules/Financeiro` com o centro de custo e o rateio que já existem. Centro de
   custo por **unidade própria**, por **parceria** e para o **Fundo de Fortalecimento da Rede**;
   cada lançamento leva a origem do recurso (E3). Relatório mensal por unidade com receitas e
   despesas separadas (Art. 11-A § 7º). A integração Captação → Financeiro deixa de ser opcional
   para projeto com recurso público de unidade própria.
5. **E5 — Contribuição da filiada (Art. 15-A).** A filiada envia o demonstrativo do ano anterior
   até 30/04, com receitas separadas por origem; o sistema calcula a contribuição sobre a parte
   privada acima do piso, dentro do teto. Piso, percentual e teto são parâmetros que a Assembleia
   fixa — dado da empresa, não constante no código. Invariante testada: recurso de origem pública
   nunca entra na base (Art. 61-A).

## Fora desta decisão

- Regimento Interno (reserva financeira, cooperação entre filiadas no mesmo município): quando a
  Assembleia aprovar.
- Assinatura conjunta de conta: o banco controla; o sistema só registra quem são os responsáveis.
- Publicação no site das parcerias e certidões (Art. 67): onda própria do `uniafibro.com.br`.
- Eleição, votação e voto ponderado.

## Ondas (até 300 linhas cada)

1. Esta ADR.
2. Associações: `tipo`, municípios de atuação, guarda "associada só em unidade própria", AGFESC como
   unidade própria (ajusta a semente do PR #10891 se ainda aberto).
3. Captação: executante, origem do recurso, aprovações Diretoria/Conselho Fiscal, conta específica.
4. Captação: metas e registro mensal de atendimentos e metas; exame mensal do Conselho Fiscal.
5. Financeiro: centros de custo de unidade, parceria e Fundo; relatório mensal por unidade.
6. Contribuição da filiada (demonstrativo + cálculo) — depois do Regimento e da primeira Assembleia.

## Alternativas consideradas

- **Manter a 0451 como está e tratar dinheiro depois.** O estatuto exige registro mensal no sistema
  desde a primeira parceria de unidade própria; deixar para depois cria lançamento solto que não
  fecha a prestação de contas.
- **Módulo financeiro próprio da rede.** Duplicaria contas, títulos, centro de custo e exportação
  contábil que o `Modules/Financeiro` já tem.
- **Cadastrar os associados das filiadas na Uniafibro.** Faz a Uniafibro tratar dado de saúde de
  pessoas de outra entidade (outro CNPJ, outra controladora) sem base no estatuto.

## Consequências

- Associadas já cadastradas em membro que vire `filiada` precisam de destino antes da onda 2 ir para
  produção (conferir se há dado real; se houver, decisão [W] caso a caso).
- **Conflito de interesse (Art. 48 § 4º):** contratar licença de software de pessoa jurídica ligada ao
  Gerente Executivo exige o rito daquele parágrafo, e é vedado com recurso de parceria pública quando
  o instrumento proibir. O uso do oimpresso pela Uniafibro segue esse rito; o sistema não decide isso.
- Valores de contribuição, piso e teto não entram em commit, PR ou log.
