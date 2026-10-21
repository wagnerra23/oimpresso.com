---
id: cowork-repair-consulta-os-link-casos
casos: Acompanhamento de OS por link com código · /os/{codigo} e /consulta-os
irmaos: resources/js/Pages/ConsultaOs/Index.casos.md (UC-COS-01..11, no main) · repair-portal.jsx (forma) · crm-portal.jsx aba Assistência (forma)
origem: "[CC] 2026-10-15, pedido de [W]: o link que a loja manda, com código, e o cliente que não tem cadastro"
base: wagnerra23/oimpresso.com@main 93ff8a960b74
estado: decidido por [W] em 2026-10-15 (_DECISOES-W-2026-10-15.md) — vira casos do main nas threads 06/07 do Repair e 25 do Crm
---

# Casos de uso — acompanhamento de OS por link com código

> **Continua** o `Index.casos.md` do ConsultaOs (UC-COS-01..11) sem mudar nenhum deles. Âncoras:
> charter do ConsultaOs (Mission "reduz ligação/WhatsApp perguntando *e a minha OS?*", Non-Goals,
> Anti-hooks) e a pendência [T0] do `_saida-04.md` (o nº da OS é sequencial **por empresa** e a busca
> pública cruza empresas).
>
> **Como funciona, em uma frase:** toda OS ganha um **código de acompanhamento** aleatório e único no
> sistema. Ele é a credencial: abre a OS **sem login e sem cadastro**. Como o código é único entre
> empresas, ele também diz de qual empresa é a OS, o que fecha o [T0] para quem chega pelo link.

## Como o cliente chega

| Entrada | Endereço | Quem usa |
|---|---|---|
| **Link recebido** (WhatsApp, e-mail, QR do recibo) | `/os/K7PM4QXR` | quase todo mundo: um toque e abre |
| **Código digitado** | `/consulta-os` → campo "Código de acompanhamento" | quem tem só o recibo em papel |
| **Nº da OS + série** | `/consulta-os` → aba "Nº da OS + série" | legado, `/repair-status` antigo e QR já impressos |
| **Portal do contato** (logado) | `/contact/…` → aba Assistência | cliente com acesso ao portal |

O código tem 8 caracteres de um alfabeto sem 0/O e 1/I/L (`23456789ABCDEFGHJKMNPQRSTUVWXYZ`), e
aparece como `K7PM-4QXR`. Com 31 símbolos e 8 posições são cerca de 8,5 × 10¹¹ combinações, o que,
com o `throttle:30,1` que já existe, torna inviável tentar códigos ao acaso.

## Como provar (o que o Code escreve)

