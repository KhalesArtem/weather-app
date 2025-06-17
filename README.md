# Приложение "Погода" 🌤️

Веб-приложение на Symfony для получения информации о погоде в режиме реального времени с интеллектуальной системой кеширования.

## 📋 Содержание

- [Возможности](#возможности)
- [Требования](#требования)
- [Быстрый старт](#быстрый-старт)
- [Архитектура](#архитектура)
- [Использование](#использование)
- [API документация](#api-документация)
- [Тестирование](#тестирование)
- [Структура проекта](#структура-проекта)

## 🚀 Возможности

### Основные функции
- ✅ Получение данных о погоде в реальном времени через WeatherAPI.com
- ✅ Интеллектуальное кеширование с настраиваемым TTL (по умолчанию 30 минут)
- ✅ Автоматический возврат к устаревшим данным при недоступности API
- ✅ Веб-интерфейс и RESTful API
- ✅ Комплексное логирование с отдельным каналом для погоды
- ✅ Docker-окружение для разработки
- ✅ Полное покрытие тестами (unit и функциональные)

### Технологии
- **PHP 8.4** с Xdebug
- **Symfony 7.3 LTS**
- **Doctrine ORM** с MySQL 8.0
- **Docker & Docker Compose**
- **PHPUnit** для тестирования
- **Bootstrap 5** для UI

## 📦 Требования

- Docker и Docker Compose
- Make (для удобных команд)
- Git

## 🏃 Быстрый старт

### 1. Клонирование репозитория
```bash
git clone https://github.com/your-repo/weather-app.git
cd weather-app
```

### 2. Настройка окружения
```bash
# Копируем файл с переменными окружения
cp .env.local.example .env.local

# Редактируем .env.local и добавляем ваш API ключ
# WEATHER_API_KEY=ваш_ключ_api
```

### 3. Запуск приложения
```bash
# Полная установка и запуск
make setup

# Приложение будет доступно по адресу:
# http://localhost:8080
```

## 🏗️ Архитектура

### Структура сервисов

```
┌─────────────┐     ┌───────────────────┐     ┌──────────────┐
│   Browser   │────▶│  WeatherController│────▶│WeatherService│
└─────────────┘     └───────────────────┘     └──────┬───────┘
                                                     │
                           ┌─────────────────────────┴─────────┐
                           ▼                                   ▼
                    ┌──────────────┐                 ┌───────────────────┐
                    │WeatherApiClient│               │WeatherCacheService│
                    └──────┬───────┘                 └───────┬───────────┘
                           │                                 │
                           ▼                                 ▼
                    ┌──────────────┐                 ┌───────────────┐
                    │ WeatherAPI   │                 │   MySQL DB    │
                    └──────────────┘                 └───────────────┘
```

### Стратегия кеширования

1. **Cache First**: Сначала проверяется кеш в базе данных
2. **TTL-based**: Настраиваемое время жизни кеша (по умолчанию 30 минут)
3. **Stale Fallback**: Возврат устаревших данных при сбое API
4. **Per-City Storage**: Данные кешируются для каждого города отдельно

## 💻 Использование

### Веб-интерфейс

#### Просмотр погоды
```
http://localhost:8080/weather/Odessa
http://localhost:8080/weather/Saint-Petersburg
http://localhost:8080/weather/New York
```

#### Принудительное обновление
```
http://localhost:8080/weather/Odessa?refresh=true
```

### API эндпоинты

#### 1. Получить данные о погоде
```bash
# Обычный запрос
curl http://localhost:8080/api/weather/Odessa

# С форматированием
curl http://localhost:8080/api/weather/Odessa | jq

# Принудительное обновление (игнорировать кеш)
curl "http://localhost:8080/api/weather/Odessa?refresh=true"
```

**Успешный ответ:**
```json
{
  "success": true,
  "data": {
    "city": "Odessa",
    "country": "Ukraine",
    "temperature": -15.0,
    "condition": "Снег",
    "humidity": 85,
    "wind_speed": 15.0,
    "last_updated": "2025-06-17 12:00:00",
    "api_last_updated": "2025-06-17 11:45",
    "cached": true,
    "cache_age_minutes": 15,
    "stale": false
  }
}
```

#### 2. Очистить кеш города
```bash
curl -X POST http://localhost:8080/api/weather/Odessa/cache/clear
# или
curl -X DELETE http://localhost:8080/api/weather/Odessa/cache/clear
```

#### 3. Получить статистику кеша
```bash
curl http://localhost:8080/api/weather/cache/stats | jq
```

**Ответ:**
```json
{
  "success": true,
  "data": {
    "total_cached_cities": 5,
    "fresh_cache_entries": 3,
    "stale_cache_entries": 2,
    "cache_max_age_minutes": 30
  }
}
```

#### 4. API документация
```bash
curl http://localhost:8080/api/weather | jq
```

## 🧪 Тестирование

### Запуск тестов
```bash
# Все тесты
make test

# Только unit тесты
make test-unit

# Только функциональные тесты
make test-functional
```

### Покрытие тестами
- **Unit тесты**: 40 тестов
- **Функциональные тесты**: 14 тестов
- **Всего assertions**: 261

## 📁 Структура проекта

```
weather-app/
├── config/                 # Конфигурация Symfony
│   ├── packages/          # Настройки пакетов
│   │   ├── doctrine.yaml  # Конфигурация БД
│   │   ├── monolog.yaml   # Логирование
│   │   └── test/         # Тестовое окружение
│   └── services.yaml      # Определение сервисов
├── docker/                # Docker конфигурация
│   ├── nginx/            # Веб-сервер
│   └── php/              # PHP контейнер
├── migrations/            # Миграции БД
├── public/               # Публичная директория
├── src/                  # Исходный код
│   ├── Controller/       # Контроллеры
│   ├── Entity/          # Doctrine сущности
│   ├── Exception/       # Кастомные исключения
│   ├── Form/            # Symfony формы
│   ├── Repository/      # Репозитории
│   └── Service/         # Бизнес-логика
├── templates/            # Twig шаблоны
├── tests/               # Тесты
│   ├── Unit/           # Unit тесты
│   └── Functional/     # Функциональные тесты
├── var/                 # Кеш, логи
├── .env.local.example   # Пример конфигурации
├── docker-compose.yml   # Docker настройки
├── Makefile            # Команды для управления
└── README_RU.md        # Этот файл
```

## 🛠️ Команды Make

### Основные команды
```bash
make help        # Показать все доступные команды
make setup       # Полная установка проекта
make up          # Запустить контейнеры
make down        # Остановить контейнеры
make restart     # Перезапустить контейнеры
```

### База данных
```bash
make db-create   # Создать базы данных
make db-migrate  # Выполнить миграции
make db-fixtures # Загрузить тестовые данные
make db-reset    # Полный сброс БД
make test-db     # Настроить тестовую БД
```

### Разработка
```bash
make shell       # Войти в PHP контейнер
make logs        # Просмотр логов погоды
make cache-clear # Очистить кеш Symfony
make install     # Установить зависимости
```

## 🔧 Конфигурация

### Переменные окружения (.env.local)

```bash
# API конфигурация
WEATHER_API_KEY=ваш_ключ_api_здесь
WEATHER_API_URL=https://api.weatherapi.com/v1
WEATHER_CACHE_MAX_AGE_MINUTES=30

# База данных
DATABASE_URL="mysql://weather_user:weather_pass@mysql:3306/weather_db?serverVersion=8.0"

# Окружение
APP_ENV=dev
APP_DEBUG=1
```

### Получение API ключа

1. Зарегистрируйтесь на https://www.weatherapi.com/signup.aspx
2. Получите бесплатный API ключ (1 миллион запросов в месяц)
3. Добавьте ключ в файл `.env.local`

## 📊 Логирование

Приложение использует отдельный канал `weather` для логирования:

```bash
# Просмотр логов в реальном времени
make logs

# Или вручную
docker compose exec php tail -f var/log/weather_dev.log
```

### Уровни логирования
- **INFO**: Общая информация о запросах
- **DEBUG**: Детали кеширования
- **WARNING**: Возврат к устаревшим данным
- **ERROR**: Ошибки API и исключения

## 🐛 Решение проблем

### Конфликт портов
```bash
# Проверить занятые порты
lsof -i :8080  # Веб-сервер
lsof -i :3306  # MySQL

# Остановить контейнеры
make down
```

### Проблемы с API ключом
1. Проверьте ключ в `.env.local`
2. Проверьте логи: `make logs`
3. Протестируйте API напрямую:
   ```bash
   curl "https://api.weatherapi.com/v1/current.json?key=ВАШ_КЛЮЧ&q=Moscow"
   ```

### Очистка кеша
```bash
# Очистить весь кеш
make cache-clear

# Очистить кеш конкретного города через API
curl -X POST http://localhost:8080/api/weather/Moscow/cache/clear
```

## 🚀 Деплой в продакшн

### 1. Настройка окружения
```bash
APP_ENV=prod
APP_DEBUG=0
```

### 2. Оптимизация
```bash
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
```

### 3. Безопасность
- Используйте HTTPS
- Установите надежные пароли БД
- Регулярно обновляйте API ключи
- Мониторьте лимиты API

## 📝 Лицензия

Этот проект создан в учебных целях.

## 👨‍💻 Автор

Создано с использованием Symfony и лучших практик разработки.

---

💡 **Совет**: Для реальных данных о погоде обязательно замените тестовый API ключ на настоящий!
