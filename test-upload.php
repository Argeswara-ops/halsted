<?php
/**
 * File Uji Coba Analisis Halstead Metrics
 * Tempatkan file ini untuk diuji pada fitur "Upload File"
 */

class PustakaBuku {
    private $koleksi = [];

    // Menambah buku baru ke koleksi
    public function tambahBuku($id, $judul, $penulis, $harga) {
        $buku = [
            'id' => $id,
            'judul' => $judul,
            'penulis' => $penulis,
            'harga' => $harga
        ];
        $this->koleksi[] = $buku;
        return true;
    }

    // Mencari buku berdasarkan penulis
    public function cariBukuOlehPenulis($penulis) {
        $hasil = [];
        foreach ($this->koleksi as $item) {
            if (strtolower($item['penulis']) === strtolower($penulis)) {
                $hasil[] = $item;
            }
        }
        return $hasil;
    }

    // Menghitung total nilai aset buku
    public function hitungTotalHarga() {
        $total = 0;
        foreach ($this->koleksi as $item) {
            $total += $item['harga'];
        }
        return $total;
    }
}

// Inisialisasi dan pengujian objek
$perpustakaan = new PustakaBuku();
$perpustakaan->tambahBuku("B01", "Pemrograman PHP", "Budi Raharjo", 95000);
$perpustakaan->tambahBuku("B02", "Algoritma Struktur Data", "Budi Raharjo", 120000);
$perpustakaan->tambahBuku("B03", "Belajar JavaScript", "Liem", 85000);

$bukuBudi = $perpustakaan->cariBukuOlehPenulis("Budi Raharjo");
$totalAset = $perpustakaan->hitungTotalHarga();

echo "Buku karya Budi Raharjo berjumlah: " . count($bukuBudi) . " buku.\n";
echo "Total nilai seluruh aset buku: Rp" . $totalAset . "\n";
?>
