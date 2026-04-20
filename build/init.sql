CREATE TABLE `account` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tax_id` VARCHAR(16) UNIQUE NOT NULL,
  `owner_name` VARCHAR(100) NOT NULL,
  `balance` DECIMAL(17, 2) NOT NULL,
  `created_at` TIMESTAMP NOT NULL,
  `id_currency` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_currency`) REFERENCES `currency`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transaction` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_account` INT(11) NOT NULL,
  `type` ENUM(`DEPOSIT`,`WITHDRAWL`) NOT NULL,
  `amount` DECIMAL(17, 2) NOT NULL,
  `description` VARCHAR(255),
  `created_at` TIMESTAMP NOT NULL,
  `balance_after` DECIMAL(17, 2),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_account`) REFERENCES `account`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `currency` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) UNIQUE NOT NULL,
  PRIMARY KEY (`id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
