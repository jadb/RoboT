<?php

use Robo\Task\Composer;
use Robo\Task\Watch;
use Robot\Command\Bumper;
use Robot\Command\Watcher;
use Robot\Task\FileSystem;
use Robot\Task\Git;
use Robot\Task\SemVer;

class RoboFile
{
    // Tasks
    use Composer;
    use FileSystem;
    use Git;
    use SemVer;
    use Watch;

    // Commands
    use Bumper;
    use Watcher;
}
