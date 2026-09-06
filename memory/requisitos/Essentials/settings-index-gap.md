---
id: requisitos-essentials-settings-index-gap
tela: Essentials/Settings/Index (/hrm/settings)
prototipo: prototipo-ui/cowork/hrm-extras.jsx
tela_viva: resources/js/Pages/Essentials/Settings/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Essentials/Settings/Index

> **Fase 1 = PARIDADE.** `hrm-page.jsx:2` declara o porte reverso de `nav_hrm.blade`. A aba
> `hrm-config` é despachada em `hrm-page.jsx:505` para `X.Config`, que vive em
> `hrm-extras.jsx:553-600+`. O próprio protótipo declara o porte em `:567-568`:
> *"Esta tela já é Inertia no main — `EssentialsSettingsController` renderiza `Essentials/Settings/Index`"*.
>
> ⚠️ **NÃO confundir de fonte.** Existe um segundo `Config` no bundle — `essenciais-extras.jsx:189`,
> rota `ess-config` — com 3 cards **de outro domínio** (prefixo de tarefa · upload · mural), e um
> `Configuracoes.charter.md` no intake dos essenciais que descreve **essa outra tela**. O protótipo
> desambigua com todas as letras (`essenciais-extras.jsx:223`, nota *"Uma configuração, dois
> lugares"*): *"Tolerância de marcação, prefixo da folha e meta de venda continuam em `HRM ·
> Configurações` — o controller é o mesmo"*. Ancorar esta tela naquele charter seria usar protótipo
> que desenha OUTRA tela (§5 2026-08-10). A fonte aqui é o `hrm-extras.jsx::Config`.
>
> Este gap executa a thread [`07-configuracoes-puxar.md`](../../../prototipo-ui/design-docs/cowork-inbox/hrm/playbook/07-configuracoes-puxar.md),
> cujo §Estado marcava frescor 🔵 e mandava **não repintar**.

> ⚠️ **Fonte declarada = `hrm-extras.jsx`**, onde a tela de fato vive; o `hrm-page.jsx:505` apenas
> despacha a aba `hrm-config` para ela, e o `bundle_source` do charter aponta o page por ser o
> arquivo de entrada do bundle. Os dois são verdade e não conflitam.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cobertura das chaves de configuração | **PARIDADE COMPLETA — 10 de 10.** As 10 chaves do protótipo (`hrm-extras.jsx:573-594`) estão todas no vivo, em `Settings/Index.tsx:22-31`: `leave_ref_no_prefix` :22 · `leave_instructions` :23 · `payroll_ref_no_prefix` :24 · `essentials_todos_prefix` :25 · `grace_before_checkin` :26 · `grace_after_checkin` :27 · `grace_before_checkout` :28 · `grace_after_checkout` :29 · `is_location_required` :30 · `calculate_sales_target_commission_without_tax` :31. | Nada — paridade. ⚠️ **Errata ao playbook:** a thread 07 §Estado afirma *"O protótipo tem **12 campos**"* contra 10 chaves vivas, e manda classificar os excedentes. **Medido hoje: o protótipo tem 10** (`:573` `:574` `:579` `:580` `:585` `:586` `:587` `:588` `:593` `:594`), não 12 — a diferença de 2 não existe, e o gap que a thread previa está **fechado**. O `_saida-07.md` que ela pede pode registrar isso. |
| Agrupamento em cards | **Diverge no recorte, não no conteúdo.** Vivo (4 cards): *Prefixos de referência* `:66` · *Instruções para afastamentos* `:108` · *Tolerâncias de ponto (minutos)* `:127` · *Comportamentos* `:172`. Protótipo (4 cards): *Licenças* `:571` · *Folha e tarefas* `:577` · *Tolerância de marcação* `:583` · *Regras* `:591`. O vivo agrupa por **tipo de campo** (prefixos juntos); o protótipo, por **domínio** (licenças juntas). | Nada — **layout**, e a régua do playbook (`08-feriados-puxar.md` §3) é explícita: layout não vira pedido. Registrado para não virar "bug" na próxima leitura. |
| Chave de presença dentro do HRM | **Presente nos dois lados, e é resíduo declarado.** As 5 chaves de presença (`grace_*` ×4 + `is_location_required`) vivem hoje em Configurações do HRM. A thread 07 §3(ii) antecipa: *"depois de D1 pertence ao Ponto → **decisão [W]**: fica em Settings do HRM ou migra"*, e o §PARAR SE avisa que isso *"vai aparecer: 5 das 10 chaves são de presença"*. | **Decidir.** É o **RESÍDUO 5** que a própria thread manda registrar e **não mover**. Este gap cumpre o registro; a decisão é de [W]. |
| "Permitir marcação via web" | **Ausente dos dois lados — por decisão já tomada.** O protótipo registra em `:596`: a opção *"não está aqui: virou permissão de função (`allow_users_for_attendance_from_web`)"*. A thread 07 §3(i) classifica como **chave morta** e manda **remover do protótipo**. | Nada — decidido. Não reabrir: a ausência no vivo está **certa**. |
| Guarda de permissão | **Diverge de mecanismo.** Protótipo `:566`: `SemPermissao` com frase explicando que *"o próprio controller recusa quem não é"* administrador. No vivo, o `.tsx` não tem bloqueio visível equivalente. | **Decidir.** ⚠️ Medir o `EssentialsSettingsController` antes de tratar como defeito — se a guarda está no back-end (o que a própria frase do protótipo afirma), a ausência no `.tsx` pode estar correta, e o que falta é só a **mensagem com motivo** que o charter do intake exige. |
| Nota "Uma configuração, dois lugares" | **Ausente.** O protótipo fecha a tela apontando que existe outra aba de configuração (`essenciais-extras.jsx:223`). No vivo não há esse ponteiro. | **Decidir.** Só faz sentido **se e quando** a tela `ess-config` existir no produto — hoje ela não existe (`/essentials/settings` não está nas rotas medidas). Registrado para amarrar as duas decisões. |
