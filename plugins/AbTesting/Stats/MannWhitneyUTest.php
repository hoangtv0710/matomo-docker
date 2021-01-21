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

// see https://github.com/lukem512/mann-whitney-utest/blob/master/src/mann-whitney.js
// WE ARE ACTUALLY NOT USING THIS ONE AS WE ARE USING A HISTOGRAM INSTEAD OF RAW VALUES
// LEAVING THE CODE HERE TO COMPARE WHEN NEEDED ETC
class MannWhitneyUTest
{
    const PRECISION = 6;

    /**
     * @internal for tests
     */
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

    /**
     * @internal for tests
     */
    public function getSumOfRanksForSamples($samples1, $samples2, $useFirst = true)
    {
        $unranked = $this->getUnrankedValues($samples1, $samples2);
        $ranked = $this->rank($unranked);

        if ($useFirst) {
            return $this->sampleRank($ranked, $samples1);
        }

        return $this->sampleRank($ranked, $samples2);
    }

    public function rank($list) {
    
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

        // add the rank to the objects
        $list = array_map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        }, $list, array_keys($list));

        // use median values for groups with the same rank
        for ($i = 0; $i < count($list); /* nothing */ ) {
            $count = 1;
            $total = $list[$i]['rank'];

            for ($j = 0; isset($list[$i + $j + 1]) && ($list[$i + $j]['val'] === $list[$i + $j + 1]['val']); $j++) {
                $total += $list[$i + $j + 1]['rank'];
                $count++;
            }

            $rank = ($total / $count);

            for ($k = 0; $k < $count; $k++) {
                $list[$i + $k]['rank'] = $rank;
            }

            $i = $i + $count;
        }
    
        return $list;
    }
    
    // Compute the rank of a sample, given a ranked
    // list and a list of observations for that sample.
    private function sampleRank($rankedList, $observations)
    {
        // Compute the rank
        $rank = 0;
        foreach ($rankedList as $observation) {
            $index = array_search($observation['val'], $observations);
            if ($index !== false) {
                // Add the rank to the sum
                $rank += $observation['rank'];

                // Remove the observation from the list
                array_splice($observations, $index, 1);
            }
        }
    
        return $rank;
    }
    
    // Compute the U value of a sample,
    // given the rank and the list of observations
    // for that sample.
    private function uValue($rank, $observations) {
        $k = count($observations);
        return $rank - (($k * ($k+1)) / 2);
    }
    
    // Check the U values are valid.
    // This utilises a property of the Mann-Whitney U test
    // that ensures the sum of the U values equals the product
    // of the number of observations.
    public function checkUisValid($u, $samples1, $samples2) {
        return ($u[0] + $u[1]) == (count($samples1) * count($samples2));
    }

    public function getStandardDeviation($sample1, $sample2)
    {
        $n = count($sample1) + count($sample2);

        // Count the ranks
        $counts = array();
        foreach (array($sample1, $sample2) as $samples) {
            foreach ($samples as $sample) {
                if (!isset($counts[$sample])) {
                    $counts[$sample] = 0;
                }
                $counts[$sample]++;
            }
        }

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

        $n1 = count($sample1);
        $n2 = count($sample2);

        // Compute standard deviation using correction for ties
        $stddev = sqrt((($n1 * $n2)/12) * (($n + 1) - $correction));

        return round($stddev, static::PRECISION);
    }

    public function getZscore($u, $sample1, $sample2) {
        $count1 = count($sample1);
        $count2 = count($sample2);

        $mu = ($count1 * $count2) / 2;
        $std = $this->getStandardDeviation($sample1, $sample2);

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

        $unranked = $this->getUnrankedValues($samples1, $samples2);
        $ranked = $this->rank($unranked);

        // Compute the rank of each sample
        $ranks = array(
            $this->sampleRank($ranked, $samples1),
            $this->sampleRank($ranked, $samples2)
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

    private function getUnrankedValues($samples1, $samples2)
    {
        // Rank the entire list of observations
        $all = array_merge($samples1, $samples2);

        $unranked = array_map(function ($val) {
            return array('val' => $val);
        }, $all);

        return $unranked;
    }
}
