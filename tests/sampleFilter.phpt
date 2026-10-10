--TEST--
ExcimerProfiler sample filter
--SKIPIF--
<?php if (!extension_loaded("excimer")) print "skip"; ?>
--FILE--
<?php

$profiler = new ExcimerProfiler;
$profiler->setPeriod(0.001);
$called = 0;
$flushed = 0;
$profiler->setFlushCallback(function ($log) use (&$flushed) {
	$flushed += count($log);
}, 5);
$profiler->setSampleFilter(function () use (&$called) {
	$called++;
	return false;
});
$profiler->start();
$deadline = microtime(true) + 2;
while ($called < 10 && microtime(true) < $deadline) {
	usleep(1000);
}
$profiler->stop();
var_dump($called >= 10, count($profiler->getLog()), $profiler->getLog()->getEventCount(), $flushed);

$profiler->clearFlushCallback();
$profiler->setSampleFilter(function () {
	return true;
});
$profiler->start();
$deadline = microtime(true) + 2;
while (count($profiler->getLog()) < 5 && microtime(true) < $deadline) {
	usleep(1000);
}
$profiler->stop();
$log = $profiler->flush();
var_dump(count($log) >= 5);

$profiler->setSampleFilter(function () { return false; });
$profiler->clearSampleFilter();
$profiler->start();
$deadline = microtime(true) + 2;
while (!count($profiler->getLog()) && microtime(true) < $deadline) {
	usleep(1000);
}
$profiler->stop();
var_dump(count($profiler->getLog()) > 0);

$profiler->flush();
$profiler->setSampleFilter(function () use ($profiler) {
	$profiler->flush();
	$profiler->clearSampleFilter();
	return true;
});
$profiler->start();
$deadline = microtime(true) + 2;
while (!count($profiler->getLog()) && microtime(true) < $deadline) {
	usleep(1000);
}
$profiler->stop();
var_dump(count($profiler->getLog()) > 0);

class Filter {
	private $profiler;

	public function __construct($profiler) {
		$this->profiler = $profiler;
	}

	public function __invoke() {
		return true;
	}

	public function __destruct() {
		echo "Released\n";
	}
}

$profiler = new ExcimerProfiler;
$profiler->setSampleFilter(new Filter($profiler));
$profiler->setSampleFilter(function () { return false; });
$profiler->setSampleFilter(new Filter($profiler));
$profiler->clearSampleFilter();
$profiler->setSampleFilter(new Filter($profiler));
unset($profiler);
gc_collect_cycles();
echo "Done\n";
--EXPECT--
bool(true)
int(0)
int(0)
int(0)
bool(true)
bool(true)
bool(true)
Released
Released
Released
Done
