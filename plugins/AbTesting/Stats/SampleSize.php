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

class SampleSize
{
    const PRECISION = 6;

    /**
     * should be typically 0.8 or hgiher
     * the higher the more "accurate" and the more samples will be needed
     * @var float
     */
    const POWER_LEVEL = 0.8;

    const DEFAULT_PAGEVIEW_CONVERSIONRATE = 25;

    public function estimateForPageviews($desiredSignificanceRate, $minimumDetectableEffectAbsolute)
    {
        $conversionRate = self::DEFAULT_PAGEVIEW_CONVERSIONRATE;

        return $this->estimateForConversions($desiredSignificanceRate, $conversionRate, $minimumDetectableEffectAbsolute);
    }

    private function convertSignificanceRateToSignficianceLevel($significanceRate)
    {
        $rate = $significanceRate / 100;  // eg 99.5 = 0.995   or 95% = 0.95

        return 1 - $rate; // eg 0.005  or 0.05
    }

    private function convertConversionRateToP($conversionRate)
    {
        $rate = $conversionRate / 100;  // eg 20% conversion rate = 0.2

        return $rate;
    }

    /**
     * Estimates number of visits per variation.
     *
     * @param float $desiredSignificanceRate   eg 95 for 95%
     * @param float $conversionRate            eg 20 for 20% baseline conversion rate
     * @param float $minimumDetectableEffectAbsolute        eg 6 for 6% absolute minimum detectable effect
     * @return float
     */
    public function estimateForConversions($desiredSignificanceRate, $conversionRate, $minimumDetectableEffectAbsolute)
    {
        $significaneLevelAlpha = $this->convertSignificanceRateToSignficianceLevel($desiredSignificanceRate);
        $p = $this->convertConversionRateToP($conversionRate);
        $delta = $this->convertConversionRateToP($minimumDetectableEffectAbsolute);

        $tAlpha = $this->ppNormalDistribution(1 - ($significaneLevelAlpha / 2));
        $tBeta = $this->ppNormalDistribution(static::POWER_LEVEL);

        $sd1 = sqrt(2 * $p * (1.0 - $p));
        $sd2 = sqrt($p * (1.0 - $p) + ($p + $delta) * (1.0 - $p - $delta));

        $variance = ($delta * $delta);

        if ($variance === 0 || $variance === '0' || $variance === (double) 0) {
            return 0;
        }

        $visits = ($tAlpha * $sd1 + $tBeta * $sd2) * ($tAlpha * $sd1 + $tBeta * $sd2) / $variance;

        return (int) ceil($visits);
    }

    /**
     * DO NOT USE. Only keeping it for reference for now. Gives very low visits when conversion rate is about 50%.
     * from http://vuurr.com/split-testing-determine-sample-size/
     * @param $desiredSignificanceRate
     * @param $total1
     * @param $converted1
     * @param $total2
     * @param $converted2
     * @return int
     */
    public function estimateForConversionsByLookingIntoData($desiredSignificanceRate, $total1, $converted1, $total2, $converted2)
    {
        // calculate z value for two tailed test.
        $desiredSignificanceRate = ((100 - $desiredSignificanceRate) / 2) + $desiredSignificanceRate;
        $z = $this->ppNormalDistribution(($desiredSignificanceRate / 100));

        $conversionRate1 = $converted1 / $total1;
        $conversionRate2 = $converted2 / $total2;
        $E = abs($conversionRate2 - $conversionRate1);

        $t = ($converted1 + $converted2) / ($total1 + $total2);
        $stdDev = $this->ppNormalDistribution(1 - $t);

        $z = pow(($z * $stdDev) / $E, 2);

        return (int) ceil($z);
    }

    private function ppNormalDistribution($p)
    {
        $a0 = 2.50662823884;
        $a1 = -18.61500062529;
        $a2 = 41.39119773534;
        $a3 = -25.44106049637;
        $b1 = -8.47351093090;
        $b2 = 23.08336743743;
        $b3 = -21.06224101826;
        $b4 = 3.13082909833;
        $c0 = -2.78718931138;
        $c1 = -2.29796479134;
        $c2 = 4.85014127135;
        $c3 = 2.32121276858;
        $d1 = 3.54388924762;
        $d2 = 1.63706781897;
        $r = null;
        $split = 0.42;
        $value = null;

        if (abs($p - 0.5) <= $split) {

            /**
             *  0.08 < P < 0.92
             **/
            $r = ($p - 0.5) * ($p - 0.5);
            $value = ($p - 0.5) * ((($a3 * $r + $a2) * $r + $a1) * $r + $a0) /
                        (((($b4   * $r + $b3) * $r + $b2) * $r + $b1) * $r + 1.0);

        } elseif (0.0 < $p && $p < 1.0) {

            /**
             * P < 0.08 || P > 0.92,
             *  R = min (P, 1-P)
             **/

            if (0.5 < $p) {
                $r = sqrt (- log (1.0 - $p));
            } else {
                $r = sqrt (- log ($p));
            }

            $value = ((($c3 * $r + $c2) * $r + $c1) * $r + $c0) / (($d2 * $r + $d1) * $r + 1.0);

            if ($p < 0.5) {
                $value = - $value;
            }

        } else {
            /*+
             *  P <= 0.0 || 1.0 <= P
             **/
            $value = null;
        }

        return $value;
    }

}
