# SalesPro

A PHP 8 and MariaDB e-commerce college project. Customers can register with email verification, sign in with a username or email and password, save shipping addresses, and view carts, orders, receipts, and shipment updates. Guest checkout remains available. Mailpit captures verification and password-reset emails locally, so no domain or email account is needed.

| Open | Local URL |
| --- | --- |
| Store | [127.0.0.1:8000](http://127.0.0.1:8000/) |
| Customer account | [127.0.0.1:8000/account.php](http://127.0.0.1:8000/account.php) |
| Admin | [127.0.0.1:8000/admin/login.php](http://127.0.0.1:8000/admin/login.php) |
| Mailpit inbox | [127.0.0.1:8025](http://127.0.0.1:8025/) |

## Windows 11: college demo

1. Install [XAMPP for Windows](https://www.apachefriends.org/download.html) with PHP 8.2 and MariaDB. Use the default location `C:\xampp` or adjust the paths below. Copy the project files to `C:\salespro` (the folder should contain `index.php`, `salespro.sql`, and `migrations`).
2. Open the XAMPP Control Panel and start **MySQL** (XAMPP's label for MariaDB) and **Apache** to set up the database. Open [phpMyAdmin](http://127.0.0.1/phpmyadmin/).
   - **Fresh demo database:** From the phpMyAdmin home screen, open **Import** and select `C:\salespro\salespro.sql`. This creates and seeds `salespro`.
   - **Existing database:** Select `salespro`, open **Export**, and save a SQL backup. **Do not import `salespro.sql`** over existing data; it drops tables.
   - In either case, select the `salespro` database. If the `customers` table is not present, open **Import** and select `C:\salespro\migrations\001_customer_accounts.sql`. Apply it once. If an import fails partway through, stop rather than rerunning it over a partial schema. After setup, leave MySQL running and stop Apache.
3. Download the Windows ZIP from [Mailpit releases](https://github.com/axllent/mailpit/releases/latest): `mailpit-windows-amd64.zip` for Intel/AMD or `mailpit-windows-arm64.zip` for ARM. Extract it to `C:\mailpit`. Create a Windows desktop shortcut with this **Target**, then open it:

   ```text
   "C:\mailpit\mailpit.exe" --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025
   ```

4. Make a second shortcut with the following **Target** and open it to start the store. Keep both shortcut windows open during the demo:

   ```text
   "C:\xampp\php\php.exe" -c "C:\xampp\php\php.ini" -S 127.0.0.1:8000 -t "C:\salespro"
   ```

5. Open the store and Mailpit using the links above. Use `127.0.0.1`, not `localhost`, throughout the demo so email links return to the same browser session. If Windows asks whether to allow incoming network access for these services, decline; local access is enough.

## Linux: development

From the project root, start the existing MariaDB runtime in its own fish terminal:

```fish
set r "$HOME/Projects/Pritom-Site-Admin&DB"
env LD_LIBRARY_PATH="$r/root/usr/lib" "$r/root/usr/bin/mariadbd" --no-defaults --basedir="$r/root/usr" --datadir="$r/db" --bind-address=127.0.0.1 --port=3306 --socket="$r/db.sock" --pid-file="$r/db.pid" --skip-name-resolve
```

For a **fresh database only**, import the seed:

```fish
set r "$HOME/Projects/Pritom-Site-Admin&DB"
env LD_LIBRARY_PATH="$r/root/usr/lib" "$r/root/usr/bin/mariadb" -h 127.0.0.1 -u root < salespro.sql
```

If the account migration hasn't been applied, back up the database and apply it once:

```fish
set r "$HOME/Projects/Pritom-Site-Admin&DB"
set backup "$HOME/salespro-before-accounts-"(date +%Y%m%d-%H%M%S)".sql"
umask 077
env LD_LIBRARY_PATH="$r/root/usr/lib" "$r/root/usr/bin/mariadb-dump" --single-transaction -h 127.0.0.1 -u root salespro > "$backup"; and \
  env LD_LIBRARY_PATH="$r/root/usr/lib" "$r/root/usr/bin/mariadb" -h 127.0.0.1 -u root salespro < migrations/001_customer_accounts.sql
```

Download and extract the matching Linux archive from [Mailpit releases](https://github.com/axllent/mailpit/releases/latest). From the extracted directory, run this in a separate terminal:

```fish
./mailpit --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025
```

Start PHP in another terminal:

```fish
set r "$HOME/Projects/Pritom-Site-Admin&DB"
env LD_LIBRARY_PATH="$r/root/usr/lib" "$r/root/usr/bin/php" -n -d "extension=$r/root/usr/lib/php/modules/pdo_mysql.so" -d "session.save_path=$r/sessions" -S 127.0.0.1:8000 -t "$HOME/Projects/Pritom-Site"
```

## Demo walkthrough

1. Add a product to your cart before signing in. Register with a demo address such as `student@example.test`, then open the message in Mailpit. Follow the link, confirm with your registration password, and sign in. The guest cart should carry over.
2. In **Your account**, add a shipping address. Check out from the cart using that address and open the receipt.
3. In the admin panel, sign in with the seeded demo credentials (`admin` / `admin123`). Open the order and add a carrier and tracking number. Check that the update appears in the customer's order history.
4. In a separate browser profile, sign in as a different verified customer. The first customer's receipt should not open there. Use **Forgot password** to demonstrate the email reset flow.

### Troubleshooting

- **No email:** Start Mailpit before registering, then use **Resend verification**. The store defaults to SMTP at `127.0.0.1:1025`. If you change ports or the store URL, set `MAILPIT_HOST`, `MAILPIT_PORT`, or `APP_URL` before starting PHP.
- **Database connection error:** Check that MariaDB is running and `salespro` is imported. The local defaults in `config/db.php` are `127.0.0.1:3306`, user `root`, and a blank password. The PHP runtime needs `pdo_mysql` and `mbstring`.
- **Port already in use:** Stop the conflicting local service. If you change the PHP port, update `APP_URL` to match or verification links will point to the wrong port.

This setup is for a local demo only. Use dummy data, keep the services off the network, and don't expose the seeded admin credentials or Mailpit inbox on a public host.
