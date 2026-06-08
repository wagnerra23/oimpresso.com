# Feedback Wagner — Lookup CNPJ sobrescreve dados, contatos só se vazio

> **Origem:** Wagner 2026-05-22, durante revisão do PR [#1419](https://github.com/wagnerra23/oimpresso.com/pull/1419) (fix consulta CNPJ no drawer 760).
> **Tipo:** regra de produto (estratégia de preenchimento automático).
> **Aplica a:** TODA integração com fonte de dados pública/oficial (BrasilAPI, ViaCEP, Sintegra futuro, SPC/Serasa futuro, RFB futuro).

## Regra

| Categoria | Comportamento | Razão |
|---|---|---|
| **Dados cadastrais oficiais** — razão social, fantasia, endereço, CEP, código IBGE, regime tributário, CNAE, situação cadastral | **SOBRESCREVE** sempre que o lookup achar valor | Receita / órgão público é fonte da verdade. Se user digitou errado (CNPJ trocado) ou cliente trocou endereço, o cadastro precisa ser corrigido pela fonte canônica. |
| **Contatos pessoais** — telefone, celular/mobile, WhatsApp, email, contato principal, cargo, link site | **SÓ preenche se em branco** (não sobrescreve) | Contato real digitado pelo user (ex: celular do Zé que cuida da gráfica) ≠ telefone público da Receita (que pode estar desatualizado, ser o do contador ou da matriz). Pisar destrói relacionamento real. |
| **Inscrição Estadual (IE)** | Não vem do BrasilAPI (responsabilidade Sintegra/SEFAZ — 27 sistemas estaduais diferentes). Manual no front por hora. | ADR futura avalia provider pago (cnpj.ws / CNPJá! / ReceitaWS Plus ~R$ [redacted Tier 0]/mês 5k consultas). |

## Caso real

Larissa @ ROTA LIVRE biz=4 (loja vestuário, [cliente-rotalivre](cliente-rotalivre.md)) cadastrando fornecedor:

- Digitou CNPJ errado por engano → razão social ficou "EMPRESA X LTDA" sem ser a real. Lookup deve corrigir.
- Tem o celular do Zé (sócio que cuida da operação) anotado manualmente. Lookup CNPJ traz "ddd_telefone_1" da matriz em SP. Não pode pisar.

## Aplicação

Local mais óbvio: `resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx::handleCnpjLookup`.

```ts
// Dados (sobrescreve)
if (novoNome) { setNome(novoNome); performSave('nome', novoNome, nome); }
if (novaFantasia) { setFantasia(novaFantasia); performSave('fantasia', novaFantasia, fantasia); }
// Endereço (sobrescreve via PATCH /endereco em batch)
const enderecoCandidato = {...}; // sem checagem de vazio
// Contato (só vazio via PATCH /contato em batch)
if (novoEmail && !contact.email) contatoCandidato.email = novoEmail;
if (novoMobile && !(contact.mobile ?? contact.tel)) contatoCandidato.mobile = novoMobile;
```

## Quando NÃO aplica

- **Lookup INICIADO pelo user explicitamente** (clica "Buscar CNPJ") → aplica esta regra.
- **Sincronização automática agendada futura** (ex: cron noturno revalida cadastros) → revisar política antes de aplicar (pode ser mais conservadora ainda, ex: só marca "drift detected" sem sobrescrever, gera task de revisão).

## Histórico de decisões

- **2026-05-22 v1**: Wagner aprovou inicialmente "só preenche vazio" (PR #1419 v1). Após revisão, mudou para esta política diferenciada (sobrescreve dados, conservador em contato).

## Refs

- ADR 0179 — Cliente drawer 760px
- ADR 0093 — Multi-tenant Tier 0 (lookup é dado público sem `business_id`, PATCH é tenant-scoped via `locateContact`)
- PR [#1419](https://github.com/wagnerra23/oimpresso.com/pull/1419) — implementação
- `Modules/Crm/Services/BrLookupService::lookupCnpj`
- `Modules/Crm/Http/Controllers/ClienteAutosaveController::endereco` e `::contato`
