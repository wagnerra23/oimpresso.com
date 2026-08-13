---
date: "2026-08-13"
slug: "smoke-real-pages-no-modulo-dono-a-divida-paga"
tldr: "A dívida declarada no handoff de 12/08 foi paga: smoke real em produção nas 6 ondas da migração de Pages, com o componente Inertia lido do runtime (não do código) e controle positivo em cada detector. 0 erros, 0 falhas de rede. Duas lições novas: a rota que deduzi do routes.php dava 404, e os dois trackers do browser devolviam 'zero' porque nem tinham ligado."
topic: "Smoke real das Pages no módulo dono — a dívida paga, e os dois zeros que não eram medição"
title: "Smoke real das Pages no módulo dono — a dívida paga, e os dois zeros que não eram medição"
type: reference
authority: historical
lifecycle: ativo
owner: W
---

# Smoke real das Pages no módulo dono — a dívida paga

Fecha a dívida nº 1 do [handoff de 2026-08-12 21:00](2026-08-12-2100-pages-no-modulo-dono-mergeado-e-a-refutacao-que-reprovou.md),
que declarava textualmente: *"**Smoke real NÃO foi feito.** Provei que compila e resolve […],
não que renderiza igual. R1 exige o smoke antes de 'pronto'"*.

Autorização [W]: *"pode testar"* + *"pronto"* (login feito por [W] no próprio Chrome — **não digitei
credencial**; a sessão foi usada já autenticada).

## O que foi provado — 6 de 6 ondas, em produção

O oráculo do componente é o **runtime** (`window.history.state.page.component`), não o código:
é ele que diz qual `.tsx` o resolvedor de fato escolheu.

| Onda (destino da migração) | Rota em prod | Componente resolvido | Recursos |
|---|---|---|---|
| **Whatsapp** (38 arquivos, a maior) | `/atendimento` | `Atendimento/CaixaUnificada/Index` | 250 · todos 200 |
| **Forja + team-mcp** (import cruzado) | `/forja` | `team-mcp/Forja/Cockpit` | 112 · todos 200 |
| **PaymentGateway** (piloto, 12) | `/settings/payment-gateways` | `Settings/PaymentGateways/Index` | 112 · todos 200 |
| **KB** (1 tela do `ads`) | `/ads/admin/graph` | `ads/Admin/Graph` | 217 · todos 200 |
| **Cms** | `/`, `/c/blogs` | `Site/Home`, `Site/Blogs` | todos 200 |
| **Superadmin** | `/pricing` | `Site/Pricing` | todos 200 |

**Controle negativo embutido:** `/login` seguiu resolvendo `Site/Login` e `/ia` seguiu resolvendo
`Jana/Index` — as telas que **ficaram** no núcleo continuam achando o caminho. O resolvedor de duas
raízes não roubou nem perdeu ninguém.

A `Atendimento/CaixaUnificada/Index` renderizou com **dado real de produção** (contagem de conversas
e não-lidas na tela, sidebar preta conforme [UI-0023](../requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)),
não um esqueleto. Screenshot conferido em tela.

⚠️ A tela exibia dois avisos operacionais (canal WhatsApp fora do ar, certificado vencido). São
**estado do negócio, pré-existentes** — nada a ver com a migração, e não foram tocados aqui.

## Lição 1 — deduzi a rota lendo o `routes.php`, e ela dava 404

Li `Modules/Whatsapp/Routes/web.php`, vi `->prefix('whatsapp')` na linha 67 e
`/caixa-unificada` na 167, e naveguei para `/whatsapp/caixa-unificada`. **404.** Repeti o erro em
`/whatsapp/inbox`. O prefixo servido é **`/atendimento`**, e eu só descobri isso lendo os `href` do
menu **na página logada** — ou seja, perguntando ao runtime.

É a §5 2026-07-17 (*"deduzir QUEM RODA parseando código quando o runtime sabe responder"*) e a
§5 2026-08-06 (*"quando código e tela discordam, a tela ganha"*) — desta vez no eixo **rota**, e o
oráculo barato estava a um clique: o menu renderizado. Não vira lápide nova: é instância de classe
já registrada (LC-08), e o ledger conta.

## Lição 2 — os dois "zero" que não eram medição

Perguntei por erros de console: **"No console errors"**. Perguntei por requests: **"No network
requests found"** — numa página que carrega 250 arquivos. O segundo zero é absurdo na cara, e foi
ele que denunciou o primeiro: **os trackers só começam a gravar quando o tool é chamado pela
primeira vez**. Os dois zeros mediam a minha janela, não a página.

Conserto: recarreguei com os dois já ligados, e só então li — 250 recursos, **todos 200**.
Depois, os dois controles positivos, porque *ausência de erro* e *ausência de medição* são
indistinguíveis sem eles:

- **rede:** `sem_status_reportado: 0` — se a API não expusesse status ali, o campo viria 250 e o
  `falhas: []` seria vazio por cegueira;
- **console:** plantei um `console.error` e o detector devolveu **1/1**.

É a mesma família do `0 failed` numa suíte que não rodou (LC-13) e do `0 mortos` num host sem `gh`
(§5 2026-07-29). Nada a codificar: o controle positivo já é a defesa, e ela funcionou.

## Estado

- **Dívida nº 1 do handoff anterior: PAGA.** R1 satisfeito para as 6 ondas.
- **As outras dívidas continuam abertas**, sem alteração: sinal camuflado dos 4 paths mortos
  pós-re-key · 3 links com profundidade `../../../../` errada em `TeamMcp/*-visual-comparison.md` ·
  `bootstrap/ssr/` fora do `.gitignore` · 1.654 branches locais (1.543 órfãs).
- **Etapa 2 segue combinada e não iniciada**: renomear namespaces divergentes
  (`Atendimento`→`Whatsapp`), em PR separado. O smoke de hoje é evidência a favor dela — hoje a
  URL diz `atendimento`, a pasta diz `Whatsapp` e o namespace diz `Atendimento`.

## Estado MCP no fechamento

`cycles-active` → **nenhum cycle ativo** em COPI. `sessions-recent limit:3` → 3 logs indexados hoje
(04/08 cron unassigned, 05/08 âncora medível, 05/08 máquinas que não avisavam) — **nenhum** toca
Pages/Inertia/migração de módulo, logo sem sessão irmã concorrente neste tema.
