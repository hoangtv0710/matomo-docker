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

namespace Piwik\Plugins\AbTesting\Stats;

/**
 * Good for conversions that can be only converted once (or boune rate). The data should come from a binomial
 * distribution.
 */
class ChiSquare
{
    const PRECISION = 6;

    public function getZscore($controlVisits, $controlConversions, $experimentVisits, $experimentConversions)
    {
        $eConversionRate = $this->getConversionRate($experimentConversions, $experimentVisits);
        $cConversionRate = $this->getConversionRate($controlConversions, $controlVisits);

        if ($eConversionRate < $cConversionRate) {
            $tmp = $eConversionRate;
            $eConversionRate = $cConversionRate;
            $cConversionRate = $tmp;

            $tmp = $experimentVisits;
            $experimentVisits = $controlVisits;
            $controlVisits = $tmp;
        }

        if ($this->isZero($experimentVisits) || $this->isZero($controlVisits)) {
            return 0;
        }

        $z = $eConversionRate - $cConversionRate;

        $s = ($eConversionRate * (1 - $eConversionRate)) / $experimentVisits +
            ($cConversionRate * (1 - $cConversionRate)) / $controlVisits;

        if ($this->isZero($s)) {
            return 0;
        }

        $zScore = $z / sqrt($s);

        return round($zScore, static::PRECISION);
    }

    private function isZero($num)
    {
        return $num === 0 || $num === '0' || (double) 0 === $num || $num === false;
    }

    public function getSignificanceRate($controlVisits, $controlConversions, $experimentVisits, $experimentConversions)
    {
        $zScore = $this->getZscore($controlVisits, $controlConversions, $experimentVisits, $experimentConversions);
        $confidence = $this->cumulativeNormalDistribution($zScore);

        return round($confidence * 100, 4);
    }

    public function getConversionRate($conversions, $visits)
    {
        if ($visits == 0) {
            return 0;
        }

        return round($conversions / $visits, static::PRECISION);
    }

    public function cumulativeNormalDistribution($zScore)
    {
        $b1 = 0.319381530;
        $b2 = -0.356563782;
        $b3 = 1.781477937;
        $b4 = -1.821255978;
        $b5 = 1.330274429;
        $p = 0.2316419;
        $c = 0.39894228;

        if ($zScore >= 0.0) {
            $t = 1.0 / ( 1.0 + $p * $zScore );
            return (1.0 - $c * exp((-1 * $zScore) * $zScore / 2.0) * $t *
                ( $t * ( $t * ( $t * ( $t * $b5 + $b4 ) + $b3 ) + $b2 ) + $b1 ));
        } else {
            $t = 1.0 / ( 1.0 - $p * $zScore );
            return ( $c * exp(-$zScore * $zScore / 2.0) * $t *
                ( $t * ( $t * ( $t * ( $t * $b5 + $b4 ) + $b3 ) + $b2 ) + $b1 ));
        }
    }

}
