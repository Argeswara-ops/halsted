import os
import sys
import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options

# Gunakan URL default localhost:8000, atau ambil dari argument command-line
base_url = "http://localhost:8000"
if len(sys.argv) > 1:
    base_url = sys.argv[1]

print(f"=== Menjalankan Pengujian UI Otomatis (Selenium Python) ===")
print(f"Target URL: {base_url}\n")

# Konfigurasi browser
chrome_options = Options()
# chrome_options.add_argument("--headless") # Hapus komentar jika ingin menjalankan tanpa membuka jendela browser
chrome_options.add_argument("--window-size=1920,1080")

# Inisialisasi WebDriver
try:
    from webdriver_manager.chrome import ChromeDriverManager
    from selenium.webdriver.chrome.service import Service
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=chrome_options)
except ImportError:
    # Fallback jika webdriver_manager tidak terinstall
    driver = webdriver.Chrome(options=chrome_options)

driver.implicitly_wait(10)

try:
    # Pembuatan folder screenshots jika belum ada
    os.makedirs("tests/ui/screenshots", exist_ok=True)

    # 1. Buka halaman utama
    print("[LANGKAH 1] Membuka halaman utama...")
    driver.get(base_url)
    
    # Verifikasi judul halaman
    assert "CodePulse" in driver.title
    print("   ✔ Halaman utama berhasil dibuka.")
    driver.save_screenshot("tests/ui/screenshots/01_homepage.png")
    print("   ✔ Screenshot halaman utama disimpan di tests/ui/screenshots/01_homepage.png")
    
    # 2. Navigasi ke Analyzer Console
    print("\n[LANGKAH 2] Navigasi ke Analyzer Console...")
    btn_analyzer = driver.find_element(By.ID, "nav-analyzer")
    btn_analyzer.click()
    time.sleep(1.5) # Tunggu transisi visual
    
    # Pastikan panel input paste terlihat
    panel_paste = driver.find_element(By.ID, "panel-input-paste")
    assert panel_paste.is_displayed()
    print("   ✔ Berhasil berpindah ke tab Analyzer Console.")
    driver.save_screenshot("tests/ui/screenshots/02_analyzer_console.png")
    
    # 3. Memasukkan kode uji coba
    print("\n[LANGKAH 3] Memasukkan kode PHP sampel ke text area...")
    textarea_code = driver.find_element(By.ID, "code")
    textarea_code.clear()
    
    sample_code = """<?php
function hitungLuasLingkaran($jariJari) {
    $pi = 3.14159;
    if ($jariJari <= 0) {
        return 0;
    }
    return $pi * $jariJari * $jariJari;
}
echo hitungLuasLingkaran(7);
?>"""
    textarea_code.send_keys(sample_code)
    driver.save_screenshot("tests/ui/screenshots/03_pasted_code.png")
    print("   ✔ Kode berhasil diinput dan screenshot disimpan.")
    
    # 4. Menjalankan Analisis
    print("\n[LANGKAH 4] Mengklik tombol 'Jalankan Analisis Kode'...")
    submit_button = driver.find_element(By.XPATH, "//button[@type='submit']")
    submit_button.click()
    
    # 5. Memverifikasi Hasil Analisis
    print("\n[LANGKAH 5] Memverifikasi tampilan dashboard hasil analisis...")
    # Tunggu sampai panel overview aktif
    WebDriverWait(driver, 15).until(
        EC.presence_of_element_located((By.ID, "results-panel-overview"))
    )
    
    # Verifikasi bahwa dashboard hasil analisis muncul
    results_tab = driver.find_element(By.ID, "main-view-results")
    assert results_tab.is_displayed()
    print("   ✔ Hasil analisis terdeteksi sukses dimuat.")
    
    time.sleep(2) # Tunggu rendering visual selesai
    driver.save_screenshot("tests/ui/screenshots/04_analysis_results.png")
    print("   ✔ Screenshot hasil analisis disimpan di tests/ui/screenshots/04_analysis_results.png")
    
    # Memeriksa teks di dalam dashboard hasil
    overview_panel = driver.find_element(By.ID, "results-panel-overview")
    overview_text = overview_panel.text
    
    print("\n=== Ringkasan Hasil Pengujian ===")
    assert "TOTAL FILES" in overview_text, "Metrik 'TOTAL FILES' tidak ditemukan"
    print("   ✔ TOTAL FILES terdeteksi di Dashboard.")
    
    assert "TOTAL FUNCTIONS" in overview_text, "Metrik 'TOTAL FUNCTIONS' tidak ditemukan"
    print("   ✔ TOTAL FUNCTIONS terdeteksi di Dashboard.")
    
    assert "AVERAGE COMPLEXITY" in overview_text, "Metrik 'AVERAGE COMPLEXITY' tidak ditemukan"
    print("   ✔ AVERAGE COMPLEXITY terdeteksi di Dashboard.")
    
    assert "ESTIMATED BUGS" in overview_text, "Metrik 'ESTIMATED BUGS' tidak ditemukan"
    print("   ✔ ESTIMATED BUGS terdeteksi di Dashboard.")
    
    print("=================================")
    print("\nSemua skenario pengujian UI berhasil lolos pengujian (PASS)!")

except Exception as e:
    print(f"\n[GAGAL] Pengujian UI mengalami error: {e}")
    try:
        driver.save_screenshot("tests/ui/screenshots/error_state.png")
        print("   ✔ Screenshot kondisi error disimpan di tests/ui/screenshots/error_state.png")
    except Exception as err:
        print(f"Gagal mengambil screenshot error: {err}")
    sys.exit(1)

finally:
    print("\nMenutup browser...")
    driver.quit()
    print("Pengujian selesai.")
