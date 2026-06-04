const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

(async () => {
  const baseUrl = process.argv[2] || 'http://localhost:8000';
  console.log('=== Menjalankan Pengujian UI Otomatis (Playwright JavaScript) ===');
  console.log(`Target URL: ${baseUrl}\n`);

  const screenshotDir = path.join(__dirname, 'screenshots');
  if (!fs.existsSync(screenshotDir)) {
    fs.mkdirSync(screenshotDir, { recursive: true });
  }

  // Luncurkan browser Chromium
  const browser = await chromium.launch({ headless: false }); // Ubah menjadi headless: true jika ingin menjalankan di background
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 }
  });
  const page = await context.newPage();

  try {
    // 1. Buka halaman utama
    console.log('[LANGKAH 1] Membuka halaman utama...');
    await page.goto(baseUrl);
    const title = await page.title();
    if (!title.includes('CodePulse')) {
      throw new Error(`Judul halaman salah: ${title}`);
    }
    console.log('   ✔ Halaman utama berhasil dibuka.');
    await page.screenshot({ path: path.join(screenshotDir, '01_homepage_pw.png') });
    console.log('   ✔ Screenshot disimpan di tests/ui/screenshots/01_homepage_pw.png');

    // 2. Navigasi ke Analyzer Console
    console.log('\n[LANGKAH 2] Navigasi ke Analyzer Console...');
    await page.click('#nav-analyzer');
    await page.waitForTimeout(1500); // Tunggu animasi transisi selesai
    const isPasteVisible = await page.isVisible('#panel-input-paste');
    if (!isPasteVisible) {
      throw new Error('Panel input paste tidak terlihat setelah tab diklik');
    }
    console.log('   ✔ Berhasil berpindah ke tab Analyzer Console.');
    await page.screenshot({ path: path.join(screenshotDir, '02_analyzer_console_pw.png') });

    // 3. Memasukkan kode uji coba
    console.log('\n[LANGKAH 3] Memasukkan kode PHP sampel ke text area...');
    await page.fill('#code', '');
    const sampleCode = `<?php
function hitungLuasLingkaran($jariJari) {
    $pi = 3.14159;
    if ($jariJari <= 0) {
        return 0;
    }
    return $pi * $jariJari * $jariJari;
}
echo hitungLuasLingkaran(7);
?>`;
    await page.fill('#code', sampleCode);
    await page.screenshot({ path: path.join(screenshotDir, '03_pasted_code_pw.png') });
    console.log('   ✔ Kode berhasil diinput.');

    // 4. Menjalankan Analisis
    console.log('\n[LANGKAH 4] Mengklik tombol \'Jalankan Analisis Kode\'...');
    await page.click('button[type="submit"]');

    // 5. Memverifikasi Hasil Analisis
    console.log('\n[LANGKAH 5] Memverifikasi tampilan dashboard hasil analisis...');
    // Tunggu selektor hasil analisis aktif
    await page.waitForSelector('#results-panel-overview', { state: 'visible', timeout: 15000 });
    
    const isResultsVisible = await page.isVisible('#main-view-results');
    if (!isResultsVisible) {
      throw new Error('Dashboard hasil analisis tidak muncul.');
    }
    console.log('   ✔ Hasil analisis terdeteksi sukses dimuat.');
    
    await page.waitForTimeout(2000); // Tunggu render selesai
    await page.screenshot({ path: path.join(screenshotDir, '04_analysis_results_pw.png') });
    console.log('   ✔ Screenshot hasil analisis disimpan di tests/ui/screenshots/04_analysis_results_pw.png');

    const overviewText = await page.innerText('#results-panel-overview');
    
    console.log('\n=== Ringkasan Hasil Pengujian ===');
    if (!overviewText.includes('TOTAL FILES')) throw new Error('Metrik TOTAL FILES tidak ditemukan');
    console.log('   ✔ TOTAL FILES terdeteksi di Dashboard.');

    if (!overviewText.includes('TOTAL FUNCTIONS')) throw new Error('Metrik TOTAL FUNCTIONS tidak ditemukan');
    console.log('   ✔ TOTAL FUNCTIONS terdeteksi di Dashboard.');

    if (!overviewText.includes('AVERAGE COMPLEXITY')) throw new Error('Metrik AVERAGE COMPLEXITY tidak ditemukan');
    console.log('   ✔ AVERAGE COMPLEXITY terdeteksi di Dashboard.');

    if (!overviewText.includes('ESTIMATED BUGS')) throw new Error('Metrik ESTIMATED BUGS tidak ditemukan');
    console.log('   ✔ ESTIMATED BUGS terdeteksi di Dashboard.');
    console.log('=================================');

    console.log('\nSemua skenario pengujian UI berhasil lolos pengujian (PASS)!');

  } catch (error) {
    console.error(`\n[GAGAL] Pengujian UI mengalami error: ${error.message}`);
    try {
      await page.screenshot({ path: path.join(screenshotDir, 'error_state_pw.png') });
      console.log('   ✔ Screenshot kondisi error disimpan di tests/ui/screenshots/error_state_pw.png');
    } catch (err) {
      console.error(`Gagal mengambil screenshot error: ${err.message}`);
    }
    process.exit(1);
  } finally {
    console.log('\nMenutup browser...');
    await browser.close();
    console.log('Pengujian selesai.');
  }
})();
