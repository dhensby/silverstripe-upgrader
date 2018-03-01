<?php

namespace SilverStripe\Upgrader\Tests\UpgradeRule\PHP;

use PHPUnit\Framework\TestCase;
use SilverStripe\Upgrader\CodeCollection\CodeChangeSet;
use SilverStripe\Upgrader\Tests\FixtureLoader;
use SilverStripe\Upgrader\Tests\InspectCodeTrait;
use SilverStripe\Upgrader\Tests\MockCodeCollection;
use SilverStripe\Upgrader\UpgradeRule\PHP\ApiChangeWarningsRule;

class ApiChangeWarningsRuleTest extends TestCase
{
    use FixtureLoader;
    use InspectCodeTrait;

    protected function setUp()
    {
        parent::setUp();
        $this->setUpInspect();
    }

    protected function tearDown()
    {
        $this->tearDownInspect();
        parent::tearDown();
    }

    /**
     * @runInSeparateProcess
     */
    public function testClassExtendsWithoutReplacement()
    {

        $input = <<<PHP
<?php

class MyClass extends Object
{
}
PHP;

        $parameters = [
            'warnings' => [
                'classes' => [
                    'Object' => [
                        'message' => 'Classes extending Object need to use traits',
                        'url' => 'https://some-url'
                    ]
                ]
            ]
        ];

        $updater = (new ApiChangeWarningsRule($this->state->getContainer()))->withParameters($parameters);

        // Build mock collection
        $code = new MockCodeCollection([
            'MyClass.php' => $input,
            'object.php' => '<?php class object {} ',
        ]);
        $this->autoloader->addCollection($code);
        $file = $code->itemByPath('MyClass.php');
        $changeset = new CodeChangeSet();

        $updater->upgradeFile($input, $file, $changeset);

        $this->assertTrue($changeset->hasWarnings('MyClass.php'));
        $warnings = $changeset->warningsForPath('MyClass.php');
        $this->assertEquals(count($warnings), 1);
        $this->assertContains('MyClass.php:3', $warnings[0]);
        $this->assertContains('Classes extending Object need to use traits', $warnings[0]);
        $this->assertContains('class MyClass extends Object', $this->getLineForWarning($input, $warnings[0]));
    }

    /**
     * @param $input
     * @param $warning
     * @return string|null
     */
    protected function getLineForWarning($input, $warning)
    {
        // Expects "<info>path/file.php:5<info><comment>...</comment>"
        $line = preg_replace('/.*:(\d+).*/', '$1', $warning);
        $lines = explode("\n", $input);

        return is_numeric($line) ? $lines[$line-1] : null;
    }
}
