# Boost Marine — сайт и админка

> **Полная техническая документация:** [docs/TECHNICAL.md](docs/TECHNICAL.md) · **GitHub:** [github.com/5eeee/boost-marine](https://github.com/5eeee/boost-marine) · **Прод:** https://boostmarine.ru/

## Структура проекта

```
boost_marine_site/
│
├── site/                          ← Публичный сайт (boostmarine.ru)
│   ├── index.php                  ← Роутер ЧПУ + SEO
│   ├── sitemap.php
│   ├── robots.txt
│   ├── .htaccess
│   ├── pages/                     ← HTML-страницы
│   │   ├── index.html
│   │   ├── services.html
│   │   ├── equipment.html
│   │   ├── blog.html
│   │   └── article.html
│   └── assets/
│       ├── css/                   ← Стили
│       ├── js/                    ← Скрипты
│       ├── img/                   ← Картинки, иконки
│       └── media/                 ← Видео
│
├── admin/                         ← Админка (admin.boostmarine.ru)
│   ├── index.php                  ← Главная панель
│   ├── login.php
│   ├── api.php
│   ├── bot.php
│   ├── miniapp.php
│   ├── track.php
│   ├── config.php                 ← Подключение config/config.php
│   ├── assets/
│   │   ├── css/admin.css
│   │   └── js/admin.js
│   ├── includes/                  ← Блоки вкладок
│   ├── lib/                       ← API-обёртки (Метрика, Вебмастер, ИИ)
│   ├── config/config.php          ← БД, ключи, настройки
│   ├── tools/                     ← Служебные скрипты (миграции, диагностика)
│   ├── sql/install.sql
│   ├── uploads/                   ← Загруженные файлы
│   ├── tinymce/
│   └── vendor/
│
└── deploy/
    ├── ftp_deploy.py              ← Заливка на хостинг
    └── restructure.py             ← Скрипт реструктуризации
```

## Деплой на хостинг

```powershell
$env:BM_FTP_PASS = "ваш_пароль_ftp"
python deploy/ftp_deploy.py site admin
```

- Сайт заливается в `www/boostmarine.ru` из папки `site/`
- Админка — в `www/admin.boostmarine.ru` из папки `admin/`
- FTP-хост: `ftp.boostmarine.ru`

## После изменений CSS/JS

Увеличьте версию в `admin/config/config.php` → `ASSET_VERSION` и в HTML `?v=...` на сайте.

## Домены

| Что | URL |
|-----|-----|
| Сайт | https://boostmarine.ru |
| API / медиа | https://admin.boostmarine.ru |
| Трекер | https://admin.boostmarine.ru/track.php |
