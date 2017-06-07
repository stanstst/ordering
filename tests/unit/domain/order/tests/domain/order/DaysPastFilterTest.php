<?php
/**
 * Created by PhpStorm.
 * User: stan
 * Date: 07/06/17
 * Time: 11:57
 */

namespace tests\domain\order;

use app\domain\order\DaysPastFilter;
use app\domain\order\ListFilter;
use app\domain\order\ViewModel;
use PHPUnit\Framework\TestCase;
use yii\db\ActiveQuery;

class DaysPastFilterTest extends TestCase
{
    private $viewModel;
    /**
     * @var DaysPastFilter
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
        $this->object = new DaysPastFilter($this->viewModel);
    }

    /**
     * @test
     */
    public function addsWhereClauseIfPastDaysArePresentInRequest()
    {

        $request = [
            ListFilter::LIST_FILTER_REQUEST_KEY =>
                [
                    DaysPastFilter::FILTER_KEY => '3'
                ]
        ];
        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with('(DATEDIFF(CURDATE(), dateCreated)) <= 3');

        $this->object->apply($this->queryMock, $request);

        $this->assertEquals([DaysPastFilter::FILTER_KEY => 3], $this->viewModel->filtersState);
    }

    /**
     * @test
     */
    public function doesNotAddWhereIfFilterDaysIsMissingInRequest()
    {
        $this->queryMock->expects($this->never())
            ->method('andWhere');
        $this->object->apply($this->queryMock, []);
        $this->object->apply($this->queryMock, [ListFilter::LIST_FILTER_REQUEST_KEY => ['otherKey' => '123']]);

        $this->assertEquals([], $this->viewModel->filtersState);
    }

    /**
     * @test
     */
    public function doesNotAddWhereIfFilterDaysIsInvalidInRequest()
    {
        $this->queryMock->expects($this->never())
            ->method('andWhere');

        $request = [
            ListFilter::LIST_FILTER_REQUEST_KEY =>
                [
                    DaysPastFilter::FILTER_KEY => 'non-integer'
                ]
        ];
        $this->object->apply($this->queryMock, $request);

        $this->assertEquals([], $this->viewModel->filtersState);
    }
}
