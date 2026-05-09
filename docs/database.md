# Vendix - Database Structure & Logic

## Overview
The `sales_management` database is the core of the Vendix application, designed using relational database principles (MySQL/MariaDB). It ensures data integrity through foreign keys and cascading relationships.

### Core Logic & Relationships
- **Sales Flow**: A `sale` is linked to a `customer` and a `user` (cashier). Each sale contains multiple `sale_items`, which reference specific `products`. When a sale is completed, the product stock is reduced automatically. Payments are tracked in the `payments` table.
- **Inventory & Purchasing Flow**: `purchase_orders` are linked to `suppliers` and `users`. They contain `purchase_order_items`. When items are marked as "Received", the stock of the respective `products` is automatically increased, and a log is created in `stock_movements`.
- **Stock Adjustments**: Manual corrections, damage reports, or returns are handled via the `stock_adjustments` table, which triggers `stock_movements` to maintain an audit trail.
- **Authentication & RBAC**: The `users` table holds accounts. Access to specific modules is governed by the `role_permissions` table.
- **Activity Auditing**: The `activity_logs` table stores an immutable history of important actions (Create, Update, Delete, Login) for security and auditing purposes.

---

## Database Tables & Demo Data

### `users`
Stores system users and their roles for authentication.

| id | username | role | status | force_logout |
| :--- | :--- | :--- | :--- | :--- |
| 1 | admin | Admin | active | 0 |
| 2 | seller3 | cashier | active | 0 |
| 3 | manager1 | Manager | active | 0 |
| 5 | seller1 | cashier | active | 0 |
| 6 | manager2 | inventory | active | 0 |

### `role_permissions`
Defines module-level access for each role (Dynamic RBAC).

| id | role_name | permission_key | is_allowed |
| :--- | :--- | :--- | :--- |
| 1 | manager | view_dashboard | 1 |
| 2 | manager | view_pos | 0 |
| 11 | cashier | view_pos | 1 |
| 35 | inventory | view_stock | 1 |

### `customers`
Stores customer details for invoicing and tracking.

| id | name | phone | email |
| :--- | :--- | :--- | :--- |
| 1 | Ahmed Benali | 0600076501 | ahmed@mail.com |
| 2 | Sara El Amrani | 0600000002 | sara@mail.com |
| 9 | zakaria el khayat | 0708807293 | zakariaelkhayat79@gmail.com |

### `suppliers`
Stores vendors providing products.

| id | name | contact_person | phone | email |
| :--- | :--- | :--- | :--- | :--- |
| 1 | TechParts Morocco | Ahmed | 0600112233 | info@techparts.ma |
| 2 | VenTech | zakaria el khayat | 0712345678 | zakariaelkhayat79@gmail.com |

### `products`
Stores inventory items, current stock, and pricing.

| id | name | sku | price | cost_price | stock | min_stock | category |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Laptop HP | SKU-00001 | 5000.00 | 3000.00 | 23 | 10 | Electronics |
| 2 | Mouse Logitech | SKU-00002 | 150.00 | 80.00 | 16 | 10 | Accessories |
| 7 | Iphone 17pro max | SKU-00007 | 8500.00 | 4000.00 | 21 | 17 | Electronics |

### `sales` & `sale_items`
Tracks transactions and the specific products sold.

**`sales`**
| id | customer_id | user_id | payment_status | total_amount |
| :--- | :--- | :--- | :--- | :--- |
| 1 | 1 | 5 | Paid | 5150.00 |

**`sale_items`**
| id | sale_id | product_id | quantity | unit_price | subtotal |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | 1 | 1 | 1 | 5000.00 | 5000.00 |
| 2 | 1 | 2 | 1 | 150.00 | 150.00 |

### `purchase_orders` & `purchase_order_items`
Tracks inventory orders sent to suppliers.

**`purchase_orders`**
| id | supplier_id | status | total_cost |
| :--- | :--- | :--- | :--- |
| 1 | 1 | ordered | 15000.00 |

**`purchase_order_items`**
| id | purchase_order_id | product_id | quantity_ordered | quantity_received | unit_cost |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | 1 | 1 | 5 | 0 | 3000.00 |

### `stock_adjustments` & `stock_movements`
Handles manual corrections and logs all inventory changes.

**`stock_adjustments`**
| id | product_id | adjustment_type | quantity | reason |
| :--- | :--- | :--- | :--- | :--- |
| 1 | 2 | damage | 2 | Damaged in warehouse |

**`stock_movements`**
| id | product_id | movement_type | quantity | stock_before | stock_after |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | 1 | sale | -1 | 24 | 23 |

### `settings`
Stores dynamic application configurations (e.g., SMTP setup, app name).

| id | setting_key | setting_value |
| :--- | :--- | :--- |
| 1 | app_name | Vendix |
| 6 | auto_email_invoices | 1 |
| 7 | smtp_host | smtp.gmail.com |

### `activity_logs`
Immutable audit trail of user actions.

| id | user_id | action_type | entity_type | description |
| :--- | :--- | :--- | :--- | :--- |
| 1 | 1 | LOGIN | auth | User logged in |
| 2 | 5 | CREATE | sale | Created sale with 2 item(s) |
