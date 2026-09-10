@php
    $contoh = $this->contoh;

    /*
     * Judul dibuat sependek mungkin dan deskripsi diringkas satu kalimat,
     * dengan kata kunci ditebalkan agar bisa dipindai sekilas.
     * Isi HTML di sini ditulis tetap di berkas ini, bukan dari masukan pengguna.
     */
    $langkah = [
        ['Siapkan referensi', 'Kategori, Merek, Kondisi, Cabang, Karyawan, Vendor, Jenis Lampiran. <strong>Diisi sekali</strong> di awal.'],
        ['Daftarkan barang', 'Menu <strong>Barang</strong>. Satu baris per jenis, <strong>dipakai berulang</strong>.'],
        ['Pesan ke vendor', 'Menu <strong>Pesanan Pembelian</strong>. Diajukan lalu <strong>disetujui</strong>.'],
        ['Terima barang', 'Menu <strong>Penerimaan Barang</strong>. Unit aset lahir di sini.'],
        ['Catat tagihan', 'Menu <strong>Faktur Vendor</strong>, lalu <strong>Pembayaran</strong>.'],
        ['Serahkan ke karyawan', 'Buat surat <strong>draf</strong>, lalu <strong>Terbitkan</strong>.'],
        ['Tarik, servis, lampirkan', 'Semua aksi dan berkas ada di halaman <strong>Ubah</strong> aset.'],
        ['Pantau laporan', 'Enam laporan, unduh <strong>XLSX</strong> atau <strong>PDF</strong>.'],
    ];

    $peran = [
        ['Admin Pusat', 'akses penuh', 'success', 'Melihat dan mengubah data seluruh cabang, menyetujui pesanan pembelian, membatalkan surat yang sudah terbit, memindahkan aset antar cabang, dan mengimpor data lama.'],
        ['Admin Cabang', 'terbatas cabang', 'info', 'Hanya melihat dan mengubah data pada cabangnya sendiri. Boleh mengajukan pesanan pembelian, tetapi persetujuannya di tangan Admin Pusat. Kolom Cabang terkunci otomatis saat mengisi formulir.'],
        ['Peninjau', 'baca saja', 'gray', 'Hanya membaca data dan laporan, tanpa bisa mengubah apa pun.'],
    ];

    $aturan = [
        ['Barang masih dipegang seseorang. Tarik kembali terlebih dahulu.', 'Muncul saat memindahkan aset antar cabang. Yang boleh dikirim hanya unit yang ada di gudang, jadi tarik dulu dari pemegangnya.'],
        ['Masih ada servis berjalan; selesaikan dulu.', 'Satu aset hanya boleh punya satu catatan servis terbuka. Tutup servis sebelumnya lewat tombol Selesaikan di menu Servis Aset.'],
        ['Karyawan masih memegang sekian aset. Tarik semua terlebih dahulu.', 'Muncul saat menonaktifkan karyawan. Pakai tombol Tarik Semua Aset di menu Karyawan agar tidak ada aset menggantung.'],
        ['Hanya dokumen draft yang bisa diterbitkan.', 'Surat yang sudah terbit tidak bisa diterbitkan ulang. Bila isinya keliru, batalkan suratnya (khusus Admin Pusat), lalu buat surat baru.'],
        ['Barang tidak bisa diservis pada status ini.', 'Aset yang sedang dalam perjalanan antar cabang atau sudah dilepas tidak bisa dimasukkan servis.'],
        ['Nomor seri aset ... belum diisi; lengkapi dulu sebelum diserahkan.', 'Serial boleh dikosongkan saat barang diterima, tetapi wajib terisi sebelum unit diserahkan, dikirim antar cabang, atau ditinggal di tempat servis. Isi lewat Aset → Ubah.'],
        ['Aset ... masih dipegang ...; tarik kembali terlebih dahulu.', 'Satu unit hanya bisa dipegang satu orang. Tarik dulu dari pemegang sebelumnya.'],
        ['PO yang Anda ajukan sendiri harus disetujui orang lain.', 'Pemisahan tugas: pengaju dan penyetuju pesanan tidak boleh orang yang sama. Minta Admin Pusat lain untuk menyetujui.'],
        ['Nomor Seri belum diisi.', 'Muncul saat menyetujui penerimaan barang. Lengkapi serial tiap unit lebih dulu; penerimaan ditolak seluruhnya sampai lengkap.'],
        ['Diterima sekian unit, sisa pesanan hanya sekian.', 'Jumlah yang diterima melebihi sisa pesanan. Periksa kembali jumlah barang yang benar-benar datang.'],
        ['Alokasi untuk faktur ... melebihi sisa tagihannya.', 'Pembayaran tidak boleh melebihi sisa faktur. Kelebihan bayar harus dicatat terpisah, belum didukung pada tahap ini.'],
        ['Hanya cabang tujuan yang bisa mengonfirmasi penerimaan.', 'Tombol Terima hanya muncul bagi admin cabang tujuan, atau bagi Admin Pusat.'],
        ['Ada draft surat atas nama karyawan ini; hapus atau ganti dulu.', 'Selesaikan atau hapus surat draf yang memuat karyawan tersebut sebelum menonaktifkannya.'],
    ];

    $istilah = [
        ['Barang', 'Jenis barang di katalog, misalnya "Lenovo ThinkPad T14". Satu barang bisa dibeli berkali-kali.'],
        ['Pesanan Pembelian (PO)', 'Dokumen pemesanan ke vendor. Bernomor setelah disetujui, dan isinya terkunci sejak diajukan.'],
        ['Penerimaan Barang (GR)', 'Dokumen barang datang. Unit aset lahir saat penerimaan ini disetujui.'],
        ['Faktur Vendor', 'Tagihan resmi dari vendor. Nomornya berasal dari vendor, bukan dibuat sistem.'],
        ['Arsip Pembelian', 'Lapisan biaya tiap unit: tanggal, harga satuan, vendor, dan garansi. Ditulis sistem saat penerimaan disetujui.'],
        ['Unit', 'Satu barang fisik, punya kode aset sendiri. Inilah yang diserahkan, diservis, dan dilepas.'],
        ['Tersedia', 'Unit yang ada di gudang, tidak sedang dipegang, dan statusnya boleh dipindahtangankan.'],
        ['Dipegang', 'Unit yang sedang berada di tangan karyawan.'],
        ['Dilepas', 'Unit yang sudah dihapusbukukan karena dijual, dibuang, dihibahkan, atau hilang.'],
        ['Draf', 'Surat serah terima yang belum diterbitkan. Belum bernomor dan belum memengaruhi stok.'],
        ['Terbit', 'Surat yang sudah resmi: nomor terbentuk, stok berpindah, PDF siap diunduh.'],
        ['Dalam Perjalanan', 'Aset yang sudah dikirim ke cabang lain tetapi belum dikonfirmasi diterima.'],
    ];

    $laporan = [
        ['Posisi & Nilai Aset', 'Rekap stok dan nilai aset, umur barang, garansi yang akan habis, serta aset yang belum pernah dipakai.'],
        ['Kepemilikan Aset', 'Daftar siapa memegang apa, termasuk penyaring khusus karyawan nonaktif yang masih memegang aset.'],
        ['Servis & Biaya', 'Lama pengerjaan dan biaya servis per aset atau per vendor, termasuk pekerjaan yang lewat estimasi.'],
        ['Mutasi & Pelepasan', 'Seluruh pergerakan aset per periode, nilai yang dihapusbukukan, dan kiriman antar cabang yang belum diterima.'],
        ['Stok per Cabang', 'Jumlah unit tiap barang di tiap cabang, dipilah menurut keadaannya.'],
        ['Hutang Vendor', 'Tagihan yang belum lunas, umur hutang, dan yang sudah lewat jatuh tempo.'],
    ];
