<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('bpmn-manager.table_prefix').'processes', function (Blueprint $table) {
            $table->id();
            $table->json('metadata');
            $table->json('input');
            $table->mediumText('state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('bpmn-manager.table_prefix').'processes');
    }
};
