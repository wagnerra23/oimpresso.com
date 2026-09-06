---
id: requisitos-essentials-messages-index-gap
tela: Essentials/Messages/Index (/essentials/messages)
prototipo: prototipo-ui/cowork/essenciais-page.jsx
tela_viva: resources/js/Pages/Essentials/Messages/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Essentials/Messages/Index

> **Fase 1 = PARIDADE.** `essenciais-page.jsx:1-3` declara o porte reverso do blade
> (`messages/{index,message_div,recent_messages}.blade.php`). Região: `Mensagens` (`:565-622`).
> Contrato: [`mensagens.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/mensagens.contract.json)
> (3 seções: `cabecalho-filtro` · `mural` · `compositor`).
> O charter do vivo declara `related_prototype: n/a (mural de chat bespoke … não segue um dos 5
> Padrões de Tela)` — a âncora desta tela vem do `bundle_source: essenciais-page.jsx`, e as duas coisas
> **coexistem por desenho**: a §5 2026-08-28 de [proibicoes.md](../../proibicoes.md) registra que
> `n/a` é declaração consciente que a máquina reconhece (`ehDeclaracaoNa`) e que convive com a âncora
> de bundle — e proíbe promover `bundle_source` a `related_prototype` em leva.

> ⚠️ **O contrato citado ainda NÃO é gate ativo.** Ele vive em
> `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`
> (medido: os 31 contratos ativos incluem `essentials-tipos`, `essentials-licencas` e
> `essentials-metas` — nenhum dos 5 dos essenciais). Ele é **proposta de contrato**: descreve a
> copy literal pretendida e serve de âncora para esta comparação, mas **não trava merge hoje**.
> Por isso as divergências de copy abaixo saem como `Decidir.`, nunca como "quebra de gate".

| Parte | Estado no vivo | Ação |
|---|---|---|
| Mural em ordem cronológica | **Paridade, e o vivo vai além.** Vivo `Messages/Index.tsx:171` (`CardContent` rolável com `ref={scrollRef}`) e o cabeçalho de cada mensagem com autor · localidade · hora. Protótipo `:600-604` (`ess-msg-h` com `<b>{nome}</b>` + `{local} · {hora}`). O vivo ainda faz **polling** de novas mensagens (`:114-118`, `setInterval(fetchNew, intervalMs)`), que o protótipo não simula. | Nada — vivo à frente no polling. |
| Compositor | **Paridade de capacidade.** Vivo `:228-267`: `<form onSubmit={submit}>` com `<Textarea>` (`:230`) e envio por Enter sem Shift; o `<Select>` de localidade em `:248-263` e o botão *"Enviar"* em `:266`. Protótipo `:610`: textarea com envio por Ctrl/Cmd+Enter. | Nada — paridade. |
| Copy do compositor | **Diverge, medido.** Contrato: `placeholder` = *"Escreva uma mensagem"* (protótipo `:610`). Vivo `:234`: **"Digite sua mensagem…"**. Contrato: `enviar` = "Enviar" — presente no vivo (1 ocorrência). | **Decidir.** Copy de contrato é soberania de [W]; renomeação, não ausência (§5 2026-07-15). |
| Título da tela | **Diverge, medido.** Contrato: `copy.titulo` = *"Mensagens"* (protótipo `:583`, `Card title="Mensagens"`). Vivo `:167`: **"Mural de mensagens"**. | **Decidir.** Mesma classe da linha acima. |
| Filtro de localidade no cabeçalho | **Ausente no cabeçalho — existe só no compositor.** Protótipo `:585-587`: `<select>` com *"Todas as localidades"* **no cabeçalho**, filtrando o mural (`lista` em `:574`). No vivo, o `<Select>` de localidade (`:248`) define a localidade **da mensagem a enviar**, não filtra o mural; o cabeçalho (`:165-169`) tem só o título. | **Decidir.** É comportamento (filtrar) → vira pedido pela régua do playbook. E note a inversão semântica: o mesmo controle serve a propósitos opostos nos dois lados — implementar o filtro sem tocar no seletor de envio é o desenho correto. |
| "Marcar tudo como lido" | **Ausente.** Protótipo `:589-590`: botão com contador de não-lidas, desabilitado quando não há nenhuma. Varredura contada no vivo: `Marcar tudo como lido` em `Messages/Index.tsx` = **0 ocorrências**. O contrato lista `nao-lidas` como estado próprio. | **Decidir.** É mutação (marca leitura por usuário) — precisa de modelo de leitura por destinatário, que o blade não tem. Decisão de [W] sobre escopo. |
| Estado sem permissão | **Paridade.** Vivo `:172-175` (a frase em `:174`): *"Você não tem permissão para ver mensagens."* sob `can.view`. O contrato lista `sem-permissao` como estado, e o charter do intake exige o padrão: *"o que o papel não pode aparece bloqueado com motivo, nunca escondido sem explicação"* — cumprido. | Nada — paridade. |
