# MyTask API — Quick Start

## 🚀 Setup

```bash
git clone https://github.com/mohamedselim7/MyTask.git
cd MyTask
composer install
cp .env.example .env
# Edit .env for DB settings
php artisan key:generate
php artisan migrate
php artisan serve
```

API base URL: `http://127.0.0.1:8000/api`

---

## 📚 Endpoints

### 1️⃣ Get All Products
- **Method:** GET  
- **URL:** `/api/products`  
- **Body:** none  

### 2️⃣ Get Product Details
- **Method:** GET  
- **URL:** `/api/products/{id}`  
- **Body:** none  
- **Response includes:** `total_stock`, `reserved_stock`, `available_stock`  

### 3️⃣ Create Product
- **Method:** POST  
- **URL:** `/api/products`  
- **Body:**
```json
{
  "name": "Product Name",
  "price": 100,
  "stock": 50
}
```

### 4️⃣ Create Hold
- **Method:** POST  
- **URL:** `/api/holds`  
- **Body:**
```json
{
  "product_id": 1,
  "qty": 2
}
```

### 5️⃣ Create Order
- **Method:** POST  
- **URL:** `/api/orders`  
- **Body:**
```json
{
  "hold_id": 1
}
```

### 6️⃣ Payment Webhook
- **Method:** POST  
- **URL:** `/api/payments/webhook`  
- **Body:**
```json
{
  "idempotency_key": "unique-key",
  "order_id": 1,
  "status": "paid"
}
```

> All POST requests should have `Content-Type: application/json`.

---

## 🧪 Testing
```bash
php artisan test
# or
vendor/bin/phpunit
```

---

## ⚡ Notes
- `available_stock` = stock - reserved - active holds  
- Holds expire automatically if not converted to order  
- Webhook is idempotent (avoids double payments)  
- Cache used for GET products, but stock calculation remains accurate
