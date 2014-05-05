<?php

namespace Robot\Command;

use Robot\Command\Exception\RequiredTaskException;

trait Bumper
{
    protected $bumperMetadataSeparator = '+';

    protected $bumperPrereleaseSeparator = '-';

    protected $bumperTypes = [
        'milestone' => 'major',
        'feature' => 'minor',
        'hotfix' => 'patch'
    ];

/**
 * @desc Release current state.
 */
    public function bump($type = 'patch', $tag = null)
    {
        if (!$this->bumperReady()) {
            return;
        }

        if (!empty($this->bumperTypes[$type])) {
            $type = $this->bumperTypes[$type];
        }

        if (!is_null($tag)) {
            $this->bumperSemanticVersionCandidate($type, $tag);
        } else {
            $this->bumperSemanticVersion($type);
        }

        $semVer = $this->taskSemVer();

        $this->taskGit()
            ->add()
            ->commit('Bump version to ' . $semVer . '.')
            ->tag((string)$semVer)
            ->pull()
            ->push()
            ->run();
    }

    protected function bumperFileSystemReady()
    {
        $task = 'Robot\Task\FileSystem';
        if (!in_array($task, class_uses($this))) {
            throw new RequiredTaskException('Bump', $task);
        }

        return true;
    }

/**
 * Checks that the:
 *
 *   - Git task is used
 *   - Git repo is clean
 *
 * @return boolean
 * @throws \Robot\Command\Exception\RequiredTaskException If [this condition is met]
 */
    protected function bumperGitReady()
    {
        $task = 'Robot\Task\Git';
        if (!in_array($task, class_uses($this)) || !method_exists($this, 'taskGitRequiredClean')) {
            throw new RequiredTaskException('Bumper', $task);
        }

        return $this->taskGitRequiredClean()->run()->wasSuccessful();
    }

/**
 * Checks that the:
 *
 *   - Git task is used
 *   - Git repo is clean
 *   - Semver task is used
 *
 * @return boolean
 */
    protected function bumperReady()
    {
        return $this->bumperGitReady()
            && $this->bumperSemVerReady()
            && $this->bumperFileSystemReady();
    }

/**
 * Checks that the:
 *
 *   - Semver task is used
 *
 * @return boolean
 */
    protected function bumperSemVerReady()
    {
        $task = 'Robot\Task\SemVer';
        if (!in_array($task, class_uses($this))) {
            throw new RequiredTaskException('Bumper', $task);
        }

        return true;
    }

/**
 * Bumps version.
 *
 * @param string $type
 * @return void
 */
    protected function bumperSemanticVersion($type)
    {
        $this->bumperUpdate($this->taskSemVer()->increment($type)->run()->getMessage());
    }


    protected function bumperSemanticVersionCandidate($type, $tag)
    {
        $semVer = $this->taskSemVer()
            ->setPrereleaseSeparator($this->bumpPrereleaseSeparator)
            ->setMetadataSeparator($this->bumpMetadataSeparator);

        if (preg_match('/(?:(?:[1-9]?\d+\.[1-9]?\d+\.[1-9]\d*?)|(?:0\.0\.0))/', $semVer)) {
            $semVer->increment($type);
        }

        $this->bumperUpdate($semVer->prerelease($tag)->run()->getMessage());
    }

/**
 * Replaces version in the following files (only if they exist):
 *
 * - composer.json
 * - version
 * - Version
 * - VERSION
 * - version.txt
 * - Version.txt
 * - VERSION.txt
 *
 * @param  string $version
 * @return void
 */
    protected function bumperUpdate($version)
    {
        $filename = 'version';
        foreach (['', '.txt'] as $extension) {
            $filepath = $filename . $extension;
            foreach ([strtoupper($filename) . $extension, ucfirst($filepath), $filepath] as $path) {
                if (file_exists($path)) {
                    $file = $path;
                    break;
                }
            }

            if (!isset($file)) {
                continue;
            }

            $this->taskWriteToFile($file)->line($version)->run();
            unset($file);
        }

        if (file_exists('composer.json')) {
            $this->taskReplaceInFile('composer.json')
                ->regex('/"version": "[^\"]*"/')
                ->to('"version": "' . ltrim($version, 'v') . '"')
                ->run();
        }
    }

}
