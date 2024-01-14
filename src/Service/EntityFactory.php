<?php

namespace App\Service;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;

class EntityFactory
{
    /**
     * Creates Review instance.
     *
     * @return Review
     */
    public function createReview(): Review
    {
        return new Review();
    }

    /**
     * Creates FailureReport instance.
     *
     * @return FailureReport
     */
    public function createFailureReport(): FailureReport
    {
        return new FailureReport();
    }
}
