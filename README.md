# Rocketeer++;

```
# .rocketeer/tasks.php
<?php
use Rocketeer\Facades\Rocketeer;

Rocketeer::addTaskListeners('deploy', 'before-dependencies', function ($task) {
    $name = $task->getConnection()->connection()->getName();
    $task->runForCurrentRelease("cp .env.$name .env");
});

Rocketeer::addTaskListeners('deploy', 'before-symlink', function ($task) {
    $name = $task->getConnection()->connection()->getName();
    $task->runForCurrentRelease("php artisan cache:clear");
    $task->run("sudo chmod -R 777 /path/to/tmp");
});
```
