# A2ice «АБСУРД» — единый локальный код и dev CI/CD

**Дата:** 2026-10-17. **Статус:** решения подготовлены в feature PR, не внедрены в локальную Windows и не включены на хостинге. Нельзя выдавать этот документ за установленный WordPress на dev или за работающий автодеплой.

## Единственный источник исходного кода

- Репозиторий: https://github.com/a2ice/absurd
- Windows workspace: `C:\OSP6\home\a2ice\projects\absurd`
- Редактируемые Git-каталоги: `wp-content/themes/absurd-stage-v1` и `wp-content/plugins/absurdtheater-core`.
- Текущий установленный WordPress до миграции: `C:\OSP6\home\absurdtheater-wp.local\public`, URL `http://127.0.0.1:8767/`.
- Важно: в репозитории **папка `wp-content` находится в корне Git**, а не под `public`. Не переносить пути молча.
- Исходный `origin/main` пока содержит только первоначальный README. **Нельзя переключить локальный WordPress на него, прежде чем код темы и плагина будет принят в main.** Сначала принять PR с кодом, потом синхронизировать локальный main.

### Как убрать копирование между Git и WordPress

В этой ветке имеется `scripts/link-local-wordpress.ps1`. На **Windows Dmitanium** после сохранения нужных локальных изменений в Git:

```powershell
cd C:\OSP6\home\a2ice\projects\absurd
git status --short
.\scripts\link-local-wordpress.ps1
```

Скрипт по умолчанию только показывает источник и текущую WordPress-цель. Проверь пути и что старая рабочая тема/плагин забэкаплены.

Только после проверки:

```powershell
.\scripts\link-local-wordpress.ps1 -Apply
```

Он **перемещает текущие две папки установленного кастомного кода в отдельный timestamped backup** под `C:\OSP6\user\a2ice-backups` и ставит в рабочем WordPress NTFS junction на каталоги Git-репозитория. **С этого момента Ctrl+S в VS Code меняет файлы, обслуживаемые WordPress; никакой sync-local-hero.ps1 больше не нужен.** Иногда потребуется Ctrl+Shift+R для браузерного кеша. Работающие данные/БД/ядро не перемещаются.

Это безопасный промежуточный шаг: если позже нужно физически поместить и локальное ядро WordPress внутрь нового workspace (например `.local-runtime/public`), делай отдельной операцией с проверкой vhost, перенастройкой OSP6 и откатом; не смешивай с первым переключением исходников.

**ВАЖНО о ветках:** NTFS junction указывает на *текущий working tree*, а не на конкретный Git commit. Если переключиться на ветку без темы (старый `main`), WordPress лишится исходников. Поэтому переходить на main **только после merge исходников темы и плагина в origin/main**, с чистым worktree и локальным backup. Команды после принятия PR:

```powershell
git fetch origin
git switch main
git pull --ff-only origin main
git status --short
```

После этого `git switch main` должен остаться единственным обычным рабочим режимом. Каждый `git commit/push` отправляет изменения в `origin/main` после явного коммита; простое сохранение файла отправки не производит. Для крупной функциональности предпочтительно PR в отдельной ветке, чтобы main оставался проверенным.

## Окружения

| Environment | URL | Фактически установлено |
|---|---|---|
| Local | `http://127.0.0.1:8767/` | WordPress 7.1.3 ранее проверен; путь старой установки надо перепроверить до junction |
| Dev | `https://absurd.a2ice.ru/` | **Владелец сообщил о настроенных поддомене и Let's Encrypt; WordPress НЕ подтверждён** |
| Future production | `https://theaterabsurd.ru/` | Домен и хостинг ещё не подтверждены |
| Historical static demo | `https://absurdtheater.a2ice.ru/` | Отдельный существующий сайт; не изменять |

## Как подготовить WordPress на dev без риска

Действия в CISHOST/ISPmanager проводятся отдельно от основного `a2ice.ru` и исторического демо.

1. На `absurd.a2ice.ru` определить **фактический document root**, проверить HTTPS/TLS, PHP 8.2+ с требуемыми модулями, лимиты загрузки, доступные диски и действующие файлы. **Не заменять файлы, если в этом корне уже есть значимый сайт.**
2. Сохранить серверный backup корня и важных настроек. Если WordPress отсутствует, создать **отдельную базу данных и отдельного БД-пользователя** с минимальными разрешениями; пароли хранить в панели/секретном менеджере, не в переписке и Git.
3. Установить текущий официальный WordPress и завершить первичную настройку на `https://absurd.a2ice.ru/`. Проверить админку и внешний HTTP 200, HTTPS, `/wp-json/`.
4. Дев-окружение должно быть неиндексируемым: WP Settings → Reading → Discourage search engines (это **только сигнал**), а по возможности HTTP Basic Auth / отдельный доступ. Проверить `robots` и `noindex`. Никаких реальных спектаклей, персональных данных или медиа клиента без подтверждения.
5. Сохранить отдельные dev-конфигурацию, БД и uploads. Не переносить в dev локальную тестовую БД: в ней есть демонстрационные записи.
6. Создать тематический каталог `wp-content/themes/absurd-stage-v1/` и каталог плагина `wp-content/plugins/absurdtheater-core/` (пути на FTP подтвердить относительно корня FTP пользователя), снять backup перед первым обновлением кода.

