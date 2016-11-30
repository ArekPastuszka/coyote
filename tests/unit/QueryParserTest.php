<?php

use Coyote\Services\Parser\Parsers\Link;
use Faker\Factory;

class QueryParserTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var array
     */
    private $keywords = ['ip', 'user', 'browser'];

    public function testParseIpQuery()
    {
        $parser = new \Coyote\Services\Elasticsearch\QueryParser('ip:127.0.0.1', $this->keywords);
        $this->assertArrayHasKey('ip', $filters = $parser->getFilters());
        $this->assertEquals('127.0.0.1', $filters['ip']);
    }

    public function testParseUserQuery()
    {
        $parser = new \Coyote\Services\Elasticsearch\QueryParser('user:admin', $this->keywords);
        $this->assertArrayHasKey('user', $filters = $parser->getFilters());
        $this->assertEquals('admin', $filters['user']);

        $parser = new \Coyote\Services\Elasticsearch\QueryParser('user:"admin adminski"', $this->keywords);
        $this->assertArrayHasKey('user', $filters = $parser->getFilters());
        $this->assertEquals('admin adminski', $filters['user']);
    }
}
