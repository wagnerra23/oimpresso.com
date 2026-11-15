---
id: modules-associacoes-pages-associacoes-index-casos
casos: Associações · /associacoes/lista
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa da diretoria da rede e do representante + critério de aceite verificável (Dado/Quando/Então)
por_que: o representante enxergar ou editar outra associação é vazamento dentro da rede (D4); empresa vizinha na lista é vazamento Tier 0; documento apagado perde a prova da regularidade da associação.
owner: wagner
last_run: "2026-11-13"
last_run_ci: "_pendente_ — o trio nasce com a suíte de contrato; o veredito entra quando a lane verticais-pest (MySQL) rodar AssociacoesTelaContratoTest."
related_us: [US-ASSOC-002]
---

# Casos de Uso & Aceite — Associações (`/associacoes/lista`)

> **Âncora:** ADR 0451 D3 (dados), D4 (quem acessa) e D7 (prazo de 30 dias) e SPEC US-ASSOC-002. Padrão de tela PT-01 Lista. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-ASSOC-00 | chegar pelo menu; sem permissão, sem menu e 403 | must | `AssociacoesTelaContratoTest` |
| UC-ASSOC-01 | diretoria vê a rede; representante só a dele; a 99 nunca | must | `AssociacoesTelaContratoTest` |
| UC-ASSOC-02 | só a diretoria cria e troca representantes; representante só de usuário da empresa | must | `AssociacoesTelaContratoTest` |
| UC-ASSOC-03 | outra associação ou outra empresa: 404 em editar, diretoria e documento | must | `AssociacoesTelaContratoTest` |
| UC-ASSOC-04 | documento com arquivo no Arquivos; arquivo só abre no alcance | must | `AssociacoesTelaContratoTest` |
| UC-ASSOC-05 | tirar da lista com motivo, sem apagar; sem `associacoes.editar` não grava | must | `AssociacoesTelaContratoTest` |
| UC-ASSOC-06 | mudança de diretoria abre pendência; ata nova fecha | must | `AssociacoesTelaContratoTest` |
| UC-ASSOC-07 | prazo da config: pendente dentro, atrasada depois | must | `AssociacoesTelaContratoTest` |

---

## UC-ASSOC-00 · Chego na tela pelo menu · `must`
- **Aceite:** Dado usuário com `associacoes.access` e a empresa com o pacote · Quando abre o sistema · Então o grupo "Associações" está na sidebar e o item "Associações" leva a `/associacoes/lista` (200). Sem a permissão, o grupo some e a rota responde 403.
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-00`.
- **Status: 🧪**

## UC-ASSOC-01 · Cada um vê o seu alcance · `must`
- **Aceite:** Dado as associações A e B na empresa 98 e uma associação na 99 · Quando a diretoria abre a lista · Então vê A e B e não a da 99. Quando o representante de A abre · Então vê só A (ADR 0451 D4).
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-01`.
- **Status: 🧪**

## UC-ASSOC-02 · Cadastrar associação e representantes · `must`
- **Aceite:** Dado a diretoria com `associacoes.editar` · Quando cadastra uma associação com um usuário representante · Então ela nasce na empresa da sessão com o vínculo. Usuário de outra empresa é recusado. O representante não cria associação (403) e, ao editar a dele, a lista de representantes enviada é ignorada.
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-02`.
- **Status: 🧪**

## UC-ASSOC-03 · Outra associação e outra empresa ficam fora · `must`
- **Aceite:** Dado o representante de A · Quando tenta editar B, registrar diretoria de B ou anexar documento em B · Então 404 e nada muda. A empresa 99 recebe 404 em associação da 98.
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-03`.
- **Status: 🧪**

## UC-ASSOC-04 · Anexar documento · `must`
- **Aceite:** Dado o representante de A · Quando anexa o estatuto em PDF com validade · Então o documento grava na empresa 98 com o arquivo no `Modules/Arquivos` e abre por `/associacoes/documentos/{id}/arquivo` com `nosniff`. O representante de B e a empresa 99 recebem 404 no mesmo arquivo.
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-04`.
- **Status: 🧪**

## UC-ASSOC-05 · Tirar da lista sem apagar · `must`
- **Aceite:** Dado um documento na lista · Quando tiram sem motivo · Então erro de validação. Com motivo · Então grava quando, quem e o motivo, e a linha continua no banco. Sem `associacoes.editar`, anexar responde 403.
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-05`.
- **Status: 🧪**

## UC-ASSOC-06 · Mudança de diretoria e ata nova · `must`
- **Aceite:** Dado a associação A em dia · Quando registram que a diretoria mudou · Então fica pendente. Quando enviam um documento "Ata da diretoria" · Então a pendência fecha e `diretoria_atualizada_em` vira a data do envio (ADR 0451 D7).
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-06`.
- **Status: 🧪**

## UC-ASSOC-07 · Pendência atrasada pelo prazo da config · `must`
- **Aceite:** Dado `associacoes.prazo_ata_diretoria_dias` = 30 · Quando a mudança tem 30 dias · Então pendente (vence hoje). Com 31 dias · Então atrasada há 1 dia. O prazo vem da config: com 60 dias, a mesma data volta a pendente. Avisa; não bloqueia nada.
- **Teste:** `AssociacoesTelaContratoTest` — `UC-ASSOC-07`.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Escolher o contato do núcleo como representante (`representante_contact_id`) na ficha — o servidor já valida o contato da empresa; falta a busca na tela.

## Trilha do tempo
- 2026-11-13 · [W+C] trio nascido com a onda 3 da ADR 0451 (charter + casos + teste de contrato 98×99). Refs: UI-0013 · ADR 0264 G-1/G-2.
