# Техническая документация — Boost Marine

> Репозиторий: [github.com/5eeee/boost-marine](https://github.com/5eeee/boost-marine)  
> Прод: https://boostmarine.ru/  
> Автор: Владимир Кутомкин

## 1. Назначение

Корпоративный сайт морской сервисной компании и PHP-админка: услуги, оборудование, блог, SEO, Яндекс.Метрика/Вебмастер, Telegram-бот, AI-генерация статей (Ollama).

## 2. Стек

| Часть | Технологии |
|-------|------------|
| Публичный сайт | PHP-роутер, HTML/CSS/JS, Apache `.htaccess` |
| Админка | PHP 8, MySQL, TinyMCE, Composer |
| Deploy | Python FTP (`deploy/ftp_deploy.py`) |
| AI | Ollama (localhost:11434) |

## 3. Структура

```
site/                     # boostmarine.ru
├── index.php             # Роутер страниц
├── pages/*.html          # Контент страниц
└── assets/{css,js,img}/

admin/                    # admin.boostmarine.ru
├── index.php, login.php
├── api.php               # REST API для фронта
├── bot.php, miniapp.php  # Telegram
├── track.php             # Аналитика
├── config/config.php     # БД, SMTP, API keys
├── sql/install.sql       # Схема MySQL
└── uploads/
```

## 4. Публичное API (`admin/api.php?type=`)

| type | Данные |
|------|--------|
| `works` | Портфолио работ |
| `products` | Продукты/оборудование |
| `services` | Услуги |
| `articles` | Список статей блога |
| `article&slug=...` | Одна статья |
| `contacts` | Контакты |
| `ticker` | Бегущая строка |

## 5. MySQL-таблицы

`users`, `works`, `work_images`, `products`, `articles`, `settings`, `bot_sessions`, analytics-таблицы и др. — см. `admin/sql/install.sql`

## 6. Конфигурация

Файл `admin/config/config.php`: `DB_*`, `SMTP_*`, `METRICA_*`, `TG_BOT_TOKEN`, `AI_API_URL`, `AI_MODEL`, `ASSET_VERSION`

> **Важно:** секреты вынести в env и не коммитить в репозиторий.

## 7. Деплой

```powershell
$env:BM_FTP_PASS = "your_password"
python deploy/ftp_deploy.py site admin
```

- Сайт → `www/boostmarine.ru`
- Админка → `www/admin.boostmarine.ru`
- После изменения CSS/JS — обновить `ASSET_VERSION`

## 8. Функции админки

- CRUD статей, услуг, работ
- SEO-настройки, sitemap
- Яндекс.Метрика и Вебмастер
- Telegram-бот и miniapp
- AI-генерация контента через Ollama
