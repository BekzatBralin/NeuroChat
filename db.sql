-- phpMyAdmin SQL Dump
-- version 5.2.1deb1+deb12u1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Июл 29 2026 г., 17:24
-- Версия сервера: 10.11.14-MariaDB-0+deb12u2
-- Версия PHP: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `neurochat`
--

-- --------------------------------------------------------

--
-- Структура таблицы `admin_notes`
--

CREATE TABLE `admin_notes` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `status` enum('plan','wip','done') NOT NULL DEFAULT 'plan',
  `created_at` int(11) NOT NULL DEFAULT unix_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `admin_notes_app`
--

CREATE TABLE `admin_notes_app` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `status` varchar(20) DEFAULT 'plan',
  `created_at` int(11) DEFAULT unix_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `admin_notes_archive`
--

CREATE TABLE `admin_notes_archive` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `updated_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `read_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `token` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expires` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `chats`
--

CREATE TABLE `chats` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `uid` varchar(36) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Новый чат',
  `model` varchar(50) NOT NULL,
  `pinned` tinyint(1) DEFAULT 0,
  `created_at` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `project_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `chat_projects`
--

CREATE TABLE `chat_projects` (
  `chat_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `created_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `fcm_tokens`
--

CREATE TABLE `fcm_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` text NOT NULL,
  `device_hash` varchar(64) NOT NULL,
  `created_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `info_docs`
--

CREATE TABLE `info_docs` (
  `doc_type` varchar(32) NOT NULL,
  `content` longtext NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `chat_id` int(10) UNSIGNED NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `cache` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mobile_auth_tokens`
--

CREATE TABLE `mobile_auth_tokens` (
  `state` varchar(64) NOT NULL,
  `token` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `models`
--

CREATE TABLE `models` (
  `id` int(11) NOT NULL,
  `key_name` varchar(64) NOT NULL,
  `display_name` varchar(128) NOT NULL,
  `backend_model` varchar(256) NOT NULL,
  `color_class` varchar(64) DEFAULT 'rigel',
  `base_energy` int(11) DEFAULT 1,
  `price_input` decimal(10,6) DEFAULT 0.000000,
  `price_output` decimal(10,6) DEFAULT 0.000000,
  `is_active` tinyint(1) DEFAULT 1,
  `is_stream` tinyint(1) DEFAULT 1,
  `supports_files` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `system_prompt` text DEFAULT NULL,
  `description` varchar(512) DEFAULT '',
  `created_at` int(11) DEFAULT 0,
  `updated_at` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `data` longtext NOT NULL,
  `expires` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `shared_chats`
--

CREATE TABLE `shared_chats` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `chat_uid` varchar(64) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `usage_log`
--

CREATE TABLE `usage_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `model` varchar(50) NOT NULL,
  `input_tokens` int(10) UNSIGNED DEFAULT 0,
  `output_tokens` int(10) UNSIGNED DEFAULT 0,
  `ts` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `google_id` varchar(64) DEFAULT NULL,
  `tg_id` varchar(32) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `nickname` varchar(32) DEFAULT NULL,
  `avatar` text DEFAULT NULL,
  `focus_bg` varchar(500) DEFAULT '',
  `accent_color` varchar(7) DEFAULT '#4f8fff',
  `role` enum('guest','user','pro','admin') NOT NULL DEFAULT 'guest',
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_login` int(10) UNSIGNED DEFAULT NULL,
  `push_enabled` tinyint(1) DEFAULT 1,
  `tg_token` varchar(64) DEFAULT NULL,
  `original_avatar` varchar(500) DEFAULT '',
  `def_search` int(11) DEFAULT 3,
  `cache` tinyint(1) DEFAULT 1,
  `notifications` tinyint(1) DEFAULT 1,
  `energy` int(11) DEFAULT 0,
  `last_energy_refill` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_modes`
--

CREATE TABLE `user_modes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `slot` tinyint(4) NOT NULL,
  `name` varchar(50) NOT NULL,
  `prompt` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_skills`
--

CREATE TABLE `user_skills` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `content` longtext NOT NULL,
  `is_global` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_skill_chats`
--

CREATE TABLE `user_skill_chats` (
  `skill_id` int(11) NOT NULL,
  `chat_id` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_variables`
--

CREATE TABLE `user_variables` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `admin_notes`
--
ALTER TABLE `admin_notes`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `admin_notes_app`
--
ALTER TABLE `admin_notes_app`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `admin_notes_archive`
--
ALTER TABLE `admin_notes_archive`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`token`);

--
-- Индексы таблицы `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`),
  ADD KEY `idx_chats_user` (`user_id`,`updated_at`);

--
-- Индексы таблицы `chat_projects`
--
ALTER TABLE `chat_projects`
  ADD PRIMARY KEY (`chat_id`,`project_id`),
  ADD KEY `idx_project_id` (`project_id`);

--
-- Индексы таблицы `fcm_tokens`
--
ALTER TABLE `fcm_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device` (`device_hash`);

--
-- Индексы таблицы `info_docs`
--
ALTER TABLE `info_docs`
  ADD PRIMARY KEY (`doc_type`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_chat` (`chat_id`,`created_at`);

--
-- Индексы таблицы `mobile_auth_tokens`
--
ALTER TABLE `mobile_auth_tokens`
  ADD PRIMARY KEY (`state`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `models`
--
ALTER TABLE `models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Индексы таблицы `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expires` (`expires`);

--
-- Индексы таблицы `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Индексы таблицы `shared_chats`
--
ALTER TABLE `shared_chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Индексы таблицы `usage_log`
--
ALTER TABLE `usage_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usage` (`user_id`,`model`,`ts`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `google_id` (`google_id`);

--
-- Индексы таблицы `user_modes`
--
ALTER TABLE `user_modes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slot` (`user_id`,`slot`);

--
-- Индексы таблицы `user_skills`
--
ALTER TABLE `user_skills`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `user_skill_chats`
--
ALTER TABLE `user_skill_chats`
  ADD PRIMARY KEY (`skill_id`,`chat_id`);

--
-- Индексы таблицы `user_variables`
--
ALTER TABLE `user_variables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_var` (`user_id`,`name`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `admin_notes`
--
ALTER TABLE `admin_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `admin_notes_app`
--
ALTER TABLE `admin_notes_app`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `admin_notes_archive`
--
ALTER TABLE `admin_notes_archive`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `chats`
--
ALTER TABLE `chats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `fcm_tokens`
--
ALTER TABLE `fcm_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `models`
--
ALTER TABLE `models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `shared_chats`
--
ALTER TABLE `shared_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `usage_log`
--
ALTER TABLE `usage_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_modes`
--
ALTER TABLE `user_modes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_skills`
--
ALTER TABLE `user_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_variables`
--
ALTER TABLE `user_variables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `usage_log`
--
ALTER TABLE `usage_log`
  ADD CONSTRAINT `usage_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_skill_chats`
--
ALTER TABLE `user_skill_chats`
  ADD CONSTRAINT `user_skill_chats_ibfk_1` FOREIGN KEY (`skill_id`) REFERENCES `user_skills` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
