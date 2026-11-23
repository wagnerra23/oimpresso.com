---
title: "NfeBrasil — Runbook da tela Tributação (/nfe-brasil/tributacao)"
module: NfeBrasil
tela: NfeBrasil/Tributacao/Index
owner: W
status: rascunho
last_validated: "2026-10-07"
preconditions:
  - "permissão nfe.tributacao.manage para as mutações e para a leitura pelo certificado"
  - "charter resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md lido (status draft)"
  - "alvo de forma = prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx (D-ANCORA, playbook Fiscal)"
steps:
  - "ler charter + Index.casos.md (UC-NFTR-01..18)"
  - "resolver a âncora com node scripts/design/ancora.mjs NfeBrasil/Tributacao/Index"
  - "mudança de comportamento entra com UC + teste que o cita (casos-gate)"
  - "Pest no CT 100; e2e na lane e2e-gate.yml"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Tributação (`/nfe-brasil/tributacao`)

> **Por que existe:** o hook `block-mwart-violation` exige um RUNBOOK por tela antes de editar o
> `.tsx` (ADR 0104 §F1). A tela já está em produção desde 2026-05-10; este arquivo nasceu na
> thread 22 do playbook Fiscal (2026-10-07) para registrar como mexer nela, não para replanejá-la.

## Onde está cada coisa

| Peça | Arquivo |
|---|---|
| Página | `resources/js/Pages/NfeBrasil/Tributacao/Index.tsx` |
| Drawer "Configurar pelo certificado" | `resources/js/Pages/NfeBrasil/Tributacao/_components/ConfigurarPeloCertificado.tsx` |
| Lei / contrato | `Index.charter.md` · `Index.casos.md` (UC-NFTR-*) |
| Controller | `Modules/NfeBrasil/Http/Controllers/TributacaoController.php` (index, aplicarTemplate, toggleAutoEmission, CRUD de regra) |
| Leitura pelo certificado | `EmpresaFiscalLookupController` → `GET /nfe-brasil/tributacao/empresa-fiscal` (read-only) |
| Item de menu | grupo Fiscal › **Tributação** (order 98), publicado em `Modules/Fiscal/Http/Controllers/DataController.php` para quem tem `nfe.tributacao.manage` (ou superadmin). Até 2026-11-17 não havia item: só URL direta ou o link pequeno na tela Certificado |
| Templates | `TributacaoTemplateService` (`listar` · `sugerir` · `aplicar`, exige NCM padrão) |

## Fluxo "Configurar pelo certificado" (UC-NFTR-14..18)

1. Botão no cartão "Configuração rápida por setor" abre um drawer lateral (Sheet, PT-02).
2. Passo 1 mostra CNPJ e razão social lidos. Passo 2 mostra cada campo com a fonte; regime
   divergente vira escolha e bloqueia o "Continuar" até ser escolhido (UC-NFTR-16/18).
3. Escolher o regime relê `?regime=` para reordenar as sugestões (UC-NFTR-15).
4. Passo 3 exige NCM de 8 dígitos e diferente de `00000000`. Passo 4 resume e é o único com
   "Aplicar template", que faz `POST templates/{slug}/aplicar` com `ncm_default` (UC-NFTR-17).
5. Fechar em qualquer passo não grava nada.

## Verificação

| O quê | Como |
|---|---|
| Backend | Pest no CT 100: `TributacaoTemplateAplicarTest`, `EmpresaFiscalLookupTest`, `TributacaoIndexContratoTest` |
| Tela | e2e `e2e/nfe-tributacao-onboarding.spec.ts` (lane `e2e-gate.yml`, leitura mockada) |
| Pós-merge | smoke em biz=1 (nunca biz=4): abrir o drawer, ir até o passo 4, fechar sem aplicar |

## Naturezas de operação (tela aprovada por [W], 2026-11-13)

**Fonte de forma:** [`prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`](../../../prototipo-ui/cowork/Felipe/Configuracao%20de%20Tributos.html)
(rota do protótipo `/configuracoes/fiscal/naturezas`). Veio do Artefato publicado em 2026-11-13; as 7 fontes
embutidas foram trocadas pelos arquivos de `prototipo-ui/design-system/assets/fonts/` depois de conferido que
os bytes são iguais (sha256), e o arquivo volta byte a byte ao do Artefato quando a troca é revertida.

**Um lugar só.** A tela entra como seção de `/nfe-brasil/tributacao`, não como rota nova em `/configuracoes`.
O backend já existe: `nfe_operacoes_fiscais` + `NfeOperacaoFiscal` + `MotorTributarioService::calcularNaOperacao`
(thread 07 do playbook Fiscal, que deixou registrado: *"Sem tela. Cadastro de operações … são das threads de UI"*).
Criar outra tabela de naturezas faria dois lugares decidirem CFOP e CST.

### O que a tela pede × o que o `main` tem (medido 2026-11-15)

