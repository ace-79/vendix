<!-- PROJECT LOGO & HEADER -->
<div align="center">
  <h1 align="center">🛒 Vendix POS & Inventory System</h1>

  <p align="center">
    A modern, high-performance web application designed to streamline retail operations, sales tracking, and inventory management.
    <br />
    <strong>Simplify your sales. Elevate your business.</strong>
    <br />
    <br />
    <a href="#-quick-start"><strong>Quick Start »</strong></a>
    ·
    <a href="#-features">Features</a>
    ·
    <a href="#-screenshots">Screenshots</a>
    ·
    <a href="#-documentation">Documentation</a>
  </p>
</div>

---

## 📑 Table of Contents
- [Quick Start](#-quick-start)
- [Screenshots](#-screenshots)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [System Requirements](#-system-requirements)
- [Installation & Setup](#-installation--setup)
- [Usage & Demo Accounts](#-usage--demo-accounts)
- [Project Structure](#-project-structure)
- [API Endpoints](#-api-endpoints)
- [Security Features](#-security-features)
- [Troubleshooting](#-troubleshooting)
- [Documentation](#-documentation)

---

<!-- BADGES -->
<div align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5" />
  <img src="https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white" alt="CSS3" />
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript" />
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License" />
</div>

---

## 🚀 Quick Start

Want to get up and running in minutes? Follow these quick steps:

1. **Download XAMPP/WAMP** - [XAMPP](https://www.apachefriends.org/) or [WampServer](http://www.wampserver.com/)
2. **Place project** in `htdocs` (XAMPP) or `www` (WAMP)
3. **Import database** - Use phpMyAdmin to import `database/vendix.sql`
4. **Visit** `http://localhost/vendix` in your browser
5. **Login** with Demo Account (see credentials below)

> For detailed setup instructions, jump to [Installation & Setup](#-installation--setup)

---

## 📸 Screenshots

GitHub automatically extracts and displays images when using relative paths! Here is a preview of the Vendix application interfaces directly from our repository:

| Dashboard Overview | Point of Sale (POS) | Activity Logs |
|:---:|:---:|:---:|
| <img src="SceenShoots/Dashboard.png" alt="Dashboard" width="100%"> | <img src="SceenShoots/POS.png" alt="POS System" width="100%"> | <img src="SceenShoots/Activity-logs.png" alt="Activity Logs" width="100%"> |

---

## ✨ Features

### Core Functionality
- **🛒 Fast Point of Sale (POS)** - Intuitive checkout interface designed for speed, supporting barcode scanning and quick product lookup
- **📦 Smart Inventory Management** - Real-time stock tracking, low-stock alerts, automated adjustments, and audit logs
- **🚚 Supplier & Purchase Orders** - Create and manage purchase orders with automated supplier tracking
- **👥 Customer Management** - Track customer information, purchase history, and contact details
- **📊 Advanced Analytics & Reports** - Daily, monthly, and yearly sales performance reports with visual insights
- **📧 Automated Notifications** - Email invoices and purchase orders using PHPMailer integration

### System Features
- **🛡️ Role-Based Access Control** - Multi-level permissions for Admin, Manager, Inventory, and Cashier roles
- **📋 Activity Logging** - Complete audit trail of all system actions and transactions
- **💰 Multi-Currency Support** - Handle transactions in different currencies
- **📱 Responsive Design** - Works seamlessly on desktop and tablet devices
- **⚡ High Performance** - Optimized queries and efficient caching

---

## 🛠️ Tech Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| **Backend** | PHP (Vanilla) | 8.0+ |
| **Database** | MySQL / MariaDB | 8.0+ |
| **Frontend** | HTML5, CSS3, JavaScript (Vanilla) | ES6+ |
| **UI/UX** | Custom CSS (Responsive Design) | - |
| **Email Service** | PHPMailer | 6.x |
| **Server** | Apache HTTP Server | 2.4+ |

---

## 💻 System Requirements

### Minimum Requirements
- **Web Server**: Apache 2.4+ with PHP 8.0+
- **Database**: MySQL 8.0+ or MariaDB 10.5+
- **PHP Extensions**: 
  - `mysqli` (MySQL extension)
  - `json`
  - `spl` (Standard PHP Library)
  - `date`, `filter`, `hash`
- **Disk Space**: ~500 MB (including project files and database)
- **RAM**: 2 GB (for development), 4+ GB (for production)

### Recommended Requirements
- **Web Server**: Apache 2.4+ with PHP 8.1+ or PHP 8.2+
- **Database**: MySQL 8.0+ with proper backups configured
- **SSL Certificate**: HTTPS enabled for secure transactions
- **Disk Space**: 1+ GB for media uploads and growth
- **RAM**: 8+ GB for production environment

### Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

## 📂 Project Structure

```text
vendix/
├── api/                    # REST API endpoints (JSON responses)
│   ├── auth.php           # Authentication & authorization
│   ├── users.php          # User management
│   ├── products.php       # Product operations
│   ├── customers.php      # Customer data
│   ├── sales.php          # Sales transactions
│   ├── payments.php       # Payment processing
│   ├── stock.php          # Inventory operations
│   ├── suppliers.php      # Supplier management
│   ├── purchase_orders.php # PO management
│   ├── activity_logs.php  # Audit logs
│   └── reports.php        # Report generation
├── assets/                 # Static assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   ├── fonts/             # Font files
│   └── images/            # Images & product uploads
├── config/                 # Configuration files
│   ├── db.php             # Database connection
│   ├── auth.php           # Authentication config
│   ├── helpers.php        # Helper functions
│   └── passwords.php      # Password hashing config
├── database/              # Database files
│   └── vendix.sql         # Schema & sample data
├── docs/                  # Documentation
│   ├── README.md          # This file
│   └── database.md        # Database architecture
├── includes/              # Reusable components
│   ├── header.php         # Page header
│   ├── navbar.php         # Navigation bar
│   ├── sidebar.php        # Sidebar menu
│   └── footer.php         # Page footer
├── pages/                 # Application views
│   ├── dashboard.php      # Main dashboard
│   ├── pos.php            # Point of Sale interface
│   ├── products.php       # Product catalog
│   ├── customers.php      # Customer management
│   ├── sales.php          # Sales records
│   ├── stock.php          # Inventory management
│   ├── suppliers.php      # Supplier directory
│   ├── purchase_orders.php # PO management
│   ├── reports.php        # Reports portal
│   ├── activity_log.php   # Audit trail
│   ├── permissions.php    # Access control
│   ├── settings.php       # Application settings
│   ├── daily_report.php   # Daily sales report
│   ├── monthly_report.php # Monthly report
│   └── yearly_report.php  # Yearly report
├── utils/                 # Utility services
│   ├── api_helper.php     # API response handling
│   ├── inventory_service.php # Inventory operations
│   ├── sales_service.php  # Sales calculations
│   ├── mailer.php         # Email functionality
│   └── PHPMailer/         # PHPMailer library
├── ajax/                  # AJAX handlers
│   └── user_action.php    # User interaction handlers
├── index.php              # Application entry point
├── login.php              # Authentication portal
├── logout.php             # Session termination
└── README.md              # Project overview
```

---

## � API Endpoints

The application provides RESTful API endpoints for integrations and programmatic access.

### Authentication
- `POST /api/auth.php` - Login user and get session token
- `POST /api/auth.php?action=logout` - Logout current user
- `GET /api/auth.php?action=verify` - Verify session validity

### Users
- `GET /api/users.php` - List all users
- `POST /api/users.php` - Create new user
- `PUT /api/users.php?id={id}` - Update user
- `DELETE /api/users.php?id={id}` - Delete user

### Products
- `GET /api/products.php` - List all products
- `GET /api/products.php?id={id}` - Get product details
- `POST /api/products.php` - Create product
- `PUT /api/products.php?id={id}` - Update product
- `DELETE /api/products.php?id={id}` - Delete product

### Sales
- `GET /api/sales.php` - List sales records
- `POST /api/sales.php` - Create new sale
- `GET /api/sales.php?id={id}` - Get sale details

### Reports
- `GET /api/reports.php?type=daily` - Daily sales report
- `GET /api/reports.php?type=monthly` - Monthly report
- `GET /api/reports.php?type=yearly` - Yearly report

> For complete API documentation, refer to each endpoint file in the `/api` directory

---

## 🛡️ Security Features

- **Password Hashing**: Bcrypt algorithm for secure password storage
- **SQL Injection Prevention**: Prepared statements and parameterized queries
- **CSRF Protection**: Session tokens for state-changing operations
- **Role-Based Access Control**: Fine-grained permission system
- **Activity Logging**: Comprehensive audit trail of all operations
- **Session Management**: Secure session handling with automatic timeout
- **Input Validation**: Server-side validation of all user inputs
- **XSS Protection**: HTML escaping and content security measures

---

## �🚀 Installation & Setup

Follow these steps to get a local copy up and running quickly.

### 1. Prerequisites
You need a local server environment such as **WampServer** or **XAMPP**.
- [Download WampServer](http://www.wampserver.com/en/) or [Download XAMPP](https://www.apachefriends.org/index.html).

### 2. Clone or Copy the Repository
Place the project folder into your local server's web root directory:
- **WAMP**: `C:\wamp64\www\vendix`
- **XAMPP**: `C:\xampp\htdocs\vendix`

### 3. Database Configuration
1. Open **phpMyAdmin**: [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Log in using the default credentials (Username: `root`, Password: *Leave blank*).
3. Create a new database and name it **`vendix`**. *(Collation: `utf8mb4_general_ci`)*.
4. Go to the **Import** tab, select the `database/vendix.sql` file from the project, and click **Import**.

### 4. Link Application to Database
The application is pre-configured to use the default WAMP/XAMPP settings. If needed, open `config/db.php` and verify the settings are as follows:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // Default XAMPP/WAMP user
define('DB_PASS', '');       // Default XAMPP/WAMP password (empty string)
define('DB_NAME', 'vendix'); // Database you just created
```

---

## 💻 Usage & Demo Accounts

Navigate to **`http://localhost/vendix`** in your browser. Use the credentials below to explore the different permission levels.

> **🔐 Universal Password for all demo accounts: `123456`**

| Role | Username | Access Level |
| :--- | :--- | :--- |
| **Admin** | `admin` | Full control: users, settings, delete permissions, complete oversight. |
| **Manager** | `manager1` | Products, customers, suppliers, reports. (No POS access). |
| **Inventory** | `manager2` | Stock adjustments, product catalogs, supplier purchase orders. |
| **Cashier** | `seller1` | Restricted environment. Primarily uses the POS and views products. |

---

## � Troubleshooting

### Database Connection Issues
**Problem**: "Cannot connect to database" error
- ✅ Verify MySQL/MariaDB service is running
- ✅ Check `config/db.php` configuration matches your setup
- ✅ Ensure database name is exactly `vendix` (case-sensitive)
- ✅ Verify user has proper privileges in phpMyAdmin

### Login Issues
**Problem**: Cannot login with demo account
- ✅ Verify database has been imported (`database/vendix.sql`)
- ✅ Check that demo user accounts exist in the `users` table
- ✅ Ensure password is exactly `123456`
- ✅ Clear browser cache and cookies, then try again

### File Upload Problems
**Problem**: Product images not uploading
- ✅ Verify `assets/images/uploads/products/` directory exists
- ✅ Check folder permissions (should be writable by web server)
- ✅ Ensure file size is under server limits
- ✅ Verify supported file types (JPG, PNG, GIF)

### Email/Notification Issues
**Problem**: Emails not sending
- ✅ Check PHPMailer configuration in `utils/mailer.php`
- ✅ Verify SMTP credentials if using external mail server
- ✅ Check server PHP mail configuration
- ✅ Review error logs in your server control panel

### Permission Denied Errors
**Problem**: Files return permission errors
- ✅ Verify all files have proper read/write permissions
- ✅ Check web server user has access to project directory
- ✅ Ensure database user has necessary privileges

### Need More Help?
- 📖 Check the [Database Architecture Guide](database.md)
- 🔍 Review error logs in your server's error log file
- 💻 Check browser console for JavaScript errors (F12)

---

## 📝 Documentation

For deeper technical insights, including:
- **Database Schema** - Table structures and relationships
- **API Documentation** - Detailed endpoint specifications
- **Configuration Guide** - Setup and customization options
- **User Guide** - Feature walkthroughs and best practices

Please refer to: [Database Architecture Guide](database.md)

---

## 📄 License

This project is open source and available under the MIT License.

---

## 💬 Support & Contact

For questions, issues, or feedback:
- 📧 Email: [zakariaelkhayat79@gmail.com]


---

<div align="center">
  <p><i>Developed to simplify your sales and elevate your business.</i></p>
  <p>Made with ❤️ for retailers everywhere</p>
</div>
