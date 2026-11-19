CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(64) NOT NULL,
    email VARCHAR(64) NOT NULL,
    mobile VARCHAR(32) NOT NULL ,
     created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  )  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


  
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY ,
    customer_id BIGINT UNSIGNED NOT NULL,
    payment_code VARCHAR(64) NOT NULL UNIQUE,
    gateway_payment_id VARCHAR(64) NULL UNIQUE ,
    payment_link TEXT NULL,
amount DECIMAL(13, 3) NOT NULL ,
     currency CHAR(3) NOT NULL DEFAULT 'BHD',
    status VARCHAR(32) NOT NULL DEFAULT 'initiated',
    transaction_id VARCHAR(64) NULL,
    transaction_status VARCHAR(32) NULL,
    response_code VARCHAR(64) NULL,
    response_message VARCHAR(512) NULL,
   created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at TIMESTAMP NOT NULL DEFAULT  CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_payments_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
   )  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;