<?php

declare(strict_types=1);

namespace Engelsystem\Test\Unit\Http\SessionHandlers;

use Engelsystem\Config\Config;
use Engelsystem\Helpers\Carbon;
use Engelsystem\Http\SessionHandlers\DatabaseHandler;
use Engelsystem\Test\Unit\HasDatabase;
use Engelsystem\Test\Unit\TestCase;

class DatabaseHandlerTest extends TestCase
{
    use HasDatabase;

    /**
     * @covers \Engelsystem\Http\SessionHandlers\DatabaseHandler::__construct
     * @covers \Engelsystem\Http\SessionHandlers\DatabaseHandler::getQuery
     * @covers \Engelsystem\Http\SessionHandlers\DatabaseHandler::read
     */
    public function testRead(): void
    {
        $handler = new DatabaseHandler($this->database);
        $this->assertEquals('', $handler->read('foo'));

        $this->database->getConnection()
            ->table('sessions')
            ->insert([
                ['id' => 'id-foo', 'payload' => 'Lorem Ipsum', 'last_activity' => Carbon::now()],
            ]);
        $this->assertEquals('Lorem Ipsum', $handler->read('id-foo'));
    }

    /**
     * @covers \Engelsystem\Http\SessionHandlers\DatabaseHandler::write
     */
    public function testWrite(): void
    {
        $handler = new DatabaseHandler($this->database);

        foreach (['Lorem Ipsum', 'Dolor Sit!'] as $data) {
            $this->assertTrue($handler->write('id-foo', $data));

            $return = $this->database->getConnection()->table('sessions')
                ->where('id', 'id-foo')
                ->get();
            $this->assertCount(1, $return);

            $return = $return->first();
            $this->assertEquals($data, $return->payload);
        }
    }

    /**
     * @covers \Engelsystem\Http\SessionHandlers\DatabaseHandler::destroy
     */
    public function testDestroy(): void
    {
        $table = $this->database->getConnection()->table('sessions');
        $table
            ->insert([
                ['id' => 'id-foo', 'payload' => 'Lorem Ipsum', 'last_activity' => Carbon::now()->subHours(25)],
                ['id' => 'id-bar', 'payload' => 'Dolor Sit', 'last_activity' => Carbon::now()],
            ]);

        $handler = new DatabaseHandler($this->database);
        $this->assertTrue($handler->destroy('id-baz'));

        $return = $table->get();
        $this->assertCount(2, $return);

        $this->assertTrue($handler->destroy('id-bar'));

        $return = $table->get();
        $this->assertCount(1, $return);

        $return = $return->first();
        $this->assertEquals('id-foo', $return->id);
    }

    /**
     * @covers \Engelsystem\Http\SessionHandlers\DatabaseHandler::gc
     */
    public function testGc(): void
    {
        $this->app->instance('config', new Config(['session' => ['lifetime' => 2]])); // 2 days

        $table = $this->database->getConnection()->table('sessions');
        $table
            ->insert([
                ['id' => 'id-foo', 'payload' => 'Lorem Ipsum', 'last_activity' => Carbon::now()->subHours(48 + 1)],
                ['id' => 'id-bar', 'payload' => 'Dolor Sit', 'last_activity' => Carbon::now()],
            ]);

        $handler = new DatabaseHandler($this->database);

        // Max lifetime gets overwritten by settings anyway
        $this->assertEquals(1, $handler->gc(42));

        $return = $table->get();
        $this->assertCount(1, $return);

        $return = $return->first();
        $this->assertEquals('id-bar', $return->id);
    }

    /**
     * Prepare tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->initDatabase();
    }
}
