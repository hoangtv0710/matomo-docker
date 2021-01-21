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

namespace Piwik\Plugins\Cohorts\Visualizations\Cohorts;

use Piwik\Common;
use Piwik\Metrics;
use Piwik\Plugin\ThemeStyles;
use Piwik\Plugins\Cohorts\Reports\GetCohorts;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable\Config as VisualizationConfig;


class Config extends VisualizationConfig
{
    /**
     * @var string
     */
    public $cell_background_color_min = '#fff';

    /**
     * @var string
     */
    public $cell_background_color_max; // set in constructor

    /**
     * @var string
     */
    public $cell_text_color = '#1a1a1a';

    /**
     * @var string
     */
    public $cell_text_color_contrast = '#fff';

    /**
     * @var string[]
     */
    public $selectable_metrics = [];

    /**
     * @var int[]
     */
    public $datatable_row_limits = [
        5,
        10,
        15,
        20,
        25,
        30,
    ];

    public function __construct()
    {
        parent::__construct();

        $this->show_limit_control = true;
        $this->show_pagination_control = false;
        $this->show_search = false;
        $this->datatable_js_type = 'CohortsDataTable';
        $this->selectable_metrics = $this->getAvailableCohortMetrics();
        $this->disable_row_evolution = true;

        $themeStyles = ThemeStyles::get();
        $this->cell_background_color_max = $themeStyles->colorHeaderBackground;

        $period = Common::getRequestVar('period');
        if ($period == 'range') {
            $this->show_limit_control = false;
        }

        $this->addPropertiesThatShouldBeAvailableClientSide([
            'cell_background_color_min',
            'cell_background_color_max',
            'cell_text_color_min',
            'cell_text_color_max',
            'selectable_metrics',
            'datatable_row_limits',
        ]);
    }

    private function getAvailableCohortMetrics()
    {
        $result = [];
        foreach (GetCohorts::getAvailableCohortsMetricsTranslations() as $metric => $translation) {
            $result[] = [
                'column' => $metric,
                'translation' => $translation,
            ];
        }
        return $result;
    }
}