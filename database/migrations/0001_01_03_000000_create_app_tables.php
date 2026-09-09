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

        /*
         * Katalog barang. Satu baris per jenis barang, dipakai berulang oleh
         * setiap pembelian dan setiap unit. Memisahkan ini membuat pertanyaan
         * "berapa total keyboard MK270 yang kita punya" bisa dijawab tanpa
         * mencocokkan teks merek dan model.
         */
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('brand_id')->constrained('brands');
            $table->string('model')->nullable();
            $table->json('specifications')->nullable();
            $table->json('accessories')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'brand_id', 'model']);
        });

        /*
         * Satu baris per baris faktur pembelian. Tanggal, harga, vendor, dan
         * garansi berlaku untuk seluruh unit dalam batch yang sama, sehingga
         * pembelian ulang produk yang sama cukup menambah batch, bukan
         * menciptakan barang baru yang tampak seperti duplikat.
         */
        Schema::create('purchase_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->string('invoice_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->integer('warranty_months')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['product_id', 'purchase_date']);
            $table->index('invoice_number');
        });

        /*
         * Satu baris per unit fisik. Nama tabelnya tetap "assets" karena
         * transaksi, serah terima, servis, dan lampiran sudah menunjuk ke sini.
         *
         * Yang berubah: baris ini tidak lagi mewakili lot berisi banyak unit.
         * Karena itu kolom quantity dan seluruh kolom qty_* hilang; jumlah stok
         * dihitung dengan mencacah baris per status, bukan lewat aritmetika yang
         * harus terus disinkronkan. Status, kondisi, dan pemegang kini melekat
         * pada unit yang benar, sehingga satu unit masuk servis tidak lagi ikut
         * mengunci unit lain yang sehat.
         */
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 20)->unique();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('purchase_batch_id')->nullable()->constrained('purchase_batches');
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('serial_number')->nullable()->unique();
            $table->string('imei_1')->nullable();
            $table->string('imei_2')->nullable();
            // Diisi hanya bila unit ini menyimpang dari spesifikasi produknya,
            // misalnya setelah RAM-nya ditambah lewat upgrade.
            $table->json('specifications')->nullable();
            $table->foreignId('condition_id')->constrained('conditions');
            $table->foreignId('current_holder_id')->nullable()->constrained('employees');
            $table->foreignId('current_user_id')->nullable()->constrained('employees');
            $table->foreignId('current_status_id')->nullable()->constrained('asset_statuses');
            $table->date('retired_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['branch_id', 'product_id']);
            $table->index(['current_status_id', 'branch_id']);
            $table->index('current_holder_id');
            $table->index('purchase_batch_id');
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
            // Satu baris per unit; tidak ada lagi kolom jumlah karena satu aset
            // kini selalu berarti satu unit fisik.
            $table->foreignId('asset_id')->constrained('assets');
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
            // Tanpa kolom jumlah: satu transaksi selalu menyangkut satu unit.
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
        Schema::dropIfExists('purchase_batches');
        Schema::dropIfExists('products');
        Schema::dropIfExists('import_batches');
    }
};
