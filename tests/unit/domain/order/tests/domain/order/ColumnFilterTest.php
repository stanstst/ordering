<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 11:57
 */

namespace tests\domain\order;

use app\domain\order\IntegerColumnFilter;
use app\domain\order\ListFilter;
use app\domain\order\ViewModel;
use PHPUnit\Framework\TestCase;
use yii\db\ActiveQuery;

class ColumnFilterTest extends TestCase
{
    private $columnName = 'column';
    private $columnValue = '123';
    /**
     * @var ViewModel
     */
    private $viewModel;
    /**
     * @var IntegerColumnFilter
     */
    private $object;

    /**
     * @var ActiveQuery | \PHPUnit_Framework_MockObject_MockObject
     */
    private $queryMock;

    public function setUp()
    {
        parent::setUp();
        $this->queryMock = $this->getMockBuilder(ActiveQuery::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->viewModel = new ViewModel();

        $this->object = new IntegerColumnFilter($this->columnName, $this->viewModel);
    }

    /**
     * @test
     */
    public function addsWhereClauseIfColumnIsPresentInRequest()
    {
        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with([$this->columnName => $this->columnValue]);

        $request = [
            ListFilter::LIST_FILTER_REQUEST_KEY =>
                [
                    $this->columnName => $this->columnValue
                ]
        ];

        $this->object->apply($this->queryMock, $request);

        $this->assertEquals([$this->columnName => (integer)$this->columnValue], $this->viewModel->filtersState);
    }

    /**
     * @test
     */
    public function doesNotAddWhereIfFilterColumnMissingInRequest()
    {
        $this->queryMock->expects($this->never())
            ->method('andWhere');

        $request = [
            ListFilter::LIST_FILTER_REQUEST_KEY =>
                [
                    'other-columnName' => $this->columnValue
                ]
        ];

        $this->object->apply($this->queryMock, $request);
        $this->assertEquals([], $this->viewModel->filtersState);
    }
}
