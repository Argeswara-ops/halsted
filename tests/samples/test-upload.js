// Algoritma Pengurutan Gelembung (Bubble Sort)
function bubbleSort(arrayData) {
    let panjang = arrayData.length;
    let ditukar;
    
    do {
        ditukar = false;
        for (let i = 0; i < panjang - 1; i++) {
            if (arrayData[i] > arrayData[i + 1]) {
                // Proses penukaran nilai (Swap)
                let temp = arrayData[i];
                arrayData[i] = arrayData[i + 1];
                arrayData[i + 1] = temp;
                ditukar = true;
            }
        }
        panjang--;
    } while (ditukar);
    
    return arrayData;
}

const dataAcak = [64, 34, 25, 12, 22, 11, 90];
const dataTerurut = bubbleSort(dataAcak);
console.log("Hasil urut: " + dataTerurut.join(", "));
