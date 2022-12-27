<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/j3hwi4do
 */
namespace Facebook\ShipIt;

final class ShipItVerifyRepoPhase extends ShipItPhase {
  private bool $createPatch = false;
  private bool $useLatestSourceCommit = false;
  private ?string $verifySourceCommit = null;
  private bool $shouldDoSubmodules = true;

  const shape('name' => string, 'email' => string) COMMITTER_INFO = shape(
    'name' => 'FBShipIt Internal User',
    'email' => 'fbshipit@example.com',
  );

  public function __construct(
    private (function(ShipItChangeset): Awaitable<ShipItChangeset>) $genFilter,
  ) {
    $this->skip();
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Verify that destination repository is sync';
  }

  <<__Override>>
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'verify',
        'description' =>
          'Verify that the destination repository is in sync, then exit',
        'write' => $_ ==> $this->unskip(),
      ),
      shape(
        'long_name' => 'create-fixup-patch',
        'description' =>
          'Create a patch to get the destination repository in sync, then exit',
        'write' => $_ ==> {
          $this->unskip();
          $this->createPatch = true;
          return true;
        },
      ),
      shape(
        'long_name' => 'verify-source-commit::',
        'description' => 'Hash of first commit that needs to be synced',
        'write' => $x ==> {
          $this->verifySourceCommit = $x;
          return $this->verifySourceCommit;
        },
      ),
      shape(
        'long_name' => 'use-latest-source-commit',
        'description' =>
          'Find the latest synced source commit to use as a base for verify',
        'write' => $_ ==> {
          $this->useLatestSourceCommit = true;
          return $this->useLatestSourceCommit;
        },
      ),
      shape(
        'long_name' => 'skip-submodules',
        'description' => 'Don\'t sync submodules',
        'write' => $_ ==> {
          $this->shouldDoSubmodules = false;
          return $this->shouldDoSubmodules;
        },
      ),
    ];
  }

  /**
   * Verifys the destination repo by creating a fresh source repo and then
   * comparing them.
   *
   * NOTE: the destination repo MUST be a git repo.
   *
   * The shape is only returned if 'createPatch' is passed AND the repo is out
   * of sync. In all other cases ShipItExitException is thrown with an
   * appropriate error code, after printing an error/success message.
   */
  public static async function genVerifyRepo(
    shape(
      'manifest' => ShipItManifest,
      'genFilter' => (function(ShipItChangeset): Awaitable<ShipItChangeset>),
      'useLatestSourceCommit' => bool,
      'verifySourceCommit' => ?string,
      'shouldDoSubmodules' => bool,
      'createPatch' => bool,
    ) $args,
  ): Awaitable<shape('diffstat' => string, 'patch' => string)> {
    if ($args['useLatestSourceCommit']) {
      if ($args['verifySourceCommit'] is nonnull) {
        throw new ShipItException(
          "the 'verify-source-commit' flag cannot be used with the ".
          "'use-latest-source-commit' flag since the latter automatically ".
          "sets the verify source commit",
        );
      }
      $repo = await ShipItRepo::genTypedOpen<ShipItDestinationRepo>(
        $args['manifest']->getDestinationSharedLock(),
        $args['manifest']->getDestinationPath(),
        $args['manifest']->getDestinationBranch(),
      );
      invariant(
        $repo is ShipItRepoGIT,
        "This phase only works if the destination is a git repo",
      );
      $args['verifySourceCommit'] = await $repo->genFindLastSourceCommit(
        keyset[],
        $args['manifest']->getCommitMarker(),
      );
    }
    $clean_dir = await ShipItCreateNewRepoPhase::genCreateNewGitRepo(
      $args['manifest'],
      $args['genFilter'],
      static::COMMITTER_INFO,
      $args['shouldDoSubmodules'],
      $args['verifySourceCommit'],
    );
    $clean_path = $clean_dir->getPath();
    $dirty_remote = 'shipit_dest';
    $dirty_ref = $dirty_remote.'/'.$args['manifest']->getDestinationBranch();

    await (
      new ShipItShellCommand(
        $clean_path,
        'git',
        'remote',
        'add',
        $dirty_remote,
        $args['manifest']->getDestinationPath(),
      )
    )->genRun();
    await (
      new ShipItShellCommand($clean_path, 'git', 'fetch', $dirty_remote)
    )->genRun();

    await (
      new ShipItShellCommand($clean_path, 'git', 'checkout', 'HEAD')
    )->genRun();

    $diffstat = (
      await (
        new ShipItShellCommand(
          $clean_path,
          'git',
          'diff',
          '--stat',
          $dirty_ref,
          'HEAD',
        )
      )->genRun()
    )->getStdOut();

    if ($diffstat === '') {
      if ($args['createPatch']) {
        ShipItLogger::err(
          "  CREATE PATCH FAILED: destination is already in sync.\n",
        );
        throw new ShipItExitException(1);
      }
      ShipItLogger::out("  Verification OK: destination is in sync.\n");
      throw new ShipItExitException(0);
    }

    if (!$args['createPatch']) {
      ShipItLogger::err(
        "  VERIFICATION FAILED: destination repo does not match:\n\n%s\n",
        $diffstat,
      );
      throw new ShipItExitException(1);
    }

    $patch = (
      await (
        new ShipItShellCommand(
          $clean_path,
          'git',
          'diff',
          '--full-index',
          '--binary',
          '--no-color',
          $dirty_ref,
          'HEAD',
        )
      )->genRun()
    )->getStdOut();

    return shape('diffstat' => $diffstat, 'patch' => $patch);
  }

  <<__Override>>
  protected async function genRunImpl(
    ShipItManifest $manifest,
  ): Awaitable<void> {
    $results = await static::genVerifyRepo(shape(
      'manifest' => $manifest,
      'genFilter' => $this->genFilter,
      'useLatestSourceCommit' => $this->useLatestSourceCommit,
      'verifySourceCommit' => $this->verifySourceCommit,
      'shouldDoSubmodules' => $this->shouldDoSubmodules,
      'createPatch' => $this->createPatch,
    ));
    $diffstat = $results['diffstat'];
    $diff = $results['patch'];

    if ($diffstat === '') {
      if ($this->createPatch) {
        ShipItLogger::err(
          "  CREATE PATCH FAILED: destination is already in sync.\n",
        );
        throw new ShipItExitException(1);
      }
      ShipItLogger::out("  Verification OK: destination is in sync.\n");
      throw new ShipItExitException(0);
    }

    if (!$this->createPatch) {
      ShipItLogger::err(
        "  VERIFICATION FAILED: destination repo does not match:\n\n%s\n",
        $diffstat,
      );
      throw new ShipItExitException(1);
    }

    $source_sync_id = $this->verifySourceCommit;
    if ($source_sync_id === null) {
      $repo = await ShipItRepo::genTypedOpen<ShipItSourceRepo>(
        $manifest->getSourceSharedLock(),
        $manifest->getSourcePath(),
        $manifest->getSourceBranch(),
      );
      $changeset = await $repo->genHeadChangeset();
      if ($changeset === null) {
        throw new ShipItException('Could not find source id.');
      }
      $source_sync_id = $changeset->getID();
    }

    $patch_file = PHP\tempnam(PHP\sys_get_temp_dir(), 'shipit-resync-patch-')
      as string;
    PHP\file_put_contents($patch_file, $diff);

    ShipItLogger::out(
      "  Created patch file: %s\n\n".
      "%s\n\n".
      "  To apply:\n\n".
      "    $ cd %s\n".
      "    $ git apply < %s\n".
      "    $ git status\n".
      "    $ git add --all --patch\n".
      "    $ git commit -m '%s: %s'\n".
      "    $ git push\n\n".
      "  WARNING: there are 4 possible causes for differences:\n\n".
      "    1. changes in source haven't been copied to destination\n".
      "    2. changes were made to destination that aren't in source\n".
      "    3. the filter function has a bug\n".
      "    4. FBShipIt has a bug\n\n".
      "  APPLYING THE PATCH IS ONLY CORRECT FOR THE FIRST SITUATION; review\n".
      "  the changes carefully.\n\n",
      $patch_file,
      $diffstat,
      $manifest->getDestinationPath(),
      $patch_file,
      $manifest->getCommitMarker(),
      $source_sync_id,
    );
    throw new ShipItExitException(0);
  }
}
