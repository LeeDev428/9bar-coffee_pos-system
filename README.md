# 📘 9Bar Coffee POS System - User Guide

## 🎯 What is this System?

The **9Bar Coffee POS System** is a complete Point of Sale and Inventory Management solution built specifically for coffee shops. It helps you manage daily sales, track inventory automatically, print receipts, and generate reports - all in one system.

---

## 🚀 Quick Overview

### What Can This System Do?

1. **💰 Process Sales** - Fast checkout with product selection, add-ons, and multiple payment methods
2. **📦 Track Inventory** - Automatically deduct ingredients when products are sold
3. **🖨️ Print Receipts** - Thermal printer support for instant receipt printing
4. **📊 View Reports** - Daily sales statistics, best-selling products, and transaction history
5. **🚫 Void Transactions** - Cancel incorrect sales with proper authorization
6. **👥 User Management** - Separate access levels for Admin and Staff

---

## 🔐 Login Credentials

### Admin Account
- **Username:** `admin`
- **Password:** `admin123`
- **Access:** Full system access (all features)

### Staff Account
- **Username:** `staff1`
- **Password:** `staff123`
- **Access:** Limited access (POS, sales records, inventory view)

---

## 🎓 How It Works - Main Flow

### For Staff (Cashiers)

```
┌─────────────────────────────────────────────────────────────┐
│  1. LOGIN → 2. GO TO POS → 3. SELECT PRODUCTS               │
│  4. ADD TO CART → 5. CHOOSE PAYMENT → 6. COMPLETE SALE      │
│  7. RECEIPT PRINTS AUTOMATICALLY                             │
└─────────────────────────────────────────────────────────────┘
```

#### Step-by-Step Process:

**Step 1: Login**
- Open the website in your browser
- Enter your username and password
- Click "Login"

**Step 2: Navigate to POS (Point of Sale)**
- After login, click "POINT OF SALE" in the sidebar
- You'll see all available products organized by category

**Step 3: Build the Order**
- Click on products to add them to the cart
- Select size (8oz, 12oz, 16oz, 22oz) if applicable
- Choose ice level for cold drinks (Less Ice, Normal, Extra Ice)
- Add extras like whipped cream, pearls, or chocolate bits
- Adjust quantity using + and - buttons

**Step 4: Process Payment**
- Review the cart (right side of screen)
- Select payment method:
  - **Cash** - For cash payments
  - **GCash** - For digital payments (enter reference number)
- Click "Complete Sale"

**Step 5: Receipt Printing**
- Receipt prints automatically on thermal printer
- Sale is recorded in the system
- Inventory is automatically updated
- Cart clears for next customer

**Step 6: View Sales Records**
- Click "SALES RECORDS" in sidebar
- View all transactions
- Search by date, payment method, or transaction ID
- **Void sales** if needed (requires your staff password)
- Reprint receipts if customer needs duplicate

---

### For Admin (Managers)

```
┌──────────────────────────────────────────────────────────────┐
│  DASHBOARD → VIEW REPORTS → MANAGE PRODUCTS → VOID SALES    │
│  ADJUST INVENTORY → VIEW VOID HISTORY → CONFIGURE SETTINGS   │
└──────────────────────────────────────────────────────────────┘
```

#### Admin Features:

**1. Dashboard**
- View today's total sales (Cash + GCash breakdown)
- See transaction count
- Check best-selling products
- Monitor low stock alerts

**2. Manage Products**
- Add new products (name, price, category, image)
- Edit existing products
- Set product status (Active/Inactive)
- Link products to ingredients for automatic tracking
- Configure packaging requirements

**3. Inventory Management**
- View stock levels (Products, Ingredients, Packaging, Add-ons)
- Manual stock adjustments
- Set minimum/maximum stock levels
- Reorder level configuration
- Low stock alerts

**4. Sales Records & Reports**
- Complete transaction history
- Advanced search and filters
- Export to CSV for external reporting
- Void transactions (requires admin password)
- View void history with audit trail

**5. Void History (Admin Only)**
- See all voided transactions
- Track who voided each sale
- View void reasons
- Check if inventory was restored
- Admin approval status

**6. Settings**
- Configure thermal printer
- Set receipt format
- Enable/disable auto-print
- System configuration

---

## 🧠 System Logic Explained

### How Inventory Works

#### Automatic Stock Deduction
When a sale is completed, the system automatically deducts:

1. **Product Stock** - Main product quantity (cups/servings)
2. **Ingredients** - Raw materials used (coffee beans, milk, syrup)
3. **Packaging** - Cups, lids, straws based on size
4. **Add-ons** - Extra items added to the order

