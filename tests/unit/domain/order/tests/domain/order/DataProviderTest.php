<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 18:29
 */

namespace tests\domain\order;

use app\domain\order\ListDataProvider;
use app\domain\order\ListFilter;
use PHPUnit\Framework\TestCase;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\QueryBuilder;

class DataProviderTest extends TestCase
{
    /**
     * @var ActiveQuery | \PHPUnit_Framework_MockObject_MockObject | QueryBuilder
     */
    private $queryMock;
    /**
     * @var ListDataProvider
     */
    private $object;

    /**
     * @var ListFilter | \PHPUnit_Framework_MockObject_MockObject
     */
    private $filterMock;

    public function setUp()
    {
        parent::setUp();

        $this->queryMock = $this->getMockBuilder(ActiveQuery::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->queryMock->expects($this->any())
            ->method('with')
            ->willReturnSelf();
        $this->queryMock->expects($this->any())
            ->method('from')
            ->willReturnSelf();
        $this->queryMock->expects($this->any())
            ->method('andWhere')
            ->willReturnSelf();

        $this->filterMock = $this->getMockBuilder(ListFilter::class)
            ->getMock();
        $this->object = new ListDataProvider($this->queryMock, [$this->filterMock]);
    }

    /**
     * @test
     */
    public function addsRelationsToQuery()
    {
        $this->queryMock->expects($this->once())
            ->method('with')
            ->with(['user', 'product']);
        $this->object->get(['request']);
    }

    /**
     * @test
     */
    public function returnsActiveDataProviderWithInjectedQuery()
    {
        $actualActiveDataProvider = $this->object->get(['request']);
        $this->assertInstanceOf(ActiveDataProvider::class, $actualActiveDataProvider);
        $this->assertEquals($this->queryMock, $actualActiveDataProvider->query);
    }

    /**
     * @test
     */
    public function callsApplyOnListFilters()
    {
        $this->filterMock->expects($this->once())
            ->method('apply')
            ->with($this->queryMock, ['request']);

        $this->object->get(['request']);
    }
}
