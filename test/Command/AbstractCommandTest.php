<?php

/**
 * @see       https://github.com/laminas/laminas-composer-autoloading for the canonical source repository
 * @copyright https://github.com/laminas/laminas-composer-autoloading/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-composer-autoloading/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ComposerAutoloading\Command;

use Laminas\ComposerAutoloading\Command;
use Laminas\ComposerAutoloading\Exception;
use LaminasTest\ComposerAutoloading\ProjectSetupTrait;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

class AbstractCommandTest extends TestCase
{
    use ProjectSetupTrait;

    /** @var vfsStreamDirectory */
    private $dir;

    /** @var vfsStreamDirectory */
    private $modulesDir;

    /** @var Command\AbstractCommand|PHPUnit_Framework_MockObject_MockObject */
    private $command;

    protected function setUp()
    {
        parent::setUp();

        $this->dir = vfsStream::setup('project');
        $this->modulesDir = vfsStream::newDirectory('module')->at($this->dir);

        $this->command = $this->getMockBuilder(Command\AbstractCommand::class)
            ->setMethods(['execute'])
            ->setConstructorArgs([$this->dir->url(), 'module', $this->composer])
            ->enableOriginalConstructor()
            ->enableProxyingToOriginalMethods()
            ->getMockForAbstractClass();
    }

    public function type()
    {
        return [
            'psr-0' => ['psr-0'],
            'psr-4' => ['psr-4'],
        ];
    }

    /**
     * @dataProvider type
     *
     * @param string $type
     */
    public function testThrowsExceptionWhenComposerJsonDoesNotExist($type)
    {
        $this->command->expects($this->never())->method('execute');
        $this->setUpModule($this->modulesDir, 'App', $type);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('composer.json file does not exist');
        $this->command->process('App', $type);
    }

    /**
     * @dataProvider type
     *
     * @param string $type
     */
    public function testThrowsExceptionWhenComposerJsonIsNotWritable($type)
    {
        $this->command->expects($this->never())->method('execute');
        $this->setUpModule($this->modulesDir, 'App', $type);
        vfsStream::newFile('composer.json', 0444)->at($this->dir);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('composer.json file is not writable');
        $this->command->process('App', $type);
    }

    /**
     * @dataProvider type
     *
     * @param string $type
     */
    public function testThrowsExceptionWhenComposerJsonHasInvalidContent($type)
    {
        $this->command->expects($this->never())->method('execute');
        $this->setUpModule($this->modulesDir, 'App', $type);
        vfsStream::newFile('composer.json')
            ->withContent('invalid content')
            ->at($this->dir);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Error parsing composer.json file');
        $this->command->process('App', $type);
    }

    /**
     * @dataProvider type
     *
     * @param string $type
     */
    public function testThrowsExceptionWhenComposerJsonHasNoContent($type)
    {
        $this->command->expects($this->never())->method('execute');
        $this->setUpModule($this->modulesDir, 'App', $type);
        $this->setUpComposerJson($this->dir);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('The composer.json file was empty');
        $this->command->process('App', $type);
    }

    public function testThrowsExceptionWhenCannotDetermineModuleType()
    {
        $this->command->expects($this->never())->method('execute');
        vfsStream::newDirectory('App')->at($this->modulesDir);
        $this->setUpComposerJson($this->dir, []);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine autoloading type; no src directory found in module');
        $this->command->process('App');
    }

    /**
     * @dataProvider type
     *
     * @param string $type
     */
    public function testComposerJsonContentIsNotChangedAndDumpAutoloadIsNotCalledWhenExecuteMethodReturnsFalse($type)
    {
        $this->command->expects($this->once())->method('execute')->willReturn(false);
        $this->setUpModule($this->modulesDir, 'App', $type);
        $composerJson = $this->setUpComposerJson($this->dir, ['foo' => 'bar']);

        $this->assertNotComposerDumpAutoload();
        $this->assertFalse($this->command->process('App', $type));
        $this->assertEquals('{"foo":"bar"}', file_get_contents($composerJson->url()));
    }

    /**
     * @dataProvider type
     *
     * @param string $type
     */
    public function testComposerJsonContentIsUpdatedAndDumpAutoloadIsCalledWhenExecuteMethodReturnsNewContent($type)
    {
        $expectedComposerJson = <<< 'EOC'
{
    "new": "content"
}

EOC;

        $this->command->expects($this->once())->method('execute')->willReturn(['new' => 'content']);
        $this->setUpModule($this->modulesDir, 'App', $type);
        $composerJson = $this->setUpComposerJson($this->dir, ['foo' => 'bar']);

        $this->assertComposerDumpAutoload();
        $this->assertTrue($this->command->process('App', $type));
        $this->assertEquals($expectedComposerJson, file_get_contents($composerJson->url()));
    }
}
