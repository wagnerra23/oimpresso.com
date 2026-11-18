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
3. **Recusa visível** — quando a API recusa (instalação concluída, arquivo que não é imagem), o alerta
   "Não enviado" mostra a mensagem dela.

Contrato comportamental: [`Instalacao.casos.md`](Instalacao.casos.md).

## Fora desta versão

Ainda não estão na tela (não assuma que estão): colher a assinatura do cliente (aparece só como estado),
o botão Concluir, o protocolo em PDF e o envio ao cliente. Os itens de EPI, nível e vedação do protótipo não
têm onde ser gravados.
A regra do que pode ser enviado mora no backend (`ComprovanteInstalacao`); a tela só mostra a recusa.
