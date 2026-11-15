# ব্যক্তিগত হিসাব — Personal Finance Manager

বাংলায় ব্যক্তিগত আয়-ব্যয়ের হিসাব রাখার একটি সহজ ওয়েব অ্যাপ। নিজের সার্ভারে চালান, আপনার ডেটা আপনার কাছেই থাকবে।

A simple, self-hosted personal finance web app with a Bengali interface. Plain PHP and MySQL/MariaDB, no framework, runs on ordinary shared hosting.

## ফিচার · Features

- একাধিক অ্যাকাউন্ট (ব্যাংক, নগদ, মোবাইল ব্যাংকিং, ক্রেডিট কার্ড) এবং প্রতিটির চলতি ব্যালেন্স
- আয়, ব্যয়, অ্যাকাউন্ট ট্রান্সফার ও ফেরতযোগ্য লেনদেন
- ধার নেওয়া/দেওয়া এবং পরিশোধের হিসাব, বাকি পরিমাণ ও অবস্থাসহ
- ক্যাটাগরি ও সাব-ক্যাটাগরি, মাসিক বাজেট
- মাসিক রিপোর্ট ও চার্ট
- একাধিক ব্যবহারকারী — প্রত্যেকে শুধু নিজের হিসাব দেখেন

---

- Multiple accounts (bank, cash, mobile banking, credit card) with running balances
- Income, expenses, transfers between accounts and reimbursements
- Loans you took or gave, with repayments, remaining amount and status
- Categories with sub-categories, and budgets per category
- Monthly reports with charts
- Multi-user: each person sees only their own data

## প্রয়োজন · Requirements

- PHP 8.1 or newer with the `mysqli` extension
- MySQL 5.7+ or MariaDB 10.3+
- Apache (the included `.htaccess` blocks access to `.env` and internal files). On Nginx, deny those paths in your server config.

## ইনস্টল · Installation

1. Copy the files into a folder your web server serves, e.g. `public_html/finance`.
2. Create an empty database and a user for it.
3. Load the schema:
   ```bash
   mysql -u YOUR_USER -p YOUR_DATABASE < schema.sql
   ```
4. Create your settings file:
   ```bash
   cp .env.example .env
   ```
   Fill in `DB_NAME`, `DB_USER` and `DB_PASSWORD`. Keep `APP_DEBUG=false` on a live site.
5. Open the site in your browser and sign up. New accounts start with a set of common categories and a cash account.

To keep `.env` outside the web folder, put it anywhere and set the `APP_ENV_FILE` environment variable to its full path.

## আপডেট · Upgrading

Apply any new files in `migrations/` that your database doesn't have yet, in date order:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < migrations/2026-11-15_auth_attempts.sql
```

## নিরাপত্তা · Security

- Passwords are stored with `password_hash`.
- Every form carries a CSRF token; deletes only happen through POST.
- Login and signup are rate limited per IP and per username.
- If you run behind a reverse proxy or CDN, set `CLIENT_IP_HEADER` in `.env` so rate limits see the real visitor IP.

Found a security problem? Please report it privately instead of opening a public issue.

## অবদান · Contributing

Issues and pull requests are welcome. Please keep the interface in Bengali and code, comments and identifiers in English.

## লাইসেন্স · License

[MIT](LICENSE)
