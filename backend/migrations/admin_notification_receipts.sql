-- Track admin announcements separately for every user.
CREATE TABLE IF NOT EXISTS admin_notification_receipts (
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at INT NOT NULL,
    PRIMARY KEY (notification_id, user_id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
