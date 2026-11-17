# Corner Shop

A small PHP and MySQL storefront for XAMPP. Customers can browse and search products, manage a session-based cart, register and sign in, view their order history, and place orders. Store administrators have a protected dashboard for order status, customer accounts, catalog management, inventory warnings, and order-value trends. Checkout saves the order and adjusts stock; it does not collect payment.

## Run locally with XAMPP

1. Copy this folder into `C:\xampp\htdocs\Hard-sys` (or another folder under `htdocs`).
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, select **Import**, and import `database.sql`. The script creates the `hard_sys_shop` database and sample products.
4. Visit `http://localhost/Hard-sys/`.

If you already installed the previous version of the shop, import `database_update.sql` once to add customer accounts and link new orders to them. Do not run that update on a fresh install.

To update an existing account table for admin access, import `database_admin_update.sql` in phpMyAdmin. Register an account through the shop, then promote it in phpMyAdmin by running `UPDATE hard_sys_shop.customers SET is_admin = 1 WHERE email = 'your-email@example.com';` with that account's email. The admin area is at `http://localhost/Hard-sys/admin/`.

The default database connection is `127.0.0.1`, database `hard_sys_shop`, user `root`, and an empty password, matching a typical local XAMPP setup. Override these with `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables if needed.

The PHP PDO MySQL extension must be enabled. Passwords are stored as hashes. New orders are linked to the signed-in customer and stored with status `new`; no payment is taken. Analytics describe order totals, not collected payments. Products are managed centrally by admins; seller accounts are not enabled yet.
