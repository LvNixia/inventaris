<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap C rancangan ERP: tagihan vendor dan pembayarannya.
 * Rinciannya di docs/erp/04-tagihan.md dan docs/erp/05-pembayaran.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            // Nomor berasal dari vendor, bukan dibuat sistem, jadi keunikannya
            // dipasangkan dengan vendor: dua vendor boleh punya nomor sama.
            $table->string('invoice_number');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            // Disimpan agar daftar hutang tidak perlu menjumlah ulang pembayaran
            // setiap kali tampil; kecocokannya dijaga Pemeriksaan Data.
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->enum('status', ['unpaid', 'partial', 'paid', 'cancelled'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['vendor_id', 'invoice_number']);
            $table->index(['vendor_id', 'status']);
            $table->index('due_date');
            $table->index(['branch_id', 'invoice_date']);
        });

        /*
         * K4: satu faktur boleh mencakup beberapa penerimaan. Vendor sering
         * menagih beberapa pengiriman sekaligus dalam satu faktur bulanan.
         */
        Schema::create('purchase_invoice_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            // Nama indeks ditulis pendek: nama otomatis Laravel melewati batas
            // 64 karakter milik MySQL.
            $table->unique(['purchase_invoice_id', 'goods_receipt_id'], 'pir_invoice_receipt_unique');
            $table->index('goods_receipt_id');
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->nullable()->unique();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['transfer', 'cash', 'giro', 'lainnya'])->default('transfer');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            // Ditandai batal, bukan dihapus, supaya nomor pembayaran tidak
            // pernah dipakai ulang.
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['vendor_id', 'payment_date']);
            $table->index('payment_date');
        });

        /*
         * K5: satu pembayaran boleh melunasi beberapa faktur. Satu transfer
         * dialokasikan ke tiap faktur dengan porsinya masing-masing.
         */
        Schema::create('vendor_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained('vendor_payments')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['vendor_payment_id', 'purchase_invoice_id'], 'vpa_payment_invoice_unique');
            $table->index('purchase_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_allocations');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('purchase_invoice_receipts');
        Schema::dropIfExists('purchase_invoices');
    }
};
