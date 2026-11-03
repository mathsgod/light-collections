<?php

declare(strict_types=1);

namespace Light\Collections\Tests;

use Light\Collections\Generic\{DefaultEqualityComparer, Dictionary, EqualityComparer, GenericList, HashSet, KeyNotFoundException, Queue, Stack};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CollectionEdgeCasesTest extends TestCase
{
    private function collisionComparer(): EqualityComparer
    {
        return new class implements EqualityComparer {
            public function hash(mixed $value): string { return 'same-bucket'; }
            public function equals(mixed $a, mixed $b): bool { return strtolower($a) === strtolower($b); }
        };
    }

    public function testDictionaryCollisionsRetainIndependentKeysAndOriginalKeyWhenReplacing(): void
    {
        $dictionary = new Dictionary($this->collisionComparer());
        $dictionary->add('Ada', 1);
        $dictionary->add('Bob', 2);
        $dictionary->set('ADA', 3);
        $dictionary->set('Cat', 4);
        self::assertSame(['Ada', 'Bob', 'Cat'], $dictionary->keys());
        self::assertSame([3, 2, 4], $dictionary->values());
        self::assertSame(3, $dictionary['ada']);
        self::assertFalse($dictionary->tryAdd('BOB', 99));
        unset($dictionary['ADA']);
        self::assertFalse(isset($dictionary['Ada']));
        self::assertSame(2, $dictionary['bob']);
        self::assertFalse($dictionary->remove('missing'));
        self::assertTrue($dictionary->remove('BOB'));
        self::assertTrue($dictionary->remove('cat'));
        self::assertTrue($dictionary->isEmpty());
        self::assertSame([], $dictionary->toArray());
    }

    public function testDictionaryPreservesNullValuesAndDistinguishesAbsentKeys(): void
    {
        $dictionary = new Dictionary();
        $dictionary['null'] = null;
        $dictionary['false'] = false;
        $dictionary['number'] = 1;
        self::assertTrue(isset($dictionary['null']));
        self::assertTrue($dictionary->tryGetValue('null', $value));
        self::assertNull($value);
        self::assertNull($dictionary->getOrDefault('null', 'fallback'));
        self::assertTrue($dictionary->containsValue(null));
        self::assertTrue($dictionary->containsValue(false));
        self::assertFalse($dictionary->containsValue('1'));
        self::assertFalse($dictionary->tryGetValue('absent', $value));
        self::assertNull($value);
        self::assertSame('fallback', $dictionary->getOrDefault('absent', 'fallback'));
        self::assertFalse(isset($dictionary['absent']));
        $dictionary->clear();
        $dictionary->clear();
        self::assertCount(0, $dictionary);
        self::assertSame([], $dictionary->keys());
        self::assertSame([], $dictionary->values());
        self::assertFalse($dictionary->containsValue(null));
        $dictionary->add('again', 2);
        self::assertSame(2, $dictionary['again']);
    }

    public function testDictionaryRejectsDuplicateKeyWithoutReplacingValue(): void
    {
        $dictionary = new Dictionary($this->collisionComparer());
        $dictionary->add('Ada', 1);
        try {
            $dictionary->add('ADA', 2);
            self::fail('Expected duplicate key error');
        } catch (\InvalidArgumentException) {
            self::assertSame(1, $dictionary['Ada']);
            self::assertCount(1, $dictionary);
        }
    }

    public static function nullKeyOperations(): iterable
    {
        foreach (['add', 'set', 'get', 'getOrDefault', 'tryAdd', 'tryGetValue', 'containsKey', 'remove',
            'offsetExists', 'offsetGet', 'offsetSet', 'offsetUnset'] as $method) {
            yield $method => [$method];
        }
    }

    #[DataProvider('nullKeyOperations')]
    public function testDictionaryRejectsNullKeysAcrossAllEntryPoints(string $method): void
    {
        $dictionary = new Dictionary();
        $this->expectException(\InvalidArgumentException::class);
        match ($method) {
            'add', 'set', 'tryAdd', 'offsetSet' => $dictionary->$method(null, 1),
            'getOrDefault' => $dictionary->getOrDefault(null, 'fallback'),
            'tryGetValue' => $dictionary->tryGetValue(null, $value),
            default => $dictionary->$method(null),
        };
    }

    public function testDictionaryMissingIndexerThrows(): void
    {
        $this->expectException(KeyNotFoundException::class);
        $value = (new Dictionary())['absent'];
    }

    public function testHashSetCollisionsDoNotMergeUnequalValues(): void
    {
        $set = new HashSet(['Ada', 'Bob', 'ADA'], $this->collisionComparer());
        self::assertCount(2, $set);
        self::assertTrue($set->contains('ada'));
        self::assertTrue($set->remove('BOB'));
        self::assertFalse($set->remove('missing'));
        $set->add('Cat');
        $set->add('cat');
        self::assertTrue($set->setEquals(['ADA', 'CAT']));
        $set->unionWith(['Dan', 'dan']);
        self::assertTrue($set->isSupersetOf(['ADA', 'DAN']));
        $set->intersectWith(['ada', 'DAN']);
        self::assertTrue($set->setEquals(['Ada', 'Dan']));
        $set->exceptWith(['ADA']);
        self::assertTrue($set->setEquals(['dan']));
        $set->symmetricExceptWith(['DAN', 'Bob', 'BOB']);
        self::assertTrue($set->setEquals(['bob']));
        self::assertSame(['Bob'], iterator_to_array($set, false));
        $set->clear(); $set->clear();
        self::assertTrue($set->isEmpty());
        self::assertSame([], $set->toArray());
    }

    public function testHashSetSelfOperationsAndEmptyRelationships(): void
    {
        $set = new HashSet([1, 2]);
        $set->unionWith($set);
        $set->intersectWith($set);
        self::assertTrue($set->setEquals([1, 2]));
        self::assertTrue($set->isSubsetOf([1, 1, 2]));
        self::assertFalse($set->isSubsetOf([1]));
        self::assertFalse($set->isSupersetOf([1, 2, 3]));
        self::assertTrue($set->isSupersetOf([]));
        self::assertFalse($set->setEquals([1]));
        self::assertFalse($set->overlaps([]));
        $set->symmetricExceptWith($set);
        self::assertTrue($set->isEmpty());
        self::assertTrue($set->isSubsetOf([1]));
        self::assertFalse($set->isSupersetOf([1]));
        self::assertTrue($set->setEquals([]));
        $set->add(1);
        $set->exceptWith($set);
        self::assertTrue($set->isEmpty());
    }

    public function testListMissingAndStrictValuesAndArrayAccess(): void
    {
        $object = (object) ['id' => 1];
        $list = new GenericList([null, false, 1, $object]);
        self::assertTrue(isset($list[0]));
        self::assertFalse(isset($list[-1]));
        self::assertFalse(isset($list['0']));
        self::assertFalse(isset($list[4]));
        self::assertFalse($list->contains('1'));
        self::assertFalse($list->contains(clone $object));
        self::assertTrue($list->contains($object));
        self::assertSame(-1, $list->indexOf('missing'));
        self::assertFalse($list->remove('missing'));
        unset($list[1]);
        self::assertSame([null, 1, $object], $list->toArray());
        self::assertTrue($list->remove(null));
        self::assertSame([1, $object], iterator_to_array($list, false));
    }

    public static function invalidListOperations(): iterable
    {
        yield 'get negative' => ['get', [-1]];
        yield 'get past end' => ['get', [2]];
        yield 'set negative' => ['set', [-1, 3]];
        yield 'set past end' => ['set', [2, 3]];
        yield 'insert negative' => ['insert', [-1, 3]];
        yield 'insert past end' => ['insert', [3, 3]];
        yield 'insertRange negative' => ['insertRange', [-1, []]];
        yield 'insertRange past end' => ['insertRange', [3, []]];
        yield 'removeAt negative' => ['removeAt', [-1]];
        yield 'getRange negative count' => ['getRange', [0, -1]];
        yield 'getRange negative index' => ['getRange', [-1, 1]];
        yield 'getRange empty past end' => ['getRange', [3, 0]];
        yield 'removeRange negative index' => ['removeRange', [-1, 0]];
        yield 'removeRange negative count' => ['removeRange', [0, -1]];
        yield 'removeRange overflow count' => ['removeRange', [1, PHP_INT_MAX]];
        yield 'removeRange past end' => ['removeRange', [1, 2]];
        yield 'indexer get past end' => ['offsetGet', [2]];
        yield 'indexer set past end' => ['offsetSet', [2, 3]];
        yield 'indexer unset past end' => ['offsetUnset', [2]];
    }

    #[DataProvider('invalidListOperations')]
    public function testListRejectsInvalidIndicesAndRanges(string $method, array $arguments): void
    {
        $this->expectException(\OutOfRangeException::class);
        (new GenericList([1, 2]))->$method(...$arguments);
    }

    public static function invalidOffsetTypes(): iterable
    {
        foreach (['offsetGet', 'offsetSet', 'offsetUnset'] as $method) {
            foreach (['0', false, 0.5, []] as $offset) {
                yield [$method, $offset];
            }
        }
    }

    #[DataProvider('invalidOffsetTypes')]
    public function testListRejectsNonIntegerOffsets(string $method, mixed $offset): void
    {
        $list = new GenericList([1]);
        $this->expectException(\TypeError::class);
        $method === 'offsetSet' ? $list->offsetSet($offset, 2) : $list->$method($offset);
    }

    public function testListRangeCopiesAreIndependentAndAllowEmptyEndRanges(): void
    {
        $list = new GenericList([1, 2]);
        $copy = $list->getRange(0, 2);
        $copy->set(0, 99);
        self::assertSame([1, 2], $list->toArray());
        self::assertSame([], $list->getRange(2, 0)->toArray());
        $list->removeRange(2, 0);
        $list->insert(2, 3);
        $list->insertRange(3, [4]);
        self::assertSame([1, 2, 3, 4], $list->toArray());
        $list->clear(); $list->clear();
        self::assertTrue($list->isEmpty());
    }

    public function testConstructorsConsumeGeneratorsAndIgnoreTheirKeys(): void
    {
        foreach ([GenericList::class, Stack::class, Queue::class, HashSet::class] as $class) {
            $items = (static function () { yield 'same' => 1; yield 'same' => 2; })();
            $collection = new $class($items);
            self::assertSame($class === Stack::class ? [2, 1] : [1, 2], iterator_to_array($collection, false));
        }
    }

    public function testStackAndQueueNullElementsAreSuccessfulTryOperations(): void
    {
        $stack = new Stack([false, null]);
        self::assertTrue($stack->tryPop($value));
        self::assertNull($value);
        self::assertTrue($stack->tryPop($value));
        self::assertFalse($value);
        self::assertFalse($stack->tryPop($value));
        self::assertNull($value);
        $stack->clear(); $stack->push(1); $stack->clear();
        self::assertSame([], $stack->toArray());
        $queue = new Queue([null, false]);
        self::assertTrue($queue->tryDequeue($value));
        self::assertNull($value);
        self::assertTrue($queue->tryDequeue($value));
        self::assertFalse($value);
        self::assertFalse($queue->tryDequeue($value));
        self::assertNull($value);
    }

    public static function emptyPeeks(): iterable
    {
        yield 'stack' => [Stack::class];
        yield 'queue' => [Queue::class];
    }

    #[DataProvider('emptyPeeks')]
    public function testPeekOnEmptyCollectionThrows(string $class): void
    {
        $this->expectException(\UnderflowException::class);
        (new $class())->peek();
    }

    public static function mutations(): iterable
    {
        yield 'stack push' => [Stack::class, 'push', [2]];
        yield 'stack pop' => [Stack::class, 'pop', []];
        yield 'stack clear' => [Stack::class, 'clear', []];
        yield 'queue enqueue' => [Queue::class, 'enqueue', [2]];
        yield 'queue dequeue' => [Queue::class, 'dequeue', []];
        yield 'queue clear' => [Queue::class, 'clear', []];
        yield 'set add' => [HashSet::class, 'add', [2]];
        yield 'set remove' => [HashSet::class, 'remove', [1]];
        yield 'set clear' => [HashSet::class, 'clear', []];
        yield 'list set' => [GenericList::class, 'set', [0, 2]];
        yield 'list removeRange' => [GenericList::class, 'removeRange', [0, 1]];
        yield 'list clear' => [GenericList::class, 'clear', []];
    }

    #[DataProvider('mutations')]
    public function testMutationAfterLastYieldStillInvalidatesIterator(string $class, string $method, array $args): void
    {
        $collection = new $class([1]);
        $iterator = $collection->getIterator(); $iterator->rewind();
        $collection->$method(...$args);
        $this->expectException(\RuntimeException::class);
        $iterator->next();
    }

    public function testDictionaryReplacementRemovalAndClearInvalidateIterator(): void
    {
        foreach (['set', 'remove', 'clear'] as $method) {
            $dictionary = new Dictionary(); $dictionary->add('a', 1);
            $iterator = $dictionary->getIterator(); $iterator->rewind();
            match ($method) {
                'set' => $dictionary->set('a', 2),
                'remove' => $dictionary->remove('a'),
                'clear' => $dictionary->clear(),
            };
            try { $iterator->next(); self::fail('Expected iterator invalidation'); }
            catch (\RuntimeException) { self::assertTrue(true); }
        }
    }

    public function testNoOpMutationsDoNotInvalidateIterators(): void
    {
        $list = new GenericList([1, 2]);
        $iterator = $list->getIterator(); $iterator->rewind();
        $list->addRange([]); $list->insertRange(1, []); $list->removeRange(1, 0);
        self::assertSame(0, $list->removeAll(fn () => false));
        self::assertFalse($list->remove(99));
        $iterator->next(); self::assertSame(2, $iterator->current());
        $set = new HashSet([1, 2]);
        $iterator = $set->getIterator(); $iterator->rewind();
        self::assertFalse($set->tryAdd(1)); self::assertFalse($set->remove(99));
        $iterator->next(); self::assertSame(2, $iterator->current());
        $dictionary = new Dictionary(); $dictionary->add('a', 1); $dictionary->add('b', 2);
        $iterator = $dictionary->getIterator(); $iterator->rewind();
        self::assertFalse($dictionary->tryAdd('a', 99)); self::assertFalse($dictionary->remove('missing'));
        $iterator->next(); self::assertSame('b', $iterator->current()->key);
    }

    public function testDefaultComparerScalarTypesResourcesAndObjectIdentity(): void
    {
        $comparer = new DefaultEqualityComparer();
        $values = [null, false, true, 0, 0.0, '0', '', INF, -INF, NAN, [], [1]];
        $set = new HashSet([...$values, ...$values]);
        self::assertCount(count($values), $set);
        foreach ($values as $value) {
            self::assertTrue($set->contains($value));
        }
        self::assertFalse($comparer->equals(1, '1'));
        self::assertFalse($comparer->equals(1, 1.0));
        $one = (object) ['id' => 1]; $two = (object) ['id' => 1];
        self::assertFalse($comparer->equals($one, $two));
        $stream = fopen('php://memory', 'r+');
        try {
            $dictionary = new Dictionary(); $dictionary->add($stream, 'stream');
            self::assertSame('stream', $dictionary->get($stream));
            self::assertTrue($comparer->equals($stream, $stream));
        } finally { fclose($stream); }
    }
}
