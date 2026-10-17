# A2ice «АБСУРД» — единый локальный код и dev CI/CD

**Дата проверки:** 2026-10-17. **Актуальный статус:** локальный WordPress на OSP6 с NTFS junction проверен (frontend/CSS Ctrl+S/admin); WordPress 7.1.3 установлен на dev `absurd.a2ice.ru` (HTTPS login/dashboard проверены скриншотами); FTPS-доступ ограниченного пользователя и manual dry-run **проверены (PASS после повторной попытки)**; реальная публикация выполнена **только частично — тема загружена, плагин не подтверждён**; автоматический деплой **не включён**, независимый dev E2E пока не выполнен. Исторические шаги ниже могут описывать предыдущие состояния.

## Единственный источник исходного кода

- Репозиторий: https://github.com/a2ice/absurd
- Канонический Windows workspace (clean local `main`): `C:\OSP6\home\a2ice\projects\absurd-main`. Историческая папка `absurd` сохранена как резерв предыдущей ветки.
- Редактируемые Git-каталоги: `wp-content/themes/absurd-stage-v1` и `wp-content/plugins/absurdtheater-core`.
- Текущий установленный WordPress до миграции: `C:\OSP6\home\absurdtheater-wp.local\public`, URL `http://127.0.0.1:8767/`.
- Важно: в репозитории **папка `wp-content` находится в корне Git**, а не под `public`. Не переносить пути молча.
- **Обновлено 2026-10-17:** `origin/main` уже содержит тему, плагин, актуальный видео-hero и отключённый до настройки dev автодеплой (PR #1, #2, #4 merged). Рабочую локальную ветку `main` можно синхронизировать командой `git fetch origin; git switch main; git pull --ff-only origin main` **только с чистым рабочим деревом**. Сначала проверьте `git status --short`. После принятого коммита GitHub версия начнёт обслуживаться локально лишь когда установлены junction и этот же Git workspace является источником темы.

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
| Local | `http://absurdtheater-wp.local/` | WordPress 7.1.3, OSP6 PHP 8.4 без Nginx для проекта, оба NTFS junction PASS, Ctrl+S→CSS PASS, wp-admin PASS |
| Dev | `https://absurd.a2ice.ru/` | WordPress 7.1.3 с отдельной БД, HTTPS login/dashboard PASS; **тема выгружена FTPS по успешному логу run #39047942016, плагин НЕ выгружен полностью**, активация и независимый E2E не подтверждены |
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

**Внешняя read-only проверка GitHub Actions 2026-10-17, run #39019131873:** `https://absurd.a2ice.ru/` HTTPS 200; `/wp-login.php` HTTP 404; `/wp-json/` HTTP 404; признаков стандартной установки WordPress не найдено. WordPress на dev **не подтверждён, вероятнее отсутствует в корне домена** (закрытую/нестандартную установку исключить нельзя). Нужен скриншот ISPmanager с *доменом, document root, PHP* и областью БД без секретов.

## GitHub Actions: main → dev

Workflow: `.github/workflows/deploy-dev.yml` (находится в `main`; изменения путей на ограниченный FTP root — в отдельном PR, до merge не работают).

- На PR и push в `main`: проверки безопасности tracked files, PHP lint и JS syntax.
- На каждый push в `main`: **автоматический FTPS deployment** только при `DEV_WORDPRESS_READY=true` и `DEV_DEPLOY_ENABLED=true`.
- При ручном запуске `workflow_dispatch` можно делать dry-run **до включения автоматического deployment**, но при `DEV_WORDPRESS_READY=true`.
- Передача только **двух** каталогов исходного кода темы и плагина. **Никаких WP core, БД, конфигов, uploads или переключения URL/сертификатов**.
- State-файлы FTPS изолированы по теме и плагину; `dangerous-clean-slate=false`. Это уменьшает риск очистки иных серверных каталогов, но не заменяет backup.
- GitHub `development` environment; для него можно настроить ограничение ветки `main`. Не включать ручное approve environment, если требуется полностью автоматическая выкладка.

### Ограничение FTP-пользователя под WordPress dev

В ISPmanager создавать **нового**, отдельного от прочих сайтов пользователя с Home Directory `/www/absurd.a2ice.ru/wp-content` относительно домашнего каталога владельца (`/var/www/user180027/data`). Это ограничивает FTP-доступ только этой веткой WordPress, не даёт FTP-аккаунту доступа к `wp-config.php` в корне сайта. При этом в FTP root остаются `themes/`, `plugins/` и `uploads/`; поэтому использовать отдельные credentials и соблюдать запрет деплоя за пределы двух явных target каталогов. Перед первым деплоем **проверить реальный FTP virtual root после входа**, не полагаться на системный путь.

После merge PR GitHub Action проверяет **строго** относительные к этому FTP-root пути `/themes/absurd-stage-v1/` и `/plugins/absurdtheater-core/`; учётную запись с доступом ко всему хостингу не использовать. Если ISPmanager не позволяет ограничение до `wp-content`, не расширять доступ молча: сначала согласовать альтернативный безопасный вариант.

В репозитории **Settings → Secrets and variables → Actions** указать следующие **Secrets** (значения не присылать в чат):

| Secret | Описание |
|---|---|
| `DEV_FTP_HOST` | Подтверждённый FTPS hostname CISHOST |
| `DEV_FTP_USER` | Выделенный FTP-пользователь, домашний каталог `/www/absurd.a2ice.ru/wp-content` относительно `/var/www/user180027/data`, не корень всех сайтов |
| `DEV_FTP_PASSWORD` | Пароль FTPS |
| `DEV_THEME_FTP_DIR` | После принятия PR об ограниченном FTP root: **ровно** `/themes/absurd-stage-v1/` |
| `DEV_PLUGIN_FTP_DIR` | После принятия PR об ограниченном FTP root: **ровно** `/plugins/absurdtheater-core/` |

Repository **Variables**:

| Variable | Старт | Когда изменить |
|---|---|---|
| `DEV_WORDPRESS_READY` | `false` | `true` после установки и проверки WordPress + резервной копии на dev |
| `DEV_DEPLOY_ENABLED` | `false` | `true` только после успешного ручного dry-run, первого реального тестового deployment и E2E |

Если workflow размещает код не туда, **не менять пути наугад**: проверить FTP virtual root относительно панели ISPmanager. Не раскрывать пароли/секреты в логах.

### Результат первой проверки FTPS — 17.10.2026

- Новый CISHOST FTP пользователь `user180027_absurd_deploy` ограничен домашним каталогом `/var/www/user180027/data/www/absurd.a2ice.ru/wp-content`. FileZilla на Windows показала авторизованную FTPS-сессию и `/` с `languages/`, `plugins/`, `themes/`, `upgrade/`, `uploads/`.
- GitHub Repository Secrets: `DEV_FTP_HOST`, `DEV_FTP_USER`, `DEV_FTP_PASSWORD`, `DEV_THEME_FTP_DIR`, `DEV_PLUGIN_FTP_DIR` созданы (только имена подтверждены скриншотом). Variables: `DEV_WORDPRESS_READY=true`, **`DEV_DEPLOY_ENABLED=false`**.
- Первый ручной workflow-dispatch `main`, `dry_run=true`: [run #39047259724](https://github.com/a2ice/absurd/actions/runs/39047259724). Валидация PASS; первый FTPS connect получил `ETIMEDOUT` IPv4 TCP/21, `ENETUNREACH` IPv6 — до TLS/аутентификации.
- Однократный диагностический GitHub Actions [run #39047497249](https://github.com/a2ice/absurd/actions/runs/39047497249): с другого GitHub-hosted runner TCP/21 **доступен**, 443 **доступен**, 22 **не отвечает**. Диагностический [PR #8](https://github.com/a2ice/absurd/pull/8) закрыт **без merge**, основной workflow не изменялся.
- Повторно запущен только упавший job исходного run #39047259724. Оба FTPS dry-run шага **PASS**: тема — 19 файлов/папок (4.59 MB), плагин — 3 файла (34.6 kB), удалений — 0. Режим **dry_run=true**, файловая публикация **НЕ производилась**. Возможна нестабильность TCP/21 между исходящими IP GitHub-hosted runners и CISHOST, одиночный PASS не гарантирует стабильность последующих вызовов.
- **Исторический этап (выполнен частично):** первый ручной `workflow_dispatch` по `main`, `dry_run=false` проведён, но итог **FAIL** из-за нестабильного FTPS; см. раздел ниже. `DEV_DEPLOY_ENABLED=false`. Не раскрывать значения Secrets и не заменять FTPS протокол незашифрованным FTP ради обхода ошибки.

### Первый настоящий deploy — частичный FAIL; стабилизация FTPS (17.10.2026)

- [Workflow #39047942016](https://github.com/a2ice/absurd/actions/runs/39047942016), `main`, `dry_run=false`, `validate=PASS`: начальная попытка прервалась ещё до FTP/TLS auth с `ETIMEDOUT 31.41.40.19:21`, IPv6 `ENETUNREACH`.
- Ручной `rerun failed jobs`: **тема успешно передана** (19 объектов, около 4.59 MB, 0 удалений; в логе `Sync complete` и сохранение состояния), однако шаг передачи плагина оборвался с `ECONNRESET (data socket)` на `absurdtheater-core.php`. Не считать плагин опубликованным и не включать его в WordPress до фактической проверки.
- Ещё один ограниченный повтор завершился `ETIMEDOUT` FTP TCP/21 на шаге темы; плагин не выполнялся. **Итоговый run FAIL; полного первого деплоя нет**. Не запускать бесконечные ручные повторы.
- Для устойчивости подготовлен/принят [PR #10](https://github.com/a2ice/absurd/pull/10), commit `8f86d84d8f4e16d1c0e8fbaf41fe6b8033df1658`: GitHub-hosted FTPS использует IPv4-first DNS и до двух последовательных попыток на каждый шаг с backoff 15с; второй сбой **завершает job с ошибкой**, строгий FTPS TLS и защита путей сохранены. CI [run #39048617799](https://github.com/a2ice/absurd/actions/runs/39048617799) PASS на PR (это **только syntax/lint**, не новое доказательство передачи файлов).
- Следующий шаг: **один** manual workflow-dispatch на актуальной `main`, `dry_run=false` и `DEV_DEPLOY_ENABLED=false`. Если транспортные ошибки повторяются — открыть обращение CISHOST по журналам `ETIMEDOUT TCP/21` и `ECONNRESET FTPS data socket` от GitHub-hosted Azure runner; запросить проверку FTP passive ranges и сетевого ограничения без отключения TLS или расширения FTP-доступа. После полного PASS — проверить через ISPmanager точные файлы на FTP, активировать тему/плагин, независимый E2E; только затем авто-push.

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

- Dev WordPress **установлен** и открывается на HTTPS (доказательства: скриншоты владельца из браузера, wp-login и wp-admin); полные smoke / REST / HTTP→HTTPS / staging authentication ещё не пройдены.
- В ISPmanager **создан** отдельный FTP аккаунт `user180027_absurd_deploy` с домашней директорией `/var/www/user180027/data/www/absurd.a2ice.ru/wp-content` (скриншот владельца, 17.10.2026). **Подключение FTPS, FTP virtual root/права записи ещё не проверены; GitHub Secrets пока не заданы.**
- Workflow **уже слит в main**, GitHub Actions validate PASS, deploy job skipped из-за выключенных flags. Локальный junction на Dmitanium был успешно применён; **серверного деплоя всё ещё нет**.
- `origin/main` **уже включает** собственную тему, плагин и CI workflow (merge PR #2, #4). Локальный worktree `absurd-main` синхронизирован с `origin/main`, статус чистый по предоставленному выводу Git.
- Не проведён независимый E2E нового dev.
