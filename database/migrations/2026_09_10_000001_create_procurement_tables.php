<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap A rancangan ERP: master syarat pembayaran dan dokumen pemesanan.
 * Rinciannya di docs/erp/02-pengadaan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('days')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        /*
         * Tabel vendor sudah punya contact, phone, dan address, jadi yang
         * ditambahkan hanya yang benar-benar belum ada.
         */
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('npwp')->nullable()->after('phone');
            $table->string('email')->nullable()->after('npwp');
            $table->foreignId('payment_term_id')->nullable()->after('email')->constrained('payment_terms');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            // Nomor baru terbit saat PO disetujui, mengikuti pola surat serah terima.
            $table->string('po_number')->nullable()->unique();
            $table->foreignId('branch_id')->constrained('branches');
            /*
             * Nullable: persetujuan sering turun sebelum tokonya dipilih —
             * yang disetujui adalah barang dan plafon harganya. Vendor
             * sebenarnya tercatat saat barangnya diterima.
             */
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms');
            $table->date('po_date');
            $table->date('expected_date')->nullable();
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'partial_receipt',
                'completed',
                'cancelled',
            ])->default('draft');
            // Nilai dibekukan agar PO lama tidak ikut berubah saat harga barang diperbarui.
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['branch_id', 'po_date']);
            $table->index('vendor_id');
            $table->index('status');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2)->default(0);
            // Pajak per baris: satu PO sering memuat barang kena PPN dan tidak.
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->integer('warranty_months')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_term_id');
            $table->dropColumn(['npwp', 'email']);
        });

        Schema::dropIfExists('payment_terms');
    }
};
