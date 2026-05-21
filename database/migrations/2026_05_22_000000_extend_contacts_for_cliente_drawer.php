<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave B — ADR 0179 (Cliente drawer 760px substitui Show.tsx full-page).
 *
 * Estende `contacts` aditivamente com 16 colunas NULL pra suportar o drawer
 * 760 + 5 tabs cadastrais inline (Wave C). Reuso `App\Contact` UPOS canon
 * em vez de tabela paralela `clientes` -- Q3 Wagner 2026-05-21 (zero
 * migration de Model, biz=1/biz=4 preservam dados restaurados PR #1313).
 *
 * IDEMPOTENTE -- Schema::hasColumn check antes de cada add. Reversivel:
 * down() so dropa colunas novas desta wave (campos pre-existentes ficam).
 *
 * Multi-tenant: `business_id` ja existe em `contacts` (UPOS core) +
 * indexado. Esta migration adiciona indice composto (business_id, vip)
 * pra acelerar pill "VIP" listagem da Wave G.
 *
 * LGPD: nenhuma coluna nova entra em logOnly (App\Contact::$logOnly).
 * `tags` JSON e `favorito_users` JSON sao operacionais, nao PII.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Tipo de pessoa -- diferencia fluxo cadastral PF/PJ na Wave C.
            //   PF = Pessoa Fisica (CPF, nascimento, RG)
            //   PJ = Pessoa Juridica (CNPJ, fantasia, IE, cargo, contato principal)
            // Null = legado (cadastros antigos sem essa info). Default null preserva
            // backfill graceful sem trigger de logica nova em registros antigos.
            if (! Schema::hasColumn('contacts', 'tipo')) {
                $table->enum('tipo', ['PF', 'PJ'])->nullable()->after('type');
            }

            // Fantasia (PJ) -- nome fantasia comercial, distinto de razao social.
            // OBS: ja existe coluna `nome_fantasia` (PR #1316 restore BR fields).
            // Esta migration adiciona `fantasia` como alias semantico do protótipo
            // Cowork (HANDOFF_CLIENTES.md §2). Wave C decide qual o canon -- por
            // ora mantemos as duas pra evitar quebra de query Blade legacy.
            if (! Schema::hasColumn('contacts', 'fantasia')) {
                $table->string('fantasia', 255)->nullable();
            }

            // Inscricao estadual -- PJ. Ja existe `inscricao_estadual` (NFe canon)
            // mas Cowork blueprint usa `ie` (alias curto). Adicionamos como
            // espelho opcional; Wave C decide migracao.
            if (! Schema::hasColumn('contacts', 'ie')) {
                $table->string('ie', 20)->nullable();
            }

            // Nota: `rg` ja existe (PR #1316 restore BR fields) -- pulamos.

            // Nascimento (PF). Nullable porque PJ nao tem.
            if (! Schema::hasColumn('contacts', 'nascimento')) {
                $table->date('nascimento')->nullable();
            }

            // Cargo do contato principal (PJ). Ex: "Diretor Comercial", "Comprador".
            if (! Schema::hasColumn('contacts', 'cargo')) {
                $table->string('cargo', 80)->nullable();
            }

            // Telefone alternativo (mobile principal ja existe em `contacts.mobile`).
            // Wave C adiciona mascara `(00) 0 0000-0000` server-side.
            if (! Schema::hasColumn('contacts', 'tel2')) {
                $table->string('tel2', 20)->nullable();
            }

            // Canal preferido de contato. Larissa biz=4 ROTA LIVRE manda WhatsApp
            // por padrao; impressao corporativa biz=1 pode preferir email.
            if (! Schema::hasColumn('contacts', 'canal_preferido')) {
                $table->enum('canal_preferido', [
                    'whatsapp', 'email', 'telefone', 'presencial',
                ])->nullable();
            }

            // Tabela de preco padrao por cliente. UPOS canon usa `customer_group_id`
            // FK; aqui usamos enum simples por requisito Cowork (4 valores fixos).
            // Wave C decide se evolui pra FK customer_group ou mantem enum.
            if (! Schema::hasColumn('contacts', 'tabela_preco_padrao')) {
                $table->enum('tabela_preco_padrao', [
                    'padrao', 'varejo', 'atacado', 'parceiro',
                ])->nullable()->default('padrao');
            }

            // Forma de pagamento padrao -- usada como sugestao no checkout Sells.
            if (! Schema::hasColumn('contacts', 'pgto_padrao')) {
                $table->enum('pgto_padrao', [
                    'pix', 'boleto', 'cartao', 'dinheiro', 'transferencia',
                ])->nullable();
            }

            // Observacoes comerciais -- texto livre. Larissa anota "cliente paga
            // sempre em dinheiro, prefere ligar de manha".
            if (! Schema::hasColumn('contacts', 'obs_comercial')) {
                $table->text('obs_comercial')->nullable();
            }

            // Segmento de negocio -- usado em relatorios IA Brain B (Wave E).
            if (! Schema::hasColumn('contacts', 'segmento')) {
                $table->enum('segmento', [
                    'varejo', 'atacado', 'agencia', 'corporativo', 'evento', 'governo',
                ])->nullable();
            }

            // Tags JSON -- multi-select de 9 valores semanticos. Cowork blueprint:
            // ["vip","atencao","churn_risk","promotor","novo","fiel","problematico",
            //  "potencial","perdido"]. Wave G renderiza chips coloridas semanticas.
            if (! Schema::hasColumn('contacts', 'tags')) {
                $table->json('tags')->nullable();
            }

            // VIP boolean -- atalho pra filtro rapido + pill destacado na listagem.
            // Indexado composto com business_id pra acelerar query.
            if (! Schema::hasColumn('contacts', 'vip')) {
                $table->boolean('vip')->default(false);
            }

            // Favoritos pessoais por user_id -- localStorage no client + persistido
            // server-side via JSON array. Wave G renderiza Star pessoal.
            if (! Schema::hasColumn('contacts', 'favorito_users')) {
                $table->json('favorito_users')->nullable();
            }

            // Site corporativo (PJ). Cowork: "ver site rapido" botao opcional.
            if (! Schema::hasColumn('contacts', 'site_url')) {
                $table->string('site_url', 120)->nullable();
            }

            // Bairro (endereco). UPOS legacy nao tem -- so address_line_1/2.
            // Cowork protótipo §2.3 separa bairro de logradouro/complemento.
            // ViaCEP retorna campo "bairro" -- ClienteAutosaveController valida.
            if (! Schema::hasColumn('contacts', 'neighborhood')) {
                $table->string('neighborhood', 120)->nullable();
            }
        });

        // Indice composto (business_id, vip) -- acelera filtro VIP na listagem
        // Wave G. ALTER TABLE separado porque hasColumn nao serve pra hasIndex.
        // Try/catch porque alguns motores (MySQL <5.7 sem online-DDL) podem
        // travar; Hostinger e CT100 ambos MySQL 8.x = ok. NUNCA falha down().
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $table->index(['business_id', 'vip'], 'contacts_business_id_vip_index');
            });
        } catch (\Throwable $e) {
            // Indice ja existe ou motor rejeitou -- nao fatal. Wave G ainda funciona
            // (query degrade gracioso). Log inline.
            \Log::warning('extend_contacts_for_cliente_drawer: indice (business_id, vip) skip', [
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // Drop indice composto primeiro (antes das colunas).
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropIndex('contacts_business_id_vip_index');
            });
        } catch (\Throwable $e) {
            // Indice nao existe -- ok.
        }

        Schema::table('contacts', function (Blueprint $table) {
            // Drop apenas colunas que esta migration adicionou. RG NAO entra
            // (foi adicionado em PR #1316 -- nao e nosso). Idem `nome_fantasia`.
            $cols = [
                'tipo', 'fantasia', 'ie', 'nascimento', 'cargo', 'tel2',
                'canal_preferido', 'tabela_preco_padrao', 'pgto_padrao',
                'obs_comercial', 'segmento', 'tags', 'vip', 'favorito_users',
                'site_url', 'neighborhood',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
