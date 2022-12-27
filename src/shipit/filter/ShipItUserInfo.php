<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/v5jxhmm9
 */
namespace Facebook\ShipIt;

interface ShipItUserInfo {
  // eg convert a local unix account name to a github account name
  public static function genDestinationUserFromLocalUser(
    string $local_user,
  ): Awaitable<?string>;
}
