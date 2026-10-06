---
sessao: decisoes
titulo: Decisões [W] — Tributação e redução de suporte
data: 2026-10-06
autor_registro: "[CC]"
fonte: chat do projeto Cowork, [W] — "aceito todas sugestões, documente as decisões"
base: wagnerra23/oimpresso.com@main 3de6bdc5bf8b
---
# Decisões [W] · 2026-10-06 · Tributação

[W] aceitou **em bloco** as 7 propostas abaixo. Cada uma vem com a **condição** que a crítica adversária impôs; a condição faz parte da decisão.

| id | decisão | condição (não negociável na execução) | destrava |
|---|---|---|---|
| D-MOTOR | **Sim.** Lane 🔴 própria pode escrever no `MotorTributarioService`, como emenda à lei 2 do módulo. | Sozinha no PR, `MotorTributarioServiceTest` estendido no mesmo PR; nenhum PR de UI toca o motor. | 06 · 07 |
| D-OPERACAO | **Sim.** A natureza de operação é a porta de entrada, com regra geral por operação e exceções produto · NCM · NCM+UF. | Cada regra tem `válida de/até`; editar gera versão nova; o `tributacao_default` vira a regra geral da operação "Venda", sem perder dado (append-only, ADR 0093 G8). Templates L1 geram as operações. | 07 |
| D-SIM | **Sim.** O Non-Goal *"Calculadora interativa de tributo"* do `Index.charter.md` cai **só** para um simulador read-only. | Chama `MotorTributarioService::calcular` (zero cálculo no front), mostra o nível N1–N4 e traz o aviso literal de que é prévia, não garantia. | 08 |
| D-UF | **Sim.** Tabela ICMS/FCP por UF **curada**. | Sem sync TBT (o Non-Goal continua). Só entra número com lei citada literal (lei 4 do módulo). Alíquota interna vem do contador; o seed traz só as interestaduais com a resolução do Senado citada. | 09 |
| D-IA | **Sim.** A IA sugere (NCM · natureza · IBS/CBS · inconsistências) e **nunca aplica**. Motor: **Jana** (`Modules/Jana`) — **confirmado por [W] 2026-10-06**, com acesso próprio pra tributação (thread 10). | Aceitar exige `nfe.tributacao.manage` + `activity`. Sugestão de risco alto exige confirmar a leitura. NFS-e e serviço × mercadoria só como sugestão. O Non-Goal do `RegraForm` cai só para sugestão. | 10 |
| D-ENTRADA | **Projeto com ADR primeiro.** | A thread 11 escreve a proposta de ADR; o código vem depois da ratificação. | 11 |
| D-SUPORTE | **Sim.** Reduzir suporte por: medir → saúde fiscal → bloqueio com saída → conserto cadastral → aceite do contador. IA por último. | Medição (13) vem **antes** de qualquer automação nova. NF-e sem NCM não é saída: a saída é o **NCM padrão da empresa, que já existe** (`ncm_default` → `ncm_padrao`) + revisão. *(Corrigido 13:45: "da categoria" não existe no código.)* | 13 · 14 · 15 · 16 |

**Confirmado [W] 2026-10-06:** o motor da IA é a Jana.

## Achado da leitura de 2026-10-06 13:45 — muda a ordem de tudo
`NfeService::emitirParaTransaction` (NFC-e) e `::emitirParaInvoice` montam **um único item genérico** com o NCM padrão da empresa ("fase 2A"). Ou seja: hoje o NCM do produto **não entra na nota**, e as exceções por NCM e por produto, a ST (06), a vigência (07) e a saúde fiscal por produto (14) não teriam efeito na emissão. A **thread 17 (itens reais, fase 2B)** passa a ser pré-requisito de 06 → 07 → 14 → 16. Sem decisão nova: está dentro de D-MOTOR/D-SUPORTE.

## D-CONTADOR — RESPONDIDA [W] 2026-10-06 14:20: sim, como proposto
Modelo de acesso do contador: **(1) link de revisão por e-mail, padrão** (assinado, 14 dias, código de 6 dígitos, um business, só regras fiscais) · **(2) usuário "Contador", opcional** (configura + aceita, sem venda/financeiro) · **(3) planilha** que volta pelo Import CSV da empresa. Aceite **não bloqueia emissão**. O admin não aceita em nome do contador. Detalhe e casos: thread 15.

## RESPONDIDAS [W] 2026-10-06 14:50 — "ok nas 4" (valem as propostas abaixo)
- **D-NATUREZA** — Banner/placa/adesivo personalizado: quem decide ICMS × ISS? **Proposta:** o contador, por produto, com fonte registrada; enquanto pendente, emite pelo padrão da UF (em SP, ICMS). A Jana nunca decide. (thread 29)
- **D-DIFAL-UF** — DIFAL do Simples a consumidor de outra UF. **Proposta:** padrão "não recolhe" (ADI 5.464); o contador marca as UFs que cobram por lei própria. (thread 29)
- **D-FECHAMENTO** — O link do contador mostra também o fechamento do mês (10 conferências) ou só a revisão de regras? **Proposta:** mostra, só leitura, sem dado de cliente. (thread 28)
- **D-REJEITADA** — Nota rejeitada pendente bloqueia o fechamento do mês? **Proposta:** não bloqueia; aparece como pendência com ação ("resolver ou inutilizar"). (thread 28)

## D-ANCORA — RESPONDIDA [W] 2026-10-06 14:22: o protótipo do Cowork (`fiscal-tributacao.jsx`, rota `fiscal-tributacao` em `oimpresso.com.html`) é o alvo de forma da Tributação. O `prototipos/nfe-tributacao/` (PR #7145) fica como material de comparação, não alvo. Promover a âncora nos charters (`related_prototype`) segue a UI-0029 e é feito na thread 12, com esta decisão citada.

## (histórico) Aberta depois da leitura de 2026-10-06 13:38
| id | pergunta | por quê | trava |
|---|---|---|---|
| D-ANCORA | Qual protótipo é o alvo de forma da Tributação: o do Cowork (`fiscal-tributacao.jsx`, rota `fiscal-tributacao`) ou o `prototipos/nfe-tributacao/` do designer-agente (PR #7145)? | Existem **dois** desenhos da mesma tela. O `sources/Wagner/nfe-tributacao.md` §1 diz que promover protótipo a âncora das 4 telas vivas é soberania [W] (UI-0029). Sem essa resposta o ALVO mediria o lado errado. | 12 → 05 · 08 · 14 |

