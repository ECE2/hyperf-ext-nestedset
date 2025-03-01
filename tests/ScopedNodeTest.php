<?php

namespace HyperfTest\HyperfExtNestedset;

use Ece2\HyperfExtNestedset\NestedSet;
use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Database\Model\Events\Deleting;
use Hyperf\Database\Model\Events\Restored;
use Hyperf\Database\Model\Events\Restoring;
use Hyperf\Database\Model\Events\Saving;
use Hyperf\Database\Model\Register;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use HyperfTest\HyperfExtNestedset\Model\MenuItem;
use HyperfTest\HyperfExtNestedset\Stub\ContainerStub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

class ScopedNodeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ContainerStub::getContainer();

        Schema::dropIfExists('menu_items');

        Schema::create('menu_items', function (\Hyperf\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id');
            $table->string('title')->nullable();
            NestedSet::columns($table);
        });
    }

    public function setUp(): void
    {
        $container = $this->getContainer();

        $data = include __DIR__ . '/data/menu_items.php';

        Db::table('menu_items')->insert($data);

        MenuItem::resetActionsPerformed();
    }

    public function tearDown(): void
    {
        Db::table('menu_items')->truncate();
    }

    protected function getContainer()
    {
        $dispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->with(\Mockery::any())->andReturnUsing(function ($event) {
            if (in_array(get_class($event), [Saving::class, Deleting::class, Deleted::class, Restoring::class, Restored::class])) {
                $event->handle();
            }
        });
        $container = ContainerStub::getContainer(function ($conn) use ($dispatcher) {
            $conn->setEventDispatcher($dispatcher);
        });
        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturn($dispatcher);

        Register::setEventDispatcher($dispatcher);

        return $container;
    }

    public function assertTreeNotBroken($menuId)
    {
        $this->assertFalse(MenuItem::scoped(['menu_id' => $menuId])->isBroken());
    }

    public function testNotBroken()
    {
        $this->assertTreeNotBroken(1);
        $this->assertTreeNotBroken(2);
    }

    public function testMovingNodeNotAffectingOtherMenu()
    {
        $node = MenuItem::where('menu_id', '=', 1)->first();

        $node->down();

        $node = MenuItem::where('menu_id', '=', 2)->first();

        $this->assertEquals(1, $node->getLft());
    }

    public function testScoped()
    {
        $node = MenuItem::scoped(['menu_id' => 2])->first();

        $this->assertEquals(3, $node->getKey());
    }

    public function testSiblings()
    {
        $node = MenuItem::find(1);

        $result = $node->getSiblings();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());

        $result = $node->getNextSiblings();

        $this->assertEquals(2, $result->first()->getKey());

        $node = MenuItem::find(2);

        $result = $node->getPrevSiblings();

        $this->assertEquals(1, $result->first()->getKey());
    }

    public function testDescendants()
    {
        $node = MenuItem::find(2);

        $result = $node->getDescendants();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(5, $result->first()->getKey());

        $node = MenuItem::scoped(['menu_id' => 1])->with('descendants')->find(2);

        $result = $node->descendants;

        $this->assertEquals(1, $result->count());
        $this->assertEquals(5, $result->first()->getKey());
    }

    public function testAncestors()
    {
        $node = MenuItem::find(5);

        $result = $node->getAncestors();

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());

        $node = MenuItem::scoped(['menu_id' => 1])->with('ancestors')->find(5);

        $result = $node->ancestors;

        $this->assertEquals(1, $result->count());
        $this->assertEquals(2, $result->first()->getKey());
    }

    public function testDepth()
    {
        $node = MenuItem::scoped(['menu_id' => 1])->withDepth()->where('id', '=', 5)->first();

        $this->assertEquals(1, $node->depth);

        $node = MenuItem::find(2);

        $result = $node->children()->withDepth()->get();

        $this->assertEquals(1, $result->first()->depth);
    }

    public function testSaveAsRoot()
    {
        $node = MenuItem::find(5);

        $node->saveAsRoot();

        $this->assertEquals(5, $node->getLft());
        $this->assertEquals(null, $node->parent_id);

        $this->assertOtherScopeNotAffected();
    }

    public function testInsertion()
    {
        $node = MenuItem::create(['menu_id' => 1, 'parent_id' => 5]);

        $this->assertEquals(5, $node->parent_id);
        $this->assertEquals(5, $node->getLft());

        $this->assertOtherScopeNotAffected();
    }

    public function testInsertionToParentFromOtherScope()
    {
        $this->expectException(\Hyperf\Database\Model\ModelNotFoundException::class);

        $node = MenuItem::create(['menu_id' => 2, 'parent_id' => 5]);
    }

    public function testDeletion()
    {
        $node = MenuItem::find(2)->delete();

        $node = MenuItem::find(1);

        $this->assertEquals(2, $node->getRgt());

        $this->assertOtherScopeNotAffected();
    }

    public function testMoving()
    {
        $node = MenuItem::find(1);
        $this->assertTrue($node->down());

        $this->assertOtherScopeNotAffected();
    }

    protected function assertOtherScopeNotAffected()
    {
        $node = MenuItem::find(3);

        $this->assertEquals(1, $node->getLft());
    }

    // Commented, cause there is no assertion here and otherwise the test is marked as risky in PHPUnit 7.
    // What's the purpose of this method? @todo: remove/update?
    /*public function testRebuildsTree()
    {
        $data = [];
        MenuItem::scoped([ 'menu_id' => 2 ])->rebuildTree($data);
    }*/

    public function testAppendingToAnotherScopeFails()
    {
        $this->expectException(\LogicException::class);

        $a = MenuItem::find(1);
        $b = MenuItem::find(3);

        $a->appendToNode($b)->save();
    }

    public function testInsertingBeforeAnotherScopeFails()
    {
        $this->expectException(\LogicException::class);

        $a = MenuItem::find(1);
        $b = MenuItem::find(3);

        $a->insertAfterNode($b);
    }
}
