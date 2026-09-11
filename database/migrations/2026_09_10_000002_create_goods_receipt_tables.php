<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B rancangan ERP: penerimaan barang.
 * Rinciannya di docs/erp/03-penerimaan.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('gr_number')->nullable()->unique();
            /*
             * Nullable: pembelian mendadak, hibah, dan retur vendor tidak punya
             * PO. Mewajibkannya justru mendorong orang memakai jalur lain.
             */
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->date('receipt_date');
            $table->string('delivery_document_number')->nullable();
            /*
             * Nomor nota atau nomor pesanan marketplace. Pembelian yang lunas
             * di muka tidak melahirkan faktur, jadi tanpa kolom ini unit
             * asetnya kehilangan rujukan ke bukti belinya.
             */
            $table->string('purchase_reference')->nullable();
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['branch_id', 'receipt_date']);
            $table->index('purchase_order_id');
            $table->index('status');
        });

        /*
         * Satu baris per unit fisik, bukan satu baris berisi jumlah.
         *
         * Dengan begitu nomor seri punya tempat menginap sebelum penerimaan
         * disetujui, dan jumlah yang diterima menjadi hasil cacahan — tidak
         * mungkin berbeda dari daftar serialnya.
         */
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items');
            $table->foreignId('product_id')->constrained('products');
            $table->string('serial_number')->nullable();
            $table->string('imei_1')->nullable();
            $table->string('imei_2')->nullable();
            // Disalin dari baris PO saat ada; diisi manual pada GR tanpa PO.
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->integer('warranty_months')->nullable();
            $table->foreignId('condition_id')->nullable()->constrained('conditions');
            // Terisi saat penerimaan disetujui; jadi penanda unit mana yang lahir.
            $table->foreignId('asset_id')->nullable()->constrained('assets');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('goods_receipt_id');
            $table->index('purchase_order_item_id');
            $table->index('asset_id');
        });

        /*
         * Penanda asal batch. purchase_batches berubah peran: dari tabel yang
         * diisi manusia menjadi lapisan biaya yang ditulis penerimaan barang.
         */
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->foreignId('goods_receipt_id')->nullable()->after('branch_id')->constrained('goods_receipts');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('goods_receipt_id');
        });

        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
