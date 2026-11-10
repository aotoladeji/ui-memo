-- Run once as a MySQL admin:  mysql -u root -p < scripts/create-databases.sql
CREATE DATABASE IF NOT EXISTS ui_memo      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ui_memo_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  -- used by `php artisan test`
CREATE USER IF NOT EXISTS 'ui_memo'@'localhost' IDENTIFIED BY 'ui_memo_pw';
GRANT ALL PRIVILEGES ON ui_memo.*      TO 'ui_memo'@'localhost';
GRANT ALL PRIVILEGES ON ui_memo_test.* TO 'ui_memo'@'localhost';
FLUSH PRIVILEGES;
