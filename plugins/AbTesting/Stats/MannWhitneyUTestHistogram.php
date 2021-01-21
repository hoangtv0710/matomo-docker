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

// Does not maky any assumptions about the distribution. Good for eg revenue etc.
class MannWhitneyUTestHistogram
{
    const PRECISION = 6;

    public function getSumOfRanks($samples1, $samples2)
    {
        $unranked = $this->getUnrankedValues($samples1, $samples2);

        $ranked = $this->rank($unranked);

        $sum = 0;
        foreach ($ranked as $rank) {
            $sum += $rank['rank'];
        }

        return $sum;
    }

    public function getSumOfRanksForSamples($samples1, $samples2, $useFirst = true)
    {
        $unranked = $this->getUnrankedValues($samples1, $samples2);
        $ranked = $this->rank($unranked);

        if ($useFirst) {
            return $this->getRankForSample($ranked, $samples1);
        }

        return $this->getRankForSample($ranked, $samples2);
    }

    public function rank($list)
    {
        // sort in ascending order
        usort($list, function($a, $b) {
            if ($a['val'] == $b['val']) {
                return 0;
            }

            if ($b['val'] > $a['val']) {
                return -1;
            }

            return 1;
        });

        $prevSum = 0;
        $totalCount = 0;

        $list = array_map(function ($item) use (&$prevSum, &$totalCount) {
            $totalCount = $item['count'] + $totalCount;
            $sum = ($totalCount * ($totalCount+1)) / 2;

            $rank = $sum - $prevSum;

            $prevSum = $sum;

            $item['rank'] = $rank;

            return $item;
        }, $list);

        return $list;
    }
    
    // Compute the rank of a sample, given a ranked
    // list and a list of observations for that sample.
    private function getRankForSample($rankedList, $observations)
    {
        // Compute the rank
        $rank = 0;
        foreach ($rankedList as $item) {
            $val = $item['val'];
            if (isset($observations[$val])) {
                $count = $observations[$val];

                if (!$this->isZero($item['count'])) {
                    // Add the rank to the sum
                    $rank += ($item['rank'] / $item['count']) * $count;
                }

                unset($observations[$val]);
            }
        }
    
        return $rank;
    }

    private function isZero($num)
    {
        return $num === 0 || $num === '0' || (double) 0 === $num || $num === false;
    }

    // Compute the U value of a sample,
    // given the rank and the list of observations
    // for that sample.
    private function uValue($rank, $observations) {
        $k = array_sum($observations);
        return $rank - (($k * ($k+1)) / 2);
    }
    
    // Check the U values are valid.
    // This utilises a property of the Mann-Whitney U test
    // that ensures the sum of the U values equals the product
    // of the number of observations.
    public function checkUisValid($u, $samples1, $samples2) {
        return ($u[0] + $u[1]) == (array_sum($samples1) * array_sum($samples2));
    }

    public function getStandardDeviation($sample1, $sample2)
    {
        $n1 = array_sum($sample1);
        $n2 = array_sum($sample2);

        $n = $n1 + $n2;

        if ($n < 2) {
            return 0;
        }

        $counts = $this->mergeSamples($sample1, $sample2);
        $ties = array_filter($counts, function ($sample) {
            return $sample > 1;
        });
        $ties = array_values($ties);
        $k = count($ties);

        // Compute correction
        $correction = 0;
        for ($i = 0; $i < $k; $i++) {
            $correction += (pow($ties[$i],3) - $ties[$i]) / ($n * ($n - 1));
        }

        // Compute standard deviation using correction for ties
        $stddev = sqrt((($n1 * $n2)/12) * (($n + 1) - $correction));

        return round($stddev, static::PRECISION);
    }

    public function getZscore($u, $sample1, $sample2) {
        $count1 = array_sum($sample1);
        $count2 = array_sum($sample2);

        $mu = ($count1 * $count2) / 2;
        $std = $this->getStandardDeviation($sample1, $sample2);

        if ($this->isZero($std)) {
            return 0;
        }

        $uBig = min($u);
        $z = abs((abs($uBig - $mu) - 0.5) / $std);

        return round($z, static::PRECISION);
    }
    
