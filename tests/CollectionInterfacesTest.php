<?php

declare(strict_types=1);

namespace Light\Collections\Tests;

use Light\Collections\Generic\Dictionary;
use Light\Collections\Generic\GenericList;
use Light\Collections\Generic\HashSet;
use Light\Collections\Generic\ICollection;
use Light\Collections\Generic\IDictionary;
use Light\Collections\Generic\IList;
use Light\Collections\Generic\IReadOnlyCollection;
use Light\Collections\Generic\ISet;
use Light\Collections\Generic\Queue;
use Light\Collections\Generic\Stack;
use PHPUnit\Framework\TestCase;

final class CollectionInterfacesTest extends TestCase
{
    public function testCollectionHierarchy(): void
    {
        $list = new GenericList();
        $set = new HashSet();
        $dictionary = new Dictionary();

        self::assertInstanceOf(IReadOnlyCollection::class, $list);
        self::assertInstanceOf(ICollection::class, $list);
        self::assertInstanceOf(IList::class, $list);
        self::assertInstanceOf(ISet::class, $set);
        self::assertInstanceOf(IDictionary::class, $dictionary);
        self::assertInstanceOf(IReadOnlyCollection::class, new Stack());
        self::assertInstanceOf(IReadOnlyCollection::class, new Queue());
    }

    public function testHashSetEnforcesUniquenessAndSupportsNull(): void
    {
        /** @var HashSet<int|null> $set */
        $set = new HashSet([1, 1, 2, null, null]);

        self::assertCount(3, $set);
        self::assertTrue($set->contains(1));
        self::assertTrue($set->contains(null));
        self::assertFalse($set->tryAdd(2));
        self::assertTrue($set->tryAdd(3));
        self::assertTrue($set->remove(1));
        self::assertFalse($set->contains(1));
    }

    public function testHashSetMutationOperations(): void
    {
        $union = new HashSet([1, 2]);
        $union->unionWith([2, 3]);
        self::assertTrue($union->setEquals([1, 2, 3]));

        $intersection = new HashSet([1, 2, 3]);
        $intersection->intersectWith([2, 3, 4]);
        self::assertTrue($intersection->setEquals([2, 3]));

        $difference = new HashSet([1, 2, 3]);
        $difference->exceptWith([2, 4]);
        self::assertTrue($difference->setEquals([1, 3]));

        $difference->exceptWith($difference);
        self::assertTrue($difference->isEmpty());

        $symmetric = new HashSet([1, 2, 3]);
        $symmetric->symmetricExceptWith([3, 4]);
        self::assertTrue($symmetric->setEquals([1, 2, 4]));
    }

    public function testHashSetRelationships(): void
    {
        $set = new HashSet([1, 2]);

        self::assertTrue($set->isSubsetOf([1, 2, 3]));
        self::assertTrue($set->isSupersetOf([1]));
        self::assertTrue($set->overlaps([2, 9]));
        self::assertFalse($set->overlaps([8, 9]));
        self::assertFalse($set->setEquals([1, 2, 3]));
    }


    public function testListAndDictionaryExposeCollectionConveniences(): void
    {
        $list = new GenericList();
        self::assertTrue($list->isEmpty());
        $list->add('value');
        self::assertFalse($list->isEmpty());

        /** @var Dictionary<string, int> $dictionary */
        $dictionary = new Dictionary();
        self::assertTrue($dictionary->isEmpty());
        self::assertSame(99, $dictionary->getOrDefault('missing', 99));
        $dictionary->add('answer', 42);
        self::assertSame(42, $dictionary->getOrDefault('answer', 99));
        self::assertCount(1, $dictionary->toArray());
    }
}
