---
id: modules-superadmin-pages-superadmin-configuracoes-index-charter
page: /superadmin/settings
component: Modules/Superadmin/Resources/js/Pages/superadmin/Configuracoes/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/superadmin-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-01"
related_us: [US-SUPER-008]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
runbook: memory/requisitos/Superadmin/RUNBOOK-configuracoes.md
---

# Page Charter — /superadmin/settings

> Nasce `draft` na thread Superadmin/05, parte 2 (Blade → Inertia). Vai a `live` no PR pós-deploy,
> com o smoke. Backend: `SuperadminSettingsController@edit` e `@update`. Ver
> [RUNBOOK-configuracoes](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-configuracoes.md).

## Mission

Responde *"como ajusto o que vale para a plataforma inteira?"*: nome e idioma da aplicação, dados
de cobrança da assinatura, servidor de e-mail, gateways de pagamento, Pusher, backup/cron e JS/CSS
injetado. Persona única: [W], superadmin. Admin de negócio é barrado.

## Goals — Features (faz)

- Os mesmos campos do Blade, em seções (navegação lateral como no protótipo).
- Segredos (senhas, chaves secretas, tokens) aparecem só como **definido / não definido**; campo
  vazio mantém o valor gravado, campo preenchido regrava.
- "Descartar" volta ao estado carregado; "Salvar alterações" grava tudo de uma vez.
- Mostra o comando do cron e a versão do superadmin (só leitura).

## Non-Goals — Features (NÃO faz)

- **Não mostra o valor de nenhum segredo**, nem parcial (sem "últimos 4").
- **Não testa conexão** SMTP/gateway: não existe endpoint para isso.
- **Não tem editor rico** para termos, e-mail de boas-vindas e instruções offline: é HTML em texto.

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

- ❌ **Não mandar o valor de um segredo nas props** (`config.valores`), nem "para preencher o
  campo". A lista `SuperadminSettingsController::SEGREDOS` é a fronteira; chave nova de senha,
  secret ou token entra nela, não em `ENV_VISIVEIS`.
- ❌ **Não tratar segredo vazio como "apagar".** A tela nunca recebe o valor; se vazio apagasse, um
  "Salvar" sem tocar no campo derrubaria o SMTP e os gateways da plataforma.
- ❌ **Não escopar por `business_id`.** Configuração global por definição
  ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  §exceções Superadmin); a trava é o gate `superadmin`.
- ❌ **Não ler a tabela `system` inteira** para a tela: ela guarda outras chaves. A lista é fechada
  (`SISTEMA`).

## Divergências declaradas contra o protótipo

| Protótipo (`ViewConfig`, L1369) | Produção | Por quê |
|---|---|---|
| gateways como chaves liga/desliga (Pix, boleto, cartão) | credenciais de cada gateway + pagamento offline | o backend não tem "liga gateway"; Pix/boleto vivem no PaymentGateway, não aqui |
| "Última execução do cron" | comando do cron | não há registro da última execução nesta tela |
| "Backup diário" / "Retenção de backup" | destino do backup (local/Dropbox) | backend só guarda o disco |
| "Notificações ativas" (Pusher) | sem chave | o `update()` fixa `BROADCAST_DRIVER=pusher` |
| seção "Dados de cobrança" ausente | presente | campos que o Blade já editava (fatura da assinatura) |

## Refs

- Casos: [Index.casos.md](Index.casos.md)
- Protótipo: `prototipo-ui/cowork/Wagner/superadmin-page.jsx` → `ViewConfig()` (L1369)
