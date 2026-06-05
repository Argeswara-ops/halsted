# LAPORAN ANALISIS KODE STATIS & PENGUJIAN UI OTOMATIS
**Aplikasi:** CodePulse.io (Static Code Analyzer)  
**Metode Analisis:** Halstead Metrics & McCabe Cyclomatic Complexity (White-box Metrics)  
**Metode Pengujian:** Black-box Automated UI Testing  
**Tanggal:** 5 Juni 2026  

---

## 1. Pendahuluan
Laporan ini disusun untuk mendokumentasikan hasil analisis kode statis dan pengujian antarmuka pengguna (UI) otomatis pada aplikasi **CodePulse.io**. 

CodePulse.io adalah aplikasi berbasis web yang menganalisis kompleksitas dan kualitas kode sumber (PHP, JavaScript, dan HTML). Aplikasi ini menggunakan pendekatan **White-box Metrics** (Halstead & McCabe) untuk mendeteksi kompleksitas alur logika, usaha mental pemrograman, serta estimasi bug bawaan dalam file kode sumber tanpa menjalankan kode tersebut.

---

## 2. Struktur Proyek & Berkas Terkait
Berikut adalah berkas-berkas penting yang diuji dan digunakan dalam proses pengujian ini:
*   **Aplikasi Utama:** 
    *   [index.php](file:///c:/laragon/www/halsted/index.php) - Antarmuka pengguna (UI) dan visualisasi Control Flow Graph (CFG).
    *   [lib/Analyzer.php](file:///c:/laragon/www/halsted/lib/Analyzer.php) - Mesin utama analisis statis (Halstead & McCabe calculator).
*   **Skrip Pengujian UI:**
    *   [tests/ui/test_ui_selenium.py](file:///c:/laragon/www/halsted/tests/ui/test_ui_selenium.py) - Skrip uji otomatis menggunakan Python + Selenium.
    *   [tests/ui/test_ui_playwright.js](file:///c:/laragon/www/halsted/tests/ui/test_ui_playwright.js) - Skrip uji otomatis menggunakan Node.js + Playwright.
*   **Berkas Sampel Uji:**
    *   [tests/samples/test-upload.php](file:///c:/laragon/www/halsted/tests/samples/test-upload.php) - File kode PHP contoh untuk pengujian upload.

---

## 3. Landasan Teori (Metode Analisis Statis)

Aplikasi CodePulse.io menghitung dua metrik utama untuk mengukur kualitas kode:

### A. Kompleksitas Siklomatis McCabe (McCabe Cyclomatic Complexity)
Mengukur jumlah jalur eksekusi yang independen secara linear dalam suatu fungsi. Rumus dasar:
$$CC = P + 1$$
Di mana $P$ adalah jumlah titik keputusan (*decision points* seperti `if`, `while`, `for`, `case`, `&&`, `||`).
*   **Kategori Risiko:**
    *   **1 - 10 (Aman):** Kode sederhana, risiko rendah, mudah ditest.
    *   **11 - 20 (Sedang):** Kompleksitas menengah, risiko sedang.
    *   **> 20 (Tinggi / Risiko Tinggi):** Kode sangat kompleks, risiko tinggi, sulit dipelihara.

### B. Metrik Kompleksitas Halstead (Halstead Metrics)
Diukur berdasarkan jumlah token berupa Operator ($n_1, N_1$) dan Operand ($n_2, N_2$):
*   **Vocabulary ($n = n_1 + n_2$)**: Keberagaman token dalam kode.
*   **Length ($N = N_1 + N_2$)**: Ukuran fisik kode.
*   **Volume ($V = N \times \log_2(n)$)**: Kapasitas memori/informasi kode.
*   **Difficulty ($D = \frac{n_1}{2} \times \frac{N_2}{n_2}$)**: Tingkat kesulitan memahami/menulis kode.
*   **Effort ($E = D \times V$)**: Usaha mental yang diperlukan untuk membuat kode.
*   **Time ($T = \frac{E}{18}$)**: Estimasi waktu implementasi dalam satuan detik.
*   **Delivered Bugs ($B = \frac{V}{3000}$)**: Estimasi jumlah bug bawaan dalam sistem.

---

## 4. Rencana & Skenario Pengujian UI Otomatis
Pengujian UI otomatis disimulasikan menggunakan browser untuk memastikan alur navigasi dan analisis berjalan dengan sukses dari halaman utama hingga hasil kalkulasi muncul di dasbor.

### Tabel Skenario Uji (Test Cases)

| ID | Skenario Uji | Deskripsi Langkah | Hasil yang Diharapkan | Status | Bukti Gambar (Screenshot) |
| :--- | :--- | :--- | :--- | :---: | :--- |
| **TC-01** | Mengakses Halaman Utama | Membuka browser dan mengakses alamat `http://localhost:8000` | Halaman utama terbuka dengan judul "CodePulse.io" | **PASS** | `01_homepage.png` |
| **TC-02** | Navigasi ke Analyzer Console | Mengklik tombol atau tab navigasi "Analyzer Console" | Tab bergeser, input text area kode terlihat di layar | **PASS** | `02_analyzer_console.png` |
| **TC-03** | Pengisian Kode Sampel | Menyalin kode PHP sampel (fungsi perhitungan lingkaran) ke dalam input area | Kode terisi dengan benar di text area | **PASS** | `03_pasted_code.png` |
| **TC-04** | Eksekusi & Kalkulasi Analisis | Mengklik tombol "Jalankan Analisis Kode" | Dasbor hasil analisis muncul memuat metrik Halstead & McCabe beserta graf CFG | **PASS** | `04_analysis_results.png` |

---

## 5. Hasil Pengujian UI Otomatis
Berdasarkan eksekusi skrip pengujian otomatis ([test_ui_playwright.js](file:///c:/laragon/www/halsted/tests/ui/test_ui_playwright.js) / [test_ui_selenium.py](file:///c:/laragon/www/halsted/tests/ui/test_ui_selenium.py)), seluruh test case dinyatakan **Lolos (PASS)** dengan detail sebagai berikut:
1.  Aplikasi berhasil dibuka pada port lokal 8000.
2.  Input kode statis PHP berhasil diproses oleh parser [lib/Analyzer.php](file:///c:/laragon/www/halsted/lib/Analyzer.php).
3.  Metrik Volume, Difficulty, Effort, dan Estimated Bugs berhasil terhitung dan ditampilkan di layar.
4.  Diagram alir alur kontrol (Control Flow Graph) berhasil dirender secara interaktif untuk memvisualisasikan jalur logika kode.

---

## 6. Kesimpulan
Aplikasi **CodePulse.io** berfungsi dengan baik dalam melakukan analisis kode statis. Pengujian UI otomatis membuktikan bahwa integrasi antara antarmuka frontend berbasis HTML/CSS/JS dengan mesin kalkulasi backend PHP berjalan dengan lancar dan stabil. Penggunaan metrik McCabe dan Halstead pada sistem ini sangat efektif untuk membantu pengembang mengukur dan mengoptimalkan kualitas struktur internal kode program (*white-box testing framework*).
