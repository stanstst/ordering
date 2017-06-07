<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 11:57
 */

namespace tests\domain\order;

use app\domain\order\ColumnFilter;
use app\domain\order\ListFilter;
use PHPUnit\Framework\TestCase;
use yii\db\ActiveQuery;

class ColumnFilterTest extends TestCase
{
    private $columnName = 'column';
    private $columnValue = '123';
    /**
     * @var ColumnFilter
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

        $this->object = new ColumnFilter($this->columnName);
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
    }

    /**
     * @testtt
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
    }
}
