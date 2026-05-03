<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linka decisions a projects/parts. Decision já tem parent_decision_id (subtarefas);
 * agora também tem project_id e part_id para rastreabilidade estratégica.
 */
class LinkDecisionsToProjects extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_dual_brain_decisions', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('business_id');
            $table->unsignedBigInteger('part_id')->nullable()->after('project_id');
            $table->index('project_id', 'idx_dbd_project');
            $table->index('part_id', 'idx_dbd_part');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_dual_brain_decisions', function (Blueprint $table) {
            $table->dropIndex('idx_dbd_project');
            $table->dropIndex('idx_dbd_part');
            $table->dropColumn(['project_id', 'part_id']);
        });
    }
}
