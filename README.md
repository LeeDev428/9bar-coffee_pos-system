# ☕ 9Bar Coffee POS System

A complete Point of Sale (POS) and Inventory Management System built for coffee shops. Designed for **9Bars Coffee** to manage sales, track inventory, and print thermal receipts.

![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)
![License](https://img.shields.io/badge/License-MIT-green.svg)

---

## 📋 Table of Contents

- [Features](#-features)
- [Screenshots](#-screenshots)
- [Installation](#-installation)
- [Usage](#-usage)
- [Database Structure](#-database-structure)
- [Configuration](#-configuration)
- [Technologies Used](#-technologies-used)
- [Login Credentials](#-login-credentials)
- [Troubleshooting](#-troubleshooting)
- [License](#-license)

---

## ✨ Features

### 🛒 Point of Sale (POS)
- **Fast checkout** with product search and category filtering
- **Add-ons support** (whipped cream, pearls, chocolate bits, etc.)
- **Size variants** (8oz, 12oz, 16oz, 22oz)
- **Ice level selection** for cold drinks
- **Multiple payment methods** (Cash & GCash)
- **Real-time cart management**
- **Thermal receipt printing** (XPrinter compatible)

### 📦 Inventory Management
- **Automatic stock deduction** when sales are made
- Tracks **products, ingredients, packaging, and add-ons**
- **Low stock alerts** when items run low
- **Product-ingredient connections** for accurate tracking
- **B1T1 (Buy 1 Take 1) logic** - automatically deducts correct quantities
- **Reorder level monitoring**
- **Manual stock adjustments**

### 💰 Sales & Reports
- **Daily sales dashboard** with real-time statistics
- **Cash vs GCash breakdown**
- **Transaction history** with search and filters
- **Best-selling products** tracking
- **Void transactions** with admin authorization
- **GCash reference number** tracking

### 👥 User Management
- **Role-based access** (Admin & Staff)
- **Secure login** with failed attempt tracking
- **Password reset via email**
- **Session management**
- **Admin-only features** (void sales, manage products, view reports)

### 🖨️ Thermal Printing
- **Auto-print receipts** after checkout
- **Manual reprint** option from sales history
- Supports **XPrinter 58mm thermal printers**
- Compatible with **Windows, USB, and Network printers**
- **Customizable receipt format**

### 🚫 Void Feature
- **Cancel incorrect transactions** with admin password
- **Automatic inventory restoration** when voiding
- **Reason tracking** for audit trail
- **Prevents unauthorized voids**

---

## 📸 Screenshots

### Login Page
Clean and modern login interface with branding.

### POS Interface
Easy-to-use point of sale system with category filtering and cart management.

### Admin Dashboard
Real-time sales statistics and inventory alerts.

### Products Management
Add, edit, and manage products with image upload support.

### Sales History
View transaction history with void and reprint options.

---

## 🚀 Installation

### Prerequisites
- **PHP 7.4+** or higher
- **MySQL 8.0+** or higher
- **Apache/Nginx** web server
- **Composer** (for PHPMailer)
- **Laragon/XAMPP/WAMP** (recommended for Windows)

### Step 1: Clone or Download
```bash
# Clone the repository
git clone https://github.com/LeeDev428/9bar-coffee_pos-system.git

# Or download and extract to your web server directory
# Laragon: C:\laragon\www\
# XAMPP: C:\xampp\htdocs\
```

### Step 2: Import Database
1. Open **phpMyAdmin** or **HeidiSQL**
2. Create a new database named `9bar_pos`
3. Import the file: `9bar_pos_complete.sql`

**Via Command Line:**
```bash
mysql -u root -p < 9bar_pos_complete.sql
```

### Step 3: Configure Database
Edit `includes/database.php` if your MySQL credentials are different:
```php
private $host = 'localhost';
private $dbname = '9bar_pos';
private $username = 'root';
private $password = '';  // Your MySQL password
```

### Step 4: Install Dependencies (Optional)
If you want email functionality:
```bash
composer install
```

### Step 5: Access the System
Open your browser and navigate to:
```
http://localhost/9bar-coffee_pos-system
```

---

## 🎯 Usage

### For Staff (POS)
1. Login with staff credentials
2. Go to **POS** page
3. Select products by clicking on them
4. Add add-ons and adjust quantities
5. Choose payment method (Cash or GCash)
6. Complete the sale
7. Receipt prints automatically (if printer is connected)

### For Admin
1. Login with admin credentials
2. Access full dashboard with sales statistics
3. Manage products, inventory, and users
4. View sales reports and transaction history
5. Void incorrect transactions (requires password)
6. Adjust stock levels manually
7. Configure system settings

---

## 🗄️ Database Structure

The system uses **18 tables** organized into these categories:

### Core Tables
- `users` - Admin and staff accounts
- `products` - Product catalog (41 products)
- `categories` - Product categories (8 categories)
- `sales` - Sales transactions
- `sale_items` - Individual items in each sale

### Inventory Tables
- `inventory` - Product stock levels
- `ingredients` - Raw materials (coffee, milk, syrups)
- `packaging_supplies` - Cups, lids, straws
- `addons` - Add-on items (whipped cream, pearls, etc.)
- `product_ingredients` - Links products to ingredients
- `product_packaging` - Links products to packaging

### System Tables
- `settings` - System configuration
- `password_resets` - Password recovery tokens
- `stock_adjustments` - Manual inventory changes
- `recipes` - Recipe reference data

### Views
- `daily_sales_summary` - Daily revenue aggregation
- `low_stock_items` - Items below minimum stock

---

## ⚙️ Configuration

### Email Settings
Edit `includes/smtp_config.php` to configure email notifications:
```php
'host' => 'smtp.gmail.com',
'port' => 587,
'username' => 'your-email@gmail.com',
'password' => 'your-app-password',
'from_email' => 'your-email@gmail.com',
'from_name' => '9Bars Coffee'
```

**Note:** Use Gmail App Password, not your regular password.

### Printer Settings
Configure thermal printer in `admin/pages/settings.php`:
- Printer name (e.g., "XPrinter XP-58IIB")
- Connection type (Windows/USB/Network)
- Auto-print settings

---

## 🛠️ Technologies Used

### Backend
- **PHP 7.4+** - Server-side scripting
- **MySQL 8.0+** - Database management
- **PDO** - Database abstraction layer

### Frontend
- **HTML5** - Markup
- **CSS3** - Styling with gradients and animations
- **JavaScript (Vanilla)** - Client-side interactions

### Libraries
- **PHPMailer 7.0** - Email sending via SMTP
- **ESC/POS** - Thermal printer protocol

### Development Tools
- **Laragon** - Local development environment
- **Composer** - Dependency management
- **Git** - Version control

---

## 🔐 Login Credentials

### Admin Account
- **Username:** `admin`
- **Password:** `admin123`
- **Email:** `admin@gmail.com`

### Staff Account
- **Username:** `staff`
- **Password:** `staff123`
- **Email:** `staff@gmail.com`

**⚠️ Important:** Change these default passwords after first login!

---

## 🐛 Troubleshooting

### Database Connection Error
```
Solution: Check database.php credentials and ensure MySQL is running
```

### Receipt Not Printing
```
Solution: 
1. Check printer is connected and powered on
2. Verify printer name in settings matches exactly
3. Install printer drivers (XPrinter drivers for Windows)
4. Test printer with test-printer-ports.php
```

### Email Not Sending
```
Solution:
1. Enable 2FA on Gmail account
2. Generate App Password (not regular password)
3. Update smtp_config.php with app password
4. Test with database/test-email.php
```

### Stock Not Deducting
```
Solution: Ensure product has connections set up in:
- Product Ingredients (admin/pages/inventory-ingredients.php)
- Product Packaging (admin/pages/inventory-packaging.php)
```

### Dashboard Shows ₱0.00
```
Solution: Make some sales transactions first. Dashboard shows today's sales only.
```

---

## 📁 Project Structure

```
9bar-coffee_pos-system/
├── admin/                  # Admin panel (products, sales, reports)
├── staff/                  # Staff POS interface
├── includes/              # Core PHP classes
│   ├── database.php       # Database connection
│   ├── auth.php          # Authentication
│   ├── ThermalPrinter.php # Printer integration
│   └── ProductManager.php # Product operations
├── assets/                # CSS, images, JavaScript
├── database/              # SQL scripts and migrations
├── vendor/                # Composer dependencies
├── 9bar_pos_complete.sql  # Complete database export
├── login.php             # Login page
└── index.php             # Landing page
```

---

## 📚 Documentation

Additional documentation files included:
- `DATABASE_IMPORT_INSTRUCTIONS.md` - Detailed import guide
- `VOID_FEATURE_DOCUMENTATION.md` - How to void transactions
- `RECEIPT_PRINTING_COMPLETE.md` - Printer setup guide
- `FORGOT_PASSWORD_FIX.md` - Password reset setup
- `README_QUICK_START.md` - Quick setup guide

---

## 🤝 Contributing

This project is developed for **9Bars Coffee**. For improvements or bug reports:
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

## 👨‍💻 Developer

**LeeDev428**
- GitHub: [@LeeDev428](https://github.com/LeeDev428)
- Repository: [9bar-coffee_pos-system](https://github.com/LeeDev428/9bar-coffee_pos-system)

---

## 🙏 Acknowledgments

- **9Bars Coffee** - For the opportunity to develop this system
- **PHPMailer** - Email functionality
- **ESC/POS Community** - Thermal printer protocols
- **XPrinter** - Hardware support

---

## 📞 Support

For questions or issues:
1. Check the [Troubleshooting](#-troubleshooting) section
2. Review documentation files in the project
3. Open an issue on GitHub
4. Contact the developer

---

## 🔄 Version History

### v1.0.0 (November 2025)
- ✅ Initial release
- ✅ POS system with inventory tracking
- ✅ Thermal receipt printing
- ✅ Admin & staff roles
- ✅ Email notifications
- ✅ Void transaction feature
- ✅ Automatic stock deduction
- ✅ GCash payment support

---

<div align="center">

**Made with ☕ for 9Bars Coffee**

⭐ Star this repository if you find it helpful!

</div>
