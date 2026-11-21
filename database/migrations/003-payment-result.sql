ALTER TABLE payments
    ADD COLUMN transaction_date DATETIME NULL AFTER transaction_status;