<?php

declare(strict_types=1);

namespace Light\Collections\Tests;

use Light\Collections\Generic\{DefaultEqualityComparer, Dictionary, GenericList, HashSet, Queue};
use PHPUnit\Framework\TestCase;

final class CollectionRegressionTest extends TestCase
{
    public function testQueueCanBeDrainedAndRefilledRepeatedly(): void
    {
        $queue = new Queue();
        for ($i = 0; $i < 100; ++$i) {
            $queue->enqueue($i);
            self::assertSame($i, $queue->peek());
            self::assertSame($i, $queue->dequeue());
            self::assertCount(0, $queue);
        }
        $queue->enqueue('last');
        self::assertSame(['last'], $queue->toArray());
        self::assertSame('last', $queue->dequeue());
    }

    public function testQueueCompactionDrainAndClearPreserveFifo(): void
    {
        $queue = new Queue(range(0, 199));
        for ($i = 0; $i < 150; ++$i) {
            self::assertSame($i, $queue->dequeue());
        }
        self::assertSame(range(150, 199), $queue->toArray());
        for ($i = 150; $i < 200; ++$i) {
            self::assertSame($i, $queue->dequeue());
        }
        $queue->clear();
        $queue->enqueue('again');
        self::assertSame('again', $queue->dequeue());
        $queue->enqueue('discard');
        $queue->clear();
        $queue->enqueue('fresh');
        self::assertSame('fresh', $queue->peek());
    }

    public function testTryPeekDistinguishesNullFromEmptyAndDoesNotConsumeOrInvalidate(): void
    {
        $queue = new Queue([null, false]);
        $iterator = $queue->getIterator();
        $iterator->rewind();
        self::assertTrue($queue->tryPeek($value));
        self::assertNull($value);
        self::assertCount(2, $queue);
        $iterator->next();
        self::assertFalse($iterator->current());
        self::assertNull($queue->dequeue());
        self::assertTrue($queue->tryPeek($value));
        self::assertFalse($value);
        self::assertFalse($queue->dequeue());
        $value = 'stale';
        self::assertFalse($queue->tryPeek($value));
        self::assertNull($value);
    }

    public function testSignedZeroHasConsistentHashForScalarsAndNestedArrays(): void
    {
        $comparer = new DefaultEqualityComparer();
        foreach ([[0.0, -0.0], [['n' => [0.0]], ['n' => [-0.0]]]] as [$positive, $negative]) {
            self::assertTrue($comparer->equals($positive, $negative));
            self::assertSame($comparer->hash($positive), $comparer->hash($negative));
            $set = new HashSet([$positive, $negative]);
            self::assertCount(1, $set);
            self::assertTrue($set->remove($negative));
            $dictionary = new Dictionary();
            $dictionary->add($positive, 'value');
            self::assertSame('value', $dictionary->get($negative));
            self::assertFalse($dictionary->tryAdd($negative, 'duplicate'));
        }
        self::assertCount(2, new HashSet([0, 0.0]));
    }

    public function testArrayHashIgnoresReferenceEncoding(): void
    {
        $value = 7;
        $referenced = [&$value, &$value];
        $plain = [7, 7];
        $comparer = new DefaultEqualityComparer();
        self::assertTrue($comparer->equals($referenced, $plain));
        self::assertSame($comparer->hash($referenced), $comparer->hash($plain));
        self::assertCount(1, new HashSet([$referenced, $plain]));
    }

    public function testArrayHashSupportsClosuresAndUsesObjectIdentityWithoutSerializing(): void
    {
        $closure = static fn () => 1;
        $object = new class {
            public int $value = 1;
            public function __serialize(): array { throw new \RuntimeException('Must not serialize'); }
        };
        $key = ['callback' => $closure, 'object' => $object];
        $set = new HashSet([$key, $key]);
        self::assertCount(1, $set);
        $dictionary = new Dictionary();
        $dictionary->add($key, 'found');
        $object->value = 2;
        self::assertSame('found', $dictionary->get($key));
        self::assertFalse($set->contains(['callback' => static fn () => 1, 'object' => $object]));
    }

