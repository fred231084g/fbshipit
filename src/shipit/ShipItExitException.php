<?hh
// (c) Facebook, Inc. and its affiliates. Confidential and proprietary.

namespace Facebook\ShipIt;

final class ShipItExitException extends \Exception {
  public function __construct(public int $exitCode) {
    parent::__construct("ShipIt exited with code: $exitCode");
  }
}
