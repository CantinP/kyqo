<?php

use Kyqo\Database\Schema\Blueprint;
use Kyqo\Database\Schema\Migration;
use Kyqo\Database\Schema\SchemaBuilder;

/**
 * Create the base users table.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('users');
    }
};