| Camada | O que roda | Onde |
|---|---|---|
| **Contrato** | Pest, um `it('UC-COS-NN · …')` por UC, tenants 98 × 99. Molde: `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` (mesma lane). | `tests/Feature/ConsultaOs/ConsultaOsLinkContratoTest.php` |
| **Discriminante** | UC-COS-15: o **mesmo nº** de OS nas duas empresas, cada uma com o seu código; o link de uma nunca devolve a outra. UC-COS-16: código bem formado e inexistente → 404 idêntico ao de código de OS apagada. | dentro de cada `it` |
| **Segurança** | UC-COS-12: 1.000 códigos gerados sem repetição e sem relação com o id (dois ids seguidos ≠ prefixo comum). UC-COS-25: os 3 headers na resposta e o código redigido no log. UC-COS-07 continua: `throttle:` em `/os/{codigo}`. | idem |
| **Valor** | UC-COS-28 não grava valor: o corpo do POST de decisão não tem campo de preço, e um `valor` mandado é ignorado. | idem |
| **Controle negativo** | Mutação no recibo: tirar o filtro por código e ver o UC-COS-15 falhar. | `_saida-06.md` |
| **Forma** | `design-diff --compare --check` da `ConsultaOs/Index` contra `repair-portal.jsx` nos cenários link · digitado · pronto · entregue · orçamento · expirado · código errado, 390px e 1280px, claro e escuro. | recibo da 06 |
| **Impressão** | O PDF da via do cliente traz QR, endereço e código legível (UC-COS-20) — conferir pelo texto extraído do PDF, não pelo template. | recibo da 06 |

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Thread |
|----|-------------|------|--------|--------|
| UC-COS-12 | Toda OS nasce com código de acompanhamento | must `[T0]` | [T0] `_saida-04` · Anti-hooks "NÃO expõe por enumeração" | Repair 06 |
| UC-COS-13 | O link abre a OS direto, sem login nem formulário | must | Mission · Non-Goals "NÃO exige login" | Repair 06 |
| UC-COS-14 | O código digitado acha a mesma OS | must | Goals "busca" | Repair 06 |
| UC-COS-15 | O código resolve a empresa: nada de OS de outra loja | must `[T0]` | [T0] `_saida-04` · UC-COS-09 | Repair 06 |
| UC-COS-16 | Código errado: resposta neutra, sem pista | must `[T0]` | UC-COS-03 · UC-COS-07 | Repair 06 |
| UC-COS-17 | O link expira 90 dias depois da entrega | must | D-LINK-VALIDADE ✅ | Repair 06 |
| UC-COS-18 | A loja gera um novo código e o anterior para de abrir | must | Anti-hooks · LGPD (link vazado) | Repair 06 |
| UC-COS-19 | A página do link mostra a loja (nome e telefone do balcão) | must | D-LOJA ✅ · UC-COS-04 | Repair 06 |
| UC-COS-20 | Recibo e etiqueta imprimem QR, código e endereço | must | `repair-print.js` bloco "Acompanhe pelo site" | Repair 06 |
| UC-COS-21 | A mensagem de abertura e de troca de status leva o link | should | template do status (`repair_statuses.sms_template`) | Repair 06 |
| UC-COS-22 | Cliente sem cadastro no portal acompanha pelo link | must | pedido [W] 2026-10-15 | Repair 06 |
| UC-COS-23 | "Receber acesso": o cliente confirma o celular da OS por WhatsApp e entra no portal | must (com a config ligada) | D-ACESSO ✅ | Repair 07 |
| UC-COS-24 | Cliente logado vê as OS dele na aba Assistência | must `[T0]` | D3/D6 do Crm · `crm-portal.jsx` | Crm 25 |
| UC-COS-25 | A página do link não vaza o código | must `[T0]` | LGPD · `auditarConsulta` (PiiRedactor) | Repair 06 |
| UC-COS-26 | Nº da OS + série continua valendo, com série obrigatória | must `[T0]` | UC-COS-08/09 · D-LEGADO ✅ | Repair 06 |
| UC-COS-27 | OS pronta mostra há quantos dias espera retirada | must | pedido [W] 2026-10-15 ("refinar") | Repair 06 · Crm 25 |
| UC-COS-28 | Aprovar ou recusar o orçamento pelo link | must | D-APROVAR ✅ (_DECISOES-W-2026-10-15b) — exceção escrita no charter | Repair 08 |
| UC-COS-29 | Uma peça só mostra a OS nas duas portas | must | anti-duplicação (os-acompanhamento.jsx) | Repair 06 · Crm 25 |
| UC-COS-30 | OS entregue mostra a data e até quando vai a garantia | must | D-LINK-VALIDADE ✅ (90 dias = garantia) | Repair 06 |

---

## UC-COS-12 · Toda OS nasce com código de acompanhamento · `must` `[T0]`

- **Aceite:** Dado uma folha de OS criada (balcão, coleta ou no local) · Então ela tem um
  `codigo_acompanhamento` de 8 caracteres do alfabeto sem ambíguos · E ele é **único no sistema
  inteiro**, não só na empresa · E não é derivado do nº da OS, do id nem da data (dois códigos
  seguidos não têm relação).
- **Migração:** as OS que já existem recebem código num backfill. As concluídas há mais tempo que a
  validade (UC-COS-17) ficam sem link.
- **Controle negativo:** forçar uma colisão no gerador regera o código em vez de gravar duplicado.

## UC-COS-13 · O link abre a OS direto, sem login nem formulário · `must`

- **Persona:** cliente final que tocou no link da mensagem da loja.
- **Aceite:** Dado um visitante sem sessão · Quando abre `/os/{codigo}` com um código válido ·
  Então vê a OS daquele código: nº, aparelho, status com cor, fases, previsão e andamento (whitelist
  do UC-COS-04) · E não passa por formulário nenhum · E a resposta é 200.
- **Forma:** `repair-portal.jsx`, prévia "Abriu o link recebido". Mobile-first, legível em 390px.

## UC-COS-14 · O código digitado acha a mesma OS · `must`

- **Aceite:** Dado o código `K7PM4QXR` · Quando o cliente digita `k7pm-4qxr`, `K7PM 4QXR` ou
  `K7PM4QXR` em `/consulta-os` · Então recebe a mesma OS do UC-COS-13 · E um texto que não tem 8
  caracteres válidos é recusado antes da busca, com "Confira o código" (422).

