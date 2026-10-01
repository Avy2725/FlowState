# FlowState

FlowState is a group productivity dashboard built with PHP, MySQL and JavaScript. Users track tasks and projects, and admins review user activity, manage accounts and read audit logs.

## Features

- Sign up for users and admins, with email OTP verification before an account becomes active
- Login with account status checks (pending, active, suspended)
- User dashboard for tasks and projects (priority, due date, status)
- Admin dashboard for account management, activity logs and workflow analytics
- Logout that clears the session

## Pages

| File | Purpose |
|---|---|
| `login.php` | Login form and session setup |
| `signUp.php`, `signUpAdmin.php` | Account registration |
| `otpverify.php` | One-time password verification |
| `userdashboard.php` | Task and project tracking |
| `admindashboard.php` | Admin tools and logs |
| `logout.php` | Ends the session |
| `styles/` | Page stylesheets |
| `CA_IMG/` | Uploaded profile images |

## Running locally

1. Install PHP 8+ and MySQL (XAMPP or similar works).
2. Create the database and import your schema.
3. Copy `connection.example.php` to `connection.php` and set your credentials.
4. Add a `varifyotpemail.php` that defines `send_verification($fullname, $email, $otp)` for sending the OTP email.
5. Serve the folder with Apache or `php -S localhost:8000`, then open `login.php`.

## Password hashing

New accounts use `password_hash()` once `ca_userPass` can hold it. Run `migrations/001_widen_password_column.sql` once on an existing database. Until then the app keeps using md5 so logins still work, and legacy md5 passwords are upgraded the next time each user signs in.
