<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 18:29
 */

namespace tests\domain\order;

use app\domain\order\DataProvider;
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
     * @var DataProvider
     */
    private $object;

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

        $this->object = new DataProvider($this->queryMock);
    }

    /**
     * @test
     */
    public function addsRelationsToQuery()
    {
        $this->queryMock->expects($this->once())
            ->method('with')
            ->with(['user', 'product']);
        $this->object->get();
    }

    /**
     * @test
     */
    public function returnsActiveDataProviderWithInjectedQuery()
    {
        $actualActiveDataProvider = $this->object->get();
        $this->assertInstanceOf(ActiveDataProvider::class, $actualActiveDataProvider);
        $this->assertEquals($this->queryMock, $actualActiveDataProvider->query);
    }
}
