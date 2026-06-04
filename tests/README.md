# Panduan Pengujian UI Otomatis (UI Testing) - CodePulse.io

Direktori `tests/` ini digunakan untuk memisahkan seluruh kebutuhan pengujian dari kode aplikasi utama. Folder ini terbagi menjadi dua bagian:
1. **`samples/`**: Berisi file-file kode contoh yang digunakan untuk diunggah/dianalisis di dalam aplikasi CodePulse.
2. **`ui/`**: Berisi skrip pengujian UI otomatis (*automated UI testing*) menggunakan Selenium (Python) dan Playwright (JavaScript).

Berikut adalah panduan lengkap cara mempersiapkan, menjalankan, dan mengumpulkan tugas pengujian UI ini.

---

## 🛠️ Opsi A: Menggunakan Python + Selenium (Rekomendasi Akademik)

Sangat cocok jika tugas kuliah Anda mewajibkan penggunaan Python atau Selenium. Skrip ini akan membuka Google Chrome secara otomatis, mensimulasikan klik, mengisi form, dan mengambil tangkapan layar (screenshot) sebagai bukti uji.

### 1. Prasyarat
Pastikan Anda memiliki:
- Python 3.x terinstal di komputer.
- Google Chrome terinstal.

### 2. Instalasi Dependensi
Buka terminal/command prompt di folder root proyek ini, lalu jalankan perintah berikut untuk menginstal Selenium dan manajer webdriver otomatis:
```bash
pip install selenium webdriver-manager
```

### 3. Cara Menjalankan Pengujian
1. Jalankan server lokal PHP Anda terlebih dahulu:
   ```bash
   php -S localhost:8000
   ```
2. Di terminal baru, jalankan skrip pengujian:
   ```bash
   python tests/ui/test_ui_selenium.py
   ```
   *Catatan: Jika aplikasi Anda sudah di-hosting (misal di Vercel), Anda dapat mengujinya langsung dengan memasukkan URL hosting sebagai argumen:*
   ```bash
   python tests/ui/test_ui_selenium.py https://nama-hosting-anda.vercel.app
   ```

### 4. Hasil Pengujian
Hasil screenshot langkah pengujian otomatis akan disimpan di dalam folder:
📁 `tests/ui/screenshots/`
- `01_homepage.png` (Mengakses halaman awal)
- `02_analyzer_console.png` (Masuk ke menu analyzer)
- `03_pasted_code.png` (Memasukkan sampel kode uji)
- `04_analysis_results.png` (Tampilan dashboard hasil analisis)

---

## 🛠️ Opsi B: Menggunakan Node.js + Playwright (Modern & Cepat)

Jika Anda ingin menggunakan ekosistem JavaScript (Node.js) untuk pengujian otomatis.

### 1. Prasyarat
Pastikan Anda memiliki:
- Node.js terinstal di komputer.

### 2. Instalasi Dependensi
Buka terminal di direktori root proyek ini, lalu jalankan perintah:
```bash
npm install playwright
```

### 3. Cara Menjalankan Pengujian
1. Pastikan server lokal PHP (`localhost:8000`) sedang berjalan.
2. Jalankan perintah berikut di terminal:
   ```bash
   node tests/ui/test_ui_playwright.js
   ```
   *Atau jalankan pada URL hosting:*
   ```bash
   node tests/ui/test_ui_playwright.js https://nama-hosting-anda.vercel.app
   ```

### 4. Hasil Pengujian
Hasil screenshot akan tersimpan di folder `tests/ui/screenshots/` dengan akhiran `_pw.png` (misal `04_analysis_results_pw.png`).

---

## 📄 Panduan Pengumpulan Tugas (Tugas Kuliah / Praktikum)

Jika Anda perlu mengumpulkan tugas bagian **Pengujian UI / UI Testing**, berikut adalah hal-seputar pengumpulan yang perlu Anda ketahui:

### 1. Apakah Harus Menyertakan Kode Aplikasi Utama (`index.php`)?
**Jawab: Tidak perlu.**
Tugas pengujian UI fokus pada *skrip pengujiannya* (bagaimana cara Anda menguji tombol, navigasi, dan fungsionalitas visual web). Anda hanya perlu mengumpulkan:
1. Skrip pengujian otomatis (seperti `test_ui_selenium.py` atau `test_ui_playwright.js`).
2. Laporan pengujian (*Test Report*) yang berisi dokumentasi screenshot langkah-langkah pengujian yang sukses.

### 2. File Apa Saja yang Harus Disalin untuk Dikumpulkan?
Anda cukup menyalin folder **`tests/ui/`** ke tempat pengumpulan tugas Anda. Folder tersebut berisi:
- `test_ui_selenium.py` (Skrip uji otomatis Python)
- `test_ui_playwright.js` (Skrip uji otomatis JavaScript)
- Folder `screenshots/` (Bukti hasil screenshot pengujian otomatis yang berhasil dijalankan)

### 3. Contoh Isi Laporan Pengujian (Test Report)
Berikut adalah contoh struktur penulisan laporan jika Anda diminta membuat file PDF/Word:

> **JUDUL LAPORAN: PENGUJIAN BLACK-BOX UI OTOMATIS - CODEPULSE.IO**
> 
> **1. Deskripsi Pengujian**
> Pengujian dilakukan menggunakan metode Black-Box Testing secara otomatis dengan bantuan framework Selenium WebDriver (Python). Pengujian memvalidasi alur utama pengguna dalam menganalisis kode statis.
> 
> **2. Lingkungan Pengujian**
> - URL Target: `http://localhost:8000` (atau URL hosting)
> - Browser: Google Chrome (Automated Chrome Driver)
> 
> **3. Skenario Pengujian**
> 
> | ID | Deskripsi Skenario | Hasil yang Diharapkan | Status | Bukti Gambar |
> |---|---|---|---|---|
> | TC-01 | Mengakses Halaman Utama | Halaman terbuka, judul halaman mengandung kata "CodePulse". | PASS | `01_homepage.png` |
> | TC-02 | Berpindah ke menu Analyzer | Halaman berpindah ke tab console dan form input kode muncul. | PASS | `02_analyzer_console.png` |
> | TC-03 | Pengisian Kode Uji Coba | Kode program sampel terisi ke dalam text area dengan benar. | PASS | `03_pasted_code.png` |
> | TC-04 | Proses Eksekusi Analisis | Muncul visual Dashboard Hasil Analisis yang memuat metrik Halstead & McCabe. | PASS | `04_analysis_results.png` |
