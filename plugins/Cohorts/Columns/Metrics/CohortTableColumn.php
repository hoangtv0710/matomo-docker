<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

namespace Piwik\Plugins\Cohorts\Columns\Metrics;

use Piwik\Piwik;
use Piwik\Plugin\Metric;

class CohortTableColumn extends Metric
{
    private $period;
    private $index;

    public function __construct($period, $index)
    {
        $this->period = $period;
        $this->index = $index;
    }

    public function getName()
    {
        return 'Cohorts_' . $this->period . $this->index;
    }

    public function getTranslatedName()
    {
        return ucfirst(Piwik::translate('Intl_Period' . ucfirst($this->period))) . ' ' . $this->index;
    }

    public function getDocumentation()
    {
        if ($this->index == 0) {
            return Piwik::translate('Cohorts_ColumnDocumentationCohortPeriod')
                . ' ' . Piwik::translate('Cohorts_ColumnDocumentation2');
        }

        if ($this->index > 1) {
            $period = $period = Piwik::translate('Intl_Period' . ucfirst($this->period) . 's');
        } else {
            $period = Piwik::translate('Intl_Period' . ucfirst($this->period));
        }

        return Piwik::translate('Cohorts_ColumnDocumentation1', [$this->index, $period])
            . ' ' . Piwik::translate('Cohorts_ColumnDocumentation2');
    }
}