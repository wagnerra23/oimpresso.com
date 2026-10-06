---
sessao: "15"
titulo: Aceite do contador — link de revisão (padrão), usuário contador (opcional), planilha (volta pelo Import CSV)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 24561f0da83b (2026-10-06)
prefixo: Modules/NfeBrasil/Database/Migrations/<nova: nfe_revisoes_contador + aceito_por/aceito_em nas versões> · Models · Http/Controllers/<RevisaoContadorController novo> · rota assinada · permissão nfe.tributacao.aceitar · Tests/Feature/<novos>
nao_toca: MotorTributarioService.php · dados de cliente/venda/financeiro (o contador nunca vê)
depende: 07 (versões de regra). D-CONTADOR respondida [W] 2026-10-06 (sim, como abaixo)
decisao: _DECISOES-W-2026-10-06.md (D-SUPORTE) + D-CONTADOR
implementa: UC-NFTR-12 · UC-NFTR-12b · UC-NFTR-12c · UC-NFTR-12d · UC-NFTR-12e
us: US-NFE-009 (contador terceiro com role limitada · link read-only 14 dias) · Fiscal/ConfigController `envioDocumentos` (o e-mail do contador hoje é placeholder em CockpitController:232)
---
# 15 · Aceite do contador

## O que o aceite É (e não é)
- **É** o registro de que o responsável técnico conferiu **uma versão** de regra (operação, exceção ou template aplicado): quem, quando, de onde, e o que estava escrito.
- **Não bloqueia emissão.** Sem aceite, a regra vale e aparece na Saúde fiscal como "sem aceite". Bloquear geraria chamado, que é o contrário de D-SUPORTE.
- **Não é configuração.** Quem configura é a empresa (templates, telas atuais) **ou** o contador, se ele quiser ter usuário. O aceite é outra coisa.

## O que gera revisão
Toda versão nova: template aplicado (21) · exceção criada ou editada · regra geral editada · sugestão da Jana aceita (10) · lote do Import CSV aplicado. Cada uma entra **pendente** na revisão do contador. Editar algo já aceito cria versão nova pendente; a antiga mantém o aceite dela.

## Os 3 caminhos de acesso (D-CONTADOR · decidido [W] 2026-10-06)
1. **Link de revisão — padrão, sem conta.** A empresa cadastra o contador (nome, e-mail, CRC) em `/fiscal/config` › Envio de documentos (o campo já existe; hoje é placeholder). Ao haver pendência, ele recebe por e-mail um **link assinado** (rota `signed`, 14 dias, como a US-NFE-009), presa a **um** business. Ao abrir, recebe um **código de 6 dígitos no mesmo e-mail** (vale 15 min), porque link encaminhado não pode aceitar em nome dele. A página mostra só a lista de versões pendentes, cada uma com **o que mudou (de → para)**, quem mudou e por quê (origem: template, manual, Jana, CSV). Por item: **Aceitar** ou **Pedir ajuste** (comentário obrigatório). Nada além de regras fiscais: sem cliente, sem venda, sem valor.
2. **Usuário "Contador" — opcional**, para quem quer configurar por conta própria. É um papel com `nfe.tributacao.manage` + `nfe.tributacao.aceitar` + leitura do cockpit fiscal e do SPED. Sem vendas, financeiro, cadastro de cliente ou configuração da empresa. O aceite dele vale do mesmo jeito.
3. **Planilha — volta pelo que já existe.** A página de revisão oferece "Baixar regras (CSV)" no formato do Import CSV. O contador devolve o arquivo **para a empresa**, que importa pelo fluxo atual (preview → aplicar, UC-NFIM-01..04). O lote entra com origem "planilha do contador" e pede aceite em **um clique** dele, no próximo link.

Quem **não** pode aceitar: o dono da empresa em nome do contador. `nfe.tributacao.aceitar` é permissão separada e não vem no papel de administrador por padrão.

## Casos de uso que esta thread implementa
Formato do `main`. **Números provisórios:** confirmar no turno.

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| UC-NFTR-12 | Aceite é por versão e exige permissão própria | `must` `[T0]` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-12b | Link de revisão só abre com código do e-mail, expira e serve a um business | `must` `[T0]` | `Index.casos.md` |
| UC-NFTR-12c | A revisão mostra só regras fiscais, com de → para | `must` `[T0]` | `Index.casos.md` |
| UC-NFTR-12d | Pedir ajuste exige comentário e vira pendência na Saúde fiscal | `must` `[fiscal]` | `Index.casos.md` |
| UC-NFTR-12e | Falta de aceite não bloqueia emissão | `must` `[fiscal]` | `Index.casos.md` |

