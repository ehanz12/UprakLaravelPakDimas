<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use Laravel\Sanctum\Sanctum;

// ==========================================
// 1. AUTHENTICATION TESTS
// ==========================================

test('user can register successfully', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
    ]);
});

test('user registration fails with validation errors', function () {
    $response = $this->postJson('/api/register', [
        'name' => '',
        'email' => 'invalid-email',
        'password' => '123',
        'password_confirmation' => 'different',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('user can login successfully', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'user',
            'token',
        ]);
});

test('user login fails with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

test('authenticated user can logout and revoke token', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Logout successful']);

    expect($user->tokens()->count())->toBe(0);
});

test('authenticated user can fetch profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/user');

    $response->assertStatus(200)
        ->assertJsonFragment([
            'email' => $user->email,
        ]);
});

test('authenticated user can list all users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    Sanctum::actingAs($user1);

    $response = $this->getJson('/api/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'users',
        ])
        ->assertJsonCount(2, 'users');
});

// ==========================================
// 2. CATEGORY CRUD TESTS (PROTECTED)
// ==========================================

test('unauthenticated user cannot access categories', function () {
    $this->getJson('/api/categories')->assertStatus(401);
});

test('authenticated user can perform CRUD on categories', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // 1. Store
    $storeResponse = $this->postJson('/api/categories', [
        'name' => 'Elektronik',
        'description' => 'Barang elektronik berkualitas',
    ]);
    $storeResponse->assertStatus(201)
        ->assertJsonFragment(['name' => 'Elektronik']);
    $categoryId = $storeResponse->json('category.id');

    // 2. Index
    $this->getJson('/api/categories')
        ->assertStatus(200)
        ->assertJsonFragment(['name' => 'Elektronik']);

    // 3. Show
    $this->getJson("/api/categories/{$categoryId}")
        ->assertStatus(200)
        ->assertJsonFragment(['name' => 'Elektronik']);

    // 4. Update
    $this->putJson("/api/categories/{$categoryId}", [
        'name' => 'Elektronik Murah',
        'description' => 'Elektronik diskon',
    ])->assertStatus(200)
      ->assertJsonFragment(['name' => 'Elektronik Murah']);

    // 5. Destroy
    $this->deleteJson("/api/categories/{$categoryId}")
        ->assertStatus(200)
        ->assertJson(['message' => 'Kategori berhasil dihapus permanen']);

    $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
});

test('cannot delete category that has products', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $category = Category::create([
        'name' => 'Makanan',
        'slug' => 'makanan',
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Roti Tawar',
        'slug' => 'roti-tawar',
        'price' => 12000,
        'stock' => 10,
    ]);

    $response = $this->deleteJson("/api/categories/{$category->id}");

    $response->assertStatus(422)
        ->assertJson(['message' => 'Kategori tidak bisa dihapus, masih ada produk terkait']);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

// ==========================================
// 3. PRODUCT CRUD TESTS (MIXED PUBLIC/PRIVATE)
// ==========================================

test('anyone can view products list and detail', function () {
    $category = Category::create(['name' => 'Buku', 'slug' => 'buku']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Novel Baru',
        'slug' => 'novel-baru',
        'price' => 50000,
        'stock' => 5,
    ]);

    // Public list
    $this->getJson('/api/products')
        ->assertStatus(200)
        ->assertJsonFragment(['name' => 'Novel Baru']);

    // Public detail
    $this->getJson("/api/products/{$product->id}")
        ->assertStatus(200)
        ->assertJsonFragment(['name' => 'Novel Baru']);
});

test('unauthenticated user cannot modify products', function () {
    $category = Category::create(['name' => 'Buku', 'slug' => 'buku']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Novel Baru',
        'slug' => 'novel-baru',
        'price' => 50000,
        'stock' => 5,
    ]);

    $this->postJson('/api/products', [])->assertStatus(401);
    $this->putJson("/api/products/{$product->id}", [])->assertStatus(401);
    $this->deleteJson("/api/products/{$product->id}")->assertStatus(401);
});

test('authenticated user can perform product write operations', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $category = Category::create(['name' => 'Pakaian', 'slug' => 'pakaian']);

    // 1. Create
    $response = $this->postJson('/api/products', [
        'category_id' => $category->id,
        'name' => 'Kaos Polos',
        'price' => 35000,
        'stock' => 100,
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['name' => 'Kaos Polos']);
    $productId = $response->json('product.id');

    // 2. Update
    $this->putJson("/api/products/{$productId}", [
        'name' => 'Kaos Polos Premium',
        'price' => 45000,
    ])->assertStatus(200)
      ->assertJsonFragment(['name' => 'Kaos Polos Premium', 'price' => 45000]);

    // 3. Destroy
    $this->deleteJson("/api/products/{$productId}")
        ->assertStatus(200)
        ->assertJson(['message' => 'Produk berhasil dihapus permanen']);

    $this->assertDatabaseMissing('products', ['id' => $productId]);
});

// ==========================================
// 4. ORDER CRUD TESTS (PROTECTED)
// ==========================================

test('unauthenticated user cannot manage orders', function () {
    $this->getJson('/api/orders')->assertStatus(401);
});

test('authenticated user can place order, which decrements product stock', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $category = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Es Kopi',
        'slug' => 'es-kopi',
        'price' => 15000,
        'stock' => 10,
    ]);

    // Place order for 3 coffees
    $response = $this->postJson('/api/orders', [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment([
            'success' => true,
            'message' => 'Order berhasil dibuat (Checkout Sukses)!',
        ]);

    $orderId = $response->json('data.id');

    // Assert total price is correct: 15000 * 3 = 45000
    expect($response->json('data.total_price'))->toEqual('45000.00');

    // Assert stock is decremented: 10 - 3 = 7
    $product->refresh();
    expect($product->stock)->toBe(7);

    // Verify detail
    $this->getJson("/api/orders/{$orderId}")
        ->assertStatus(200)
        ->assertJsonFragment(['quantity' => 3]);
});

test('cannot place order if stock is insufficient', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $category = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Es Kopi',
        'slug' => 'es-kopi',
        'price' => 15000,
        'stock' => 5,
    ]);

    // Attempt to order 6 coffees (stock is 5)
    $response = $this->postJson('/api/orders', [
        'product_id' => $product->id,
        'quantity' => 6,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Stok produk tidak mencukupi. Stok saat ini: 5',
        ]);

    // Verify stock has not changed
    $product->refresh();
    expect($product->stock)->toBe(5);
});

test('cancelling order restores product stock', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $category = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Es Kopi',
        'slug' => 'es-kopi',
        'price' => 15000,
        'stock' => 10,
    ]);

    // Place order for 2 coffees (stock becomes 8)
    $order = Order::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'total_price' => 30000,
        'status' => 'pending',
    ]);
    $product->decrement('stock', 2);

    // Update status to 'cancelled'
    $response = $this->putJson("/api/orders/{$order->id}", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['status' => 'cancelled']);

    // Assert stock is restored back to 10
    $product->refresh();
    expect($product->stock)->toBe(10);
});

test('authenticated user can delete order', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $category = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Es Kopi',
        'slug' => 'es-kopi',
        'price' => 15000,
        'stock' => 10,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'total_price' => 30000,
        'status' => 'pending',
    ]);

    $this->deleteJson("/api/orders/{$order->id}")
        ->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Data order berhasil dihapus.']);

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});
