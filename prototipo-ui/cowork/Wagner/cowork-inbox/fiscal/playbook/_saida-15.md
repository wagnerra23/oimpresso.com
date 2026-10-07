---
sessao: "15"
titulo: Aceite do contador — revisão por versão (15a), link com código (15b), cadastro e papel (15c)
autor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main (15a = #9003 mergeado · 15b = #9015 · 15c = este PR)
prefixo_tocado: Modules/NfeBrasil (migrações 2026_10_07_000005/6/7 · Models NfeRevisaoContador, NfeContadorLink, NfeContador · RevisaoContadorService · ContadorService · RevisaoContadorController · RevisaoContadorLinkController · RevisaoContadorMail · view contador/revisao · Routes) · Modules/Fiscal (ConfigController · Routes) · Fiscal/Config.tsx · Tributacao/Index.casos.md · Fiscal/Config.casos.md · Config.charter.md · AceiteContadorTest · RevisaoContadorLinkTest · ContadorCadastroTest · SCOPE · SUPERFICIE · catalog.json · dominio fiscal-faturamento · nfebrasil-pest.yml · este arquivo
---
# _saida-15 · Aceite do contador

**Resposta curta:** toda versão nova de regra (manual, CSV, Jana) nasce com revisão **pendente** e o
de → para. Aceitar exige a permissão própria `nfe.tributacao.aceitar`, checada direto no Spatie: o
dono da empresa (`Admin#`) **não** aceita pelo contador. Falta de aceite não bloqueia emissão. O
contador sem conta revisa por um **link assinado de 14 dias** preso à empresa, que só abre com um
**código de 6 dígitos** mandado ao e-mail dele. A empresa cadastra o contador em `/fiscal/config`, e
quem quiser ter usuário recebe o papel **Contador**.

Fatiado em 3 PRs, aprovado pelo gerente da fila: 15a (#9003) · 15b (#9015) · 15c.

## 1 · Os 3 caminhos da D-CONTADOR

| caminho | onde ficou | UC |
|---|---|---|
| 1 · link de revisão, sem conta | 15b: `POST /nfe-brasil/tributacao/revisoes/link` (empresa, `nfe.tributacao.manage`) → e-mail com URL assinada · página pública `nfe-brasil/contador/revisao/{business}/{link}` | UC-NFTR-22 · 23 |
| 2 · usuário "Contador" | 15c: papel `Contador#{business}` com `nfe.tributacao.manage` + `nfe.tributacao.aceitar` + `fiscal.access` + `fiscal.sped.export`, criado ao salvar o cadastro | UC-FCFG-09 |
| 3 · planilha | 15b: "Baixar regras (CSV)" no cabeçalho do `ImportRegrasCsvService`; volta pelo Import atual com origem `csv` | UC-NFTR-23 · UC-NFTR-19 |

Os números provisórios do playbook (UC-NFTR-12…12e) viraram UC-NFTR-19…23 e UC-FCFG-08/09, os
próximos livres em cada `casos.md`.

## 2 · Decisões de técnica

| decisão | por quê |
|---|---|
| aceite numa tabela própria (`nfe_revisoes_contador`), não em coluna da regra | a regra é append-only (thread 07); aceitar não é editar |
| `hasPermissionTo` em vez de `can()` | o `Gate::before` libera o `Admin#` em qualquer `can()`, e o dono aceitaria pelo contador |
| página do link = Blade autônoma | o contador não tem conta: sem shell do ERP. Aprovado pelo gerente da fila |
| ações do link sem assinatura, presas à sessão que o código liberou | a assinatura cobre um path; o código é a prova de que é o contador |
| código só como hash, apagado ao ser usado | uso único |
| papel criado só se não existir | o ajuste que o administrador fizer no papel não é desfeito por um novo salvar |
| contador em tabela própria (`nfe_contadores`), não coluna em `nfe_business_configs` | nenhuma tabela existente muda; rollback limpo |

## 3 · Prova (CT 100, worktree isolado por fatia, tenant 98 e vizinho 99, migrações aplicadas e revertidas)

| fatia | rodada | resultado |
|---|---|---|
| 15a | `AceiteContadorTest` | 3 passed (UC-NFTR-19 · 20 · 21) |
| 15b | `RevisaoContadorLinkTest` | **2 passed · 63 assertions** |
| 15b | mutação: sem `business_id` na revisão · código reutilizável · rota sem `signed` · sem bloqueio por tentativas · CSV sem `business_id` · CSV sem sessão | **6 de 6 caem** |
| 15c | `ContadorCadastroTest` + `ConfigControllerTest` + `GatesPermissaoFiscalTest` + `AceiteContadorTest` | **20 passed · 133 assertions** |
| 15c | mutação: cadastro sem `business_id` · papel com `sell.view` · papel sobrescrito · salvar sem gate · leitura sem `business_id` | **5 de 5 caem** |
| 15b · 15c | PHPStan nos arquivos de produção | sem erros |
| — | staging depois | tabelas e entradas `2026_10_07_%` em `migrations` = 0 · dados de teste = 0 |

Mutação 2 da 15c (papel com `sell.view`): o assert de lista exata de permissões **não** pega, porque
lê a mesma constante. Quem pega é o controle de comportamento (`can('sell.view')` falso).

`TributacaoGatesContratoTest` tem 3 vermelhos no staging (UC-NFRF-01/04 · UC-NFIM-03), de ambiente
(usuário Admin#1), que caem igual no `main`.

## 4 · Fica para depois

- **Ligar o link ao cadastro.** Hoje o envio do link (15b) recebe nome/e-mail/CRC no pedido; com o
  cadastro (15c) no ar, o botão "Enviar link ao contador" pode usar o cadastrado por padrão. Também
  não há tela para disparar o envio: só o endpoint.
- **Envio automático quando houver pendência.** O playbook fala em e-mail "ao haver pendência"; o
  envio ainda é manual, por pedido da empresa.
- **Saúde fiscal mostrando "ajuste pedido" e "sem aceite".** É a thread 14.
- **Drawer "Enviar p/ contabilidade" do cockpit** segue mockado (`CockpitController:232`); é a thread 28.
- **Envio de documentos ao contador** (cópia de toda nota ou digest) segue sem decisão.
- Lacunas vistas no caminho, de outras threads: o `ImportRegrasCsvService` edita a regra existente no
  lugar (sem versionar) e acha a regra por `first()` sem filtrar vigência; o SPED
  (`SpedIcmsIpiGeneratorService:300`) recalcula notas antigas sem passar a data delas.
