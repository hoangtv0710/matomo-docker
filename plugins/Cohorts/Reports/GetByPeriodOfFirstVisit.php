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

namespace Piwik\Plugins\Cohorts\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugins\CoreHome\Columns\Metrics\ActionsPerVisit;
use Piwik\Plugins\CoreHome\Columns\Metrics\AverageTimeOnSite;
use Piwik\Plugins\CoreHome\Columns\Metrics\BounceRate;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

// just for formatting metrics
class GetByPeriodOfFirstVisit extends Report
{
    protected function init()
    {
        parent::init();

        $this->categoryId = 'General_Visitors';
        $this->subcategoryId = 'Cohorts_Cohorts';
        $this->name = Piwik::translate('Cohorts_VisitsByPeriodOfFirstVisit');
        $this->processedMetrics = [
            new BounceRate(),
            new ActionsPerVisit(),
            new AverageTimeOnSite(),
        ];
        $this->metrics = GetCohorts::getAvailableCohortsMetrics($includeTemporary = false);
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        // empty, no widget
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        // empty
    }
}
