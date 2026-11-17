# Corner Shop

A small PHP and MySQL storefront for XAMPP. Customers can browse and search products, manage a session-based cart, register and sign in, view their order history, and place orders. Store administrators have a protected dashboard for order status, customer accounts, catalog management, inventory warnings, and order-value trends. Checkout saves the order and adjusts stock; it does not collect payment.

## Run locally with XAMPP

1. Copy this folder into `C:\xampp\htdocs\Hard-sys` (or another folder under `htdocs`).
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, select **Import**, and import `database.sql`. The script creates the `hard_sys_shop` database and sample products.
4. Visit `http://localhost/Hard-sys/`.

If you already installed the previous version of the shop, import `database_update.sql` once to add customer accounts and link new orders to them. Do not run that update on a fresh install.

To update an existing account table for admin access, import `database_admin_update.sql` in phpMyAdmin. Register an account through the shop, then promote it in phpMyAdmin by running `UPDATE hard_sys_shop.customers SET is_admin = 1 WHERE email = 'your-email@example.com';` with that account's email. The admin area is at `http://localhost/Hard-sys/admin/`.

Administrator and storefront sign-ins use separate session identities and separate sign-in pages. Customers sign in at `http://localhost/Hard-sys/login.php`; administrators sign in at `http://localhost/Hard-sys/admin/login.php`. The admin login accepts only administrator accounts, and dashboard pages require an authenticated admin session. An administrator can sign in to the dashboard and then sign in as a different customer in the storefront; signing out from either area does not sign out the other.

For an existing shop database, import `database_product_images_update.sql` once to add product image support. Admins can then add a photo with a new product or upload/replace one on an existing product. Supported formats are JPG, PNG, and WebP, up to 5 MB.

For an existing shop database, import `database_seller_details_update.sql` once to add per-product seller names and details. Admins maintain this information while adding or editing products; seller login accounts are not required.

For an existing shop database, import `database_product_classifications_update.sql` once after the product image and seller migrations. It creates Food, Shoes, Clothes, and Everyday Essentials classifications and assigns existing products to Everyday Essentials. Admins can add classifications under **Admin → Classifications** and assign them to products; the storefront displays classification filters.

For an existing shop database, import `database_customer_profiles_update.sql` once to add customer and administrator profile pictures. Customers can update their name, profile picture, and password from Profile settings; their email address cannot be changed there. The storefront profile menu shows the signed-in customer's name and picture. Signing out clears the storefront cart. Administrators can update their profile picture and password from the dashboard profile menu. Profile pictures accept JPG, PNG, and WebP images up to 5 MB.

For an existing shop database, import `database_order_statuses_update.sql` to migrate `new`, `processing`, and `fulfilled` orders to the updated status names. New orders start as pending.

For an existing shop database, import `database-bkup/database_payment_methods_update.sql` once to save payment method selections with orders. Existing orders default to cash on delivery.

Storefront product cards show remaining stock, and clicking a product image or name opens a popup with its full description, seller details, price, and current stock. Add it to the cart there, then continue through the existing cart checkout. The **About us** footer link opens a creator page with empty spaces for one project leader, nine project members, and one technical-support role. During checkout, use the plus and minus buttons beside an item to adjust its quantity between one and the available stock; the order quantity and total update together. Choose cash on delivery or online payment (test only). Neither option collects payment or contacts a payment gateway.

Admins can update orders to Pending, Ongoing, On shipment, Delivered, or Cancelled in a popup dialog. They can add products or edit product details, stock, price, visibility, photos, and seller information in popup dialogs and permanently delete products. Saved order items keep their original product name and price. The customer directory lets admins edit customer details and optionally reset a customer's password in the edit popup; passwords are stored as hashes and never displayed. A confirmation popup appears before deletion. Accounts with order history and administrator accounts are protected. New customers continue to register through the storefront.

The default database connection is `127.0.0.1`, database `hard_sys_shop`, user `root`, and an empty password, matching a typical local XAMPP setup. Override these with `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables if needed.

The PHP PDO MySQL and Fileinfo extensions must be enabled. Prices and order totals are displayed in Philippine pesos (`₱`, PHP). Passwords are stored as hashes. New orders are linked to the signed-in customer and stored with status `new`; no payment is taken. Analytics describe order totals, not collected payments. Products are managed centrally by admins; seller accounts are not enabled yet.
