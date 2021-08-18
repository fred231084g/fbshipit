<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/f78lcdc8
 */
namespace Facebook\ShipIt;

<<\Oncalls('open_source')>>
final class FakeShipItRepo extends ShipItRepo {
  public function __construct(private ?ShipItChangeset $headChangeset = null) {
    $tempdir = new ShipItTempDir('FakeShipItRepo');
    parent::__construct(new ShipItDummyLock(), $tempdir->getPath());
  }

  <<__Override>>
  public async function genHeadChangeset(): Awaitable<?ShipItChangeset> {
    return $this->headChangeset;
  }

  <<__Override>>
  protected async function genSetBranch(string $_branch): Awaitable<bool> {
    return true;
  }

  <<__Override>>
  public async function genUpdateBranchTo(string $_base_rev): Awaitable<void> {}

  <<__Override>>
  public function clean(): void {}

  <<__Override>>
  public function pull(): void {}

  <<__Override>>
  public function pushLfs(
    string $_pull_endpoint,
    string $_push_endpoint,
  ): void {}

  <<__Override>>
  public function getOrigin(): string {
    return '';
  }

  <<__Override>>
  public static function getDiffsFromPatch(string $_patch): vec<ShipItDiff> {
    return vec[];
  }
}
