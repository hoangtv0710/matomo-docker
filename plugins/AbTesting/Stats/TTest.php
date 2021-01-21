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




use Exception;

/**
 * Good for "normally distributed" data. Good for pageviews etc.
 */
class TTest
{
    const PRECISION = 6;

    public function getVariance($stdDev)
    {
        return round($stdDev * $stdDev, static::PRECISION);
    }

    public function getMean($sum, $counter)
    {
        if ($this->isZero($counter)) {
            $mean = 0;
        } else {
            $mean = $sum / $counter;
        }

        return round($mean, static::PRECISION);
    }

    private function flattenInputArray($arrayValue)
    {
        $total = array();
        foreach ($arrayValue as $entry) {
            if (is_array($entry)) {
                foreach ($entry as $e) {
                    $total[] = $e;
                }
            } else {
                $total[] = $entry;
            }
        }
        return $total;
    }

    public function flattenValues($sum, $counter, $stdDev)
    {
        if (is_array($sum)) {
            $sum = $this->flattenInputArray($sum);
        }
        if (is_array($counter)) {
            $counter = $this->flattenInputArray($counter);
        }
        if (is_array($stdDev)) {
            $stdDev = $this->flattenInputArray($stdDev);
        }

        if (is_array($stdDev)) {
            $stdDev = $this->getCompositeStandardDeviation($sum, $stdDev, $counter);
            $stdDev = round($stdDev, static::PRECISION);
        }

        if (is_array($sum)) {
            $sum = array_sum($sum);
        }

        if (is_array($counter)) {
            $counter = array_sum($counter);
        }

        return array($sum, $counter, $stdDev);
    }

    public function getSignificanceRate($sum1, $counter1, $stdDev1, $sum2, $counter2, $stdDev2)
    {
        $tValue = $this->getIndendentTvalue($sum1, $counter1, $stdDev1, $sum2, $counter2, $stdDev2);
        $df = $this->getDegreesOfFreedom($counter1, $counter2);

        return $this->getProbability($tValue, $df) * 100;
    }

    public function getStandardError($totalDegreeOfFreedom, $sampleStandardDeviation, $counter)
    {
        $variance = $this->getVariance($sampleStandardDeviation);

        if ($this->isZero($totalDegreeOfFreedom)) {
            $error = 0;
        } else {
            $error = (($counter - 1) / $totalDegreeOfFreedom) * $variance;
        }

        return round($error, static::PRECISION);
    }

    public function getIndendentTvalue($sum1, $counter1, $stdDev1, $sum2, $counter2, $stdDev2)
    {
        $mean1 = $this->getMean($sum1, $counter1);
        $mean2 = $this->getMean($sum2, $counter2);
        $diffOf2Means = abs($mean1 - $mean2);

        $df = $this->getDegreesOfFreedom($counter1, $counter2);

        $stError1 = $this->getStandardError($df, $stdDev1, $counter1);
        $stError2 = $this->getStandardError($df, $stdDev2, $counter2);

        $totalStandardError = $stError1 + $stError2;

        if ($this->isZero($counter1)) {
            $m1 = 0;
        } else {
            $m1 = $totalStandardError / $counter1;
        }

        if ($this->isZero($counter2)) {
            $m2 = 0;
        } else {
            $m2 = $totalStandardError / $counter2;
        }

        $stdTotal = $m1 + $m2;

        $standardError = sqrt($stdTotal);

        if ($this->isZero($standardError)) {
            $t = 0;
        } else {
            $t = $diffOf2Means / $standardError;
        }

        return round($t, self::PRECISION);
    }

    private function isZero($num)
    {
        return $num === 0 || $num === '0' || (double) 0 === $num || $num === false;
    }

    public function getDegreesOfFreedom($count1, $count2)
    {
        return $count1 + $count2 - 2;
    }

