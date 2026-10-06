# Fiscal › Tributação — casos de uso

Tela: `fiscal-tributacao` (`fiscal-tributacao.jsx`, `window.FxTributacaoPage`).
Âncora no `main` (lida em 2026-10-06 @`3de6bdc5bf8b`): **a tela já existe em produção** — `/nfe-brasil/tributacao` = `resources/js/Pages/NfeBrasil/Tributacao/{Index,RegraForm,ConfigDefault,ImportCsv}.tsx`, cada uma com charter e casos (UC-NFTR-*). Motor: `Modules/NfeBrasil/Services/MotorTributarioService.php`. **Estes UC-TRB estendem os UC-NFTR, não os substituem.** Quatro propostas ferem Non-Goals aprovados (simulador, tabela UF, IA, histórico/vigência) e dependem de decisão [W]: playbook `cowork-inbox/fiscal/playbook/00-INDICE.md` §4.
Legenda: **[existe]** = o motor no `main` já faz · **[proposta]** = falta no `main`, vira pedido pro Code.

## Onde cada caso vira contrato no `main` (fonte executável = as threads)
Este arquivo descreve **o que** e **por quê**. O aceite executável (Dado/Quando/Então + teste) está **nas threads** de `cowork-inbox/fiscal/playbook/`, no formato dos casos.md do repo, com destino e número provisório:

| UC-TRB | vira no `main` | thread |
|---|---|---|
| 10 · IBS/CBS | UC-NFRF-05 · UC-NFRF-06 · UC-NFRF-07 | 04 · 05 |
| 19 · ICMS-ST | R-NFE-015 · R-NFE-016 | 06 |
| 09 · DIFAL/UF | R-NFE-017 · R-NFE-021 | 06 · 09 |
| 07 / 15 · vigência e operação | R-NFE-018 · R-NFE-019 · R-NFE-020 | 07 |
| 06 · simulador | UC-NFTR-08 | 08 |
| 14 · IA (Jana) | UC-NFTR-09 · 09b · 09c · UC-NFTR-10 · 10b | 10 |
| 30 · saúde fiscal · 16 · pronto pra emitir | UC-NFTR-11 · UC-NFTR-13 | 14 |
| 31 · aceite do contador | UC-NFTR-12 | 15 |
| 33 · bloqueio com saída · 34 · conserto cadastral | R-NFE-022 · R-NFE-022b | 16 |
| 35 · itens reais | R-NFE-023 · R-NFE-024 · R-NFE-025 | 17 |
| (bugs já catalogados) | UC-NFRF-04 · UC-NFIM-04 ficam verdes | 18 · 19 |
| 13 · devolução | R-NFE-026 · R-NFE-027 | 20 |
| 18 · entrada XML · 12 · importação | ADR primeiro (inclui câmbio da DI) | 11 |
| 11 / 17 · NFS-e | US-NFSE-007 (fora deste playbook) | — |
| 32 · medir chamados | aferição (13) → thread própria depois | 13 |
| 36 · configurar pelo certificado | UC-NFTR-14..17 · R-NFE-028 | 21 · 22 |
| 20 · retenções · 22 · Fator R · 26 · benefício · 27 · filial | R-NFSE-xx1/xx2 · R-NFE-029 · R-NFE-030 · R-NFE-031/032 | 23 · 24 · 25 · 26 |
| 21 · 23–25 · 28–29 · faltam | **backlog, sem aceite** — viram UC quando ganharem thread | — |

## Atores
Contador (define as regras) · Eliana (financeiro/fiscal, opera) · Wagner (aprova) · Larissa (balcão, só sente o efeito na nota).

## UC-TRB-01 · Configurar o padrão da empresa [existe]
Eliana informa regime (CRT), CFOP interno/interestadual, CSOSN ou CST, alíquotas e IBS/CBS. Sem padrão, emitir falha com `TributacaoNaoConfiguradaException`.
Aceite: depois de salvar, o simulador mostra "N4 usada" pra produto com NCM e sem regra.

