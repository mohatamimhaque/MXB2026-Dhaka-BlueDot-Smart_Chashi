# Shop Module (Marketplace)

> The Smart Chashi marketplace is a self-contained e-commerce module for buying and selling agricultural products. It operates as an independent sub-application under `/shop/` with its own routing, database helpers, authentication, and assets.

---

## Table of Contents

1. [Overview & Design](#overview--design)
2. [Architecture](#architecture)
3. [Directory Structure](#directory-structure)
4. [Feature Reference — Buyers](#feature-reference--buyers)
5. [Feature Reference — Farmers/Sellers](#feature-reference--farmerssellers)
6. [Feature Reference — Admins](#feature-reference--admins)
7. [Authentication](#authentication)
8. [Order Flow](#order-flow)
9. [Cart System](#cart-system)
10. [Messaging System](#messaging-system)
11. [Reviews System](#reviews-system)
12. [Email Notifications](#email-notifications)
13. [JavaScript Variables](#javascript-variables)
14. [Database Tables](#database-tables)
15. [Configuration Constants](#configuration-constants)
16. [File Structure](#file-structure)
17. [What Is Accepted / Rejected](#what-is-accepted--rejected)

---

## Overview & Design

| Attribute | Value |
|-----------|-------|
| **URL** | `/shop/` (localhost: `http://localhost/smartchashi/shop/`) |
| **Framework** | None — pure PHP, vanilla JS |
| **Auth** | Own session system (`shop_user_id`) — separate from main platform session |
| **DB** | Shares `smartcashi_db` database, own table prefix `shop_*` |
| **Currency** | Bangladeshi Taka (৳) |
| **Languages** | English and Bengali (inherits platform language setting) |

**Why a separate auth system?**  
The shop can be used independently of the main platform. A buyer can register directly in the shop without creating a main-platform farmer/officer account. The shop session key is `shop_user_id` while the main platform uses `user_id`.

---

## Architecture

```
Browser → /shop/
          │
          ▼
shop/index.php (if exists) or main index.php + router.php
          │
          ▼
shop/layouts/header.php    ← Cart count, navigation, auth state
          │
          ▼
shop/pages/{page}.php      ← Page content + data fetching
          │
     ┌────┴──────┐
     ▼           ▼
shop/includes/  shop/ajax/
  db.php          cart.php
  functions.php   orders.php
  auth.php        reviews.php
  email.php       messages.php
     │           upload.php
     ▼
MySQL (smartcashi_db)
shop_* tables
```

---

## Directory Structure

```
shop/
│
├── config/
│   └── config.php              ← SHOP_URL, SHOP_NAME, SHOP_CURRENCY constants
│
├── Database/
│   ├── shop_tables.sql         ← Core shop tables
│   ├── phase2_tables.sql       ← Reviews, messaging
│   ├── migration_v2.sql        ← v2 changes
│   ├── migration_v3.sql        ← v3 changes
│   └── migration_v4.sql        ← v4 changes
│
├── includes/
│   ├── db.php                  ← ShopDatabase class (separate PDO instance)
│   ├── functions.php           ← Cart helpers, order helpers, utilities
│   ├── auth.php                ← isShopLoggedIn(), getShopUser(), requireShopAuth()
│   └── email.php               ← Transactional email functions (PHPMailer)
│
├── layouts/
│   ├── header.php              ← <head>, navbar, cart badge, JS variable injection
│   └── footer.php              ← Scripts, closing HTML
│
├── auth/
│   ├── login.php               ← Shop login form + handler
│   ├── register.php            ← Shop registration
│   ├── logout.php              ← Destroys shop session
│   ├── forgot-password.php     ← Password reset request
│   └── verify-email.php        ← Email verification link handler
│
├── pages/
│   ├── products.php            ← Product listing: search, filter, categories
│   ├── product-detail.php      ← Single product page: images, reviews, buy button
│   ├── cart.php                ← Cart contents: quantities, totals, remove
│   ├── checkout.php            ← Address form, order summary, place order
│   ├── order-confirmation.php  ← Post-purchase confirmation with order ID
│   ├── my-orders.php           ← Buyer: order history list
│   ├── track-order.php         ← Order status timeline
│   ├── messages.php            ← Buyer↔seller messaging interface
│   └── farmer-profile.php      ← Public seller profile + product listings
│
├── profile/
│   ├── index.php               ← Account overview + stats
│   ├── orders.php              ← Order list (buyer view)
│   ├── order-detail.php        ← Single order with line items
│   └── settings.php            ← Account settings: name, email, password, address
│
├── ajax/
│   ├── cart.php                ← Add, update qty, remove, get cart
│   ├── orders.php              ← Place order, update status (seller), cancel
│   ├── auth.php                ← Login/register via AJAX
│   ├── messages.php            ← Send message, load thread, mark read
│   ├── reviews.php             ← Submit review, load reviews, edit
│   └── upload.php              ← Product image upload (seller)
│
└── assets/
    ├── css/style.css           ← Shop-specific styles
    └── js/main.js              ← Shop JS: cart, filters, messaging
```

---

## Feature Reference — Buyers

### Product Discovery

| Feature | Description |
|---------|-------------|
| Browse products | Paginated product grid with category sidebar |
| Search | Full-text search on product title and description |
| Category filter | Hierarchical category tree |
| Price filter | Min/max price range slider |
| Sort | By price (low/high), newest, most reviews |
| Product detail | Image gallery, description, seller info, stock status, reviews |
| Seller profile | Public page showing seller bio + all their products |

### Shopping

| Feature | Description |
|---------|-------------|
| Add to cart | One click; updates navbar badge without page reload |
| Cart management | Update quantities, remove items, see subtotal |
| Persistent cart | Cart stored in `shop_cart` table — survives page reload and browser close |
| Checkout | Delivery name, phone, address form; order summary with total |
| Place order | Creates `shop_orders` + `shop_order_items` records |
| Order confirmation | Shows order ID, summary, and estimated delivery info |

### Post-Purchase

| Feature | Description |
|---------|-------------|
| Order history | `my-orders.php` lists all orders with status badges |
| Order tracking | `track-order.php` shows visual timeline: pending → confirmed → shipped → delivered |
| Write review | Post rating (1–5 stars) + comment after receiving order |
| Message seller | Real-time-style messaging for order questions |

---

## Feature Reference — Farmers/Sellers

### Product Management

| Feature | Description |
|---------|-------------|
| List a product | Title, description, category, price, unit, stock, up to 5 images |
| Edit product | Update any field; toggle active/inactive |
| Delete product | Soft-delete (hides from buyers; order history preserved) |
| Image upload | `ajax/upload.php` — JPEG/PNG only, auto-resized |
| Inventory | Update stock count; product hidden when `stock = 0` |

### Order Management

| Feature | Description |
|---------|-------------|
| View orders | List of orders containing seller's products |
| Update status | `pending → confirmed → shipped → delivered` |
| Cancel order | With reason (buyer or seller can cancel pending orders) |
| Order details | Line items, buyer address, buyer contact |

### Communication

| Feature | Description |
|---------|-------------|
| Reply to buyer | Message thread per order / per product inquiry |
| Notification email | Automatic email when a new order arrives |

---

## Feature Reference — Admins

| Feature | Location | Description |
|---------|---------|-------------|
| Shop configuration | `admin-secure/pages/admin-shop-settings.php` | Name, tagline, currency, delivery notes |
| Product moderation | Content reports system | Buyers can flag products; admin resolves |
| Shop analytics | Admin → Analytics → Marketplace tab | Orders, revenue, top products, seller performance |
| Financial reports | Admin → Reports → Financial | Revenue estimates, order volume |

---

## Authentication

### Session Key

| System | Session Variable | DB Table | Check Function |
|--------|-----------------|---------|---------------|
| Main platform | `$_SESSION['user_id']` | `users` | `isLoggedIn()` |
| Shop | `$_SESSION['shop_user_id']` | `general_users` | `isShopLoggedIn()` |

The shop uses its own user table (`general_users`) completely separate from the main platform `users` table. Farmers who already have a main platform account must register separately in the shop to buy. A user can be logged into both systems simultaneously (different session variables, same `$_SESSION` array).

### Auth Functions (in `shop/includes/auth.php`)

```php
isShopLoggedIn()      → bool: is shop_user_id set in session
getShopUser()         → array: shop user row from DB or null
requireShopAuth()     → redirect to shop/auth/login.php if not logged in
isShopSeller()        → bool: current user is_seller = 1
```

### Protected Pages

| Page | Auth Required | Seller Required |
|------|--------------|----------------|
| `products.php` | No | No |
| `product-detail.php` | No (view); Yes (cart/buy) | No |
| `cart.php` | Yes | No |
| `checkout.php` | Yes | No |
| `my-orders.php` | Yes | No |
| `messages.php` | Yes | No |
| `profile/` | Yes | No |
| Product add/edit | Yes | Yes (`is_seller = 1`) |

---

## Order Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│  BUYER FLOW                                                         │
│                                                                     │
│  Browse → Product Detail → Add to Cart → Cart Review               │
│     → Checkout (address + confirmation) → Place Order               │
│     → Order Confirmation page (order_id shown)                      │
│     → Email confirmation sent to buyer                              │
│     → Email alert sent to seller                                    │
│                                                                     │
│  ORDER STATUS PROGRESSION (shop_orders):                            │
│                                                                     │
│  pending ──► confirmed ──► processing ──► shipped ──► delivered     │
│     │                                                    │          │
│     └───────────────────► cancelled ◄────────────────────┘          │
│                                                                     │
│  returned ◄── delivered (post-delivery dispute)                     │
│                                                                     │
│  SELLER FLOW:                                                        │
│  Receives email → logs in → views order → confirms → ships          │
│  → marks delivered after confirmation                               │
│                                                                     │
│  BUYER POST-PURCHASE:                                               │
│  View order status → message seller → leave review (after delivery) │
└─────────────────────────────────────────────────────────────────────┘
```

### Order Status Values (shop_orders)

| Status | Who Sets | Description |
|--------|---------|-------------|
| `pending` | System | Order just placed; awaiting seller action |
| `confirmed` | Seller | Seller has confirmed will fulfil the order |
| `processing` | Seller | Order being prepared / packed |
| `shipped` | Seller | Item dispatched; tracking info may be added |
| `delivered` | Seller | Item received by buyer |
| `cancelled` | Buyer or Seller | Order cancelled; stock restored |
| `returned` | Seller / Admin | Post-delivery return accepted |

### Payment Methods (shop_orders)

| Value | Description |
|-------|-------------|
| `cod` | Cash on delivery (default) |
| `bkash` | bKash mobile banking |
| `nagad` | Nagad mobile banking |
| `bank` | Bank transfer |

---

## Cart System

Cart data is stored in `shop_cart` table (not PHP session), enabling:
- Cart persists across browser sessions
- Same cart visible on multiple devices
- Counts reflect actual DB state (no stale session data)

### Cart AJAX (`shop/ajax/cart.php`)

| `action` | Description | Response |
|----------|-------------|---------|
| `add` | Add item or increment quantity | `{success, cart_count, message}` |
| `update` | Set specific quantity | `{success, item_total, cart_total}` |
| `remove` | Remove item from cart | `{success, cart_count}` |
| `get` | Full cart contents | `{success, items[], total, count}` |

### Cart Count in Navbar

`shop/includes/functions.php`:
```php
function getCartCount($user_id) {
    $db = new ShopDatabase();
    $result = $db->single("SELECT SUM(quantity) as total FROM shop_cart WHERE user_id = ?", [$user_id]);
    return (int)($result['total'] ?? 0);
}
```

The navbar badge is updated client-side after every cart action via JS.

---

## Messaging System

Buyers and sellers can communicate directly within the shop via a two-table threading model.

### How It Works

1. Buyer clicks "Contact Seller" on a product or order page
2. A `shop_conversations` row is created (thread header) with `farmer_id`, `customer_id`, `customer_type`, and an optional `product_id` / `order_id` link
3. Each message is a `shop_messages` row linked by `conversation_id`
4. Unread counts are tracked per-participant in `shop_conversations.farmer_unread` / `customer_unread`
5. Messages displayed in chat-style interface on `shop/pages/messages.php`

### Table Relationship

```
shop_conversations (thread)
  conversation_id  INT PK
  farmer_id        → users.user_id
  customer_id      → general_users.user_id (or users.user_id if customer_type='farmer')
  customer_type    ENUM('farmer','general')
  product_id       → marketplace_products.product_id
  order_id         → shop_orders.order_id
  farmer_unread    INT
  customer_unread  INT
      │
      └─── shop_messages (individual messages)
             conversation_id → shop_conversations
             sender_type     ENUM('farmer','customer')
             message         TEXT
             attachment_url  VARCHAR (image/file)
```

### AJAX (`shop/ajax/messages.php`)

| `action` | Description |
|----------|-------------|
| `send` | Send a new message (creates conversation if none exists) |
| `load_thread` | Load all messages in a conversation |
| `load_inbox` | Load all conversations (inbox list with unread counts) |
| `mark_read` | Mark messages in thread as read (decrements unread counter) |
| `unread_count` | Get total unread message count for current user |

---

## Reviews System

Buyers can review products after purchase.

### Rules

- One review per buyer per product (enforced by UNIQUE KEY in DB)
- Review only available after a `delivered` order containing that product
- Reviews show star rating (1–5) + optional text comment
- Average rating displayed on product card and detail page

### AJAX (`shop/ajax/reviews.php`)

| `action` | Description |
|----------|-------------|
| `submit` | Create or update a review |
| `load` | Load all reviews for a product |
| `get_user_review` | Check if current user has reviewed this product |

---

## Email Notifications

`shop/includes/email.php` handles all transactional shop emails via PHPMailer (SMTP from main `config/config.php`).

| Trigger | Recipient | Email Type |
|---------|----------|-----------|
| Order placed | Buyer | Order confirmation with summary |
| Order placed | Seller | New order alert with buyer details |
| Order confirmed | Buyer | Confirmation notification |
| Order shipped | Buyer | Shipment notification |
| Order delivered | Buyer | Delivery confirmation |
| Order cancelled | Both | Cancellation notice |
| Registration | Buyer | Email verification link |
| Password reset | User | Reset link |

---

## JavaScript Variables

Shop pages inject variables via `shop/layouts/header.php` for use in `shop/assets/js/main.js`:

```javascript
window.SHOP_URL        = 'http://localhost/smartchashi/shop';
window.SHOP_CART_AJAX  = 'http://localhost/smartchashi/shop/ajax/cart.php';
window.SHOP_AUTH_AJAX  = 'http://localhost/smartchashi/shop/ajax/auth.php';
window.SHOP_MSG_AJAX   = 'http://localhost/smartchashi/shop/ajax/messages.php';
window.SHOP_REV_AJAX   = 'http://localhost/smartchashi/shop/ajax/reviews.php';
window.SHOP_LOGGED_IN  = true;      // or false
window.SHOP_USER_ID    = 42;        // current shop user ID (null if not logged in)
window.SHOP_LOGIN_URL  = 'http://localhost/smartchashi/shop/auth/login.php';
```

These allow the JS to construct correct AJAX URLs dynamically without hardcoding paths.

---

## Database Tables

The shop module shares the `smartcashi_db` database with the main platform. Products are stored in the main `marketplace_products` table; the shop module adds its own tables for cart, orders, messaging, and users.

| Table | Purpose | Key Notes |
|-------|---------|-----------|
| `general_users` | Shop buyer accounts | Separate from main platform `users` table — independent registration |
| `marketplace_products` | Product listings | Shared with main platform; `seller_id → users.user_id` (farmer) |
| `shop_cart` | Active cart items | Supports guest carts via `session_id` (no login required to add to cart) |
| `shop_orders` | Shop module order records | `buyer_id → general_users.user_id`; has `order_number`, full shipping fields |
| `shop_order_items` | Line items per order | `order_id → shop_orders`, `product_id → marketplace_products` |
| `shop_conversations` | Messaging threads | Links `farmer_id` (users) ↔ `customer_id` (general_users or users) |
| `shop_messages` | Individual messages in a thread | `conversation_id → shop_conversations`; supports file attachments |
| `product_reviews` | Product ratings and reviews | Threaded (supports replies via `parent_review_id`); `is_verified_purchase` flag |
| `product_wishlist` | Saved product watchlists | `notify_price_drop`, `notify_back_in_stock` flags |
| `shop_settings` | Key-value configuration store | `setting_type` ENUM: text/html/json/boolean/number |
| `shop_otp_codes` | Email OTP verification | `purpose` ENUM: register/reset_password; hashed codes with expiry |

### Key Schema Details

**`general_users`** — shop buyer accounts:
- Columns: `email`, `phone`, `password_hash`, `first_name`, `last_name`, `profile_img_url`, `address`, `city`, `district`, `postal_code`, `is_active`, `email_verified`, `remember_token`
- Completely separate from main `users` table — a farmer can have BOTH a `users` account and a `general_users` account

**`shop_cart`** — guest + authenticated carts:
- `user_id` is NULL for guest carts; `session_id` identifies the guest session
- Logged-in buyers have `user_id` set and `session_id` = NULL

**`shop_orders`** — order records:
- `order_number`: human-readable order reference (e.g., `ORD-20260115-001`)
- Payment methods: `cod` (cash on delivery) \| `bkash` \| `nagad` \| `bank`
- Order statuses: `pending` → `confirmed` → `processing` → `shipped` → `delivered` → `cancelled` / `returned`

**`shop_conversations`** — messaging thread header:
- `customer_type` ENUM: `farmer` \| `general` — identifies which user table the customer is in
- `farmer_unread` / `customer_unread`: unread message counts per participant
- Related to `shop_messages` by `conversation_id`

**`shop_settings`** — seeded defaults include:
- `delivery_charge`: `50` (৳50 flat)
- `min_free_delivery`: `500` (free delivery over ৳500)
- `shop_meta_description`, `footer_about`, `footer_phone`, `footer_email`

See [database.md](database.md) for full column definitions.

---

## Configuration Constants

Defined in `shop/config/config.php`:

| Constant | Default | Description |
|----------|---------|-------------|
| `SHOP_URL` | Auto-detected | Base URL for all shop links |
| `SHOP_NAME` | Smart Chashi Shop | Display name in header |
| `SHOP_TAGLINE` | Agricultural Marketplace | Subtitle |
| `SHOP_CURRENCY` | ৳ | Currency symbol prefix |
| `SHOP_MAX_IMAGES` | 5 | Max product images per listing |
| `SHOP_MAX_FILE_SIZE` | 5MB | Max image upload size |

Helper function:
```php
shopUrl($path) → SHOP_URL . '/' . ltrim($path, '/')
```

---

## File Structure (Summary)

```
shop/
├── config/config.php           ← Constants (SHOP_URL, SHOP_NAME, currency)
├── includes/
│   ├── db.php                  ← ShopDatabase (separate PDO instance from main DB class)
│   ├── functions.php           ← getCartCount(), formatPrice(), calculateOrderTotal()
│   ├── auth.php                ← isShopLoggedIn(), getShopUser(), requireShopAuth()
│   └── email.php               ← sendOrderConfirmation(), sendShipmentNotification()
├── layouts/
│   ├── header.php              ← navbar, cart badge, JS variables injected
│   └── footer.php              ← scripts, closing tags
├── auth/                       ← login, register, logout, verify-email
├── pages/                      ← product listing, detail, cart, checkout, orders, tracking
├── profile/                    ← account settings, order history
├── ajax/                       ← cart, orders, auth, messages, reviews, upload
└── assets/
    ├── css/style.css           ← all shop CSS
    └── js/main.js              ← cart AJAX, filter, sort, messaging, gallery
```

---

## What Is Accepted / Rejected

### Product Listings

| Item | Accepted | Reason |
|------|----------|--------|
| Agricultural products (seeds, produce, tools) | Yes | Core use case |
| JPEG / PNG product images | Yes | Standard web formats |
| Images up to 5 MB | Yes | Server upload limit |
| Multiple images per product (up to 5) | Yes | |
| Price in Taka (decimal) | Yes | |
| Zero or negative price | No | Validation rejects |
| Non-image file types (PDF, video) | No | MIME type check |
| Images over 5 MB | No | Server-side file size check |
| HTML in product descriptions | Sanitized | `htmlspecialchars` before storage |
| Script tags in descriptions | No | Stripped by sanitization |

### Orders

| Scenario | Accepted | Notes |
|----------|----------|-------|
| Order with 0 quantity | No | Cart validation prevents |
| Order for out-of-stock product | No | Stock check before order creation |
| Cancelling a pending order | Yes | By buyer or seller |
| Cancelling a delivered order | No | Status transition not allowed |
| Multiple products in one order | Yes | One `shop_orders` → many `shop_order_items` |

### Reviews

| Scenario | Accepted | Notes |
|----------|----------|-------|
| Review after confirmed delivery | Yes | |
| Review without purchase | No | `delivered` order required |
| Second review on same product | Replaces previous | UNIQUE KEY → ON DUPLICATE KEY UPDATE |
| Rating outside 1–5 | No | Server-side validation |
