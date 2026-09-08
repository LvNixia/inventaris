<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('file_name');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->integer('total')->default(0);
            $table->integer('created_count')->default(0);
            $table->integer('updated_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('log_path')->nullable();
            $table->enum('status', ['preview', 'processing', 'done', 'failed', 'reverted'])->default('preview');
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 20)->unique();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('brand_id')->constrained('brands');
            $table->string('model')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('serial_number')->nullable()->unique();
            $table->string('imei_1')->nullable();
            $table->string('imei_2')->nullable();
            $table->json('specifications')->nullable();
            $table->json('accessories')->nullable();
            $table->foreignId('condition_id')->constrained('conditions');
            $table->date('purchase_date')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->string('invoice_number')->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->date('warranty_until')->nullable();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('split_from_asset_id')->nullable()->constrained('assets');
            $table->text('notes')->nullable();
            $table->foreignId('current_holder_id')->nullable()->constrained('employees');
            $table->foreignId('current_user_id')->nullable()->constrained('employees');
            $table->foreignId('current_status_id')->nullable()->constrained('asset_statuses');
            $table->integer('qty_out')->default(0);
            $table->integer('qty_in')->default(0);
            $table->integer('qty_writeoff')->default(0);
            $table->integer('qty_available')->default(0);
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['branch_id', 'category_id']);
            $table->index(['current_holder_id']);
        });

        Schema::create('handover_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->nullable()->unique();
            $table->date('document_date');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('first_party_id')->constrained('employees');
            $table->foreignId('second_party_id')->constrained('employees');
            $table->foreignId('witness_id')->nullable()->constrained('employees');
            $table->enum('status', ['draft', 'issued', 'cancelled'])->default('draft');
            $table->string('pdf_path')->nullable();
            $table->string('cancelled_pdf_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->boolean('legacy')->default(false);
            $table->foreignId('created_by')->constrained('users');

            $table->string('first_party_name')->nullable();
            $table->string('first_party_position')->nullable();
            $table->string('first_party_division')->nullable();
            $table->string('second_party_name')->nullable();
            $table->string('second_party_position')->nullable();
            $table->string('second_party_division')->nullable();
            $table->string('witness_name')->nullable();
            $table->string('witness_position')->nullable();
            $table->string('witness_division')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'document_date']);
        });

        Schema::create('handover_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handover_document_id')->constrained('handover_documents');
            $table->foreignId('asset_id')->constrained('assets');
            $table->integer('quantity');
            $table->foreignId('user_employee_id')->nullable()->constrained('employees');
            $table->string('item_name')->nullable();
            $table->string('serial_number')->nullable();
            $table->json('specifications')->nullable();
            $table->string('condition')->nullable();
            $table->text('remarks')->nullable();

            $table->unique(['handover_document_id', 'asset_id']);
        });

        Schema::create('asset_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('service_kind_id')->constrained('service_kinds');
            $table->enum('performed_by', ['internal', 'vendor']);
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->boolean('item_left')->default(false);
            $table->dateTime('started_at');
            $table->dateTime('expected_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('ticket_ref')->nullable();
            $table->text('complaint');
            $table->text('work_done')->nullable();
            $table->foreignId('service_result_id')->nullable()->constrained('service_results');
            $table->decimal('cost_service', 15, 2)->nullable();
            $table->decimal('cost_parts', 15, 2)->nullable();
            $table->date('service_warranty_until')->nullable();
            $table->json('spec_before')->nullable();
            $table->json('spec_after')->nullable();
            $table->foreignId('condition_after_id')->nullable()->constrained('conditions');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['asset_id', 'status']);
        });

        Schema::create('asset_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->enum('type', ['handover', 'return', 'status_change', 'disposal', 'branch_transfer', 'cancellation', 'correction', 'legacy']);
            $table->date('transaction_date');
            $table->integer('quantity')->nullable();
            $table->foreignId('from_employee_id')->nullable()->constrained('employees');
            $table->foreignId('to_employee_id')->nullable()->constrained('employees');
            $table->foreignId('user_employee_id')->nullable()->constrained('employees');
            $table->foreignId('status_id')->constrained('asset_statuses');
            $table->string('stock_direction');
            $table->foreignId('handover_document_id')->nullable()->constrained('handover_documents');
            $table->foreignId('from_branch_id')->nullable()->constrained('branches');
            $table->foreignId('to_branch_id')->nullable()->constrained('branches');
            $table->foreignId('condition_after_id')->nullable()->constrained('conditions');
            $table->foreignId('disposal_reason_id')->nullable()->constrained('disposal_reasons');
            $table->foreignId('service_id')->nullable()->constrained('asset_services');
            $table->text('notes')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches');
            $table->boolean('legacy')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['asset_id', 'transaction_date', 'id']);
            $table->index('to_employee_id');
            $table->index('from_employee_id');
            $table->index('handover_document_id');
        });

        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->integer('year');
            $table->integer('month');
            $table->integer('last_no')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'year', 'month']);
        });

        Schema::create('asset_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('service_id')->nullable()->constrained('asset_services');
            $table->foreignId('attachment_type_id')->constrained('attachment_types');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->integer('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_attachments');
        Schema::dropIfExists('document_counters');
        Schema::dropIfExists('asset_transactions');
        Schema::dropIfExists('asset_services');
        Schema::dropIfExists('handover_items');
        Schema::dropIfExists('handover_documents');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('import_batches');
    }
};