## UC-TRB-02 · Cadastrar regra por NCM [existe]
NCM (8 dígitos), UF origem, UF destino opcional, CFOP, CSOSN **ou** CST (nunca os dois), ICMS/PIS/COFINS/IPI (decimal), MVA e FCP.
Aceite: se UF destino ficar vazia, a regra é N3; se for preenchida, N2.

## UC-TRB-03 · Importar regras por CSV [existe]
O contador manda uma planilha; Eliana importa. Linha inválida é rejeitada com o motivo e as outras entram.

## UC-TRB-04 · Vincular produto [existe]
O vínculo é automático pelo NCM do produto: não se liga produto a regra um por um. Exceção (N1) só via `fiscal_rule_override_id`.
Aceite: na aba "Vínculo com produtos", cada produto mostra a situação (regra pelo NCM · cai no padrão · sem NCM · exceção · serviço).

## UC-TRB-05 · Produto sem NCM bloqueia a nota [existe]
Aceite: o simulador mostra "Emissão bloqueada" com os dois caminhos: cadastrar NCM ou criar exceção.

## UC-TRB-06 · Simular antes de emitir [proposta: tela]
Escolher produto, quantidade, UF destino, tipo de destinatário e regime. A tela mostra o rastro da cascata (qual nível foi usado e por quê), o CFOP e os tributos com base, alíquota e valor.
Aceite: o resultado vem da mesma função do motor (`calcular`), não de uma cópia no front.

## UC-TRB-07 · Vigência da regra [proposta]
Regra com "válida de / até". Editar cria uma nova versão e encerra a anterior na véspera. A nota usa a regra vigente na data da emissão.
Motivo: alíquotas mudam (Reforma 2026→2033, convênios de ICMS, ISS municipal) e a nota antiga precisa continuar explicável.

## UC-TRB-08 · Operação na regra [proposta]
Chave da regra ganha `operacao` (venda · devolução · importação · remessa). Hoje o motor busca só por NCM + UF.

## UC-TRB-09 · ICMS por UF [proposta]
Tabela versionada com ICMS interno, FCP e interestadual (7%/12%; 4% pra importado). DIFAL = interno do destino − interestadual, quando o destinatário não tem IE. Se a regra não traz alíquota, vale a tabela.
Gap no `main`: MVA e FCP são gravados, mas `aplicarRegra` não os usa no cálculo (ST e DIFAL não saem).

## UC-TRB-10 · Reforma tributária IBS/CBS [parcial]
O motor já lê `c_class_trib`, `cst_ibs`, `cst_cbs`, `aliquota_ibs` e `aliquota_cbs`.
Gap: `UpsertRegraTributariaRequest` não valida nenhum desses campos, então a tela não consegue gravá-los.
Aceite: regra sem IBS/CBS aparece com a marca "vazio" e entra nas sugestões.

## UC-TRB-11 · Serviços e NFS-e por CNAE [proposta]
Cada CNAE da empresa aponta para um item da LC 116 e o código de tributação nacional (6 dígitos); o ISS é por município, com retenções (ISS retido, IRRF). NFS-e com item fora dos CNAEs é bloqueada. O anexo do Simples e o Fator R vêm do CNAE.

## UC-TRB-12 · Importação com dólar [proposta]
Valor aduaneiro = (FOB + frete + seguro) × câmbio da data de registro da DI/DUIMP. Depois vêm II, IPI, PIS/COFINS-importação e ICMS calculado "por dentro". O câmbio fica gravado na nota de entrada (CFOP 3102); variação posterior vai pro financeiro, nunca recalcula o imposto.

## UC-TRB-13 · Devolução [proposta]
O CFOP espelho vem de uma tabela (5102→1202, 6102→2202, 5405→1411, 1102→5202…). Alíquotas e base são copiadas da nota referenciada (refNFe), não da regra vigente. Devolução parcial é proporcional à quantidade. Finalidade da NF-e = 4.

