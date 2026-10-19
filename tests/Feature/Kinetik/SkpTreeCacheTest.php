<?php

use App\Kinetik\Data\KipRkData;
use App\Kinetik\Data\KipSkpTree;
use Illuminate\Support\Facades\Cache;

it('returns a cached SKP tree as a KipSkpTree, not an incomplete class', function () {
    // The array store serialises like the file store, so it shows the same problem.
    config(['cache.default' => 'array', 'cache.stores.array.serialize' => true]);
    Cache::forgetDriver('array');

    $rk = new ReflectionClass(KipRkData::class);
    $tree = new KipSkpTree('1', null, collect([$rk->newInstanceWithoutConstructor()]), collect(['9' => [['iki' => 'x']]]));

    Cache::remember('kinetik:skp-tree:test', 60, fn () => $tree);
    $hit = Cache::remember('kinetik:skp-tree:test', 60, fn () => null);

    expect($hit)->toBeInstanceOf(KipSkpTree::class)->and($hit->rks->first())->toBeInstanceOf(KipRkData::class);
});
