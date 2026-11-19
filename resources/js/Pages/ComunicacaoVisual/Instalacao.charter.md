---
id: resources-js-pages-comunicacao-visual-instalacao-charter
page: /comunicacao-visual/instalacoes/{id}
component: resources/js/Pages/ComunicacaoVisual/Instalacao.tsx
runbook: memory/requisitos/ComunicacaoVisual/RUNBOOK-instalacao.md
related_us: [US-COMVIS-007]
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-campo.jsx
status: draft
---

# Charter — Pages/ComunicacaoVisual/Instalacao.tsx

## Persona-alvo

Instalador da gráfica de comunicação visual, no celular, em pé na frente da fachada. Precisa saber aonde ir
e deixar o comprovante da instalação sem voltar ao escritório.

## Objetivo desta página

1. **Onde e quando** — OS, dia e hora, cliente, endereço com "Abrir no mapa", equipe e veículo.
2. **Fotos** — tirar a foto antes e a foto depois pela câmera do celular; o item fica marcado quando a API
   grava. Refazer vale a mais nova.
3. **Assinatura** — o cliente assina no quadro da tela; o instalador escreve o nome de quem assinou e marca o
   aceite do cliente para guardar a assinatura (LGPD). Sem desenho, nome e aceite, "Salvar assinatura" não sai.
   A assinatura é colhida uma vez.
4. **Concluir** — o botão só libera quando o comprovante tem foto antes, foto depois e assinatura; concluída,
   a tela mostra "Instalação concluída" e não oferece mais nada para enviar.
5. **Recusa visível** — quando a API recusa (instalação concluída, arquivo que não é imagem, assinatura já
   colhida, item faltando), o alerta "Não enviado" mostra a mensagem dela.

Contrato comportamental: [`Instalacao.casos.md`](Instalacao.casos.md).

## Fora desta versão

Concluída, a tela oferece "Baixar protocolo (PDF)" (`GET /comunicacao-visual/instalacoes/{id}/protocolo`,
`ProtocoloInstalacao`, UC-CVCEL-07..10).

Ainda não está na tela (não assuma que está): o envio do protocolo ao cliente. Os itens de EPI,
nível e vedação do protótipo não têm onde ser gravados.
A regra do que pode ser enviado mora no backend (`ComprovanteInstalacao`); a tela só mostra a recusa.