    private function erf($x) {
        $cof = array(-1.3026537197817094, 6.4196979235649026e-1, 1.9476473204185836e-2, -9.561514786808631e-3, -9.46595344482036e-4, 3.66839497852761e-4,
            4.2523324806907e-5, -2.0278578112534e-5, -1.624290004647e-6,
            1.303655835580e-6, 1.5626441722e-8, -8.5238095915e-8,
            6.529054439e-9, 5.059343495e-9, -9.91364156e-10, -2.27365122e-10, 9.6467911e-11, 2.394038e-12, -6.886027e-12, 8.94487e-13, 3.13092e-13, -1.12708e-13, 3.81e-16, 7.106e-15, -1.523e-15, -9.4e-17, 1.21e-16, -2.8e-17
        );
        $j = count($cof) - 1;
        $isneg = false;
        $d = 0;
        $dd = 0;
        $t = null;
        $ty = null;
        $tmp = null;
        $res = null;

        if ($x < 0) {
            $x = -$x;
            $isneg = true;
        }

        $t = 2 / (2 + $x);
        $ty = 4 * $t - 2;

        for (; $j > 0; $j--) {
            $tmp = $d;
            $d = $ty * $d - $dd + $cof[$j];
            $dd = $tmp;
        }

        $res = $t * exp(-$x * $x + 0.5 * ($cof[0] + $ty * $d) - $dd);
        return $isneg ? $res - 1 : 1 - $res;
    }

    public function normalDistribution($x, $mean, $std)
    {
        if ($this->isZero($std)) {
            return 0;
        }

        return 0.5 * (1 + $this->erf(($x - $mean) / sqrt(2 * $std * $std)));
    }

    public function getPvalue($z)
    {
        // factor to correct two sided p-value
        $f = 2;
        $p = $this->normalDistribution($z, 0, 1) * $f;

        return $p;
    }

    public function getSignificanceRate($samples1, $samples2)
    {
        $u = $this->getU($samples1, $samples2);
        $z = $this->getZscore($u, $samples1, $samples2);
        $p = $this->getPvalue($z);
        $t = abs(1 - abs($p));

        return round($t, static::PRECISION) * 100;
    }

    public function getU($samples1, $samples2)
    {
        if (!is_array($samples1)) {
            throw new \Exception('$samples1 must be an array');
        }

        if (!is_array($samples2)) {
            throw new \Exception('$samples2 must be an array');
        }

        if (empty($samples1)) {
            throw new \Exception('$samples1 cannot be empty');
        }

        if (empty($samples2)) {
            throw new \Exception('$samples1 cannot be empty');
        }

        // Rank the entire list of observations
        $unranked = $this->getUnrankedValues($samples1, $samples2);
        $ranked = $this->rank($unranked);

        // Compute the rank of each sample
        $ranks = array(
            $this->getRankForSample($ranked, $samples1),
            $this->getRankForSample($ranked, $samples2)
        );

        // Compute the U values
        $us = array(
            $this->uValue($ranks[0], $samples1),
            $this->uValue($ranks[1], $samples2)
        );
        
        // An optimisation is to use a property of the U test
        // to calculate the U value of sample 1 based on the value
        // of sample 0
        // $u[1] = (samples[0].length * samples[1].length) - u[0];

        // Return the array of U values
        return $us;
    }

    private function mergeSamples($samples1, $samples2)
    {
        $all = $samples1;
        foreach ($samples2 as $value => $count) {
            if (!isset($all[$value])) {
                $all[$value] = $count;
            } else {
                $all[$value] += $count;
            }
        }

        return $all;
    }

    public function getUnrankedValues($samples1, $samples2)
    {
        $all = $this->mergeSamples($samples1, $samples2);

        $unranked = array_map(function ($count, $val) {
            return array('val' => $val, 'count' => $count);
        }, $all, array_keys($all));

        return $unranked;
    }
}
