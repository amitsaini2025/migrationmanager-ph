<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Session activity now uses staff_login_logs; drop the unused dedicated table.
     */
    public function up(): void
    {
        Schema::dropIfExists('staff_activity_logs');
    }

    public function down(): void
    {
        // Intentionally empty — dedicated activity table is retired.
    }
};