**Example:**
```
Customer orders: 1x Iced Latte (16oz) + Whipped Cream

System automatically deducts:
├── Product: 1 cup of Iced Latte
├── Ingredients:
│   ├── Coffee beans: 20g
│   ├── Milk: 250ml
│   └── Ice: 100g
├── Packaging:
│   ├── 16oz cup: 1 piece
│   ├── Dome lid: 1 piece
│   └── Straw: 1 piece
└── Add-ons:
    └── Whipped cream: 1 serving
```

#### Buy 1 Take 1 (B1T1) Logic
Products in "B1T1" category automatically deduct **2 servings** when 1 is sold:
- Customer pays for 1
- System deducts inventory for 2
- Both customers get their drinks

#### Low Stock Alerts
- System monitors all inventory levels
- Alerts appear when stock reaches minimum threshold
- Dashboard shows "Low Stock" warnings
- Helps prevent running out of popular items

---

### How Void (Cancel) Transactions Work

#### What is Voiding?
Voiding means **canceling a completed sale** due to errors (wrong item, price mistake, duplicate charge, etc.)

#### Void Process:

**For Staff:**
1. Go to "SALES RECORDS"
2. Find the transaction to void
3. Click "Void" button
4. Enter **your own staff password** for verification
5. Provide a reason for voiding
6. Confirm void

**For Admin:**
1. Same process as staff BUT
2. Can void **any** transaction (staff can only void recent ones)
3. Enter **admin password**
4. Admin approval is recorded in void history

#### What Happens When You Void:

```
┌────────────────────────────────────────────────────┐
│  1. Transaction status changes to "VOIDED"         │
│  2. Money is considered REFUNDED                   │
│  3. Inventory is RESTORED automatically:           │
│     - Products added back                          │
│     - Ingredients returned                         │
│     - Packaging replenished                        │
│     - Add-ons restored                             │
│  4. Void is logged in audit trail                  │
│  5. Admin can view void history anytime            │
└────────────────────────────────────────────────────┘
```

**Important:**
- Voided sales still appear in records (marked as VOIDED)
- They don't count toward daily sales totals
- Original receipt becomes invalid
- Inventory restoration is automatic - no manual adjustment needed

---

## 🖨️ Thermal Printer Setup

### Printer Configuration

**System Supports:**
- XPrinter 58mm thermal printers
- Windows-connected printers (USB/Network)
- Auto-print after every sale

**Setup Steps:**

1. **Install Printer**
   - Connect XPrinter to computer via USB
   - Install printer drivers
   - Set printer name to "Thermal Printer" in Windows

2. **Configure in System**
   - Login as Admin
   - Go to "SETTINGS"
   - Set printer type: `Windows`
   - Enter printer name: `Thermal Printer`
   - Enable auto-print receipt: `ON`
   - Save settings

3. **Test Printing**
   - Click "Test Printer" button
   - Receipt should print immediately
   - If fails, check:
     - Printer is turned on
     - Paper is loaded
     - USB cable is connected
     - Printer name matches exactly

**Receipt Format:**
```
========================================
         9BARS COFFEE
========================================
Date: 2025-11-16 14:30:25
Transaction: TXN-20251116143025-847
Cashier: staff1
----------------------------------------
ITEMS:
Iced Latte (16oz)         x1   ₱120.00
  + Whipped Cream              ₱20.00
Americano (12oz)          x1   ₱90.00
----------------------------------------
Subtotal:                      ₱230.00
----------------------------------------
TOTAL:                         ₱230.00
Payment Method: Cash
----------------------------------------
     Thank you for your purchase!
         Visit us again soon!
========================================
```

---

## 📊 Reports & Analytics

### Available Reports:

**1. Daily Sales Summary**
- Total revenue (today)
- Cash vs GCash breakdown
- Transaction count
- Average transaction value

**2. Best-Selling Products**
- Top 10 products by sales
- Quantity sold
- Revenue per product

**3. Transaction History**
- All completed sales
- Search by date range
- Filter by payment method
- Export to CSV

**4. Void History (Admin Only)**
- All voided transactions
- Who voided each sale
- Reasons for voiding
- Timestamp tracking

**5. Inventory Status**
- Current stock levels
- Low stock items
- Items needing reorder
- Stock value calculation

---

## 🔒 Security Features

### Password Protection
- All users must login
- Failed login attempts are tracked
- Account locks after 5 failed attempts
- Session timeout after inactivity