## UC-TRB-14 · Sugestões da IA [proposta]
A IA sugere NCM a partir da descrição, natureza (serviço × mercadoria, por exemplo banner personalizado → LC 24.01), preenchimento de IBS/CBS e inconsistências (NCM com regra só pra outra UF).
Regras fixas: a IA **nunca aplica sozinha**; mostra motivo e confiança; aceitar exige `nfe.tributacao.manage`, gera nova versão e vai pra auditoria; sugestão de natureza sempre pede o contador.

## UC-TRB-15 · Operação como porta de entrada [proposta]
A configuração começa pela natureza de operação (venda a contribuinte · venda a consumidor final · produção própria · remessa · devolução · importação · serviço), não pelo NCM: padrão Bling, Tiny/Olist e Conta Azul (a Omie chama de "cenário fiscal"). Cada operação tem uma regra geral, que é o N4 e substitui o padrão único da empresa. As exceções (produto · NCM · NCM+UF) ficam dentro da operação. CFOP com "?" vira 5/6/7 conforme o destino.

## UC-TRB-16 · Pronto pra emitir [proposta]
Um checklist no topo: certificado lido (regime, UF e CNAEs preenchidos sozinhos, como na Olist) · operações sugeridas · produtos com NCM · serviços com código municipal (evita a rejeição E0312) · contador revisou (convite com acesso só de revisão). Cada pendência leva direto à aba que resolve.

## UC-TRB-17 · NFS-e nacional completa [proposta]
Cada serviço tem item LC 116, código nacional, código municipal, NBS e indicador de operação. Faltando o código municipal onde a prefeitura exige, o serviço é marcado antes de emitir.

## UC-TRB-18 · Entrada por XML de compra [proposta · tela: aba Entradas]
Ler o XML do fornecedor (Manifesto DF-e ou upload), converter o CFOP (5→1, 6→2; 5405→1403), lembrar o de-para entre o item do fornecedor e o produto, avisar quando o NCM diverge (sem trocar sozinho) e marcar CST 060 como ST retida, pro produto sair com CSOSN 500. No Simples o ICMS destacado vai pro custo, sem crédito. Hoje o contador de entradas da produção é o literal `0`.

## UC-TRB-19 · Cálculo de ICMS-ST [proposta · tela: simulador]
Base ST = (valor + IPI) × (1 + MVA); ICMS-ST = base ST × alíquota interna do destino − ICMS próprio. Se o produto foi comprado com ST retida (CSOSN 500), a venda não recalcula. Hoje o motor grava `mva`, mas não calcula.

## UC-TRB-20 · Retenções federais em serviço [falta]
PIS/COFINS/CSLL 4,65%, IRRF 1,5% e INSS 11% por tipo de tomador e valor mínimo, destacados na NFS-e e lançados no financeiro como valor líquido.

## UC-TRB-21 · NFC-e no PDV [falta]
O caixa usa a operação "Venda a consumidor final". Valida antes da venda o NCM, os CFOPs permitidos na NFC-e (5101/5102/5405…) e o gate de emissão automática que já existe (UC-NFTR-01).

## UC-TRB-22 · Fator R automático [falta]
Folha ÷ receita bruta dos últimos 12 meses, com dado do Ponto/Financeiro. Decide entre Anexo III e V e avisa quando cruza 28%.

## UC-TRB-23 · Remessa e retorno [falta]
Instalação, conserto e demonstração: nota de remessa (5949/5915), saldo do que ainda não voltou e nota de retorno vinculada.

## UC-TRB-24 · Bonificação, entrega futura e nota complementar [falta]
Operações próprias, cada uma com a sua regra geral (5910 · 5922/5117 · finalidade 2).

## UC-TRB-25 · Exportação [falta]
CFOP 7xxx, sem ICMS/IPI, com câmbio da data de embarque e campos de exportação no XML.

## UC-TRB-26 · Benefício fiscal por estado [falta]
Redução de base, isenção ou diferimento, com o código de benefício (cBenef) que a UF exige no XML e a lei citada literal na regra.

## UC-TRB-27 · Filial em outro estado [falta]
A UF de origem vem da filial emissora, não é fixa. As regras são por empresa e por filial.