### UC-NFTR-12 · Aceite é por versão e exige permissão própria · `must` `[T0]` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** contador (link ou usuário)
- **Aceite:** Dado uma versão pendente · Quando um usuário **sem** `nfe.tributacao.aceitar` (incluindo o admin da empresa) aceita → 403. Com a permissão, ou pelo link validado → grava `aceito_por` (nome + CRC + e-mail), `aceito_em`, IP e `activity("aceite.registrado")`. Quando a regra é editada · Então a versão nova nasce pendente e a antiga mantém o aceite. Controle positivo: o papel Contador não acessa venda nem financeiro.
- **Teste:** `AceiteContadorTest` — `UC-NFTR-12 · aceite por versão, permissão própria`
- **Contrato:** D-SUPORTE · US-NFE-009
- **Regressão que defende:** dono "aceitando" pelo contador; aceite antigo valendo para regra que mudou.

### UC-NFTR-12b · Link de revisão só abre com código do e-mail, expira e serve a um business · `must` `[T0]`
- **Destino:** `Index.casos.md`
- **Persona:** alguém que recebeu o link encaminhado
- **Aceite:** Dado link assinado válido · Quando abro sem o código → só a tela do código. Código errado 5× → bloqueia o link. Link com mais de 14 dias, ou com assinatura alterada → 403. Trocar o business no link → 403. Controle positivo: link + código certo abre a lista do business dele.
- **Teste:** `RevisaoContadorLinkTest` — `UC-NFTR-12b · link assinado + código, 14 dias, um business`
- **Contrato:** ADR 0093 · US-NFE-009 (14 dias)
- **Regressão que defende:** link encaminhado aceitando em nome do contador; link que abre outra empresa.

### UC-NFTR-12c · A revisão mostra só regras fiscais, com de → para · `must` `[T0]`
- **Destino:** `Index.casos.md`
- **Persona:** contador
- **Aceite:** Dado versões pendentes · Então cada item mostra operação/NCM/UF, o campo que mudou (valor antigo → novo), autor, data e origem (template · manual · Jana · CSV). A resposta **não** contém cliente, venda, valor de nota nem CPF/CNPJ de terceiros. Controle positivo: "Baixar regras (CSV)" sai no formato das 10 colunas do Import CSV.
- **Teste:** `RevisaoContadorLinkTest` — `UC-NFTR-12c · só regras fiscais, com diff`
- **Contrato:** LGPD minimização · `ImportRegrasCsvService::COLUNAS_OBRIGATORIAS`
- **Regressão que defende:** página do contador vazando dado de negócio.

### UC-NFTR-12d · Pedir ajuste exige comentário e vira pendência na Saúde fiscal · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** contador que discorda
- **Aceite:** Dado item pendente · Quando peço ajuste sem comentário → 422. Com comentário → o item fica "ajuste pedido", aparece na Saúde fiscal com o texto do contador e a empresa é avisada. Controle positivo: depois da correção (versão nova), o item volta pendente pro contador.
- **Teste:** `AceiteContadorTest` — `UC-NFTR-12d · pedir ajuste com comentário`
- **Contrato:** UC-NFTR-11 (saúde fiscal)
- **Regressão que defende:** discordância do contador que se perde no e-mail.

### UC-NFTR-12e · Falta de aceite não bloqueia emissão · `must` `[fiscal]`
- **Destino:** `Index.casos.md`
- **Persona:** Larissa vendendo com regra ainda não revisada
- **Aceite:** Dado regra vigente sem aceite · Quando emito → emite normalmente e a Saúde fiscal mostra "sem aceite". Controle positivo: com aceite, o aviso some.
- **Teste:** `AceiteContadorTest` — `UC-NFTR-12e · sem aceite emite normal`
- **Contrato:** D-SUPORTE (bloqueio vira chamado)
- **Regressão que defende:** um gate de aceite parando o balcão.

## Prova
Os testes verdes · `TributacaoGatesContratoTest` segue verde · nenhuma rota do link alcança tabela fora de regras fiscais (teste de resposta).

Terminou: `_saida-15.md`. Pare.