## UC-COS-15 · O código resolve a empresa · `must` `[T0]`

- **Aceite:** Dado o mesmo nº de OS `JS2026/0001` nas empresas 98 e 99, cada uma com o seu código ·
  Quando o cliente abre o link da OS da empresa 98 · Então vê **só** a OS da 98 · E o payload não traz
  `business_id`.
- **Efeito no UC-COS-09:** o caminho do código deixa de cruzar empresas. O caminho nº + série segue
  documentado no UC-COS-09 até a D-LEGADO.

## UC-COS-16 · Código errado: resposta neutra, sem pista · `must` `[T0]`

- **Aceite:** Dado um código bem formado que não existe · Quando o cliente abre o link ou busca ·
  Então recebe 404 `{"found": false}` · E a tela diz "Não encontramos esta OS" sem dizer se o código
  quase bateu · E a rota tem `throttle:` (mesmo limite do UC-COS-07).

## UC-COS-17 · O link expira depois da entrega · `must`

- **Aceite:** Dado uma OS entregue há mais de **90 dias** (D-LINK-VALIDADE, o prazo da garantia de serviço) · Quando o cliente abre o link · Então vê "Este link expirou" e nenhum dado da
  OS · E a resposta é 410.
- **OS em aberto não expira**, por mais tempo que leve.

## UC-COS-18 · A loja gera um novo código · `must`

- **Persona:** balcão (Larissa), quando o cliente diz que mandou o link para a pessoa errada.
- **Aceite:** Dado uma OS com código A · Quando o balcão usa "Gerar novo código" na OS · Então a OS
  passa a ter o código B · E o link com A responde como o UC-COS-16 · E a troca fica no `activity_log`
  da OS (quem e quando), sem o código antigo nem o novo no texto.

## UC-COS-19 · A página do link mostra a loja · `must`

- **Aceite:** Dado uma OS da empresa 98 · Quando o link abre · Então o topo mostra o nome da loja e o
  rodapé o telefone do balcão (dado da **empresa**, não do cliente).
- **Decidido [W] 2026-10-15 (D-LOJA):** as duas chaves entram na whitelist do UC-COS-04, só no payload do link.

## UC-COS-20 · Recibo e etiqueta imprimem QR, código e endereço · `must`

- **Aceite:** Dado uma OS · Quando o balcão imprime o recibo (`print_pdf`) ou a etiqueta
  (`print_label`) · Então sai o QR para `/os/{codigo}`, o código legível `K7PM-4QXR` e o endereço curto.
- **Hoje:** `repair-print.js` imprime "Consulte em oimpresso.com/repair-status com o número … e a
  série …". Esse texto dá lugar ao código.

## UC-COS-21 · A mensagem de abertura e de troca de status leva o link · `should`

- **Aceite:** Dado um status com aviso ligado · Quando a OS entra nele · Então o template aceita a tag
  `{link_acompanhamento}` (e `{codigo_acompanhamento}`), trocada pelo link da OS · E sem a tag no
  template nada muda.
- **Anti-hook:** abrir o link **não** dispara aviso (charter: "NÃO dispara notificação ao consultar").

## UC-COS-22 · Cliente sem cadastro no portal acompanha pelo link · `must`

- **Persona:** cliente balcão / consumidor final que nunca criou acesso. Pode estar até como "Cliente
  balcão" (walk-in) na OS.
- **Aceite:** Dado uma OS cujo contato não tem login no portal · Quando o cliente abre o link · Então
  acompanha igual ao UC-COS-13 · E nada na página pede cadastro, senha ou e-mail para ver a OS.
- **Regra:** o link é a credencial. Ter conta no portal é opcional e nunca é condição para acompanhar.

## UC-COS-23 · "Receber acesso" pelo celular da OS · `must` (com a config ligada)

- **Decidido [W] 2026-10-15 (D-ACESSO):** sim, desligado por padrão (a loja liga), código por **WhatsApp**.
- **Aceite (se ligado na config da loja):** Dado o link aberto de uma OS cujo contato tem celular ·
  Quando o cliente toca "Receber acesso" · Então recebe por WhatsApp um código de 6 dígitos no celular **da OS**
  (mascarado na tela, ex.: `(14) 9••••-••18`), válido por 10 minutos · E ao confirmar entra no portal
  do contato, na aba Assistência.
  - Contato real sem login → o login do portal é criado para ele (o mesmo que o `ContactLoginController`
    cria hoje pelo admin).
  - **Cliente balcão (walk-in)** → não cria acesso: "Para ver todas as suas OS, peça seu cadastro no
    balcão". Um walk-in é compartilhado por muitos clientes.
