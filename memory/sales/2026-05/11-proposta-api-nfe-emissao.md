# Proposta — API de emissão de NFe/NFC-e via oimpresso

> Documento pra envio externo ao cliente. Wagner edita campos `<<...>>` antes de enviar.
> Versão: 1.0 — 2026-05-15

---

**Para:** <<NOME DO CLIENTE / EMPRESA>>
**De:** Wagner Rocha — oimpresso ERP
**Data:** <<DATA>>
**Assunto:** Emissão de Nota Fiscal Eletrônica (NFe/NFC-e) via API REST

---

## O que oferecemos

Uma API REST que permite o seu sistema **emitir NFe (modelo 55) e NFC-e (modelo 65) usando o seu próprio CNPJ e certificado digital**, com toda a complexidade fiscal brasileira tratada pelo oimpresso:

- Transmissão SEFAZ (homologação e produção) com retry automático
- Aplicação de regras tributárias (CFOP, CSOSN/CST, NCM) conforme regime do seu CNPJ
- Geração de DANFE (PDF) pronto pra envio ao consumidor
- Cancelamento dentro da janela legal de 24h (evento 110111)
- Notificações via webhook quando a SEFAZ autoriza ou rejeita a nota
- Sandbox SEFAZ ilimitado pra você testar antes de ir pra produção

A NFe sai oficialmente **com o seu CNPJ como emissor** — você continua sendo o contribuinte responsável fiscal. O oimpresso é o transmissor técnico.

## Como funciona — fluxo resumido

1. Você cadastra o **certificado digital A1** (.pfx) e dados fiscais da sua empresa no oimpresso (one-time, via painel ou via API).
2. Para cada venda, seu sistema chama `POST /v1/nfe/emissoes` enviando um JSON com destinatário (CPF/CNPJ + endereço), itens (descrição, NCM, quantidade, valor) e modelo (55 ou 65).
3. O oimpresso valida, monta o XML, assina com o seu cert A1 e transmite à SEFAZ.
4. A resposta imediata informa o status inicial. Quando a SEFAZ retorna autorização final (segundos a alguns minutos), enviamos um **webhook** pro endpoint que você cadastrar.
5. Você baixa o DANFE PDF via `GET /v1/nfe/emissoes/{id}/danfe` e envia ao consumidor (email, WhatsApp, impressão).

## Tecnologia

- API REST sobre HTTPS, autenticação OAuth2 (`/oauth/token`)
- Documentação interativa em `oimpresso.com/docs` com exemplos copy-paste em curl, PHP, Python e Node
- Idempotência via header `Idempotency-Key` (evita duplicação em retry de rede)
- Webhooks assinados com HMAC SHA-256 (`X-Oimpresso-Signature`) — você valida autenticidade
- Postman collection auto-gerada pra você testar em 5 minutos

## Escopo — release 1 (MVP)

| Endpoint | Função |
|---|---|
| `POST /v1/nfe/emissoes` | Emite NFe ou NFC-e |
| `GET /v1/nfe/emissoes/{id}` | Consulta status (autorizada / rejeitada / pendente / cancelada) |
| `POST /v1/nfe/emissoes/{id}/cancelar` | Cancela NFe autorizada (até 24h) |
| `GET /v1/nfe/emissoes/{id}/danfe` | Baixa DANFE PDF |
| Webhook `nfe.autorizada` / `nfe.rejeitada` | Notificação assíncrona pro seu sistema |

**Fora do MVP (release 2 sob demanda):** NFSe (serviços municipais), CT-e (transporte), MDF-e (manifesto), inutilização de numeração, manifestação do destinatário.

## Prazo

- **Setup técnico:** 6 dias úteis após o aceite + envio do seu certificado A1 + dados fiscais
- **Sandbox liberado:** dia 4 (você já começa a testar com SEFAZ homologação)
- **Go-live produção:** após você validar 1ª NFe homologação autorizada

## Modelo comercial proposto

> *<<Wagner: escolher 1 das 3 opções abaixo, ou negociar híbrido. Os valores são proposta inicial — ajustar conforme volume estimado pelo cliente.>>*

### Opção A — Mensal fixo
- **Setup:** R$ [redacted Tier 0] (one-time)
- **Mensalidade:** R$ [redacted Tier 0]/mês até 200 NFe/mês inclusas
- **Excedente:** R$ [redacted Tier 0] por NFe acima de 200/mês
- Sandbox SEFAZ: ilimitado e gratuito
- Compromisso mínimo: 12 meses

### Opção B — Pay-per-use
- **Setup:** R$ [redacted Tier 0] (one-time)
- **Por NFe emitida e autorizada:** R$ [redacted Tier 0] (sem mínimo mensal)
- Apenas NFe que SEFAZ retorna autorização final são cobradas (rejeições não contam)
- Sandbox SEFAZ: ilimitado e gratuito

### Opção C — Enterprise (>1.000 NFe/mês)
- Conversa direta — preço sob consulta com SLA dedicado, suporte prioritário e ambiente isolado

## Responsabilidades — quem responde por quê

| Responsabilidade | oimpresso | Cliente |
|---|---|---|
| Disponibilidade da API (SLA 99.5%) | ✅ | — |
| Transmissão correta à SEFAZ | ✅ | — |
| Custódia segura do certificado A1 (encrypt-at-rest) | ✅ | — |
| Aplicação correta da tributação cadastrada | ✅ | — |
| **Validade dos dados enviados** (CNPJ destinatário, NCM, CFOP, valores) | — | ✅ |
| **Conformidade fiscal/contábil das notas emitidas** | — | ✅ |
| Renovação anual do certificado A1 | — | ✅ |
| Cumprimento do prazo legal de cancelamento (24h) | — | ✅ |

Termos de Serviço completos enviados separadamente após aceite preliminar.

## Pré-requisitos do cliente

- CNPJ ativo e regular junto à SEFAZ do estado de operação
- Certificado digital A1 vigente (e-CNPJ A1, formato .pfx)
- Inscrição estadual ativa (para NFe modelo 55) ou regime de NFC-e habilitado (modelo 65)
- Endpoint HTTPS público pra receber webhooks (ou aceita polling se preferir)

## Próximo passo

Call de 30 minutos pra:

1. Confirmar volume estimado de NFe/mês (define a melhor opção comercial)
2. Demonstrar a API em sandbox (1 emissão real homologação ao vivo)
3. Alinhar cronograma de setup
4. Enviar contrato + ToS pra revisão jurídica do cliente

**Disponibilidade:** <<DIAS / HORÁRIOS>>
**Contato direto:** Wagner Rocha — wagnerra@gmail.com — <<TELEFONE>>

---

*oimpresso ERP — núcleo modular brasileiro. Backend fiscal NFe em produção contínua desde 2024 (cliente piloto ROTA LIVRE — vestuário, vertical Modules/Vestuario). Mais de <<NÚMERO>> NFC-e emitidas até hoje sem incidente fiscal.*

> ⚠️ Wagner: o número exato de NFC-e emitidas tá no banco — query `SELECT COUNT(*) FROM nfe_emissoes WHERE status='autorizada'` antes de mandar. Se for número modesto, omite a frase ou substitui por "operação contínua há 2+ anos".
