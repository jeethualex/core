<?php


namespace OpenDialogAi\Core\Graph\tests\DGraph;


use OpenDialogAi\Core\Conversation\Model;
use OpenDialogAi\Core\Graph\DGraph\DGraphQuery;
use OpenDialogAi\Core\Graph\DGraph\DGraphQueryFilter;
use OpenDialogAi\Core\Tests\TestCase;

class DGraphQueryTest extends TestCase
{
    public function testBasicQuery() {
        $query = new DGraphQuery();
        $query->eq(Model::ID, 'test')
        ->setQueryGraph([
            Model::UID,
            Model::ID
        ]);

        $preparedQuery = $query->prepare();
        $this->assertEquals('{ dGraphQuery( func:eq(id,"test")){uid id }}', $preparedQuery);
    }

    public function testQueryWithBasicFilter() {
        $query = new DGraphQuery();
        $query->eq(Model::ID, 'test')
        ->filter(function (DGraphQueryFilter $filter) { $filter->eq(Model::EI_TYPE, 'conversation_template'); })
        ->setQueryGraph([
            Model::UID,
            Model::ID
        ]);

        $preparedQuery = $query->prepare();
        $this->assertEquals(
            '{ dGraphQuery( func:eq(id,"test"))@filter( eq(ei_type,"conversation_template")){uid id }}',
            $preparedQuery
        );
    }

    public function testQueryWithComplexFilter() {
        $query = new DGraphQuery();
        $query->eq(Model::ID, 'test')
        ->filter(function (DGraphQueryFilter $filter) { $filter->eq(Model::EI_TYPE, 'conversation_template'); })
        ->andFilter(function (DGraphQueryFilter $filter) {
            $filter->notHas(Model::HAS_OPENING_SCENE);
        })
        ->setQueryGraph([
            Model::UID,
            Model::ID
        ]);

        $preparedQuery = $query->prepare();
        $this->assertEquals(
            '{ dGraphQuery( func:eq(id,"test"))@filter( eq(ei_type,"conversation_template") and not has(has_opening_scene)){uid id }}',
            $preparedQuery
        );
    }

    public function testQueryWithRecurseNoDepth() {
        $query = new DGraphQuery();
        $query->uid("0xABCD")->recurse()->setQueryGraph([
            Model::UID,
            Model::UPDATE_OF
        ]);

        $preparedQuery = $query->prepare();
        $this->assertEquals(
            '{ dGraphQuery( func:uid(0xABCD))@recurse(loop:false){uid update_of }}',
            $preparedQuery
        );
    }

    public function testQueryWithRecurseWithDepth() {
        $query = new DGraphQuery();
        $query->uid("0xABCD")->recurse(true, 5)->setQueryGraph([
            Model::UID,
            Model::UPDATE_OF
        ]);

        $preparedQuery = $query->prepare();
        $this->assertEquals(
            '{ dGraphQuery( func:uid(0xABCD))@recurse(loop:true,depth:5){uid update_of }}',
            $preparedQuery
        );
    }
}