- **Não pode:** trocar o celular pelo link, nem mandar o código para outro número.

## UC-COS-24 · Cliente logado vê as OS dele na aba Assistência · `must` `[T0]`

- **Aceite:** Dado o contato A logado no portal · Quando abre a aba Assistência · Então vê só as OS
  com `contact_id` = A **e** `business_id` da sessão · E cada uma mostra fases, previsão, andamento e o
  mesmo código do link · E uma OS do contato B do mesmo negócio não aparece.
- **Forma:** `crm-portal.jsx`, rota `crm-portal-assistencia`.

## UC-COS-25 · A página do link não vaza o código · `must` `[T0]`

- **Aceite:** Dado `/os/{codigo}` · Então a resposta tem `X-Robots-Tag: noindex`, `Cache-Control:
  private, no-store` e `Referrer-Policy: no-referrer` · E o log de auditoria grava o código redigido
  (como o `auditarConsulta` já faz com o número).

## UC-COS-26 · Nº da OS + série continua valendo, com série obrigatória · `must` `[T0]`

- **Decidido [W] 2026-10-15 (D-LEGADO):** a série passa a ser obrigatória nesse caminho (hoje é opcional, UC-COS-08).
- **Aceite:** Dado só o nº da OS, sem série · Quando o cliente busca · Então recebe 422
  "Informe o número da OS e o número de série" · E com nº + série certos acha a OS.

## UC-COS-27 · OS pronta mostra há quantos dias espera retirada · `must`

- **Aceite:** Dado uma OS em status concluído "pronto" há N dias · Quando o cliente abre o link ou a aba Assistência · Então vê "Pronto para retirada há N dias" com o horário do balcão · E a partir de 7 dias o aviso muda de tom (atenção) · E o Início do portal do contato mostra o mesmo aviso.
- **Não inventa política:** prazo de guarda ou cobrança de armazenagem só aparece se a loja tiver isso configurado (campo `retirarAte`, hoje inexistente — decisão [W] se um dia existir).

## UC-COS-28 · Aprovar ou recusar o orçamento pelo link · `must`

- **Decidido [W] 2026-10-15 (D-APROVAR, `_DECISOES-W-2026-10-15b.md`):** aprovado, ligado por padrão, a loja pode desligar. Antes o charter dizia "NÃO permite editar/cancelar (read-only)" e a ADR ARQ-0002 manda não mostrar preço. Esta proposta grava uma decisão do cliente **sem mostrar valor** (o valor e o laudo vão pelo WhatsApp/e-mail, como o template do status 3 já faz).
- **Aceite:** Dado uma OS em "Orçamento enviado" · Quando o cliente toca "Aprovar orçamento" e confirma · Então a OS registra a aprovação com origem "link do cliente" e data · E o balcão vê a aprovação na OS · E a página passa a mostrar "aprovado" · E recusar segue o mesmo caminho, com "devolução sem reparo".
- **Limites:** POST com o código + CSRF de sessão anônima, `throttle`, uma decisão por orçamento (não troca depois pelo link), nada de valor no corpo.
- **Forma:** `repair-portal.jsx`, prévia "Orçamento aguardando aprovação" + chave "Aprovar orçamento pelo link (proposta)" na configuração.

## UC-COS-29 · Uma peça só mostra a OS nas duas portas · `must`

- **Aceite:** a página do link (`/os/{codigo}`) e a aba Assistência do portal do contato usam o **mesmo** componente de cartão de OS · E a whitelist (UC-COS-04 + D-LOJA) é a mesma nas duas.
- **Forma:** `os-acompanhamento.jsx` (`window.OsAcompanhamento`), `variante` "publico" | "portal".

## UC-COS-30 · OS entregue mostra a data e até quando vai a garantia · `must`

- **Aceite:** Dado uma OS entregue há menos de 90 dias · Quando o cliente abre o link · Então vê "Entregue em dd/mm/aaaa" e "Garantia do serviço até dd/mm/aaaa" (entrega + 90 dias) · E no dia seguinte ao fim da garantia o link responde como o UC-COS-17.
- **Fonte da data:** a data em que a OS entrou no status "Entregue", não a previsão de entrega.

---

## Fora deste arquivo

- Captcha (resto da US-CONSULTA-001): com o código aleatório, fica para o caminho nº + série.
- "Quem" na atividade (nome do funcionário): pendência [W] do `_saida-04` §3, não muda aqui.
