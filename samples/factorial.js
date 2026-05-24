// Fungsi Rekursif untuk Menghitung Faktorial
function factorial(n) {
    if (n === 0 || n === 1) {
        return 1;
    }
    return n * factorial(n - 1);
}

// Menampilkan hasil
const number = 5;
const result = factorial(number);
console.log("Faktorial dari " + number + " adalah " + result);
