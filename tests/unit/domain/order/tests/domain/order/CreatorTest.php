<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 06/06/17
 * Time: 10:43
 */

namespace tests\domain\order;

use app\domain\order\ListDataProvider;
use app\domain\order\ViewModel;
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
     * @var ViewModel
     */
    private $view;

    /**
     * @var Creator
     */
    private $object;

    public function setUp()
    {

        $this->dataProviderMock = $this->getMockBuilder(ListDataProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->view = new ViewModel();

        $this->object = new Creator($this->dataProviderMock, $this->view);
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

        $this->assertSame($this->view, $actualView);
        $this->assertSame($activeDataProvider, $this->view->dataProvider);
    }
}
