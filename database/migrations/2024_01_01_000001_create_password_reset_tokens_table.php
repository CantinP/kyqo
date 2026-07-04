<?php

use Kyqo\Database\Schema\Blueprint;
use Kyqo\Database\Schema\Migration;
use Kyqo\Database\Schema\SchemaBuilder;

return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('password_reset_tokens');
    }
};
