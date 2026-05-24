# CodePulse.io - Premium Static Code Analyzer

CodePulse.io adalah aplikasi analisis kode statis berbasis web premium untuk mengukur kompleksitas dan kualitas kode sumber (mendukung PHP, JavaScript, dan HTML). Aplikasi ini menggunakan metode metrik formal **Halstead Metrics** dan **McCabe Cyclomatic Complexity** untuk memproyeksikan beban kerja kognitif dan potensi bug.

## 🚀 Fitur Utama

- **Analisis Kompleksitas McCabe (Cyclomatic Complexity)**:
  Mengukur jumlah jalur eksekusi independen secara linier pada fungsi atau metode untuk mengidentifikasi percabangan kode yang berisiko tinggi.
  
- **Metrik Halstead (Halstead Metrics)**:
  Menganalisis token pemrograman yang terbagi menjadi *Operators* dan *Operands* untuk menghitung metrik esensial:
  - **Volume ($V$)**: Ukuran ukuran fisik kode secara informasional.
  - **Difficulty ($D$)**: Tingkat kesulitan dalam menulis atau memahami kode.
  - **Effort ($E$)**: Usaha mental yang diperlukan untuk mengimplementasikan kode.
  - **Time ($T$)**: Estimasi waktu pemahaman atau implementasi kode (dalam detik).
  - **Bugs ($B$)**: Estimasi jumlah bug bawaan dalam kode tersebut.

- **Fleksibilitas Input**:
  - **Paste Code**: Tempel kode sumber langsung pada text area yang disediakan.
  - **File Upload**: Unggah file kode tunggal (.php, .js, .html).
  - **Folder Upload**: Unggah seluruh folder proyek web (secara otomatis memindai file kode di dalamnya).

- **Ekspor Laporan**:
  - **Print PDF**: Layout khusus cetak yang bersih dan ramah printer untuk kebutuhan dokumentasi.
  - **Export Excel (CSV)**: Ekspor hasil analisis tabel ke dalam format spreadsheet/CSV.

- **Desain UI Premium & Modern**:
  Antarmuka dengan tema gelap (dark-mode) yang indah dilengkapi efek *Glassmorphism*, aksen warna gradien yang futuristik, serta performa rendering yang sangat cepat.

---

## 🛠️ Tech Stack

- **Backend Logic**: PHP (Analisis statis pencarian token, pemetaan fungsi, dan kalkulasi metrik).
- **Frontend & Styling**: 
  - HTML5 & CSS3 (Tailwind CSS CDN & Custom Vanilla CSS untuk visualisasi kaca/glassmorphism).
  - Google Fonts (Outfit untuk sans-serif & JetBrains Mono untuk font monospace).
- **JavaScript (ES6+)**:
  - Pengelolaan tab interaktif dan drag-and-drop file/folder.
  - Generator struktur Control Flow Graph (CFG) interaktif.
  - Ekspor laporan ke format spreadsheet CSV.
- **Serverless/Deployment**: Terintegrasi `vercel.json` menggunakan runtime `vercel-php`.

---

## 💻 Cara Menjalankan Secara Lokal

1. **Prasyarat**: Pastikan Anda telah menginstal PHP di sistem Anda (versi 7.4 atau yang lebih baru direkomendasikan).
2. **Menjalankan Server**:
   Buka terminal di direktori proyek ini dan jalankan perintah:
   ```bash
   php -S localhost:8000
   ```
3. **Akses Aplikasi**:
   Buka peramban (browser) Anda dan akses alamat `http://localhost:8000`.

---

## 📄 Struktur Proyek

```text
├── index.php            # Aplikasi utama (Frontend UI + Backend Engine Analisis)
├── test-upload.php      # File PHP sampel untuk pengujian upload
├── test-upload.js       # File JS sampel untuk pengujian upload
├── vercel.json          # Konfigurasi deployment serverless Vercel
├── README.md            # Dokumentasi proyek (file ini)
└── samples/             # Folder berisi file sampel tambahan untuk pengujian folder
    ├── factorial.js
    └── fibonacci.php
```