    public function getProbability($tValue, $degreesOfFreedom)
    {
        if ($degreesOfFreedom <= 0) {
            return 0;
        }

        $p0 = '0.50';
        $p1 = '0.70';
        $p2 = '0.80';
        $p3 = '0.90';
        $p4 = '0.95';
        $p5 = '0.98';
        $p6 = '0.99';
        $p7 = '0.995';
        $p8 = '0.998';
        $p9 = '0.999';

        // from https://www.medcalc.org/manual/t-distribution.php
        // and from http://www.sjsu.edu/faculty/gerstman/StatPrimer/t-table.pdf
        $table = array(
            1 => array('1.000' => $p0, '1.963' => $p1, '3.078' => $p2, '6.314' => $p3, '12.706' => $p4, '31.820' => $p5, '63.657' => $p6, '127.321' => $p7, '318.309' => $p8, '636.619' => $p9),
            2 => array('0.816' => $p0, '1.386' => $p1, '1.886' => $p2, '2.920' => $p3, '4.303' => $p4, '6.965' => $p5, '9.925' => $p6, '14.089' => $p7, '22.327' => $p8, '31.599' => $p9),
            3 => array('0.765' => $p0, '1.250' => $p1, '1.638' => $p2, '2.353' => $p3, '3.182' => $p4, '4.541' => $p5, '5.841' => $p6, '7.453' => $p7, '10.215' => $p8, '12.924' => $p9),
            4 => array('0.741' => $p0, '1.190' => $p1, '1.533' => $p2, '2.132' => $p3, '2.776' => $p4, '3.747' => $p5, '4.604' => $p6, '5.598' => $p7, '7.173' => $p8, '8.610' => $p9),
            5 => array('0.727' => $p0, '1.156' => $p1, '1.476' => $p2, '2.015' => $p3, '2.571' => $p4, '3.365' => $p5, '4.032' => $p6, '4.773' => $p7, '5.893' => $p8, '6.869' => $p9),
            6 => array('0.718' => $p0, '1.134' => $p1, '1.440' => $p2, '1.943' => $p3, '2.447' => $p4, '3.143' => $p5, '3.707' => $p6, '4.317' => $p7, '5.208' => $p8, '5.959' => $p9),
            7 => array('0.711' => $p0, '1.119' => $p1, '1.415' => $p2, '1.895' => $p3, '2.365' => $p4, '2.998' => $p5, '3.499' => $p6, '4.029' => $p7, '4.785' => $p8, '5.408' => $p9),
            8 => array('0.706' => $p0, '1.108' => $p1, '1.397' => $p2, '1.860' => $p3, '2.306' => $p4, '2.897' => $p5, '3.355' => $p6, '3.833' => $p7, '4.501' => $p8, '5.041' => $p9),
            9 => array('0.703' => $p0, '1.100' => $p1, '1.383' => $p2, '1.833' => $p3, '2.262' => $p4, '2.821' => $p5, '3.250' => $p6, '3.690' => $p7, '4.297' => $p8, '4.781' => $p9),
            10 => array('0.700' => $p0, '1.093' => $p1, '1.372' => $p2, '1.812' => $p3, '2.228' => $p4, '2.764' => $p5, '3.169' => $p6, '3.581' => $p7, '4.144' => $p8, '4.587' => $p9),
            11 => array('0.697' => $p0, '1.088' => $p1, '1.363' => $p2, '1.796' => $p3, '2.201' => $p4, '2.718' => $p5, '3.106' => $p6, '3.497' => $p7, '4.025' => $p8, '4.437' => $p9),
            12 => array('0.695' => $p0, '1.083' => $p1, '1.356' => $p2, '1.782' => $p3, '2.179' => $p4, '2.681' => $p5, '3.055' => $p6, '3.428' => $p7, '3.930' => $p8, '4.318' => $p9),
            13 => array('0.694' => $p0, '1.079' => $p1, '1.350' => $p2, '1.771' => $p3, '2.160' => $p4, '2.650' => $p5, '3.012' => $p6, '3.372' => $p7, '3.852' => $p8, '4.221' => $p9),
            14 => array('0.692' => $p0, '1.076' => $p1, '1.345' => $p2, '1.761' => $p3, '2.145' => $p4, '2.625' => $p5, '2.977' => $p6, '3.326' => $p7, '3.787' => $p8, '4.140' => $p9),
            15 => array('0.691' => $p0, '1.074' => $p1, '1.341' => $p2, '1.753' => $p3, '2.131' => $p4, '2.602' => $p5, '2.947' => $p6, '3.286' => $p7, '3.733' => $p8, '4.073' => $p9),
            16 => array('0.690' => $p0, '1.071' => $p1, '1.337' => $p2, '1.746' => $p3, '2.120' => $p4, '2.584' => $p5, '2.921' => $p6, '3.252' => $p7, '3.686' => $p8, '4.015' => $p9),
            17 => array('0.689' => $p0, '1.069' => $p1, '1.333' => $p2, '1.740' => $p3, '2.110' => $p4, '2.567' => $p5, '2.898' => $p6, '3.222' => $p7, '3.646' => $p8, '3.965' => $p9),
            18 => array('0.688' => $p0, '1.067' => $p1, '1.330' => $p2, '1.734' => $p3, '2.101' => $p4, '2.552' => $p5, '2.878' => $p6, '3.197' => $p7, '3.610' => $p8, '3.922' => $p9),
            19 => array('0.688' => $p0, '1.066' => $p1, '1.328' => $p2, '1.729' => $p3, '2.093' => $p4, '2.539' => $p5, '2.861' => $p6, '3.174' => $p7, '3.579' => $p8, '3.883' => $p9),
            20 => array('0.687' => $p0, '1.064' => $p1, '1.325' => $p2, '1.725' => $p3, '2.086' => $p4, '2.528' => $p5, '2.845' => $p6, '3.153' => $p7, '3.552' => $p8, '3.850' => $p9),
            21 => array('0.686' => $p0, '1.063' => $p1, '1.323' => $p2, '1.721' => $p3, '2.080' => $p4, '2.518' => $p5, '2.831' => $p6, '3.135' => $p7, '3.527' => $p8, '3.819' => $p9),
            22 => array('0.685' => $p0, '1.061' => $p1, '1.321' => $p2, '1.717' => $p3, '2.074' => $p4, '2.508' => $p5, '2.819' => $p6, '3.119' => $p7, '3.505' => $p8, '3.792' => $p9),
            23 => array('0.685' => $p0, '1.060' => $p1, '1.319' => $p2, '1.714' => $p3, '2.069' => $p4, '2.500' => $p5, '2.807' => $p6, '3.104' => $p7, '3.485' => $p8, '3.768' => $p9),
            24 => array('0.685' => $p0, '1.059' => $p1, '1.318' => $p2, '1.711' => $p3, '2.064' => $p4, '2.492' => $p5, '2.797' => $p6, '3.090' => $p7, '3.467' => $p8, '3.745' => $p9),
            25 => array('0.684' => $p0, '1.058' => $p1, '1.316' => $p2, '1.708' => $p3, '2.060' => $p4, '2.485' => $p5, '2.787' => $p6, '3.078' => $p7, '3.450' => $p8, '3.725' => $p9),
            26 => array('0.684' => $p0, '1.058' => $p1, '1.315' => $p2, '1.706' => $p3, '2.056' => $p4, '2.479' => $p5, '2.779' => $p6, '3.067' => $p7, '3.435' => $p8, '3.707' => $p9),
            27 => array('0.684' => $p0, '1.057' => $p1, '1.314' => $p2, '1.703' => $p3, '2.052' => $p4, '2.473' => $p5, '2.771' => $p6, '3.057' => $p7, '3.421' => $p8, '3.690' => $p9),
            28 => array('0.683' => $p0, '1.056' => $p1, '1.313' => $p2, '1.701' => $p3, '2.048' => $p4, '2.467' => $p5, '2.763' => $p6, '3.047' => $p7, '3.408' => $p8, '3.674' => $p9),
            29 => array('0.683' => $p0, '1.055' => $p1, '1.311' => $p2, '1.699' => $p3, '2.045' => $p4, '2.462' => $p5, '2.756' => $p6, '3.038' => $p7, '3.396' => $p8, '3.659' => $p9),
            30 => array('0.683' => $p0, '1.055' => $p1, '1.310' => $p2, '1.697' => $p3, '2.042' => $p4, '2.457' => $p5, '2.750' => $p6, '3.030' => $p7, '3.385' => $p8, '3.646' => $p9),
            31 => array('0.683' => $p0, '1.054' => $p1, '1.309' => $p2, '1.695' => $p3, '2.040' => $p4, '2.453' => $p5, '2.744' => $p6, '3.022' => $p7, '3.375' => $p8, '3.633' => $p9),
            32 => array('0.683' => $p0, '1.054' => $p1, '1.309' => $p2, '1.694' => $p3, '2.037' => $p4, '2.449' => $p5, '2.738' => $p6, '3.015' => $p7, '3.365' => $p8, '3.622' => $p9),
            33 => array('0.682' => $p0, '1.053' => $p1, '1.308' => $p2, '1.692' => $p3, '2.035' => $p4, '2.445' => $p5, '2.733' => $p6, '3.008' => $p7, '3.356' => $p8, '3.611' => $p9),
            34 => array('0.682' => $p0, '1.053' => $p1, '1.307' => $p2, '1.691' => $p3, '2.032' => $p4, '2.441' => $p5, '2.728' => $p6, '3.002' => $p7, '3.348' => $p8, '3.601' => $p9),
            35 => array('0.682' => $p0, '1.052' => $p1, '1.306' => $p2, '1.690' => $p3, '2.030' => $p4, '2.438' => $p5, '2.724' => $p6, '2.996' => $p7, '3.340' => $p8, '3.591' => $p9),
            36 => array('0.682' => $p0, '1.052' => $p1, '1.306' => $p2, '1.688' => $p3, '2.028' => $p4, '2.434' => $p5, '2.719' => $p6, '2.991' => $p7, '3.333' => $p8, '3.582' => $p9),
            37 => array('0.682' => $p0, '1.051' => $p1, '1.305' => $p2, '1.687' => $p3, '2.026' => $p4, '2.431' => $p5, '2.715' => $p6, '2.985' => $p7, '3.326' => $p8, '3.574' => $p9),
            38 => array('0.682' => $p0, '1.051' => $p1, '1.304' => $p2, '1.686' => $p3, '2.024' => $p4, '2.429' => $p5, '2.712' => $p6, '2.980' => $p7, '3.319' => $p8, '3.566' => $p9),
            39 => array('0.681' => $p0, '1.051' => $p1, '1.304' => $p2, '1.685' => $p3, '2.023' => $p4, '2.426' => $p5, '2.708' => $p6, '2.976' => $p7, '3.313' => $p8, '3.558' => $p9),
            40 => array('0.681' => $p0, '1.050' => $p1, '1.303' => $p2, '1.684' => $p3, '2.021' => $p4, '2.423' => $p5, '2.704' => $p6, '2.971' => $p7, '3.307' => $p8, '3.551' => $p9),
            42 => array('0.681' => $p0, '1.049' => $p1, '1.302' => $p2, '1.682' => $p3, '2.018' => $p4, '2.418' => $p5, '2.698' => $p6, '2.963' => $p7, '3.296' => $p8, '3.538' => $p9),
            44 => array('0.681' => $p0, '1.048' => $p1, '1.301' => $p2, '1.680' => $p3, '2.015' => $p4, '2.414' => $p5, '2.692' => $p6, '2.956' => $p7, '3.286' => $p8, '3.526' => $p9),
            46 => array('0.681' => $p0, '1.048' => $p1, '1.300' => $p2, '1.679' => $p3, '2.013' => $p4, '2.410' => $p5, '2.687' => $p6, '2.949' => $p7, '3.277' => $p8, '3.515' => $p9),
            48 => array('0.680' => $p0, '1.047' => $p1, '1.299' => $p2, '1.677' => $p3, '2.011' => $p4, '2.407' => $p5, '2.682' => $p6, '2.943' => $p7, '3.269' => $p8, '3.505' => $p9),
            50 => array('0.680' => $p0, '1.047' => $p1, '1.299' => $p2, '1.676' => $p3, '2.009' => $p4, '2.403' => $p5, '2.678' => $p6, '2.937' => $p7, '3.261' => $p8, '3.496' => $p9),
            60 => array('0.679' => $p0, '1.045' => $p1, '1.296' => $p2, '1.671' => $p3, '2.000' => $p4, '2.390' => $p5, '2.660' => $p6, '2.915' => $p7, '3.232' => $p8, '3.460' => $p9),
            70 => array('0.679' => $p0, '1.044' => $p1, '1.294' => $p2, '1.667' => $p3, '1.994' => $p4, '2.381' => $p5, '2.648' => $p6, '2.899' => $p7, '3.211' => $p8, '3.435' => $p9),
            80 => array('0.678' => $p0, '1.043' => $p1, '1.292' => $p2, '1.664' => $p3, '1.990' => $p4, '2.374' => $p5, '2.639' => $p6, '2.887' => $p7, '3.195' => $p8, '3.416' => $p9),
            90 => array('0.678' => $p0, '1.043' => $p1, '1.291' => $p2, '1.662' => $p3, '1.987' => $p4, '2.369' => $p5, '2.632' => $p6, '2.878' => $p7, '3.183' => $p8, '3.402' => $p9),
            100 => array('0.677' => $p0, '1.042' => $p1, '1.290' => $p2, '1.660' => $p3, '1.984' => $p4, '2.364' => $p5, '2.626' => $p6, '2.871' => $p7, '3.174' => $p8, '3.391' => $p9),
            120 => array('0.677' => $p0, '1.041' => $p1, '1.289' => $p2, '1.658' => $p3, '1.980' => $p4, '2.358' => $p5, '2.617' => $p6, '2.860' => $p7, '3.160' => $p8, '3.373' => $p9),
            150 => array('0.677' => $p0, '1.041' => $p1, '1.287' => $p2, '1.655' => $p3, '1.976' => $p4, '2.351' => $p5, '2.609' => $p6, '2.849' => $p7, '3.145' => $p8, '3.357' => $p9),
            200 => array('0.677' => $p0, '1.040' => $p1, '1.286' => $p2, '1.652' => $p3, '1.972' => $p4, '2.345' => $p5, '2.601' => $p6, '2.839' => $p7, '3.131' => $p8, '3.340' => $p9),
            300 => array('0.677' => $p0, '1.040' => $p1, '1.284' => $p2, '1.650' => $p3, '1.968' => $p4, '2.339' => $p5, '2.592' => $p6, '2.828' => $p7, '3.118' => $p8, '3.323' => $p9),
            500 => array('0.676' => $p0, '1.039' => $p1, '1.283' => $p2, '1.648' => $p3, '1.965' => $p4, '2.334' => $p5, '2.586' => $p6, '2.820' => $p7, '3.107' => $p8, '3.310' => $p9),
            // infinity:
            501 => array('0.675' => $p0, '1.037' => $p1, '1.282' => $p2, '1.645' => $p3, '1.960' => $p4, '2.326' => $p5, '2.576' => $p6, '2.807' => $p7, '3.090' => $p8, '3.291' => $p9));

        $row = array();
        if (isset($table[$degreesOfFreedom])) {
            $row = $table[$degreesOfFreedom];
        } else {
            foreach ($table as $df => $values) {
                if ($degreesOfFreedom >= $df) {
                    $row = $values;
                }
            }
        }

        $result = 0;

        foreach ($row as $area => $probability) {
            if ($tValue >= $area) {
                $result = $probability;
            }
        }

        return $result;
    }

