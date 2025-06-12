<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_templates', 'whatsapp_text')) {
                $table->text('whatsapp_text')->nullable()->after('sms_body');
            }
            if (!Schema::hasColumn('notification_templates', 'auto_send_wa_notif')) {
                $table->boolean('auto_send_wa_notif')->default(0)->after('auto_send_sms');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            if (Schema::hasColumn('notification_templates', 'whatsapp_text')) {
                $table->dropColumn('whatsapp_text');
            }
            if (Schema::hasColumn('notification_templates', 'auto_send_wa_notif')) {
                $table->dropColumn('auto_send_wa_notif');
            }
        });
    }
};