# 集合類別

[套件說明](../README.md) · [Enumerable](../../light-linq/docs/enumerable.md)

本套件獨立於 LINQ，不需要安裝 light-linq 即可使用。若應用程式另有安裝 light-linq，可透過 `Enumerable::from($collection)` 查詢集合。

類別位於 `Light\Collections\Generic`，實作可列舉集合介面，可直接交給 `Enumerable::from()`。泛型型別使用 PHPDoc 表達，不是 PHP 原生執行期泛型。

## GenericList：可變有序集合

```php
use Light\Collections\Generic\GenericList;

$list = new GenericList([1, 2]);
$list->add(3);
$list->insert(0, 0);
$list->insertRange(1, [10, 20]);
$slice = $list->getRange(1, 2)->toArray(); // [10, 20]
$list->removeRange(1, 2);
$list->set(0, 5);
$first = $list->get(0); // 5
```

PHP 保留 `list` 關鍵字，因此類別命名為 `GenericList`。也支援 array index 存取、`remove()`、`removeAt()`、`contains()`、`indexOf()`、`clear()`、`count()` 及 `toArray()`。索引由 0 開始。

### 批次新增、排序、反轉及條件移除

```php
$values = new GenericList([3, 1]);
$values->addRange([4, 2]);
$values->sort(); // 原地變成 [1, 2, 3, 4]
$values->reverse(); // 原地變成 [4, 3, 2, 1]
$removed = $values->removeAll(fn ($n) => $n % 2 === 0); // 2
$remaining = $values->toArray(); // [3, 1]
$values->sort(fn ($left, $right) => $right <=> $left); // 降序
```

`addRange()` 接受 iterable，可傳入自己，並略過來源 keys。`sort()` 預設用 PHP `<=>`；自訂比較 callback 回傳負整數、0、正整數。sort／reverse 都原地修改整個 list。`removeAll()` 回傳移除數量，保留剩餘順序並重設索引；沒有匹配時回傳 0。

批次操作完成修改後會令已開始的 iterator 失效；sort／reverse 每次呼叫都會令 iterator 失效。空 addRange 或沒有移除元素的 removeAll 不會令 iterator 失效。

## Dictionary：自訂 key 型別與比較

```php
use Light\Collections\Generic\Dictionary;

$scores = new Dictionary();
$scores->add('Ada', 10);
$scores['Ada'] = 15;
$added = $scores->tryAdd('Ada', 20); // false
$found = $scores->tryGetValue('Ada', $score); // true，$score 是 15
$names = $scores->keys(); // ['Ada']

foreach ($scores as $pair) {
    $key = $pair->key;
    $value = $pair->value;
}
```

`add()` 遇到重複 key 會拋出 `InvalidArgumentException`；`set()` 或 indexer 會新增或覆寫。`get()` 找不到 key 會拋出 `KeyNotFoundException`；可用 `getOrDefault($key, $default)` 或 `tryGetValue()`。

Dictionary 不接受 null key。列舉及 `toArray()` 回傳 `KeyValuePair`，不是 PHP associative array，因此能支援物件 key。另有 `containsKey()`、`containsValue()`、`values()`、`remove()`、`clear()` 及 `count()`。建構子可傳入 `EqualityComparer`。

## HashSet：唯一元素與原地集合運算

```php
use Light\Collections\Generic\HashSet;

$set = new HashSet([1, 2, 2]);
$inserted = $set->tryAdd(2); // false
$set->unionWith([2, 3]);
$set->exceptWith([1]);
$hasTwo = $set->contains(2); // true
$equal = $set->setEquals([2, 3]); // true
```

`unionWith()`、`intersectWith()`、`exceptWith()`、`symmetricExceptWith()` 會修改集合本身；Enumerable 的同類操作則產生查詢結果。另有子集／超集判斷、`overlaps()`、`remove()`、`clear()`、`count()` 及 `toArray()`。

預設相等比較區分型別，物件比較 identity。可在建構子的第二個參數傳入自訂 `EqualityComparer`；[分組指南](../../light-linq/docs/grouping-and-joins.md) 有完整 comparer 範例。

## Queue 與 Stack

```php
use Light\Collections\Generic\Queue;
use Light\Collections\Generic\Stack;

$queue = new Queue(['first', 'second']);
$queue->enqueue('third');
$next = $queue->dequeue(); // 'first'：先入先出

$stack = new Stack(['first', 'second']);
$stack->push('third');
$top = $stack->pop(); // 'third'：後入先出
```

兩者都有 `peek()`（只看不取）、`clear()`、`count()`、`toArray()`。空集合的取出及 peek 會拋出 `UnderflowException`；需要不拋例外的取出方式，可使用 `tryDequeue($value)` 或 `tryPop($value)`，以回傳 bool 判斷是否成功。


### Queue tryPeek

```php
$pending = new Queue([null, 'job']);
$found = $pending->tryPeek($nextValue); // true，$nextValue 是 null
$size = $pending->count(); // 2，不會取走元素
$pending->dequeue();
$found = $pending->tryPeek($nextValue); // true，$nextValue 是 'job'
```

空 queue 的 tryPeek 回傳 false 並把輸出設為 null；實際存有 null 元素時回傳 true。tryPeek 不修改集合，不會令 iterator 失效。Queue 取光元素後可以再次 enqueue 重用。

### Hash 相等規則

0.0 與 -0.0 比較相等並產生相同 hash；整數 0 仍與浮點數 0.0 不同。array hash 按鍵和值遞迴計算，巢狀物件採 identity，不呼叫 serialize，因此 Closure 亦可出現在 array key 內。相同 array 不會因 PHP reference 的內部表示不同而產生不同 hash。深層／遞迴 array 的 hashing 有深度限制，碰撞仍由 equals 判斷；這不代表已提供循環 array 的特殊相等比較。
