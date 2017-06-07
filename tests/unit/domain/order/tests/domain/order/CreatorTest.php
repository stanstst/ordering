<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 10:43
 */

namespace tests\domain\order;

use app\components\AssocEntityRepo;
use app\domain\order\ListDataProvider;
use app\domain\order\ViewModel;
use app\models\Product;
use app\models\User;
use \PHPUnit\Framework\TestCase;

use app\domain\order\Creator;
use yii\data\ActiveDataProvider;

class CreatorTest extends TestCase
{
    /**
     * @var ListDataProvider | \PHPUnit_Framework_MockObject_MockObject
     */
    private $dataProviderMock;
    /**
     * @var Creator
     */
    private $object;
    /**
     * @var AssocEntityRepo | \PHPUnit_Framework_MockObject_MockObject
     */
    private $entityRepoMock;

    public function setUp()
    {

        $this->dataProviderMock = $this->getMockBuilder(ListDataProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->entityRepoMock = $this->getMockBuilder(AssocEntityRepo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->object = new Creator($this->dataProviderMock, $this->entityRepoMock, new ViewModel());
        parent::setUp();
    }

    /**
     * @test
     */
    public function loadRecordsAddsActiveDataProviderInView()
    {
        $activeDataProvider = new ActiveDataProvider();
        $this->dataProviderMock->expects($this->once())
            ->method('get')
            ->willReturn($activeDataProvider);

        $actualView = $this->object->loadRecords([]);

        $this->assertInstanceOf(ViewModel::class, $actualView);
        $this->assertSame($activeDataProvider, $actualView->dataProvider);
    }

    /**
     * @test
     */
    public function loadRecordsAddsProductsInView()
    {
        $expectedProducts = ['product1', 'product2'];
        $expectedUsers = ['user1', 'user2'];
        $this->entityRepoMock->expects($this->exactly(2))
            ->method('getList')
            ->withConsecutive([Product::tableName(), ['id', 'name']],
                [User::tableName(), ['id', 'firstName', 'lastName']])
            ->willReturnOnConsecutiveCalls($expectedProducts, $expectedUsers);

        $actualView = $this->object->loadRecords([]);

        $this->assertInstanceOf(ViewModel::class, $actualView);
        $this->assertEquals($expectedProducts, $actualView->products);
    }
}
