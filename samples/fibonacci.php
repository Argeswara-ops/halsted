<?php
// Fungsi untuk Menghitung Deret Fibonacci
function fibonacci($n) {
    $sequence = [0, 1];
    
    for ($i = 2; $i < $n; $i++) {
        $sequence[] = $sequence[$i - 1] + $sequence[$i - 2];
    }
    
    return $sequence;
}

// Jalankan dan cetak hasil
$limit = 10;
$fib = fibonacci($limit);
echo "Deret Fibonacci (" . $limit . " angka): " . implode(", ", $fib);
?>
