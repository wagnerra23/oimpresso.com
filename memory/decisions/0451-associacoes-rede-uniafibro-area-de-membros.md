---
slug: 0451-associacoes-rede-uniafibro-area-de-membros
number: 451
title: "Associações: área de membros da rede Uniafibro — uma empresa só, associações e associadas dentro dela"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-13"
module: null
quarter: 2026-Q4
tags: [associacoes, uniafibro, fibromialgia, lgpd, dado-sensivel, multi-tenant, fisio, app]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0437-modulo-fisio-vertical-fisioterapia
pii: false
review_triggers:
  - "uma associação-membro pedir para ter o próprio sistema, separado da Uniafibro"
  - "o estatuto exigir CPF da associada (eleição, cota, prestação de contas)"
  - "a rede passar a cobrar mensalidade ou repassar dinheiro entre associações"
  - "a área da associada sair do app da Play e virar app próprio"
---

# ADR 0451 · Associações: área de membros da rede Uniafibro

> **Status `proposto`.** [W] decidiu o modelo de empresa em 2026-11-13, textual: *"Sim, 1 empresa"*.
> As decisões D4 e D5 seguem a recomendação feita a ele no mesmo dia e ainda não foram
> respondidas uma a uma; estão marcadas **(a confirmar)**. O merge desta ADR é o ato de ratificação.

## Contexto

A Uniafibro-SC (União das Associações de Fibromiálgicos de Santa Catarina, nome do estatuto)
une associações de cidades catarinenses. Em 2026-11-13 ela está **em formação**: o registro em
cartório está em andamento (decisão [W] no site, PR #10867), então ainda não tem CNPJ próprio. Hoje são AGFESC (Criciúma) e AFIBROMAR (Maravilha). Cada associação mantém nome,
diretoria e CNPJ; a Uniafibro ajuda com projetos, documentação e voz junto ao Estado. O estatuto
da rede exige que a associação avise mudança de diretoria em 30 dias.

O que existe no oimpresso em 2026-11-13 (medido em `origin/main` `5869c93093`):

- o site `uniafibro.com.br`, HTML estático servido por rota de domínio (`routes/web.php`), sem banco;
- o app "Uniafibro" na Google Play (`com.oimpresso.fisio`), que é o invólucro TWA do portal do
  paciente do `Modules/Fisio`: abre `oimpresso.com/fisio/app` e, pelo link da clínica,
  `/fisio/p/{token}` com PIN (repositório `oimpresso-app`, pasta `fisio-twa`, PR #111);
- **nenhuma** tabela, entidade ou módulo de associação ou associada (`git grep` em entidades,
  migrations e `SCOPE.md`: zero).

## Decisão

1. **D1 — A Uniafibro é uma empresa (business) só.** As associações-membro são registros dentro
   dela, não empresas próprias. Todo dado novo leva `business_id` com escopo global (ADR 0093);
   nenhuma tela olha para fora da empresa. A visão consolidada da rede é a própria empresa, sem
   consulta entre empresas.
2. **D2 — Módulo novo `Modules/Associacoes`, liberado pelo pacote `associacoes_module`** no
   superadmin e pela permissão `associacoes.access`. O nome é genérico de propósito: o mesmo
   desenho serve a outra rede de associações; a Uniafibro é a primeira empresa a usar.
3. **D3 — Dados.**
   - `associacoes`: nome, cidade, região, CNPJ, representante (contato do núcleo), situação e a
     data da última atualização de diretoria.
   - `associacao_documentos`: tipo (estatuto, ata da diretoria, certidão), arquivo guardado pelo
     `Modules/Arquivos`, validade e data de envio. Documento não se apaga: sai da lista com motivo.
   - `associadas`: associação, contato do núcleo, data de entrada e situação (ativa ou desligada).
   - `associada_consentimentos`: finalidade, versão e `revoked_at`, sem DELETE — o mesmo desenho
     dos consentimentos do Fisio (`Modules/Fisio/Services/Consentimentos.php`).
4. **D4 — Quem acessa (a confirmar).** A diretoria da Uniafibro vê a rede inteira. O
   representante de cada associação entra no sistema e vê **só** a associação dele (permissão mais
   filtro por associação, com teste). A associada não entra no sistema: vê o próprio cadastro pelo
   app (D6).
5. **D5 — Dados da associada (a confirmar).** Nome, celular e cidade. **Sem CPF** enquanto o
   estatuto não exigir. "Pessoa com fibromialgia" é dado de saúde, sensível pela LGPD (art. 11):
   o cadastro exige consentimento explícito por finalidade — ser associada, receber mensagens
   pelo WhatsApp, ter o nome visível para a Uniafibro. Diagnóstico, laudo e CID não entram neste
   módulo. Nenhum dado pessoal em log, commit ou PR.
6. **D6 — Área da associada no app que já existe.** A associada acessa pelo mesmo app da Play,
   com link + PIN no padrão do portal do Fisio (token assinado, PIN com trava, aparelho lembrado),
   numa rota pública própria do módulo no mesmo domínio que o app já abre. Não há conta nem senha.
   Não se publica app novo nem se mexe no Play Console por causa deste módulo.
7. **D7 — Prazo de 30 dias.** Ao registrar mudança de diretoria, a associação fica com pendência
   até a ata nova chegar; passados 30 dias, a pendência aparece como atrasada no painel da rede.
   O sistema avisa; não bloqueia nada.

## Fora desta decisão

- Mensalidade, repasse de dinheiro, eleição e votação.
- Ligar a associada ao paciente do Fisio: são empresas diferentes (a clínica é outra empresa), e
  essa ligação seria consulta entre empresas.
- Mudar o pacote, o nome ou o endereço do app na Play.

## Ondas (até 300 linhas cada)

1. Esta ADR.
2. Esqueleto do módulo, migrations, entidades e testes de isolamento (entre empresas e entre
   associações); SCOPE, SPEC e BRIEFING.
3. Associações e documentos, com a pendência de 30 dias.
4. Associadas e consentimentos, com dado pessoal mascarado.
5. Painel da rede.
6. Área da associada no app.

## Alternativas consideradas

- **Cada associação uma empresa.** Isola por construção, mas a visão da rede passaria a ser
  consulta entre empresas, que a ADR 0093 proíbe sem desenho próprio. Recusada por [W] em 2026-11-13.
- **Estender o `Modules/Fisio`.** O Fisio é por clínica e trata prontuário; associação não é
  clínica, e misturar os dois levaria dado clínico para onde ele não precisa estar.
- **Guardar associadas só como contatos do núcleo, com etiqueta.** Não dá conta de consentimento
  por finalidade nem de situação na associação.

## Consequências

- Mais um módulo com pacote próprio; empresas sem o pacote não veem nada.
- A Uniafibro precisa de uma empresa criada no superadmin antes da onda 2 ir para produção. Enquanto
  o registro não sair, a empresa existe sem CNPJ; nenhuma tela diz que a entidade já está formalizada.