## UC-TRB-28 · Troca de regime [falta]
Passar de Simples pra Presumido numa data: todas as operações ganham nova versão vigente a partir dela, e o template novo é aplicado sem apagar as exceções.

## UC-TRB-29 · Auditoria comparativa [falta]
Para uma nota emitida, mostrar a regra e a versão usadas e o que daria com a regra de hoje. Só leitura, a partir do `activity_log` que já existe.

## UC-TRB-30 · Saúde fiscal [decidido · tela: aba Saúde fiscal]
Uma lista do que vai virar rejeição: produto sem NCM · serviço sem código municipal · regra sem cClassTrib · operação sem aceite · certificado perto de vencer · rejeição de causa cadastral. Checada a cada cadastro e antes de emitir, e cada item tem a ação que resolve.

## UC-TRB-31 · Aceite do contador [decidido]
Cada operação ou exceção guarda quem aceitou e quando (`activity('nfe.tributacao')` · `aceite.registrado`). Editar cria uma versão nova, e o aceite anterior não vale pra ela. O contador entra com acesso só de revisão.

## UC-TRB-32 · Medir chamados por motivo [decidido · vem antes de automatizar]
Cada chamado fiscal ganha um motivo: rejeição cadastral · dúvida de imposto · configuração · certificado · outro. Sem essa contagem nenhuma automação nova entra.

## UC-TRB-33 · Bloqueio com saída de 1 clique [decidido]
O bloqueio nunca é beco sem saída. Produto sem NCM: aceitar a sugestão ou usar o **NCM padrão da empresa**, que já existe (`tributacao_default.ncm_default` → `business.ncm_padrao`, `NfeService`), e marcar pra revisão. Emitir sem NCM **não** é saída, porque NF-e de mercadoria exige NCM. Defeito conhecido a fechar junto: `ConfigDefaultController` grava `00000000` como padrão e os templates não gravam `ncm_default` (auditoria 2026-05, bug #3).

## UC-TRB-34 · Conserto de rejeição cadastral [decidido]
Quando a causa é cadastro (cStat 110 · 539), a tela propõe a correção e reenvia na mesma ação. Só para causas cadastrais com receita determinística; nada que mude o imposto.

## UC-TRB-35 · Nota com os itens reais [falta · pré-requisito de quase tudo]
Hoje a NFC-e sai com **um item genérico** ("Venda PDV #X", NCM padrão da empresa) e a NF-e de cobrança, com um item "Cobrança recorrente" (`NfeService::emitirParaTransaction` / `emitirParaInvoice`, "fase 2A"). Enquanto for assim, regra por NCM, exceção por produto, ST e saúde fiscal por produto **não chegam na nota**. A fase 2B lê `transaction_sell_lines`: um item por linha, com o NCM do produto (ou o padrão marcado pra revisão), e o motor chamado por item. PIS/COFINS deixam de ser CST `07` fixo, `cod_municipio` deixa de ser `9999999` e o tipo de destinatário deixa de ser sempre `9`.

## UC-TRB-36 · Configurar pelo certificado [decidido · tela: "Configurar pelo certificado"]
Depois de enviar o certificado, o sistema consulta o CNPJ da própria empresa em paralelo: **SEFAZ** com o próprio certificado (IE, situação, regime de apuração, endereço), no mesmo serviço e com a mesma regra de autoridade da ADR 0186, e **BrasilAPI** (CNAEs, Simples). Mostra cada dado com a fonte. Fontes divergentes **não** são resolvidas sozinhas. Sugere o template que casa regime + UF + CNAE, mostra os valores e **só aplica com clique**, porque o charter da Index proíbe aplicar sem clique. O NCM padrão é obrigatório e não aceita `00000000`. As regras por NCM são preservadas (UC-NFTR-03). Em UF fora das 6 suportadas, IE e regime são digitados.

## Fora de escopo desta tela
Cálculo do DAS mensal · SPED (aba própria) · cadastro do certificado (aba Certificado).
