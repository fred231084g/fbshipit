<?hh
// Copyright (c) Meta Platforms, Inc. and affiliates.

use Facebook\ShipIt;
use Facebook\ShipIt\{ShipItRepoHG, ShipItEnv, ShipItChangeset, BaseTest};
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

<<\Oncalls('open_source')>>
final class ShipItRepoHGTest extends BaseTest {

  <<__LateInit>> private static ShipItChangeset $testChangeset;

  private static string $expectedHgPatch = <<< 'HGPATCH'
# HG changeset patch
# User Tester McTesterson <tester@example.com>
# Date 1655755205 %%DATE%%
#      Mon, 20 Jun 2022 13:00:05 -0700
# Node ID 730c1a3381881be0fc32d0b229e1b57ad4c3cb23
# Parent  0000000000000000000000000000000000000000
From subject, I provide a tricky message, D1234567890

From this place, I provide a tricky message, D1234567890

diff --git a/sample/file/1 b/sample/file/1
change - change
diff --git a/sample/file/2 b/sample/file/2
change - change
--
1.7.9.5

HGPATCH;

  <<__Override>>
  public static async function createData(): Awaitable<void> {
    date_default_timezone_set("America/Los_Angeles"); // @oss-enable
    self::$testChangeset = (new ShipItChangeset())
      ->withAuthor("Tester McTesterson <tester@example.com>")
      ->withID("730c1a3381881be0fc32d0b229e1b57ad4c3cb23")
      ->withSubject("From subject, I provide a tricky message, D1234567890")
      ->withMessage("From this place, I provide a tricky message, D1234567890")
      ->withDiffs(vec[
        shape(
          'path' => 'sample/file/1',
          'body' => 'change - change',
        ),
        shape(
          'path' => 'sample/file/2',
          'body' => 'change - change',
        ),
      ])
      ->withTimestamp(1655755205);
  }

  public async function testRenderPatchToGenerateHgStyleHeader(
  ): Awaitable<void> {
    $patch_output = ShipItRepoHG::renderPatch(self::$testChangeset);

    expect($patch_output)->toNotBeNull();

    $expected_patch_no_daylight_savings =
      Str\replace(self::$expectedHgPatch, '%%DATE%%', '25200');
    $expected_patch_daylight_savings =
      Str\replace(self::$expectedHgPatch, '%%DATE%%', '28800');

    // Verify we have an HG styled header
    expect(
      Str\compare($patch_output, $expected_patch_no_daylight_savings) === 0 ||
        Str\compare($patch_output, $expected_patch_daylight_savings) === 0,
    )->toBeTrue("Failed to generate an HG styled header in the patch");
  }
}
