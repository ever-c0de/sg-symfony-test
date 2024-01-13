<?php

namespace App\Service;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;

class EntityFactory
{
    public function createReview(): Review
    {
        return new Review();
    }

    public function createFailureReport(): FailureReport
    {
        return new FailureReport();
    }
}
