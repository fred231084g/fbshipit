<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/l38w0ens
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, Regex};

abstract class ShipItUtil {
  const SHORT_REV_LENGTH = 7;
  // flags for shellExec, no flag equal to 1
  // for compatibility with ShipItRepo verbose flags
  const DONT_VERBOSE = 0;
  const VERBOSE_SHELL = 2;
  const VERBOSE_SHELL_INPUT = 4;
  const VERBOSE_SHELL_OUTPUT = 8;
  const NO_THROW = 16;
  const RETURN_STDERR = 32;

  // 0 is runtime log rate - typechecker is sufficient.
  <<__Deprecated('Use ShipItShellCommand instead in new code', 0)>>
  public static function shellExec(
    string $path,
    ?string $stdin,
    int $flags,
    string ...$args
  ): string {
    $command = new ShipItShellCommand($path, ...$args);

    if ($flags & self::VERBOSE_SHELL) {
      $cmd = Str\join($args, ' ');
      ShipItLogger::err("\$ %s\n", $cmd);
    }


    if ($stdin !== null) {
      if ($flags & self::VERBOSE_SHELL_INPUT) {
        ShipItLogger::err("--STDIN--\n%s\n", $stdin);
      }
      $command->setStdIn($stdin);
    }

    if ($flags & self::VERBOSE_SHELL_OUTPUT) {
      $command->setOutputToScreen();
    }

    if ($flags && self::NO_THROW) {
      $command->setNoExceptions();
    }

    $result = $command->runSynchronously();

    $output = $result->getStdOut();
    if ($flags & self::RETURN_STDERR) {
      $output .= "\n".$result->getStdErr();
    }
    return $output;
  }
}
