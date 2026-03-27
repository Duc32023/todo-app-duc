<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_user', function (Blueprint $table) {
            if (!Schema::hasColumn('task_user', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('task_user', 'returned_count')) {
                $table->unsignedInteger('returned_count')->default(0)->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_user', function (Blueprint $table) {
            if (Schema::hasColumn('task_user', 'returned_count')) {
                $table->dropColumn('returned_count');
            }
            if (Schema::hasColumn('task_user', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
