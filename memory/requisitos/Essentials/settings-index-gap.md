---
id: requisitos-essentials-settings-index-gap
tela: Essentials/Settings/Index (/hrm/settings)
prototipo: prototipo-ui/cowork/Wagner/hrm-extras.jsx
tela_viva: resources/js/Pages/Essentials/Settings/Index.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Essentials/Settings/Index

> **Fase 1 = PARIDADE.** `hrm-page.jsx:2` declara o porte reverso de `nav_hrm.blade`. A aba
> `hrm-config` é despachada em `hrm-page.jsx:549` para `X.Config`, que vive em
> `hrm-extras.jsx:244-290` (medido 2026-09-29). O próprio protótipo declara o porte em `:258-259`:
> *"Esta tela já é Inertia no main — `EssentialsSettingsController` renderiza `Essentials/Settings/Index`"*.
>
> ⚠️ **NÃO confundir de fonte.** Existe um segundo `Config` no bundle — `essenciais-extras.jsx:186`,
> rota `ess-config` — com 3 cards **de outro domínio** (*Tarefas* `:195` · *Documentos e memorandos* `:202` · *Lembretes e mensagens* `:208`), e um
> `Configuracoes.charter.md` no intake dos essenciais que descreve **essa outra tela**. O protótipo
> desambigua com todas as letras (`essenciais-extras.jsx:220-222`, nota *"Uma configuração, dois
> lugares"*): *"Tolerância de marcação, prefixo da folha e meta de venda continuam em `HRM ·
> Configurações` — o controller é o mesmo"* (2026-09-29: a frase é anterior à aposentadoria de
> 2026-09-24 — a tolerância de marcação não está mais em nenhuma das duas telas). Ancorar esta tela naquele charter seria usar protótipo
> que desenha OUTRA tela (§5 2026-08-10). A fonte aqui é o `hrm-extras.jsx::Config`.
>
> Este gap executa a thread `07-configuracoes-puxar.md` (`prototipo-ui/cowork/Wagner/cowork-inbox/hrm/playbook/07-configuracoes-puxar.md`),
> cujo §Estado marcava frescor 🔵 e mandava **não repintar**.

> ⚠️ **Fonte declarada = `hrm-extras.jsx`**, onde a tela de fato vive; o `hrm-page.jsx:549` apenas
> despacha a aba `hrm-config` para ela, e o `bundle_source` do charter aponta o page por ser o
> arquivo de entrada do bundle. Os dois são verdade e não conflitam.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cobertura das chaves de configuração | **PARIDADE COMPLETA — 5 de 5 (medido 2026-09-29).** As 5 chaves do protótipo (`hrm-extras.jsx:264` `:265` `:270` `:271` `:276`) estão todas no vivo, em `Settings/Index.tsx:22-26`: `leave_ref_no_prefix` :22 · `leave_instructions` :23 · `payroll_ref_no_prefix` :24 · `essentials_todos_prefix` :25 · `calculate_sales_target_commission_without_tax` :26. Em 2026-09-06 eram 10 de 10; as 5 de presença foram aposentadas nos dois lados em 2026-09-24 (ver a linha "Chave de presença"). | Nada — paridade. ⚠️ **Errata ao playbook (fato datado):** a thread 07 §Estado afirmava *"O protótipo tem **12 campos**"* contra 10 chaves vivas; medido em 2026-09-06, o protótipo tinha 10, não 12 — a diferença de 2 nunca existiu. Desde 2026-09-24 são 5 de cada lado. |
| Agrupamento em cards | **Diverge no recorte, não no conteúdo (medido 2026-09-29).** Vivo (3 cards): *Prefixos de referência* `:61` · *Instruções para afastamentos* `:103` · *Comportamentos* `:123`. Protótipo (3 cards): *Licenças* `hrm-extras.jsx:262` · *Folha e tarefas* `:268` · *Regras* `:274`. O vivo agrupa por **tipo de campo** (os 3 prefixos juntos); o protótipo, por **domínio** (licenças juntas). O 4º card de cada lado (tolerâncias de ponto) saiu com a aposentadoria de 2026-09-24. | Nada — **layout**, e a régua do playbook (`08-feriados-puxar.md` §3) é explícita: layout não vira pedido. Registrado para não virar "bug" na próxima leitura. |
| Chave de presença dentro do HRM | **Aposentada nos dois lados (medido 2026-09-29).** As 5 chaves de presença (`grace_*` ×4 + `is_location_required`) foram aposentadas por [W] em 2026-09-24 (ADR 0014 emenda, PR #7884): o docblock do `EssentialsSettingsController` (`:25-28`) registra que não migram para configuração do Ponto; o vivo deixa só o comentário `Settings/Index.tsx:118`, e o protótipo, a frase *"Tolerância de marcação e localização obrigatória saíram daqui"* (`hrm-extras.jsx:278`). | Nada — decidido por [W] em 2026-09-24 (ADR 0014 emenda). O RESÍDUO 5 que a thread 07 §3(ii) mandava registrar está fechado: as chaves não ficam no HRM nem migram para o Ponto. |
| "Permitir marcação via web" | **Ausente dos dois lados — por decisão já tomada.** Em 2026-09-06 o protótipo registrava, numa nota da tela, que a opção *"não está aqui: virou permissão de função (`allow_users_for_attendance_from_web`)"*; medido em 2026-09-29, essa nota saiu de `hrm-extras.jsx` (grep = 0) e a permissão só aparece no catálogo de papéis (`hrm-data.jsx:143`). A thread 07 §3(i) classifica como **chave morta** e manda **remover do protótipo**. | Nada — decidido. Não reabrir: a ausência no vivo está **certa**. |
| Guarda de permissão | **Diverge de mecanismo.** Protótipo `hrm-extras.jsx:257`: `SemPermissao` com frase explicando que *"o próprio controller recusa quem não é"* administrador. No vivo, o `.tsx` não tem bloqueio visível equivalente; medido 2026-09-29, a guarda está no back-end: `EssentialsSettingsController::authorizeAdmin` (`EssentialsSettingsController.php:92-100`), chamada em `edit` (`:42`) e `update` (`:61`), com `abort(403, 'Apenas administradores podem ver/editar as configurações do Essentials.')` (`:98`). | **Decidir.** A ausência no `.tsx` está correta — a guarda é do back-end, como o protótipo afirma. Fica para [W] só se a **mensagem com motivo** que o charter do intake exige precisa aparecer dentro da tela (como o `SemPermissao` do protótipo) em vez da página de 403 — como a 403 renderiza essa mensagem não foi medido. |
| Nota "Uma configuração, dois lugares" | **Ausente.** O protótipo fecha a tela apontando que existe outra aba de configuração (`essenciais-extras.jsx:220-222`). No vivo não há esse ponteiro. | **Decidir.** Só faz sentido **se e quando** a tela `ess-config` existir no produto — hoje ela não existe (`/essentials/settings` não está nas rotas medidas). Registrado para amarrar as duas decisões. |
