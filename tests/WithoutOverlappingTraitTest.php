<?php

namespace Illuminated\Console;

use Artisan;
use GenericCommand;
use Mockery;
use MysqlStrategyCommand;
use TestCase;

class WithoutOverlappingTraitTest extends TestCase
{
    /** @test */
    public function it_adds_mutex_strategy_which_is_file_by_default()
    {
        $command = new GenericCommand;
        $this->assertEquals('file', $command->getMutexStrategy());
    }

    /** @test */
    public function mutex_strategy_can_be_overloaded_by_protected_field()
    {
        $command = new MysqlStrategyCommand;
        $this->assertEquals('mysql', $command->getMutexStrategy());
    }

    /** @test */
    public function mutex_strategy_can_be_set_by_the_public_method()
    {
        $command = new GenericCommand;
        $command->setMutexStrategy('redis');
        $this->assertEquals('redis', $command->getMutexStrategy());
    }

    /** @test */
    public function it_generates_mutex_name_based_on_command_name_and_arguments()
    {
        $command = Mockery::mock(GenericCommand::class)->makePartial();
        $command->shouldReceive('getName')->withNoArgs()->once()->andReturn('icm:generic');
        $command->shouldReceive('argument')->withNoArgs()->once()->andReturn(['foo' => 'bar', 'baz' => 'faz']);

        $md5 = md5(json_encode(['foo' => 'bar', 'baz' => 'faz']));
        $this->assertEquals("icmutex-icm:generic-{$md5}", $command->getMutexName());
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function it_is_trying_to_acquire_lock_on_command_initialization()
    {
        $mutex = Mockery::mock('overload:Illuminated\Console\Mutex');
        $mutex->shouldReceive('acquireLock')->with(0)->once()->andReturn(false);

        Artisan::call('icm:generic');
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function it_is_releasing_the_lock_after_execution_if_it_was_acquired()
    {
        $mutex = Mockery::mock('overload:Illuminated\Console\Mutex');
        $mutex->shouldReceive('acquireLock')->with(0)->once()->andReturn(true);
        $mutex->shouldReceive('releaseLock')->withNoArgs();

        Artisan::call('icm:generic');
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function it_stops_execution_if_lock_had_not_been_acquired()
    {
        $mutex = Mockery::mock('overload:Illuminated\Console\Mutex');
        $mutex->shouldReceive('acquireLock')->with(0)->once()->andReturn(false);
        Mockery::mock()->shouldReceive('exit')->withNoArgs()->once();

        $code = Artisan::call('icm:generic');
        $output = Artisan::getOutput();

        $this->assertEquals(0, $code);
        $this->assertContains('Command is running now!', $output);
    }
}