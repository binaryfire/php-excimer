--TEST--
ExcimerProfiler filters samples by Fiber
--SKIPIF--
<?php
if (!extension_loaded("excimer")) print "skip";
if (!class_exists('Fiber')) print "skip Fibers require PHP 8.1";
?>
--FILE--
<?php

function unrelatedWork() {
	global $calls;
	$target = $calls + 10;
	$deadline = microtime(true) + 2;
	while ($calls < $target && microtime(true) < $deadline) {
		usleep(1000);
	}
	var_dump($calls >= $target);
}

function ownWork($profiler) {
	$deadline = microtime(true) + 2;
	while (count($profiler->getLog()) < 10 && microtime(true) < $deadline) {
		usleep(1000);
	}
}

$profiler = new ExcimerProfiler;
$profiler->setPeriod(0.001);
$calls = 0;
$fiber = new Fiber(function () use ($profiler, &$calls) {
	$owner = Fiber::getCurrent();
	$profiler->setSampleFilter(function () use ($owner, &$calls) {
		$calls++;
		return Fiber::getCurrent() === $owner;
	});
	$profiler->start();
	Fiber::suspend();
	ownWork($profiler);
	$profiler->stop();
});
$fiber->start();
unrelatedWork();
var_dump(count($profiler->getLog()));
$fiber->resume();
$found = false;
foreach ($profiler->getLog() as $entry) {
	foreach ($entry->getTrace() as $frame) {
		if (isset($frame['function'])) {
			if ($frame['function'] === 'ownWork') $found = true;
			if ($frame['function'] === 'unrelatedWork') echo "Wrong Fiber\n";
		}
	}
}
var_dump($found);
$profiler->clearSampleFilter();
--EXPECT--
bool(true)
int(0)
bool(true)
