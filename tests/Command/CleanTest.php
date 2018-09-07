<?php

namespace TwigBridge\Tests\Command;

use TwigBridge\Tests\Base;
use Mockery as m;
use TwigBridge\Command\Clean;
use Symfony\Component\Console\Input\ArrayInput;

class CleanTest extends Base
{
    public function testInstance()
    {
        $command = new Clean;

        $this->assertInstanceOf('Illuminate\Console\Command', $command);
    }

    public function testFailed()
    {
        $app = $this->getApplication();

        $app['twig'] = m::mock('Twig_Environment');
        $app['twig']->shouldReceive('getCache');
        $this->assertEquals(1, $app['twig']->mockery_getExpectationCount());

        $app['files'] = m::mock('Twig_Environment');
        $app['files']->shouldReceive('deleteDirectory');
        $app['files']->shouldReceive('exists')->andReturn(true);
        $this->assertEquals(2, $app['files']->mockery_getExpectationCount());

        $command = new Clean;
        $command->setLaravel($app);

        $output = m::mock('Symfony\Component\Console\Output\NullOutput')->makePartial();
        $output->shouldReceive('writeln')->with('<error>Twig cache failed to be cleaned</error>');
        $this->assertEquals(1, $output->mockery_getExpectationCount());

        $command->run(
            new ArrayInput([]),
            $output
        );
    }

    public function testSuccess()
    {
        $app = $this->getApplication();

        $app['twig'] = m::mock('Twig_Environment');
        $app['twig']->shouldReceive('getCache');
        $this->assertEquals(1, $app['twig']->mockery_getExpectationCount());

        $app['files'] = m::mock('Twig_Environment');
        $app['files']->shouldReceive('deleteDirectory');
        $app['files']->shouldReceive('exists')->andReturn(false);
        $this->assertEquals(2, $app['files']->mockery_getExpectationCount());

        $command = new Clean;
        $command->setLaravel($app);

        $output = m::mock('Symfony\Component\Console\Output\NullOutput')->makePartial();
        $output->shouldReceive('writeln')->with('<info>Twig cache cleaned</info>');
        $this->assertEquals(1, $output->mockery_getExpectationCount());

        $command->run(
            new ArrayInput([]),
            $output
        );
    }
}
