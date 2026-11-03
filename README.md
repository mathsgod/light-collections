# light-collections

Standalone .NET-style generic collections for PHP 8.3+. This package provides data structures and iteration contracts without depending on LINQ, XML, or a database provider.

## Development setup

```sh
composer install
composer test
```

Composer package: `mathsgod/php-collections`. Namespace: `Light\Collections\Generic`, mapped to `src/Generic/` through PSR-4. Generic types are documented using PHPDoc; they are not runtime-enforced PHP generics.

## Collections

```php
require 'vendor/autoload.php';

use Light\Collections\Generic\Dictionary;
use Light\Collections\Generic\GenericList;
use Light\Collections\Generic\HashSet;
use Light\Collections\Generic\Queue;
use Light\Collections\Generic\Stack;

$list = new GenericList([1, 2]);
$list->add(3);
$list->insert(0, 0);
$numbers = $list->toArray(); // [0, 1, 2, 3]

$dictionary = new Dictionary();
$dictionary->add('Ada', 10);
$dictionary['Ada'] = 15;
$score = $dictionary->get('Ada'); // 15

$set = new HashSet([1, 2, 2]);
$set->unionWith([2, 3]);
$uniqueCount = $set->count(); // 3

$queue = new Queue(['first', 'second']);
$next = $queue->dequeue(); // 'first'

$stack = new Stack(['first', 'second']);
$top = $stack->pop(); // 'second'
```

PHP reserves the keyword `list`, so the list class is named `GenericList`.
All collections support `foreach`, `count()`, and `toArray()`.
Dictionary iteration yields `KeyValuePair` objects, not PHP array key/value entries; this supports keys such as objects. Dictionary keys cannot be null. Duplicate keys passed to `add()` throw `InvalidArgumentException`; the indexer and `set()` insert or replace.

HashSet enforces uniqueness and supports union, intersection, difference, symmetric difference, and subset/superset checks. Stack is last-in-first-out; Queue is first-in-first-out.

## List bulk operations

```php
$values = new GenericList([3, 1]);
$values->addRange([4, 2]);
$values->sort(); // [1, 2, 3, 4]
$values->reverse(); // [4, 3, 2, 1]
$removed = $values->removeAll(fn ($n) => $n % 2 === 0); // 2
$remaining = $values->toArray(); // [3, 1]

$values->sort(fn ($left, $right) => $right <=> $left); // descending
```

These methods modify the list in place. `addRange()` accepts any iterable,
including the list itself, and appends its values without retaining source keys.
`sort()` uses PHP's `<=>` comparison by default; a custom comparer must return a
negative, zero, or positive integer. `reverse()` reverses the whole list.
`removeAll()` removes every matching item, preserves remaining order, reindexes
the list, and returns the removal count. New bulk operations invalidate active
iterators when they change the list; sort/reverse invalidate them on every call.
If a comparer, predicate, or input iterable throws, the operation does not apply
its pending replacement or appended values; user callback side effects are not
rolled back.

## Inspect a queue without consuming it

```php
$pending = new Queue([null, 'job']);
$found = $pending->tryPeek($value); // true; $value is null
$count = $pending->count(); // 2: nothing was removed
$pending->dequeue();
$found = $pending->tryPeek($value); // true; $value is 'job'
```

`tryPeek(&$value)` returns false and sets the output to null when empty.
It distinguishes an actual null element from an empty queue using its boolean
return value, and does not invalidate active iterators. A drained queue can be
refilled and reused.

## Interfaces and equality

```text
IReadOnlyCollection<T>
├── ICollection<T>
│   ├── IList<T> → GenericList<T>
│   └── ISet<T> → HashSet<T>
├── Stack<T>
└── Queue<T>

IReadOnlyCollection<KeyValuePair<TKey, TValue>>
└── IDictionary<TKey, TValue> → Dictionary<TKey, TValue>
```

The base collection interface extends PHP's `IteratorAggregate` and `Countable`.
`EqualityComparer`, `DefaultEqualityComparer`, `KeyValuePair`, and `KeyNotFoundException` are also part of this package.
Default equality distinguishes PHP types, compares arrays strictly, and uses object identity. Positive and negative floating-point zero share a hash; NaN values compare equal. Array hashes include ordered keys and values, use identity for nested objects, and do not invoke object serialization, so arrays containing closures are supported. Hash traversal is bounded for deep or recursive arrays, with collisions resolved by equality; this does not add cycle-aware array equality. Dictionary and HashSet accept custom `EqualityComparer` implementations. Equal values must have the same hash; hash collisions are checked with `equals()`.

See the [collection usage guide](docs/collections.md) for more examples.

## LINQ integration

Applications that also install `mathsgod/light-linq` can pass these collections to `Light\Linq\Enumerable::from($collection)` for filtering, projection, grouping, and aggregation.
The dependency direction is **light-linq → light-collections**; this package does not import or require light-linq. LINQ integration tests stay in light-linq, while standalone collection tests live here.