@endphp

<x-filament-panels::page>
    {{--
        Halaman ini memakai CSS sendiri, bukan kelas utilitas Tailwind.
        Aplikasi tidak mengompilasi Tailwind (tidak ada node_modules), dan berkas
        CSS bawaan Filament hanya memuat kelas komponen .fi-*, sehingga kelas
        utilitas seperti grid-cols-5 atau bg-gray-50 tidak akan berpengaruh.
        Warna memakai variabel tema Filament agar ikut mode gelap.
    --}}
    @push('styles')
        <style>
            .panduan { display: flex; flex-direction: column; row-gap: var(--app-section-gap, 1.25rem); }

            .panduan p { margin: 0; font-size: 0.875rem; color: var(--gray-600); }
            .panduan p + p { margin-top: 0.75rem; }

            .panduan h3 {
                font-size: 0.9375rem;
                font-weight: 600;
                line-height: 1.4;
                color: var(--gray-950);
                margin: 1.25rem 0 0.5rem;
            }

            .panduan h3:first-child { margin-top: 0; }

            .panduan ul {
                list-style: disc;
                padding-inline-start: 1.125rem;
                margin: 0;
                font-size: 0.875rem;
                color: var(--gray-600);
            }

            .panduan li + li { margin-top: 0.625rem; }
            .panduan li > p { margin-top: 0.375rem; }
            .panduan strong { color: var(--gray-950); font-weight: 600; }

            .panduan-kode {
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.8125rem;
                background: var(--gray-100);
                border-radius: 0.25rem;
                padding: 0.0625rem 0.25rem;
            }

            /* Kartu alur kerja: nomor dan judul sebaris. */
            .panduan-alur {
                display: grid;
                gap: 0.75rem;
                grid-template-columns: 1fr;
            }

            @media (min-width: 40rem) {
                .panduan-alur { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (min-width: 64rem) {
                .panduan-alur { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            }

            .panduan-alur-item {
                background: var(--gray-50);
                border-radius: var(--radius-lg, 0.5rem);
                box-shadow: inset 0 0 0 1px var(--gray-200);
                padding: 0.875rem;
            }

            .panduan-alur-kepala {
                display: flex;
                align-items: center;
                gap: 0.5rem;

                /*
                 * Tinggi dikunci setinggi dua baris judul supaya deskripsi tiap
                 * kartu tetap mulai pada garis yang sama, meski ada judul
                 * yang turun ke baris kedua.
                 */
                min-height: 2.25rem;
                margin-bottom: 0.5rem;
            }

            .panduan-nomor {
                display: flex;
                align-items: center;
                justify-content: center;
                flex: none;
                width: 1.625rem;
                height: 1.625rem;
                border-radius: 9999px;
                background: var(--primary-600);
                color: #fff;
                font-size: 0.8125rem;
                font-weight: 700;
                line-height: 1;
                font-variant-numeric: tabular-nums;
            }

            .panduan-alur-judul {
                font-size: 0.875rem;
                font-weight: 600;
                line-height: 1.25;
                color: var(--gray-950);
                margin: 0;
            }

            .panduan-alur-item p {
                font-size: 0.8125rem;
                line-height: 1.5;
                color: var(--gray-500);
            }

            /* Peran pengguna */
            .panduan-peran + .panduan-peran { margin-top: 1.25rem; }
            .panduan-peran h3 { display: flex; align-items: center; gap: 0.5rem; margin-top: 0; }

            /* Daftar pesan penolakan */
            .panduan-pesan {
                border-inline-start: 2px solid var(--warning-500);
                padding-inline-start: 0.75rem;
            }

            .panduan-pesan + .panduan-pesan { margin-top: 1.25rem; }
            .panduan-pesan h3 { margin-top: 0; }

            :is(.dark, .dark *) .panduan p,
            :is(.dark, .dark *) .panduan ul { color: var(--gray-300); }

            :is(.dark, .dark *) .panduan h3,
            :is(.dark, .dark *) .panduan strong,
            :is(.dark, .dark *) .panduan-alur-judul { color: #fff; }

            :is(.dark, .dark *) .panduan-alur-item {
                background: rgba(255, 255, 255, 0.05);
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
            }

            :is(.dark, .dark *) .panduan-kode { background: rgba(255, 255, 255, 0.1); }
        </style>
    @endpush

    <div class="panduan">
        <x-filament::section>
            <x-slot name="heading">Alur kerja singkat</x-slot>
            <x-slot name="description">Lima langkah utama, berurutan dari kiri ke kanan.</x-slot>

            <div class="panduan-alur">
                @foreach ($langkah as $i => [$judul, $keterangan])
                    <div class="panduan-alur-item">
                        <div class="panduan-alur-kepala">
                            <span class="panduan-nomor">{{ $i + 1 }}</span>
                            <h3 class="panduan-alur-judul">{{ $judul }}</h3>
                        </div>
                        {{-- Teks tetap dari berkas ini; hanya <strong> untuk kata kunci. --}}
                        <p>{!! $keterangan !!}</p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">Peran pengguna</x-slot>
            <x-slot name="description">Menentukan data mana yang bisa dilihat dan diubah, serta siapa yang berhak menyetujui.</x-slot>

            @foreach ($peran as [$nama, $label, $warna, $keterangan])
                <div class="panduan-peran">
                    <h3>
                        {{ $nama }}
                        <x-filament::badge :color="$warna" size="xs">{{ $label }}</x-filament::badge>
                    </h3>
                    <p>{{ $keterangan }}</p>
                </div>
            @endforeach
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">1. Dari pesanan sampai jadi aset</x-slot>

            <h3>Tiga lapis yang perlu dipahami</h3>

            <p>Pencatatan dipisah menjadi tiga supaya tiap data tersimpan di tempat yang benar:</p>

            <ul>
                <li><strong>Barang</strong> — jenis barangnya, misalnya Lenovo ThinkPad T14. Diisi sekali, lalu dipakai berulang oleh setiap pembelian.</li>
                <li><strong>Pembelian</strong> — satu baris faktur: tanggal, harga satuan, vendor, dan garansi.</li>
                <li><strong>Unit</strong> — barang fisiknya. Satu unit satu baris, punya kode aset dan nomor seri sendiri. Inilah yang diserahkan, diservis, dan dilepas.</li>
            </ul>

            <h3>Mendaftarkan barang baru</h3>

            <ul>
                <li>Buka <strong>Manajemen Aset → Barang</strong>, lalu klik <strong>Buat</strong>.</li>
                <li>Isi kategori, merek, dan tipe. Spesifikasi di sini berlaku untuk semua unit barang tersebut.</li>
                <li>Cukup sekali. Pembelian berikutnya tinggal memilih barang yang sama.</li>
            </ul>

            <h3>Memesan ke vendor</h3>

            <ul>
                <li>Buka <strong>Pengadaan → Pesanan Pembelian</strong>, lalu klik <strong>Buat</strong>.</li>
                <li>Pilih vendor. Syarat pembayarannya terisi otomatis dari data vendor, dan masih boleh diubah untuk pesanan ini saja.</li>
                <li>Tambahkan barang beserta jumlah, harga satuan, PPN, dan lama garansi. PPN diisi per baris karena satu pesanan sering memuat barang kena pajak dan tidak.</li>
                <li>
                    Simpan sebagai draf, lalu klik <strong>Ajukan</strong>.
                    <p>Setelah diajukan, isinya terkunci. Admin Pusat yang menyetujui, dan <strong>tidak boleh orang yang sama dengan pengajunya</strong>.</p>
                </li>
                <li>Saat disetujui, nomor PO terbit — contohnya <span class="panduan-kode">{{ $contoh['nomorPo'] }}</span> — dan pesanan siap dikirim ke vendor.</li>
            </ul>

            <p>Kalau isinya keliru sebelum disetujui, penyetuju bisa menekan <strong>Kembalikan ke Draf</strong> beserta alasannya, lalu pengaju memperbaikinya.</p>

            <h3>Menerima barang</h3>

            <p>Di sinilah unit aset lahir. Sebelum penerimaan disetujui, belum ada satu pun aset yang dibuat.</p>

            <ul>
                <li>
                    Cara tercepat: dari daftar <strong>Pesanan Pembelian</strong>, klik <strong>Buat Penerimaan</strong> pada pesanannya. Halaman penerimaan terbuka dengan pesanan, cabang, dan vendor sudah terisi, dan sisa unit yang belum diterima sudah menjadi baris — lengkap dengan harga serta garansinya.
                    <p>Bisa juga lewat <strong>Pengadaan → Penerimaan Barang → Buat</strong>, lalu pilih pesanannya sendiri. Setelah pesanan terpilih, tombol <strong>Tarik Sisa Pesanan</strong> di atas tabel unit melakukan hal yang sama.</p>
                </li>
                <li>Isi nomor surat jalan bila ada.</li>
                <li>
                    Barang yang datang kurang dari yang dipesan? Hapus baris yang tidak jadi datang. Sisanya tetap bisa diterima lewat penerimaan berikutnya.
                    <p>Nomor seri boleh dikosongkan selama masih draf, jadi penerimaan bisa disimpan setengah jadi dan dilanjutkan besok.</p>
                </li>
                <li>
                    Klik <strong>Setujui Penerimaan</strong>. Nomor penerimaan terbit — contohnya <span class="panduan-kode">{{ $contoh['nomorGr'] }}</span> — lalu unit aset dibuat sebanyak barisnya, masing-masing berkode sendiri seperti <span class="panduan-kode">{{ $contoh['kodeAset'] }}</span>, lengkap dengan harga dan garansi.
                    <p>Bila ada baris yang nomor serinya masih kosong, penerimaan ditolak <strong>seluruhnya</strong> dan semua kekurangan disebutkan sekaligus. Tidak ada unit yang telanjur lahir.</p>
                </li>
            </ul>

            <p>Menerima sebagian tidak masalah — pesanan otomatis berstatus <strong>Diterima Sebagian</strong>, dan sisanya bisa diterima lewat penerimaan berikutnya. Menerima melebihi sisa pesanan akan ditolak.</p>

            <p>Pembelian mendadak, hibah, atau retur vendor yang tidak punya pesanan tetap bisa dicatat: kosongkan kolom Pesanan, lalu isi barang, harga, dan garansinya sendiri.</p>

            <h3>Mencatat tagihan dan pembayaran</h3>

            <ul>
                <li>
                    Cara tercepat: dari daftar <strong>Penerimaan Barang</strong>, klik <strong>Buat Faktur</strong> pada penerimaan yang sudah disetujui. Vendor, cabang, penerimaan, dan nilai tagihannya sudah terisi — tinggal mengetik nomor faktur dari vendor.
                    <p>Bisa juga lewat <strong>Pengadaan → Faktur Vendor → Buat</strong>. Pilih vendor lebih dulu, lalu pilih penerimaan mana saja yang ditagih — boleh lebih dari satu, dan subtotalnya dijumlahkan sendiri.</p>
                </li>
                <li>Penerimaan yang sudah ditagih faktur lain tidak muncul lagi di daftar pilihan, jadi satu penerimaan tidak terbayar dua kali. Membatalkan fakturnya mengembalikan penerimaan itu ke daftar.</li>
                <li>
                    Cabang, syarat pembayaran, subtotal, dan PPN semuanya diturunkan dari pesanan di balik penerimaan itu — PPN dihitung dari persen pajak tiap barisnya.
                    <p>Syarat pembayaran diambil dari <strong>pesanannya</strong>, bukan dari data vendor yang berlaku hari ini: yang mengikat adalah syarat saat pesanan disetujui. Semua angkanya tetap boleh ditimpa bila faktur vendor berbeda.</p>
                </li>
                <li>Jatuh tempo dihitung otomatis dari syarat pembayaran. Nomor faktur ikut tersalin ke unit aset yang lahir dari penerimaan itu.</li>
                <li>
                    Untuk melunasi, klik <strong>Bayar</strong> pada fakturnya. Vendor, cabang, alokasi, dan nilainya sudah terisi sebesar sisa tagihan — tinggal pilih metode dan isi nomor bukti.
                    <p>Bisa juga lewat <strong>Pengadaan → Pembayaran Vendor</strong>. Satu pembayaran boleh dialokasikan ke beberapa faktur sekaligus, karena satu transfer sering melunasi beberapa tagihan; nilai pembayarannya dijumlahkan sendiri dari alokasi itu.</p>
                </li>
                <li>Status faktur berpindah sendiri: <strong>Belum Dibayar → Dibayar Sebagian → Lunas</strong>. Tidak ada yang perlu dipilih manual.</li>
            </ul>

            <p>Alokasi tidak boleh melebihi sisa tagihan, dan jumlah alokasi harus sama persis dengan nilai pembayaran. Keduanya mencegah kelebihan bayar yang sulit ditelusuri belakangan.</p>

            <h3>Memasukkan data aset yang sudah berjalan</h3>

            <p>Untuk inventaris lama yang masih tercatat di spreadsheet, pakai <strong>Manajemen Aset → Impor Aset</strong> daripada mengetik ulang satu per satu.</p>

            <ul>
                <li>Klik <strong>Unduh Berkas Contoh</strong> untuk melihat kolom yang dibaca, lalu isi datamu di sana.</li>
                <li>Unggah berkasnya, klik <strong>Periksa Berkas</strong>. Tidak ada data yang ditulis pada tahap ini.</li>
                <li>Hasil pemeriksaan menunjukkan berapa baris siap masuk, berapa yang bermasalah, dan apa masalahnya per baris.</li>
                <li>Bila sudah cocok, klik <strong>Jalankan Impor</strong>. Baris bermasalah dilewati, sisanya tetap masuk.</li>
            </ul>

            <p>Barang, vendor, dan batch pembelian dibuat otomatis bila belum ada. Baris dengan nomor faktur, tanggal, dan harga yang sama dianggap satu pembelian, jadi satu faktur berisi sepuluh unit tidak melahirkan sepuluh catatan pembelian.</p>

            <p>Keliru impor? Buka bagian <strong>Riwayat impor</strong> di halaman yang sama lalu klik <strong>Batalkan</strong>. Pembatalan hanya berlaku selama unit-unitnya belum diserahkan, diservis, atau dilepas — setelah itu riwayatnya tidak bisa dibalik.</p>

            <p>Menu ini hanya terbuka untuk Admin Pusat karena impor membuat barang dan pembelian sekaligus.</p>

            <h3>Nomor seri: boleh menyusul, tetapi ada batasnya</h3>

            <p>Serial boleh dikosongkan saat barang diterima — penerimaan sering terburu-buru dan memaksa mengisi di muka biasanya berakhir dengan orang mengetik tanda hubung.</p>

            <p>Tetapi untuk kategori yang mewajibkan serial, unit itu <strong>tidak bisa diserahkan, dikirim antar cabang, atau ditinggal di tempat servis</strong> sebelum serialnya terisi. Nomor itulah yang tercetak di surat serah terima dan tertulis di nota vendor.</p>

            <p>Lisensi ikut aturan yang sama, hanya sebutannya berbeda: kolomnya bernama <strong>Kunci Lisensi</strong> dan diisi kunci produk. Satu kunci berarti satu unit, jadi sepuluh lisensi berarti sepuluh baris dengan kunci masing-masing — sehingga ketahuan kunci mana yang dipakai siapa.</p>

            <p>Unit yang serialnya belum diisi ditandai <strong>Belum diisi</strong> pada kolom S/N di daftar aset, dan bisa disisir sekaligus lewat penyaring <strong>Nomor seri belum diisi</strong>. Halaman <strong>Pemeriksaan Data</strong> juga mendaftarnya.</p>

            <h3>Menambah stok barang yang sudah ada</h3>

            <p>Jangan membuat barang baru. Buat <strong>pesanan baru</strong> pada barang yang sama, lalu terima barangnya seperti biasa. Dengan begitu harga dan tanggal beli tiap pembelian tetap terpisah, sehingga nilai aset dan pengingat garansi tidak tercampur antar pembelian.</p>

            <p>Menu <strong>Arsip Pembelian</strong> hanya untuk melihat pembelian lama. Barisnya kini ditulis sistem setiap kali penerimaan barang disetujui, sebagai lapisan biaya tiap unit — tidak diisi manual lagi.</p>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">2. Menyerahkan aset ke karyawan</x-slot>

            <h3>Membuat draf surat</h3>

            <ul>
                <li>Buka <strong>Dokumen → Surat Serah Terima</strong>, lalu klik <strong>Buat</strong>.</li>
                <li>Isi pihak pertama (yang menyerahkan), pihak kedua (yang menerima), dan saksi bila ada.</li>
                <li>
                    Pada <strong>Daftar Barang</strong>, tambahkan satu baris per unit. Pilihan hanya memuat unit yang ada di gudang.
                    <p>Tidak perlu hafal kode aset. Ketik merek, tipe, nomor seri, atau kodenya, lalu pilih dari hasil pencarian.</p>
                    <p>Unit yang nomor serinya belum diisi ditandai ⚠ pada pilihan. Unit itu tetap bisa dipilih, tetapi suratnya akan ditolak saat diterbitkan sampai serialnya dilengkapi.</p>
                </li>
                <li>Simpan. Surat berstatus <strong>Draf</strong>, belum bernomor, dan belum memengaruhi stok.</li>
            </ul>

            <h3>Menerbitkan surat</h3>

            <ul>
                <li>
                    Bila isinya sudah benar, klik <strong>Terbitkan</strong>.
                    <p>Saat itulah nomor surat dibuat, contohnya <span class="panduan-kode">{{ $contoh['nomorSurat'] }}</span>, stok berpindah ke karyawan, dan PDF dibentuk.</p>
                </li>
                <li>Unduh berkasnya lewat tombol <strong>Unduh PDF</strong> untuk ditandatangani.</li>
            </ul>

            <h3>Bila isinya keliru</h3>

            <p>Selama masih draf, isi surat bebas diubah. Setelah terbit, koreksi hanya bisa lewat pembatalan oleh Admin Pusat, lalu dibuatkan surat baru. Nomor surat berjalan per cabang dan per bulan.</p>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">3. Menarik aset kembali</x-slot>

            <h3>Penarikan satuan</h3>

            <ul>
                <li>Buka aset bersangkutan lewat <strong>Aset → Ubah</strong>.</li>
                <li>Klik <strong>Tarik Kembali</strong>, lalu pilih karyawan asal dan jumlah unit yang dikembalikan.</li>
                <li>Isi <strong>Kondisi Saat Diterima</strong> agar penurunan kondisi barang ikut tercatat.</li>
            </ul>

            <h3>Karyawan berhenti bekerja</h3>

            <p>Pakai <strong>Karyawan → Tarik Semua Aset</strong> supaya seluruh barang yang dipegangnya kembali sekaligus. Karyawan baru bisa dinonaktifkan setelah tidak memegang aset apa pun.</p>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">4. Servis dan perbaikan</x-slot>

            <h3>Bila hanya sebagian unit yang bermasalah</h3>

            <p>Tidak perlu perlakuan khusus. Setiap unit punya barisnya sendiri, jadi memasukkan satu keyboard ke servis tidak memengaruhi unit lain yang sejenis — sisanya tetap muncul saat membuat surat serah terima.</p>

            <p>Cari unit yang bermasalah lewat nomor serinya di daftar aset, lalu buka <strong>Servis / Upgrade</strong> pada unit itu. Biaya servis dan lampirannya menempel pada unit yang benar.</p>

            <h3>Membuka catatan servis</h3>

            <ul>
                <li>Dari halaman <strong>Ubah</strong> aset, klik <strong>Servis / Upgrade</strong>.</li>
                <li>Pilih jenis servis dan pelaksananya. Kolom Vendor hanya muncul bila dikerjakan pihak luar.</li>
                <li>Isi <strong>Estimasi Selesai</strong>. Pekerjaan yang melewati tanggal ini tersaring di laporan servis.</li>
            </ul>

            <h3>Menutup setelah selesai</h3>

            <ul>
                <li>Buka <strong>Manajemen Aset → Servis Aset</strong>, lalu klik <strong>Selesaikan</strong>.</li>
                <li>Isi hasil servis, biaya jasa, biaya suku cadang, dan status barang setelah servis.</li>
            </ul>

            <p style="margin-top: 0.75rem;">Biaya yang diisi di sini menjadi dasar laporan biaya servis, termasuk untuk menilai aset mana yang biaya perawatannya sudah tidak sepadan.</p>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">5. Pindah cabang dan pelepasan</x-slot>

            <h3>Pindah cabang</h3>

            <ul>
                <li>Dari halaman Ubah aset, klik <strong>Pindah Cabang</strong>, lalu pilih cabang tujuan dan jumlahnya.</li>
                <li>Aset berstatus <strong>Dalam Perjalanan</strong> sampai cabang tujuan menekan tombol <strong>Terima</strong> pada daftar aset.</li>
                <li>Kiriman yang belum dikonfirmasi bisa dipantau di laporan Mutasi &amp; Pelepasan.</li>
            </ul>

            <h3>Pelepasan</h3>

            <ul>
                <li>Klik <strong>Dilepas / Dijual</strong>, lalu pilih alasannya: dijual, dibuang, dihibahkan, atau hilang.</li>
                <li>Unit yang dilepas keluar dari perhitungan stok dan nilai aset, tetapi riwayatnya tetap tersimpan.</li>
            </ul>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">6. Lampiran berkas</x-slot>

            <h3>Mengunggah berkas</h3>

            <ul>
                <li>Buka aset lewat <strong>Aset → Ubah</strong>, lalu pilih tab <strong>Lampiran</strong> di bagian bawah halaman.</li>
                <li>Klik <strong>Unggah Lampiran</strong>, lalu pilih <strong>Jenis Lampiran</strong>: Foto, Faktur, Kartu Garansi, Tanda Terima Servis, atau Lainnya.</li>
                <li>Berkas yang diterima: JPG, PNG, WEBP, dan PDF, maksimal 10 MB per berkas.</li>
            </ul>

            <h3>Kolom Terkait Servis</h3>

            <p>Kolom ini menentukan berkas menempel ke mana. Kosongkan bila berkasnya milik aset itu sendiri, misalnya foto unit, faktur pembelian, atau kartu garansi. Isi bila berkasnya milik satu kali servis, misalnya tanda terima servis atau nota vendor.</p>

            <p>Gunanya terasa saat satu aset sudah beberapa kali masuk servis: tanpa kolom ini, seluruh nota menumpuk jadi satu daftar tanpa ketahuan milik pekerjaan yang mana.</p>

            <p>Bila pilihannya kosong dan tertulis tidak ada pilihan tersedia, artinya aset tersebut memang belum pernah dibuatkan catatan servis. Kolom ini opsional, jadi lampiran tetap bisa disimpan.</p>

            <h3>Mengelola lampiran</h3>

            <ul>
                <li><strong>Unduh</strong> untuk mengambil berkasnya. Berkas disimpan tertutup, tidak bisa dibuka lewat tautan langsung.</li>
                <li><strong>Ubah</strong> hanya mengganti jenis lampiran dan kaitan servisnya. Untuk mengganti berkas, hapus lalu unggah ulang.</li>
                <li><strong>Hapus</strong> menghapus catatan sekaligus berkasnya. Tindakan ini tidak bisa dibatalkan.</li>
            </ul>

            <p>Daftar jenis lampiran bisa ditambah lewat <strong>Referensi → Jenis Lampiran</strong>. Jenis yang dinonaktifkan tidak lagi muncul saat mengunggah, tetapi lampiran lama yang memakainya tetap tersimpan.</p>
        </x-filament::section>

        <x-filament::section collapsible>
            <x-slot name="heading">7. Laporan</x-slot>

            <h3>Pilihan laporan</h3>

            <ul>
                @foreach ($laporan as [$nama, $guna])
                    <li>
                        <strong>{{ $nama }}</strong>
                        <p>{{ $guna }}</p>
                    </li>
                @endforeach
            </ul>

            <h3>Cara mengunduh</h3>

            <p>Atur penyaring lebih dulu, baru klik <strong>Unduh XLSX</strong> atau <strong>Cetak PDF</strong>. Hasil unduhan selalu mengikuti penyaring yang sedang aktif, bukan seluruh isi tabel.</p>

            <p>Berkas PDF akan terunduh lebih dulu, lalu bisa dibuka dan dicetak dari komputer.</p>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Pesan yang sering muncul dan artinya</x-slot>
            <x-slot name="description">Penolakan ini disengaja, untuk menjaga catatan aset dan hutang tetap masuk akal.</x-slot>

            @foreach ($aturan as [$pesan, $penjelasan])
                <div class="panduan-pesan">
                    <h3>&ldquo;{{ $pesan }}&rdquo;</h3>
                    <p>{{ $penjelasan }}</p>
                </div>
            @endforeach
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Menyesuaikan tampilan tabel</x-slot>
            <x-slot name="description">Berlaku di semua daftar, termasuk laporan.</x-slot>

            <ul>
                <li>
                    <strong>Kolom</strong> di kanan atas tabel mengatur kolom mana yang tampil.
                    <p>Seluruh kolom bisa disembunyikan, termasuk kolom kode aset. Bila tabel terasa kosong, periksa menu ini lebih dulu.</p>
                </li>
                <li>
                    <strong>Jumlah baris per halaman</strong> ada di kanan bawah tabel, bawaannya 10.
                    <p>Bisa diubah ke 25, 50, atau 100. Pilihan tersimpan untuk tabel tersebut, jadi tidak perlu diatur ulang setiap kali membuka halaman.</p>
                </li>
            </ul>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Istilah yang dipakai aplikasi</x-slot>

            <ul>
                @foreach ($istilah as [$kata, $arti])
                    <li>
                        <strong>{{ $kata }}</strong>
                        <p>{{ $arti }}</p>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Bila angka terlihat janggal</x-slot>

            <p>Buka <strong>Laporan → Pemeriksaan Data</strong>, lalu klik <strong>Jalankan Pemeriksaan</strong>.</p>

            <p>Halaman itu menelusuri seluruh unit aset, catatan servis, dan tagihan vendor, lalu melaporkan kejanggalan beserta kode dokumen yang perlu diperiksa. Delapan hal yang diawasi:</p>

            <ul>
                <li>Unit wajib bernomor seri tetapi belum diisi</li>
                <li>Unit tercatat dipegang seseorang padahal statusnya Gudang atau Baru Didaftarkan</li>
                <li>Unit sudah dilepas tetapi masih tercatat dipegang</li>
                <li>Unit belum punya status sama sekali</li>
                <li>Catatan servis masih terbuka padahal unitnya sudah dilepas</li>
                <li>Unit belum tertaut ke pembelian, sehingga hilang dari perhitungan nilai aset</li>
                <li>Nilai terbayar pada faktur tidak cocok dengan jumlah pembayarannya</li>
                <li>Faktur berstatus lunas padahal nilai terbayarnya kurang</li>
            </ul>

            <p style="margin-top: 0.75rem;">Dua yang terakhir mengawasi angka hutang. Nilai terbayar disimpan di faktur supaya daftar hutang tidak perlu menjumlah ulang tiap kali dibuka; pemeriksaan ini yang memastikan angka simpanan itu tidak pernah menyimpang dari pembayaran yang sebenarnya.</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
