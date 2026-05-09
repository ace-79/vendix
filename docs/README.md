# Vendix - Smart Ecommerce System

## Overview
Vendix is a comprehensive **Smart Ecommerce and Point of Sale (POS) Management System** designed to streamline business operations. Built with a focus on simplicity, speed, and real-time data tracking, Vendix enables businesses to easily manage their inventory, sales, customers, suppliers, and purchase orders.

### Key Features
- **Real-Time POS (Point of Sale)**: Fast and intuitive checkout process with barcode scanning support.
- **Inventory Management**: Track stock levels, low-stock alerts, and perform stock adjustments.
- **Purchase Orders**: Create, track, and receive orders from suppliers, including automated email notifications.
- **Sales & Reporting**: Comprehensive daily, monthly, and yearly reports to analyze business performance.
- **Role-Based Access Control**: Secure login system with distinct permissions for Admins, Managers, Inventory staff, and Cashiers.
- **Email Notifications**: Automated invoices sent to customers and purchase orders sent to suppliers using PHPMailer.

---

## Project Structure
```text
vendix/
├── api/                    # Backend API endpoints (JSON responses)
│   ├── customers.php
│   ├── products.php
│   ├── purchase_orders.php
│   ├── sales.php
│   └── ...
├── assets/                 # Static assets
│   ├── css/                # Stylesheets (style.css, etc.)
│   ├── images/             # Uploads, logos, product images
│   └── js/                 # Frontend JavaScript (app.js)
├── config/                 # Configuration files
│   ├── auth.php            # Authentication logic
│   ├── db.php              # Database connection
│   └── helpers.php         # Utility functions
├── database/               # Database SQL files
│   └── sales_management.sql
├── docs/                   # Documentation
│   ├── README.md
│   └── database.md
├── includes/               # Reusable UI components
│   ├── footer.php
│   ├── header.php
│   ├── navbar.php
│   └── sidebar.php
├── pages/                  # Main application views
│   ├── dashboard.php
│   ├── pos.php
│   ├── products.php
│   ├── reports.php
│   └── ...
├── utils/                  # Utility services
│   ├── mailer.php          # Email sending logic
│   ├── PHPMailer/          # PHPMailer library
│   └── ...
├── index.php               # Entry point
└── login.php               # Login page
```

---

## Installation & Setup

### 1. Install WampServer
To run this application locally, you need a local server environment like WampServer (Windows).
1. Download **WampServer** from [wampserver.com](http://www.wampserver.com/en/).
2. Run the installer and follow the instructions to install it (usually in `C:\wamp64`).
3. Once installed, launch WampServer. The WampServer icon in the system tray should turn **Green**, indicating that Apache and MySQL are running.

### 2. Add Project to WampServer
1. Copy the entire `vendix` project folder.
2. Paste it into the WampServer web root directory: `C:\wamp64\www\`.
3. Your project should now be located at `C:\wamp64\www\vendix`.

### 3. Setup Database (Exporting to WAMP)
1. Open your browser and navigate to **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Log in (default username is usually `root` with no password).
3. Click on **New** in the left sidebar to create a new database.
4. Name the database `sales_management` and select `utf8mb4_general_ci` as the collation, then click **Create**.
5. Select the newly created `sales_management` database.
6. Click the **Import** tab at the top.
7. Click **Choose File** and select the `database/sales_management.sql` file from your project folder.
8. Scroll down and click **Import** (or **Go**). The tables and demo data will be imported.

### 4. Link Database with App
The application connects to the database using the configuration in `config/db.php`.
If your WampServer MySQL uses the default settings, no changes are needed. It assumes:
- **Host**: `localhost`
- **Username**: `root`
- **Password**: `""` (empty)
- **Database**: `sales_management`

*(If you set a password for your root user in WampServer, update it inside `config/db.php`)*

---

## How to Use & Demo Accounts

Navigate to [http://localhost/vendix](http://localhost/vendix) in your web browser. You will be greeted by the login screen.

You can use the following demo accounts to explore the system. **The password for all accounts is: `123456`**

| Role | Username | Description |
| :--- | :--- | :--- |
| **Admin** | `admin` | Full access to all features, including deleting customers and managing permissions. |
| **Manager** | `manager1` | Can manage products, customers, suppliers, and view reports. Cannot access POS. |
| **Cashier** | `seller1` | Restricted access. Primarily uses the POS system to process sales and view products. |
| **Inventory** | `manager2` | Manages stock, adjustments, products, and purchase orders. |

*(For more details on database structure and relations, please see [database.md](database.md))*
