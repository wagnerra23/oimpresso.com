---
date: '2026-05-27'
topic: "Sicoob API — checklist credenciais Kamila pedir pro gerente (Martinho Caçambas biz=164)"
type: handoff
audience: kamila
business_id: 164
business_name: Martinho Caçambas
banco: Sicoob (756)
caminho: API REST (OAuth2 + mTLS)
related_us:
  - US-FIN-044
related_adrs:
  - 0105-cliente-como-sinal-guiar-sem-mandar
status: pendente-cliente
owner: W
last_validated: '2026-05-27'
---

# Sicoob API — checklist do que Kamila pede pro gerente

> Liga pro gerente do Sicoob do Martinho Caçambas (biz=164) e pede TUDO de uma vez — não dá pra fatiar, sem qualquer item não roda nem o sandbox.

> **Contexto correto** (consolidação 2026-05-27): Kamila é Admin#164 do Martinho Caçambas, NÃO da ROTA LIVRE (biz=4). ROTA LIVRE (Larissa, biz=4) é cliente separado, vestuário em Gravatal/SC — não usa Sicoob. Martinho Caçambas (biz=164, locação/manutenção caçambas, região Tubarão/SC, competidor HiSoft) é o cliente do Sicoob. Ver [cliente-martinho-cacambas.md](../reference/cliente-martinho-cacambas.md) e [cliente-rotalivre.md](../reference/cliente-rotalivre.md).

## 1 · Produto certo

Pede explicitamente:

> "Quero usar a **API de Cobrança Bancária** do Sicoob (REST via developers.sicoob.com.br), não o CNAB arquivo. Preciso emitir boleto e receber baixa em tempo real."

Se o gerente disser "use o nosso internet banking pra registrar boleto" — é OUTRO produto, não serve.
Se ele disser "use CNAB 240" — também é outro produto (esse a gente já tem pronto, é alternativa).
O nome correto: **Cobrança Bancária API** ou **API Cobrança v3**.

## 2 · Credenciais Sicoob Developer Portal

| Item | O que é | Como vem |
|---|---|---|
| **Acesso ao portal** | Login em `developers.sicoob.com.br` | Gerente cadastra o CNPJ do Martinho Caçambas e libera o login (email + senha). Kamila precisa do login. |
| **Client ID** | Identifica o app do Martinho nas chamadas REST | Kamila gera no portal depois de logar — botão "Criar aplicativo" → escolhe escopo `cob_write` + `cob_read` + `webhooks_write`. Resultado: string tipo `9b5e0aac-...`. |
| **Client Secret** | Senha do app (sigilo total) | Aparece UMA vez no portal quando cria o app. **Salvar imediatamente** num gerenciador de senha — depois o portal só mostra a hash. |
| **Subscription Key** (se Sicoob pedir) | Chave de API gateway adicional | Alguns Sicoob singulares exigem além do client_id. Confirmar com gerente — se ele falar "não tem", tudo bem. |

## 3 · Certificado mTLS (.pfx)

| Item | O que é | Como vem |
|---|---|---|
| **Arquivo `.pfx`** | Certificado digital A1 emitido pelo Sicoob (não é o e-CNPJ da empresa, é específico API) | Gerente emite via convênio cooperativo. Vem por download seguro no portal OU email com link expirável. **NÃO usa e-CNPJ comum.** |
| **Senha do `.pfx`** | Pra abrir o certificado | Vem junto, separado do arquivo (email diferente ou SMS). Kamila guarda no mesmo gerenciador de senha. |
| **Validade** | Quando expira | Geralmente 1 ano. Anotar a data — quando faltar 30 dias, renovar (mesmo processo). |

## 4 · Dados da conta cooperativada

Esses Kamila já tem (estão no extrato Sicoob do Martinho) — só confirmar:

| Campo | Valor do Martinho Caçambas | Onde aparece |
|---|---|---|
| **Cooperativa singular** | Ex: Sicoob Credicitrus / Sicoob Cocred / etc | Topo do extrato Sicoob |
| **Agência (PA — Posto de Atendimento)** | 4 dígitos (ex: `3082`) | Extrato/internet banking |
| **Dígito da agência** | 1 dígito (se houver) | Extrato |
| **Conta corrente** | Geralmente 6-8 dígitos | Extrato |
| **Dígito da conta** | 1 dígito | Extrato |
| **Convênio (Código Cedente)** | 4, 6 OU 7 dígitos | **Não está no extrato comum.** Gerente envia quando libera o produto Cobrança. Este é o campo mais "esquecido" — sem ele NÃO emite boleto. |

