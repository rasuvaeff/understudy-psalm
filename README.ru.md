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
- `rasuvaeff/understudy` ^0.1

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
| `$repo->find(Arg::int())` — настоящий вызов | по-прежнему ошибка |

Последняя строка и есть смысл. Матчер, доехавший до настоящего вызова, даёт
`MatcherLeaked` в рантайме, и плагин, который это скрыл бы, хуже отсутствия
плагина.

## Что он сообщает ещё

| Чем сообщается | О чём |
|---|---|
| `UnderstudyMisuse` | Матчер, вид которого параметр принять не может. Замыкание, которое ничего не специфицирует, или делает два вызова, или зовёт статический метод. Кардинальность, которую не удовлетворяет ни один прогон. Противоречащие друг другу аргументы `verify()`. |
| Родной `InvalidArgument` Psalm | `returns()` и `answers()` против специфицируемого метода. Плагин их не проверяет — он подставляет параметр шаблона билдера, а `WhenBuilder<TReturn>` уже объявляет `returns(TReturn ...)`. Дальше Psalm сам. |
| Родные диагностики Psalm по массивам и методам | `wire()`, форма которого читается из конструктора названного класса: неизвестный ключ — ошибка, каждый дубль типизирован своим контрактом. Динамический class-string не трогается. |

Всё, в чём плагин не уверен, остаётся молчаливым. Ложное обвинение здесь стоит
дороже пропущенного: то, что пропустит статический анализ, движок всё равно
поймает.

## API

| Тип | Назначение |
|---|---|
| `UnderstudyMisuse` | Issue, которым сообщается любая диагностика плагина. Один тип, а не по одному на правило: понимать пользователю нужно «understudy проверяет мои спецификации или нет», а необходимость глушить каждое правило отдельно была бы контрактом хуже, чем не глушить ничего. |
| `Plugin` | Точка входа, которую загружает Psalm; регистрируется через `extra.psalm.pluginClass`. Больше в пакете ничего публичного нет — хуки, которые он регистрирует, помечены `@internal`, и их решения — это поведение плагина, а не его API. |

## Разработка

```bash
make build          # validate + cs + psalm + unit + integration
make test-integration
```

Интеграционный сьют гоняет настоящие процессы Psalm по фикстурному проекту
дважды: с плагином и без. Прогон без плагина — то, что придаёт смысл прогону
с ним: плагин, который загрузился и ничего не делает, проходит позитивную
фикстуру ровно так же, как работающий.

## Лицензия

BSD-3-Clause.
