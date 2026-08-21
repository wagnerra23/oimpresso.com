# Handoff [CC] → [CL] · Contrato de Tela para /financeiro/unificado

> **Natureza:** governança/CI (não é tela). **Tier:** não-Tier-0 (sem schema/dado/segredo).
> **Papéis:** [CC] declarou a intenção (seções/copy/ordem) a partir do vivo do `main`; **[CL] instrumenta + liga o gate**; **[W] mergeia** = vira verdade.
> **Transporte:** este par via `cowork-inbox/` ou GitHub Issue `cowork-intake`. Nada foi escrito no git por mim (read-only).

## Por quê (o buraco que isto fecha)
Header e subnav do Financeiro/Unificado **não têm guarda de máquina hoje**. As catracas (`design-identity-gate`, `casos-gate`, `conformance-gate`) rodam em `resources/js` e cobrem **identidade/comportamento/token** — não **estrutura** (quais abas, copy, ordem, primary=dropdown). E **não existe Contrato de Tela pro Financeiro** (só `caixa-unificada.contract.json`). Resultado: divergência de header/subnav é a classe que **só o olho do [W] pega** (trilha do charter: "duas cores", "âncora podre", "iguale o filtro ao segmented" — todas manuais). Este contrato transforma "só o olho pega" em "o CI pega".

Diff concreto que motivou (protótipo Cowork × produção viva):
- **PageHeader:** produção usa `<PageHeader>` v3.8 shared (#2947) + breadcrumb; primary **"Novo título" é dropdown** (Novo recebimento/Novo pagamento). (O export Cowork ainda tem `.os-page-h` bespoke + botão simples — divergência que o gate não via.)
- **Subnav:** produção = `FinanceiroSubNav`/`PageHeaderTabs` unificado (ADR 0313), **8 abas** + `···` overflow com legacy. (Export tinha lista morta/parcial.)

## O que [CL] precisa fazer
1. **Commitar** `prototipo-ui/contrato/financeiro-unificado.contract.json` (conteúdo no arquivo irmão deste handoff).
2. **Instrumentar as âncoras** `data-contract="<id>"` nos elementos reais de produção, um por `id` do contrato:
   - `page-title`, `lentes`, `novo-titulo`, `kpi-cards`, `filtrar-por`, `period-bar`, `lifecycle-chips`, `table-header` → em `resources/js/Pages/Financeiro/Unificado/Index.tsx`.
   - `subnav` → no `FinanceiroSubNav`/`PageHeaderTabs` (ou no wrapper que o renderiza na Unificada).
3. **Verificar copy literal contra a fonte** ANTES de flipar required (o `contrato-de-tela.yml` roda advisory primeiro de propósito). Atenção: cabeçalhos de tabela podem estar em CAPS por CSS (`text-transform`) — o contrato casa a **string do source**, não a renderizada; ajuste o casing do contrato se o `.tsx` usar outra forma.
4. **Ligar** `/financeiro/unificado` no `contrato-de-tela.yml` (advisory). Flipar advisory→required quando estável (padrão: 2 verdes), como no piloto `caixa-unificada`.

## Fora de escopo (não fazer)
- Não repintar a tela — ela é 🔵/live (charter v19). Este PR só adiciona o contrato + âncoras (aditivo, sem mudar UI).
- `estados` do `novo-titulo` (`closed`/`open`) são informativos no v1 do schema — não precisam de asserção agora.

## Definição de pronto
- `contrato-de-tela.yml` roda a Unificada em advisory, verde (todas as âncoras presentes + copy casando).
- Charter/casos intocados (é chrome/gate, não comportamento) — `UnificadoBulkGuardTest`/`RetencaoLoopE2ETest` seguem ✅.
