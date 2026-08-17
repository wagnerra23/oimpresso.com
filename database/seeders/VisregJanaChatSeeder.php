<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pré-condição de render da tela `Jana/Chat` (/ia/conversa) no gate visual.
 *
 * ── POR QUE ESTE SEEDER EXISTE (não é "dado de enfeite") ────────────────────
 *
 * `ChatController::index` CRIA uma conversa quando o usuário não tem nenhuma
 * `status='ativa'` — com `iniciada_em => now()` e uma mensagem de briefing cujo
 * `created_at` é o instante REAL do render. Sem uma conversa semeada, portanto,
 * a tela fabrica o próprio fixture a cada execução.
 *
 * Isso é fatal pra baseline por causa de onde a hora é lida. O
 * `VISREG_FREEZE_CLOCK` congela `new Date()` SEM argumento no navegador — e o
 * shim documenta, em resources/visreg/freeze-clock.js:29, que `new Date(x)` COM
 * argumento segue real de propósito. `Pages/Jana/Chat.tsx:131-141` cai
 * exatamente nesse caso:
 *
 *     const dt   = new Date(m.created_at);   // ← COM argumento: instante REAL do dado
 *     const hora = dt.toLocaleTimeString(...) // ← "15:48" em CADA bolha
 *     const hoje = new Date();                // ← SEM argumento: congelado
 *
 * Ou seja: a hora exibida vem do DADO, não do relógio congelado. Deixar o
 * controller criar a conversa faria a baseline drifar A CADA MINUTO — não por
 * release, nem por dia. Seria reintroduzir, com granularidade pior, o mesmo
 * apodrecimento que o `VisregFinanceiroFlowSeeder` documenta.
 *
 * ── A INVARIANTE (mesma dos seeders irmãos) ─────────────────────────────────
 *
 * Toda data aqui é o INSTANTE CONGELADO, literal — nunca `now()`, nunca
 * relativa. Isso deixa os dois lados constantes:
 *
 *   - `hora`: `created_at` fixo → string de parede constante. O contexto do
 *     Playwright roda em UTC e a app em `config('app.timezone')`, então a hora
 *     exibida pode não ser "12:00" — o gate exige que ela seja CONSTANTE, não
 *     que bata com a do PHP (ver o aviso em config/visreg.php).
 *   - separador de dia: `dt` (dado) e `hoje` (congelado) são o MESMO instante →
 *     `sameDay` é sempre verdadeiro → renderiza "Hoje" pra sempre. Estável
 *     porque os DOIS lados são constantes, não porque a data é passada.
 *
 * ⚠️ NÃO troque `INSTANTE` por `now()`/`addDays()`, e mantenha-o igual ao
 * `VISREG_FREEZE_CLOCK` do workflow. Desalinhar os dois faz o separador de dia
 * virar `dd/mm` em vez de "Hoje" — muda o render sem ninguém mexer na tela.
 *
 * ⚠️ As duas mensagens têm `created_at` DISTINTOS de propósito:
 * `buildMensagensPayload` ordena por `created_at`, e empate deixa a ordem por
 * conta do MySQL — duas bolhas trocando de lugar entre execuções é flake de
 * baseline com cara de regressão.
 *
 * Só biz=1 (ADR 0101/0358 — nunca biz=4, cliente real). O biz=98 fica sem
 * conversa de propósito: é o tenant do estado `empty`.
 *
 * @see .github/workflows/visual-regression.yml (step "Seed demo tenant" + a sonda)
 * @see tests/Browser/visreg-screens.json       (o contrato da tela)
 * @see resources/visreg/freeze-clock.js:29     (por que a hora do dado escapa do shim)
 */
class VisregJanaChatSeeder extends Seeder
{
    /** Igual ao `VISREG_FREEZE_CLOCK` do workflow — ver o ⚠️ do docblock. */
    private const INSTANTE = '2026-06-11 12:00:00';

    /** 30s depois, só pra desempatar o `orderBy('created_at')`. */
    private const INSTANTE_RESPOSTA = '2026-06-11 12:00:30';

    private const BUSINESS_ID = 1;

    private const USER_ID = 1;

    private const TITULO = 'Conversa de prova visual';

    public function run(): void
    {
        // DB::table (não Eloquent) pelo mesmo motivo do VisregFinanceiroFlowSeeder:
        // sem global scope de business no caminho, o seed é literal e previsível.
        DB::table('jana_conversas')->updateOrInsert(
            [
                'business_id' => self::BUSINESS_ID,
                'user_id' => self::USER_ID,
                'titulo' => self::TITULO,
            ],
            [
                // 'ativa' é o que `ChatController::index` procura pra RETOMAR em vez
                // de criar. Arquivar esta conversa reativa a criação automática — e
                // com ela a baseline que drifa por minuto.
                'status' => 'ativa',
                'iniciada_em' => self::INSTANTE,
                'created_at' => self::INSTANTE,
                'updated_at' => self::INSTANTE,
            ],
        );

        $conversaId = DB::table('jana_conversas')
            ->where('business_id', self::BUSINESS_ID)
            ->where('user_id', self::USER_ID)
            ->where('titulo', self::TITULO)
            ->value('id');

        if ($conversaId === null) {
            throw new \RuntimeException(
                'VisregJanaChatSeeder: conversa não persistiu — a tela Jana/Chat criaria o próprio fixture e a baseline drifaria por minuto.'
            );
        }

        // Duas bolhas: `user` (direita) e `assistant` (esquerda). Uma só cobriria
        // metade do vocabulário visual da thread.
        DB::table('jana_mensagens')->updateOrInsert(
            ['conversa_id' => $conversaId, 'role' => 'user'],
            [
                'content' => 'Como está o faturamento deste mês?',
                'created_at' => self::INSTANTE,
            ],
        );

        DB::table('jana_mensagens')->updateOrInsert(
            ['conversa_id' => $conversaId, 'role' => 'assistant'],
            [
                'content' => 'Este é um texto fixo de prova visual — o gate compara pixels, então a resposta não pode vir do modelo.',
                'created_at' => self::INSTANTE_RESPOSTA,
            ],
        );
    }
}
