<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // Menampilkan daftar produk dengan fitur pencarian dan filter kategori
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('category')
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->q . '%')
                      ->orWhere('sku', 'like', '%' . $request->q . '%');
            })
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->category_id);
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::all();

        return view('admin.products.index', compact('products', 'categories'));
    }

    // Menampilkan form tambah produk
    public function create(): View
    {
        $categories = Category::all();
        return view('admin.products.create', compact('categories'));
    }

    // Menyimpan produk baru beserta kalkulasi diskon otomatis
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'description' => ['nullable', 'string'],
        ]);

        // Hitung diskon otomatis berdasarkan harga (Cabang McCabe)
        if ($data['price'] > 1000000) {
            $data['discount'] = 10; // Diskon 10% untuk barang mahal
        } elseif ($data['price'] > 500000) {
            $data['discount'] = 5;  // Diskon 5% untuk barang sedang
        } else {
            $data['discount'] = 0;  // Tidak ada diskon
        }

        // Generate SKU unik
        $data['sku'] = 'PRD-' . strtoupper(Str::random(8));

        // Handle upload file gambar
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image_url'] = $path;
        }

        Product::create($data);

        return redirect()->route('admin.produk.index')->with('status', 'Produk berhasil ditambahkan.');
    }

    // Mengupdate data produk lama
    public function update(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'description' => ['nullable', 'string'],
        ]);

        // Update gambar baru jika diunggah
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($produk->image_url) {
                Storage::disk('public')->delete($produk->image_url);
            }
            $path = $request->file('image')->store('products', 'public');
            $data['image_url'] = $path;
        }

        $produk->update($data);

        return redirect()->route('admin.produk.index')->with('status', 'Produk berhasil diperbarui.');
    }

    // Menghapus data produk
    public function destroy(Product $produk): RedirectResponse
    {
        if ($produk->image_url) {
            Storage::disk('public')->delete($produk->image_url);
        }

        $produk->delete();

        return redirect()->route('admin.produk.index')->with('status', 'Produk berhasil dihapus.');
    }
}
