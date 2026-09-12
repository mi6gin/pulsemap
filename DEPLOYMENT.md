# Развёртывание PulseMap

Проект готов к двум вариантам: Render Blueprint и любой сервер с Docker Compose. В обоих случаях нужны отдельный web-процесс, постоянно работающий queue worker и постоянная PostgreSQL.

## Вариант 1: Render Blueprint

1. В Render выберите **New → Blueprint** и подключите репозиторий `mi6gin/pulsemap`.
2. Render прочитает `render.yaml` и предложит создать `pulsemap-web`, `pulsemap-worker` и `pulsemap-db`.
3. Подтвердите создание ресурсов. `APP_KEY_BASE64` генерируется автоматически и одинаков для web и worker; строка подключения к PostgreSQL передаётся автоматически.
4. Дождитесь зелёного healthcheck `/up`. Миграции и сид-пользователь запускаются перед web-процессом.
5. Откройте выданный адрес `https://pulsemap-web-….onrender.com` и войдите как `demo@example.com` / `password`.
6. Проверьте любую карточку Яндекс.Карт. Worker должен перейти из `queued` в `syncing`, затем в `complete`.
7. Замените текст в разделе «Демо» README на выданный URL и отправьте коммит.

Web-сервис и PostgreSQL в Blueprint используют free-планы, если они доступны аккаунту. Постоянный background worker обычно тарифицируется отдельно — проверьте цену на экране подтверждения до создания ресурсов.

## Вариант 2: Docker Compose на VPS

Требования: Docker Engine и Docker Compose.

```bash
cp .env.production.example .env.production
php artisan key:generate --show
```

В `.env.production` замените:

- `APP_KEY` на результат `key:generate --show`;
- `APP_URL` на HTTPS-адрес приложения;
- `DB_PASSWORD` и `POSTGRES_PASSWORD` на один сильный пароль;
- при необходимости `APP_PORT` и `YANDEX_MAPS_PROXY`.

Запуск:

```bash
docker compose --env-file .env.production -f compose.production.yaml up -d --build
docker compose --env-file .env.production -f compose.production.yaml ps
docker compose --env-file .env.production -f compose.production.yaml logs -f worker
```

Проверка:

```bash
curl --fail "${APP_URL}/up"
```

Для обновления:

```bash
git pull --ff-only
docker compose --env-file .env.production -f compose.production.yaml up -d --build
```

## Финальный чек-лист сдачи

- `/up` отвечает HTTP 200;
- вход `demo@example.com` / `password` работает;
- неверная ссылка показывает валидационную ошибку;
- произвольная карточка переходит `queued → syncing → complete`;
- число сохранённых отзывов соответствует доступной выдаче;
- переход на вторую страницу показывает следующие 50 отзывов без перезагрузки;
- после повторной синхронизации нет дублей;
- web и worker используют одну PostgreSQL и одно общее cache-хранилище;
- в README добавлены публичный URL и ссылка на репозиторий.
