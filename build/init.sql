
CREATE TABLE `currency` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) UNIQUE NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `account` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tax_id` VARCHAR(16) UNIQUE NOT NULL,
  `owner_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_currency` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_currency`) REFERENCES `currency`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transaction` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_account` INT(11) NOT NULL,
  `type` ENUM('DEPOSIT','WITHDRAWAL') NOT NULL,
  `amount` DECIMAL(17, 2) NOT NULL,
  `description` VARCHAR(255),
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `balance_after` DECIMAL(17, 2),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_account`) REFERENCES `account`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `currency` (`name`) VALUES ( 'AUD'), ( 'BRL'), ( 'CAD'), ( 'CHF'), ( 'CNY'), ( 'CZK'), ( 'DKK'), ( 'HKD'), ( 'HUF'), ( 'IDR'), ( 'ILS'), ( 'INR'), ( 'ISK'), ( 'JPY'), ( 'KRW'), ( 'MXN'), ( 'MYR'), ( 'NOK'), ( 'NZD'), ( 'PHP'), ( 'PLN'), ( 'RON'), ( 'SEK'), ( 'SGD'), ( 'THB'), ( 'TRY'), ( 'ZAR');

INSERT INTO `account` (`tax_id`, `owner_name`, `created_at`, `id_currency`) VALUES
  ( 'IT12345678901234', 'Mario Rossi', '2025-01-10 09:15:00', 1),
  ( 'US00000000001234', 'Alice Johnson', '2025-02-20 11:30:00', 2),
  ( 'GB99999999991234', 'John Smith', '2025-03-05 08:00:00', 3),
  ( 'IT98765432109876', 'Luisa Bianchi', '2025-04-12 16:45:00', 1);

INSERT INTO `transaction` (`type`, `amount`, `description`, `created_at`, `balance_after`) VALUES
  ( 'DEPOSIT', 1000.00, 'Initial deposit', '2025-01-10 09:16:00', 1000.00),
  ( 'WITHDRAWAL', 200.00, 'ATM withdrawal', '2025-01-15 10:00:00', 800.00),
  ( 'DEPOSIT', 50.00, 'Salary adjustment', '2025-01-31 12:00:00', 850.00),
  ( 'WITHDRAWAL', 50.00, 'Groceries', '2025-02-05 18:00:00', 800.00),
  ('DEPOSIT', 5000.00, 'Initial funding', '2025-02-20 11:31:00', 5000.00),
  ('WITHDRAWAL', 1250.50, 'Online purchase', '2025-02-25 14:20:00', 3749.50),
  ('DEPOSIT', 300.00, 'Refund', '2025-03-01 10:00:00', 4049.50),

  ( 'DEPOSIT', 250.00, 'Gift', '2025-03-05 08:05:00', 250.00),

  ('DEPOSIT', 100.00, 'Initial deposit', '2025-04-12 16:50:00', 100.00),
  ('WITHDRAWAL', 20.00, 'Coffee shop', '2025-04-13 09:10:00', 80.00);