### Role-Based Access
- **Admin:** Full access to everything
- **Staff:** Limited to POS and sales viewing
- Void actions require password re-entry
- Sensitive actions are logged

### Audit Trail
- All voids are logged with:
  - Who performed the void
  - When it was done
  - Reason provided
  - Original transaction details
- Admin can review complete void history

---

## ⚙️ Technical Details

### System Architecture

```
┌─────────────────────────────────────────────────────┐
│                   USER INTERFACE                     │
│  (Web Browser - Staff/Admin Access)                 │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│               PHP BACKEND                            │
│  ├── Authentication (Login/Logout)                  │
│  ├── ProductManager (Product operations)            │
│  ├── SalesManager (Sales processing)                │
│  ├── ThermalPrinter (Receipt printing)              │
│  └── Database (Data management)                     │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│              MySQL DATABASE                          │
│  ├── users (accounts)                               │
│  ├── products (41 coffee products)                  │
│  ├── sales (transaction records)                    │
│  ├── inventory (stock levels)                       │
│  ├── ingredients (raw materials)                    │
│  ├── void_history (audit trail)                     │
│  └── settings (system configuration)                │
└─────────────────────────────────────────────────────┘
```

### Database Tables

**Core Tables:**
- `users` - Admin and staff accounts
- `products` - 41 coffee products (Americano, Latte, Frappe, etc.)
- `categories` - 8 categories (Hot Coffee, Iced Coffee, Frappe, B1T1, etc.)
- `sales` - All transaction records
- `sale_items` - Individual items per sale
- `void_history` - Voided transaction audit trail

**Inventory Tables:**
- `inventory` - Product stock levels
- `ingredients` - Raw materials (coffee, milk, syrups)
- `packaging_supplies` - Cups, lids, straws
- `addons` - Extra items (whipped cream, pearls, chocolate bits)
- `product_ingredients` - Links products to ingredients
- `product_packaging` - Links products to packaging

**System Tables:**
- `settings` - Printer config, system settings
- `password_resets` - Password recovery tokens
- `stock_adjustments` - Manual inventory changes

### Technologies Used
- **Backend:** PHP 7.4+
- **Database:** MySQL 8.0+
- **Frontend:** HTML5, CSS3, JavaScript
- **Server:** Apache/Nginx (Laragon for development)
- **Printer:** ESC/POS protocol for thermal printers

---

## 🛠️ Troubleshooting

### Common Issues

**1. Printer Not Printing**
- ✅ Check printer is powered on
- ✅ Verify paper is loaded
- ✅ Check USB cable connection
- ✅ Ensure printer name in settings matches Windows printer name exactly
- ✅ Test printer from Windows (print test page)
- ✅ Restart printer and try again

**2. Login Issues**
- ✅ Check username/password (case-sensitive)
- ✅ Account locked? Wait 30 seconds after 5 failed attempts
- ✅ Clear browser cache and cookies
- ✅ Try different browser

**3. Void Transaction Errors**
- ✅ Ensure you enter YOUR OWN password (not admin password for staff)
- ✅ Check transaction hasn't been voided already
- ✅ Verify you have permission (staff can only void recent transactions)
- ✅ Refresh page and try again

**4. Inventory Not Deducting**
- ✅ Check product is linked to ingredients in admin panel
- ✅ Verify packaging requirements are set
- ✅ Ensure current stock is above 0
- ✅ Check if product status is "Active"

**5. Sales Not Showing in Records**
- ✅ Refresh the page
- ✅ Check date range filter
- ✅ Verify sale completed successfully
- ✅ Look in "All Statuses" (not just "Completed")

---

## 📝 Best Practices

### For Staff:
1. **Always verify cart before completing sale**
2. **Double-check payment method selected**
3. **Enter GCash reference number for digital payments**
4. **Only void when absolutely necessary**
5. **Provide clear reason when voiding**
6. **Check receipt printed before customer leaves**

### For Admin:
1. **Review void history daily**
2. **Monitor low stock alerts**
3. **Update product prices regularly**
4. **Backup database weekly**
5. **Check daily sales report**
6. **Adjust reorder levels based on demand**
7. **Keep printer paper stocked**

---

## 📞 Support

If you encounter issues not covered in this guide:

1. Check the main `README.md` for technical setup
2. Review database logs for error messages
3. Verify all system requirements are met
4. Contact system administrator

---

## 📄 License

This system is proprietary software developed for 9Bars Coffee. All rights reserved.

---

**Last Updated:** November 16, 2025  
**System Version:** 1.0  
**Document Version:** 1.0