## 5 · Carteira e Modalidade

Sicoob aceita só essas combinações na API:

| Carteira | Modalidade | Quando usar |
|---|---|---|
| `1` | Simples (sem caução) | **Padrão pro Martinho.** Cliente paga, dinheiro cai na conta direto. Recomendado. |
| `3` | Caucionada (Sicoob adianta) | Sicoob desconta antes do vencimento, cobra do cliente, paga taxa. Só se Martinho tiver linha de antecipação contratada. |

Pergunta pro gerente: **"Liberou minha conta pra carteira 1 (Simples) na API de Cobrança?"** Se ele disser "não, é caucionada", pede pra liberar Simples ou avisa antes da Kamila configurar.

## 6 · Webhook (entrega real-time da baixa)

Pra Sicoob avisar o oimpresso em tempo real quando o cliente paga:

| Item | Valor |
|---|---|
| **URL de webhook** | `https://oimpresso.com/paymentgateway/webhooks/sicoob-api/164` *(business_id do Martinho)* |
| **Eventos a habilitar** | `cobranca.liquidada`, `cobranca.vencida`, `cobranca.cancelada` |
| **HMAC secret** | Sicoob gera uma chave compartilhada quando Kamila cadastra o webhook no portal — Kamila copia e guarda |

**Importante:** essa configuração de webhook é feita NO PORTAL Sicoob pela Kamila, depois que Wagner subir a feature. Não precisa pedir nada agora — só saber que vai existir.

## 7 · Sandbox vs Produção

| Ambiente | URL base | Quando usar |
|---|---|---|
| **Sandbox** | `https://sandbox.sicoob.com.br` | Wagner testa primeiro com client_id/secret de sandbox (boleto fake, sem dinheiro real). |
| **Produção** | `https://api.sicoob.com.br` | Depois que sandbox passar, Kamila gera client_id/secret de produção e Wagner sobe. |

**Pede pro gerente liberar OS DOIS ambientes** (mesmo portal, mas aplicativos separados).

## 8 · Faixa de Nosso Número

Sicoob libera um bloco de numeração de boleto (ex: 1 a 999999) pro Martinho.
Pergunta pro gerente: **"Qual a minha faixa de Nosso Número? Posso usar de 1 até quanto?"**
Sem isso, sistema pode emitir nº que o Sicoob recusa.

## 9 · Beneficiário (PJ que sai impresso no boleto)

Confirma com a dona do Martinho (Kamila pode olhar no contrato social):

- CNPJ
- Razão social completa
- Endereço (logradouro + número, bairro, cidade, UF, CEP)
- Esses dados saem impressos no boleto → cliente final identifica quem cobrou.

## 10 · Taxa do Sicoob

Por curiosidade comercial (não bloqueia implementação, mas Wagner/cliente precisam saber):

- **Por boleto registrado** (cobrado mesmo se cliente não pagar): geralmente R$ 1,50 a R$ 3,50 — negociável.
- **Por liquidação** (quando cliente paga): às vezes tem taxa adicional ~R$ 0,50.
- **Tarifa mensal de uso da API**: geralmente isento pro cooperado, mas confirma.

Kamila pede a **tabela de tarifas escrita** (PDF/email).

---

## Resumo executivo — o que Kamila precisa ter na mão antes de chamar Wagner

- [ ] Login no portal `developers.sicoob.com.br`
- [ ] **Client ID** + **Client Secret** SANDBOX (salvo em gerenciador de senha)
- [ ] **Client ID** + **Client Secret** PRODUÇÃO (salvo separado)
- [ ] Arquivo `.pfx` + senha (sandbox)
- [ ] Arquivo `.pfx` + senha (produção)
- [ ] **Convênio (Código Cedente)** anotado
- [ ] Confirmação carteira `1` Simples liberada
- [ ] Faixa de Nosso Número anotada
- [ ] Dados beneficiário Martinho Caçambas confirmados (CNPJ + razão + endereço)
- [ ] Tabela de tarifas em PDF

Quando tudo isso estiver pronto, **Kamila avisa o Wagner** e ele agenda a implementação (US-FIN-044, ~24h estimadas).

---

**Próximo passo Wagner:** quando Kamila confirmar credenciais sandbox prontas, mover US-FIN-044 de `todo` → `doing` e priorizar no cycle. Antes disso, deixar parado — implementar sem credenciais reais é tempo perdido (smoke contract test mockado dá falso-positivo).