| Protótipo | No `main` | Falta |
|---|---|---|
| nome, finalidade (finNFe 1–4) | `nome`, `finalidade` | — |
| ativa / inativa (fica guardada) | só `softDeletes` | coluna `ativa` |
| tipo da nota (saída · entrada) | — | coluna `tipo` |
| pede nota referenciada | — | coluna `pede_nota_referenciada` |
| CFOP dentro / outro estado / exterior | um `cfop` com `?` no 1º dígito | 3 CFOPs (5405/6404 têm sufixos diferentes; o `?` não cobre) |
| tributos por **origem** da alíquota (pela UF, TIPI, regime, fixa, da nota de origem) | `regra_geral` com **números** fixos | formato de `regra_geral` com origem, lido pelo motor |
| modBC, redução de base, diferimento, crédito do Simples, cEnq e CST de IPI, ICMS fora da base do PIS/COFINS, origem da MVA, DIFAL ligado | — | campos na `regra_geral` + motor |
| escolher a natureza no lançamento da nota | `NfeService` só passa `operacaoId` na bonificação | seletor no lançamento |
| conferência (erros bloqueiam o salvar) | — | validação no servidor, com a mesma lista da tela |

### Ondas (cada uma um PR, flag desligada até a última)

1. **Protótipo no git + este plano** — #10887 (no main).
2. **Schema aditivo** em `nfe_operacoes_fiscais` (`tipo`, `ativa`, `pede_nota_referenciada`, `cfop_interestadual`,
   `cfop_exterior`) — nada existente muda; o `cfop` atual segue sendo o de dentro do estado. Entrou no #10896.
3. **Seção Naturezas na Tributação** atrás de flag: lista, editor em 4 passos e a conferência validada no servidor.
   Entrou no #10896 (3a backend, 3b tela).
4. **Motor lê a origem das alíquotas.** Mexe em valor de imposto: regra mestre de cálculo — prova por dois
   caminhos + tabela antes→depois em dry-run + aprovação [W] **antes** do merge. #10919 (no main, aprovação [W]
   relatada por [F] em 2026-11-16).
5. **Natureza escolhida no lançamento da NF-e.** 5a servidor #10934 · 5b seletor no painel fiscal e no modal da venda
   #10938 · atalho no topo do `/fiscal` #10942.
6. **Ligar a flag** por empresa, depois do aceite do contador (UC-NFTR-19 — aceite por versão, permissão própria).
   _Corrigido em 2026-11-16: esta linha citava o UC-NFTR-12, que é o 404 da sugestão de outra empresa._

**Depois das ondas (2026-11-23):** "Importar nota de referência" no passo 1 da natureza nova (`ImportarNota.tsx` +
`importarXml.ts`, UC-NFTR-73 a 77). O XML é lido no navegador, como no protótipo, e não vai ao servidor; a natureza
preenchida passa pela mesma conferência antes de salvar. Fica de fora o "Usar XML de exemplo" do protótipo, que
cadastraria tributos de uma nota de demonstração.

#### Onda 6 — checklist para ligar (por empresa, nunca para todas de uma vez)

1. **#10934 e #10938 no `main`** — sem eles a empresa cadastra a natureza, mas não consegue escolhê-la ao emitir.
   **Dependências achadas na auditoria de 2026-11-16** (uma por PR, todas antes de ligar): IPI no XML (#11001),
   IBS/CBS de 2027 e 2028 na configuração (#11000 — a CBS de 2027 fica pendente da resolução do Senado) e
   PIS/COFINS sem o ICMS na base (#11002). As de valor (#11001 e #11002) precisam da aprovação [W].
2. **Empresa escolhida pelo [W]** e conferida: certificado A1 válido e regime certo na configuração fiscal. O motor
   escolhe PIS/COFINS por ele: Simples se a empresa é do Simples; fora dele, Lucro Real só com `regime = lucro_real`
   e, em qualquer outro valor, Lucro Presumido (`MotorTributarioService::aplicarNaturezaTela`).
3. **Aceite do contador** daquela empresa (UC-NFTR-19). A revisão fiscal geral segue NÃO feita (seção abaixo);
   o aceite do contador da empresa não a substitui.
4. **Ligar só aquela empresa:** tool MCP `flag-set` com `feature=useV2NfeNaturezas`, `business_id=<N>`,
   `value=true` (cria a regra `biz-<N>` no GrowthBook e grava em `feature_flag_audits`). **Não** pôr a flag em
   `FeatureFlagService::$fallbackDefaults`, que liga para todas.
5. **Conferir na tela**, logado na empresa: a seção Naturezas aparece em `/nfe-brasil/tributacao`, o atalho
   aparece no topo do `/fiscal` e o seletor de natureza aparece no painel fiscal da venda.
6. **Primeira nota** com uma natureza da tela emitida em homologação antes de produção, com o XML conferido pelo
   contador.
7. **Desligar** (se precisar): `flag-set` com `remove=true` — a empresa volta ao padrão (desligada) e as naturezas
   cadastradas ficam guardadas.

### Revisão fiscal — NÃO feita (verificado 2026-11-15)

O guia de revisão (Claude Docs, 2026-11-13) tem 0 comentários; o Artefato do protótipo tem 0; nenhum PR, handoff
ou decisão do playbook responde os 9 pontos (garantia, conserto, IBS/CBS nas remessas, devolução de compra no Lucro
Real, devolução de venda CST 98, 4/7/12% e importados, DIFAL/FCP, padrão PIS/COFINS, campos que faltam).
Consequência: **as 10 naturezas de exemplo do protótipo NÃO viram seed.** Em produção cada empresa cadastra as suas
e o contador aceita pelo fluxo que já existe (thread 15). Só a "Venda" padrão continua como hoje.
