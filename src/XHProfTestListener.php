<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\XHProfTestListener;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Warning;

/**
 * A TestListener that integrates with XHProf.
 *
 * Here is an example XML configuration for activating this listener:
 *
 * <code>
 * <listeners>
 *  <listener class="PHPUnit\XHProfTestListener\XHProfTestListener">
 *   <arguments>
 *    <array>
 *     <element key="xhprofLibFile">
 *      <string>/var/www/xhprof_lib/utils/xhprof_lib.php</string>
 *     </element>
 *     <element key="xhprofRunsFile">
 *      <string>/var/www/xhprof_lib/utils/xhprof_runs.php</string>
 *     </element>
 *     <element key="xhprofWeb">
 *      <string>http://localhost/xhprof_html/index.php</string>
 *     </element>
 *     <element key="appNamespace">
 *      <string>Doctrine2</string>
 *     </element>
 *     <element key="xhprofFlags">
 *      <string>XHPROF_FLAGS_CPU,XHPROF_FLAGS_MEMORY</string>
 *     </element>
 *     <element key="xhprofIgnore">
 *      <string>call_user_func,call_user_func_array</string>
 *     </element>
 *    </array>
 *   </arguments>
 *  </listener>
 * </listeners>
 * </code>
 *
 * @author     Benjamin Eberlei <kontakt@beberlei.de>
 * @author     Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright  2011-2015 Sebastian Bergmann <sebastian@phpunit.de>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link       http://www.phpunit.de/
 * @since      Class available since Release 1.0.0
 */
class XHProfTestListener implements TestListener
{
    /**
     * @var array
     */
    protected $runs = [];

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var int
     */
    protected $suites = 0;

    /**
     * @var bool
     */
    protected $xhprof_is_started = false;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if (!isset($options['appNamespace'])) {
            throw new \InvalidArgumentException(
              'The "appNamespace" option is not set.'
            );
        }

        if (!isset($options['xhprofLibFile']) ||
            !file_exists($options['xhprofLibFile'])) {
            throw new \InvalidArgumentException(
              'The "xhprofLibFile" option is not set or the configured file does not exist'
            );
        }

        if (!isset($options['xhprofRunsFile']) ||
            !file_exists($options['xhprofRunsFile'])) {
            throw new \InvalidArgumentException(
              'The "xhprofRunsFile" option is not set or the configured file does not exist'
            );
        }

        require_once $options['xhprofLibFile'];
        require_once $options['xhprofRunsFile'];

        $this->options = $options;
    }

    /**
     * An error occurred.
     */
    public function addError(Test $test, \Throwable $e, float $time): void
    {
    }

    /**
     * A failure occurred.
     */
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
    }

    /**
     * Incomplete test.
     */
    public function addIncompleteTest(Test $test, \Throwable $e, float $time): void
    {
    }

    /**
     * Skipped test.
     */
    public function addSkippedTest(Test $test, \Throwable $e, float $time): void
    {
    }

    /**
     * Risky test.
     */
    public function addRiskyTest(Test $test, \Throwable $e, float $time): void
    {
    }

    /**
     * A warning occurred.
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
    }

    /**
     * A test started.
     */
    public function startTest(Test $test): void
    {
        if (!extension_loaded('xhprof'))
            return;

        $annotations = $test->getAnnotations();

        if (!isset($annotations['method']['profile']))
            return;

        $this->startProfiling();
    }

    /**
     * A test ended.
     */
    public function endTest(Test $test, float $time): void
    {
        if (!extension_loaded('xhprof'))
            return;

        $annotations = $test->getAnnotations();

        if (!isset($annotations['method']['profile']))
            return;

        $test_name = get_class($test) . '::' . $test->getName();
        $this->endProfiling($test_name);
    }

    /**
     * A test suite started.
     */
    public function startTestSuite(TestSuite $suite): void
    {
        $this->suites++;
    }

    /**
     * A test suite ended.
     */
    public function endTestSuite(TestSuite $suite): void
    {
        $this->suites--;

        if ($this->suites == 0) {
            print "\n\nXHProf runs: " . count($this->runs) . "\n";

            foreach ($this->runs as $test => $run) {
                print ' * ' . $test . "\n   " . $run . "\n\n";
            }

            print "\n";
        }
    }

    protected function startProfiling(): void
    {
        if (!isset($this->options['xhprofFlags'])) {
            $flags = XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY;
        } else {
            $flags = 0;

            foreach (explode(',', $this->options['xhprofFlags']) as $flag) {
                $flags += constant($flag);
            }
        }

        xhprof_enable($flags, [
            'ignored_functions' => explode(',', $this->options['xhprofIgnore'])
        ]);

        $this->xhprof_is_started = true;
    }

    protected function endProfiling($name): void
    {
        $name = str_replace('\\', '_', $name);

        $data = xhprof_disable();
        $runs = new \XHProfRuns_Default;
        $run = $runs->save_run($data, $this->options['appNamespace'] . '_' . $name);
        $this->runs[$name] = $this->options['xhprofWeb'] . '?run=' . $run
                           . '&source=' . $this->options['appNamespace'] . '_' . $name
                           ;

        $this->xhprof_is_started = false;
    }
}
