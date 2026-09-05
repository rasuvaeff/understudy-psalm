# rasuvaeff/understudy-psalm

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/understudy-psalm/v)](https://packagist.org/packages/rasuvaeff/understudy-psalm)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/understudy-psalm/downloads)](https://packagist.org/packages/rasuvaeff/understudy-psalm)
[![Build](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/understudy-psalm/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/understudy-psalm/php)](https://packagist.org/packages/rasuvaeff/understudy-psalm)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[English version](README.md)

Psalm-плагин для [understudy](https://github.com/rasuvaeff/understudy).

> Работаете с AI-ассистентом? Дайте ему [llms.txt](llms.txt).

## Требования

- PHP 8.3 - 8.5
- `vimeo/psalm` ^6.16
- `rasuvaeff/understudy` ^0.8

## Установка

```bash
composer require --dev rasuvaeff/understudy-psalm
vendor/bin/psalm-plugin enable rasuvaeff/understudy-psalm
```

## Что он делает

understudy задаёт вызов, делая его внутри замыкания:

```php
when(fn () => $repo->find(Arg::int(min: 1)))->returns($book);
```

`Arg::int()` объявлен как `mixed` — матчер обязан проходить туда, где
контракт объявляет что угодно. Psalm сообщает об этом как об ошибке типа
аргумента: в общем случае справедливо, здесь — нет.

Плагин снимает это сообщение внутри setup-замыкания и только там:

| Где | Psalm |
|---|---|
| `when(fn () => $repo->find(Arg::int()))` | молчит |
| `Understudy::expect(fn () => $repo->find(Arg::any()))` | молчит |
| `Understudy::calls(fn () => $repo->find(Arg::any()))` | молчит |
| `Understudy::verifySequence(fn () => ..., fn () => ...)` | молчит |
| `expectSequence(fn () => ..., fn () => ...)` | молчит |
| `$repo->find(Arg::int())` — настоящий вызов | по-прежнему ошибка |

Последняя строка и есть смысл. Матчер, доехавший до настоящего вызова, даёт
`MatcherLeaked` в рантайме, и плагин, который это скрыл бы, хуже отсутствия
плагина.

**Последнюю строку сообщает Psalm, а не этот плагин, и для неё нужен
`errorLevel="1"`.** Плагин подавляет; сообщение, которое он оставляет, —
собственный `MixedArgument` Psalm'а, а он существует только на уровне 1. На
уровнях 2 и выше утёкший матчер здесь не даёт ничего — ловит его рантаймовый
`MatcherLeaked`, одним прогоном позже. У PHPStan-плагина есть своё правило
(`understudy.matcherLeak`), и оно сообщает на любом уровне; здесь его
сознательно нет: правило, достаточно строгое, чтобы поймать утечку текстуально,
заодно ошибается на матчере, который приходит в спецификацию через переменную,
свойство или хелпер, — а ложное обвинение здесь дороже пропущенного.

Учитывается каждый глагол, принимающий call-замыкание, в обоих написаниях:
свободные функции `when()`, `expect()`, `expectSequence()` и `verify()`, те же
имена на `Understudy::` и читатели, существующие только там, — `calls()`,
`lastCall()` и `verifySequence()`. Читается каждое замыкание протокола:
матчер в третьем шаге обрабатывается ровно так же, как матчер в `when()`.

Две идиомы understudy 0.4 покрыты так же:

- **`Arg::rest()`** легитимно передаёт меньше аргументов, чем объявляет
  контракт, — `when(fn () => $storage->recordOutcome('svc', Arg::rest()))` —
  поэтому `TooFewArguments` замолкает на этом вызове, но только когда вызов
  стоит внутри спецификации *и* его последний написанный аргумент —
  `Arg::rest()`. Настоящий вызов с `Arg::rest()` в конце — недовызов
  по-настоящему (движок отвечает `ArgumentCountError`) и сохраняет оба
  репорта.
- **`$captor->capture()`** из `Arg::captor()` — матчер в одежде вызова
  метода: его репорт о типе аргумента замолкает внутри спецификации, он не
  считается против «ровно один вызов на замыкание», а capture, утёкший в
  настоящий вызов, репорт сохраняет.

На чём именно плагин действует, решает резолвер, а не написанное имя: чужой
`Acme\Console\Arg::int()` внутри спецификации сохраняет свои диагностики, а наш
под алиасом теряет их под любым именем. Это верно и для `capture()`: к моменту,
когда Psalm спрашивает тип возврата метода, получатель им уже разрешён — чужой
`capture()` без аргументов внутри спецификации остаётся чужим методом и
сохраняет всё, что Psalm о нём говорит.

## Что он сообщает ещё

| Чем сообщается | О чём |
|---|---|
| `UnderstudyMisuse` | Матчер, вид которого параметр принять не может. Замыкание, которое ничего не специфицирует, или делает два вызова, или зовёт статический метод. Кардинальность, которую не удовлетворяет ни один прогон. Противоречащие друг другу аргументы `verify()`. |
| Родной `InvalidArgument` Psalm | `returns()` и `answers()` против специфицируемого метода. Плагин их не проверяет — он подставляет параметр шаблона билдера, а `WhenBuilder<TReturn>` уже объявляет `returns(TReturn ...)`. Дальше Psalm сам. |
| Родные диагностики Psalm по массивам и методам | `wire()`, форма которого читается из конструктора названного класса: неизвестный ключ — ошибка, каждый дубль типизирован своим контрактом, а параметр-пересечение сохраняет пересечение — ядро строит на него один дубль. Класс, который ядро связать отказывается, и динамический class-string не трогаются. |

Всё, в чём плагин не уверен, остаётся молчаливым. Ложное обвинение здесь стоит
дороже пропущенного: то, что пропустит статический анализ, движок всё равно
поймает.

Поэтому уточнённый тип параметра не даёт ничего: `Arg::string()` против
`non-empty-string`, `class-string` или литерального объединения — пара, которую
аргумент удовлетворяет, как и `Arg::int()` против `positive-int` или
`int<1, 10>`: уточнение остаётся тем же видом, который уточняет. Правило
говорит, только когда КАЖДЫЙ член типа параметра — определённое «нет», и
отказывается судить тип, значения которого не может перечислить.

## API

| Тип | Назначение |
|---|---|
| `UnderstudyMisuse` | Issue, которым сообщается любая диагностика плагина. Один тип, а не по одному на правило: понимать пользователю нужно «understudy проверяет мои спецификации или нет», а необходимость глушить каждое правило отдельно была бы контрактом хуже, чем не глушить ничего. |
| `Plugin` | Точка входа, которую загружает Psalm; регистрируется через `extra.psalm.pluginClass`. Больше в пакете ничего публичного нет — хуки, которые он регистрирует, помечены `@internal`, и их решения — это поведение плагина, а не его API. |

Оба стабильны: это те два имени, которые потребитель выписывает у себя — одно
в `psalm.xml`, другое в `issueHandlers`, — и переименование любого молча
сломало бы чьё-то подавление. **Формулировка** диагностики стабильной не
является, патч-релиз вправе её переписать: ассертить нужно тип issue, а не
предложение.

## Безопасность

Плагин работает внутри Psalm, читает исходники и рефлексию, только сообщает.
Он не исполняет код анализируемого проекта и ничего не пишет.

## Примеры

См. [examples/README.md](examples/README.md). Исполняемая демонстрация — набор
фикстурных проектов в `tests/Integration/Fixtures`: каждый прогоняется
настоящим процессом Psalm как часть `composer build` — включая контрольный
прогон с выключенным плагином, который и отличает работающий плагин от того,
что загрузился и ничего не делает.

## Семейство understudy

| Пакет | Что это |
|---|---|
| [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) | Движок: дубли, матчеры, ожидания, верификация. |
| [rasuvaeff/understudy-testo](https://github.com/rasuvaeff/understudy-testo) | Testo-адаптер — верификация и сброс вокруг каждого теста. |
| [rasuvaeff/understudy-phpunit](https://github.com/rasuvaeff/understudy-phpunit) | Адаптер для PHPUnit и Pest — то же самое, через трейт. |
| **rasuvaeff/understudy-psalm** *(этот пакет)* | Psalm-плагин — спецификации с матчерами и диагностики ошибок. |
| [rasuvaeff/understudy-phpstan](https://github.com/rasuvaeff/understudy-phpstan) | PHPStan-расширение — то же самое для PHPStan, плюс свои правила. |

## Разработка

```bash
make build          # validate, normalize, require-checker, cs, psalm, unit, integration
make test-integration
```

Интеграционный сьют гоняет настоящие процессы Psalm по фикстурному проекту
дважды: с плагином и без. Прогон без плагина — то, что придаёт смысл прогону
с ним: плагин, который загрузился и ничего не делает, проходит позитивную
фикстуру ровно так же, как работающий.

## Лицензия

BSD-3-Clause.
