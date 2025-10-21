<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string("unique_number")->unique();
            $table->string('business_name');
            $table->foreignId('business_type_id')->nullable()->constrained('business_types');
            $table->text('business_description')->nullable();
            $table->text('company_address')->nullable();
            $table->enum('status', ['pending', 'approved', 'suspended', 'blocked'])->default('pending');
            $table->string('license_number')->nullable();
            $table->string('business_logo')->nullable()->comment('Cloudinary base URL');
            $table->string('logo_cloudinary_id')->nullable();
            $table->string('docs_url')->nullable()->comment('Cloudinary document URL');
            $table->string('docs_cloudinary_id')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('preferred_payment_method')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->enum('gender', ['male', 'female', 'others'])->nullable();
            $table->text('address')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string("image")->nullable()->comment('Cloudinary base URL');
            $table->string("image_cloudinary_id")->nullable();
            $table->string('tax_certificate')->nullable();
            $table->decimal('commission_rate')->nullable()->comment('Reward budget percentage');
            $table->string('settlement_period')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable()->comment('Admin ID who approved');
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->text('products_services')->nullable();
            $table->unsignedBigInteger('corporate_member_id')->unique()->nullable()->comment('Linked corporate member account');
            $table->string('verification_documents')->nullable();
            $table->string('verification_docs_url')->nullable();
            $table->string('verification_cloudinary_id')->nullable();
            $table->enum('merchant_created_by', ['general_member', 'admin'])->default('admin')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('phone');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
