# App — Marcações a validar (tela 39) — fila do gestor

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/ponto/aprovacoes?estado=pendente|validada|recusada|todas` (padrão `pendente`) →
`{ itens:[{id, colaborador_nome, tipo, local_texto, marcada_em, nsr, gps_precisao_m, dispositivo, hash_curto, estado}], contadores:{pendente, validada, recusada, todas}, pode_recusar }`.

- Só marcações do celular (REP-P) **fora do geofence**, dos últimos 7 dias, mais nova primeiro.
  É a mesma fila da tela web `/ponto/aprovacoes` (`FilaGestorRepPService`, um lugar só).
- `id` é **uuid** (string), como em `ponto_marcacoes`. `tipo` ∈ `ENTRADA|ALMOCO_INICIO|ALMOCO_FIM|SAIDA`.
  `marcada_em` ISO 8601 com fuso. `hash_curto` = 8 primeiros caracteres do hash encadeado.
- `local_texto` = distância até o centro do geofence ("A 84,2 km do local de trabalho"); `null` sem geofence.
  `gps_precisao_m` vem `null`: o REP-P não grava a precisão do GPS na marcação.
- `contadores` ignoram o filtro (são os números dos chips). `pode_recusar` = tem `ponto.aprovacoes.manage`.
- `POST …/{id}/validar` → `200 { estado:"validada" }`: registro na trilha (`activity_log` `ponto.repp`);
  a marcação não muda. Trilha desligada → `503 { erro:"trilha_desligada" }`.
- `POST …/{id}/recusar` (sem corpo) → `200 { estado:"recusada", nsr_anulacao }`: grava uma
  **anulação** nova apontando a original (`Marcacao::anular()`, motivo fixo "Recusada na validação
  REP-P"). Nunca UPDATE/DELETE em `ponto_marcacoes` (Portaria 671/2021).
- Acesso = o do módulo Ponto (`ponto.access` ou papel admin/rh/gestor); recusar exige ainda
  `ponto.aprovacoes.manage`. Erros: `403 sem_permissao` · `404 nao_encontrado` (inexistente ou de
  outro business) · `409 ja_revisada` (já decidida) · `422 validacao` (estado inválido).
- Área `ponto_gestor` em `/api/app/inicio` (§6): mesma regra de acesso do GET.
