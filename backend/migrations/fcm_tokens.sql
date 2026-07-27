-- Таблица для хранения FCM-токенов устройств
CREATE TABLE IF NOT EXISTS fcm_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token TEXT NOT NULL,
    device_hash VARCHAR(64) NOT NULL,
    created_at INT NOT NULL,
    UNIQUE KEY unique_device (device_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Добавляем read_at в admin_notifications если ещё нет
ALTER TABLE admin_notifications ADD COLUMN IF NOT EXISTS read_at INT DEFAULT NULL;
