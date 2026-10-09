# Cadastral Valuation Fund — клиент НСПД

PHP 8.2+ библиотека **rosreestr/cadastral** для получения истории кадастровой стоимости из Фонда данных государственной кадастровой оценки НСПД. Исходная версия (2024) обращалась к устаревшей HTML-форме Росреестра. Новая версия получает данные из JSON API НСПД, сохраняя публичный метод searchByCadastral().

**Важно:** это сторонний клиент публичного сервиса, а не официальный поддерживаемый API. Адреса endpoint'ов и структура данных могут измениться. Код различает отсутствие записей, некорректный ответ и сетевую ошибку.

## Использование

Установите библиотеку через Composer и VCS-репозиторий:
https://github.com/4erk/cadastral-valuation-fund

Пример PHP:

    require 'vendor/autoload.php';

    use Rosreestr\Cadastral\Client;

    $client = new Client();
    $history = $client->searchByCadastral('78:11:0006113:5691');

    if ($history !== null) {
        echo 'Записей: ' . $history->getTotalCount() . PHP_EOL;

        foreach ($history->getItems() as $item) {
            echo ($item->determinationDate ?? 'дата неизвестна')
                . ': ' . $item->cadastralValue->decimal . ' руб.' . PHP_EOL;
        }

        $current = $history->getCurrent();
        echo 'Последняя оценка: ' . $current->cadastralValue->decimal . PHP_EOL;
    }

    $chart = $client->getHistoryDiagram('78:11:0006113:5691');
    $details = $client->getCurrentDetails('78:11:0006113:5691', 'room');

Метод searchByCadastral() возвращает Response или null, только если записей нет. Ошибки сети, некорректный JSON и неожиданный формат ответа вызывают исключения — их нельзя ошибочно считать отсутствием стоимости.

## Что возвращает библиотека

Каждая запись Item содержит:

| Поле | Описание |
|---|---|
| cadNumber | Кадастровый номер |
| costRecordId | Уникальный ID записи стоимости |
| cadastralValue.value | Сумма в рублях (float, совместимость со старым API) |
| cadastralValue.decimal | Десятичная строка без потери записи исходной суммы |
| determinationDate | Дата определения кадастровой стоимости |
| applicationDate | Дата начала применения |
| infoEntryDate | Дата регистрации данных в ЕГРН |
| determinationBasis | Основание определения |
| determinationCode | Код основания |
| documentCode | Код вида документа |
| determinationName.text | Тип документа |
| approvalDetails | Реквизиты, если присутствуют |
| files | Метаданные файлов, предоставленные НСПД |
| references | ID для получения карточки связанной процедуры |
| raw | Полный исходный объект строки НСПД |

Отсутствующие даты и документы имеют значение null. Две записи с одинаковыми датой и суммой не удаляются: у них могут быть разные costRecordId.

Response предоставляет getItems(), getTotalCount(), getCadastralNumber(), getCurrent(), а также JSON-представление. getCurrent() выбирает последнюю применимую запись из истории. Сведения для графика приходят из другого API и могут отличаться количеством точек.

Для контрольного объекта 78:11:0006113:5691 (состояние API на октябрь 2026) история включала шесть записей: 12 332 049,99; 14 723 539,81; 6 193 600,42; 23 805 438,71 (две разные записи); 23 690 357,06 ₽.

Дополнительные методы:

- getHistoryDiagram(number) — исходные точки графика: applicationDate, determinationDate, cost, displayDiagram.
- getCurrentDetails(number, kind) — актуальные данные, включая стоимость, вид, адрес, площадь. Например, kind=room или kind=land.
- getRecordDetails(item) — расширенная карточка оценки, когда имеется ссылка на процедуру.

## Архитектура и источники

- src/Client.php — HTTP-транспорт, валидация и тайм-ауты; можно внедрить Guzzle ClientInterface.
- src/Valuation/HistoryParser.php — чистый JSON-парсер для исторических записей.
- src/Valuation/Response.php и Item/Value/Link — объектная модель.
- src/Valuation/Parser.php — старый HTML-парсер для совместимости; основным клиентом больше не вызывается.
- tests/Fixtures — зафиксированные ответы НСПД для воспроизводимых тестов.
- tools/live-probe.php — ручной тест API с реальным подключением.

Используемые относительные пути:

    GET /api/data-fund/v3/cadastral-value-history-table?cadNumber=...
    GET /api/data-fund/v2/cadastral-history-diagram?cadNumber=...
    GET /api/data-fund/v1/cadastral-now?cadNumber=...&kind=room
    GET /api/data-fund/v3/cadastral-value-card?... (когда доступна карточка)

База фиксирована: https://nspd.gov.ru. Передать произвольный URL через кадастровый номер невозможно. Ограничены время запросов и размер JSON. Настройки HTTP-клиента можно передать третьим аргументом конструктора.

В текущей конфигурации библиотеки проверка TLS отключена по решению пользователя; это означает, что она не выполняет криптографическую проверку подлинности сервера.

## Тесты и dev-flow

Из корня проекта:

    dev status
    dev test

CI на GitHub включает Composer validate, аудит production-зависимостей, PHP lint и PHPUnit. PHPUnit работает с Guzzle MockHandler и сохранёнными JSON-фикстурами, без внешней сети.

Для ручной проверки через сервер GOSKADASTR используется SSH SOCKS-туннель с 4ERK-PC. Не публикуйте открытый прокси и не записывайте секреты в репозиторий:

    ssh -N -D 127.0.0.1:18749 goskadastr-prod
    export CADASTRAL_PROXY=socks5h://127.0.0.1:18749
    php tools/live-probe.php 78:11:0006113:5691 room

Установленный контейнер PHP может использовать свой адрес для доступа к локальному туннелю; это настраивается при запуске Docker. Другие настройки Guzzle поддерживаются конструктором.

## Репозитории и версии

- **Оригинал**: https://github.com/4erk/cadastral-valuation-fund (ветка main).
- **Версионированный upstream**: https://github.com/goskadastr/cadastral-valuation-fund (ветка main).
- **Старый GitLab**: remote legacy-gitlab, сохранённый без новых релизов.
- Обычный поток: GitHub issue → dev start → dev test → dev pr → CI → dev merge → dev release.
- SemVer определяется Git-тегом vX.Y.Z. Фиксированного поля version в composer.json нет.
- dev release публикует версию сначала в 4erk, затем в goskadastr. Обновление библиотек сайта GOSKADASTR происходит отдельно, после проверки совместимости.