**Сейчас WordPress на dev не подтверждён.** Недоступность через внешний веб-инструмент не означает, что сайта нет. Нужен скриншот панели ISPmanager с *доменом, document root, PHP* и областью базы данных без секретов.

## GitHub Actions: main → dev

Workflow: `.github/workflows/deploy-dev.yml` (в текущем PR, автоматически появится после merge).

- На PR и push в `main`: проверки безопасности tracked files, PHP lint и JS syntax.
- На каждый push в `main`: **автоматический FTPS deployment** только при `DEV_WORDPRESS_READY=true` и `DEV_DEPLOY_ENABLED=true`.
- При ручном запуске `workflow_dispatch` можно делать dry-run **до включения автоматического deployment**, но при `DEV_WORDPRESS_READY=true`.
- Передача только **двух** каталогов исходного кода темы и плагина. **Никаких WP core, БД, конфигов, uploads или переключения URL/сертификатов**.
- State-файлы FTPS изолированы по теме и плагину; `dangerous-clean-slate=false`. Это уменьшает риск очистки иных серверных каталогов, но не заменяет backup.
- GitHub `development` environment; для него можно настроить ограничение ветки `main`. Не включать ручное approve environment, если требуется полностью автоматическая выкладка.

В репозитории **Settings → Secrets and variables → Actions** указать следующие **Secrets** (значения не присылать в чат):

| Secret | Описание |
|---|---|
| `DEV_FTP_HOST` | Подтверждённый FTPS hostname CISHOST |
| `DEV_FTP_USER` | Выделенный FTP-пользователь с доступом к dev, не всему хостингу |
| `DEV_FTP_PASSWORD` | Пароль FTPS |
| `DEV_THEME_FTP_DIR` | Путь в FTP, оканчивается строго на `/wp-content/themes/absurd-stage-v1/` |
| `DEV_PLUGIN_FTP_DIR` | Путь в FTP, оканчивается строго на `/wp-content/plugins/absurdtheater-core/` |

Repository **Variables**:

| Variable | Старт | Когда изменить |
|---|---|---|
| `DEV_WORDPRESS_READY` | `false` | `true` после установки и проверки WordPress + резервной копии на dev |
| `DEV_DEPLOY_ENABLED` | `false` | `true` только после успешного ручного dry-run, первого реального тестового deployment и E2E |

Если workflow размещает код не туда, **не менять пути наугад**: проверить FTP virtual root относительно панели ISPmanager. Не раскрывать пароли/секреты в логах.

### Порядок первого запуска

1. Независимо подтвердить работоспособность dev WordPress и сохранить backup.
2. Принять проверенный код в `main`, принять workflow с флагами **выключено**. В `main` должны существовать оба каталога.
3. Заполнить secrets, установить `DEV_WORDPRESS_READY=true`, оставить `DEV_DEPLOY_ENABLED=false`.
4. GitHub → Actions → `Validate and deploy WordPress to dev` → Run workflow на `main`, `dry_run=true`. Проверить точные FTP пути и список файлов.
5. После проверки и разрешения на dev публикацию — ручной `dry_run=false`, затем проверить правильность theme/plugin в dev WP, wp-admin, desktop/mobile и ключевые маршруты.
6. После реального E2E PASS включить `DEV_DEPLOY_ENABLED=true`. С этого момента обычный `git push origin main` автоматически запускает выкладку обновлённого кода на dev.
7. Откат: revert проблемного коммита в `main` → повторный deploy предыдущего содержимого и проверка dev. Для возможного частичного FTPS сбоя использовать сделанный перед деплоем серверный backup.

Для production нужен отдельный явно разрешённый workflow/окружение. Здесь **production не автоматизируется**.

## Что НЕ выполнено и зависит от доступа

- Не подтверждена реальная установленная dev CMS на CISHOST.
- Не подтверждён FTP document root/доступы и не заведены GitHub Secrets.
- В этой ветке подготовлены workflow и локальный junction script, но они пока **не применены на Windows или сервере**.
- `origin/main` пока первоначальный README; сначала PR с исходниками, затем обновление локального main.
- Не проведён независимый E2E нового dev.
