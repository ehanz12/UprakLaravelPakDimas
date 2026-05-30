<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{

    public function index()
    {
        
        $orders = Order::with(['user', 'product'])->get(); 

        return response()->json([
            'success' => true,
            'message' => 'Daftar semua orderan berhasil diambil.',
            'data'    => $orders
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors()
            ], 422);
        }

        
        DB::beginTransaction();

        try {
           
            $product = Product::find($request->product_id);

           
            if ($product->stock < $request->quantity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Stok produk tidak mencukupi. Stok saat ini: ' . $product->stock
                ], 400);
            }

           
            $totalPrice = $product->price * $request->quantity;

          
            $order = Order::create([
                'user_id'     => auth()->id(), 
                'product_id'  => $request->product_id,
                'quantity'    => $request->quantity,
                'total_price' => $totalPrice,
                'status'      => 'pending' 
            ]);

           
            $product->decrement('stock', $request->quantity);

          
            DB::commit(); 

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil dibuat (Checkout Sukses)!',
                'data'    => $order
            ], 201);

        } catch (\Exception $e) {
            
            DB::rollBack(); 
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

   
    public function show(string $id)
    {
        
        $order = Order::with(['user', 'product'])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Data order tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail order berhasil ditemukan.',
            'data'    => $order
        ], 200);
    }

   
    public function update(Request $request, string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Data order tidak ditemukan.'
            ], 404);
        }

       
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,success,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Status tidak valid.',
                'errors'  => $validator->errors()
            ], 422);
        }

        
        if ($request->status == 'cancelled' && $order->status != 'cancelled') {
            $product = Product::find($order->product_id);
            if ($product) {
                $product->increment('stock', $order->quantity);
            }
        }

       
        $order->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status order berhasil diperbarui.',
            'data'    => $order
        ], 200);
    }

    
    public function destroy(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Data order tidak ditemukan.'
            ], 404);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data order berhasil dihapus.'
        ], 200);
    }
}