    public function testArrayHashRetainsKeyOrderAndDistinguishesStrictValues(): void
    {
        $values = [['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1], ['a' => '1', 'b' => 2]];
        self::assertCount(3, new HashSet($values));
        $comparer = new DefaultEqualityComparer();
        self::assertSame($comparer->hash(NAN), $comparer->hash(NAN));
        self::assertCount(1, new HashSet([NAN, NAN]));
    }

    public function testDeepArrayHashCollisionsStillCheckEquality(): void
    {
        $first = 'a'; $second = 'b';
        for ($i = 0; $i < 40; ++$i) { $first = [$first]; $second = [$second]; }
        self::assertCount(2, new HashSet([$first, $second, $first]));
    }

    public function testAddRangeAcceptsIterablesSelfAndEmptyWithoutPreservingKeys(): void
    {
        $list = new GenericList([1]);
        $list->addRange(['a' => 2, 'b' => 3]);
        $list->addRange((static function () { yield 4; yield 5; })());
        self::assertSame([1, 2, 3, 4, 5], $list->toArray());
        $list->addRange($list);
        self::assertSame([1, 2, 3, 4, 5, 1, 2, 3, 4, 5], $list->toArray());
        $list->addRange([]);
        self::assertCount(10, $list);
    }

    public function testSortSupportsDefaultAndCustomComparersInPlace(): void
    {
        $list = new GenericList([3, 1, 2, 1]);
        $list->sort();
        self::assertSame([1, 1, 2, 3], $list->toArray());
        $list->sort(fn ($a, $b) => $b <=> $a);
        self::assertSame([3, 2, 1, 1], $list->toArray());
        $rows = new GenericList([['id' => 1, 'age' => 30], ['id' => 2, 'age' => 20], ['id' => 3, 'age' => 30]]);
        $rows->sort(fn ($a, $b) => $a['age'] <=> $b['age']);
        self::assertSame([2, 1, 3], array_column($rows->toArray(), 'id'));
    }

    public function testReverseChangesOriginalAndRetainsNullFalseAndDuplicates(): void
    {
        $list = new GenericList([null, false, 1, 1]);
        $list->reverse();
        self::assertSame([1, 1, false, null], $list->toArray());
        $list->reverse();
        self::assertSame([null, false, 1, 1], $list->toArray());
        foreach ([[], [1]] as $values) {
            $small = new GenericList($values);
            $small->sort(); $small->reverse();
            self::assertSame($values, $small->toArray());
        }
    }

    public function testRemoveAllReturnsCountPreservesOrderAndReindexes(): void
    {
        $list = new GenericList([1, 2, 3, 4, 5]);
        self::assertSame(2, $list->removeAll(fn ($n) => $n % 2 === 0));
        self::assertSame([1, 3, 5], $list->toArray());
        self::assertSame(3, $list->get(1));
        self::assertSame(0, $list->removeAll(fn ($n) => false));
        self::assertSame(3, $list->removeAll(fn ($n) => true));
        self::assertSame([], $list->toArray());
        self::assertSame(0, $list->removeAll(fn () => throw new \RuntimeException('Unexpected predicate')));
    }

    public function testRemoveAllCanSelectNullAndFalseIndependently(): void
    {
        $list = new GenericList([null, false, 0, '']);
        self::assertSame(1, $list->removeAll(fn ($value) => $value === null));
        self::assertSame([false, 0, ''], $list->toArray());
    }

    public function testMutatingListOperationsInvalidateActiveIterator(): void
    {
        foreach (['addRange', 'sort', 'reverse', 'removeAll'] as $method) {
            $list = new GenericList([3, 1, 2]);
            $iterator = $list->getIterator(); $iterator->rewind();
            match ($method) {
                'addRange' => $list->addRange([4]),
                'sort' => $list->sort(),
                'reverse' => $list->reverse(),
                'removeAll' => $list->removeAll(fn ($n) => $n === 1),
            };
            try {
                $iterator->next(); self::fail('Expected iterator invalidation');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('modified during iteration', $error->getMessage());
            }
        }
    }

    public function testThrowingCallbacksDoNotPartiallyReplaceList(): void
    {
        foreach (['sort', 'removeAll', 'addRange'] as $method) {
            $list = new GenericList([3, 1, 2]);
            try {
                match ($method) {
                    'sort' => $list->sort(fn () => throw new \RuntimeException('stop')),
                    'removeAll' => $list->removeAll(static function ($n) {
                        if ($n === 1) { throw new \RuntimeException('stop'); }
                        return true;
                    }),
                    'addRange' => $list->addRange((static function () { yield 4; throw new \RuntimeException('stop'); })()),
                };
                self::fail('Expected callback exception');
            } catch (\RuntimeException $error) {
                self::assertSame('stop', $error->getMessage());
            }
            self::assertSame([3, 1, 2], $list->toArray());
        }
    }
}
