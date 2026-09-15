---
sessao: "07"
titulo: Configurações — PUXAR (produção à frente)
dono: "[CC] read-only → [CL] só se houver gap"
base: 159e572dd448
prefixo: nenhum na 1ª fase (read-only). Se gap medido: resources/js/Pages/Essentials/Settings/Index.tsx (1 arquivo) OU hrm-page.jsx (`Config`) do build — nunca os dois na mesma thread
nao_toca: EssentialsSettingsController (a Page viva já usa) · as outras 6 Pages de Essentials · DS
depende: — (vaga 1)
---
# 07 · Configurações — puxar o vivo

## Estado
`/hrm/settings` **já é Inertia**: `resources/js/Pages/Essentials/Settings/Index.tsx` (lida em 04/09: `useForm` + 4 Cards + `Label htmlFor` em todos os campos + `toast`). **10 chaves reais:** `leave_ref_no_prefix · leave_instructions · payroll_ref_no_prefix · essentials_todos_prefix · grace_before_checkin · grace_after_checkin · grace_before_checkout · grace_after_checkout · is_location_required · calculate_sales_target_commission_without_tax`. O protótipo (`hrm-page.jsx`, `Config`) tem **12 campos**. Frescor 🔵 — **não repintar**.

## O que esta thread faz (medição, não aplicação)
1. Listar os 12 campos do protótipo (nome · chave · seção) lendo `hrm-page.jsx` no build servido.
2. Cruzar com as 10 chaves da Page viva.
3. Para cada campo só do protótipo, classificar: **(i)** chave morta — ex.: "permitir marcação via web" saiu das settings e virou permissão `essentials.allow_users_for_attendance_from_web` (`Presenca.charter` P1) → **remover do protótipo**; **(ii)** chave de presença (`grace_*`, `is_location_required`) → depois de D1 pertence ao Ponto → **decisão [W]**: fica em Settings do HRM ou migra; **(iii)** chave real que a Page não expõe → gap de 1 arquivo pro [CL].
4. Escrever `_saida-07.md` com a tabela campo × chave × classe × ação. Nada de código nesta fase.

## PARAR SE
- Classe (ii) aparecer (vai aparecer: 5 das 10 chaves são de presença) → registrar como RESÍDUO 5 para [W]; não mover nada.
- A Page viva tiver mudado desde 04/09 → reler antes de classificar (sha no `_saida`).

## Prova
- `_saida-07.md` com 12 linhas classificadas e a decisão pedida a [W]
- Se gap (iii): PR de 1 arquivo em `Pages/Essentials/Settings/Index.tsx` com teste `SettingsIndexTest` verde
