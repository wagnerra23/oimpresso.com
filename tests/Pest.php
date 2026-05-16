<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

// Pest 3.x descobre `Pest.php` somente em `tests/` (Bootstrappers\BootFiles),
// então o `uses(...)->in()` de módulos com test suites próprios precisa ficar
// AQUI mesmo. `realpath` resolve worktrees / junctions.
$kbFeatureDir = realpath(__DIR__ . '/../Modules/KB/Tests/Feature');
$kbUnitDir    = realpath(__DIR__ . '/../Modules/KB/Tests/Unit');
if ($kbFeatureDir !== false) { uses(TestCase::class)->in($kbFeatureDir); }
if ($kbUnitDir    !== false) { uses(TestCase::class)->in($kbUnitDir); }
