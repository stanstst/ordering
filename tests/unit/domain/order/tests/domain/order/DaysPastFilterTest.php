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
use PHPUnit\Framework\TestCase;
use yii\db\ActiveQuery;

class DaysPastFilterTest extends TestCase
{
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

        $this->object = new DaysPastFilter();
    }

    /**
     * @test
     */
    public function addsWhereClauseIfPastDaysArePresentInRequest()
    {
        $request[ListFilter::LIST_FILTER_REQUEST_KEY][DaysPastFilter::FILTER_KEY] = 3;
        $this->queryMock->expects($this->once())
            ->method('andWhere')
            ->with('(DATEDIFF(CURDATE(), dateCreated)) < 3');

        $this->object->apply($this->queryMock, $request);
    }

    /**
     * @test
     */
    public function doesNotAddWhereIfFilterDaysIsMissingInRequest() {
        $this->queryMock->expects($this->never())
            ->method('andWhere');
        $this->object->apply($this->queryMock, []);
        $this->object->apply($this->queryMock, [ListFilter::LIST_FILTER_REQUEST_KEY => ['otherKey' => 123]]);
    }
}