    public function getCompositeStandardDeviation($sums, $standardDeviations, $numCounts)
    {
        $numEntries = count($sums);

        if ($numEntries != count($standardDeviations)) {
            throw new Exception('inconsistent list lengths');
        }

        if ($numEntries != count($numCounts)) {
            throw new Exception('wrong nCounts list length');
        }

        $means = array();
        for ($i = 0; $i < $numEntries; $i++) {
            if ($this->isZero($numCounts[$i])) {
                $means[$i] = 0;
            } else {
                $means[$i] = $sums[$i] / $numCounts[$i];
            }
        }

        // calculate total number of samples, N, and grand mean, GM
        $totalNumberOfSamples = array_sum($numCounts);

        if ($totalNumberOfSamples <= 1) {
            // Not enough participants, standard deviation cannot be calculated
            return 0;
        }

        $grandMean = 0.0;
        for ($i = 0; $i < $numEntries; $i++) {
            $grandMean += $means[$i] * $numCounts[$i];
        }
        $grandMean /= $totalNumberOfSamples;

        $errorSumOfSquares = 0.0;
        for ($i = 0; $i < $numEntries; $i++) {
            $errorSumOfSquares += (pow($standardDeviations[$i], 2)) * ($numCounts[$i] - 1);
        }

        $totalGroupSumOfSquares = 0.0;
        for ($i = 0; $i < $numEntries; $i++) {
            $totalGroupSumOfSquares += (pow($means[$i]-$grandMean, 2)) * $numCounts[$i];
        }

        // calculate standard deviation as square root of grand variance
        $result = sqrt(($errorSumOfSquares+$totalGroupSumOfSquares)/($totalNumberOfSamples-1));

        return $result;
    }
}
